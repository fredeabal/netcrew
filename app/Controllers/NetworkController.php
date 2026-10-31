<?php

namespace App\Controllers;

use App\Libraries\WireguardService;
use App\Models\NetworkModel;
use CodeIgniter\Shield\Models\UserModel;

class NetworkController extends BaseController
{
    protected $networkModel;
    protected $userModel;

    public function __construct()
    {
        $this->networkModel = new NetworkModel();
        $this->userModel = new UserModel();
    }

    // ---------------------------------------------------------------------
    // Mostrar listado de redes
    // ---------------------------------------------------------------------
    public function index()
    {

        $search = $this->request->getGet('q');
        $isSuperAdmin = auth()->user()->inGroup('superadmin', 'supervisor');

        $data = [
            'title'    => 'Redes',
            'networks' => $this->networkModel->getNetworksForUser(auth()->id(), $isSuperAdmin, $search, 10),
            'pager'    => $this->networkModel->pager,
            'search'   => $search,
        ];

        echo view('template/header', $data);
        echo view('networks/index');
        echo view('template/footer');
    }

    // ---------------------------------------------------------------------
    // Mostrar formulario de creación
    // ---------------------------------------------------------------------
    public function create()
    {

        $isSuperAdmin = auth()->user()->inGroup('superadmin', 'supervisor');

        $data = [
            'title' => 'Crear Red',
            'users' => $isSuperAdmin ? $this->userModel->findAll() : null,
        ];

        echo view('template/header', $data);
        echo view('networks/create');
        echo view('template/footer');
    }

    // ---------------------------------------------------------------------
    // Guardar nueva red
    // ---------------------------------------------------------------------
    public function store()
    {


        $data = $this->request->getPost();

        // Si no es superadmin ni supervisor, forzar que el owner sea él mismo
        $user = auth()->user();
        if (! $user->inGroup('superadmin', 'supervisor')) {
            $data['owner_id'] = $user->id;


        }

        // Forzar siempre a /24 y asegurar que termine en .0 (dirección de red base)
        $cidr = $data['cidr'] ?? '';
        $ip_part = explode('/', $cidr)[0];
        $octets = explode('.', $ip_part);
        if (count($octets) === 4) {
            $octets[3] = '0';
            $ip_part = implode('.', $octets);
        }
        $cidr = $ip_part . '/24';
        $data['cidr'] = $cidr;

        // Validación adicional para asegurar formato IPv4 válido y en rango privado/CGNAT
        if (!preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\/24$/', $cidr) || !$this->isValidPrivateNetworkIp($ip_part)) {
            return redirect()->back()->withInput()->with('error', 'Formato de IP no válido. Debes usar un rango IPv4 privado válido (10.x.x.0/24, 172.16-31.x.0/24, 192.168.x.0/24 o 100.64-127.x.0/24).');
        }

        // Validar y sanitizar servidores DNS si se proporcionaron
        if (!empty($data['dns'])) {
            $cleanDns = $this->validateAndNormalizeDns((string) $data['dns']);
            if ($cleanDns === null) {
                return redirect()->back()->withInput()->with('error', 'El formato de servidores DNS no es válido. Ingresa direcciones IP o dominios válidos separados por comas.');
            }
            $data['dns'] = $cleanDns;
        }

        // Verificar solapamiento con redes restringidas
        $settings = service('settings');
        $restricted = $settings->get('WireGuard.restrictedCidrs');
        if (!empty($restricted)) {
            $restrictedList = array_map('trim', explode(',', $restricted));
            foreach ($restrictedList as $resCidr) {
                if (strpos($resCidr, '/') === false) {
                    $resCidr .= '/24';
                }
                if ($this->checkCidrOverlap($cidr, $resCidr)) {
                    return redirect()->back()->withInput()->with('error', "No puedes usar este rango porque es una red restringida.");
                }
            }
        }



        if ($this->networkModel->save($data)) {
            // Sincronizar firewall de WireGuard si el servidor está configurado
            if (WireguardService::isConfigured()) {
                try {
                    WireguardService::fromSettings(5)->syncFirewall();
                } catch (\Throwable $e) {
                    log_message('notice', 'No se pudo sincronizar el firewall de WireGuard tras crear la red: ' . $e->getMessage());
                }
            }

            return redirect()->to('/networks')->with('message', 'Red creada.');
        }

        return redirect()->back()->withInput()->with('errors', $this->networkModel->errors());
    }

