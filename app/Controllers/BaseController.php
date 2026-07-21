<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 *
 * Extend this class in any new controllers:
 * ```
 *     class Home extends BaseController
 * ```
 *
 * For security, be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */

    // protected $session;

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Load here all helpers you want to be available in your controllers that extend BaseController.
        // Caution: Do not put the this below the parent::initController() call below.
        // $this->helpers = ['form', 'url'];

        // Caution: Do not edit this line.
        parent::initController($request, $response, $logger);

        // Preload any models, libraries, etc, here.
        // $this->session = service('session');
    }

    /**
     * Obtiene una sesión SSH autenticada con el servidor WireGuard usando la configuración global.
     * 
     * Soporta dos métodos de autenticación:
     * - 'password': Autenticación por contraseña tradicional.
     * - 'key': Autenticación por Llave Privada SSH (PEM, OpenSSH, etc.).
     *
     * @param int $timeout Timeout de conexión en segundos (por defecto 5)
     * @return \phpseclib3\Net\SSH2
     * @throws \Exception
     */
    protected function getSshSession(int $timeout = 5): \phpseclib3\Net\SSH2
    {
        $settings = service('settings');
        $host = $settings->get('WireGuard.sshHost');
        $user = $settings->get('WireGuard.sshUser');
        $port = (int)($settings->get('WireGuard.sshPort') ?: 22);
        $authType = $settings->get('WireGuard.sshAuthType') ?: 'password';

        if (empty($host) || empty($user)) {
            throw new \Exception('La configuración del servidor SSH o el usuario está incompleta.');
        }

        $encrypter = \Config\Services::encrypter();

        // Determinar credencial según el tipo de autenticación
        if ($authType === 'key') {
            $encryptedKey = $settings->get('WireGuard.sshPrivateKey');
            if (empty($encryptedKey)) {
                throw new \Exception('No se ha configurado ninguna llave privada SSH.');
            }
            try {
                // Desencriptamos la llave privada
                $privateKeyString = $encrypter->decrypt(base64_decode($encryptedKey));
                // phpseclib3 carga la llave desde el string
                $credential = \phpseclib3\Crypt\PublicKeyLoader::load($privateKeyString);
            } catch (\Exception $e) {
                throw new \Exception('Error al procesar la llave privada SSH: ' . $e->getMessage());
            }
        } else {
            $encryptedPass = $settings->get('WireGuard.sshPassword');
            $password = '';
            if (!empty($encryptedPass)) {
                try {
                    $password = $encrypter->decrypt(base64_decode($encryptedPass));
                } catch (\Exception $e) {
                    throw new \Exception('Error al desencriptar la contraseña SSH: ' . $e->getMessage());
                }
            }
            $credential = $password;
        }

        // Conectar e iniciar sesión
        $ssh = new \phpseclib3\Net\SSH2($host, $port, $timeout);
        if (!$ssh->login($user, $credential)) {
            throw new \Exception('Fallo de autenticación SSH. Verifica el usuario, la contraseña o la llave privada.');
        }

        return $ssh;
    }

    /**
     * Enmascara de forma inteligente un comando con 'sudo' si el usuario no es 'root'.
     *
     * - Si el usuario es 'root', devuelve el comando tal cual.
     * - Si es otro usuario y usa 'key', asume passwordless sudo ("sudo cmd").
     * - Si es otro usuario y usa 'password', inyecta la contraseña ("echo pass | sudo -S cmd").
     *
     * @param string $cmd Comando a ejecutar
     * @return string
     */
    protected function wrapSudoCommand(string $cmd): string
    {
        $settings = service('settings');
        $user = $settings->get('WireGuard.sshUser');
        $authType = $settings->get('WireGuard.sshAuthType') ?: 'password';

        if ($user === 'root') {
            return $cmd;
        }

        if ($authType === 'key') {
            // Autenticación por llave: asumimos que sudo no requiere contraseña
            return "sudo " . $cmd;
        }

        // Autenticación por contraseña: intentamos entubar la contraseña a sudo -S
        $encryptedPass = $settings->get('WireGuard.sshPassword');
        $password = '';
        if (!empty($encryptedPass)) {
            try {
                $encrypter = \Config\Services::encrypter();
                $password = $encrypter->decrypt(base64_decode($encryptedPass));
            } catch (\Exception $e) {
                // Si falla el desencriptado, devolvemos sudo normal
            }
        }

        return !empty($password)
            ? "echo " . escapeshellarg($password) . " | sudo -S -p '' " . $cmd
            : "sudo " . $cmd;
    }
}
