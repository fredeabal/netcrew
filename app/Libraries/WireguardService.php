<?php

namespace App\Libraries;

use App\Models\DeviceModel;
use App\Models\NetworkModel;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;
use RuntimeException;
use Throwable;

/**
 * Servicio centralizado para gestionar el servidor WireGuard vía SSH.
 *
 * Es la única fuente de verdad para:
 * - Sesión SSH y elevación con sudo.
 * - Alta / baja / reconciliación de peers.
 * - Reglas de firewall (iptables) y direcciones del servidor en la interfaz.
 *
 * Todos los métodos "run*" verifican el código de salida del comando remoto
 * y lanzan RuntimeException si falla, para que los controladores puedan
 * revertir cambios en la BD y mantener ambos lados sincronizados.
 */
class WireguardService
{
    // Subred por defecto cuando no hay redes válidas configuradas
    public const FALLBACK_CIDR = '10.10.10.0/24';

    // Puerto de escucha del servidor WireGuard
    public const LISTEN_PORT = 51820;

    protected SSH2 $ssh;
    protected string $user;
    protected string $authType;
    protected ?string $password;
    protected string $interface;

    protected function __construct(SSH2 $ssh, string $user, string $authType, ?string $password)
    {
        $this->ssh       = $ssh;
        $this->user      = $user;
        $this->authType  = $authType;
        $this->password  = $password;
        $this->interface = (string) (service('settings')->get('WireGuard.interface') ?: 'wg0');
    }

    // ---------------------------------------------------------------------
    // Conexión
    // ---------------------------------------------------------------------

    /**
     * Indica si la conexión SSH global está configurada (host y usuario).
     */
    public static function isConfigured(): bool
    {
        $settings = service('settings');

        return ! empty($settings->get('WireGuard.sshHost')) && ! empty($settings->get('WireGuard.sshUser'));
    }

    /**
     * Crea una sesión usando la configuración global guardada en la BD.
     */
    public static function fromSettings(int $timeout = 5): self
    {
        $settings = service('settings');
        $host     = (string) $settings->get('WireGuard.sshHost');
        $user     = (string) $settings->get('WireGuard.sshUser');
        $port     = (int) ($settings->get('WireGuard.sshPort') ?: 22);
        $authType = (string) ($settings->get('WireGuard.sshAuthType') ?: 'password');

        if ($host === '' || $user === '') {
            throw new RuntimeException('La configuración del servidor SSH o el usuario está incompleta.');
        }

        if ($authType === 'key') {
            $credential = self::decryptSetting('WireGuard.sshPrivateKey');
            if (empty($credential)) {
                throw new RuntimeException('No se ha configurado ninguna llave privada SSH.');
            }
        } else {
            $credential = self::decryptSetting('WireGuard.sshPassword') ?? '';
        }

        return self::fromParams($host, $user, $port, $authType, $credential, $timeout);
    }

    /**
     * Crea una sesión con credenciales explícitas (sin leer ni escribir la BD).
     * Se usa para probar la conexión antes de guardar los ajustes.
     */
    public static function fromParams(string $host, string $user, int $port, string $authType, string $credential, int $timeout = 10): self
    {
        if ($host === '' || $user === '') {
            throw new RuntimeException('El host o el usuario SSH están vacíos.');
        }

        $loginCredential = $credential;

        if ($authType === 'key') {
            if ($credential === '') {
                throw new RuntimeException('No se ha proporcionado ninguna llave privada SSH.');
            }

            try {
                $loginCredential = PublicKeyLoader::load($credential);
            } catch (Throwable $e) {
                throw new RuntimeException('Error al procesar la llave privada SSH: ' . $e->getMessage(), 0, $e);
            }
        }

        try {
            $ssh      = new SSH2($host, $port, $timeout);
            $loggedIn = $ssh->login($user, $loginCredential);
        } catch (Throwable $e) {
            throw new RuntimeException('No se pudo conectar con el servidor SSH: ' . $e->getMessage(), 0, $e);
        }

        if (! $loggedIn) {
            throw new RuntimeException('Fallo de autenticación SSH. Verifica el usuario, la contraseña o la llave privada.');
        }

        return new self($ssh, $user, $authType, $authType === 'password' ? $credential : null);
    }

