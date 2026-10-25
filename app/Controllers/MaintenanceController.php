<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Libraries\WireguardService;
use App\Models\DeviceModel;

class MaintenanceController extends BaseController
{
    // ---------------------------------------------------------------------
    // Mostrar vista de Mantenimiento del Sistema
    // ---------------------------------------------------------------------
    public function maintenance()
    {
        $data = [
            'title' => 'Mantenimiento del Sistema',
        ];

        echo view('template/header', $data);
        echo view('settings/maintenance', $data);
        echo view('template/footer');
    }

    // ---------------------------------------------------------------------
    // Sincronización y reconciliación de la configuración de WireGuard
    // ---------------------------------------------------------------------
    public function clearGhostPeers()
    {
        $syncResult = $this->syncWireguardPeers();

        if ($syncResult['success']) {
            return redirect()->to(base_url('settings/maintenance'))->with('message', $syncResult['message']);
        } else {
            return redirect()->to(base_url('settings/maintenance'))->with('error', $syncResult['message']);
        }
    }

    // ---------------------------------------------------------------------
    // Reiniciar servicio WireGuard
    // ---------------------------------------------------------------------
    public function restartWireguard()
    {
        try {
            $wg = WireguardService::fromSettings(10);
            $interface = $wg->getInterface();

            $wg->run('systemctl restart ' . escapeshellarg('wg-quick@' . $interface));

            return redirect()->to(base_url('settings/maintenance'))->with('message', "El servicio WireGuard ({$interface}) ha sido reiniciado correctamente.");
        } catch (\Throwable $e) {
            return redirect()->to(base_url('settings/maintenance'))->with('error', "Error al reiniciar WireGuard: " . esc($e->getMessage()));
        }
    }

    // ---------------------------------------------------------------------
    // Ejecutar todo el mantenimiento del sistema
    // ---------------------------------------------------------------------
    public function clearAll()
    {
        $sessionsCount = $this->cleanDirectory(WRITEPATH . 'session');
        $debugCount = $this->cleanDirectory(WRITEPATH . 'debugbar');
        $logsCount = $this->cleanDirectory(WRITEPATH . 'logs');

        // Optimizar Base de Datos
        $dbMsg = "";
        try {
            $db = \Config\Database::connect();
            $db->query('VACUUM;');
            $dbMsg = "<br><small class='text-muted'>Base de datos SQLite optimizada (VACUUM).</small>";
        } catch (\Exception $e) {
            $dbMsg = "<br><small class='text-danger'>Error al optimizar BD: " . $e->getMessage() . "</small>";
        }

        // Ejecutar la sincronización de WireGuard
        $syncResult = $this->syncWireguardPeers();
        $ghostMsg = "";
        if ($syncResult['success']) {
            $ghostMsg = "<br><small class='text-muted'>" . $syncResult['message'] . "</small>";
        } else {
            $ghostMsg = "<br><small class='text-danger'>Error de sincronización: " . $syncResult['message'] . "</small>";
        }

        return redirect()->to(base_url('settings/maintenance'))->with('message', "Mantenimiento general completado.<br><small class='text-muted'>{$sessionsCount} sesiones, {$debugCount} debugs y {$logsCount} logs eliminados.</small>{$dbMsg}{$ghostMsg}");
    }

    // ---------------------------------------------------------------------
    // Helper: Sincroniza y reconcilia los peers entre la BD y el servidor
    // ---------------------------------------------------------------------
    private function syncWireguardPeers(): array
    {
        if (!WireguardService::isConfigured()) {
            return ['success' => false, 'message' => 'El servidor SSH de WireGuard no está configurado.'];
        }

        try {
            $wg = WireguardService::fromSettings(10);
            $res = $wg->syncPeers();

            $msgParts = [];
            if ($res['removed'] > 0) {
                $msgParts[] = "{$res['removed']} fantasmas eliminados";
            }
            if ($res['restored'] > 0) {
                $msgParts[] = "{$res['restored']} activos restaurados";
            }
            if (empty($msgParts)) {
                $msgParts[] = "servidor ya estaba sincronizado";
            }

            $finalMsg = "Sincronización VPN completada (" . implode(", ", $msgParts) . ")";

            return ['success' => true, 'message' => $finalMsg];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Error WireGuard: ' . $e->getMessage()];
        }
    }

    // ---------------------------------------------------------------------
    // Descargar copia de seguridad de la base de datos (SQLite)
    // ---------------------------------------------------------------------
    public function downloadBackup()
    {
        $dbPath = config('Database')->default['database'];

        if (!file_exists($dbPath)) {
            return redirect()->to(base_url('settings/maintenance'))->with('error', 'El archivo de base de datos no existe.');
        }

        $filename = 'backup-netcrew-' . date('Y-m-d_H-i-s') . '.db';
        $tempBackupPath = sys_get_temp_dir() . '/' . $filename;

        // Crear una copia segura usando VACUUM INTO para evitar corrupción si hay escrituras concurrentes
        try {
            $sqlite = new \SQLite3($dbPath);
            $sqlite->exec("VACUUM INTO '{$tempBackupPath}'");
            $sqlite->close();
        } catch (\Exception $e) {
            return redirect()->to(base_url('settings/maintenance'))->with('error', 'Error al generar la copia segura: ' . $e->getMessage());
        }

        $data = file_get_contents($tempBackupPath);
        unlink($tempBackupPath);

        return $this->response->download($filename, $data);
    }

