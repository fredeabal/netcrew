<?php

namespace App\Libraries;

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;
use RuntimeException;
use Throwable;

/**
 * Servicio centralizado para gestionar redes P2P basadas en Nebula.
 *
 * Responsabilidades:
 * - Generar Autoridades Certificadoras (CA) por red mediante `nebula-cert`.
 * - Firmar certificados de dispositivos con su IP, grupos y expiración.
 * - Construir archivos `config.yml` listos para clientes (con hole-punching P2P y relay opcional).
 * - Generar configuración del nodo Lighthouse para el servidor.
 *
 * Puede ejecutar `nebula-cert` localmente o, si no está instalado en el servidor web,
 * delegar la ejecución al servidor remoto vía SSH2.
 */
class NebulaService
{
    public const DEFAULT_PORT = 4242;

    /**
     * Indica si Nebula tiene un host de Lighthouse configurado.
     */
    public static function isConfigured(): bool
    {
        $endpoint = self::resolveLighthouseEndpoint();
        return $endpoint['host'] !== '';
    }

    /**
     * Indica si la conexión SSH opcional de Nebula está configurada.
     */
    public static function isSshConfigured(): bool
    {
        $settings = service('settings');
        return !empty($settings->get('Nebula.sshHost')) && !empty($settings->get('Nebula.sshUser'));
    }

    /**
     * Crea una sesión SSH2 usando los ajustes de Nebula.
     */
    public static function sshFromSettings(int $timeout = 8): SSH2
    {
        $settings = service('settings');
        $host     = (string) $settings->get('Nebula.sshHost');
        $user     = (string) $settings->get('Nebula.sshUser');
        $port     = (int) ($settings->get('Nebula.sshPort') ?: 22);
        $authType = (string) ($settings->get('Nebula.sshAuthType') ?: 'password');

        if ($host === '' || $user === '') {
            throw new RuntimeException('La configuración SSH de Nebula está incompleta.');
        }

        if ($authType === 'key') {
            $encKey = (string) $settings->get('Nebula.sshPrivateKey');
            $cred   = $encKey !== '' ? self::decryptSetting($encKey) : '';
            if ($cred === '') {
                throw new RuntimeException('No se ha configurado ninguna llave privada SSH.');
            }
            $loginCred = PublicKeyLoader::load($cred);
        } else {
            $encPass   = (string) $settings->get('Nebula.sshPassword');
            $loginCred = $encPass !== '' ? self::decryptSetting($encPass) : '';
        }

        $ssh = new SSH2($host, $port, $timeout);
        if (!$ssh->login($user, $loginCred)) {
            throw new RuntimeException('Fallo de autenticación SSH.');
        }

        return $ssh;
    }

    protected static function decryptSetting(string $encrypted): string
    {
        try {
            return (string) \Config\Services::encrypter()->decrypt(base64_decode($encrypted));
        } catch (Throwable $e) {
            throw new RuntimeException('No se pudo desencriptar la credencial SSH guardada.', 0, $e);
        }
    }

    /**
     * Resuelve el host y puerto público del servidor Lighthouse.
     *
     * @return array{host: string, port: int}
     */
    public static function resolveLighthouseEndpoint(): array
    {
        $settings = service('settings');
        $host     = trim((string) $settings->get('Nebula.lighthouseHost'));
        $port     = (int) ($settings->get('Nebula.lighthousePort') ?: self::DEFAULT_PORT);

        if ($host === '') {
            $host = trim((string) $settings->get('Nebula.sshHost'));
        }

        if ($port < 1 || $port > 65535) {
            $port = self::DEFAULT_PORT;
        }

        return [
            'host' => $host,
            'port' => $port,
        ];
    }

    /**
     * Devuelve la IP VPN del Lighthouse dentro del rango de la red (primer host .1).
     */
    public static function getLighthouseVpnIp(string $cidr): string
    {
        $ipPart = explode('/', $cidr)[0];
        $octets = explode('.', $ipPart);
        if (count($octets) === 4) {
            $octets[3] = '1';
            return implode('.', $octets);
        }

        return '10.10.10.1';
    }

