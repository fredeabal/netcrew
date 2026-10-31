<?php

namespace App\Controllers;

use App\Libraries\WireguardService;
use App\Models\DeviceModel;
use App\Models\NetworkModel;

class DeviceController extends BaseController
{
    protected $deviceModel;
    protected $networkModel;

    public function __construct()
    {
        $this->deviceModel = new DeviceModel();
        $this->networkModel = new NetworkModel();
    }

    // ---------------------------------------------------------------------
    // Mostrar listado de nodos / dispositivos en una página dedicada
    // ---------------------------------------------------------------------
    public function index($networkId)
    {
        $network = $this->networkModel->find($networkId);

        if (!$network) {
            return redirect()->to('/networks')->with('error', 'Red no encontrada.');
        }

        // Validar acceso / propiedad de la red
        if (!$this->hasNetworkAccess($network, 'view')) {
            return redirect()->to('/networks')->with('error', 'No tienes permisos para administrar los nodos de esta red.');
        }

        $devices = $this->deviceModel->where('network_id', $networkId)->findAll();

        // Ordenar dispositivos correctamente por IP (evitando ordenamiento alfabético de SQLite)
        usort($devices, function($a, $b) {
            return ip2long($a->ip_address) <=> ip2long($b->ip_address);
        });

        $data = [
            'title'   => 'Administrar Nodos - ' . $network->name,
            'network' => $network,
            'devices' => $devices
        ];

        echo view('template/header', $data);
        echo view('devices/index');
        echo view('template/footer');
    }

    // ---------------------------------------------------------------------
    // Mostrar formulario HTML para crear un nuevo nodo
    // ---------------------------------------------------------------------
    public function create($networkId)
    {
        $network = $this->networkModel->find($networkId);
        if (!$network) {
            return redirect()->to('/networks')->with('error', 'Red no encontrada.');
        }

        if (!$this->hasNetworkAccess($network, 'create')) {
            return redirect()->to('/networks/' . $networkId . '/devices')->with('error', 'No tienes permisos para agregar nodos a esta red.');
        }

        $data = [
            'title'   => 'Nuevo Nodo - ' . $network->name,
            'network' => $network,
        ];

        echo view('template/header', $data);
        echo view('devices/create', $data);
        echo view('template/footer');
    }

    // ---------------------------------------------------------------------
    // Mostrar formulario HTML para editar un nodo
    // ---------------------------------------------------------------------
    public function edit($id)
    {
        $device = $this->deviceModel->find($id);
        if (!$device) {
            return redirect()->to('/networks')->with('error', 'Dispositivo no encontrado.');
        }

        $network = $this->networkModel->find($device->network_id);
        if (!$network) {
            return redirect()->to('/networks')->with('error', 'Red no encontrada.');
        }

        if (!$this->hasNetworkAccess($network, 'edit')) {
            return redirect()->to('/devices/show/' . $id)->with('error', 'No tienes permisos para editar este nodo.');
        }

        $data = [
            'title'   => 'Editar Nodo - ' . $device->name,
            'device'  => $device,
            'network' => $network,
        ];

        echo view('template/header', $data);
        echo view('devices/edit', $data);
        echo view('template/footer');
    }

    // ---------------------------------------------------------------------
    // Mostrar página de detalles de un nodo
    // ---------------------------------------------------------------------
    public function show($id)
    {
        $device = $this->deviceModel->find($id);
        if (!$device) {
            return redirect()->to('/networks')->with('error', 'Dispositivo no encontrado.');
        }

        $network = $this->networkModel->find($device->network_id);
        if (!$network) {
            return redirect()->to('/networks')->with('error', 'Red no encontrada.');
        }

        // Validar acceso / propiedad de la red
        if (!$this->hasNetworkAccess($network, 'view')) {
            return redirect()->to('/networks')->with('error', 'Acceso denegado.');
        }

        // Intentar obtener datos WireGuard en tiempo real
        $wg = $this->fetchPeerStatus($device);
        if ($wg && !empty($wg['last_seen'])) {
            $device->last_seen = $wg['last_seen'];
        }

        $data = [
            'title'   => 'Nodo: ' . $device->name,
            'device'  => $device,
            'network' => $network,
            'wg'      => $wg,
        ];

        echo view('template/header', $data);
        echo view('devices/show');
        echo view('template/footer');
    }

