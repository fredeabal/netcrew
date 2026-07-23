<?php

namespace App\Controllers;

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
        $wg = null;
        $globalSettings = service('settings');
        $interface = $globalSettings->get('WireGuard.interface') ?: 'wg0';

        try {
            $ssh = $this->getSshSession(5);
            $wgCmd = $this->wrapSudoCommand("wg show " . escapeshellarg($interface) . " dump");
            $cmd = $wgCmd . " && echo '---TIME---' && date +%s";

            $output      = $ssh->exec($cmd);
            $outputParts = explode('---TIME---', $output);
            $wgOutput    = $outputParts[0] ?? '';
            $remoteTime  = isset($outputParts[1]) ? (int)trim($outputParts[1]) : time();

            $lines = explode("\n", $wgOutput);
            for ($i = 1; $i < count($lines); $i++) {
                $line = trim($lines[$i]);
                if (empty($line)) continue;
                $parts = preg_split('/\s+/', $line);
                if (count($parts) >= 7 && $parts[0] === $device->public_key) {
                    $handshake = (int)$parts[4];
                    $rx        = (int)$parts[5];
                    $tx        = (int)$parts[6];
                    $endpoint  = $parts[2] !== '(none)' ? $parts[2] : null;

                    $wg = [
                        'connected'        => ($handshake > 0 && ($remoteTime - $handshake) < 180),
                        'endpoint'         => $endpoint,
                        'public_ip'        => $endpoint ? explode(':', $endpoint)[0] : null,
                        'port'             => $endpoint ? (explode(':', $endpoint)[1] ?? null) : null,
                        'latest_handshake' => $handshake ?: null,
                        'remote_time'      => $remoteTime,
                        'rx_bytes'         => $rx,
                        'tx_bytes'         => $tx,
                    ];

                    $lastSeen = $this->updateLastSeenFromHandshake($device, $handshake);
                    if ($lastSeen) {
                        $device->last_seen = $lastSeen;
                    }
                    break;
                }
            }
        } catch (\Exception $e) {
            log_message('notice', 'No se pudo obtener estado WireGuard en tiempo real para nodo ' . $id . ': ' . $e->getMessage());
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

        $networkId = $this->request->getPost('network_id');
        $name = $this->request->getPost('name');

        if (empty($networkId) || empty($name)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'El nombre del dispositivo es obligatorio.'
            ])->setStatusCode(400);
        }

        // Validar que SSH esté configurado a nivel global antes de crear nodos
        $globalSettings = service('settings');
        $host = $globalSettings->get('WireGuard.sshHost');
        $user = $globalSettings->get('WireGuard.sshUser');

        if (empty($host) || empty($user)) {
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

        // Validar propiedad de la red
        if ($network->owner_id != auth()->id() && !auth()->user()->inGroup('superadmin', 'supervisor')) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'No tienes permisos para agregar nodos a esta red.'
            ])->setStatusCode(403);
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

        $deviceType = $this->request->getPost('device_type') ?? 'pc';
        if (!in_array($deviceType, ['pc', 'server', 'mobile', 'tablet', 'router'])) {
            $deviceType = 'pc';
        }

        // Registrar dispositivo
        $data = [
            'network_id'  => (int)$networkId,
            'name'        => trim($name),
            'ip_address'  => $ipAddress,
            'public_key'  => $publicKey,
            'private_key' => $privateKey,
            'active'      => 1,
            'device_type' => $deviceType
        ];

        if ($this->deviceModel->save($data)) {
            $insertId = $this->deviceModel->getInsertID();
            $deviceObj = $this->deviceModel->find($insertId);
            if ($deviceObj) {
                $this->syncNodeWithLxc($network, $deviceObj, 'add');
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

        $name = trim($this->request->getPost('name') ?? '');
        $deviceType = $this->request->getPost('device_type') ?? 'pc';

        if (empty($name)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'El nombre es obligatorio.'
            ])->setStatusCode(400);
        }

        if (!in_array($deviceType, ['pc', 'server', 'mobile', 'tablet', 'router'])) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Tipo de dispositivo no válido.'
            ])->setStatusCode(400);
        }

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

        if ($this->deviceModel->delete($id)) {
            $this->syncNodeWithLxc($network, $device, 'remove');
            return redirect()->to("networks/{$networkId}/devices")->with('message', 'Nodo eliminado.');
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

        if ($this->deviceModel->update($id, ['active' => $newActiveStatus])) {
            $device->active = $newActiveStatus;
            $this->syncNodeWithLxc($network, $device, $newActiveStatus ? 'add' : 'remove');
            $status = $newActiveStatus ? 'activado' : 'desactivado';
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

        // Validar propiedad de la red
        if ($network->owner_id != auth()->id() && !auth()->user()->inGroup('superadmin', 'supervisor')) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Acceso denegado.'
            ])->setStatusCode(403);
        }

        $decryptedPrivateKey = $this->deviceModel->decryptKey($device->private_key);
        $serverPublicKey = service('settings')->get('WireGuard.publicKey') ?: 'lQ7kfC0if1tK0M91yaShyj13swsGNE/W+v7wg7LX6VE=';
        $serverEndpoint = service('settings')->get('WireGuard.endpoint') ?: 
            (isset($_SERVER['HTTP_HOST']) ? explode(':', $_SERVER['HTTP_HOST'])[0] . ':51820' : 'vpn.tudominio.com:51820');

        $configString = $this->buildConfigContent($decryptedPrivateKey, $device->ip_address, $serverPublicKey, $network->cidr, $serverEndpoint);

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
        $serverPublicKey = service('settings')->get('WireGuard.publicKey') ?: 'lQ7kfC0if1tK0M91yaShyj13swsGNE/W+v7wg7LX6VE=';
        $serverEndpoint = service('settings')->get('WireGuard.endpoint') ?: 
            (isset($_SERVER['HTTP_HOST']) ? explode(':', $_SERVER['HTTP_HOST'])[0] . ':51820' : 'vpn.tudominio.com:51820');

        $configString = $this->buildConfigContent($decryptedPrivateKey, $device->ip_address, $serverPublicKey, $network->cidr, $serverEndpoint);

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
    protected function buildConfigContent($privateKey, $ipAddress, $serverPublicKey, $allowedIps, $serverEndpoint)
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
        $globalSettings = service('settings');
        $interface = $globalSettings->get('WireGuard.interface') ?: 'wg0';

        try {
            $ssh = $this->getSshSession(5);
            $wgCmd = $this->wrapSudoCommand("wg show " . escapeshellarg($interface) . " dump");
            $cmd = $wgCmd . " && echo '---TIME---' && date +%s";

            $output      = $ssh->exec($cmd);
            $outputParts = explode('---TIME---', $output);
            $wgOutput    = $outputParts[0] ?? '';
            $remoteTime  = isset($outputParts[1]) ? (int)trim($outputParts[1]) : time();

            $lines = explode("\n", $wgOutput);
            for ($i = 1; $i < count($lines); $i++) {
                $line = trim($lines[$i]);
                if (empty($line)) continue;
                $parts = preg_split('/\s+/', $line);
                if (count($parts) >= 7 && $parts[0] === $device->public_key) {
                    $handshake = (int)$parts[4];
                    $rx        = (int)$parts[5];
                    $tx        = (int)$parts[6];
                    $endpoint  = $parts[2] !== '(none)' ? $parts[2] : null;
                    $connected = ($handshake > 0 && ($remoteTime - $handshake) < 180);

                    $result['wg'] = [
                        'connected'        => $connected,
                        'endpoint'         => $endpoint,
                        'public_ip'        => $endpoint ? explode(':', $endpoint)[0] : null,
                        'port'             => $endpoint ? (explode(':', $endpoint)[1] ?? null) : null,
                        'latest_handshake' => $handshake ?: null,
                        'rx_bytes'         => $rx,
                        'tx_bytes'         => $tx,
                    ];

                    $lastSeen = $this->updateLastSeenFromHandshake($device, $handshake);
                    if ($lastSeen) {
                        $result['last_seen'] = $lastSeen;
                    }
                    break;
                }
            }
        } catch (\Exception $e) {
            log_message('notice', 'No se pudo obtener estado WireGuard en tiempo real para nodo ' . $id . ': ' . $e->getMessage());
        }

        return $this->response->setJSON([
            'success' => true,
            'device'  => $result,
        ]);
    }

    /**
     * Sincroniza un peer (dispositivo) con el LXC de WireGuard vía SSH en tiempo real.
     */
    protected function syncNodeWithLxc($network, $device, string $action): bool
    {
        $globalSettings = service('settings');
        $interface = $globalSettings->get('WireGuard.interface') ?: 'wg0';
        $pubkey = $device->public_key;
        $ip = $device->ip_address . '/32';

        if ($action === 'add') {
            $cmd1 = "wg set " . escapeshellarg($interface) . " peer " . escapeshellarg($pubkey) . " allowed-ips " . escapeshellarg($ip);
        } else {
            $cmd1 = "wg set " . escapeshellarg($interface) . " peer " . escapeshellarg($pubkey) . " remove";
        }
        $cmd2 = "wg-quick save " . escapeshellarg($interface);
        // Construir script dinámico de iptables para aislar subredes
        $networkModel = new \App\Models\NetworkModel();
        $networks = $networkModel->findAll();
        
        $iptablesScript = "sysctl -w net.ipv4.ip_forward=1; ";
        
        // 1. Limpiar TODAS las reglas FORWARD genéricas e inseguras de wg0
        $iptablesScript .= "while iptables -D FORWARD -i " . escapeshellarg($interface) . " -o " . escapeshellarg($interface) . " -j ACCEPT 2>/dev/null; do true; done; ";
        $iptablesScript .= "while iptables -D FORWARD -i " . escapeshellarg($interface) . " -o " . escapeshellarg($interface) . " -j DROP 2>/dev/null; do true; done; ";
        $iptablesScript .= "while iptables -D FORWARD -i " . escapeshellarg($interface) . " -o wg+ -j DROP 2>/dev/null; do true; done; ";
        
        // 2. Limpiar las reglas específicas de subredes para evitar duplicados, y luego añadirlas
        foreach ($networks as $net) {
            $cidr = escapeshellarg($net->cidr);
            $intf = escapeshellarg($interface);
            $iptablesScript .= "while iptables -D FORWARD -i {$intf} -o {$intf} -s {$cidr} -d {$cidr} -j ACCEPT 2>/dev/null; do true; done; ";
            $iptablesScript .= "iptables -A FORWARD -i {$intf} -o {$intf} -s {$cidr} -d {$cidr} -j ACCEPT; ";
        }
        
        // 3. Regla final: DROP todo el tráfico cruzado entre diferentes subredes en wg0
        $intf = escapeshellarg($interface);
        $iptablesScript .= "iptables -A FORWARD -i {$intf} -o {$intf} -j DROP; ";
        
        // 4. Reglas de NAT y Drop hacia wg+ (otras interfaces de wireguard)
        $iptablesScript .= "iptables -A FORWARD -i {$intf} -o wg+ -j DROP; ";
        $iptablesScript .= "DEFAULT_DEV=\$(ip route show | grep default | awk '{print \$5}' | head -n1); ";
        
        // 5. Enrutar tráfico a Internet (NAT/Masquerade y FORWARD ACCEPT)
        $iptablesScript .= "while iptables -t nat -D POSTROUTING -o \"\$DEFAULT_DEV\" -j MASQUERADE 2>/dev/null; do true; done; ";
        $iptablesScript .= "while iptables -D FORWARD -i {$intf} -o \"\$DEFAULT_DEV\" -j DROP 2>/dev/null; do true; done; ";
        $iptablesScript .= "while iptables -D FORWARD -i {$intf} -o \"\$DEFAULT_DEV\" -j ACCEPT 2>/dev/null; do true; done; ";
        $iptablesScript .= "if [ ! -z \"\$DEFAULT_DEV\" ]; then iptables -t nat -A POSTROUTING -o \"\$DEFAULT_DEV\" -j MASQUERADE; iptables -A FORWARD -i {$intf} -o \"\$DEFAULT_DEV\" -j ACCEPT; fi";

        $cmd3 = $iptablesScript;

        // Construir la ejecución concatenando comandos con sudo envuelto
        $cmd = $this->wrapSudoCommand($cmd1) . " && " .
               $this->wrapSudoCommand($cmd2) . " && " .
               $this->wrapSudoCommand("bash -c " . escapeshellarg($cmd3));

        try {
            // Obtener sesión SSH usando BaseController
            $ssh = $this->getSshSession(5);

            $output = $ssh->exec($cmd);
            
            // wg set usualmente no produce salida si tiene éxito, pero podemos loguear si hay algún mensaje
            if (!empty($output)) {
                log_message('notice', "Salida del comando de sincronización WireGuard: " . $output);
            }
        } catch (\Exception $e) {
            log_message('error', "Excepción al sincronizar nodo con LXC WireGuard vía SSH: " . $e->getMessage());
            return false;
        }

        return true;
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

        $globalSettings = service('settings');
        $interface = $globalSettings->get('WireGuard.interface') ?: 'wg0';

        $peers = [];
        $remoteTime = time();
        try {
            // Obtener sesión SSH usando BaseController
            $ssh = $this->getSshSession(5);

            $wgCmd = $this->wrapSudoCommand("wg show " . escapeshellarg($interface) . " dump");
            $cmd = $wgCmd . " && echo '---TIME---' && date +%s";

            $devices = $this->deviceModel->where('network_id', $networkId)->findAll();
            $devicesByPublicKey = [];
            foreach ($devices as $device) {
                $devicesByPublicKey[$device->public_key] = $device;
            }

            $output = $ssh->exec($cmd);
            $outputParts = explode('---TIME---', $output);
            $wgOutput = $outputParts[0] ?? '';
            if (isset($outputParts[1])) {
                $remoteTime = (int)trim($outputParts[1]);
            }

            $lines = explode("\n", $wgOutput);
            // Saltar la primera línea (info de la interfaz)
            for ($i = 1; $i < count($lines); $i++) {
                $line = trim($lines[$i]);
                if (empty($line)) continue;
                $parts = preg_split('/\s+/', $line);
                if (count($parts) >= 7) {
                    $pubKey = $parts[0];
                    $endpoint = $parts[2];
                    $handshake = (int)$parts[4];
                    $rx = (int)$parts[5];
                    $tx = (int)$parts[6];

                    $connected = ($handshake > 0 && ($remoteTime - $handshake) < 180);
                    $lastSeen = null;

                    if (isset($devicesByPublicKey[$pubKey])) {
                        $lastSeen = $this->updateLastSeenFromHandshake($devicesByPublicKey[$pubKey], $handshake);
                    }

                    $peers[$pubKey] = [
                        'connected' => $connected,
                        'endpoint' => $endpoint !== '(none)' ? $endpoint : null,
                        'latest_handshake' => $handshake,
                        'last_seen' => $lastSeen,
                        'last_seen_date' => $lastSeen ? date('d/m/Y', strtotime($lastSeen)) : null,
                        'last_seen_time' => $lastSeen ? date('H:i', strtotime($lastSeen)) : null,
                        'rx' => $rx,
                        'tx' => $tx,
                    ];
                }
            }
        } catch (\Exception $e) {
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

        if ($handshake !== $currentLastSeen) {
            $this->deviceModel->update($device->id, ['last_seen' => $lastSeen]);
            $device->last_seen = $lastSeen;
        }

        return $device->last_seen ?? $lastSeen;
    }
}
