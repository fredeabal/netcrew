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

        // Autodescubrir clave si está en blanco y tenemos datos SSH
        if (empty($data['wg_public_key']) && !empty($data['wg_ssh_host']) && !empty($data['wg_ssh_user'])) {
            $this->autoDiscoverPublicKey();
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
        // Guardamos temporalmente los datos enviados en el POST para la prueba
        $settings = service('settings');
        
        $tempHost = $this->request->getPost('wg_ssh_host');
        $tempUser = $this->request->getPost('wg_ssh_user');
        $tempPort = $this->request->getPost('wg_ssh_port');
        $tempAuthType = $this->request->getPost('wg_ssh_auth_type');
        $tempPass = $this->request->getPost('wg_ssh_password');
        $tempKey  = $this->request->getPost('wg_ssh_private_key');

        // Salvaguardar configuraciones actuales para restaurarlas después
        $origHost = $settings->get('WireGuard.sshHost');
        $origUser = $settings->get('WireGuard.sshUser');
        $origPort = $settings->get('WireGuard.sshPort');
        $origAuthType = $settings->get('WireGuard.sshAuthType');
        $origPass = $settings->get('WireGuard.sshPassword');
        $origKey = $settings->get('WireGuard.sshPrivateKey');

        // Asignar los temporales para la prueba
        if (!empty($tempHost)) $settings->set('WireGuard.sshHost', $tempHost);
        if (!empty($tempUser)) $settings->set('WireGuard.sshUser', $tempUser);
        if (!empty($tempPort)) $settings->set('WireGuard.sshPort', (int)$tempPort);
        if (!empty($tempAuthType)) $settings->set('WireGuard.sshAuthType', $tempAuthType);
        
        $encrypter = \Config\Services::encrypter();
        if (!empty($tempPass) && $tempPass !== '********') {
            $settings->set('WireGuard.sshPassword', base64_encode($encrypter->encrypt($tempPass)));
        }
        if (!empty($tempKey) && $tempKey !== '********') {
            $settings->set('WireGuard.sshPrivateKey', base64_encode($encrypter->encrypt($tempKey)));
        }

        try {
            // Aumentar tiempo de ejecución por si hay que instalar wireguard en un servidor nuevo
            set_time_limit(120);

            // Obtenemos la sesión usando BaseController (10 segundos de timeout de conexión)
            $ssh = $this->getSshSession(10);
            
            // Asegurar que los comandos largos (como apt install) no den timeout (120s)
            $ssh->setTimeout(120);

            // Intentar inicializar y obtener la clave pública
            $pubkey = $this->runAutoDiscoveryCommand($ssh);
            
            if (!empty($pubkey)) {
                $settings->set('WireGuard.publicKey', $pubkey);
            }

            // Si fue exitoso, dejamos las nuevas configuraciones en la BD y confirmamos
            return redirect()->to(base_url('settings/wireguard'))->with('message', '¡Conexión exitosa con el servidor WireGuard!');
        } catch (\Exception $e) {
            // Restaurar configuraciones originales en caso de fallo
            $settings->set('WireGuard.sshHost', $origHost);
            $settings->set('WireGuard.sshUser', $origUser);
            $settings->set('WireGuard.sshPort', $origPort);
            $settings->set('WireGuard.sshAuthType', $origAuthType);
            $settings->set('WireGuard.sshPassword', $origPass);
            $settings->set('WireGuard.sshPrivateKey', $origKey);

            return redirect()->to(base_url('settings/wireguard'))->with('error', "Fallo de conexión SSH<br><small class='text-danger'>No se pudo contactar al servidor: " . esc($e->getMessage()) . "</small>");
        }
    }

    // ---------------------------------------------------------------------
    // Helper: Intentar autodescubrir la clave pública de WireGuard
    // ---------------------------------------------------------------------
    protected function autoDiscoverPublicKey()
    {
        try {
            $ssh = $this->getSshSession(10);
            $ssh->setTimeout(120);
            $pubkey = $this->runAutoDiscoveryCommand($ssh);
            if (!empty($pubkey)) {
                service('settings')->set('WireGuard.publicKey', $pubkey);
            }
        } catch (\Exception $e) {
            // Ignorar silenciosamente errores en el autoguardado rápido
        }
    }

    // ---------------------------------------------------------------------
    // Helper: Comandos SSH para instalar/generar/obtener claves WireGuard
    // e inicializar la interfaz de red si no está configurada
    // ---------------------------------------------------------------------
    protected function runAutoDiscoveryCommand($ssh): ?string
    {
        $settings = service('settings');
        $interface = $settings->get('WireGuard.interface') ?: 'wg0';

        // Comprobar si wg está instalado
        $wgCheck = $ssh->exec("which wg");
        if (empty(trim($wgCheck))) {
            // Intentar instalar
            $installCmd = $this->wrapSudoCommand("apt-get update") . " && " . $this->wrapSudoCommand("DEBIAN_FRONTEND=noninteractive apt-get install wireguard iptables -y");
            $ssh->exec($installCmd);
            
            // Verificar si ahora sí está instalado
            $wgCheckAfter = $ssh->exec("which wg");
            if (empty(trim($wgCheckAfter))) {
                throw new \RuntimeException("WireGuard no está instalado en el servidor y falló la instalación automática.");
            }
        }

        // Habilitar IP Forwarding permanentemente en el servidor
        $forwardCmd = "sysctl -w net.ipv4.ip_forward=1 && sed -i 's/#net.ipv4.ip_forward=1/net.ipv4.ip_forward=1/g' /etc/sysctl.conf";
        $forwardCmd = $this->wrapSudoCommand("bash -c " . escapeshellarg($forwardCmd));
        $ssh->exec($forwardCmd);

        // 1. Verificar si existen las claves
        $checkKeysCmd = $this->wrapSudoCommand("ls -la /etc/wireguard/privatekey /etc/wireguard/publickey");
        $checkOutput = $ssh->exec($checkKeysCmd);
        $keysExist = (strpos($checkOutput, 'No such file or directory') === false && strpos($checkOutput, 'privatekey') !== false);
        
        if (!$keysExist) {
            // Generar llaves
            $genCmd = $this->wrapSudoCommand("bash -c " . escapeshellarg("wg genkey | tee /etc/wireguard/privatekey | wg pubkey > /etc/wireguard/publickey && chmod 600 /etc/wireguard/privatekey /etc/wireguard/publickey"));
            $ssh->exec($genCmd);
        }
        
        // 2. Leer la llave privada de forma limpia y verificar si existe {interface}.conf
        $readPrivCmd = $this->wrapSudoCommand("cat /etc/wireguard/privatekey");
        $privKeyRaw = trim($ssh->exec($readPrivCmd));
        $privKey = '';
        if (preg_match('/([A-Za-z0-9+\/]{43}=)$/', $privKeyRaw, $matches)) {
            $privKey = $matches[1];
        }

        $confFile = "/etc/wireguard/{$interface}.conf";
        $checkConfCmd = $this->wrapSudoCommand("ls -la " . escapeshellarg($confFile));
        $confOutput = $ssh->exec($checkConfCmd);
        $confExists = (strpos($confOutput, 'No such file or directory') === false && strpos($confOutput, "{$interface}.conf") !== false);

        // Obtener todas las redes para asignar una IP .1 por cada rango en el servidor
        $networkModel = new \App\Models\NetworkModel();
        $networks = $networkModel->findAll();
        
        $serverIps = [];
        if (empty($networks)) {
            $serverIps[] = '10.10.10.1/24';
        } else {
            foreach ($networks as $net) {
                $cidr = $net->cidr;
                if (strpos($cidr, '/') !== false) {
                    list($ip, $mask) = explode('/', $cidr);
                    $ipLong = ip2long($ip);
                    if ($ipLong !== false) {
                        $mask = (int)$mask;
                        $maskLong = ~((1 << (32 - $mask)) - 1);
                        $networkLong = $ipLong & $maskLong;
                        $serverIps[] = long2ip($networkLong + 1) . '/' . $mask;
                    }
                }
            }
        }
        $serverIpString = implode(', ', array_unique($serverIps));

        $needWriteConf = !$confExists;
        if ($confExists && !empty($privKey)) {
            $readConfContentCmd = $this->wrapSudoCommand("cat " . escapeshellarg($confFile));
            $existingConfContent = $ssh->exec($readConfContentCmd);
            if (strpos($existingConfContent, $privKey) === false || strpos($existingConfContent, '[sudo] password for') !== false) {
                $needWriteConf = true;
            }
            // VERIFICAR QUE LA IP DEL SERVIDOR SEA LA CORRECTA
            if (strpos($existingConfContent, "Address = {$serverIpString}") === false) {
                $needWriteConf = true;
            }
            // VERIFICAR QUE EL ARCHIVO TENGA LAS REGLAS DE FIREWALL ACTUALIZADAS
            if (strpos($existingConfContent, "RELATED,ESTABLISHED") === false) {
                $needWriteConf = true;
            }
        }

        if ($needWriteConf && !empty($privKey)) {
            // Escribir el archivo de configuración con reglas de firewall para comunicación entre peers e internet
            $confContent = "[Interface]\n" .
                           "Address = {$serverIpString}\n" .
                           "SaveConfig = true\n" .
                           "ListenPort = 51820\n" .
                           "PrivateKey = {$privKey}\n" .
                           "PostUp = iptables -A FORWARD -i {$interface} -j ACCEPT; iptables -A FORWARD -o {$interface} -m state --state RELATED,ESTABLISHED -j ACCEPT; DEFAULT_DEV=\$(ip route show default | awk '{for(i=1;i<=NF;i++) if(\$i==\"dev\") print \$(i+1)}' | head -n1); if [ ! -z \"\$DEFAULT_DEV\" ]; then iptables -t nat -A POSTROUTING -o \"\$DEFAULT_DEV\" -j MASQUERADE; fi\n" .
                           "PostDown = iptables -D FORWARD -i {$interface} -j ACCEPT; iptables -D FORWARD -o {$interface} -m state --state RELATED,ESTABLISHED -j ACCEPT; DEFAULT_DEV=\$(ip route show default | awk '{for(i=1;i<=NF;i++) if(\$i==\"dev\") print \$(i+1)}' | head -n1); if [ ! -z \"\$DEFAULT_DEV\" ]; then iptables -t nat -D POSTROUTING -o \"\$DEFAULT_DEV\" -j MASQUERADE; fi\n";
            
            $writeConfCmd = $this->wrapSudoCommand("bash -c " . escapeshellarg("echo " . escapeshellarg($confContent) . " | tee " . escapeshellarg($confFile) . " > /dev/null && chmod 600 " . escapeshellarg($confFile)));
            $ssh->exec($writeConfCmd);

            // Si el archivo ya existía pero con llave mala, apagamos la interfaz antes de volver a levantarla
            if ($confExists) {
                $downCmd = $this->wrapSudoCommand("wg-quick down " . escapeshellarg($interface));
                $ssh->exec($downCmd);
            }
        }

        // 3. Verificar si la interfaz de red está activa
        $checkActiveCmd = $this->wrapSudoCommand("wg show " . escapeshellarg($interface));
        $activeOutput = $ssh->exec($checkActiveCmd);
        
        // Si no está activa, intentamos levantarla
        if (empty(trim($activeOutput)) || strpos($activeOutput, 'Unable to modify interface') !== false || strpos($activeOutput, 'No such device') !== false) {
            $startCmd = $this->wrapSudoCommand("wg-quick up " . escapeshellarg($interface)) . " && " . $this->wrapSudoCommand("systemctl enable wg-quick@" . escapeshellarg($interface));
            $ssh->exec($startCmd);

            // Verificar si finalmente se levantó la interfaz
            $verifyActiveCmd = $this->wrapSudoCommand("wg show " . escapeshellarg($interface));
            $verifyOutput = $ssh->exec($verifyActiveCmd);
            if (empty(trim($verifyOutput)) || strpos($verifyOutput, 'Unable to modify interface') !== false || strpos($verifyOutput, 'No such device') !== false) {
                throw new \RuntimeException("No se pudo levantar la interfaz de WireGuard ({$interface}) en el servidor. Verifica los logs de red del servidor.");
            }
        }
        
        // 4. Leer y retornar la clave pública
        $readPubCmd = $this->wrapSudoCommand("cat /etc/wireguard/publickey");
        $pubkey = trim($ssh->exec($readPubCmd));
        
        if (preg_match('/([A-Za-z0-9+\/]{43}=)$/', $pubkey, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
}
