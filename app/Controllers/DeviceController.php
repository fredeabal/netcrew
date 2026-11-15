<?php

namespace App\Controllers;

use App\Libraries\NebulaService;
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

        $allDevices = $this->deviceModel->where('network_id', $networkId)->findAll();

        // Ordenar dispositivos correctamente por IP (evitando ordenamiento alfabético de SQLite)
        usort($allDevices, function($a, $b) {
            return ip2long($a->ip_address) <=> ip2long($b->ip_address);
        });

        $total   = count($allDevices);
        $perPage = 100;
        $page    = max(1, (int) ($this->request->getVar('page_devices') ?? 1));
        $devices = array_slice($allDevices, ($page - 1) * $perPage, $perPage);

        $pagerLinks = '';
        if ($total > $perPage) {
            $pager = service('pager');
            $pagerLinks = $pager->makeLinks($page, $perPage, $total, 'premium', 1, 'devices');
        }

        $data = [
            'title'             => 'Administrar Nodos - ' . $network->name,
            'network'           => $network,
            'devices'           => $devices,
            'totalDevicesCount' => $total,
            'pagerLinks'        => $pagerLinks,
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

        // Estado en tiempo real (Nebula P2P)
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

        // Firmar certificado Nebula P2P para el dispositivo
        try {
            $caKey = $this->networkModel->decryptCaKey((string) $network->nebula_ca_key);
            if (empty($network->nebula_ca_crt) || $caKey === '') {
                throw new \RuntimeException('La CA de Nebula de esta red no es válida o no se pudo descifrar.');
            }
            $signed       = NebulaService::signDeviceCert(
                $network->nebula_ca_crt,
                $caKey,
                $name,
                $ipAddress . '/24',
                'devices',
                $expiresAt
            );
            $publicKey    = $signed['fingerprint'];
            $privateKey   = 'nebula';
            $nebulaCrt    = $signed['crt'];
            $nebulaKey    = $signed['key'];
            $nebulaGroups = 'devices';
        } catch (\Throwable $e) {
            $msg = 'Error al firmar certificado Nebula: ' . $e->getMessage();
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
            'network_id'    => $networkId,
            'name'          => $name,
            'ip_address'    => $ipAddress,
            'public_key'    => $publicKey,
            'private_key'   => $privateKey,
            'nebula_crt'    => $nebulaCrt,
            'nebula_key'    => $nebulaKey,
            'nebula_groups' => $nebulaGroups,
            'active'        => 1,
            'device_type'   => $deviceType,
            'routing_mode'  => $routingMode,
            'expires_at'    => $expiresAt,
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

        $insertId = $this->deviceModel->getInsertID();
        \App\Models\AuditLogModel::log('DEVICE_CREATE', "Creó el nodo '{$data['name']}' ({$data['ip_address']}) en red ID: {$networkId}");

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
            \App\Models\AuditLogModel::log('DEVICE_UPDATE', "Nodo '{$name}' (IP: {$device->ip_address}) actualizado en la red '{$network->name}'.");

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

        if ($this->deviceModel->delete($id)) {
            \App\Models\AuditLogModel::log('DEVICE_DELETE', "Eliminó el nodo '{$device->name}' ({$device->ip_address})");
            return redirect()->to("networks/{$networkId}/devices")->with('message', 'Nodo eliminado.');
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

        try {
            $caKey = $this->networkModel->decryptCaKey((string) $network->nebula_ca_key);
            if (empty($network->nebula_ca_crt) || $caKey === '') {
                return redirect()->back()->with('error', 'No se pudo descifrar la CA de Nebula.');
            }
            $signed = NebulaService::signDeviceCert(
                $network->nebula_ca_crt,
                $caKey,
                $device->name,
                $device->ip_address . '/24',
                $device->nebula_groups ?: 'devices',
                $device->expires_at
            );
            $updateData = [
                'public_key' => $signed['fingerprint'],
                'nebula_crt' => $signed['crt'],
                'nebula_key' => $signed['key'],
            ];
            if ($this->deviceModel->update($id, $updateData)) {
                \App\Models\AuditLogModel::log('DEVICE_ROTATE_KEYS', "Rotó certificado Nebula del nodo '{$device->name}' (ID: {$id}) en red ID: {$network->id}");
                return redirect()->to("devices/show/{$id}")->with('message', '¡Certificado Nebula regenerado con éxito! Descarga la nueva configuración .yml.');
            }
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Error regenerando certificado Nebula: ' . $e->getMessage());
        }

        return redirect()->back()->with('error', 'Error al guardar el nuevo certificado Nebula.');
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

        // Si se intenta activar, validar que la red esté activa y el dispositivo no haya expirado
        if ($newActiveStatus) {
            if (!$network->active) {
                return redirect()->to("networks/{$networkId}/devices")
                    ->with('error', 'No se puede activar el nodo porque la red a la que pertenece está desactivada.');
            }
            if (!empty($device->expires_at) && strtotime($device->expires_at) < time()) {
                return redirect()->to("networks/{$networkId}/devices")
                    ->with('error', 'No se puede activar el nodo porque su vigencia ha expirado. Edita la fecha de expiración primero.');
            }
        }

        if ($this->deviceModel->update($id, ['active' => $newActiveStatus ? 1 : 0])) {
            $status = $newActiveStatus ? 'activado' : 'desactivado';
            \App\Models\AuditLogModel::log('DEVICE_TOGGLE_ACTIVE', "Cambió estado a {$status} para el nodo '{$device->name}' (ID: {$id}) en red ID: {$networkId}");
            return redirect()->to("networks/{$networkId}/devices")->with('message', "Nodo {$status}.");
        }

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

        try {
            $payload = $this->buildDeviceConfigPayload($network, $device);
        } catch (\RuntimeException $e) {
            return $this->response->setJSON([
                'success' => false,
                'message' => $e->getMessage()
            ])->setStatusCode(400);
        }

        \App\Models\AuditLogModel::log('DEVICE_VIEW_KEYS', "Visualizó configuración y claves del nodo '{$device->name}' (ID: {$id}) en red ID: {$network->id}");

        return $this->response->setJSON([
            'success' => true,
            'device'  => [
                'id'         => $device->id,
                'name'       => $device->name,
                'ip_address' => $device->ip_address,
                'engine'     => 'nebula',
                'filename'   => $payload['filename'],
                'config'     => $payload['content'],
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

        if (!$network->active) {
            return redirect()->to('/networks/' . $network->id . '/devices')->with('error', 'No se puede descargar la configuración de una red desactivada.');
        }

        if (!$device->active || (!empty($device->expires_at) && strtotime($device->expires_at) < time())) {
            return redirect()->to('/networks/' . $network->id . '/devices')->with('error', 'No se puede descargar la configuración de un nodo inactivo o expirado.');
        }

        try {
            $payload = $this->buildDeviceConfigPayload($network, $device);
        } catch (\RuntimeException $e) {
            return redirect()->to('/networks/' . $network->id . '/devices')->with('error', $e->getMessage());
        }

        $platform = (string) ($this->request->getGet('platform') ?: 'yml');
        $slug     = url_title((string) $device->name, '-', true) ?: 'nodo-' . $device->id;
        $bundle   = \App\Libraries\NebulaInstallerService::buildForPlatform($platform, $slug, $payload['content']);

        \App\Models\AuditLogModel::log('DEVICE_DOWNLOAD', "Descargó {$bundle['filename']} ({$platform}) del nodo '{$device->name}' (ID: {$id}) en red ID: {$network->id}");

        return $this->response->setHeader('Content-Type', $bundle['contentType'])
                                ->setHeader('Content-Disposition', 'attachment; filename="' . $bundle['filename'] . '"')
                                ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                                ->setHeader('Pragma', 'no-cache')
                                ->setBody($bundle['body']);
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
        if (!$network || !$this->hasNetworkAccess($network, 'edit')) {
            return $this->response->setJSON(['success' => false, 'message' => 'Acceso denegado'])->setStatusCode(403);
        }

        if (!$network->active) {
            return $this->response->setJSON(['success' => false, 'message' => 'No se puede compartir un nodo perteneciente a una red desactivada'])->setStatusCode(400);
        }

        if (!$device->active || (!empty($device->expires_at) && strtotime($device->expires_at) < time())) {
            return $this->response->setJSON(['success' => false, 'message' => 'No se puede compartir un nodo inactivo o expirado'])->setStatusCode(400);
        }

        $token = bin2hex(random_bytes(24));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+60 minutes'));

        $db = \Config\Database::connect();

        // Limpiar tokens anteriores no utilizados para este nodo para reducir la superficie de exposición
        $db->table('device_share_tokens')
           ->where('device_id', $device->id)
           ->where('used', 0)
           ->delete();

        $db->table('device_share_tokens')->insert([
            'device_id'  => $device->id,
            'token'      => $token,
            'expires_at' => $expiresAt,
            'used'       => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        \App\Models\AuditLogModel::log('DEVICE_SHARE_LINK', "Generó enlace temporal compartido para el nodo '{$device->name}' (ID: {$id}) en red ID: {$network->id}");

        $shareUrl = site_url('connect/' . $token);

        return $this->response->setJSON([
            'success'   => true,
            'token'     => $token,
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

        // Limitación de tasa (Rate Limiting): Máximo 5 peticiones por minuto por usuario/IP
        $throttler = service('throttler');
        $throttleKey = 'send_config_email_' . (auth()->id() ?? 'ip_' . $this->request->getIPAddress());
        if ($throttler->check(md5($throttleKey), 5, MINUTE) === false) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Has alcanzado el límite de envíos por minuto. Por favor, espera unos instantes.'
            ])->setStatusCode(429);
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
        if (!$network || !$this->hasNetworkAccess($network, 'edit')) {
            return $this->response->setJSON(['success' => false, 'message' => 'Acceso denegado'])->setStatusCode(403);
        }

        if (!$network->active) {
            return $this->response->setJSON(['success' => false, 'message' => 'No se puede enviar la configuración de un nodo en una red desactivada'])->setStatusCode(400);
        }

        if (!$device->active || (!empty($device->expires_at) && strtotime($device->expires_at) < time())) {
            return $this->response->setJSON(['success' => false, 'message' => 'No se puede enviar la configuración de un nodo inactivo o expirado'])->setStatusCode(400);
        }

        try {
            $payload       = $this->buildDeviceConfigPayload($network, $device);
            $configContent = $payload['content'];
            $filename      = $payload['filename'];
        } catch (\RuntimeException $e) {
            return $this->response->setJSON(['success' => false, 'message' => $e->getMessage()])->setStatusCode(400);
        }

        try {
            $settings = service('settings');
            $emailService = \Config\Services::email();

            $host = $settings->get('Email.SMTPHost');
            $userSmtp = $settings->get('Email.SMTPUser');
            $port = $settings->get('Email.SMTPPort') ?: 587;
            $crypto = $settings->get('Email.SMTPCrypto') ?: 'tls';
            $encryptedPass = $settings->get('Email.SMTPPass');
            $decryptedPass = '';
            if (!empty($encryptedPass)) {
                try {
                    $decryptedPass = \Config\Services::encrypter()->decrypt(base64_decode($encryptedPass));
                } catch (\Throwable $e) {
                    log_message('error', 'Error al desencriptar contraseña SMTP: ' . $e->getMessage());
                }
            }

            if (!empty($host)) {
                $config = [
                    'protocol'   => 'smtp',
                    'SMTPHost'   => $host,
                    'SMTPUser'   => $userSmtp,
                    'SMTPPass'   => $decryptedPass,
                    'SMTPPort'   => (int) $port,
                    'SMTPCrypto' => $crypto,
                    'mailType'   => 'html',
                    'charset'    => 'utf-8',
                    'newline'    => "\r\n",
                ];
                $emailService->initialize($config);
            }

            $fromEmail = $settings->get('Email.fromEmail') ?: ($userSmtp ?: 'no-reply@tudominio.com');
            $fromName  = $settings->get('Email.fromName') ?: 'NetCrew Nebula';
            $emailService->setFrom($fromEmail, $fromName);
            $emailService->setTo($targetEmail);
            $emailService->setSubject('Configuración VPN Nebula P2P - ' . $device->name);

            // Adjuntar archivo .yml de Nebula
            $emailService->attach($configContent, 'attachment', $filename, 'text/plain');

            // Datos para la plantilla HTML
            $logoUrl = base_url('assets/images/logos/dark-logo.svg');
            $appUrl = base_url();
            $year = date('Y');
            $routingModeLabel = ($device->routing_mode ?? 'split') === 'full' 
                ? 'Túnel Completo (Todo el tráfico)' 
                : 'Túnel Dividido (Solo red VPN)';

            $htmlMessage = '
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
                <img src="' . $logoUrl . '" alt="NetCrew" style="max-width: 180px; margin-bottom: 25px; display: block;">
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width: 520px; background-color: #f8f9fa; background-image: linear-gradient(#f8f9fa, #f8f9fa); border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #e9ecef; overflow: hidden;">
                    <tr>
                        <td align="left" style="padding: 35px 30px;">
                            <h2 style="color: #333f52; -webkit-text-fill-color: #333f52; margin-top: 0; margin-bottom: 8px; text-align: center; font-weight: 600; font-size: 22px;">
                                Configuración de Nebula P2P
                            </h2>
                            <p style="color: #5a6a85; -webkit-text-fill-color: #5a6a85; font-size: 14px; line-height: 1.5; text-align: center; margin-top: 0; margin-bottom: 25px;">
                                Te enviamos los parámetros de conexión VPN para tu dispositivo en la red de NetCrew.
                            </p>

                            <!-- Tarjeta de Detalles del Dispositivo -->
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #ffffff; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 20px; font-size: 13px;">
                                <tr>
                                    <td style="padding: 10px 16px; border-bottom: 1px solid #edf2f7; color: #64748b; font-weight: 600; width: 40%;">Dispositivo</td>
                                    <td style="padding: 10px 16px; border-bottom: 1px solid #edf2f7; color: #1e293b; font-weight: 600; text-align: right;">' . esc($device->name) . '</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 16px; border-bottom: 1px solid #edf2f7; color: #64748b; font-weight: 600;">Red VPN</td>
                                    <td style="padding: 10px 16px; border-bottom: 1px solid #edf2f7; color: #1e293b; font-weight: 600; text-align: right;">' . esc($network->name) . '</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 16px; border-bottom: 1px solid #edf2f7; color: #64748b; font-weight: 600;">IP Asignada</td>
                                    <td style="padding: 10px 16px; border-bottom: 1px solid #edf2f7; color: #F38020; font-weight: 600; text-align: right; font-family: monospace;">' . esc($device->ip_address) . '</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 16px; color: #64748b; font-weight: 600;">Enrutamiento</td>
                                    <td style="padding: 10px 16px; color: #1e293b; font-weight: 600; text-align: right;">' . esc($routingModeLabel) . '</td>
                                </tr>
                            </table>

                            <!-- Aviso de archivos adjuntos -->
                            <div style="margin-top: 20px; padding: 12px 14px; background-color: #edf2f7; border-radius: 8px; color: #475569; font-size: 13px; text-align: center;">
                                <strong>Adjunto:</strong> <code>' . esc($filename) . '</code> para importar en Nebula.
                            </div>

                            <div style="text-align: center; margin-top: 25px;">
                                <a href="' . $appUrl . '" style="display: inline-block; padding: 11px 22px; background-color: #F38020; background-image: linear-gradient(#F38020, #F38020); color: #ffffff; -webkit-text-fill-color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 14px;">
                                    Acceder a NetCrew
                                </a>
                            </div>
                        </td>
                    </tr>
                </table>

                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width: 520px; margin-top: 20px;">
                    <tr>
                        <td align="center" style="padding: 0 20px;">
                            <p style="color: #8c98a4; -webkit-text-fill-color: #8c98a4; font-size: 12px; line-height: 1.5; margin: 0;">
                                &copy; ' . $year . ' NetCrew &bull; Gestión Segura de Redes Nebula P2P
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';

            $emailService->setMessage($htmlMessage);

            if ($emailService->send()) {
                \App\Models\AuditLogModel::log('DEVICE_SEND_EMAIL', "Envió configuración del nodo '{$device->name}' (ID: {$id}) por correo a: {$targetEmail}");
                return $this->response->setJSON(['success' => true, 'message' => 'Configuración enviada exitosamente a ' . esc($targetEmail)]);
            } else {
                $debugger = $emailService->printDebugger(['headers']);
                log_message('error', 'Fallo al enviar correo de configuración: ' . $debugger);
                return $this->response->setJSON(['success' => false, 'message' => 'No se pudo enviar el correo. Revisa la configuración SMTP en Ajustes.'])->setStatusCode(500);
            }
        } catch (\Throwable $e) {
            log_message('error', 'Excepción al enviar correo de configuración: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'Error al procesar el envío de correo.'])->setStatusCode(500);
        }
    }

    // ---------------------------------------------------------------------
    // Vista pública de conexión compartida (Sin autenticación requerida)
    // ---------------------------------------------------------------------
    public function connectShare($token)
    {
        // 1. Detección de bots y rastreadores de vista previa para evitar que consuman o filtren el secreto
        $userAgent = (string) $this->request->getUserAgent();
        if (preg_match('/(facebookexternalhit|Twitterbot|Slackbot|TelegramBot|WhatsApp|Discordbot|SkypeUriPreview|Google-Read-Aloud|meta-externalagent)/i', $userAgent)) {
            return $this->response
                ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate')
                ->setHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
                ->setBody('<!DOCTYPE html><html><head><title>NetCrew VPN Link</title><meta name="robots" content="noindex,nofollow"></head><body>Enlace de conexión temporal NetCrew.</body></html>');
        }

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
            return view('errors/html/error_404', [
                'message' => 'Dispositivo no encontrado.'
            ]);
        }

        $network = $this->networkModel->find($device->network_id);
        if (!$network) {
            return view('errors/html/error_404', [
                'message' => 'Red no encontrada.'
            ]);
        }

        // Validar que la red y el dispositivo continúen activos y no hayan expirado
        if (!$network->active || !$device->active || (!empty($device->expires_at) && strtotime($device->expires_at) < time())) {
            return view('errors/html/error_404', [
                'message' => 'El dispositivo o la red están inactivos o han expirado.'
            ]);
        }

        // Consumir el token atómicamente en la primera visualización
        $db->table('device_share_tokens')
           ->where('id', $row->id)
           ->where('used', 0)
           ->update(['used' => 1]);

        if ($db->affectedRows() !== 1) {
            return view('errors/html/error_404', [
                'message' => 'El enlace de conexión ya ha sido utilizado.'
            ]);
        }

        try {
            $payload      = $this->buildDeviceConfigPayload($network, $device);
            $configString = $payload['content'];
        } catch (\RuntimeException $e) {
            return view('errors/html/error_404', [
                'message' => $e->getMessage()
            ]);
        }

        $data = [
            'token'   => $token,
            'device'  => $device,
            'network' => $network,
            'config'  => $configString,
        ];

        // Añadir cabeceras para prevenir almacenamiento en caché o indexación
        $this->response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                       ->setHeader('Pragma', 'no-cache')
                       ->setHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
                       ->setHeader('Referrer-Policy', 'no-referrer');

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
            return $this->response->setStatusCode(404)->setBody(
                view('errors/html/error_404', [
                    'message' => 'El enlace de conexión ha expirado o no es válido.'
                ])
            );
        }

        $device = $this->deviceModel->find($row->device_id);
        if (!$device) {
            return $this->response->setStatusCode(404)->setBody(
                view('errors/html/error_404', [
                    'message' => 'Dispositivo no encontrado.'
                ])
            );
        }

        $network = $this->networkModel->find($device->network_id);
        if (!$network) {
            return $this->response->setStatusCode(404)->setBody(
                view('errors/html/error_404', [
                    'message' => 'Red no encontrada.'
                ])
            );
        }

        if (!$network->active || !$device->active || (!empty($device->expires_at) && strtotime($device->expires_at) < time())) {
            return $this->response->setStatusCode(404)->setBody(
                view('errors/html/error_404', [
                    'message' => 'El dispositivo o la red están inactivos o han expirado.'
                ])
            );
        }

        try {
            $payload = $this->buildDeviceConfigPayload($network, $device);
        } catch (\RuntimeException $e) {
            return $this->response->setStatusCode(404)->setBody(
                view('errors/html/error_404', [
                    'message' => $e->getMessage()
                ])
            );
        }

        $platform = (string) ($this->request->getGet('platform') ?: 'yml');
        $slug     = url_title((string) $device->name, '-', true) ?: 'nodo-' . $device->id;
        $bundle   = \App\Libraries\NebulaInstallerService::buildForPlatform($platform, $slug, $payload['content']);

        return $this->response->setHeader('Content-Type', $bundle['contentType'])
                                ->setHeader('Content-Disposition', 'attachment; filename="' . $bundle['filename'] . '"')
                                ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                                ->setHeader('Pragma', 'no-cache')
                                ->setHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
                                ->setHeader('Referrer-Policy', 'no-referrer')
                                ->setBody($bundle['body']);
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
    // Helper: Construye el contenido de configuración Nebula (.yml)
    // ---------------------------------------------------------------------
    protected function buildDeviceConfigPayload(object $network, object $device): array
    {
        $slug         = url_title($device->name, '-', true) ?: 'device';
        $decryptedKey = $this->deviceModel->decryptKey((string) $device->nebula_key);
        if (empty($device->nebula_crt) || $decryptedKey === '' || empty($network->nebula_ca_crt)) {
            throw new \RuntimeException('No se pudo desencriptar el certificado o la llave privada Nebula del nodo.');
        }

        return [
            'content'   => NebulaService::buildClientConfig($network, $device, $device->nebula_crt, $decryptedKey),
            'filename'  => $slug . '.yml',
            'extension' => 'yml',
        ];
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
            // Estado en tiempo real (Nebula P2P)
            'wg' => null,
        ];

        // Estado del nodo en tiempo real
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
    // Helper: Estado en tiempo real de un peer desde el Lighthouse Nebula
    // ---------------------------------------------------------------------
    protected function fetchPeerStatus(object $device): ?array
    {
        $telemetry = NebulaService::getLighthousePeerTelemetry();
        $fp        = strtolower((string) ($device->public_key ?? ''));
        $ip        = (string) ($device->ip_address ?? '');

        $peer = $telemetry['by_fp'][$fp] ?? $telemetry['by_ip'][$ip] ?? null;
        if (! $peer) {
            return null;
        }

        if ((int) ($device->active ?? 0) === 1 && $ip !== '') {
            $reachable = NebulaService::probeReachableIps([$ip]);
            if ($reachable !== null) {
                $peer['connected'] = isset($reachable[$ip]);
            }
        }

        if (! empty($peer['latest_handshake'])) {
            $this->updateLastSeenFromHandshake($device, (int) $peer['latest_handshake']);
        }

        return $peer;
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

        $network = $this->networkModel->find($device->network_id);
        if (!$network) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Red no encontrada'])->setStatusCode(404);
        }
        if (!$this->hasNetworkAccess($network, 'view')) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'No tienes permisos para esta red'])->setStatusCode(403);
        }

        // 1. Si el servidor local tiene la interfaz nebula1 activa, hacer ping directo
        @exec('ip link show nebula1 >/dev/null 2>&1', $tunOut, $tunCode);
        if ($tunCode === 0) {
            @exec('ping -c 4 -W 2 ' . escapeshellarg($device->ip_address) . ' 2>&1', $pingOut, $pingCode);
            $output = implode("\n", $pingOut);
            if ($pingCode === 0) {
                return $this->response->setJSON(['status' => 'success', 'output' => $output]);
            }
            return $this->response->setJSON([
                'status'  => 'error',
                'message' => 'El dispositivo no respondió al Ping (ICMP). Puede estar apagado, desconectado, o un Firewall está bloqueando ICMP.',
                'output'  => $output,
            ]);
        }

        // 2. Si hay SSH configurado, intentar por SSH
        if (NebulaService::isSshConfigured()) {
            try {
                $ssh        = NebulaService::sshFromSettings(10);
                $output     = (string) $ssh->exec('ping -c 4 -W 2 ' . escapeshellarg($device->ip_address) . ' 2>&1');
                $exitStatus = $ssh->getExitStatus();

                if ($exitStatus === 0) {
                    return $this->response->setJSON([
                        'status' => 'success',
                        'output' => $output
                    ]);
                }

                return $this->response->setJSON([
                    'status'  => 'error',
                    'message' => 'El dispositivo no respondió al Ping (ICMP). Puede estar apagado, desconectado, o un Firewall está bloqueando ICMP.',
                    'output'  => $output
                ]);
            } catch (\Throwable $e) {
                return $this->response->setJSON([
                    'status'  => 'error',
                    'message' => 'Error de conexión SSH con el servidor.',
                    'output'  => $e->getMessage()
                ]);
            }
        }

        // 3. Lighthouse sin /dev/net/tun (modo descubrimiento/relay LXC): informar estado real del handshake
        $peer = $this->fetchPeerStatus($device);
        if ($peer && ! empty($peer['connected'])) {
            $when = date('d/m/Y H:i:s', (int) $peer['latest_handshake']);
            return $this->response->setJSON([
                'status' => 'success',
                'output' => "Nodo conectado al Lighthouse Nebula.\nIP VPN: {$device->ip_address}\nIP Pública: {$peer['endpoint']}\nÚltimo handshake: {$when}\n\n(El servidor opera en modo Lighthouse puro sin /dev/net/tun; los dispositivos P2P se comunican directamente entre sí).",
            ]);
        }

        return $this->response->setJSON([
            'status'  => 'error',
            'message' => 'El nodo aún no ha realizado handshake reciente con el Lighthouse.',
        ]);
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

        $devices   = $this->deviceModel->where('network_id', $networkId)->findAll();
        $telemetry = NebulaService::getLighthousePeerTelemetry();
        $activeIps = [];
        foreach ($devices as $dev) {
            if ((int) ($dev->active ?? 0) === 1 && ! empty($dev->ip_address)) {
                $activeIps[] = (string) $dev->ip_address;
            }
        }
        $reachable = NebulaService::probeReachableIps($activeIps);
        $peers     = [];

        foreach ($devices as $dev) {
            $fp   = strtolower((string) ($dev->public_key ?? ''));
            $ip   = (string) ($dev->ip_address ?? '');
            $peer = $telemetry['by_fp'][$fp] ?? $telemetry['by_ip'][$ip] ?? null;

            $isConnected = $peer ? (bool) ($peer['connected'] ?? false) : false;
            if ($reachable !== null && (int) ($dev->active ?? 0) === 1) {
                $isConnected = isset($reachable[$ip]);
            }

            if ($peer || $isConnected) {
                $ts = $peer ? (int) ($peer['latest_handshake'] ?? 0) : 0;
                if ($ts > 0) {
                    $this->updateLastSeenFromHandshake($dev, $ts);
                }
                $peers[(string) $dev->public_key] = [
                    'connected'      => $isConnected,
                    'endpoint'       => $peer ? (string) ($peer['endpoint'] ?? '') : '',
                    'last_seen_date' => $ts > 0 ? date('d/m/Y', $ts) : null,
                    'last_seen_time' => $ts > 0 ? date('H:i', $ts) : null,
                ];
            }
        }

        return $this->response->setJSON([
            'success'    => true,
            'engine'     => 'nebula',
            'remoteTime' => time(),
            'peers'      => $peers,
        ]);
    }

    // ---------------------------------------------------------------------
    // Actualizar último acceso del dispositivo
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
}
