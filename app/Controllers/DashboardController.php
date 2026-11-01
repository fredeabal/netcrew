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

        // Estructura base de telemetría de red
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
     * Procesa la telemetría agregada por Red VPN y el tráfico real de WireGuard.
     * Genera una métrica global orientativa que no satura aunque existan cientos de redes.
     */
    protected function buildTelemetryData(array $devices, array $networks, array $telemetry): array
    {
        $telemetry['totalDevices'] = count($devices);

        if (! $telemetry['isConfigured'] || empty($devices)) {
            $telemetry['offlineDevices'] = $telemetry['totalDevices'];
            return $telemetry;
        }

        try {
            // Obtener estado de peers (cacheado por 5s para evitar múltiples sesiones SSH)
            $dump  = WireguardService::cachedDump(5, 3);
            $peers = $dump['peers'] ?? [];
            $telemetry['isInterfaceUp'] = true;

            $onlineCount = 0;

            // Inicializar mapa acumulador por cada red
            $netTrafficMap = [];
            foreach ($networks as $net) {
                $netTrafficMap[$net->id] = [
                    'id'    => $net->id,
                    'name'  => $net->name,
                    'rx'    => 0,
                    'tx'    => 0,
                    'total' => 0,
                ];
            }

            foreach ($devices as $dev) {
                $peer = $peers[$dev->public_key] ?? null;
                // En WireGuard (servidor):
                // tx_bytes = bytes transmitidos hacia el nodo -> Descarga (Download / RX cliente)
                // rx_bytes = bytes recibidos desde el nodo   -> Subida (Upload / TX cliente)
                $download = (int) ($peer['tx_bytes'] ?? 0);
                $upload   = (int) ($peer['rx_bytes'] ?? 0);
                $isConnected = ((int) $dev->active === 1) && ! empty($peer['connected']);

                if ($isConnected) {
                    $onlineCount++;
                }

                $telemetry['totalRxBytes'] += $download; // Total Descarga
                $telemetry['totalTxBytes'] += $upload;   // Total Subida

                if (isset($netTrafficMap[$dev->network_id])) {
                    $netTrafficMap[$dev->network_id]['rx']    += $download;
                    $netTrafficMap[$dev->network_id]['tx']    += $upload;
                    $netTrafficMap[$dev->network_id]['total'] += ($download + $upload);
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

            // Curva gráfica global de tráfico total acumulado (sin etiquetas ni nombres en eje X)
            $points = [];
            $hasAnyTraffic = false;

            foreach ($netTrafficMap as $net) {
                if ($net['rx'] > 0 || $net['tx'] > 0) {
                    $hasAnyTraffic = true;
                    $points[] = ['rx' => $net['rx'], 'tx' => $net['tx'], 'total' => $net['total']];
                }
            }

            // Si hay una sola red con tráfico pero varios nodos, usamos los nodos para una curva fluida
            if (count($points) <= 1) {
                $devicePoints = [];
                foreach ($devices as $dev) {
                    $peer = $peers[$dev->public_key] ?? null;
                    $dDownload = (int) ($peer['tx_bytes'] ?? 0);
                    $dUpload   = (int) ($peer['rx_bytes'] ?? 0);
                    if ($dDownload > 0 || $dUpload > 0) {
                        $hasAnyTraffic = true;
                        $devicePoints[] = ['rx' => $dDownload, 'tx' => $dUpload, 'total' => $dDownload + $dUpload];
                    }
                }
                if (count($devicePoints) > count($points)) {
                    $points = $devicePoints;
                }
            }

            // Ordenar descendente para que la curva fluya estéticamente
            usort($points, fn ($a, $b) => $b['total'] <=> $a['total']);

            if (! empty($points)) {
                foreach ($points as $p) {
                    $telemetry['trafficRx'][] = $p['rx'];
                    $telemetry['trafficTx'][] = $p['tx'];
                }
                // Si hay un único punto, generamos un decaimiento suave para que forme la onda
                if (count($points) === 1) {
                    $baseRx = $points[0]['rx'];
                    $baseTx = $points[0]['tx'];
                    $telemetry['trafficRx'] = [(int) $baseRx, (int) round($baseRx * 0.65), (int) round($baseRx * 0.25), 0];
                    $telemetry['trafficTx'] = [(int) $baseTx, (int) round($baseTx * 0.75), (int) round($baseTx * 0.35), 0];
                } else {
                    // Anclaje final en 0 para que la curva descienda suavemente a la base
                    $telemetry['trafficRx'][] = 0;
                    $telemetry['trafficTx'][] = 0;
                }
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
