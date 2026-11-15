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

        // Estructura base de telemetría de red (Nebula P2P)
        $telemetry = [
            'isConfigured'     => NebulaService::isConfigured(),
            'isInterfaceUp'    => NebulaService::isConfigured(),
            'totalRxBytes'     => 0,
            'totalTxBytes'     => 0,
            'formattedRx'      => '0 B',
            'formattedTx'      => '0 B',
            'formattedTotal'   => '0 B',
            'onlineDevices'    => 0,
            'offlineDevices'   => 0,
            'totalDevices'     => 0,
            'healthPercentage' => 0,
            'trafficLabels'    => [],
            'networkNames'     => [],
            'trafficRx'        => [],
            'trafficTx'        => [],
            'hasTrafficData'   => false,
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

            $telemetry = $this->buildTelemetryData($devices, $allNetworks, $telemetry);
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

            $telemetry = $this->buildTelemetryData($devices, $allUserNetworks, $telemetry);
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

    /**
     * Procesa la telemetría resumen de dispositivos para redes Nebula P2P.
     */
    protected function buildTelemetryData(array $devices, array $networks, array $telemetry): array
    {
        $telemetry['totalDevices'] = count($devices);
        $activeCount = 0;
        $now = time();

        foreach ($devices as $dev) {
            $expired = !empty($dev->expires_at) && strtotime($dev->expires_at) < $now;
            if ((int) $dev->active === 1 && !$expired) {
                $activeCount++;
            }
        }

        $telemetry['onlineDevices']    = $activeCount;
        $telemetry['offlineDevices']   = max(0, $telemetry['totalDevices'] - $activeCount);
        $telemetry['healthPercentage'] = $telemetry['totalDevices'] > 0
            ? (int) round(($activeCount / $telemetry['totalDevices']) * 100)
            : 0;

        return $telemetry;
    }

    /**
     * Formatea un valor en bytes a formato legible (B, KB, MB, GB, TB).
     */
    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $pow   = (int) floor(log($bytes, 1024));
        $pow   = min($pow, count($units) - 1);

        return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
    }
}