    /**
     * Desencripta un ajuste cifrado (contraseña o llave SSH).
     */
    protected static function decryptSetting(string $key): ?string
    {
        $value = service('settings')->get($key);
        if (empty($value)) {
            return null;
        }

        try {
            return \Config\Services::encrypter()->decrypt(base64_decode($value));
        } catch (Throwable $e) {
            throw new RuntimeException('Error al desencriptar la credencial SSH guardada.', 0, $e);
        }
    }

    // ---------------------------------------------------------------------
    // Ejecución de comandos
    // ---------------------------------------------------------------------

    public function getInterface(): string
    {
        return $this->interface;
    }

    /**
     * Ajusta el timeout de ejecución de comandos (segundos).
     */
    public function setTimeout(int $seconds): self
    {
        $this->ssh->setTimeout($seconds);

        return $this;
    }

    /**
     * Envuelve un comando con sudo si el usuario no es root.
     * Con llave SSH (o sin contraseña) se usa 'sudo -n' para fallar al instante
     * en lugar de quedarse colgado esperando una contraseña.
     */
    public function sudo(string $cmd): string
    {
        if ($this->user === 'root') {
            return $cmd;
        }

        if ($this->authType === 'key' || empty($this->password)) {
            return 'sudo -n ' . $cmd;
        }

        // printf '%s\n' evita que caracteres especiales sean interpretados por bash
        return "printf '%s\\n' " . escapeshellarg($this->password) . " | sudo -S -p '' " . $cmd;
    }

    /**
     * Ejecuta un comando SIN verificar el resultado. Devuelve la salida.
     */
    public function exec(string $cmd, bool $sudo = true): string
    {
        return (string) $this->ssh->exec($sudo ? $this->sudo($cmd) : $cmd);
    }

    /**
     * Código de salida del último comando ejecutado (false si el servidor no lo envió).
     */
    public function lastExitStatus(): int|false
    {
        return $this->ssh->getExitStatus();
    }

    /**
     * Ejecuta un comando y devuelve true si terminó con código 0.
     */
    public function succeeds(string $cmd, bool $sudo = true): bool
    {
        $this->exec($cmd, $sudo);

        return ! $this->ssh->isTimeout() && $this->ssh->getExitStatus() === 0;
    }

    /**
     * Ejecuta un comando verificando el resultado. Lanza excepción si falla.
     */
    public function run(string $cmd, bool $sudo = true): string
    {
        $output = $this->exec($cmd, $sudo);

        if ($this->ssh->isTimeout()) {
            throw new RuntimeException('Tiempo de espera agotado ejecutando un comando en el servidor WireGuard.');
        }

        $status = $this->ssh->getExitStatus();
        if ($status !== false && $status !== 0) {
            // No se incluye el comando en el mensaje porque puede contener la contraseña de sudo
            $detail = mb_substr(trim($output), 0, 300);
            throw new RuntimeException("El servidor WireGuard devolvió un error (código {$status})" . ($detail !== '' ? ": {$detail}" : '.'));
        }

        return $output;
    }

    /**
     * Ejecuta varios comandos en una sola sesión bash con 'set -e'.
     */
    public function runBatch(array $commands): string
    {
        if (empty($commands)) {
            return '';
        }

        return $this->run('bash -c ' . escapeshellarg('set -e; ' . implode('; ', $commands)));
    }

    /**
     * Escribe un archivo en el servidor de forma segura (contenido en base64, sin problemas de escapado).
     */
    protected function writeRemoteFile(string $path, string $content, string $mode = '600'): void
    {
        $script = 'umask 077 && echo ' . base64_encode($content) . ' | base64 -d > ' . escapeshellarg($path)
                . ' && chmod ' . $mode . ' ' . escapeshellarg($path);

        $this->run('bash -c ' . escapeshellarg($script));
    }

    // ---------------------------------------------------------------------
    // Peers
    // ---------------------------------------------------------------------

    protected function iface(): string
    {
        return escapeshellarg($this->interface);
    }

    public function addPeer(string $publicKey, string $ip): void
    {
        $this->run('wg set ' . $this->iface() . ' peer ' . escapeshellarg($publicKey) . ' allowed-ips ' . escapeshellarg($ip . '/32'));
    }

