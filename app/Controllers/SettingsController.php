<?php

namespace App\Controllers;

use App\Libraries\NebulaService;

class SettingsController extends BaseController
{
    public function index(?string $tab = null)
    {
        $requestedTab = $tab ?? (string) $this->request->getGet('tab');
        $allowedTabs  = ['nebula', 'smtp', 'maintenance'];
        $currentTab   = in_array($requestedTab, $allowedTabs, true) ? $requestedTab : 'nebula';

        $settings = service('settings');

        // 1. Nebula
        $binaryStatus     = NebulaService::testAvailability();
        $resolvedEndpoint = NebulaService::resolveLighthouseEndpoint();

        // 2. Mantenimiento
        $dbPath = config('Database')->default['database'];
        $dbSize = file_exists($dbPath) ? (int) filesize($dbPath) : 0;

        $sessionStats       = $this->getDirStats(WRITEPATH . 'session');
        $logsStats          = $this->getDirStats(WRITEPATH . 'logs');
        $cacheStats         = $this->getDirStats(WRITEPATH . 'cache');
        $isNebulaConfigured = NebulaService::isConfigured();

        $data = [
            'title'                   => 'Configuración',
            'currentTab'              => $currentTab,
            // Nebula
            'nebula_lighthouse_host'  => $settings->get('Nebula.lighthouseHost') ?: '',
            'nebula_lighthouse_port'  => (int) ($settings->get('Nebula.lighthousePort') ?: 4242),
            'nebula_cipher'           => $settings->get('Nebula.cipher') ?: 'aes',
            'nebula_relay_enabled'    => (int) ($settings->get('Nebula.relayEnabled') ?? 1),
            'nebula_restricted_cidrs' => $settings->get('Nebula.restrictedCidrs') ?: '',
            'nebula_ssh_host'         => $settings->get('Nebula.sshHost') ?: '',
            'nebula_ssh_port'         => (int) ($settings->get('Nebula.sshPort') ?: 22),
            'nebula_ssh_user'         => $settings->get('Nebula.sshUser') ?: '',
            'nebula_ssh_auth_type'    => $settings->get('Nebula.sshAuthType') ?: 'password',
            'nebula_ssh_password'     => ! empty($settings->get('Nebula.sshPassword')) ? '********' : '',
            'nebula_ssh_private_key'  => ! empty($settings->get('Nebula.sshPrivateKey')) ? '********' : '',
            'binary_status'           => $binaryStatus,
            'resolved_endpoint'       => $resolvedEndpoint,
            // SMTP
            'fromEmail'               => $settings->get('Email.fromEmail'),
            'fromName'                => $settings->get('Email.fromName'),
            'smtp_host'               => $settings->get('Email.SMTPHost') ?: 'smtp.gmail.com',
            'smtp_user'               => $settings->get('Email.SMTPUser'),
            'smtp_pass'               => ! empty($settings->get('Email.SMTPPass')) ? '********' : '',
            'smtp_port'               => $settings->get('Email.SMTPPort') ?: 587,
            'smtp_crypto'             => $settings->get('Email.SMTPCrypto') ?: 'tls',
            'mailType'                => $settings->get('Email.mailType') ?: 'html',
            // Mantenimiento
            'stats' => [
                'dbSize'         => $this->formatBytes($dbSize),
                'sessionCount'   => $sessionStats['count'],
                'sessionSize'    => $this->formatBytes($sessionStats['size']),
                'logsCount'      => $logsStats['count'],
                'logsSize'       => $this->formatBytes($logsStats['size']),
                'cacheCount'     => $cacheStats['count'],
                'cacheSize'      => $this->formatBytes($cacheStats['size']),
                'isWgConfigured' => $isNebulaConfigured,
                'isWgUp'         => $isNebulaConfigured,
            ],
        ];

        echo view('template/header', $data);
        echo view('settings/index', $data);
        echo view('template/footer');
    }

    private function getDirStats(string $dir): array
    {
        $count = 0;
        $size  = 0;

        if (! is_dir($dir)) {
            return ['count' => 0, 'size' => 0];
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            $name = $file->getFilename();
            if ($file->isFile() && $name !== 'index.html' && $name !== '.htaccess') {
                $count++;
                $size += $file->getSize();
            }
        }

        return ['count' => $count, 'size' => $size];
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow   = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow   = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
