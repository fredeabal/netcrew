<?php

namespace App\Libraries;

class Email extends \CodeIgniter\Email\Email
{
    /**
     * Asegura que el puerto SMTP siempre sea de tipo entero para evitar TypeErrors.
     */
    public function initialize($config = null)
    {
        $settings = service('settings');
        
        // Si hay un host SMTP en los ajustes, forzamos usar esos datos globalmente
        $smtpHost = $settings->get('Email.SMTPHost');
        if (!empty($smtpHost)) {
            $decryptedPass = '';
            $encryptedPass = $settings->get('Email.SMTPPass');
            if (!empty($encryptedPass)) {
                try {
                    $encrypter = \Config\Services::encrypter();
                    $decryptedPass = $encrypter->decrypt(base64_decode($encryptedPass));
                } catch (\Exception $e) {
                    log_message('error', 'Error desencriptando SMTP pass global: ' . $e->getMessage());
                }
            }

            $dbConfig = [
                'protocol'   => 'smtp',
                'SMTPHost'   => $smtpHost,
                'SMTPUser'   => $settings->get('Email.SMTPUser'),
                'SMTPPass'   => $decryptedPass,
                'SMTPPort'   => (int) $settings->get('Email.SMTPPort'),
                'SMTPCrypto' => $settings->get('Email.SMTPCrypto') ?: 'tls',
                'mailType'   => $settings->get('Email.mailType') ?: 'html',
                'charset'    => 'utf-8',
                'newline'    => "\r\n",
            ];

            // Mezclar configuración original con la de la BD
            if (is_array($config)) {
                $config = array_merge($config, $dbConfig);
            } elseif (is_object($config)) {
                foreach ($dbConfig as $k => $v) {
                    $config->{$k} = $v;
                }
            } else {
                $config = $dbConfig;
            }
        }

        if (is_object($config) && isset($config->SMTPPort)) {
            $config->SMTPPort = (int) $config->SMTPPort;
        } elseif (is_array($config) && isset($config['SMTPPort'])) {
            $config['SMTPPort'] = (int) $config['SMTPPort'];
        }

        parent::initialize($config);

        $this->SMTPPort = (int) $this->SMTPPort;
        
        // Aplicar From email y name si están definidos en la BD
        $fromEmail = $settings->get('Email.fromEmail');
        $fromName  = $settings->get('Email.fromName');
        if (!empty($fromEmail)) {
            $this->setFrom($fromEmail, $fromName ?: '');
        }

        return $this;
    }
}