    // ---------------------------------------------------------------------
    // Restaurar base de datos a partir de un archivo subido
    // ---------------------------------------------------------------------
    public function restoreBackup()
    {
        if ($this->request->getMethod() !== 'POST') {
            return redirect()->to(base_url('settings/maintenance'));
        }

        $file = $this->request->getFile('backup_file');

        if (!$file || !$file->isValid()) {
            return redirect()->back()->with('error', 'Por favor, selecciona un archivo de respaldo válido.');
        }

        $tempPath = $file->getRealPath();

        // 1. Validar firma de cabecera de SQLite3
        $handle = fopen($tempPath, 'rb');
        if (!$handle) {
            return redirect()->back()->with('error', 'No se pudo leer el archivo temporal.');
        }
        $header = fread($handle, 15);
        fclose($handle);

        if ($header !== 'SQLite format 3') {
            return redirect()->back()->with('error', 'El archivo cargado no es una base de datos SQLite válida (cabecera incorrecta).');
        }

        // 2. Validar estructura de tablas de la app
        try {
            $sqlite = new \SQLite3($tempPath);
            // Comprobamos la existencia de las tablas básicas
            $tables = ['users', 'networks', 'devices', 'settings'];
            foreach ($tables as $table) {
                $check = $sqlite->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='{$table}'");
                if (!$check) {
                    $sqlite->close();
                    return redirect()->back()->with('error', "La base de datos cargada no es compatible (falta la tabla '{$table}').");
                }
            }
            $sqlite->close();
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Error al verificar la estructura de la base de datos: ' . $e->getMessage());
        }

        // 3. Proceder con el reemplazo de la BD actual
        $dbPath = config('Database')->default['database'];
        $backupPath = $dbPath . '.bak';
        $walPath    = $dbPath . '-wal';
        $shmPath    = $dbPath . '-shm';

        // Cerrar la conexión actual de CodeIgniter para liberar el archivo sqlite
        $db = \Config\Database::connect();
        $db->close();

        // Crear respaldo temporal del archivo actual
        if (file_exists($dbPath)) {
            if (!copy($dbPath, $backupPath)) {
                return redirect()->back()->with('error', 'No se pudo crear una copia de seguridad temporal antes del reemplazo.');
            }
        }

        // Reemplazar la base de datos activa
        try {
            if (!copy($tempPath, $dbPath)) {
                throw new \Exception('Fallo al copiar el nuevo archivo de base de datos.');
            }

            // Eliminar archivos WAL y SHM antiguos para evitar inconsistencias con la nueva BD
            if (file_exists($walPath)) {
                @unlink($walPath);
            }
            if (file_exists($shmPath)) {
                @unlink($shmPath);
            }

            // Asegurar que la nueva base de datos mantenga el modo WAL
            try {
                $sq = new \SQLite3($dbPath);
                $sq->exec('PRAGMA journal_mode=WAL;');
                $sq->close();
            } catch (\Throwable $t) {
                // No crítico si ya estaba configurado
            }

            // Eliminar copia de seguridad temporal tras éxito
            if (file_exists($backupPath)) {
                unlink($backupPath);
            }
            
            // Cerrar sesión para evitar inconsistencias de usuarios/permisos borrados
            auth()->logout();

            return redirect()->to(base_url('login'))->with('message', "Base de datos restaurada correctamente.<br><small class='text-muted'>Por razones de seguridad, tu sesión ha sido cerrada.</small>");
        } catch (\Throwable $e) {
            // Revertir en caso de fallo
            if (file_exists($backupPath)) {
                copy($backupPath, $dbPath);
                unlink($backupPath);
            }
            return redirect()->back()->with('error', 'Error al restaurar la base de datos: ' . $e->getMessage());
        }
    }

    // ---------------------------------------------------------------------
    // Helper privado para vaciar archivos de un directorio
    // ---------------------------------------------------------------------
    private function cleanDirectory($path)
    {
        $path = rtrim($path, '/') . '/';
        if (!is_dir($path)) {
            return 0;
        }

        $count = 0;
        $files = glob($path . '*');
        $currentSessionId = session_id();

        foreach ($files as $file) {
            if (is_file($file)) {
                $basename = basename($file);
                if ($basename === 'index.html' || $basename === '.gitignore' || $basename === '.htaccess') {
                    continue;
                }
                if ($currentSessionId && strpos($basename, $currentSessionId) !== false) {
                    continue;
                }
                if (@unlink($file)) {
                    $count++;
                }
            }
        }
        return $count;
    }
}
