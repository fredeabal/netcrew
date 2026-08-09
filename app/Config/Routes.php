<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'HomeController::index');

// Agrupamos las rutas que requieren autenticación
$routes->group('', ['filter' => 'session'], static function ($routes) {
    // Dashboards
    $routes->get('dashboard', 'DashboardController::index');
    
    // Perfil
    $routes->get('profile', 'ProfileController::index');
    $routes->post('profile/update', 'ProfileController::update');

    // Guía
    $routes->get('guide', 'GuideController::index');
});

// Rutas de administración (requieren permisos extra)
$routes->group('', ['filter' => 'session'], static function ($routes) {
    $routes->get('users', 'UsersController::index', ['filter' => 'permission:admin.users,users.create,users.edit,users.delete']);
    $routes->get('users/create', 'UsersController::create', ['filter' => 'permission:admin.users,users.create']);
    $routes->post('users/store', 'UsersController::store', ['filter' => 'permission:admin.users,users.create']);
    $routes->get('users/edit/(:num)', 'UsersController::edit/$1', ['filter' => 'permission:admin.users,users.edit']);
    $routes->post('users/update/(:num)', 'UsersController::update/$1', ['filter' => 'permission:admin.users,users.edit']);
    $routes->post('users/toggle-active/(:num)', 'UsersController::toggleActive/$1', ['filter' => 'permission:admin.users,users.edit']);
    $routes->post('users/delete/(:num)', 'UsersController::delete/$1', ['filter' => 'permission:admin.users,users.delete']);

    // Rutas de Roles
    $routes->get('roles', 'RolesController::index', ['filter' => 'permission:admin.roles']);
    $routes->get('roles/edit/(:segment)', 'RolesController::edit/$1', ['filter' => 'permission:admin.roles']);
    $routes->post('roles/update/(:segment)', 'RolesController::update/$1', ['filter' => 'permission:admin.roles']);
});

// Rutas de Superadmin (Configuración - requieren permisos)
$routes->group('settings', ['filter' => ['session', 'permission:admin.settings']], static function ($routes) {
    $routes->get('smtp', 'SmtpController::smtp');
    $routes->post('smtp/update', 'SmtpController::smtpUpdate');
    $routes->post('smtp/test', 'SmtpController::smtpTest');
    $routes->get('wireguard', 'WireguardController::wireguard');
    $routes->post('wireguard/update', 'WireguardController::wireguardUpdate');
    $routes->post('wireguard/test', 'WireguardController::wireguardTest');
    $routes->get('maintenance', 'MaintenanceController::maintenance');
    $routes->post('maintenance/clear-ghost-peers', 'MaintenanceController::clearGhostPeers');
    $routes->post('maintenance/restart-wireguard', 'MaintenanceController::restartWireguard');
    $routes->post('maintenance/clear-all', 'MaintenanceController::clearAll');
    $routes->get('maintenance/backup/download', 'MaintenanceController::downloadBackup');
    $routes->post('maintenance/backup/restore', 'MaintenanceController::restoreBackup');
});

// Rutas de Redes (Networks)
$routes->group('networks', ['filter' => 'session'], static function ($routes) {
    $routes->get('', 'NetworkController::index', ['filter' => 'permission:networks.view']);
    $routes->get('create', 'NetworkController::create', ['filter' => 'permission:networks.create']);
    $routes->post('store', 'NetworkController::store', ['filter' => 'permission:networks.create']);
    $routes->get('edit/(:num)', 'NetworkController::edit/$1', ['filter' => 'permission:networks.edit']);
    $routes->post('update/(:num)', 'NetworkController::update/$1', ['filter' => 'permission:networks.edit']);
    $routes->post('delete/(:num)', 'NetworkController::delete/$1', ['filter' => 'permission:networks.delete']);
    $routes->post('toggle-active/(:num)', 'NetworkController::toggleActive/$1', ['filter' => 'permission:networks.edit']);
    $routes->get('(:num)/devices', 'DeviceController::index/$1', ['filter' => 'permission:devices.view']);
});

// Rutas de Dispositivos (Devices)
$routes->group('devices', ['filter' => 'session'], static function ($routes) {
    $routes->get('list/(:num)', 'DeviceController::listByNetwork/$1', ['filter' => 'permission:devices.view']);
    $routes->post('store', 'DeviceController::store', ['filter' => 'permission:devices.create']);
    $routes->post('update/(:num)', 'DeviceController::update/$1', ['filter' => 'permission:devices.edit']);
    $routes->post('delete/(:num)', 'DeviceController::delete/$1', ['filter' => 'permission:devices.delete']);
    $routes->post('ping/(:num)', 'DeviceController::ping/$1', ['filter' => 'permission:devices.view']);
    $routes->post('toggle-active/(:num)', 'DeviceController::toggleActive/$1', ['filter' => 'permission:devices.edit']);
    $routes->get('download/(:num)', 'DeviceController::downloadConfig/$1', ['filter' => 'permission:devices.view']);
    $routes->get('details-json/(:num)', 'DeviceController::getDetailsJson/$1', ['filter' => 'permission:devices.view']);
    $routes->get('realtime-status/(:num)', 'DeviceController::realtimeStatus/$1', ['filter' => 'permission:devices.view']);
    $routes->get('node-details/(:num)', 'DeviceController::nodeDetails/$1', ['filter' => 'permission:devices.view']);
    $routes->get('show/(:num)', 'DeviceController::show/$1', ['filter' => 'permission:devices.view']);
});

// Ruta para cerrar sesión tras activar la cuenta
$routes->get('auth/logout-activated', static function () {
    auth()->logout();
    return redirect()->to('login')->with('message', 'Tu cuenta ha sido activada con éxito. Por favor, inicia sesión.');
});

// Rutas de CodeIgniter Shield
service('auth')->routes($routes);

