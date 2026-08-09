<?php

namespace App\Controllers;

use App\Controllers\BaseController;

class WireguardController extends BaseController
{
    // ---------------------------------------------------------------------
    // Mostrar formulario de ajustes globales de WireGuard
    // ---------------------------------------------------------------------
    public function wireguard()
    {
        $settings = service('settings');

        $data = [
            'title'               => 'Ajustes Globales WireGuard',
            'wg_ssh_host'         => $settings->get('WireGuard.sshHost'),
            'wg_ssh_port'         => $settings->get('WireGuard.sshPort') ?: 22,
            'wg_ssh_user'         => $settings->get('WireGuard.sshUser'),
            'wg_ssh_auth_type'    => $settings->get('WireGuard.sshAuthType') ?: 'password',
            'wg_ssh_password'     => !empty($settings->get('WireGuard.sshPassword')) ? '********' : '',
            'wg_ssh_private_key'  => !empty($settings->get('WireGuard.sshPrivateKey')) ? '********' : '',
            'wg_interface'        => $settings->get('WireGuard.interface') ?: 'wg0',
            'wg_public_key'       => $settings->get('WireGuard.publicKey'),
            'wg_endpoint'         => $settings->get('WireGuard.endpoint'),
            'wg_restricted_cidrs' => $settings->get('WireGuard.restrictedCidrs'),
        ];

        // Autodescubrir clave si está en blanco y tenemos datos SSH.
        // Se usa un timeout de 3s para no bloquear la carga de la página si el servidor está caído.
        if (empty($data['wg_public_key']) && !empty($data['wg_ssh_host']) && !empty($data['wg_ssh_user'])) {
            $this->autoDiscoverPublicKey(3);
            $data['wg_public_key'] = $settings->get('WireGuard.publicKey');
        }

        echo view('template/header', $data);
        echo view('settings/wireguard', $data);
        echo view('template/footer');
    }