    // ---------------------------------------------------------------------
    // Mostrar formulario de edición
    // ---------------------------------------------------------------------
    public function edit($id)
    {
        $network = $this->networkModel->find($id);

        if (! $network) {
            return redirect()->to('/networks')->with('error', 'Red no encontrada.');
        }

        $isSuperAdmin = auth()->user()->inGroup('superadmin', 'supervisor');

        // Validar propiedad de la red
        if ($network->owner_id != auth()->id() && !$isSuperAdmin) {
            return redirect()->to('/networks')->with('error', 'No tienes permisos para editar esta red.');
        }

        $data = [
            'title'   => 'Editar Red',
            'network' => $network,
            'users'   => $isSuperAdmin ? $this->userModel->findAll() : null,
        ];

        echo view('template/header', $data);
        echo view('networks/edit');
        echo view('template/footer');
    }

    // ---------------------------------------------------------------------
    // Actualizar red
    // ---------------------------------------------------------------------
    public function update($id)
    {
        $network = $this->networkModel->find($id);

        if (! $network) {
            return redirect()->to('/networks')->with('error', 'Red no encontrada.');
        }

        $isSuperAdmin = auth()->user()->inGroup('superadmin', 'supervisor');

        // Validar propiedad
        if ($network->owner_id != auth()->id() && !$isSuperAdmin) {
            return redirect()->to('/networks')->with('error', 'No tienes permisos.');
        }

        $data = $this->request->getPost();
        
        // Añadir el ID para que la regla de validación is_unique pueda ignorar la red actual
        $data['id'] = $id;
        
        // Si no es superadmin, forzar mantener el owner original
        if (! $isSuperAdmin) {
            $data['owner_id'] = $network->owner_id;
        }

        $data['active'] = isset($data['active']) ? 1 : 0;



        // Forzar siempre a /24 y asegurar que termine en .0 (dirección de red base)
        $cidr = $data['cidr'] ?? '';
        $ip_part = explode('/', $cidr)[0];
        $octets = explode('.', $ip_part);
        if (count($octets) === 4) {
            $octets[3] = '0';
            $ip_part = implode('.', $octets);
        }
        $cidr = $ip_part . '/24';
        $data['cidr'] = $cidr;

        // Validación adicional para asegurar formato IPv4 válido y en rango privado/CGNAT
        if (!preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\/24$/', $cidr) || !$this->isValidPrivateNetworkIp($ip_part)) {
            return redirect()->back()->withInput()->with('error', 'Formato de IP no válido. Debes usar un rango IPv4 privado válido (10.x.x.0/24, 172.16-31.x.0/24, 192.168.x.0/24 o 100.64-127.x.0/24).');
        }

        // Validar y sanitizar servidores DNS si se proporcionaron
        if (!empty($data['dns'])) {
            $cleanDns = $this->validateAndNormalizeDns((string) $data['dns']);
            if ($cleanDns === null) {
                return redirect()->back()->withInput()->with('error', 'El formato de servidores DNS no es válido. Ingresa direcciones IP o dominios válidos separados por comas.');
            }
            $data['dns'] = $cleanDns;
        }

        // Verificar solapamiento con redes restringidas
        $settings = service('settings');
        $restricted = $settings->get('WireGuard.restrictedCidrs');
        if (!empty($restricted)) {
            $restrictedList = array_map('trim', explode(',', $restricted));
            foreach ($restrictedList as $resCidr) {
                if (strpos($resCidr, '/') === false) {
                    $resCidr .= '/24';
                }
                if ($this->checkCidrOverlap($cidr, $resCidr)) {
                    return redirect()->back()->withInput()->with('error', "No puedes usar este rango porque es una red restringida.");
                }
            }
        }

        // Si el CIDR cambió y la red ya tiene dispositivos registrados, no permitir el cambio
        $deviceModel = new \App\Models\DeviceModel();
        $currentNodesCount = $deviceModel->where('network_id', $id)->countAllResults();

        if ($data['cidr'] !== $network->cidr && $currentNodesCount > 0) {
            return redirect()->back()->withInput()->with('error', 'No puedes cambiar el rango CIDR de una red que ya contiene dispositivos asignados. Elimina o mueve los nodos primero.');
        }

        // Capacidad /24 = 253 nodos
        $maxNodes = 253;
        if ($currentNodesCount > $maxNodes) {
            return redirect()->back()->withInput()->with('error', "Capacidad excedida<br><small class='text-muted'>Esta red admite un máximo de {$maxNodes} nodos (actualmente tienes {$currentNodesCount}).</small>");
        }

        $cidrChanged = ($data['cidr'] !== $network->cidr);

        if ($this->networkModel->update($id, $data)) {
            // Si el CIDR cambió en una red vacía, regenerar el script y reglas de firewall
            if ($cidrChanged && WireguardService::isConfigured()) {
                try {
                    WireguardService::fromSettings(5)->syncFirewall();
                } catch (\Throwable $e) {
                    log_message('notice', 'No se pudo actualizar el firewall tras modificar la red: ' . $e->getMessage());
                }
            }

            return redirect()->to('/networks')->with('message', 'Red actualizada.');
        }

        return redirect()->back()->withInput()->with('errors', $this->networkModel->errors());
    }

