<?php

namespace App\Controllers;

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

        // Validación adicional para asegurar formato IP
        if (!preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\/24$/', $cidr)) {
            return redirect()->back()->withInput()->with('error', 'Formato de IP no válido.');
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

        // Validación adicional para asegurar formato IP
        if (!preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\/24$/', $cidr)) {
            return redirect()->back()->withInput()->with('error', 'Formato de IP no válido.');
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

        // Capacidad /24 = 253 nodos
        $maxNodes = 253;
        $deviceModel = new \App\Models\DeviceModel();
        $currentNodesCount = $deviceModel->where('network_id', $id)->countAllResults();
        
        if ($currentNodesCount > $maxNodes) {
            return redirect()->back()->withInput()->with('error', "Capacidad excedida<br><small class='text-muted'>Esta red admite un máximo de {$maxNodes} nodos (actualmente tienes {$currentNodesCount}).</small>");
        }

        if ($this->networkModel->update($id, $data)) {
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

        // Limpiar WireGuard en el servidor Debian
        $this->cleanupWireguardOnServer($id);

        // Eliminar dispositivos asociados
        $deviceModel = new \App\Models\DeviceModel();
        $deviceModel->where('network_id', $id)->delete();

        if ($this->networkModel->delete($id)) {
            return redirect()->to('/networks')->with('message', 'Red eliminada y servidor sincronizado.');
        }

        return redirect()->to('/networks')->with('error', 'Error al eliminar.');
    }

    // ---------------------------------------------------------------------
    // Limpiar interfaz de WireGuard en el servidor Debian vía SSH
    // ---------------------------------------------------------------------
    protected function cleanupWireguardOnServer($networkId): bool
    {
        $globalSettings = service('settings');
        $interface = $globalSettings->get('WireGuard.interface') ?: 'wg0';

        // Obtener todos los dispositivos de la red para quitar sus peers
        $deviceModel = new \App\Models\DeviceModel();
        $devices = $deviceModel->where('network_id', $networkId)->findAll();
        
        $cmds = [];
        foreach ($devices as $device) {
            if (!empty($device->public_key)) {
                $cmds[] = "wg set " . escapeshellarg($interface) . " peer " . escapeshellarg($device->public_key) . " remove";
            }
        }
        
        $cmd1 = empty($cmds) ? "true" : implode(" ; ", $cmds);
        $cmd2 = "wg-quick save " . escapeshellarg($interface);

        // Reconstruir reglas de iptables excluyendo esta red
        $networkModel = new \App\Models\NetworkModel();
        $networks = $networkModel->where('id !=', $networkId)->findAll();
        
        $iptablesScript = "sysctl -w net.ipv4.ip_forward=1; ";
        $iptablesScript .= "while iptables -D FORWARD -i " . escapeshellarg($interface) . " -o " . escapeshellarg($interface) . " -j ACCEPT 2>/dev/null; do true; done; ";
        $iptablesScript .= "while iptables -D FORWARD -i " . escapeshellarg($interface) . " -o " . escapeshellarg($interface) . " -j DROP 2>/dev/null; do true; done; ";
        $iptablesScript .= "while iptables -D FORWARD -i " . escapeshellarg($interface) . " -o wg+ -j DROP 2>/dev/null; do true; done; ";
        
        foreach ($networks as $net) {
            $cidr = escapeshellarg($net->cidr);
            $intf = escapeshellarg($interface);
            $iptablesScript .= "while iptables -D FORWARD -i {$intf} -o {$intf} -s {$cidr} -d {$cidr} -j ACCEPT 2>/dev/null; do true; done; ";
            $iptablesScript .= "iptables -A FORWARD -i {$intf} -o {$intf} -s {$cidr} -d {$cidr} -j ACCEPT; ";
        }
        
        $intf = escapeshellarg($interface);
        $iptablesScript .= "iptables -A FORWARD -i {$intf} -o {$intf} -j DROP; ";
        $iptablesScript .= "iptables -A FORWARD -i {$intf} -o wg+ -j DROP; ";
        $iptablesScript .= "DEFAULT_DEV=\$(ip route show default | awk '{for(i=1;i<=NF;i++) if(\$i==\"dev\") print \$(i+1)}' | head -n1); ";
        // Enrutar tráfico a Internet (NAT/Masquerade y FORWARD ACCEPT)
        $iptablesScript .= "while iptables -t nat -D POSTROUTING -o \"\$DEFAULT_DEV\" -j MASQUERADE 2>/dev/null; do true; done; ";
        $iptablesScript .= "while iptables -D FORWARD -i {$intf} -o \"\$DEFAULT_DEV\" -j DROP 2>/dev/null; do true; done; ";
        $iptablesScript .= "while iptables -D FORWARD -i {$intf} -o \"\$DEFAULT_DEV\" -j ACCEPT 2>/dev/null; do true; done; ";
        $iptablesScript .= "if [ ! -z \"\$DEFAULT_DEV\" ]; then iptables -t nat -A POSTROUTING -o \"\$DEFAULT_DEV\" -j MASQUERADE; iptables -A FORWARD -i {$intf} -o \"\$DEFAULT_DEV\" -j ACCEPT; fi";

        $cmd3 = $iptablesScript;

        $cmd = $this->wrapSudoCommand("bash -c " . escapeshellarg($cmd1)) . " && " .
               $this->wrapSudoCommand($cmd2) . " && " .
               $this->wrapSudoCommand("bash -c " . escapeshellarg($cmd3));

        try {
            // Obtener sesión SSH usando BaseController
            $ssh = $this->getSshSession(5);
            $ssh->exec($cmd);
        } catch (\Exception $e) {
            log_message('error', "Excepción al limpiar WireGuard vía SSH: " . $e->getMessage());
            return false;
        }

        return true;
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
}
