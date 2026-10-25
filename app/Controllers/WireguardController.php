<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Libraries\WireguardService;

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
            'wg_interface'     => 'required|alpha_dash',
            'wg_endpoint'      => 'permit_empty|regex_match[/^[\w\.-]+:\d+$/]',
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
            set_time_limit(300);

            // Conectar usando parámetros del POST sin tocar la BD
            $wg = WireguardService::fromParams((string) $host, (string) $user, $port, $authType, (string) $credential, 10);
            $wg->setTimeout(120);

            // Inicializar el servidor y obtener la clave pública usando los settings actuales de interfaz
            $pubkey = $wg->provisionServer();

            if (!empty($pubkey)) {
                $settings->set('WireGuard.publicKey', $pubkey);
            }

            return redirect()->to(base_url('settings/wireguard'))
                ->with('message', '¡Conexión exitosa con el servidor WireGuard!');

        } catch (\Throwable $e) {
            log_message('error', 'Prueba de conexión WireGuard fallida: ' . $e->getMessage());

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
            $wg = WireguardService::fromSettings($connectionTimeout);
            $wg->setTimeout(120);

            $pubkey = $wg->provisionServer();
            if (!empty($pubkey)) {
                service('settings')->set('WireGuard.publicKey', $pubkey);
            }
        } catch (\Throwable $e) {
            // No bloqueante: es una operación de conveniencia en el GET de la vista
            log_message('notice', 'autoDiscoverPublicKey fallido (no bloqueante): ' . $e->getMessage());
        }
    }
}
