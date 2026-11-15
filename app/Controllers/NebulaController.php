<?php

namespace App\Controllers;

use App\Libraries\NebulaService;
use App\Models\AuditLogModel;

class NebulaController extends BaseController
{
    /**
     * Muestra el formulario de ajustes globales de Nebula (Lighthouse P2P).
     */
    public function index()
    {
        $settings = service('settings');
        $resolved = NebulaService::resolveLighthouseEndpoint();

        $hasValidPass = false;
        $encPass      = $settings->get('Nebula.sshPassword');
        if (!empty($encPass)) {
            try {
                \Config\Services::encrypter()->decrypt(base64_decode($encPass));
                $hasValidPass = true;
            } catch (\Throwable $e) {
                $hasValidPass = false;
            }
        }

        $hasValidKey = false;
        $encKey      = $settings->get('Nebula.sshPrivateKey');
        if (!empty($encKey)) {
            try {
                \Config\Services::encrypter()->decrypt(base64_decode($encKey));
                $hasValidKey = true;
            } catch (\Throwable $e) {
                $hasValidKey = false;
            }
        }

        $data = [
            'title'                  => 'Ajustes Globales Nebula (P2P)',
            'nebula_lighthouse_host' => $settings->get('Nebula.lighthouseHost') ?? '',
            'nebula_lighthouse_port' => $settings->get('Nebula.lighthousePort') ?: NebulaService::DEFAULT_PORT,
            'nebula_relay_enabled'   => $settings->get('Nebula.relayEnabled') ?? 1,
            'nebula_cipher'          => $settings->get('Nebula.cipher') ?: 'aes',
            'nebula_restricted_cidrs'=> $settings->get('Nebula.restrictedCidrs') ?? '',
            'nebula_ssh_host'        => $settings->get('Nebula.sshHost') ?? '',
            'nebula_ssh_port'        => $settings->get('Nebula.sshPort') ?: 22,
            'nebula_ssh_user'        => $settings->get('Nebula.sshUser') ?? '',
            'nebula_ssh_auth_type'   => $settings->get('Nebula.sshAuthType') ?: 'password',
            'nebula_ssh_password'    => $hasValidPass ? '********' : '',
            'nebula_ssh_private_key' => $hasValidKey ? '********' : '',
            'resolved_endpoint'      => $resolved,
            'binary_status'          => NebulaService::testAvailability(),
        ];

        echo view('template/header', $data);
        echo view('settings/nebula', $data);
        echo view('template/footer');
    }

    /**
     * Guarda los ajustes globales de Nebula.
     */
    public function update()
    {
        $host = trim((string) $this->request->getPost('nebula_lighthouse_host'));
        $host = preg_replace('#^https?://#i', '', $host);
        $host = rtrim($host, '/');

        $port         = (int) ($this->request->getPost('nebula_lighthouse_port') ?: NebulaService::DEFAULT_PORT);
        $relayEnabled = $this->request->getPost('nebula_relay_enabled') ? 1 : 0;
        $cipher       = (string) ($this->request->getPost('nebula_cipher') ?: 'aes');

        $rules = [
            'nebula_lighthouse_host' => 'permit_empty|string|max_length[255]',
            'nebula_lighthouse_port' => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[65535]',
            'nebula_cipher'          => 'required|in_list[aes,chacha20]',
            'nebula_ssh_port'        => 'permit_empty|integer|greater_than_equal_to[1]|less_than_equal_to[65535]',
            'nebula_ssh_auth_type'   => 'permit_empty|in_list[password,key]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $settings = service('settings');
        $settings->set('Nebula.lighthouseHost', $host);
        $settings->set('Nebula.lighthousePort', $port);
        $settings->set('Nebula.relayEnabled', $relayEnabled);
        $settings->set('Nebula.cipher', $cipher);
        $settings->set('Nebula.restrictedCidrs', trim((string) $this->request->getPost('nebula_restricted_cidrs')));

        $sshHost = trim((string) $this->request->getPost('nebula_ssh_host'));
        $sshUser = trim((string) $this->request->getPost('nebula_ssh_user'));
        $sshPort = (int) ($this->request->getPost('nebula_ssh_port') ?: 22);
        $authType = (string) ($this->request->getPost('nebula_ssh_auth_type') ?: 'password');

        $settings->set('Nebula.sshHost', $sshHost);
        $settings->set('Nebula.sshUser', $sshUser);
        $settings->set('Nebula.sshPort', $sshPort);
        $settings->set('Nebula.sshAuthType', $authType);

        $sshPass = (string) $this->request->getPost('nebula_ssh_password');
        if ($sshPass !== '' && $sshPass !== '********') {
            $enc = base64_encode(\Config\Services::encrypter()->encrypt($sshPass));
            $settings->set('Nebula.sshPassword', $enc);
        }

        $sshKey = trim((string) $this->request->getPost('nebula_ssh_private_key'));
        if ($sshKey !== '' && $sshKey !== '********') {
            $enc = base64_encode(\Config\Services::encrypter()->encrypt($sshKey));
            $settings->set('Nebula.sshPrivateKey', $enc);
        }

        AuditLogModel::log('SETTINGS_NEBULA_UPDATE', "Actualizó ajustes globales de Nebula (Lighthouse: {$host}:{$port}, Relay: {$relayEnabled}, Cipher: {$cipher})");

        $netModel  = new \App\Models\NetworkModel();
        $activeNet = $netModel->where('active', 1)->orderBy('id', 'ASC')->first();
        if ($activeNet) {
            $caKey = $netModel->decryptCaKey((string) ($activeNet->nebula_ca_key ?? ''));
            if ($caKey !== '') {
                NebulaService::syncLocalLighthouse($activeNet, $caKey);
            }
        }

        return redirect()->to('/settings?tab=nebula')->with('success', 'Ajustes de Nebula guardados correctamente.');
    }

    /**
     * Comprueba la disponibilidad de `nebula-cert` (local o vía SSH) en formato JSON.
     */
    public function test()
    {
        $status = NebulaService::testAvailability();

        return $this->response->setJSON([
            'success' => $status['local'] || $status['remote'],
            'message' => $status['message'],
            'status'  => $status,
        ]);
    }
}