    // ---------------------------------------------------------------------
    // Eliminar red
    // ---------------------------------------------------------------------
    public function delete($id)
    {
        // El permiso se valida abajo verificando el owner_id

        $network = $this->networkModel->find($id);

        if (! $network) {
            return redirect()->to('/networks')->with('error', 'Red no encontrada.');
        }

        // Validar propiedad
        if ($network->owner_id != auth()->id() && !auth()->user()->inGroup('superadmin', 'supervisor')) {
            return redirect()->to('/networks')->with('error', 'Sin permisos.');
        }

        // 1. Limpiar peers en el servidor WireGuard y regenerar firewall
        if (WireguardService::isConfigured()) {
            try {
                $deviceModel = new \App\Models\DeviceModel();
                $devices     = $deviceModel->where('network_id', $id)->findAll();
                $keys        = array_filter(array_column($devices, 'public_key'));

                $wg = WireguardService::fromSettings(5);
                if (!empty($keys)) {
                    $wg->removePeers($keys);
                    $wg->saveConfig();
                }
                // Actualizar firewall y direcciones en la interfaz excluyendo la red a borrar
                $wg->syncFirewall(WireguardService::networkCidrs((int)$id));
            } catch (\Throwable $e) {
                log_message('error', 'Excepción al desasociar red de WireGuard: ' . $e->getMessage());
            }
        }

        // 2. Transacción de base de datos para eliminar dispositivos y la red
        $db = \Config\Database::connect();
        $db->transStart();

        $deviceModel = new \App\Models\DeviceModel();
        $deviceModel->where('network_id', $id)->delete();
        $this->networkModel->delete($id);

        $db->transComplete();

        if ($db->transStatus() === false) {
            return redirect()->to('/networks')->with('error', 'Error al eliminar la red de la base de datos.');
        }

        return redirect()->to('/networks')->with('message', 'Red eliminada y servidor sincronizado.');
    }