    public function removePeer(string $publicKey): void
    {
        $this->run('wg set ' . $this->iface() . ' peer ' . escapeshellarg($publicKey) . ' remove');
    }

    public function removePeers(array $publicKeys): void
    {
        $commands = [];
        foreach ($publicKeys as $key) {
            if (! empty($key)) {
                $commands[] = 'wg set ' . $this->iface() . ' peer ' . escapeshellarg($key) . ' remove';
            }
        }

        $this->runBatch($commands);
    }

    /**
     * Persiste el estado actual de la interfaz (peers y direcciones) en el .conf.
     */
    public function saveConfig(): void
    {
        $this->run('wg-quick save ' . $this->iface());
    }

    public function isInterfaceUp(): bool
    {
        return $this->succeeds('wg show ' . $this->iface());
    }

    /**
     * Obtiene el estado de todos los peers del servidor.
     *
     * @return array{remote_time:int, peers:array<string,array>}
     */
    public function dump(): array
    {
        $output = $this->run('bash -c ' . escapeshellarg('wg show ' . $this->iface() . ' dump && echo ---TIME--- && date +%s'));

        [$wgOutput, $time] = array_pad(explode('---TIME---', $output, 2), 2, '');
        $remoteTime = (int) trim($time) ?: time();

        $peers = [];
        foreach (preg_split('/\R/', trim($wgOutput)) as $line) {
            $parts = preg_split('/\s+/', trim($line));

            // Las líneas de peer tienen 8 columnas; la de la interfaz tiene 4
            if (count($parts) < 8 || ! self::isValidKey($parts[0])) {
                continue;
            }

            $handshake = (int) $parts[4];

            $peers[$parts[0]] = [
                'endpoint'         => $parts[2] !== '(none)' ? $parts[2] : null,
                'latest_handshake' => $handshake,
                'rx_bytes'         => (int) $parts[5],
                'tx_bytes'         => (int) $parts[6],
                'connected'        => $handshake > 0 && ($remoteTime - $handshake) < 180,
            ];
        }

        return ['remote_time' => $remoteTime, 'peers' => $peers];
    }

    /**
     * Reconcilia los peers del servidor con los dispositivos activos de la BD.
     *
     * @return array{removed:int, restored:int}
     */
    public function syncPeers(): array
    {
        $serverPeers = $this->dump()['peers'];
        $devices     = (new DeviceModel())->where('active', 1)->findAll();

        $activeKeys = [];
        $commands   = [];
        $removed    = 0;
        $restored   = 0;

        foreach ($devices as $device) {
            $key = trim((string) $device->public_key);
            if ($key === '') {
                continue;
            }
            $activeKeys[$key] = true;
        }

        // A. Eliminar peers fantasma (presentes en el servidor pero no activos en la BD)
        foreach (array_keys($serverPeers) as $key) {
            if (! isset($activeKeys[$key])) {
                $commands[] = 'wg set ' . $this->iface() . ' peer ' . escapeshellarg($key) . ' remove';
                $removed++;
            }
        }

        // B. Agregar / actualizar peers activos de la BD
        foreach ($devices as $device) {
            $key = trim((string) $device->public_key);
            if ($key === '') {
                continue;
            }
            if (! isset($serverPeers[$key])) {
                $restored++;
            }
            $commands[] = 'wg set ' . $this->iface() . ' peer ' . escapeshellarg($key)
                        . ' allowed-ips ' . escapeshellarg(trim($device->ip_address) . '/32');
        }

        $commands[] = 'wg-quick save ' . $this->iface();
        $this->runBatch($commands);

        return ['removed' => $removed, 'restored' => $restored];
    }

    // ---------------------------------------------------------------------
    // Redes y firewall
    // ---------------------------------------------------------------------

    /**
     * CIDRs normalizados de todas las redes (opcionalmente excluyendo una).
     */
    public static function networkCidrs(?int $excludeNetworkId = null): array
    {
        $model = new NetworkModel();
        if ($excludeNetworkId !== null) {
            $model->where('id !=', $excludeNetworkId);
        }

        $cidrs = [];
        foreach ($model->findAll() as $network) {
            $normalized = self::normalizeCidr($network->cidr);
            if ($normalized !== null) {
                $cidrs[] = $normalized;
            }
        }

        $cidrs = array_values(array_unique($cidrs));

        return $cidrs ?: [self::FALLBACK_CIDR];
    }