    /**
     * Localiza el binario `nebula-cert` en el sistema local.
     */
    public static function findLocalBinary(): ?string
    {
        $candidates = [
            '/usr/local/bin/nebula-cert',
            '/usr/bin/nebula-cert',
            '/bin/nebula-cert',
            '/usr/sbin/nebula-cert',
            '/sbin/nebula-cert',
            '/opt/homebrew/bin/nebula-cert',
        ];

        foreach ($candidates as $path) {
            if (is_file($path) && is_executable($path)) {
                return $path;
            }
        }

        if (function_exists('exec')) {
            $out = [];
            @exec('PATH=/usr/local/bin:/usr/bin:/bin:/opt/homebrew/bin command -v nebula-cert 2>/dev/null', $out, $code);
            $which = isset($out[0]) ? trim((string) $out[0]) : '';
            if ($code === 0 && $which !== '' && is_file($which) && is_executable($which)) {
                return $which;
            }
        }

        return null;
    }

    /**
     * Comprueba la disponibilidad de `nebula-cert` local y/o remotamente por SSH.
     *
     * @return array{local: bool, remote: bool, binaryPath: string|null, message: string}
     */
    public static function testAvailability(): array
    {
        $localBin = self::findLocalBinary();
        if ($localBin !== null) {
            $out = [];
            @exec(escapeshellarg($localBin) . ' -version 2>&1', $out);
            $version = trim(implode(' ', $out));
            return [
                'local'      => true,
                'remote'     => false,
                'binaryPath' => $localBin,
                'message'    => 'nebula-cert disponible localmente (' . ($version ?: $localBin) . ').',
            ];
        }

        if (self::isSshConfigured()) {
            try {
                $ssh = self::sshFromSettings(5);
                $out = trim((string) $ssh->exec('command -v nebula-cert || ls /usr/local/bin/nebula-cert 2>/dev/null'));
                if ($out !== '' && $ssh->getExitStatus() === 0) {
                    $ver = trim((string) $ssh->exec(escapeshellarg($out) . ' -version 2>&1'));
                    return [
                        'local'      => false,
                        'remote'     => true,
                        'binaryPath' => $out,
                        'message'    => 'nebula-cert disponible vía SSH (' . ($ver ?: $out) . ').',
                    ];
                }
            } catch (Throwable $e) {
                return [
                    'local'      => false,
                    'remote'     => false,
                    'binaryPath' => null,
                    'message'    => 'No se encontró nebula-cert localmente y falló la comprobación SSH: ' . $e->getMessage(),
                ];
            }
        }

        return [
            'local'      => false,
            'remote'     => false,
            'binaryPath' => null,
            'message'    => 'No se encontró el binario nebula-cert ni en el servidor local ni vía SSH. Instálalo (ej. apt install nebula / brew install nebula) para generar certificados.',
        ];
    }

    /**
     * Genera un par de certificados CA (ca.crt y ca.key) para una red Nebula.
     *
     * @return array{crt: string, key: string}
     */
    public static function createCa(string $networkName, int $durationDays = 3650): array
    {
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $networkName) ?: 'NetCrew';
        $hours    = max(24, $durationDays * 24) . 'h';

        $localBin = self::findLocalBinary();
        if ($localBin !== null) {
            $tmpDir = sys_get_temp_dir() . '/nc_nebula_ca_' . bin2hex(random_bytes(8));
            if (!mkdir($tmpDir, 0700, true) && !is_dir($tmpDir)) {
                throw new RuntimeException('No se pudo crear el directorio temporal para la CA de Nebula.');
            }

            try {
                $crtPath = $tmpDir . '/ca.crt';
                $keyPath = $tmpDir . '/ca.key';
                $cmd     = sprintf(
                    '%s ca -name %s -duration %s -out-crt %s -out-key %s 2>&1',
                    escapeshellarg($localBin),
                    escapeshellarg($safeName),
                    escapeshellarg($hours),
                    escapeshellarg($crtPath),
                    escapeshellarg($keyPath)
                );

                exec($cmd, $output, $code);
                if ($code !== 0 || !is_file($crtPath) || !is_file($keyPath)) {
                    throw new RuntimeException('Error al generar la CA de Nebula: ' . implode("\n", $output));
                }

                return [
                    'crt' => (string) file_get_contents($crtPath),
                    'key' => (string) file_get_contents($keyPath),
                ];
            } finally {
                @unlink($tmpDir . '/ca.crt');
                @unlink($tmpDir . '/ca.key');
                @rmdir($tmpDir);
            }
        }

