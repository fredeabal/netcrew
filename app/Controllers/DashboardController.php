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
            'trafficRx'        => [],
            'trafficTx'        => [],
            'hasTrafficData'   => false,
        ];

        if ($user->inGroup('superadmin', 'supervisor')) {
            $userModel             = auth()->getProvider();
            $devices               = $deviceModel->findAll();
            $data['totalUsers']    = $userModel->countAllResults();
            $data['totalNetworks'] = $networkModel->countAllResults();
            $data['totalDevices']  = count($devices);

            // Últimas 5 redes registradas
            $data['recentNetworks'] = $networkModel->select('networks.*, users.username as owner_username')
                                                  ->join('users', 'users.id = networks.owner_id', 'left')
                                                  ->orderBy('networks.created_at', 'DESC')
                                                  ->limit(5)
                                                  ->find();

            $telemetry = $this->buildTelemetryData($devices, $telemetry);
        } else {
            // Usuario estándar
            $data['networks'] = $networkModel->where('owner_id', $user->id)
                                             ->orderBy('created_at', 'DESC')
                                             ->find();
            $data['networksCount'] = count($data['networks']);

            $networkIds           = array_column($data['networks'], 'id');
            $devices              = empty($networkIds) ? [] : $deviceModel->whereIn('network_id', $networkIds)->findAll();
            $data['devicesCount'] = count($devices);

            $telemetry = $this->buildTelemetryData($devices, $telemetry);
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
     * Procesa la telemetría y el tráfico real de WireGuard para los dispositivos dados.
     * Si el servidor no está configurado o no responde, devuelve valores por defecto de forma segura.
     */
    protected function buildTelemetryData(array $devices, array $telemetry): array
    {
        $telemetry['totalDevices'] = count($devices);

        if (! $telemetry['isConfigured'] || empty($devices)) {
            $telemetry['offlineDevices'] = $telemetry['totalDevices'];
            return $telemetry;
        }

        try {
            // Conexión SSH rápida con timeout de 3 segundos
            $wg = WireguardService::fromSettings(3);
            $telemetry['isInterfaceUp'] = $wg->isInterfaceUp();

            $dump  = $wg->dump();
            $peers = $dump['peers'] ?? [];

            $onlineCount     = 0;
            $trafficByDevice = [];

            foreach ($devices as $dev) {
                $peer        = $peers[$dev->public_key] ?? null;
                $rx          = (int) ($peer['rx_bytes'] ?? 0);
                $tx          = (int) ($peer['tx_bytes'] ?? 0);
                $isConnected = ! empty($peer['connected']);

                if ($isConnected) {
                    $onlineCount++;
                }

                $telemetry['totalRxBytes'] += $rx;
                $telemetry['totalTxBytes'] += $tx;

                $trafficByDevice[] = [
                    'name'  => $dev->name . ' (' . $dev->ip_address . ')',
                    'rx'    => $rx,
                    'tx'    => $tx,
                    'total' => $rx + $tx,
                ];
            }

            $telemetry['onlineDevices']    = $onlineCount;
            $telemetry['offlineDevices']   = max(0, $telemetry['totalDevices'] - $onlineCount);
            $telemetry['healthPercentage'] = $telemetry['totalDevices'] > 0
                ? (int) round(($onlineCount / $telemetry['totalDevices']) * 100)
                : 0;

            $telemetry['formattedRx']    = self::formatBytes($telemetry['totalRxBytes']);
            $telemetry['formattedTx']    = self::formatBytes($telemetry['totalTxBytes']);
            $telemetry['formattedTotal'] = self::formatBytes($telemetry['totalRxBytes'] + $telemetry['totalTxBytes']);

            // Ordenar por volumen de tráfico total descendente
            usort($trafficByDevice, fn ($a, $b) => $b['total'] <=> $a['total']);

            // Tomar los 8 dispositivos con más tráfico para la gráfica
            $topDevices    = array_slice($trafficByDevice, 0, 8);
            $hasAnyTraffic = false;

            foreach ($topDevices as $item) {
                if ($item['rx'] > 0 || $item['tx'] > 0) {
                    $hasAnyTraffic = true;
                }
                $telemetry['trafficLabels'][] = $item['name'];
                $telemetry['trafficRx'][]     = $item['rx'];
                $telemetry['trafficTx'][]     = $item['tx'];
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
