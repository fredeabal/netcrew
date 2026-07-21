<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title><?= lang('Auth.emailActivateSubject') ?></title>
    <style>
        :root { color-scheme: light; }
    </style>
</head>
<body style="background-color: #ffffff; margin: 0; padding: 0; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #ffffff; background-image: linear-gradient(#ffffff, #ffffff); margin: 0; padding: 40px 20px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
        <tr>
            <td align="center">
                <img src="<?= base_url('assets/images/logos/dark-logo.svg') ?>" alt="Logo" style="max-width: 180px; margin-bottom: 30px; display: block;">
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width: 500px; background-color: #f8f9fa; background-image: linear-gradient(#f8f9fa, #f8f9fa); border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #e9ecef;">
                    <tr>
                        <td align="left" style="padding: 40px;">
                            <h2 style="color: #333f52; -webkit-text-fill-color: #333f52; margin-top: 0; text-align: center; font-weight: 600;">Activa tu Cuenta</h2>
                            
                            <p style="color: #5a6a85; -webkit-text-fill-color: #5a6a85; font-size: 16px; line-height: 1.6; text-align: center; margin-bottom: 15px;">
                                Hola <?= esc($user->username ?? '') ?>,
                            </p>
                            
                            <p style="color: #5a6a85; -webkit-text-fill-color: #5a6a85; font-size: 16px; line-height: 1.6; text-align: center; margin-bottom: 25px;">
                                Gracias por registrarte. Para completar la creación de tu cuenta y activar tu acceso, por favor introduce el siguiente código de confirmación:
                            </p>
                            
                            <div style="text-align: center; margin: 32px 0;">
                                <div style="background-color: #ffffff; color: #F38020; padding: 16px 32px; border-radius: 8px; font-weight: 700; display: inline-block; font-size: 32px; letter-spacing: 6px; border: 1px solid #F38020;">
                                    <?= $code ?>
                                </div>
                            </div>
                            
                            <p style="color: #5a6a85; -webkit-text-fill-color: #5a6a85; font-size: 14px; line-height: 1.6; text-align: center; margin-bottom: 15px;">
                                Este código de activación expirará en 24 horas.
                            </p>
                            
                            <p style="color: #8c98a4; -webkit-text-fill-color: #8c98a4; font-size: 13px; line-height: 1.6; text-align: center; margin-bottom: 0;">
                                Si no solicitaste esta activación, puedes ignorar este correo con total seguridad.
                            </p>
                        </td>
                    </tr>
                </table>
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width: 500px; margin-top: 20px;">
                    <tr>
                        <td align="center" style="padding: 0 20px;">
                            <p style="color: #8c98a4; -webkit-text-fill-color: #8c98a4; font-size: 11px; line-height: 1.5; margin: 0;">
                                &copy; <?= date('Y') ?> NetCrew
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