    // ---------------------------------------------------------------------
    // Activar / Desactivar red
    // ---------------------------------------------------------------------
    public function toggleActive($id)
    {
        if ($this->request->getMethod() !== 'POST') {
            return redirect()->back();
        }

        $network = $this->networkModel->find($id);

        if (! $network) {
            return redirect()->to('/networks')->with('error', 'Red no encontrada.');
        }

        // Validar propiedad
        if ($network->owner_id != auth()->id() && !auth()->user()->inGroup('superadmin', 'supervisor')) {
            return redirect()->to('/networks')->with('error', 'Sin permisos.');
        }

        $network->active = !$network->active;

        if ($this->networkModel->save($network)) {
            // Sincronizar peers si el servidor está configurado
            if (WireguardService::isConfigured()) {
                try {
                    $deviceModel = new \App\Models\DeviceModel();
                    $devices     = $deviceModel->where('network_id', $id)->findAll();
                    $wg          = WireguardService::fromSettings(5);

                    if (!$network->active) {
                        // Remover peers de la red desactivada
                        $keys = array_filter(array_column($devices, 'public_key'));
                        if (!empty($keys)) {
                            $wg->removePeers($keys);
                            $wg->saveConfig();
                        }
                    } else {
                        // Restaurar los nodos activos de la BD
                        $wg->syncPeers();
                    }
                } catch (\Throwable $e) {
                    log_message('notice', 'No se pudo sincronizar WireGuard tras cambiar estado de red: ' . $e->getMessage());
                }
            }

            $status = $network->active ? 'activada' : 'desactivada';
            return redirect()->to('/networks')->with('message', "Red {$status}.");
        }

        return redirect()->to('/networks')->with('error', 'Error al cambiar estado.');
    }

    // ---------------------------------------------------------------------
    // Helper para verificar solapamiento CIDR
    // ---------------------------------------------------------------------
    protected function checkCidrOverlap($cidr1, $cidr2)
    {
        $parts1 = explode('/', $cidr1);
        $parts2 = explode('/', $cidr2);
        
        // Si el formato no es válido o no tiene máscara, no procesar solapamiento
        if (count($parts1) !== 2 || count($parts2) !== 2) return false;

        $ip1 = ip2long(trim($parts1[0]));
        $ip2 = ip2long(trim($parts2[0]));
        $mask1 = (int)trim($parts1[1]);
        $mask2 = (int)trim($parts2[1]);

        if ($ip1 === false || $ip2 === false) return false;

        $min_mask = min($mask1, $mask2);
        $shift = 32 - $min_mask;

        if ($shift == 32) return true;

        $mask = ~((1 << $shift) - 1);
        
        return ($ip1 & $mask) === ($ip2 & $mask);
    }

    /**
     * Verifica que una dirección IP sea IPv4 válida y pertenezca a rangos privados (RFC 1918) o CGNAT (RFC 6598).
     */
    protected function isValidPrivateNetworkIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }

        $ipLong = ip2long($ip);
        if ($ipLong === false) {
            return false;
        }

        $privateRanges = [
            ['10.0.0.0', '10.255.255.255'],
            ['172.16.0.0', '172.31.255.255'],
            ['192.168.0.0', '192.168.255.255'],
            ['100.64.0.0', '100.127.255.255'],
        ];

        foreach ($privateRanges as [$start, $end]) {
            if ($ipLong >= ip2long($start) && $ipLong <= ip2long($end)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Valida y normaliza una lista de servidores DNS separados por comas.
     * Devuelve null si algún elemento contiene caracteres inválidos o no es IP/dominio.
     */
    protected function validateAndNormalizeDns(string $dns): ?string
    {
        if (preg_match('/[\r\n\0]/', $dns)) {
            return null;
        }

        $entries = array_filter(array_map('trim', explode(',', $dns)), static fn ($v) => $v !== '');
        if (empty($entries)) {
            return '';
        }

        $clean = [];
        foreach ($entries as $entry) {
            if (filter_var($entry, FILTER_VALIDATE_IP) !== false) {
                $clean[] = $entry;
            } elseif (preg_match('/^(?!-)[A-Za-z0-9-]{1,63}(?<!-)(\.(?!-)[A-Za-z0-9-]{1,63}(?<!-))+$/', $entry)) {
                $clean[] = $entry;
            } else {
                return null;
            }
        }

        return implode(', ', $clean);
    }
}
