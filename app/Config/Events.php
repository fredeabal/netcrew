<?php

namespace Config;

use CodeIgniter\Events\Events;
use CodeIgniter\Exceptions\FrameworkException;
use CodeIgniter\HotReloader\HotReloader;

/*
 * --------------------------------------------------------------------
 * Application Events
 * --------------------------------------------------------------------
 * Events allow you to tap into the execution of the program without
 * modifying or extending core files. This file provides a central
 * location to define your events, though they can always be added
 * at run-time, also, if needed.
 *
 * You create code that can execute by subscribing to events with
 * the 'on()' method. This accepts any form of callable, including
 * Closures, that will be executed when the event is triggered.
 *
 * Example:
 *      Events::on('create', [$myInstance, 'myMethod']);
 */

Events::on('pre_system', static function (): void {
    if (ENVIRONMENT !== 'testing') {
        $value = ini_get('zlib.output_compression');

        if (filter_var($value, FILTER_VALIDATE_BOOLEAN) || (int) $value > 0) {
            throw FrameworkException::forEnabledZlibOutputCompression();
        }

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        ob_start(static fn ($buffer) => $buffer);
    }

    /*
     * --------------------------------------------------------------------
     * Debug Toolbar Listeners.
     * --------------------------------------------------------------------
     * If you delete, they will no longer be collected.
     */
    if (CI_DEBUG && ! is_cli()) {
        Events::on('DBQuery', 'CodeIgniter\Debug\Toolbar\Collectors\Database::collect');
        service('toolbar')->respond();
        // Hot Reload route - for framework use on the hot reloader.
        if (ENVIRONMENT === 'development') {
            service('routes')->get('__hot-reload', static function (): void {
                (new HotReloader())->run();
            });
        }
    }
});

// --------------------------------------------------------------------
// Eventos de Auditoría de Autenticación
// --------------------------------------------------------------------
Events::on('login', static function ($user): void {
    \App\Models\AuditLogModel::log('LOGIN_SUCCESS', 'Inicio de sesión exitoso', $user->id ?? null, $user->username ?? $user->email ?? 'Usuario');
});

Events::on('failedLogin', static function ($credentials): void {
    $identifier = $credentials['email'] ?? $credentials['username'] ?? 'Desconocido';
    \App\Models\AuditLogModel::log('LOGIN_FAILED', "Intento fallido de inicio de sesión para: {$identifier}");
});

Events::on('logout', static function ($user): void {
    \App\Models\AuditLogModel::log('LOGOUT', 'Cierre de sesión', $user->id ?? null, $user->username ?? $user->email ?? 'Usuario');
});

