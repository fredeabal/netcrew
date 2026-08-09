<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * BaseController proporciona helpers y utilidades comunes para los controladores.
 */
abstract class BaseController extends Controller
{
    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
    }

    // ---------------------------------------------------------------------
    // Sesión SSH
    // ---------------------------------------------------------------------

    /**
     * Obtiene una sesión SSH autenticada con el servidor WireGuard usando la configuración global de la BD.
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
        $host     = $settings->get('WireGuard.sshHost');
        $user     = $settings->get('WireGuard.sshUser');
        $port     = (int)($settings->get('WireGuard.sshPort') ?: 22);
        $authType = $settings->get('WireGuard.sshAuthType') ?: 'password';

        if (empty($host) || empty($user)) {
            throw new \Exception('La configuración del servidor SSH o el usuario está incompleta.');
        }

        $encrypter  = \Config\Services::encrypter();
        $credential = '';

        if ($authType === 'key') {
            $encryptedKey = $settings->get('WireGuard.sshPrivateKey');
            if (empty($encryptedKey)) {
                throw new \Exception('No se ha configurado ninguna llave privada SSH.');
            }
            try {
                $privateKeyString = $encrypter->decrypt(base64_decode($encryptedKey));
                $credential       = \phpseclib3\Crypt\PublicKeyLoader::load($privateKeyString);
            } catch (\Exception $e) {
                throw new \Exception('Error al procesar la llave privada SSH: ' . $e->getMessage());
            }
        } else {
            $encryptedPass = $settings->get('WireGuard.sshPassword');
            if (!empty($encryptedPass)) {
                try {
                    $credential = $encrypter->decrypt(base64_decode($encryptedPass));
                } catch (\Exception $e) {
                    throw new \Exception('Error al desencriptar la contraseña SSH: ' . $e->getMessage());
                }
            }
        }

        return $this->getSshSessionWithParams($host, $user, $port, $authType, $credential, $timeout);
    }

    /**
     * Igual que getSshSession() pero usando credenciales explícitas en lugar de leerlas de la BD.
     * Se usa en wireguardTest() para no persistir settings temporales durante la prueba.
     *
     * @param string $host
     * @param string $user
     * @param int    $port
     * @param string $authType   'password' | 'key'
     * @param mixed  $credential Contraseña en texto plano, llave privada en texto plano o instancia de PublicKeyLoader
     * @param int    $timeout
     * @return \phpseclib3\Net\SSH2
     * @throws \Exception
     */
    protected function getSshSessionWithParams(string $host, string $user, int $port, string $authType, mixed $credential, int $timeout = 10): \phpseclib3\Net\SSH2
    {
        if (empty($host) || empty($user)) {
            throw new \Exception('El host o el usuario SSH están vacíos.');
        }

        if ($authType === 'key' && is_string($credential)) {
            if (empty($credential)) {
                throw new \Exception('No se ha proporcionado ninguna llave privada SSH.');
            }
            try {
                $credential = \phpseclib3\Crypt\PublicKeyLoader::load($credential);
            } catch (\Exception $e) {
                throw new \Exception('Error al procesar la llave privada SSH: ' . $e->getMessage());
            }
        }

        $ssh = new \phpseclib3\Net\SSH2($host, $port, $timeout);
        if (!$ssh->login($user, $credential)) {
            throw new \Exception('Fallo de autenticación SSH. Verifica el usuario, la contraseña o la llave privada.');
        }

        return $ssh;
    }

    // ---------------------------------------------------------------------
    // Utilidades de comandos SSH
    // ---------------------------------------------------------------------

    /**
     * Enmascara de forma segura un comando con 'sudo' si el usuario no es 'root'.
     *
     * - Si el usuario es 'root', devuelve el comando tal cual.
     * - Si es otro usuario y usa 'key', asume passwordless sudo ("sudo cmd").
     * - Si es otro usuario y usa 'password', inyecta la contraseña de forma segura usando printf.
     *
     * @param string      $cmd              Comando a ejecutar
     * @param string|null $overridePassword Permite forzar una contraseña explícita (para pruebas sin persistir en BD)
     * @return string
     */
    protected function wrapSudoCommand(string $cmd, ?string $overridePassword = null): string
    {
        $settings = service('settings');
        $user     = $settings->get('WireGuard.sshUser');
        $authType = $settings->get('WireGuard.sshAuthType') ?: 'password';

        if ($user === 'root') {
            return $cmd;
        }

        if ($authType === 'key') {
            return "sudo " . $cmd;
        }

        // Obtener contraseña: primero el override explícito, luego la BD
        $password = $overridePassword;
        if ($password === null) {
            $encryptedPass = $settings->get('WireGuard.sshPassword');
            if (!empty($encryptedPass)) {
                try {
                    $encrypter = \Config\Services::encrypter();
                    $password  = $encrypter->decrypt(base64_decode($encryptedPass));
                } catch (\Exception $e) {
                    $password = '';
                }
            }
        }

        if (!empty($password)) {
            // Usamos printf '%s\n' en lugar de echo para evitar que caracteres especiales
            // como '!', '$', '\' o '"' sean interpretados por Bash antes de pasar a sudo
            $escapedPassword = escapeshellarg($password);
            return "printf '%s\\n' {$escapedPassword} | sudo -S -p '' " . $cmd;
        }

        return "sudo " . $cmd;
    }
}