    /**
     * Normaliza un CIDR a su dirección de red. Devuelve null si no es válido
     * o si es /31 - /32 (no tienen host .1 para el servidor).
     */
    public static function normalizeCidr(?string $cidr): ?string
    {
        if (! is_string($cidr) || substr_count($cidr, '/') !== 1) {
            return null;
        }

        [$ip, $mask] = explode('/', trim($cidr));
        if (! ctype_digit($mask)) {
            return null;
        }

        $mask   = (int) $mask;
        $ipLong = ip2long($ip);
        if ($ipLong === false || $mask < 1 || $mask > 30) {
            return null;
        }

        $maskLong = (0xFFFFFFFF << (32 - $mask)) & 0xFFFFFFFF;

        return long2ip($ipLong & $maskLong) . '/' . $mask;
    }

    /**
     * Dirección del servidor (.1) dentro de un CIDR normalizado.
     */
    public static function serverAddress(string $cidr): string
    {
        [$network, $mask] = explode('/', $cidr);

        return long2ip(ip2long($network) + 1) . '/' . $mask;
    }

    public function configPath(): string
    {
        return "/etc/wireguard/{$this->interface}.conf";
    }

    public function firewallScriptPath(): string
    {
        return "/etc/wireguard/netcrew-{$this->interface}-fw.sh";
    }

    protected function postUpCommand(): string
    {
        return 'PostUp = bash ' . $this->firewallScriptPath() . ' up';
    }

