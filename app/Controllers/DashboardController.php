<?php

namespace App\Controllers;

use App\Libraries\WireguardService;
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

        // Estructura base de telemetría global
        $telemetry = [
            'isConfigured'     => WireguardService::isConfigured(),
            'isInterfaceUp'    => false,
            'totalRxBytes'     => 0,
            'totalTxBytes'     => 0,
            'formattedRx'      => '0 B',
            'formattedTx'      => '0 B',
            'formattedTotal'   => '0 B',
            'onlineDevices'    => 0,
            'offlineDevices'   => 0,
            'totalDevices'     => 0,
            'healthPercentage' => 0,
            'networkLabels'    => [],
            'networkRx'        => [],
            'networkTx'        => [],
            'deviceTypeLabels' => ['PC / Portátil', 'Servidores', 'Móviles / Tablets', 'Routers'],
            'deviceTypeSeries' => [0, 0, 0, 0],
            'typePc'           => 0,
            'typeServer'       => 0,
            'typeMobile'       => 0,
            'typeRouter'       => 0,
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
            $allNetworks           = $networkModel->where('owner_id', $user->id)->findAll();
            $data['networksCount'] = count($allNetworks);
            $data['networks']      = $networkModel->select('networks.*, users.username as owner_username, COUNT(devices.id) as device_count')
                                                 ->join('users', 'users.id = networks.owner_id', 'left')
                                                 ->join('devices', 'devices.network_id = networks.id', 'left')
                                                 ->where('networks.owner_id', $user->id)
                                                 ->groupBy('networks.id')
                                                 ->orderBy('networks.created_at', 'DESC')
                                                 ->limit(5)
                                                 ->find();

            $networkIds           = array_column($allNetworks, 'id');
            $devices              = empty($networkIds) ? [] : $deviceModel->whereIn('network_id', $networkIds)->findAll();
            $data['devicesCount'] = count($devices);

            $telemetry = $this->buildTelemetryData($devices, $allNetworks, $telemetry);
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
     * Procesa la telemetría global por Red y distribución por tipo de dispositivo para el Dashboard.
     */
    protected function buildTelemetryData(array $devices, array $networks, array $telemetry): array
    {
        $telemetry['totalDevices'] = count($devices);

        // 1. Conteo de tipos de dispositivos (siempre disponible desde BD)
        foreach ($devices as $dev) {
            $type = strtolower((string) ($dev->device_type ?? 'pc'));
            if ($type === 'server') {
                $telemetry['typeServer']++;
            } elseif ($type === 'mobile' || $type === 'tablet') {
                $telemetry['typeMobile']++;
            } elseif ($type === 'router') {
                $telemetry['typeRouter']++;
            } else {
                $telemetry['typePc']++;
            }
        }

        $telemetry['deviceTypeSeries'] = [
            $telemetry['typePc'],
            $telemetry['typeServer'],
            $telemetry['typeMobile'],
            $telemetry['typeRouter'],
        ];

        // Inicializar acumulador de tráfico por red
        $trafficByNetwork = [];
        foreach ($networks as $net) {
            $trafficByNetwork[$net->id] = [
                'name'  => $net->name . ' (' . $net->cidr . ')',
                'rx'    => 0,
                'tx'    => 0,
                'total' => 0,
            ];
        }

        if (! $telemetry['isConfigured'] || empty($devices)) {
            $telemetry['offlineDevices'] = $telemetry['totalDevices'];
            foreach (array_slice(array_values($trafficByNetwork), 0, 8) as $netItem) {
                $telemetry['networkLabels'][] = $netItem['name'];
                $telemetry['networkRx'][]     = 0;
                $telemetry['networkTx'][]     = 0;
            }
            return $telemetry;
        }

        try {
            // Obtener estado de peers (cacheado por 5s para evitar múltiples sesiones SSH)
            $dump  = WireguardService::cachedDump(5, 3);
            $peers = $dump['peers'] ?? [];
            $telemetry['isInterfaceUp'] = true;

            $onlineCount = 0;

            foreach ($devices as $dev) {
                $peer        = $peers[$dev->public_key] ?? null;
                $rx          = (int) ($peer['rx_bytes'] ?? 0);
                $tx          = (int) ($peer['tx_bytes'] ?? 0);
                $isConnected = ((int) $dev->active === 1) && ! empty($peer['connected']);

                if ($isConnected) {
                    $onlineCount++;
                }

                $telemetry['totalRxBytes'] += $rx;
                $telemetry['totalTxBytes'] += $tx;

                if (isset($trafficByNetwork[$dev->network_id])) {
                    $trafficByNetwork[$dev->network_id]['rx']    += $rx;
                    $trafficByNetwork[$dev->network_id]['tx']    += $tx;
                    $trafficByNetwork[$dev->network_id]['total'] += ($rx + $tx);
                }
            }

            $telemetry['onlineDevices']    = $onlineCount;
            $telemetry['offlineDevices']   = max(0, $telemetry['totalDevices'] - $onlineCount);
            $telemetry['healthPercentage'] = $telemetry['totalDevices'] > 0
                ? (int) round(($onlineCount / $telemetry['totalDevices']) * 100)
                : 0;

            $telemetry['formattedRx']    = self::formatBytes($telemetry['totalRxBytes']);
            $telemetry['formattedTx']    = self::formatBytes($telemetry['totalTxBytes']);
            $telemetry['formattedTotal'] = self::formatBytes($telemetry['totalRxBytes'] + $telemetry['totalTxBytes']);

            // Ordenar redes por volumen de tráfico total descendente
            $networkList = array_values($trafficByNetwork);
            usort($networkList, fn ($a, $b) => $b['total'] <=> $a['total']);

            // Tomar hasta 8 redes principales para la comparativa
            $topNetworks   = array_slice($networkList, 0, 8);
            $hasAnyTraffic = false;

            foreach ($topNetworks as $item) {
                if ($item['rx'] > 0 || $item['tx'] > 0) {
                    $hasAnyTraffic = true;
                }
                $telemetry['networkLabels'][] = $item['name'];
                $telemetry['networkRx'][]     = $item['rx'];
                $telemetry['networkTx'][]     = $item['tx'];
            }

            $telemetry['hasTrafficData'] = $hasAnyTraffic;

        } catch (\Throwable $e) {
            log_message('notice', 'Dashboard telemetry notice: ' . $e->getMessage());
            $telemetry['offlineDevices'] = $telemetry['totalDevices'];
        }

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
