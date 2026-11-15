<?php

namespace App\Controllers;

use App\Libraries\NebulaService;
use App\Models\DeviceModel;
use App\Models\NetworkModel;

class DashboardController extends BaseController
{
    public function index()
    {
        $user         = auth()->user();
        $networkModel = new NetworkModel();
        $deviceModel  = new DeviceModel();

        $data = [
            'title' => 'Dashboard',
        ];

        // Estado base de Nebula P2P
        $telemetry = [
            'isConfigured'  => NebulaService::isConfigured(),
            'isInterfaceUp' => NebulaService::isConfigured(),
        ];

        if ($user->inGroup('superadmin', 'supervisor')) {
            $userModel             = auth()->getProvider();
            $allNetworks           = $networkModel->findAll();
            $devices               = $deviceModel->findAll();
            $data['totalUsers']    = $userModel->countAllResults();
            $data['totalNetworks'] = count($allNetworks);
            $data['totalDevices']  = count($devices);

            // Últimas 5 redes registradas con conteo de nodos
            $data['recentNetworks'] = $networkModel->select('networks.*, users.username as owner_username, COUNT(devices.id) as device_count')
                                                  ->join('users', 'users.id = networks.owner_id', 'left')
                                                  ->join('devices', 'devices.network_id = networks.id', 'left')
                                                  ->groupBy('networks.id')
                                                  ->orderBy('networks.created_at', 'DESC')
                                                  ->limit(5)
                                                  ->find();
        } else {
            // Usuario estándar
            $allUserNetworks       = $networkModel->where('owner_id', $user->id)->findAll();
            $data['networksCount'] = count($allUserNetworks);
            $data['networks']      = $networkModel->select('networks.*, users.username as owner_username, COUNT(devices.id) as device_count')
                                                 ->join('users', 'users.id = networks.owner_id', 'left')
                                                 ->join('devices', 'devices.network_id = networks.id', 'left')
                                                 ->where('networks.owner_id', $user->id)
                                                 ->groupBy('networks.id')
                                                 ->orderBy('networks.created_at', 'DESC')
                                                 ->limit(5)
                                                 ->find();

            $networkIds           = array_column($allUserNetworks, 'id');
            $devices              = empty($networkIds) ? [] : $deviceModel->whereIn('network_id', $networkIds)->findAll();
            $data['devicesCount'] = count($devices);
        }

        $data['telemetry'] = $telemetry;

        echo view('template/header', $data);

        if ($user->inGroup('superadmin', 'supervisor')) {
            echo view('dashboards/admin', $data);
        } else {
            echo view('dashboards/user', $data);
        }

        echo view('template/footer');
    }
}