    /**
     * Genera el script de firewall. Es idempotente: se puede ejecutar tantas veces
     * como se quiera y siempre deja el mismo estado. Usa cadenas propias de iptables
     * para no mezclarse con otras reglas del sistema.
     */
    public function buildFirewallScript(array $cidrs): string
    {
        $quote = static fn (string $v): string => escapeshellarg($v);
        $addrs = array_map([self::class, 'serverAddress'], $cidrs);

        $header = implode("\n", [
            '#!/bin/bash',
            '# Generado automáticamente por NetCrew. No editar manualmente.',
            'set -e',
            'IFACE=' . $quote($this->interface),
            'CHAIN=' . $quote('NETCREW-' . $this->interface),
            'CIDRS=(' . implode(' ', array_map($quote, $cidrs)) . ')',
            'ADDRS=(' . implode(' ', array_map($quote, $addrs)) . ')',
        ]);

        $body = <<<'BASH'
DEFAULT_DEV=$(ip route show default | awk '{for(i=1;i<=NF;i++) if($i=="dev") print $(i+1)}' | head -n1)

# Elimina reglas sueltas creadas por versiones anteriores de NetCrew
cleanup_legacy() {
    while iptables -D FORWARD -i "$IFACE" -j ACCEPT 2>/dev/null; do :; done
    while iptables -D FORWARD -o "$IFACE" -j ACCEPT 2>/dev/null; do :; done
    while iptables -D FORWARD -i "$IFACE" -o "$IFACE" -j ACCEPT 2>/dev/null; do :; done
    while iptables -D FORWARD -i "$IFACE" -o "$IFACE" -j DROP 2>/dev/null; do :; done
    while iptables -D FORWARD -i "$IFACE" -o wg+ -j DROP 2>/dev/null; do :; done
    for c in "${CIDRS[@]}"; do
        while iptables -D FORWARD -i "$IFACE" -o "$IFACE" -s "$c" -d "$c" -j ACCEPT 2>/dev/null; do :; done
    done
    if [ -n "$DEFAULT_DEV" ]; then
        while iptables -D FORWARD -i "$IFACE" -o "$DEFAULT_DEV" -j ACCEPT 2>/dev/null; do :; done
        while iptables -D FORWARD -i "$IFACE" -o "$DEFAULT_DEV" -j DROP 2>/dev/null; do :; done
        while iptables -t nat -D POSTROUTING -o "$DEFAULT_DEV" -j MASQUERADE 2>/dev/null; do :; done
    fi
}

has_addr() {
    local target="$1" a
    for a in $(ip -4 -o addr show dev "$IFACE" | awk '{print $4}'); do
        if [ "$a" = "$target" ]; then return 0; fi
    done
    return 1
}

is_desired_addr() {
    local target="$1" d
    for d in "${ADDRS[@]}"; do
        if [ "$d" = "$target" ]; then return 0; fi
    done
    return 1
}

# Asigna al servidor la IP .1 de cada red y quita las de redes eliminadas
sync_addresses() {
    local a
    if ! ip link show "$IFACE" >/dev/null 2>&1; then return 0; fi
    for a in "${ADDRS[@]}"; do
        if ! has_addr "$a"; then ip addr add "$a" dev "$IFACE"; fi
    done
    for a in $(ip -4 -o addr show dev "$IFACE" | awk '{print $4}'); do
        if ! is_desired_addr "$a"; then ip addr del "$a" dev "$IFACE"; fi
    done
}

firewall_up() {
    sysctl -q -w net.ipv4.ip_forward=1 || echo "Aviso: no se pudo activar ip_forward" >&2
    grep -q '^net.ipv4.ip_forward=1' /etc/sysctl.conf 2>/dev/null || echo 'net.ipv4.ip_forward=1' >> /etc/sysctl.conf || true

    cleanup_legacy

    # Cadena FORWARD propia
    iptables -N "$CHAIN" 2>/dev/null || iptables -F "$CHAIN"
    iptables -C FORWARD -j "$CHAIN" 2>/dev/null || iptables -I FORWARD 1 -j "$CHAIN"

    # Tráfico dentro de la misma subred VPN: permitido
    for c in "${CIDRS[@]}"; do
        iptables -A "$CHAIN" -i "$IFACE" -o "$IFACE" -s "$c" -d "$c" -j ACCEPT
    done
    # Tráfico entre subredes distintas u otras interfaces WireGuard: denegado
    iptables -A "$CHAIN" -i "$IFACE" -o wg+ -j DROP
    # Salida a Internet / LAN del servidor y tráfico de retorno
    iptables -A "$CHAIN" -i "$IFACE" -j ACCEPT
    iptables -A "$CHAIN" -o "$IFACE" -m conntrack --ctstate RELATED,ESTABLISHED -j ACCEPT

    # Cadena NAT propia
    iptables -t nat -N "$CHAIN" 2>/dev/null || iptables -t nat -F "$CHAIN"
    iptables -t nat -C POSTROUTING -j "$CHAIN" 2>/dev/null || iptables -t nat -A POSTROUTING -j "$CHAIN"
    if [ -n "$DEFAULT_DEV" ]; then
        for c in "${CIDRS[@]}"; do
            iptables -t nat -A "$CHAIN" -s "$c" -o "$DEFAULT_DEV" -j MASQUERADE
        done
    fi

    sync_addresses
}

firewall_down() {
    set +e
    while iptables -D FORWARD -j "$CHAIN" 2>/dev/null; do :; done
    iptables -F "$CHAIN" 2>/dev/null
    iptables -X "$CHAIN" 2>/dev/null
    while iptables -t nat -D POSTROUTING -j "$CHAIN" 2>/dev/null; do :; done
    iptables -t nat -F "$CHAIN" 2>/dev/null
    iptables -t nat -X "$CHAIN" 2>/dev/null
    return 0
}

case "$1" in
    up)   firewall_up ;;
    down) firewall_down ;;
    *)    echo "Uso: $0 up|down" >&2; exit 2 ;;
