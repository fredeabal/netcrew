<?php

namespace App\Controllers;

use App\Libraries\NebulaService;
use App\Models\AuditLogModel;
use App\Models\CompanyModel;
use App\Models\NetworkModel;
use App\Models\UserModel;

class NetworkController extends BaseController
{
    protected $networkModel;
    protected $userModel;
    protected $companyModel;

    public function __construct()
    {
        $this->networkModel = new NetworkModel();
        $this->userModel    = new UserModel();
        $this->companyModel = new CompanyModel();
    }

    // ---------------------------------------------------------------------
    // Mostrar listado de redes
    // ---------------------------------------------------------------------
    public function index()
    {
        $search         = $this->request->getGet('q');
        $isSuperAdmin   = $this->isSuperAdmin();
        $isCompanyAdmin = $this->isCompanyAdmin();

        $rawCompanyId = $this->request->getGet('company_id');
        $selectedCid  = ($isSuperAdmin && $rawCompanyId !== null && $rawCompanyId !== '' && ctype_digit((string) $rawCompanyId))
            ? (int) $rawCompanyId
            : null;
        $companyId    = $isSuperAdmin ? $selectedCid : $this->currentCompanyId();

        $data = [
            'title'             => 'Redes',
            'networks'          => $this->networkModel->getNetworksForUser((int) auth()->id(), $isSuperAdmin, $search, 100, $companyId, $isCompanyAdmin),
            'pager'             => $this->networkModel->pager,
            'search'            => $search,
            'isSuperAdmin'      => $isSuperAdmin,
            'companies'         => $isSuperAdmin ? $this->companyModel->orderBy('name', 'ASC')->findAll() : [],
            'selectedCompanyId' => $selectedCid,
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
        $isSuperAdmin   = $this->isSuperAdmin();
        $isCompanyAdmin = $this->isCompanyAdmin();
        $users          = null;

        if ($isSuperAdmin) {
            $users = $this->getActiveUsers();
        } elseif ($isCompanyAdmin) {
            $users = $this->getActiveUsers($this->currentCompanyId());
        }

        $data = [
            'title'         => 'Crear Red',
            'users'         => $users,
            'companies'     => $isSuperAdmin ? $this->companyModel->where('active', 1)->orderBy('name', 'ASC')->findAll() : [],
            'isSuperAdmin'  => $isSuperAdmin,
            'suggestedCidr' => $this->networkModel->suggestNextCidr(),
        ];

        echo view('template/header', $data);
        echo view('networks/create');
        echo view('template/footer');
    }

    // ---------------------------------------------------------------------
    // Helper para obtener usuarios activos no suspendidos (opcionalmente por empresa)
    // ---------------------------------------------------------------------
    protected function getActiveUsers(?int $companyId = null): array
    {
        $builder = $this->userModel->where('active', 1)
            ->groupStart()
            ->where('status IS NULL', null, false)
            ->orWhere('status !=', 'banned')
            ->groupEnd();

        if ($companyId !== null) {
            $builder->where('company_id', $companyId);
        }

        return $builder->orderBy('username', 'ASC')->findAll();
    }

    // ---------------------------------------------------------------------
    // Guardar nueva red
    // ---------------------------------------------------------------------
    public function store()
    {
        $data = $this->request->getPost(['name', 'cidr', 'dns', 'owner_id', 'company_id']);

        $user           = auth()->user();
        $isSuperAdmin   = $this->isSuperAdmin();
        $isCompanyAdmin = $this->isCompanyAdmin();

        // Si no es superadmin ni supervisor, forzar que el owner sea él mismo
        if (! $isSuperAdmin && ! $isCompanyAdmin) {
            $data['owner_id'] = (int) $user->id;
        } elseif (empty($data['owner_id'])) {
            $data['owner_id'] = (int) $user->id;
        }

        // Resolver y validar empresa activa
        if ($isSuperAdmin) {
            $companyId = (! empty($data['company_id']) && ctype_digit((string) $data['company_id']))
                ? (int) $data['company_id']
                : 0;
            if ($companyId <= 0 && ! empty($data['owner_id'])) {
                $ownerCandidate = $this->userModel->findById((int) $data['owner_id']);
                if ($ownerCandidate && ! empty($ownerCandidate->company_id)) {
                    $companyId = (int) $ownerCandidate->company_id;
                }
            }
            $company = $companyId > 0 ? $this->companyModel->find($companyId) : null;
            if (! $company || empty($company->active)) {
                return redirect()->back()->withInput()->with('error', 'Debes seleccionar una empresa válida y activa.');
            }
        } else {
            $companyId = $this->currentCompanyId();
            $company   = $companyId !== null ? $this->companyModel->find($companyId) : null;
            if (! $company || empty($company->active)) {
                return redirect()->back()->withInput()->with('error', 'Tu empresa no está asignada o se encuentra inactiva.');
            }
        }

        // Validar que el owner exista y pertenezca a la empresa
        $owner = ! empty($data['owner_id']) ? $this->userModel->findById((int) $data['owner_id']) : null;
        if (! $owner) {
            return redirect()->back()->withInput()->with('error', 'El propietario seleccionado no existe.');
        }

        $ownerAllowed = $isSuperAdmin
            ? ($owner->inGroup('superadmin') || (int) ($owner->company_id ?? 0) === $companyId)
            : (! $owner->inGroup('superadmin') && (int) ($owner->company_id ?? 0) === $companyId);
        if (! $ownerAllowed) {
            return redirect()->back()->withInput()->with('error', 'El propietario seleccionado no pertenece a la empresa de la red.');
        }

        $data['owner_id']   = (int) $owner->id;
        $data['company_id'] = $companyId;

        // Validar cuota máxima de redes de la empresa
        if ((int) ($company->max_networks ?? 0) > 0) {
            $existingNetworksCount = $this->networkModel->where('company_id', $companyId)->countAllResults();
            if ($existingNetworksCount >= (int) $company->max_networks) {
                return redirect()->back()->withInput()->with('error', "La empresa ha alcanzado el límite máximo de redes ({$company->max_networks}).");
            }
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
        $restricted = $settings->get('Nebula.restrictedCidrs');
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

        $data['engine'] = 'nebula';

        try {
            $ca = NebulaService::getOrCreateGlobalCa();
            $data['nebula_ca_crt'] = $ca['crt'];
            $data['nebula_ca_key'] = null;
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', 'No se pudo obtener la CA global de Nebula: ' . $e->getMessage());
        }

        if ($this->networkModel->save($data)) {
            NebulaService::syncGlobalLighthouse();
            AuditLogModel::log('NETWORK_CREATE', "Creó la red '{$data['name']}' ({$data['cidr']})", null, null, $companyId);
            return redirect()->to('/networks')->with('message', 'Red Nebula P2P creada.');
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

        if (! $this->canAccessNetwork($network)) {
            return redirect()->to('/networks')->with('error', 'No tienes permisos para editar esta red.');
        }

        $isSuperAdmin   = $this->isSuperAdmin();
        $isCompanyAdmin = $this->isCompanyAdmin();
        $users          = $isSuperAdmin
            ? $this->getActiveUsers()
            : ($isCompanyAdmin ? $this->getActiveUsers($this->currentCompanyId()) : null);

        $data = [
            'title'        => 'Editar Red',
            'network'      => $network,
            'users'        => $users,
            'isSuperAdmin' => $isSuperAdmin,
            'companies'    => $isSuperAdmin ? $this->companyModel->where('active', 1)->orderBy('name', 'ASC')->findAll() : [],
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

        if (! $this->canAccessNetwork($network)) {
            return redirect()->to('/networks')->with('error', 'No tienes permisos.');
        }

        $data = $this->request->getPost(['name', 'cidr', 'dns', 'owner_id', 'company_id', 'active']);

        // Añadir el ID para que la regla de validación is_unique pueda ignorar la red actual
        $data['id'] = $id;

        $isSuperAdmin      = $this->isSuperAdmin();
        $isCompanyAdmin    = $this->isCompanyAdmin();
        $originalCompanyId = (int) ($network->company_id ?? 0);

        if ($isSuperAdmin) {
            $companyId = (! empty($data['company_id']) && ctype_digit((string) $data['company_id']))
                ? (int) $data['company_id']
                : $originalCompanyId;
            $company = $companyId > 0 ? $this->companyModel->find($companyId) : null;
            if (! $company || empty($company->active)) {
                return redirect()->back()->withInput()->with('error', 'Debes seleccionar una empresa válida y activa.');
            }
            if ($companyId !== $originalCompanyId && (int) ($company->max_networks ?? 0) > 0) {
                $existingNetworksCount = $this->networkModel->where('company_id', $companyId)->countAllResults();
                if ($existingNetworksCount >= (int) $company->max_networks) {
                    return redirect()->back()->withInput()->with('error', "La empresa ha alcanzado el límite máximo de redes ({$company->max_networks}).");
                }
            }
            $data['company_id'] = $companyId;
        } else {
            $companyId          = $originalCompanyId;
            $data['company_id'] = $companyId > 0 ? $companyId : null;
        }

        // Resolver y validar propietario dentro de la empresa
        if ($isSuperAdmin || $isCompanyAdmin) {
            if (empty($data['owner_id'])) {
                $data['owner_id'] = $network->owner_id;
            }
            $owner = $this->userModel->findById((int) $data['owner_id']);
            if (! $owner) {
                return redirect()->back()->withInput()->with('error', 'El propietario seleccionado no existe.');
            }
            $ownerAllowed = $isSuperAdmin
                ? ($owner->inGroup('superadmin') || (int) ($owner->company_id ?? 0) === $companyId)
                : (! $owner->inGroup('superadmin') && (int) ($owner->company_id ?? 0) === $companyId);
            if (! $ownerAllowed) {
                return redirect()->back()->withInput()->with('error', 'El propietario seleccionado no pertenece a la empresa de la red.');
            }
            $data['owner_id'] = (int) $owner->id;
        } else {
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
        $restricted = $settings->get('Nebula.restrictedCidrs');
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

        if ($this->networkModel->update($id, $data)) {
            AuditLogModel::log('NETWORK_UPDATE', "Actualizó la red '{$data['name']}' ({$data['cidr']})", null, null, $companyId ?: null);
            return redirect()->to('/networks')->with('message', 'Red actualizada.');
        }

        return redirect()->back()->withInput()->with('errors', $this->networkModel->errors());
    }

    // ---------------------------------------------------------------------
    // Eliminar red
    // ---------------------------------------------------------------------
    public function delete($id)
    {
        $network = $this->networkModel->find($id);

        if (! $network) {
            return redirect()->to('/networks')->with('error', 'Red no encontrada.');
        }

        if (! $this->canAccessNetwork($network)) {
            return redirect()->to('/networks')->with('error', 'Sin permisos.');
        }

        $companyId = ! empty($network->company_id) ? (int) $network->company_id : null;

        // Transacción de base de datos para eliminar dispositivos y la red
        $db = \Config\Database::connect();
        $db->transStart();

        $deviceModel = new \App\Models\DeviceModel();
        $deviceModel->where('network_id', $id)->delete();
        $this->networkModel->delete($id);

        $db->transComplete();

        if ($db->transStatus() === false) {
            return redirect()->to('/networks')->with('error', 'Error al eliminar la red de la base de datos.');
        }

        service('settings')->forget('Nebula.blocklist_' . (int) $id);
        if (NebulaService::getGlobalCa() !== null) {
            NebulaService::syncGlobalLighthouse();
        }
        AuditLogModel::log('NETWORK_DELETE', "Eliminó la red '{$network->name}' ({$network->cidr})", null, null, $companyId);

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

        if (! $this->canAccessNetwork($network)) {
            return redirect()->to('/networks')->with('error', 'Sin permisos.');
        }

        $newActive = !$network->active;

        if ($this->networkModel->update($id, ['active' => $newActive ? 1 : 0])) {
            $status    = $newActive ? 'activada' : 'desactivada';
            $companyId = ! empty($network->company_id) ? (int) $network->company_id : null;
            AuditLogModel::log('NETWORK_TOGGLE_ACTIVE', "Cambió estado a {$status} para la red '{$network->name}' (ID: {$id})", null, null, $companyId);
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

    // ---------------------------------------------------------------------
    // Descargar configuración del servidor Lighthouse para redes Nebula
    // ---------------------------------------------------------------------
    public function downloadLighthouseConfig($id)
    {
        $network = $this->networkModel->find($id);
        if (!$network) {
            return redirect()->to('/networks')->with('error', 'Red no encontrada.');
        }

        if (! $this->canAccessNetwork($network)) {
            return redirect()->to('/networks')->with('error', 'No tienes permisos para acceder a esta red.');
        }

        try {
            if (NebulaService::usesGlobalCa($network)) {
                $globalCa = NebulaService::getGlobalCa();
                if ($globalCa === null) {
                    return redirect()->to('/networks')->with('error', 'La CA global de Nebula no está configurada.');
                }
                $lighthouseIp = NebulaService::getGlobalLighthouseVpnIp() . '/8';
                $cert         = NebulaService::signDeviceCert(
                    $globalCa['crt'],
                    $globalCa['key'],
                    'netcrew-lighthouse-global',
                    $lighthouseIp,
                    'lighthouse'
                );
                $lhNetwork                = clone $network;
                $lhNetwork->nebula_ca_crt = $globalCa['crt'];
                $yaml                     = NebulaService::buildLighthouseConfig(
                    $lhNetwork,
                    $cert['crt'],
                    $cert['key'],
                    NebulaService::getAllBlocklists()
                );
            } else {
                if (empty($network->nebula_ca_crt)) {
                    return redirect()->to('/networks')->with('error', 'Esta red no tiene una CA de Nebula configurada.');
                }

                $caKey = $this->networkModel->decryptCaKey((string) $network->nebula_ca_key);
                if ($caKey === '') {
                    return redirect()->to('/networks')->with('error', 'No se pudo descifrar la CA de Nebula de esta red.');
                }

                $lighthouseIp = NebulaService::getLighthouseVpnIp($network->cidr) . '/24';
                $cert         = NebulaService::signDeviceCert(
                    $network->nebula_ca_crt,
                    $caKey,
                    'lighthouse-' . $network->id,
                    $lighthouseIp,
                    'lighthouse,devices'
                );
                $yaml = NebulaService::buildLighthouseConfig(
                    $network,
                    $cert['crt'],
                    $cert['key'],
                    NebulaService::getBlocklist((int) $network->id)
                );
            }
        } catch (\Throwable $e) {
            return redirect()->to('/networks')->with('error', 'Error generando configuración Lighthouse: ' . $e->getMessage());
        }

        $companyId = ! empty($network->company_id) ? (int) $network->company_id : null;
        AuditLogModel::log('NETWORK_LIGHTHOUSE_DOWNLOAD', "Descargó configuración Lighthouse de la red '{$network->name}' (ID: {$id})", null, null, $companyId);

        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $network->name);
        return $this->response->setHeader('Content-Type', 'application/x-yaml; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="lighthouse-' . $safeName . '.yml"')
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->setHeader('Pragma', 'no-cache')
            ->setBody($yaml);
    }
}