    // ---------------------------------------------------------------------
    // Actualizar ajustes globales de WireGuard
    // ---------------------------------------------------------------------
    public function wireguardUpdate()
    {
        $rules = [
            'wg_ssh_host'      => 'required',
            'wg_ssh_user'      => 'required',
            'wg_ssh_port'      => 'required|numeric',
            'wg_ssh_auth_type' => 'required|in_list[password,key]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $settings = service('settings');
        
        // Guardar el host antiguo antes de actualizarlo
        $oldHost = $settings->get('WireGuard.sshHost');

        $settings->set('WireGuard.sshHost', $this->request->getPost('wg_ssh_host'));
        $settings->set('WireGuard.sshUser', $this->request->getPost('wg_ssh_user'));
        $settings->set('WireGuard.sshPort', (int)$this->request->getPost('wg_ssh_port'));
        $settings->set('WireGuard.sshAuthType', $this->request->getPost('wg_ssh_auth_type'));
        $settings->set('WireGuard.interface', $this->request->getPost('wg_interface') ?: 'wg0');
        $settings->set('WireGuard.endpoint', $this->request->getPost('wg_endpoint'));
        $settings->set('WireGuard.restrictedCidrs', $this->request->getPost('wg_restricted_cidrs'));

        $encrypter = \Config\Services::encrypter();

        // Guardar contraseña encriptada si se provee una nueva y no es la máscara '********'
        $password = $this->request->getPost('wg_ssh_password');
        if (!empty($password) && $password !== '********') {
            try {
                $encrypted = base64_encode($encrypter->encrypt($password));
                $settings->set('WireGuard.sshPassword', $encrypted);
            } catch (\Exception $e) {
                log_message('error', 'Error al encriptar contraseña SSH global: ' . $e->getMessage());
            }
        }

        // Guardar llave privada encriptada si se provee una nueva y no es la máscara '********'
        $privateKey = $this->request->getPost('wg_ssh_private_key');
        if (!empty($privateKey) && $privateKey !== '********') {
            try {
                $encryptedKey = base64_encode($encrypter->encrypt($privateKey));
                $settings->set('WireGuard.sshPrivateKey', $encryptedKey);
            } catch (\Exception $e) {
                log_message('error', 'Error al encriptar llave privada SSH global: ' . $e->getMessage());
            }
        }

        // Limpiar clave pública si se cambia de host para forzar autodescubrimiento
        if ($oldHost !== $this->request->getPost('wg_ssh_host')) {
            $settings->set('WireGuard.publicKey', null);
        }

        return redirect()->back()->with('message', 'Ajustes globales de WireGuard guardados.');
    }

    // ---------------------------------------------------------------------
    // Probar conexión SSH de WireGuard
    // ---------------------------------------------------------------------
    public function wireguardTest()
    {
        $settings  = service('settings');
        $encrypter = \Config\Services::encrypter();

        // Leer parámetros del POST
        $host     = $this->request->getPost('wg_ssh_host');
        $user     = $this->request->getPost('wg_ssh_user');
        $port     = (int)($this->request->getPost('wg_ssh_port') ?: 22);
        $authType = $this->request->getPost('wg_ssh_auth_type') ?: 'password';
        $tempPass = $this->request->getPost('wg_ssh_password');
        $tempKey  = $this->request->getPost('wg_ssh_private_key');

        // Resolver credencial: si el campo es '********' usamos la que ya está guardada en la BD
        if ($authType === 'key') {
            if (!empty($tempKey) && $tempKey !== '********') {
                $credential = $tempKey;
            } else {
                $encryptedKey = $settings->get('WireGuard.sshPrivateKey');
                if (empty($encryptedKey)) {
                    return redirect()->to(base_url('settings/wireguard'))
                        ->with('error', 'No hay llave privada SSH configurada para realizar la prueba.');
                }
                try {
                    $credential = $encrypter->decrypt(base64_decode($encryptedKey));
                } catch (\Exception $e) {
                    return redirect()->to(base_url('settings/wireguard'))
                        ->with('error', 'Error al desencriptar la llave privada SSH guardada.');
                }
            }
        } else {
            if (!empty($tempPass) && $tempPass !== '********') {
                $credential = $tempPass;
            } else {
                $encryptedPass = $settings->get('WireGuard.sshPassword');
                if (empty($encryptedPass)) {
                    return redirect()->to(base_url('settings/wireguard'))
                        ->with('error', 'No hay contraseña SSH configurada para realizar la prueba.');
                }
                try {
                    $credential = $encrypter->decrypt(base64_decode($encryptedPass));
                } catch (\Exception $e) {
                    return redirect()->to(base_url('settings/wireguard'))
                        ->with('error', 'Error al desencriptar la contraseña SSH guardada.');
                }
            }
        }

        try {
            set_time_limit(120);

            // Conectar usando parámetros del POST sin tocar la BD
            $ssh = $this->getSshSessionWithParams($host, $user, $port, $authType, $credential, 10);
            $ssh->setTimeout(120);

            // Propagar la credencial temporal solo si es autenticación por contraseña,
            // para que wrapSudoCommand no intente leer una contraseña no guardada en la BD.
            $sudoPassword = ($authType === 'password') ? $credential : null;

            // Intentar inicializar y obtener la clave pública usando los settings actuales de interfaz
            $pubkey = $this->runAutoDiscoveryCommand($ssh, $sudoPassword);

            if (!empty($pubkey)) {
                $settings->set('WireGuard.publicKey', $pubkey);
            }

            return redirect()->to(base_url('settings/wireguard'))
                ->with('message', '¡Conexión exitosa con el servidor WireGuard!');

        } catch (\Exception $e) {
            // No hay nada que restaurar: nunca escribimos en la BD
            return redirect()->to(base_url('settings/wireguard'))
                ->with('error', "Fallo de conexión SSH<br><small class='text-danger'>No se pudo contactar al servidor: " . esc($e->getMessage()) . "</small>");
        }
    }

    // ---------------------------------------------------------------------
    // Helper: Intentar autodescubrir la clave pública de WireGuard
    // ---------------------------------------------------------------------
    protected function autoDiscoverPublicKey(int $connectionTimeout = 10)
    {
        try {
            $ssh = $this->getSshSession($connectionTimeout);
            $ssh->setTimeout(120);
            // Sin override: autoDescubrimiento usa la contraseña guardada en la BD
            $pubkey = $this->runAutoDiscoveryCommand($ssh, null);
            if (!empty($pubkey)) {
                service('settings')->set('WireGuard.publicKey', $pubkey);
            }
        } catch (\Exception $e) {
            // Ignorar silenciosamente: es una operación de conveniencia en el GET de la vista
            log_message('debug', 'autoDiscoverPublicKey fallido (no bloqueante): ' . $e->getMessage());
        }
    }

    // ---------------------------------------------------------------------
    // Helper: Comandos SSH para instalar/generar/obtener claves WireGuard
    // e inicializar la interfaz de red con reglas de NAT y Forwarding Full Tunnel.
    // ---------------------------------------------------------------------
    protected function runAutoDiscoveryCommand($ssh, ?string $sudoPassword = null): ?string
    {
        $settings  = service('settings');
        $interface = $settings->get('WireGuard.interface') ?: 'wg0';

        // Comprobar si wg está instalado
        $wgCheck = $ssh->exec("which wg");
        if (empty(trim($wgCheck))) {
            // Intentar instalar
            $installCmd = $this->wrapSudoCommand("env DEBIAN_FRONTEND=noninteractive apt-get update", $sudoPassword) . " && " . $this->wrapSudoCommand("env DEBIAN_FRONTEND=noninteractive apt-get install wireguard iptables -y", $sudoPassword);
            $ssh->exec($installCmd);
            
            // Verificar si ahora sí está instalado
            $wgCheckAfter = $ssh->exec("which wg");
            if (empty(trim($wgCheckAfter))) {
                throw new \RuntimeException("WireGuard no está instalado en el servidor y falló la instalación automática.");
            }
        }

        // Habilitar IP Forwarding asegurando que persista correctamente en sysctl.conf
        $forwardCmd = "sysctl -w net.ipv4.ip_forward=1 && (grep -q '^net.ipv4.ip_forward=1' /etc/sysctl.conf || echo 'net.ipv4.ip_forward=1' >> /etc/sysctl.conf)";
        $forwardCmd = $this->wrapSudoCommand("bash -c " . escapeshellarg($forwardCmd), $sudoPassword);
        $ssh->exec($forwardCmd);

        // 1. Verificar si existen las claves
        $checkKeysCmd = $this->wrapSudoCommand("ls -la /etc/wireguard/privatekey /etc/wireguard/publickey", $sudoPassword);
        $checkOutput  = $ssh->exec($checkKeysCmd);
        $keysExist    = (strpos($checkOutput, 'No such file or directory') === false && strpos($checkOutput, 'privatekey') !== false);

        if (!$keysExist) {
            // Generar llaves
            $genCmd = $this->wrapSudoCommand("bash -c " . escapeshellarg("wg genkey | tee /etc/wireguard/privatekey | wg pubkey > /etc/wireguard/publickey && chmod 600 /etc/wireguard/privatekey /etc/wireguard/publickey"), $sudoPassword);
            $ssh->exec($genCmd);
        }
        
        // 2. Leer la llave privada
        $readPrivCmd = $this->wrapSudoCommand("cat /etc/wireguard/privatekey", $sudoPassword);
        $privKeyRaw  = trim($ssh->exec($readPrivCmd));
        $privKey     = '';
        if (preg_match('/([A-Za-z0-9+\/]{43}=)$/', $privKeyRaw, $matches)) {
            $privKey = $matches[1];
        }

        $confFile = "/etc/wireguard/{$interface}.conf";
        $checkConfCmd = $this->wrapSudoCommand("ls -la " . escapeshellarg($confFile), $sudoPassword);
        $confOutput   = $ssh->exec($checkConfCmd);
        $confExists   = (strpos($confOutput, 'No such file or directory') === false && strpos($confOutput, "{$interface}.conf") !== false);

        // Calcular la IP .1 del servidor y el CIDR base para cada red configurada.
        // Se omiten redes /31 y /32 porque no tienen host válido en .1.
        $networkModel = new \App\Models\NetworkModel();
        $networks     = $networkModel->findAll();

        $serverIps  = [];
        $validCidrs = [];

        if (empty($networks)) {
            $serverIps[]  = '10.10.10.1/24';
            $validCidrs[] = '10.10.10.0/24';
        } else {
            foreach ($networks as $net) {
                $cidr = $net->cidr;
                if (strpos($cidr, '/') !== false) {
                    [$ip, $mask] = explode('/', $cidr);
                    $mask = (int)$mask;

                    // /31 tiene solo 2 IPs (punto a punto) y /32 es un host único: no aplica host .1
                    if ($mask >= 31) {
                        continue;
                    }

                    $ipLong = ip2long($ip);
                    if ($ipLong !== false) {
                        $maskLong    = ~((1 << (32 - $mask)) - 1) & 0xFFFFFFFF;
                        $networkLong = $ipLong & $maskLong;

                        $serverIps[]  = long2ip($networkLong + 1) . '/' . $mask;
                        $validCidrs[] = long2ip($networkLong) . '/' . $mask;
                    }
                }
            }
            // Si todas las redes eran /31 o /32, usar fallback
            if (empty($serverIps)) {
                $serverIps[]  = '10.10.10.1/24';
                $validCidrs[] = '10.10.10.0/24';
            }
        }

        $serverIpString = implode(', ', array_unique($serverIps));
        $validCidrs     = array_unique($validCidrs);

        // Generar reglas iptables para aislar subredes entre sí (DROP bidireccional)
        $isolationUp   = [];
        $isolationDown = [];
        $cidrCount     = count($validCidrs);

        if ($cidrCount > 1) {
            for ($i = 0; $i < $cidrCount; $i++) {
                for ($j = $i + 1; $j < $cidrCount; $j++) {
                    $netA = $validCidrs[$i];
                    $netB = $validCidrs[$j];

                    // Denegar tráfico en ambas direcciones entre subredes locales VPN
                    $isolationUp[]   = "iptables -I FORWARD -s {$netA} -d {$netB} -j DROP";
                    $isolationUp[]   = "iptables -I FORWARD -s {$netB} -d {$netA} -j DROP";

                    $isolationDown[] = "iptables -D FORWARD -s {$netA} -d {$netB} -j DROP";
                    $isolationDown[] = "iptables -D FORWARD -s {$netB} -d {$netA} -j DROP";
                }
            }
        }

        $isolationUpStr   = !empty($isolationUp)   ? implode('; ', $isolationUp)   . '; ' : '';
        $isolationDownStr = !empty($isolationDown) ? implode('; ', $isolationDown) . '; ' : '';

        // 1. Construir reglas PostUp/PostDown: NAT sobre la interfaz WAN detectada dinámicamente
        // CORRECCIÓN APLICADA: Uso de -I para FORWARD y reordenamiento de reglas
        $postUp = "iptables -I FORWARD -i {$interface} -j ACCEPT; " .
                  "iptables -I FORWARD -o {$interface} -j ACCEPT; " .
                  "{$isolationUpStr}" .
                  "DEFAULT_DEV=\$(ip route show default | awk '{for(i=1;i<=NF;i++) if(\$i==\"dev\") print \$(i+1)}' | head -n1); " .
                  "if [ ! -z \"\$DEFAULT_DEV\" ]; then iptables -t nat -A POSTROUTING -o \"\$DEFAULT_DEV\" -j MASQUERADE; fi";

        $postDown = "iptables -D FORWARD -i {$interface} -j ACCEPT; " .
                    "iptables -D FORWARD -o {$interface} -j ACCEPT; " .
                    "{$isolationDownStr}" .
                    "DEFAULT_DEV=\$(ip route show default | awk '{for(i=1;i<=NF;i++) if(\$i==\"dev\") print \$(i+1)}' | head -n1); " .
                    "if [ ! -z \"\$DEFAULT_DEV\" ]; then iptables -t nat -D POSTROUTING -o \"\$DEFAULT_DEV\" -j MASQUERADE; fi";

        // 2. Determinar si hay que reescribir el archivo de configuración
        $needWriteConf = !$confExists;
        if ($confExists && !empty($privKey)) {
            $readConfContentCmd  = $this->wrapSudoCommand("cat " . escapeshellarg($confFile), $sudoPassword);
            $existingConfContent = $ssh->exec($readConfContentCmd);

            if (strpos($existingConfContent, $privKey) === false || strpos($existingConfContent, '[sudo] password for') !== false) {
                $needWriteConf = true;
            }
            if (strpos($existingConfContent, "Address = {$serverIpString}") === false) {
                $needWriteConf = true;
            }
            // Verificar si SaveConfig=true está presente para desactivarlo
            if (strpos($existingConfContent, "SaveConfig = true") !== false) {
                $needWriteConf = true;
            }
            // Verificar que tenga regla de NAT POSTROUTING y MASQUERADE
            if (strpos($existingConfContent, "POSTROUTING") === false || strpos($existingConfContent, "MASQUERADE") === false) {
                $needWriteConf = true;
            }
            // Si hay múltiples subredes, asegurar que las reglas DROP de aislamiento estén presentes
            if ($cidrCount > 1 && strpos($existingConfContent, "-j DROP") === false) {
                $needWriteConf = true;
            }
        }

        if ($needWriteConf && !empty($privKey)) {
            $confContent = "[Interface]\n" .
                           "Address = {$serverIpString}\n" .
                           "SaveConfig = false\n" .
                           "ListenPort = 51820\n" .
                           "PrivateKey = {$privKey}\n" .
                           "PostUp = {$postUp}\n" .
                           "PostDown = {$postDown}\n";

            // Bajar la interfaz si estaba activa antes de sobreescribir el conf
            if ($confExists) {
                $downCmd = $this->wrapSudoCommand("wg-quick down " . escapeshellarg($interface), $sudoPassword);
                $ssh->exec($downCmd);
            }

            $writeConfCmd = $this->wrapSudoCommand("bash -c " . escapeshellarg("echo " . escapeshellarg($confContent) . " | tee " . escapeshellarg($confFile) . " > /dev/null && chmod 600 " . escapeshellarg($confFile)), $sudoPassword);
            $ssh->exec($writeConfCmd);

            // Reiniciar la interfaz inmediatamente para aplicar las nuevas reglas iptables
            $restartCmd = $this->wrapSudoCommand("wg-quick down " . escapeshellarg($interface), $sudoPassword) . " ; " .
                          $this->wrapSudoCommand("wg-quick up " . escapeshellarg($interface), $sudoPassword);
            $ssh->exec($restartCmd);
        }

        // 3. Verificar si la interfaz de red está activa
        $checkActiveCmd = $this->wrapSudoCommand("wg show " . escapeshellarg($interface), $sudoPassword);
        $activeOutput   = $ssh->exec($checkActiveCmd);

        // Si no está activa, intentamos levantarla
        if (empty(trim($activeOutput)) || strpos($activeOutput, 'Unable to modify interface') !== false || strpos($activeOutput, 'No such device') !== false) {
            $startCmd = $this->wrapSudoCommand("wg-quick up " . escapeshellarg($interface), $sudoPassword) . " && " . $this->wrapSudoCommand("systemctl enable wg-quick@" . escapeshellarg($interface), $sudoPassword);
            $ssh->exec($startCmd);

            // Verificar si finalmente se levantó la interfaz
            $verifyActiveCmd = $this->wrapSudoCommand("wg show " . escapeshellarg($interface), $sudoPassword);
            $verifyOutput    = $ssh->exec($verifyActiveCmd);
            if (empty(trim($verifyOutput)) || strpos($verifyOutput, 'Unable to modify interface') !== false || strpos($verifyOutput, 'No such device') !== false) {
                throw new \RuntimeException("No se pudo levantar la interfaz de WireGuard ({$interface}) en el servidor. Verifica los logs de red del servidor.");
            }
        }
        
        // 4. Leer y retornar la clave pública
        $readPubCmd = $this->wrapSudoCommand("cat /etc/wireguard/publickey", $sudoPassword);
        $pubkey     = trim($ssh->exec($readPubCmd));
        
        if (preg_match('/([A-Za-z0-9+\/]{43}=)$/', $pubkey, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
}