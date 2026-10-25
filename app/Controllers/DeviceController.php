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

        // Validar propiedad de la red
        if ($network->owner_id != auth()->id() && !auth()->user()->inGroup('superadmin', 'supervisor')) {
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

        // Validar propiedad de la red
        if ($network->owner_id != auth()->id() && !auth()->user()->inGroup('superadmin', 'supervisor')) {
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

        // Validar propiedad
        if ($network->owner_id != auth()->id() && !auth()->user()->inGroup('superadmin', 'supervisor')) {
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
        if ($this->request->getMethod() !== 'POST') {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Método no permitido.'
            ])->setStatusCode(405);
        }

        $rules = [
            'network_id'  => 'required|is_natural_no_zero',
            'name'        => 'required|min_length[2]|max_length[100]',
            'device_type' => 'permit_empty|in_list[pc,server,mobile,tablet,router]',
        ];

        if (!$this->validate($rules)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => implode(' ', $this->validator->getErrors()),
                'errors'  => $this->validator->getErrors()
            ])->setStatusCode(400);
        }

        $networkId  = (int) $this->request->getPost('network_id');
        $name       = trim((string) $this->request->getPost('name'));
        $deviceType = (string) ($this->request->getPost('device_type') ?: 'pc');

        // Validar que SSH esté configurado a nivel global antes de crear nodos
        if (!WireguardService::isConfigured()) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'No se pueden crear nodos: El Administrador no ha configurado la conexión SSH del servidor VPN.'
            ])->setStatusCode(403);
        }

        $network = $this->networkModel->find($networkId);
        if (!$network) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Red no encontrada.'
            ])->setStatusCode(404);
        }

        // Validar que la red esté activa
        if (!$network->active) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'No se pueden agregar nodos a una red desactivada.'
            ])->setStatusCode(400);
        }

        // Validar propiedad de la red
        if ($network->owner_id != auth()->id() && !auth()->user()->inGroup('superadmin', 'supervisor')) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'No tienes permisos para agregar nodos a esta red.'
            ])->setStatusCode(403);
        }

        // Validar capacidad de la red (/24 = máx 253 nodos)
        $currentNodesCount = $this->deviceModel->where('network_id', $networkId)->countAllResults();
        if ($currentNodesCount >= 253) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'La red ha alcanzado su capacidad máxima (253 nodos).'
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
            return $this->response->setJSON([
                'success' => false,
                'message' => 'No hay direcciones IP disponibles en el rango de esta red.'
            ])->setStatusCode(400);
        }

        // Generar par de llaves WireGuard (X25519 Curve25519) usando libsodium
        if (!extension_loaded('sodium')) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'La extensión PHP libsodium es requerida para generar las llaves criptográficas.'
            ])->setStatusCode(500);
        }

        $keypair = sodium_crypto_box_keypair();
        $privateKey = base64_encode(sodium_crypto_box_secretkey($keypair));
        $publicKey = base64_encode(sodium_crypto_box_publickey($keypair));

        // Registrar dispositivo
        $data = [
            'network_id'  => $networkId,
            'name'        => $name,
            'ip_address'  => $ipAddress,
            'public_key'  => $publicKey,
            'private_key' => $privateKey,
            'active'      => 1,
            'device_type' => $deviceType
        ];

        if ($this->deviceModel->save($data)) {
            $insertId  = $this->deviceModel->getInsertID();
            $deviceObj = $this->deviceModel->find($insertId);

            // Registrar el peer en el servidor. Si falla, se revierte el alta en la BD.
            try {
                $this->pushPeer($deviceObj, true);
            } catch (\Throwable $e) {
                log_message('error', 'No se pudo registrar el nodo ' . $insertId . ' en WireGuard: ' . $e->getMessage());
                $this->deviceModel->delete($insertId);
                $this->bestEffort(fn () => $this->pushPeer($deviceObj, false), 'revertir alta del nodo ' . $insertId);

                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'No se pudo registrar el nodo en el servidor VPN. No se ha creado. Detalle: ' . $e->getMessage(),
                ])->setStatusCode(502);
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

        return $this->response->setJSON([
            'success' => false,
            'message' => 'Error al registrar el dispositivo.',
            'errors'  => $this->deviceModel->errors()
        ])->setStatusCode(400);
    }

    // ---------------------------------------------------------------------
    // Actualizar un dispositivo (Nombre / Tipo)
    // ---------------------------------------------------------------------
    public function update($id)
    {
        if ($this->request->getMethod() !== 'POST') {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Método no permitido.'
            ])->setStatusCode(405);
        }

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

        // Validar propiedad
        if ($network->owner_id != auth()->id() && !auth()->user()->inGroup('superadmin', 'supervisor')) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'No tienes permisos para editar este nodo.'
            ])->setStatusCode(403);
        }

        $rules = [
            'name'        => 'required|min_length[2]|max_length[100]',
            'device_type' => 'required|in_list[pc,server,mobile,tablet,router]',
        ];

        if (!$this->validate($rules)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => implode(' ', $this->validator->getErrors()),
                'errors'  => $this->validator->getErrors(),
            ])->setStatusCode(400);
        }

        $name       = trim((string) $this->request->getPost('name'));
        $deviceType = (string) $this->request->getPost('device_type');

        if ($this->deviceModel->update($id, ['name' => $name, 'device_type' => $deviceType])) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Nodo actualizado correctamente.'
            ]);
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

        // Validar propiedad de la red
        if ($network->owner_id != auth()->id() && !auth()->user()->inGroup('superadmin', 'supervisor')) {
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
            return redirect()->to("networks/{$networkId}/devices")->with('message', 'Nodo eliminado.');
        }

        // Revertir: volver a registrar el peer en el servidor
        if ($device->active) {
            $this->bestEffort(fn () => $this->pushPeer($device, true), 'revertir baja del nodo ' . $id);
        }

        return redirect()->to("networks/{$networkId}/devices")->with('error', 'Error al eliminar.');
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

        // Validar propiedad de la red
        if ($network->owner_id != auth()->id() && !auth()->user()->inGroup('superadmin', 'supervisor')) {
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

        // Validar propiedad de la red
        if ($network->owner_id != auth()->id() && !auth()->user()->inGroup('superadmin', 'supervisor')) {
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

        $serverEndpoint = service('settings')->get('WireGuard.endpoint') ?: 
            (isset($_SERVER['HTTP_HOST']) ? explode(':', $_SERVER['HTTP_HOST'])[0] . ':51820' : 'vpn.tudominio.com:51820');

        $configString = $this->buildConfigContent($decryptedPrivateKey, $device->ip_address, $serverPublicKey, $network->cidr, $serverEndpoint, $network->dns);

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

        // Validar propiedad de la red
        if ($network->owner_id != auth()->id() && !auth()->user()->inGroup('superadmin', 'supervisor')) {
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

        $serverEndpoint = service('settings')->get('WireGuard.endpoint') ?: 
            (isset($_SERVER['HTTP_HOST']) ? explode(':', $_SERVER['HTTP_HOST'])[0] . ':51820' : 'vpn.tudominio.com:51820');

        $configString = $this->buildConfigContent($decryptedPrivateKey, $device->ip_address, $serverPublicKey, $network->cidr, $serverEndpoint, $network->dns);

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
    protected function buildConfigContent($privateKey, $ipAddress, $serverPublicKey, $allowedIps, $serverEndpoint, $dns = null)
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
            $config .= "DNS = " . trim($dns) . "\n";
        }
                  
        $allowedIpsList = [$allowedIps];
                  

        
        $allowedIpsString = implode(', ', $allowedIpsList);
        
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

        // Validar propiedad
        if ($network->owner_id != auth()->id() && !auth()->user()->inGroup('superadmin', 'supervisor')) {
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
            $dump = WireguardService::fromSettings(5)->dump();
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
        if ($network->owner_id != auth()->id() && !auth()->user()->inGroup('superadmin', 'supervisor')) {
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

        if ($network->owner_id != auth()->id() && !auth()->user()->inGroup('superadmin', 'supervisor')) {
            return $this->response->setJSON(['success' => false, 'message' => 'Acceso denegado.'])->setStatusCode(403);
        }

        $peers = [];
        try {
            $dump       = WireguardService::fromSettings(5)->dump();
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

        // Limitar escrituras en SQLite: solo actualizar si han pasado al menos 60 segundos
        if (abs($handshake - $currentLastSeen) >= 60) {
            $this->deviceModel->update($device->id, ['last_seen' => $lastSeen]);
            $device->last_seen = $lastSeen;
        }

        return $device->last_seen ?? $lastSeen;
    }
}