        if (self::isSshConfigured()) {
            $ssh       = self::sshFromSettings(10);
            $remoteBin = trim((string) $ssh->exec('command -v nebula-cert || echo ""'));
            if ($remoteBin === '') {
                throw new RuntimeException('No se encontró nebula-cert ni localmente ni en el servidor SSH.');
            }

            $remoteDir = '/tmp/nc_nebula_ca_' . bin2hex(random_bytes(8));
            try {
                $cmd = sprintf(
                    'mkdir -p %s && chmod 700 %s && %s ca -name %s -duration %s -out-crt %s/ca.crt -out-key %s/ca.key && cat %s/ca.crt && echo "---NC_SPLIT---" && cat %s/ca.key',
                    escapeshellarg($remoteDir),
                    escapeshellarg($remoteDir),
                    escapeshellarg($remoteBin),
                    escapeshellarg($safeName),
                    escapeshellarg($hours),
                    escapeshellarg($remoteDir),
                    escapeshellarg($remoteDir),
                    escapeshellarg($remoteDir),
                    escapeshellarg($remoteDir)
                );
                $out = (string) $ssh->exec($cmd);
                if ($ssh->getExitStatus() !== 0 || strpos($out, '---NC_SPLIT---') === false) {
                    throw new RuntimeException('Error generando la CA de Nebula por SSH: ' . $out);
                }
                [$crt, $key] = explode('---NC_SPLIT---', $out, 2);
                return [
                    'crt' => trim($crt) . "\n",
                    'key' => trim($key) . "\n",
                ];
            } finally {
                $ssh->exec('rm -rf ' . escapeshellarg($remoteDir));
            }
        }

