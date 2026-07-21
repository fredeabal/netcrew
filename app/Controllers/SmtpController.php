<?php

namespace App\Controllers;

use App\Controllers\BaseController;

class SmtpController extends BaseController
{
    // ---------------------------------------------------------------------
    // Mostrar formulario de ajustes SMTP
    // ---------------------------------------------------------------------
    public function smtp()
    {
        $settings = service('settings');

        $data = [
            'fromEmail'   => $settings->get('Email.fromEmail'),
            'fromName'    => $settings->get('Email.fromName'),
            'smtp_host'   => $settings->get('Email.SMTPHost') ?: 'smtp.gmail.com',
            'smtp_user'   => $settings->get('Email.SMTPUser'),
            'smtp_pass'   => !empty($settings->get('Email.SMTPPass')) ? '********' : '',
            'smtp_port'   => $settings->get('Email.SMTPPort') ?: 587,
            'smtp_crypto' => $settings->get('Email.SMTPCrypto') ?: 'tls',
            'mailType'    => $settings->get('Email.mailType') ?: 'html',
        ];

        echo view('template/header');
        echo view('settings/smtp', $data);
        echo view('template/footer');
    }

    // ---------------------------------------------------------------------
    // Actualizar ajustes SMTP
    // ---------------------------------------------------------------------
    public function smtpUpdate()
    {
        // Validar datos
        $rules = [
            'smtp_host' => 'required',
            'smtp_port' => 'required|numeric',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $settings = service('settings');

        // Guardar valores en la BD
        $settings->set('Email.fromEmail', $this->request->getPost('fromEmail'));
        $settings->set('Email.fromName', $this->request->getPost('fromName'));
        $settings->set('Email.SMTPHost', $this->request->getPost('smtp_host'));
        $settings->set('Email.SMTPUser', $this->request->getPost('smtp_user'));
        
        // Solo actualizar contraseña si se proporciona una nueva y validamos que no sea la máscara '********'
        $pass = $this->request->getPost('smtp_pass');
        if (!empty($pass) && $pass !== '********') {
            try {
                $encrypter = \Config\Services::encrypter();
                $encrypted = base64_encode($encrypter->encrypt($pass));
                $settings->set('Email.SMTPPass', $encrypted);
            } catch (\Exception $e) {
                log_message('error', 'Error al encriptar contraseña SMTP: ' . $e->getMessage());
            }
        }

        $settings->set('Email.SMTPPort', (int)$this->request->getPost('smtp_port'));
        $settings->set('Email.SMTPCrypto', $this->request->getPost('smtp_crypto'));
        $settings->set('Email.mailType', $this->request->getPost('mailType'));

        return redirect()->back()->with('message', 'Ajustes SMTP guardados exitosamente.');
    }

    // ---------------------------------------------------------------------
    // Enviar correo de prueba SMTP
    // ---------------------------------------------------------------------
    public function smtpTest()
    {
        // En CodeIgniter Shield obtenemos el email principal
        $user = auth()->user();
        $adminEmail = '';
        if ($user) {
            $identities = $user->getIdentities();
            if (!empty($identities)) {
                $adminEmail = $identities[0]->secret;
            }
        }
        
        if (empty($adminEmail)) {
            return redirect()->back()->with('error', 'No se encontró un email válido asociado a tu cuenta.');
        }

        $settings = service('settings');
        $emailService = \Config\Services::email();
        
        $host = $this->request->getPost('smtp_host') ?: $settings->get('Email.SMTPHost');
        $userSmtp = $this->request->getPost('smtp_user') ?: $settings->get('Email.SMTPUser');
        $port = $this->request->getPost('smtp_port') ?: $settings->get('Email.SMTPPort');
        $crypto = $this->request->getPost('smtp_crypto') ?: $settings->get('Email.SMTPCrypto');

        $passInput = (string)$this->request->getPost('smtp_pass');
        $decryptedPass = '';
        if (!empty($passInput) && $passInput !== '********') {
            $decryptedPass = $passInput;
        } else {
            $encryptedPass = $settings->get('Email.SMTPPass');
            if (!empty($encryptedPass)) {
                try {
                    $encrypter = \Config\Services::encrypter();
                    $decryptedPass = $encrypter->decrypt(base64_decode($encryptedPass));
                } catch (\Exception $e) {
                    log_message('error', 'Error al desencriptar contraseña SMTP para prueba: ' . $e->getMessage());
                }
            }
        }
        
        // Cargar configuración para la prueba
        $config = [
            'protocol'  => 'smtp',
            'SMTPHost'  => $host,
            'SMTPUser'  => $userSmtp,
            'SMTPPass'  => $decryptedPass,
            'SMTPPort'  => (int) $port,
            'SMTPCrypto'=> $crypto,
            'mailType'  => $settings->get('Email.mailType') ?: 'html',
            'charset'   => 'utf-8',
            'newline'   => "\r\n",
        ];

        $emailService->initialize($config);

        $fromEmail = $this->request->getPost('fromEmail') ?: ($settings->get('Email.fromEmail') ?: ($config['SMTPUser'] ?: 'no-reply@tudominio.com'));
        $fromName  = $this->request->getPost('fromName') ?: ($settings->get('Email.fromName') ?: 'Prueba de Sistema');
        
        $emailService->setFrom($fromEmail, $fromName);
        $emailService->setTo($adminEmail);
        $emailService->setSubject('Prueba de Conexión SMTP');
        $logoUrl = base_url('assets/images/logos/dark-logo.svg');
        $appUrl = base_url();
        $emailService->setMessage('
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <style>
        :root { color-scheme: light; }
    </style>
</head>
<body style="background-color: #ffffff; margin: 0; padding: 0; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #ffffff; background-image: linear-gradient(#ffffff, #ffffff); margin: 0; padding: 40px 20px; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;">
        <tr>
            <td align="center">
                <img src="' . $logoUrl . '" alt="Logo" style="max-width: 180px; margin-bottom: 30px; display: block;">
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width: 500px; background-color: #f8f9fa; background-image: linear-gradient(#f8f9fa, #f8f9fa); border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #e9ecef;">
                    <tr>
                        <td align="left" style="padding: 40px;">
                            <h2 style="color: #333f52; -webkit-text-fill-color: #333f52; margin-top: 0; text-align: center; font-weight: 600;">Prueba Exitosa</h2>
                            <p style="color: #5a6a85; -webkit-text-fill-color: #5a6a85; font-size: 16px; line-height: 1.6; text-align: center; margin-bottom: 25px;">
                                Si estás leyendo este correo, significa que tu configuración SMTP en el sistema está funcionando perfectamente.
                            </p>
                            <div style="text-align: center;">
                                <a href="' . $appUrl . '" style="display: inline-block; padding: 12px 24px; background-color: #F38020; background-image: linear-gradient(#F38020, #F38020); color: #ffffff; -webkit-text-fill-color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px;">
                                    Ir a la aplicación
                                </a>
                            </div>
                        </td>
                    </tr>
                </table>
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width: 500px; margin-top: 20px;">
                    <tr>
                        <td align="center" style="padding: 0 20px;">
                            <p style="color: #8c98a4; -webkit-text-fill-color: #8c98a4; font-size: 11px; line-height: 1.5; margin: 0;">
                                &copy; ' . date('Y') . ' NetCrew
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
        ');

        if ($emailService->send()) {
            return redirect()->to(base_url('settings/smtp'))->with('message', "¡Conexión exitosa!<br><small class='text-muted'>Se ha enviado un correo de prueba a {$adminEmail}</small>");
        } else {
            $errorMsg = $emailService->printDebugger(['headers']);
            log_message('error', 'Error en test SMTP: ' . $errorMsg);
            return redirect()->to(base_url('settings/smtp'))->with('error', 'Error al conectar con SMTP.');
        }
    }
}