esac
BASH;

        return $header . "\n" . $body . "\n";
    }

    public function writeFirewallScript(array $cidrs): void
    {
        $this->writeRemoteFile($this->firewallScriptPath(), $this->buildFirewallScript($cidrs), '700');
    }

    public function applyFirewall(): void
    {
        $this->run('bash ' . escapeshellarg($this->firewallScriptPath()) . ' up');
    }

    /**
     * Regenera y aplica el firewall con las redes actuales (o las indicadas).
     */
    public function syncFirewall(?array $cidrs = null): void
    {
        $this->writeFirewallScript($cidrs ?? self::networkCidrs());
        $this->applyFirewall();
    }

    /**
     * Contenido del .conf de la interfaz del servidor.
     */
    public function buildInterfaceConfig(string $privateKey, array $cidrs): string
    {
        $addresses = implode(', ', array_map([self::class, 'serverAddress'], $cidrs));
        $script    = $this->firewallScriptPath();

        return "[Interface]\n"
             . "Address = {$addresses}\n"
             . "SaveConfig = false\n"
             . 'ListenPort = ' . self::LISTEN_PORT . "\n"
             . "PrivateKey = {$privateKey}\n"
             . "PostUp = bash {$script} up\n"
             . "PostDown = bash {$script} down\n";
    }

    // ---------------------------------------------------------------------
    // Aprovisionamiento completo del servidor
    // ---------------------------------------------------------------------

    /**
     * Instala WireGuard si falta, genera llaves, escribe la configuración,
     * levanta la interfaz, aplica el firewall y restaura los peers de la BD.
     *
     * @return string|null Llave pública del servidor
     */
    public function provisionServer(): ?string
    {
        // 1. Instalar WireGuard si no está presente
        if (! $this->succeeds('which wg', false)) {
            $this->run('env DEBIAN_FRONTEND=noninteractive apt-get update');
            $this->run('env DEBIAN_FRONTEND=noninteractive apt-get install -y wireguard iptables');

            if (! $this->succeeds('which wg', false)) {
                throw new RuntimeException('WireGuard no está instalado en el servidor y falló la instalación automática.');
            }
        }

        // 2. Llaves del servidor
        if (! $this->succeeds('test -s /etc/wireguard/privatekey')) {
            $this->run('bash -c ' . escapeshellarg(
                'umask 077 && mkdir -p /etc/wireguard && wg genkey | tee /etc/wireguard/privatekey | wg pubkey > /etc/wireguard/publickey'
            ));
        }

        $privateKey = self::extractKey($this->run('cat /etc/wireguard/privatekey'));
        if ($privateKey === null) {
            throw new RuntimeException('No se pudo leer la llave privada del servidor WireGuard.');
        }

        // 3. Script de firewall (debe existir antes de levantar la interfaz, lo usa PostUp)
        $cidrs = self::networkCidrs();
        $this->writeFirewallScript($cidrs);

        // 4. Reescribir el .conf solo si no existe o está desactualizado
        $confFile = $this->configPath();
        $existing = $this->succeeds('test -f ' . escapeshellarg($confFile))
            ? $this->run('cat ' . escapeshellarg($confFile))
            : '';

        $needsRewrite = $existing === ''
            || ! str_contains($existing, $privateKey)
            || ! str_contains($existing, $this->postUpCommand())
            || preg_match('/^\s*SaveConfig\s*=\s*true/mi', $existing) === 1;

        if ($needsRewrite) {
            // Bajar la interfaz (ejecuta el PostDown antiguo y limpia sus reglas). Puede fallar si ya estaba caída.
            $this->exec('wg-quick down ' . $this->iface());
            $this->writeRemoteFile($confFile, $this->buildInterfaceConfig($privateKey, $cidrs), '600');
        }

        // 5. Levantar la interfaz si no está activa
        if (! $this->isInterfaceUp()) {
            $this->run('wg-quick up ' . $this->iface());
        }
        $this->exec('systemctl enable ' . escapeshellarg('wg-quick@' . $this->interface));

        // 6. Aplicar firewall y restaurar peers desde la BD (el .conf reescrito no contiene peers)
        $this->applyFirewall();
        $this->syncPeers();

        // 7. Devolver la llave pública
        return self::extractKey($this->run('cat /etc/wireguard/publickey'));
    }

    // ---------------------------------------------------------------------
    // Utilidades
    // ---------------------------------------------------------------------

    public static function isValidKey(string $key): bool
    {
        return preg_match('/^[A-Za-z0-9+\/]{43}=$/', $key) === 1;
    }

    protected static function extractKey(string $output): ?string
    {
        return preg_match('/([A-Za-z0-9+\/]{43}=)\s*$/', $output, $m) ? $m[1] : null;
    }
}