        throw new RuntimeException('El binario nebula-cert no está instalado en este servidor ni configurado vía SSH.');
    }

    /**
     * Firma un certificado de dispositivo/nodo con la CA de la red.
     *
     * @return array{crt: string, key: string, fingerprint: string}
     */
    public static function signDeviceCert(
        string $caCrt,
        string $caKey,
        string $deviceName,
        string $ipCidr,
        string $groups = 'devices',
        ?string $expiresAt = null
    ): array {
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '-', $deviceName) ?: 'node';

        $durationHours = 43800;
        if (!empty($expiresAt)) {
            $expiresTs = strtotime($expiresAt);
            if ($expiresTs === false || $expiresTs <= time()) {
                throw new RuntimeException('La fecha de expiración del dispositivo ya ha vencido.');
            }
            $diffSeconds   = max(3600, $expiresTs - time());
            $durationHours = (int) ceil($diffSeconds / 3600);
        }
        $duration = $durationHours . 'h';

        $cleanGroups = array_filter(array_map(static function ($g) {
            return preg_replace('/[^a-zA-Z0-9_-]/', '', trim($g));
        }, explode(',', $groups)));
        $groupsArg = !empty($cleanGroups) ? implode(',', $cleanGroups) : 'devices';

        $localBin = self::findLocalBinary();
        if ($localBin !== null) {
            $tmpDir = sys_get_temp_dir() . '/nc_nebula_sign_' . bin2hex(random_bytes(8));
            if (!mkdir($tmpDir, 0700, true) && !is_dir($tmpDir)) {
                throw new RuntimeException('No se pudo crear directorio temporal para firmar el certificado Nebula.');
            }

            try {
                $caCrtPath = $tmpDir . '/ca.crt';
                $caKeyPath = $tmpDir . '/ca.key';
                $crtPath   = $tmpDir . '/node.crt';
                $keyPath   = $tmpDir . '/node.key';

                file_put_contents($caCrtPath, $caCrt);
                file_put_contents($caKeyPath, $caKey);
                chmod($caKeyPath, 0600);

                $cmd = sprintf(
                    '%s sign -ca-crt %s -ca-key %s -name %s -ip %s -groups %s -duration %s -out-crt %s -out-key %s 2>&1',
                    escapeshellarg($localBin),
                    escapeshellarg($caCrtPath),
                    escapeshellarg($caKeyPath),
                    escapeshellarg($safeName),
                    escapeshellarg($ipCidr),
                    escapeshellarg($groupsArg),
                    escapeshellarg($duration),
                    escapeshellarg($crtPath),
                    escapeshellarg($keyPath)
                );

                exec($cmd, $output, $code);
                if ($code !== 0 || !is_file($crtPath) || !is_file($keyPath)) {
                    throw new RuntimeException('Error al firmar certificado Nebula: ' . implode("\n", $output));
                }

                $crt = (string) file_get_contents($crtPath);
                $key = (string) file_get_contents($keyPath);

                return [
                    'crt'         => $crt,
                    'key'         => $key,
                    'fingerprint' => 'nebula-' . hash('sha256', $crt),
                ];
            } finally {
                @unlink($tmpDir . '/ca.crt');
                @unlink($tmpDir . '/ca.key');
                @unlink($tmpDir . '/node.crt');
                @unlink($tmpDir . '/node.key');
                @rmdir($tmpDir);
            }
        }

        if (self::isSshConfigured()) {
            $ssh       = self::sshFromSettings(10);
            $remoteBin = trim((string) $ssh->exec('command -v nebula-cert || echo ""'));
            if ($remoteBin === '') {
                throw new RuntimeException('No se encontró nebula-cert ni localmente ni en el servidor SSH.');
            }

            $remoteDir = '/tmp/nc_nebula_sign_' . bin2hex(random_bytes(8));
            try {
                $caCrtB64 = base64_encode($caCrt);
                $caKeyB64 = base64_encode($caKey);

                $cmd = sprintf(
                    'mkdir -p %s && chmod 700 %s && echo %s | base64 -d > %s/ca.crt && echo %s | base64 -d > %s/ca.key && chmod 600 %s/ca.key && %s sign -ca-crt %s/ca.crt -ca-key %s/ca.key -name %s -ip %s -groups %s -duration %s -out-crt %s/node.crt -out-key %s/node.key && cat %s/node.crt && echo "---NC_SPLIT---" && cat %s/node.key',
                    escapeshellarg($remoteDir),
                    escapeshellarg($remoteDir),
                    escapeshellarg($caCrtB64),
                    escapeshellarg($remoteDir),
                    escapeshellarg($caKeyB64),
                    escapeshellarg($remoteDir),
                    escapeshellarg($remoteDir),
                    escapeshellarg($remoteBin),
                    escapeshellarg($remoteDir),
                    escapeshellarg($remoteDir),
                    escapeshellarg($safeName),
                    escapeshellarg($ipCidr),
                    escapeshellarg($groupsArg),
                    escapeshellarg($duration),
                    escapeshellarg($remoteDir),
                    escapeshellarg($remoteDir),
                    escapeshellarg($remoteDir),
                    escapeshellarg($remoteDir)
                );

                $out = (string) $ssh->exec($cmd);
                if ($ssh->getExitStatus() !== 0 || strpos($out, '---NC_SPLIT---') === false) {
                    throw new RuntimeException('Error firmando certificado Nebula por SSH: ' . $out);
                }

                [$crt, $key] = explode('---NC_SPLIT---', $out, 2);
                $crt = trim($crt) . "\n";
                $key = trim($key) . "\n";

                return [
                    'crt'         => $crt,
                    'key'         => $key,
                    'fingerprint' => 'nebula-' . hash('sha256', $crt),
                ];
            } finally {
                $ssh->exec('rm -rf ' . escapeshellarg($remoteDir));
            }
        }

        throw new RuntimeException('El binario nebula-cert no está instalado en este servidor ni configurado vía SSH.');
    }

    /**
     * Construye el archivo `config.yml` de Nebula para un dispositivo cliente (modo P2P Mesh).
     */
    public static function buildClientConfig(object $network, object $device, string $decryptedCrt, string $decryptedKey): string
    {
        $settings        = service('settings');
        $endpoint        = self::resolveLighthouseEndpoint();
        $lighthouseHost  = $endpoint['host'] !== '' ? $endpoint['host'] : 'YOUR_LIGHTHOUSE_PUBLIC_IP';
        $lighthousePort  = $endpoint['port'];
        $lighthouseVpnIp = self::getLighthouseVpnIp($network->cidr);
        $relayEnabled    = (bool) ($settings->get('Nebula.relayEnabled') ?? true);
        $cipher          = (string) ($settings->get('Nebula.cipher') ?: 'aes');
        if (!in_array($cipher, ['aes', 'chacha20'], true)) {
            $cipher = 'aes';
        }

        $caBlock  = self::indentPem((string) $network->nebula_ca_crt, 4);
        $crtBlock = self::indentPem($decryptedCrt, 4);
        $keyBlock = self::indentPem($decryptedKey, 4);

        $safeNetworkName = self::sanitizeYamlComment((string) $network->name);
        $safeDeviceName  = self::sanitizeYamlComment((string) $device->name);
        $safeHost        = self::sanitizeYamlScalar($lighthouseHost);

        $relayConfig = $relayEnabled
            ? "relay:\n  relays:\n    - {$lighthouseVpnIp}\n  am_relay: false\n  use_relays: true\n"
            : "relay:\n  am_relay: false\n  use_relays: false\n";

        return <<<YAML
# Configuración Nebula P2P Mesh generada por NetCrew
# Red: {$safeNetworkName} ({$network->cidr})
# Nodo: {$safeDeviceName} ({$device->ip_address})

pki:
  ca: |
{$caBlock}
  cert: |
{$crtBlock}
  key: |
{$keyBlock}

static_host_map:
  "{$lighthouseVpnIp}": ["{$safeHost}:{$lighthousePort}"]

lighthouse:
  am_lighthouse: false
  interval: 60
  hosts:
    - "{$lighthouseVpnIp}"

listen:
  host: 0.0.0.0
  port: 0

punchy:
  punch: true
  respond: true

{$relayConfig}
tun:
  disabled: false
  drop_local_broadcast: false
  drop_multicast: false
  mtu: 1300

cipher: {$cipher}

logging:
  level: info
  format: text

firewall:
  outbound_action: drop
  inbound_action: drop
  outbound:
    - port: any
      proto: any
      host: any
  inbound:
    - port: any
      proto: icmp
      host: any
    - port: any
      proto: any
      group: devices

YAML;
    }

    /**
     * Construye el `config.yml` para el servidor Lighthouse de una red Nebula.
     */
    /**
     * Obtiene la lista de fingerprints revocados (pki.blocklist) para una red.
     *
     * @return string[]
     */
    public static function getBlocklist(int $networkId): array
    {
        $raw = service('settings')->get('Nebula.blocklist_' . $networkId);
        if (! is_array($raw)) {
            return [];
        }

        $clean = [];
        foreach ($raw as $fp) {
            if (! is_string($fp)) {
                continue;
            }
            $fp = strtolower(trim($fp));
            if ($fp !== '' && preg_match('/^[a-f0-9]{64}$/', $fp) === 1) {
                $clean[$fp] = true;
            }
        }

        return array_keys($clean);
    }

    /**
     * Agrega un fingerprint de certificado Nebula a la blocklist persistida de la red.
     */
    public static function revokeFingerprint(int $networkId, string $fingerprint): void
    {
        $fp = strtolower(trim($fingerprint));
        if ($networkId <= 0 || $fp === '' || preg_match('/^[a-f0-9]{64}$/', $fp) !== 1) {
            return;
        }

        $list = self::getBlocklist($networkId);
        if (! in_array($fp, $list, true)) {
            $list[] = $fp;
            service('settings')->set('Nebula.blocklist_' . $networkId, $list);
        }
    }

    /**
     * Genera el YAML completo para el servidor Lighthouse de una red.
     *
     * @param string[] $blocklist
     */
    public static function buildLighthouseConfig(object $network, string $lighthouseCrt, string $lighthouseKey, array $blocklist = []): string
    {
        $settings     = service('settings');
        $endpoint     = self::resolveLighthouseEndpoint();
        $port         = $endpoint['port'];
        $relayEnabled = (bool) ($settings->get('Nebula.relayEnabled') ?? true);
        $cipher       = (string) ($settings->get('Nebula.cipher') ?: 'aes');
        if (!in_array($cipher, ['aes', 'chacha20'], true)) {
            $cipher = 'aes';
        }

        $caBlock         = self::indentPem((string) $network->nebula_ca_crt, 4);
        $crtBlock        = self::indentPem($lighthouseCrt, 4);
        $keyBlock        = self::indentPem($lighthouseKey, 4);
        $amRelay         = $relayEnabled ? 'true' : 'false';
        $safeNetworkName = self::sanitizeYamlComment((string) $network->name);

        $blocklistYaml = '';
        $validBlocklist = [];
        foreach ($blocklist as $fp) {
            if (is_string($fp)) {
                $fp = strtolower(trim($fp));
                if ($fp !== '' && preg_match('/^[a-f0-9]{64}$/', $fp) === 1) {
                    $validBlocklist[$fp] = true;
                }
            }
        }
        if ($validBlocklist !== []) {
            $blocklistYaml = "\n  blocklist:";
            foreach (array_keys($validBlocklist) as $fp) {
                $blocklistYaml .= "\n    - \"{$fp}\"";
            }
        }

        return <<<YAML
# Configuración de Servidor Lighthouse Nebula generada por NetCrew
# Red: {$safeNetworkName} ({$network->cidr})

pki:
  ca: |
{$caBlock}
  cert: |
{$crtBlock}
  key: |
{$keyBlock}{$blocklistYaml}

static_host_map: {}

lighthouse:
  am_lighthouse: true

listen:
  host: 0.0.0.0
  port: {$port}

punchy:
  punch: true
  respond: true

relay:
  am_relay: {$amRelay}
  use_relays: false

tun:
  disabled: false
  dev: nebula1
  mtu: 1300

cipher: {$cipher}

logging:
  level: info
  format: text

firewall:
  outbound_action: drop
  inbound_action: drop
  outbound:
    - port: any
      proto: any
      host: any
  inbound:
    - port: any
      proto: icmp
      host: any
    - port: any
      proto: any
      group: devices

YAML;
    }

    /**
     * Lee la telemetría de handshakes recientes del Lighthouse (journalctl -u nebula).
     * Devuelve un mapa indexado por vpnIp y fingerprint.
     *
     * @return array{by_ip: array<string, array>, by_fp: array<string, array>}
     */
    public static function getLighthousePeerTelemetry(): array
    {
        $byIp = [];
        $byFp = [];
        $raw  = '';

        if (is_executable('/usr/bin/journalctl') || is_executable('/bin/journalctl')) {
            @exec('journalctl -u nebula -n 1000 --no-pager 2>/dev/null', $lines, $code);
            if ($code === 0 && ! empty($lines)) {
                $raw = implode("\n", $lines);
            }
        }

        if ($raw === '' && self::isSshConfigured()) {
            try {
                $ssh = self::sshFromSettings(6);
                $raw = (string) $ssh->exec('journalctl -u nebula -n 1000 --no-pager 2>/dev/null');
            } catch (\Throwable $e) {
                log_message('warning', 'No se pudo leer telemetría Nebula por SSH: ' . $e->getMessage());
            }
        }

        if ($raw === '') {
            return ['by_ip' => [], 'by_fp' => []];
        }

        $now    = time();
        $closed = ['by_ip' => [], 'by_fp' => []];

        foreach (preg_split("/\r\n|\n|\r/", $raw) as $line) {
            if (! preg_match('/time="([^"]+)"/', $line, $mTime)) {
                continue;
            }
            $ts = strtotime($mTime[1]);
            if (! $ts) {
                continue;
            }

            $vpnIp = preg_match('/vpnIp=([0-9.]+)/', $line, $mIp) ? $mIp[1] : '';
            $fp    = preg_match('/fingerprint=([a-f0-9]+)/i', $line, $mFp) ? strtolower($mFp[1]) : '';

            if (stripos($line, 'Close tunnel') !== false || stripos($line, 'Closing tunnel') !== false || stripos($line, 'Goodbye') !== false) {
                if ($vpnIp !== '' && $ts >= ($closed['by_ip'][$vpnIp] ?? 0)) {
                    $closed['by_ip'][$vpnIp] = $ts;
                }
                if ($fp !== '' && $ts >= ($closed['by_fp'][$fp] ?? 0)) {
                    $closed['by_fp'][$fp] = $ts;
                }
                continue;
            }

            if (strpos($line, 'Handshake message') === false) {
                continue;
            }

            $addr = preg_match('/udpAddr="([^"]+)"/', $line, $mAddr) ? $mAddr[1] : '';

            $publicIp = '';
            $port     = '';
            if ($addr !== '' && strpos($addr, ':') !== false) {
                $lastColon = strrpos($addr, ':');
                $publicIp  = substr($addr, 0, $lastColon);
                $port      = substr($addr, $lastColon + 1);
            }

            // Sin sondeo ICMP activo (tun deshabilitado), solo consideramos online handshakes recientes (5 min);
            // si un equipo se apaga o suspende sin enviar Close/Goodbye, no debemos mantenerlo 24h como conectado.
            $entry = [
                'connected'        => ($now - $ts) <= 300,
                'endpoint'         => $addr,
                'public_ip'        => $publicIp,
                'port'             => $port,
                'latest_handshake' => $ts,
                'rx_bytes'         => 0,
                'tx_bytes'         => 0,
                'remote_time'      => $now,
            ];

            if ($vpnIp !== '' && (! isset($byIp[$vpnIp]) || $ts >= $byIp[$vpnIp]['latest_handshake'])) {
                $byIp[$vpnIp] = $entry;
            }
            if ($fp !== '' && (! isset($byFp[$fp]) || $ts >= $byFp[$fp]['latest_handshake'])) {
                $byFp[$fp] = $entry;
            }
        }

        foreach ($byIp as $ip => &$entry) {
            if (($closed['by_ip'][$ip] ?? 0) >= $entry['latest_handshake']) {
                $entry['connected'] = false;
            }
        }
        unset($entry);

        foreach ($byFp as $fp => &$entry) {
            if (($closed['by_fp'][$fp] ?? 0) >= $entry['latest_handshake']) {
                $entry['connected'] = false;
            }
        }
        unset($entry);

        return ['by_ip' => $byIp, 'by_fp' => $byFp];
    }

    /**
     * Comprueba en tiempo real qué IPs VPN responden a ICMP desde la interfaz nebula1
     * (local o vía SSH en el Lighthouse) o desde una interfaz utun local en macOS.
     * Devuelve un mapa [ip => true] o null si no hay interfaz tun disponible.
     *
     * @param string[] $ips
     * @return array<string, bool>|null
     */
    public static function probeReachableIps(array $ips): ?array
    {
        $validIps = [];
        foreach ($ips as $ip) {
            $ip = trim((string) $ip);
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $validIps[] = $ip;
            }
        }
        $validIps = array_values(array_unique($validIps));
        if (empty($validIps)) {
            return [];
        }

        $escapedList = implode(' ', array_map('escapeshellarg', $validIps));
        $linuxProbe  = 'sh -c ' . escapeshellarg("for ip in {$escapedList}; do (ping -c 2 -i 0.2 -W 2 \"\$ip\" >/dev/null 2>&1 && echo \"\$ip\") & done; wait");
        $hasTunCmd   = 'test -d /sys/class/net/nebula1 || PATH="$PATH:/sbin:/usr/sbin" ip link show nebula1 >/dev/null 2>&1';

        @exec($hasTunCmd, $tunOut, $tunCode);
        if ($tunCode === 0) {
            @exec($linuxProbe, $out);
            return self::parseReachableIpLines($out);
        }

        if (self::isSshConfigured()) {
            try {
                $ssh = self::sshFromSettings(6);
                $hasTun = trim((string) $ssh->exec($hasTunCmd . ' && echo UP || echo DOWN'));
                if ($hasTun === 'UP') {
                    $raw = (string) $ssh->exec($linuxProbe);
                    return self::parseReachableIpLines(preg_split("/\r\n|\n|\r/", $raw) ?: []);
                }
            } catch (\Throwable $e) {
                log_message('warning', 'No se pudo sondear IPs Nebula por SSH: ' . $e->getMessage());
            }
        }

        // En macOS (entorno local conectado al mesh Nebula), sondear vía utun si comparte subred /24
        if (PHP_OS_FAMILY === 'Darwin' && self::hasLocalDarwinNebulaSubnet($validIps)) {
            $darwinProbe = 'sh -c ' . escapeshellarg("for ip in {$escapedList}; do (ping -c 2 -i 0.2 -W 1500 \"\$ip\" >/dev/null 2>&1 && echo \"\$ip\") & done; wait");
            @exec($darwinProbe, $out);
            return self::parseReachableIpLines($out);
        }

        return null;
    }

    /**
     * Extrae IPs IPv4 válidas de las líneas devueltas por el sondeo paralelo.
     *
     * @param string[] $lines
     * @return array<string, bool>
     */
    protected static function parseReachableIpLines(array $lines): array
    {
        $reachable = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if (filter_var($line, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $reachable[$line] = true;
            }
        }
        return $reachable;
    }

    /**
     * Comprueba si el host macOS local tiene una interfaz utun activa en la misma subred /24.
     *
     * @param string[] $ips
     */
    protected static function hasLocalDarwinNebulaSubnet(array $ips): bool
    {
        $prefixes = [];
        foreach ($ips as $ip) {
            $parts = explode('.', $ip);
            if (count($parts) === 4) {
                $prefixes[$parts[0] . '.' . $parts[1] . '.' . $parts[2]] = true;
            }
        }
        if (empty($prefixes)) {
            return false;
        }

        @exec('ifconfig 2>/dev/null', $lines, $code);
        if ($code !== 0 || empty($lines)) {
            return false;
        }

        $inUtun = false;
        foreach ($lines as $line) {
            if (preg_match('/^([a-z0-9]+):/i', $line, $m)) {
                $inUtun = str_starts_with(strtolower($m[1]), 'utun');
                continue;
            }
            if ($inUtun && preg_match('/^\s+inet\s+([0-9.]+)/', $line, $m)) {
                $parts = explode('.', $m[1]);
                if (count($parts) === 4 && isset($prefixes[$parts[0] . '.' . $parts[1] . '.' . $parts[2]])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Comprueba si el servicio nebula está corriendo en el servidor local.
     */
    public static function isLighthouseRunning(): bool
    {
        @exec('systemctl is-active --quiet nebula 2>/dev/null', $out, $code);
        return $code === 0;
    }

    /**
     * Sincroniza automáticamente la configuración del Lighthouse local (/etc/nebula/config.yml)
     * y reinicia el servicio systemd nebula si el servidor está preparado.
     */
    public static function syncLocalLighthouse(object $network, string $caKey): bool
    {
        if (empty($network->nebula_ca_crt) || $caKey === '' || empty($network->cidr)) {
            return false;
        }

        try {
            $lighthouseIp = self::getLighthouseVpnIp($network->cidr) . '/24';
            $cert         = self::signDeviceCert(
                $network->nebula_ca_crt,
                $caKey,
                'lighthouse-' . $network->id,
                $lighthouseIp,
                'lighthouse,devices'
            );
            $yaml = self::buildLighthouseConfig(
                $network,
                $cert['crt'],
                $cert['key'],
                self::getBlocklist((int) $network->id)
            );
        } catch (\Throwable $e) {
            log_message('warning', 'No se pudo generar config Lighthouse local: ' . $e->getMessage());
            return false;
        }

        $isRoot = function_exists('posix_geteuid') && posix_geteuid() === 0;
        if ($isRoot) {
            if (! is_dir('/etc/nebula')) {
                @mkdir('/etc/nebula', 0700, true);
            }
            $tunOk = false;
            $fh    = @fopen('/dev/net/tun', 'r+');
            if (is_resource($fh)) {
                $tunOk = true;
                fclose($fh);
            }
            if (! $tunOk) {
                $yaml = preg_replace('/(^tun:\R\s+disabled:\s*)false/m', '$1true', $yaml) ?? $yaml;
            }
            if (@file_put_contents('/etc/nebula/config.yml', $yaml) === false) {
                return false;
            }
            @chmod('/etc/nebula/config.yml', 0600);
            @exec('systemctl enable nebula >/dev/null 2>&1 || true');
            @exec('systemctl restart nebula >/dev/null 2>&1', $out, $code);
            return $code === 0;
        }

        $helper = '/usr/local/sbin/netcrew-lighthouse';
        if (! is_file($helper)) {
            return false;
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = @proc_open('sudo -n ' . escapeshellarg($helper), $descriptors, $pipes);
        if (! is_resource($proc)) {
            return false;
        }

        fwrite($pipes[0], $yaml);
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($proc);

        if ($exit !== 0) {
            log_message('warning', 'netcrew-lighthouse falló (exit ' . $exit . '): ' . trim((string) $err));
            return false;
        }

        return true;
    }

    /**
     * Indenta un certificado o llave PEM para insertarlo limpiamente en un bloque YAML literal `|`.
     */
    protected static function indentPem(string $pem, int $spaces = 4): string
    {
        $pad   = str_repeat(' ', $spaces);
        $lines = preg_split("/\r\n|\n|\r/", trim($pem));
        return implode("\n", array_map(static fn ($line) => $pad . $line, $lines));
    }

    /**
     * Elimina saltos de línea y caracteres de control para evitar inyección YAML en comentarios.
     */
    protected static function sanitizeYamlComment(string $value): string
    {
        return trim((string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value));
    }

    /**
     * Sanitiza escalares interpolados entre comillas dobles en YAML.
     */
    protected static function sanitizeYamlScalar(string $value): string
    {
        return trim((string) preg_replace('/[\x00-\x1F\x7F"\'\\\\]+/', '', $value));
    }
}