    // ---------------------------------------------------------------------
    // Obtener listado de nodos de una red en formato JSON
    // ---------------------------------------------------------------------
    public function listByNetwork($networkId)
    {
        $network = $this->networkModel->find($networkId);

        if (!$network) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Red no encontrada.'
            ])->setStatusCode(404);
        }

        // Validar acceso / propiedad
        if (!$this->hasNetworkAccess($network, 'view')) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'No tienes permisos para ver los nodos de esta red.'
            ])->setStatusCode(403);
        }

        $devices = $this->deviceModel->where('network_id', $networkId)->findAll();

        // Eliminar la private_key (aunque esté cifrada) para no exponerla en el cliente JS
        $safeDevices = array_map(function($device) {
            unset($device->private_key);
            return $device;
        }, $devices);

        return $this->response->setJSON([
            'success' => true,
            'devices' => $safeDevices
        ]);
    }

    // ---------------------------------------------------------------------
    // Crear y registrar un nuevo dispositivo / nodo
    // ---------------------------------------------------------------------
    public function store()
    {
        $isAjax = $this->request->isAJAX();

        if ($this->request->getMethod() !== 'POST') {
            if (!$isAjax) {
                return redirect()->to('/networks')->with('error', 'Método no permitido.');
            }
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Método no permitido.'
            ])->setStatusCode(405);
        }

        $rules = [
            'network_id'   => 'required|is_natural_no_zero',
            'name'         => 'required|min_length[2]|max_length[100]',
            'device_type'  => 'permit_empty|in_list[pc,server,mobile,tablet,router]',
            'routing_mode' => 'permit_empty|in_list[split,full]',
            'expiration'   => 'permit_empty|in_list[never,1d,7d,30d]',
        ];

        if (!$this->validate($rules)) {
            $msg = implode(' ', $this->validator->getErrors());
            if (!$isAjax) {
                return redirect()->back()->withInput()->with('error', $msg);
            }
            return $this->response->setJSON([
                'success' => false,
                'message' => $msg,
                'errors'  => $this->validator->getErrors()
            ])->setStatusCode(400);
        }

        $networkId   = (int) $this->request->getPost('network_id');
        $name        = trim((string) $this->request->getPost('name'));
        $deviceType  = (string) ($this->request->getPost('device_type') ?: 'pc');
        $routingMode = (string) ($this->request->getPost('routing_mode') ?: 'split');
        $expiration  = (string) ($this->request->getPost('expiration') ?: 'never');

        $expiresAt = null;
        if ($expiration === '1d') {
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 day'));
        } elseif ($expiration === '7d') {
            $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));
        } elseif ($expiration === '30d') {
            $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
        }

        // Validar que SSH esté configurado a nivel global antes de crear nodos
        if (!WireguardService::isConfigured()) {
            $msg = 'No se pueden crear nodos: El Administrador no ha configurado la conexión SSH del servidor VPN.';
            if (!$isAjax) {
                return redirect()->back()->withInput()->with('error', $msg);
            }
            return $this->response->setJSON([
                'success' => false,
                'message' => $msg
            ])->setStatusCode(403);
        }

        $network = $this->networkModel->find($networkId);
        if (!$network) {
            if (!$isAjax) {
                return redirect()->to('/networks')->with('error', 'Red no encontrada.');
            }
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Red no encontrada.'
            ])->setStatusCode(404);
        }

        // Validar que la red esté activa
        if (!$network->active) {
            $msg = 'No se pueden agregar nodos a una red desactivada.';
            if (!$isAjax) {
                return redirect()->back()->withInput()->with('error', $msg);
            }
            return $this->response->setJSON([
                'success' => false,
                'message' => $msg
            ])->setStatusCode(400);
        }

        // Validar permisos para crear nodos en esta red
        if (!$this->hasNetworkAccess($network, 'create')) {
            $msg = 'No tienes permisos para agregar nodos a esta red.';
            if (!$isAjax) {
                return redirect()->to('/networks/' . $networkId . '/devices')->with('error', $msg);
            }
            return $this->response->setJSON([
                'success' => false,
                'message' => $msg
            ])->setStatusCode(403);
        }

        // Validar capacidad de la red (/24 = máx 253 nodos)
        $currentNodesCount = $this->deviceModel->where('network_id', $networkId)->countAllResults();
        if ($currentNodesCount >= 253) {
            $msg = 'La red ha alcanzado su capacidad máxima (253 nodos).';
            if (!$isAjax) {
                return redirect()->back()->withInput()->with('error', $msg);
            }
            return $this->response->setJSON([
                'success' => false,
                'message' => $msg
            ])->setStatusCode(400);
        }

        // Obtener IPs existentes en esta red para evitar colisiones
        $existingDevices = $this->deviceModel->where('network_id', $networkId)->findAll();
        $existingIps = [];
        foreach ($existingDevices as $dev) {
            $existingIps[] = $dev->ip_address;
        }

        // Generar la siguiente IP disponible
        $ipAddress = $this->generateNextAvailableIp($network->cidr, $existingIps);
        if (!$ipAddress) {
            $msg = 'No hay direcciones IP disponibles en el rango de esta red.';
            if (!$isAjax) {
                return redirect()->back()->withInput()->with('error', $msg);
            }
            return $this->response->setJSON([
                'success' => false,
                'message' => $msg
            ])->setStatusCode(400);
        }

        // Generar par de llaves WireGuard (Curve25519) con clamping estándar RFC 7748
        try {
            $keypair = WireguardService::generateKeyPair();
            $privateKey = $keypair['privateKey'];
            $publicKey  = $keypair['publicKey'];
        } catch (\Throwable $e) {
            $msg = 'Error al generar llaves criptográficas: ' . $e->getMessage();
            if (!$isAjax) {
                return redirect()->back()->withInput()->with('error', $msg);
            }
            return $this->response->setJSON([
                'success' => false,
                'message' => $msg
            ])->setStatusCode(500);
        }

        // Registrar dispositivo
        $data = [
            'network_id'   => $networkId,
            'name'         => $name,
            'ip_address'   => $ipAddress,
            'public_key'   => $publicKey,
            'private_key'  => $privateKey,
            'active'       => 1,
            'device_type'  => $deviceType,
            'routing_mode' => $routingMode,
            'expires_at'   => $expiresAt,
        ];

        try {
            if (!$this->deviceModel->save($data)) {
                $msg = 'Error al registrar el dispositivo.';
                if (!$isAjax) {
                    return redirect()->back()->withInput()->with('error', $msg);
                }
                return $this->response->setJSON([
                    'success' => false,
                    'message' => $msg,
                    'errors'  => $this->deviceModel->errors()
                ])->setStatusCode(400);
            }
        } catch (\Throwable $e) {
            log_message('error', 'Error al guardar dispositivo en BD: ' . $e->getMessage());
            $msg = 'Conflicto de asignación: la dirección IP o la clave ya existen en la red. Intenta nuevamente.';
            if (!$isAjax) {
                return redirect()->back()->withInput()->with('error', $msg);
            }
            return $this->response->setJSON([
                'success' => false,
                'message' => $msg,
            ])->setStatusCode(409);
        }

        $insertId  = $this->deviceModel->getInsertID();
        $deviceObj = $this->deviceModel->find($insertId);

        // Registrar el peer en el servidor. Si falla, se revierte el alta en la BD.
        try {
            $this->pushPeer($deviceObj, true);
            \App\Models\AuditLogModel::log('DEVICE_CREATE', "Creó el nodo '{$data['name']}' ({$data['ip_address']}) en red ID: {$networkId}");
        } catch (\Throwable $e) {
            log_message('error', 'No se pudo registrar el nodo ' . $insertId . ' en WireGuard: ' . $e->getMessage());
            $this->deviceModel->delete($insertId);
            $this->bestEffort(fn () => $this->pushPeer($deviceObj, false), 'revertir alta del nodo ' . $insertId);

            $msg = 'No se pudo registrar el nodo en el servidor VPN. No se ha creado. Detalle: ' . $e->getMessage();
            if (!$isAjax) {
                return redirect()->back()->withInput()->with('error', $msg);
            }
            return $this->response->setJSON([
                'success' => false,
                'message' => $msg,
            ])->setStatusCode(502);
        }

        if (!$isAjax) {
            return redirect()->to('/networks/' . $networkId . '/devices')->with('message', 'Dispositivo agregado correctamente.');
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Dispositivo agregado correctamente.',
            'device'  => [
                'id'         => $insertId,
                'name'       => $data['name'],
                'ip_address' => $data['ip_address'],
            ]
        ]);
    }

    // ---------------------------------------------------------------------
    // Actualizar un dispositivo (Nombre / Tipo)
    // ---------------------------------------------------------------------
    public function update($id)
    {
        $isAjax = $this->request->isAJAX();

        if ($this->request->getMethod() !== 'POST') {
            if (!$isAjax) {
                return redirect()->to('/networks')->with('error', 'Método no permitido.');
            }
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Método no permitido.'
            ])->setStatusCode(405);
        }

        $device = $this->deviceModel->find($id);
        if (!$device) {
            if (!$isAjax) {
                return redirect()->to('/networks')->with('error', 'Dispositivo no encontrado.');
            }
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Dispositivo no encontrado.'
            ])->setStatusCode(404);
        }

        $network = $this->networkModel->find($device->network_id);
        if (!$network) {
            if (!$isAjax) {
                return redirect()->to('/networks')->with('error', 'Red no encontrada.');
            }
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Red no encontrada.'
            ])->setStatusCode(404);
        }

        // Validar acceso / propiedad
        if (!$this->hasNetworkAccess($network, 'edit')) {
            if (!$isAjax) {
                return redirect()->to('/devices/show/' . $id)->with('error', 'No tienes permisos para editar este nodo.');
            }
            return $this->response->setJSON([
                'success' => false,
                'message' => 'No tienes permisos para editar este nodo.'
            ])->setStatusCode(403);
        }

        $rules = [
            'name'         => 'required|min_length[2]|max_length[100]',
            'device_type'  => 'required|in_list[pc,server,mobile,tablet,router]',
            'routing_mode' => 'permit_empty|in_list[split,full]',
            'expiration'   => 'permit_empty|in_list[keep,never,1d,7d,30d]',
        ];

        if (!$this->validate($rules)) {
            $errMsg = implode(' ', $this->validator->getErrors());
            if (!$isAjax) {
                return redirect()->back()->withInput()->with('error', $errMsg);
            }
            return $this->response->setJSON([
                'success' => false,
                'message' => $errMsg,
                'errors'  => $this->validator->getErrors(),
            ])->setStatusCode(400);
        }

        $name        = trim((string) $this->request->getPost('name'));
        $deviceType  = (string) $this->request->getPost('device_type');
        $routingMode = (string) ($this->request->getPost('routing_mode') ?: 'split');
        $expiration  = (string) ($this->request->getPost('expiration') ?: 'keep');

        $updateData = [
            'name'         => $name,
            'device_type'  => $deviceType,
            'routing_mode' => $routingMode,
        ];

        if ($expiration === 'never') {
            $updateData['expires_at'] = null;
        } elseif ($expiration === '1d') {
            $updateData['expires_at'] = date('Y-m-d H:i:s', strtotime('+1 day'));
        } elseif ($expiration === '7d') {
            $updateData['expires_at'] = date('Y-m-d H:i:s', strtotime('+7 days'));
        } elseif ($expiration === '30d') {
            $updateData['expires_at'] = date('Y-m-d H:i:s', strtotime('+30 days'));
        }

        if ($this->deviceModel->update($id, $updateData)) {
            \App\Models\AuditLogModel::log('device.updated', "Nodo '{$name}' (IP: {$device->ip_address}) actualizado en la red '{$network->name}'.");

            if (!$isAjax) {
                return redirect()->to('/devices/show/' . $id)->with('message', 'Nodo actualizado correctamente.');
            }
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Nodo actualizado correctamente.'
            ]);
        }

        if (!$isAjax) {
            return redirect()->back()->withInput()->with('error', 'Error al actualizar el nodo.');
        }

        return $this->response->setJSON([
            'success' => false,
            'message' => 'Error al actualizar el nodo.',
            'errors'  => $this->deviceModel->errors()
        ])->setStatusCode(400);
    }

    // ---------------------------------------------------------------------
    // Eliminar un dispositivo
    // ---------------------------------------------------------------------
    public function delete($id)
    {
        if ($this->request->getMethod() !== 'POST') {
            return redirect()->to('/networks')->with('error', 'Método no permitido.');
        }

        $device = $this->deviceModel->find($id);
        if (!$device) {
            return redirect()->to('/networks')->with('error', 'Dispositivo no encontrado.');
        }

        $networkId = $device->network_id;

        $network = $this->networkModel->find($networkId);
        if (!$network) {
            return redirect()->to('/networks')->with('error', 'Red no encontrada.');
        }

        // Validar permisos para eliminar
        if (!$this->hasNetworkAccess($network, 'delete')) {
            return redirect()->to("networks/{$networkId}/devices")->with('error', 'Sin permisos.');
        }

        // Quitar primero el peer del servidor (si estaba activo). Si falla, no se borra de la BD.
        if ($device->active) {
            try {
                $this->pushPeer($device, false);
            } catch (\Throwable $e) {
                log_message('error', 'No se pudo quitar el nodo ' . $id . ' de WireGuard: ' . $e->getMessage());
                return redirect()->to("networks/{$networkId}/devices")
                    ->with('error', 'No se pudo quitar el nodo del servidor VPN. No se ha eliminado. Detalle: ' . esc($e->getMessage()));
            }
        }

        if ($this->deviceModel->delete($id)) {
            \App\Models\AuditLogModel::log('DEVICE_DELETE', "Eliminó el nodo '{$device->name}' ({$device->ip_address})");
            return redirect()->to("networks/{$networkId}/devices")->with('message', 'Nodo eliminado.');
        }

        // Revertir: volver a registrar el peer en el servidor
        if ($device->active) {
            $this->bestEffort(fn () => $this->pushPeer($device, true), 'revertir baja del nodo ' . $id);
        }

        return redirect()->to("networks/{$networkId}/devices")->with('error', 'Error al eliminar.');
    }

    // ---------------------------------------------------------------------
    // Rotar / Regenerar par de llaves criptográficas del dispositivo
    // ---------------------------------------------------------------------
    public function rotateKeys($id)
    {
        if ($this->request->getMethod() !== 'POST') {
            return redirect()->back();
        }

        $device = $this->deviceModel->find($id);
        if (!$device) {
            return redirect()->to('/networks')->with('error', 'Dispositivo no encontrado.');
        }

        $networkId = $device->network_id;
        $network = $this->networkModel->find($networkId);
        if (!$network) {
            return redirect()->to('/networks')->with('error', 'Red no encontrada.');
        }

        // Validar permisos para editar
        if (!$this->hasNetworkAccess($network, 'edit')) {
            return redirect()->to("networks/{$networkId}/devices")->with('error', 'Sin permisos.');
        }

        if (!WireguardService::isConfigured()) {
            return redirect()->back()->with('error', 'El servidor VPN no está configurado.');
        }

        // 1. Generar nuevo par de llaves WireGuard
        try {
            $keypair = WireguardService::generateKeyPair();
            $newPrivateKey = $keypair['privateKey'];
            $newPublicKey  = $keypair['publicKey'];
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Error generando nuevas llaves: ' . $e->getMessage());
        }

        $oldPublicKey = $device->public_key;

        // 2. Si el dispositivo está activo, aplicar rotación en el servidor WireGuard
        if ($device->active) {
            try {
                $wg = WireguardService::fromSettings(5);
                $wg->setTimeout(30);
                // Quitar clave antigua y agregar clave nueva
                $wg->removePeer($oldPublicKey);
                $wg->addPeer($newPublicKey, $device->ip_address);
                $wg->saveConfig();
            } catch (\Throwable $e) {
                log_message('error', 'Fallo al rotar peer en servidor WireGuard: ' . $e->getMessage());
                return redirect()->back()->with('error', 'No se pudo actualizar la clave en el servidor VPN: ' . esc($e->getMessage()));
            }
        }

        // 3. Persistir nuevas llaves en la base de datos (DeviceModel se encarga de cifrar la private_key)
        $updateData = [
            'public_key'  => $newPublicKey,
            'private_key' => $newPrivateKey,
        ];

        if ($this->deviceModel->update($id, $updateData)) {
            return redirect()->to("devices/show/{$id}")->with('message', '¡Llaves regeneradas con éxito! Recuerda descargar la nueva configuración o escanear el nuevo código QR.');
        }

        // Revertir cambio en servidor WireGuard en caso de fallo en BD
        if ($device->active) {
            $this->bestEffort(function() use ($device, $oldPublicKey, $newPublicKey) {
                $wg = WireguardService::fromSettings(5);
                $wg->removePeer($newPublicKey);
                $wg->addPeer($oldPublicKey, $device->ip_address);
                $wg->saveConfig();
            }, 'revertir rotación de llaves para el nodo ' . $id);
        }

        return redirect()->back()->with('error', 'Error al guardar las nuevas llaves en la base de datos.');
    }

    // ---------------------------------------------------------------------
    // Activar / Desactivar dispositivo
    // ---------------------------------------------------------------------
    public function toggleActive($id)
    {
        if ($this->request->getMethod() !== 'POST') {
            return redirect()->back();
        }

        $device = $this->deviceModel->find($id);
        if (!$device) {
            return redirect()->to('/networks')->with('error', 'Dispositivo no encontrado.');
        }

        $networkId = $device->network_id;
        $network = $this->networkModel->find($networkId);
        if (!$network) {
            return redirect()->to('/networks')->with('error', 'Red no encontrada.');
        }

        // Validar permisos para activar/desactivar
        if (!$this->hasNetworkAccess($network, 'edit')) {
            return redirect()->to("networks/{$networkId}/devices")->with('error', 'Sin permisos.');
        }

        $newActiveStatus = !$device->active;

        // Aplicar primero en el servidor. Si falla, no se cambia el estado en la BD.
        try {
            $this->pushPeer($device, $newActiveStatus);
        } catch (\Throwable $e) {
            log_message('error', 'No se pudo cambiar el estado del nodo ' . $id . ' en WireGuard: ' . $e->getMessage());
            return redirect()->to("networks/{$networkId}/devices")
                ->with('error', 'No se pudo aplicar el cambio en el servidor VPN. Detalle: ' . esc($e->getMessage()));
        }

        if ($this->deviceModel->update($id, ['active' => $newActiveStatus ? 1 : 0])) {
            $status = $newActiveStatus ? 'activado' : 'desactivado';
            return redirect()->to("networks/{$networkId}/devices")->with('message', "Nodo {$status}.");
        }

        // Revertir el cambio en el servidor
        $this->bestEffort(fn () => $this->pushPeer($device, !$newActiveStatus), 'revertir estado del nodo ' . $id);

        return redirect()->to("networks/{$networkId}/devices")->with('error', 'Error al cambiar estado.');
    }

    // ---------------------------------------------------------------------
    // Obtener detalles para QR y configuración (JSON)
    // ---------------------------------------------------------------------
    public function getDetailsJson($id)
    {
        $device = $this->deviceModel->find($id);
        if (!$device) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Dispositivo no encontrado.'
            ])->setStatusCode(404);
        }

        $network = $this->networkModel->find($device->network_id);
        if (!$network) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Red no encontrada.'
            ])->setStatusCode(404);
        }

        // Validar acceso a la red
        if (!$this->hasNetworkAccess($network, 'view')) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Acceso denegado.'
            ])->setStatusCode(403);
        }

        $decryptedPrivateKey = $this->deviceModel->decryptKey($device->private_key);
        if (empty($decryptedPrivateKey)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'No se pudo desencriptar la llave privada del nodo.'
            ])->setStatusCode(500);
        }

        $serverPublicKey = service('settings')->get('WireGuard.publicKey');
        if (empty($serverPublicKey)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'La clave pública del servidor WireGuard no está configurada. Por favor, ve a Ajustes > WireGuard y prueba la conexión para autodescubrirla.'
            ])->setStatusCode(400);
        }

        $serverEndpoint = $this->resolveServerEndpoint();

        $configString = $this->buildConfigContent(
            $decryptedPrivateKey,
            $device->ip_address,
            $serverPublicKey,
            $network->cidr,
            $serverEndpoint,
            $network->dns,
            $device->routing_mode ?? 'split'
        );

        return $this->response->setJSON([
            'success' => true,
            'device'  => [
                'id'         => $device->id,
                'name'       => $device->name,
                'ip_address' => $device->ip_address,
                'config'     => $configString
            ]
        ]);
    }

    // ---------------------------------------------------------------------
    // Descargar archivo de configuración .conf
    // ---------------------------------------------------------------------
    public function downloadConfig($id)
    {
        $device = $this->deviceModel->find($id);
        if (!$device) {
            return redirect()->to('/networks')->with('error', 'Dispositivo no encontrado.');
        }

        $network = $this->networkModel->find($device->network_id);
        if (!$network) {
            return redirect()->to('/networks')->with('error', 'Red no encontrada.');
        }

        // Validar acceso a la red
        if (!$this->hasNetworkAccess($network, 'view')) {
            return redirect()->to('/networks')->with('error', 'Acceso denegado.');
        }

        $decryptedPrivateKey = $this->deviceModel->decryptKey($device->private_key);
        if (empty($decryptedPrivateKey)) {
            return redirect()->to('/networks/' . $network->id . '/devices')->with('error', 'No se pudo desencriptar la llave privada del nodo.');
        }

        $serverPublicKey = service('settings')->get('WireGuard.publicKey');
        if (empty($serverPublicKey)) {
            return redirect()->to('/networks/' . $network->id . '/devices')->with('error', 'La clave pública del servidor WireGuard no está configurada. Ve a Ajustes > WireGuard.');
        }

        $serverEndpoint = $this->resolveServerEndpoint();

        $configString = $this->buildConfigContent(
            $decryptedPrivateKey,
            $device->ip_address,
            $serverPublicKey,
            $network->cidr,
            $serverEndpoint,
            $network->dns,
            $device->routing_mode ?? 'split'
        );

        $filename = url_title($device->name, '-', true) . '.conf';

        return $this->response->setHeader('Content-Type', 'text/plain')
                                ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
                                ->setBody($configString);
    }

    // ---------------------------------------------------------------------
    // Generar enlace temporal compartido (One-Time / Timed Secret Link)
    // ---------------------------------------------------------------------
    public function generateShareLink($id)
    {
        if ($this->request->getMethod() !== 'POST') {
            return $this->response->setJSON(['success' => false, 'message' => 'Método no permitido'])->setStatusCode(405);
        }

        $device = $this->deviceModel->find($id);
        if (!$device) {
            return $this->response->setJSON(['success' => false, 'message' => 'Dispositivo no encontrado'])->setStatusCode(404);
        }

        $network = $this->networkModel->find($device->network_id);
        if (!$network || !$this->hasNetworkAccess($network, 'view')) {
            return $this->response->setJSON(['success' => false, 'message' => 'Acceso denegado'])->setStatusCode(403);
        }

        $token = bin2hex(random_bytes(24));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+60 minutes'));

        $db = \Config\Database::connect();
        $db->table('device_share_tokens')->insert([
            'device_id'  => $device->id,
            'token'      => $token,
            'expires_at' => $expiresAt,
            'used'       => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $shareUrl = site_url('connect/' . $token);

        return $this->response->setJSON([
            'success'   => true,
            'share_url' => $shareUrl,
            'expires'   => '60 minutos',
        ]);
    }

    // ---------------------------------------------------------------------
    // Enviar archivo de configuración por correo electrónico
    // ---------------------------------------------------------------------
    public function sendConfigEmail($id)
    {
        if ($this->request->getMethod() !== 'POST') {
            return $this->response->setJSON(['success' => false, 'message' => 'Método no permitido'])->setStatusCode(405);
        }

        $targetEmail = trim((string) $this->request->getPost('email'));
        if (empty($targetEmail) || filter_var($targetEmail, FILTER_VALIDATE_EMAIL) === false) {
            return $this->response->setJSON(['success' => false, 'message' => 'Por favor, introduce un correo electrónico válido'])->setStatusCode(400);
        }

        $device = $this->deviceModel->find($id);
        if (!$device) {
            return $this->response->setJSON(['success' => false, 'message' => 'Dispositivo no encontrado'])->setStatusCode(404);
        }

        $network = $this->networkModel->find($device->network_id);
        if (!$network || !$this->hasNetworkAccess($network, 'view')) {
            return $this->response->setJSON(['success' => false, 'message' => 'Acceso denegado'])->setStatusCode(403);
        }

        $decryptedPrivateKey = $this->deviceModel->decryptKey($device->private_key);
        $serverPublicKey = service('settings')->get('WireGuard.publicKey');
        if (empty($decryptedPrivateKey) || empty($serverPublicKey)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Configuración de WireGuard incompleta o clave ilegible'])->setStatusCode(400);
        }

        $serverEndpoint = $this->resolveServerEndpoint();
        $configContent = $this->buildConfigContent(
            $decryptedPrivateKey,
            $device->ip_address,
            $serverPublicKey,
            $network->cidr,
            $serverEndpoint,
            $network->dns,
            $device->routing_mode ?? 'split'
        );

        $filename = url_title($device->name, '-', true) . '.conf';

        try {
            $emailService = \Config\Services::email();
            $emailService->setTo($targetEmail);
            $emailService->setSubject('Configuración VPN WireGuard - ' . $device->name);
            $emailService->setMessage("Hola,\n\nAdjunto encontrarás el archivo de configuración WireGuard para el nodo: {$device->name}.\nRed: {$network->name} ({$device->ip_address})\n\nGuarda el archivo .conf e impórtalo en tu cliente oficial de WireGuard.\n\nSaludos,\nEquipo NetCrew");
            $emailService->attach($configContent, 'attachment', $filename, 'text/plain');

            if ($emailService->send()) {
                return $this->response->setJSON(['success' => true, 'message' => 'Configuración enviada exitosamente a ' . esc($targetEmail)]);
            } else {
                log_message('error', 'Fallo al enviar correo de configuración: ' . $emailService->printDebugger(['headers']));
                return $this->response->setJSON(['success' => false, 'message' => 'No se pudo enviar el correo. Revisa los Ajustes SMTP.'])->setStatusCode(500);
            }
        } catch (\Throwable $e) {
            return $this->response->setJSON(['success' => false, 'message' => 'Error al enviar: ' . $e->getMessage()])->setStatusCode(500);
        }
    }

    // ---------------------------------------------------------------------
    // Vista pública de conexión compartida (Sin autenticación requerida)
    // ---------------------------------------------------------------------
    public function connectShare($token)
    {
        $db = \Config\Database::connect();
        $row = $db->table('device_share_tokens')
                  ->where('token', $token)
                  ->where('expires_at >', date('Y-m-d H:i:s'))
                  ->where('used', 0)
                  ->get()
                  ->getRow();

        if (!$row) {
            return view('errors/html/error_404', [
                'message' => 'El enlace de conexión ha expirado o ya ha sido utilizado.'
            ]);
        }

        $device = $this->deviceModel->find($row->device_id);
        if (!$device) {
            return redirect()->to('/');
        }

        $network = $this->networkModel->find($device->network_id);
        $decryptedPrivateKey = $this->deviceModel->decryptKey($device->private_key);
        $serverPublicKey = service('settings')->get('WireGuard.publicKey');
        $serverEndpoint = $this->resolveServerEndpoint();

        $configString = $this->buildConfigContent(
            $decryptedPrivateKey,
            $device->ip_address,
            $serverPublicKey,
            $network->cidr,
            $serverEndpoint,
            $network->dns,
            $device->routing_mode ?? 'split'
        );

        $data = [
            'token'   => $token,
            'device'  => $device,
            'network' => $network,
            'config'  => $configString,
        ];

        return view('devices/connect_share', $data);
    }

    // ---------------------------------------------------------------------
    // Descarga desde vista pública de conexión compartida
    // ---------------------------------------------------------------------
    public function downloadShare($token)
    {
        $db = \Config\Database::connect();
        $row = $db->table('device_share_tokens')
                  ->where('token', $token)
                  ->where('expires_at >', date('Y-m-d H:i:s'))
                  ->get()
                  ->getRow();

        if (!$row) {
            return redirect()->to('/');
        }

        $device = $this->deviceModel->find($row->device_id);
        $network = $this->networkModel->find($device->network_id);
        $decryptedPrivateKey = $this->deviceModel->decryptKey($device->private_key);
        $serverPublicKey = service('settings')->get('WireGuard.publicKey');
        $serverEndpoint = $this->resolveServerEndpoint();

        $configString = $this->buildConfigContent(
            $decryptedPrivateKey,
            $device->ip_address,
            $serverPublicKey,
            $network->cidr,
            $serverEndpoint,
            $network->dns,
            $device->routing_mode ?? 'split'
        );

        $filename = url_title($device->name, '-', true) . '.conf';

        return $this->response->setHeader('Content-Type', 'text/plain')
                                ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
                                ->setBody($configString);
    }

    // ---------------------------------------------------------------------
    // Helper: Generar IP disponible en base al CIDR
    // ---------------------------------------------------------------------
    protected function generateNextAvailableIp($cidr, array $existingIps)
    {
        if (strpos($cidr, '/') === false) {
            return null;
        }

        list($ip, $mask) = explode('/', $cidr);
        $ipLong = ip2long($ip);
        if ($ipLong === false) {
            return null;
        }

        $mask = (int)$mask;
        if ($mask < 0 || $mask > 32) {
            return null;
        }

        $maskLong = ~((1 << (32 - $mask)) - 1);
        $networkLong = $ipLong & $maskLong;
        $broadcastLong = $networkLong | (~$maskLong);

        // Empezamos en networkLong + 2 para dejar el primer host (ej: .1) libre para el servidor
        for ($i = $networkLong + 2; $i < $broadcastLong; $i++) {
            $candidateIp = long2ip($i);
            if (!in_array($candidateIp, $existingIps)) {
                return $candidateIp;
            }
        }

        return null;
    }

    // ---------------------------------------------------------------------
    // Helper: Formatear contenido del archivo .conf
    // ---------------------------------------------------------------------
    protected function buildConfigContent($privateKey, $ipAddress, $serverPublicKey, $allowedIps, $serverEndpoint, $dns = null, $routingMode = 'split')
    {
        // Limpiar endpoint de esquemas (http://, https://) y barras diagonales accidentales
        $serverEndpoint = trim($serverEndpoint);
        $serverEndpoint = preg_replace('/^https?:\/\//i', '', $serverEndpoint);
        $serverEndpoint = rtrim($serverEndpoint, '/');

        // Extraer la máscara de la subred del CIDR de la red para configurar la interfaz del cliente correctamente
        $mask = '24';
        if (strpos($allowedIps, '/') !== false) {
            $parts = explode('/', $allowedIps);
            $mask = trim(end($parts));
        }

        $config = "[Interface]\n" .
                  "PrivateKey = {$privateKey}\n" .
                  "Address = {$ipAddress}/{$mask}\n";

        if (!empty($dns)) {
            // Sanitizar DNS contra inyección de nuevas líneas o directivas en el .conf
            $cleanDns = [];
            foreach (explode(',', (string) $dns) as $entry) {
                $entry = trim(preg_replace('/[\r\n\0]/', '', $entry));
                if ($entry !== '' && (filter_var($entry, FILTER_VALIDATE_IP) !== false || preg_match('/^[a-zA-Z0-9.-]+$/', $entry))) {
                    $cleanDns[] = $entry;
                }
            }
            if (!empty($cleanDns)) {
                $config .= "DNS = " . implode(', ', $cleanDns) . "\n";
            }
        }
                  
        // Modo de enrutamiento: Full-Tunnel (todo el tráfico / Internet) vs Split-Tunnel (solo red local)
        if ($routingMode === 'full') {
            $allowedIpsString = '0.0.0.0/0, ::/0';
        } else {
            $allowedIpsString = $allowedIps;
        }
        
        $config .= "\n[Peer]\n" .
                   "PublicKey = {$serverPublicKey}\n" .
                   "AllowedIPs = {$allowedIpsString}\n" .
                   "Endpoint = {$serverEndpoint}\n" .
                   "PersistentKeepalive = 25\n";
                     
        return $config;
    }

    // ---------------------------------------------------------------------
    // Obtener todos los datos del nodo para el modal de detalles
    // ---------------------------------------------------------------------
    public function nodeDetails($id)
    {
        $device = $this->deviceModel->find($id);
        if (!$device) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Dispositivo no encontrado.'
            ])->setStatusCode(404);
        }

        $network = $this->networkModel->find($device->network_id);
        if (!$network) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Red no encontrada.'
            ])->setStatusCode(404);
        }

        // Validar acceso
        if (!$this->hasNetworkAccess($network, 'view')) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Acceso denegado.'
            ])->setStatusCode(403);
        }

        // Datos estáticos del nodo
        $result = [
            'id'          => $device->id,
            'name'        => $device->name,
            'ip_address'  => $device->ip_address,
            'public_key'  => $device->public_key,
            'device_type' => $device->device_type ?? 'pc',
            'os'          => $device->os ?? null,
            'active'      => (bool)$device->active,
            'network'     => $network->name,
            'cidr'        => $network->cidr,

            'last_seen'   => $device->last_seen,
            'created_at'  => $device->created_at,
            // Estado WireGuard en tiempo real (se rellena si hay SSH configurado)
            'wg' => null,
        ];

        // Intentar obtener datos de WireGuard en tiempo real vía SSH
        $result['wg'] = $this->fetchPeerStatus($device);
        if ($result['wg'] && !empty($result['wg']['last_seen'])) {
            $result['last_seen'] = $result['wg']['last_seen'];
        }

        return $this->response->setJSON([
            'success' => true,
            'device'  => $result,
        ]);
    }

    // ---------------------------------------------------------------------
    // Helper: Registrar o quitar un peer en el servidor WireGuard.
    // Lanza excepción si el servidor no confirma el cambio.
    // ---------------------------------------------------------------------
    protected function pushPeer(object $device, bool $add): void
    {
        $wg = WireguardService::fromSettings(5);
        $wg->setTimeout(30);

        if ($add) {
            // Regenerar firewall/direcciones por si la red del nodo es nueva
            $wg->syncFirewall();
            $wg->addPeer($device->public_key, $device->ip_address);
        } else {
            $wg->removePeer($device->public_key);
        }

        $wg->saveConfig();
    }

    // ---------------------------------------------------------------------
    // Helper: Ejecutar una acción de reversión sin interrumpir el flujo
    // ---------------------------------------------------------------------
    protected function bestEffort(callable $action, string $context): void
    {
        try {
            $action();
        } catch (\Throwable $e) {
            log_message('critical', "Fallo al {$context}. Ejecuta la sincronización desde Mantenimiento: " . $e->getMessage());
        }
    }

    // ---------------------------------------------------------------------
    // Helper: Estado en tiempo real de un peer (null si no hay conexión)
    // ---------------------------------------------------------------------
    protected function fetchPeerStatus(object $device): ?array
    {
        try {
            $dump = WireguardService::cachedDump(5, 5);
        } catch (\Throwable $e) {
            log_message('notice', 'No se pudo obtener estado WireGuard en tiempo real para nodo ' . $device->id . ': ' . $e->getMessage());
            return null;
        }

        $peer = $dump['peers'][$device->public_key] ?? null;
        if (!$peer) {
            return null;
        }

        // Separar IP y puerto del endpoint (compatible con IPv6 entre corchetes)
        $endpoint = $peer['endpoint'];
        $publicIp = null;
        $port     = null;
        if ($endpoint && ($pos = strrpos($endpoint, ':')) !== false) {
            $publicIp = trim(substr($endpoint, 0, $pos), '[]');
            $port     = substr($endpoint, $pos + 1);
        }

        return [
            'connected'        => $peer['connected'],
            'endpoint'         => $endpoint,
            'public_ip'        => $publicIp,
            'port'             => $port,
            'latest_handshake' => $peer['latest_handshake'] ?: null,
            'remote_time'      => $dump['remote_time'],
            'rx_bytes'         => $peer['rx_bytes'],
            'tx_bytes'         => $peer['tx_bytes'],
            'last_seen'        => $this->updateLastSeenFromHandshake($device, $peer['latest_handshake']),
        ];
    }

    // ---------------------------------------------------------------------
    // Hacer ping a un dispositivo (ICMP)
    // ---------------------------------------------------------------------
    public function ping($id)
    {
        if ($this->request->getMethod() !== 'POST') {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Método no permitido'])->setStatusCode(405);
        }

        $device = $this->deviceModel->find($id);
        if (!$device) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Dispositivo no encontrado'])->setStatusCode(404);
        }

        // Obtener red para comprobar permisos
        $network = $this->networkModel->find($device->network_id);
        if (!$network) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Red no encontrada'])->setStatusCode(404);
        }
        if (!$this->hasNetworkAccess($network, 'view')) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'No tienes permisos para esta red'])->setStatusCode(403);
        }

        try {
            $wg = WireguardService::fromSettings(10);

            // Ejecutar ping enviando 4 paquetes, timeout 2 segundos por paquete
            $output     = $wg->exec('ping -c 4 -W 2 ' . escapeshellarg($device->ip_address) . ' 2>&1');
            $exitStatus = $wg->lastExitStatus();

            if ($exitStatus === 0) {
                return $this->response->setJSON([
                    'status' => 'success',
                    'output' => $output
                ]);
            } else {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => 'El dispositivo no respondió al Ping (ICMP). Puede estar apagado, desconectado, o un Firewall está bloqueando ICMP.',
                    'output' => $output
                ]);
            }
        } catch (\Throwable $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Error de conexión SSH con el servidor VPN.',
                'output' => $e->getMessage()
            ]);
        }
    }

    // ---------------------------------------------------------------------
    // Obtener estado de conexión en tiempo real de todos los nodos
    // ---------------------------------------------------------------------
    public function realtimeStatus($networkId)
    {
        $network = $this->networkModel->find($networkId);
        if (!$network) {
            return $this->response->setJSON(['success' => false, 'message' => 'Red no encontrada.'])->setStatusCode(404);
        }

        if (!$this->hasNetworkAccess($network, 'view')) {
            return $this->response->setJSON(['success' => false, 'message' => 'Acceso denegado.'])->setStatusCode(403);
        }

        $peers = [];
        try {
            $dump       = WireguardService::cachedDump(5, 5);
            $remoteTime = $dump['remote_time'];

            // Solo se devuelven los peers de esta red (no exponer datos de otras redes)
            $devices = $this->deviceModel->where('network_id', $networkId)->findAll();
            foreach ($devices as $device) {
                $peer = $dump['peers'][$device->public_key] ?? null;
                if (!$peer) {
                    continue;
                }

                $lastSeen = $this->updateLastSeenFromHandshake($device, $peer['latest_handshake']);

                $peers[$device->public_key] = [
                    'connected' => $peer['connected'],
                    'endpoint' => $peer['endpoint'],
                    'latest_handshake' => $peer['latest_handshake'],
                    'last_seen' => $lastSeen,
                    'last_seen_date' => $lastSeen ? date('d/m/Y', strtotime($lastSeen)) : null,
                    'last_seen_time' => $lastSeen ? date('H:i', strtotime($lastSeen)) : null,
                    'rx' => $peer['rx_bytes'],
                    'tx' => $peer['tx_bytes'],
                ];
            }
        } catch (\Throwable $e) {
            return $this->response->setJSON(['success' => false, 'message' => $e->getMessage()]);
        }

        return $this->response->setJSON([
            'success' => true,
            'remoteTime' => $remoteTime,
            'peers' => $peers
        ]);
    }

    // ---------------------------------------------------------------------
    // Actualizar último acceso usando el último handshake real de WireGuard
    // ---------------------------------------------------------------------
    protected function updateLastSeenFromHandshake($device, int $handshake): ?string
    {
        if ($handshake <= 0) {
            return $device->last_seen ?? null;
        }

        $lastSeen = date('Y-m-d H:i:s', $handshake);
        $currentLastSeen = !empty($device->last_seen) ? strtotime($device->last_seen) : 0;

        // Limitar escrituras en SQLite: actualizar solo cada 5 minutos (300s) y capturar excepciones
        if (abs($handshake - $currentLastSeen) >= 300) {
            try {
                $this->deviceModel->update($device->id, ['last_seen' => $lastSeen]);
                $device->last_seen = $lastSeen;
            } catch (\Throwable $e) {
                log_message('warning', 'Aviso no bloqueante: no se pudo persistir last_seen del nodo ' . $device->id . ' en SQLite: ' . $e->getMessage());
            }
        }

        return $device->last_seen ?? $lastSeen;
    }

    /**
     * Valida permisos para operar sobre dispositivos de una red según RBAC y propiedad.
     */
    protected function hasNetworkAccess(object $network, string $action = 'view'): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        if ($user->inGroup('superadmin')) {
            return true;
        }

        $hasPermission = $user->can('devices.*') || $user->can("devices.{$action}");
        if (!$hasPermission) {
            return false;
        }

        if ($user->inGroup('supervisor')) {
            return true;
        }

        return (int) $network->owner_id === (int) $user->id;
    }

    /**
     * Resuelve de forma segura el endpoint del servidor WireGuard (evitando inyecciones HTTP Host).
     */
    protected function resolveServerEndpoint(): string
    {
        $settings = service('settings');
        $endpoint = trim((string) $settings->get('WireGuard.endpoint'));
        if (!empty($endpoint)) {
            return $endpoint;
        }

        // Si no está configurado explícitamente, usar el Host SSH del servidor VPN
        $sshHost = trim((string) $settings->get('WireGuard.sshHost'));
        if (!empty($sshHost)) {
            return $sshHost . ':51820';
        }

        // Fallback al hostname seguro configurado en la app (baseURL)
        $baseHost = parse_url(config('App')->baseURL, PHP_URL_HOST);
        if (!empty($baseHost) && $baseHost !== 'localhost' && $baseHost !== '127.0.0.1') {
            return $baseHost . ':51820';
        }

        return 'vpn.tudominio.com:51820';
    }
}
