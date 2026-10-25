<!-- =====================================================================
     CABECERA Y BREADCRUMB (NAVEGACIÓN)
     ===================================================================== -->
<div class="card border shadow-none position-relative overflow-hidden mb-4">
  <div class="card-body px-4 py-3">
    <div class="row align-items-center">
      <div class="col-9">
        <h4 class="fw-semibold mb-8">Panel de Usuario</h4>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item">
              <a class="text-muted text-decoration-none" href="<?= base_url() ?>">Inicio</a>
            </li>
            <li class="breadcrumb-item" aria-current="page">Dashboard</li>
          </ol>
        </nav>
      </div>
      <div class="col-3 text-end">
        <span class="badge <?= ($telemetry['isInterfaceUp'] ?? false) ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' ?> px-3 py-2 fs-2 fw-semibold">
          <i class="ti <?= ($telemetry['isInterfaceUp'] ?? false) ? 'ti-check' : 'ti-alert-circle' ?> me-1"></i>
          WireGuard <?= ($telemetry['isInterfaceUp'] ?? false) ? 'Online' : 'Inactivo' ?>
        </span>
      </div>
    </div>
  </div>
</div>

<?php
// Contar cuántas redes del usuario están activas
$activeNetworksCount = 0;
if (!empty($networks)) {
    foreach ($networks as $net) {
        if ($net->active) $activeNetworksCount++;
    }
}
?>

<!-- =====================================================================
     TARJETAS DE ESTADÍSTICAS (MÉTRICAS DEL USUARIO)
     ===================================================================== -->
<div class="row">
    <!-- Card Mis Redes -->
    <div class="col-md-4 mb-4">
        <div class="card border shadow-none h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="card-subtitle text-muted mb-0">Mis Redes</h6>
                    <div class="bg-primary-subtle text-primary rounded p-2">
                        <i class="ti ti-network fs-6"></i>
                    </div>
                </div>
                <h3 class="card-title mb-0 fw-semibold"><?= esc($networksCount ?? 0) ?></h3>
                <p class="card-text text-muted fs-2 mt-2">Redes VPN totales creadas.</p>
            </div>
        </div>
    </div>

    <!-- Card Redes Activas -->
    <div class="col-md-4 mb-4">
        <div class="card border shadow-none h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="card-subtitle text-muted mb-0">Redes Activas</h6>
                    <div class="bg-success-subtle text-success rounded p-2">
                        <i class="ti ti-activity fs-6"></i>
                    </div>
                </div>
                <h3 class="card-title mb-0 fw-semibold"><?= esc($activeNetworksCount) ?></h3>
                <p class="card-text text-muted fs-2 mt-2">Redes actualmente operativas.</p>
            </div>
        </div>
    </div>

    <!-- Card Dispositivos -->
    <div class="col-md-4 mb-4">
        <div class="card border shadow-none h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="card-subtitle text-muted mb-0">Mis Dispositivos</h6>
                    <div class="bg-info-subtle text-info rounded p-2">
                        <i class="ti ti-devices fs-6"></i>
                    </div>
                </div>
                <h3 class="card-title mb-0 fw-semibold"><?= esc($devicesCount ?? 0) ?></h3>
                <p class="card-text text-muted fs-2 mt-2">Nodos en tus redes autorizadas.</p>
            </div>
        </div>
    </div>
</div>

<!-- =====================================================================
     TELEMETRÍA Y TRÁFICO WIREGUARD (APEXCHARTS)
     ===================================================================== -->
<div class="row">
    <!-- Gráfica de Tráfico Transferido (RX / TX) -->
    <div class="col-xl-8 col-lg-7 mb-4">
        <div class="card border shadow-none h-100">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
                    <div>
                        <h5 class="card-title fw-semibold mb-1">Tráfico de Mis Dispositivos</h5>
                        <p class="card-subtitle text-muted fs-2 mb-0">Consumo de subida y bajada por nodo</p>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-success-subtle text-success fs-2 px-2 py-1">
                            <i class="ti ti-arrow-down me-1"></i> RX: <?= esc($telemetry['formattedRx'] ?? '0 B') ?>
                        </span>
                        <span class="badge bg-warning-subtle text-warning fs-2 px-2 py-1">
                            <i class="ti ti-arrow-up me-1"></i> TX: <?= esc($telemetry['formattedTx'] ?? '0 B') ?>
                        </span>
                    </div>
                </div>

                <?php if (empty($telemetry['trafficLabels'])): ?>
                    <div class="text-center py-5">
                        <div class="bg-light-subtle rounded-circle d-inline-flex p-3 mb-3 text-muted">
                            <i class="ti ti-arrows-transfer-down fs-8"></i>
                        </div>
                        <h6 class="fw-semibold mb-1">Sin tráfico registrado</h6>
                        <p class="text-muted fs-2 mb-0">Tus nodos aún no han transmitido datos o no hay dispositivos dados de alta.</p>
                    </div>
                <?php else: ?>
                    <div id="chart-traffic-user" style="min-height: 330px;"></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Indicador Radial de Conectividad / Salud de Red -->
    <div class="col-xl-4 col-lg-5 mb-4">
        <div class="card border shadow-none h-100">
            <div class="card-body d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h5 class="card-title fw-semibold mb-0">Estado de Nodos</h5>
                        <span class="badge bg-light text-muted fs-2 border">En Vivo</span>
                    </div>
                    <p class="card-subtitle text-muted fs-2 mb-3">Tus nodos conectados en tiempo real</p>
                </div>

                <div class="position-relative d-flex justify-content-center my-2">
                    <div id="chart-health-user" style="min-height: 250px;"></div>
                </div>

                <div class="border-top pt-3 mt-2">
                    <div class="row text-center g-2">
                        <div class="col-4">
                            <div class="p-2 rounded bg-light-subtle">
                                <span class="d-block text-success fw-bold fs-4">
                                    <i class="ti ti-point-filled"></i><?= esc($telemetry['onlineDevices'] ?? 0) ?>
                                </span>
                                <span class="text-muted fs-1 text-uppercase fw-semibold">Online</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 rounded bg-light-subtle">
                                <span class="d-block text-muted fw-bold fs-4">
                                    <?= esc($telemetry['offlineDevices'] ?? 0) ?>
                                </span>
                                <span class="text-muted fs-1 text-uppercase fw-semibold">Offline</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 rounded bg-light-subtle">
                                <span class="d-block text-primary fw-bold fs-4">
                                    <?= esc($telemetry['formattedTotal'] ?? '0 B') ?>
                                </span>
                                <span class="text-muted fs-1 text-uppercase fw-semibold">Total</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- =====================================================================
     MIS REDES VPN (TABLA RESUMEN)
     ===================================================================== -->
<div class="row">
    <div class="col-12 mb-4">
        <div class="card border shadow-none">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h5 class="card-title fw-semibold mb-1">Mis Redes VPN</h5>
                        <p class="card-subtitle text-muted fs-2 mb-0">Redes privadas creadas bajo tu cuenta</p>
                    </div>
                    <a href="<?= site_url('networks') ?>" class="btn btn-sm btn-outline-primary">
                        Gestionar Redes <i class="ti ti-arrow-right ms-1"></i>
                    </a>
                </div>

                <?php if (empty($networks)): ?>
                    <div class="text-center py-4">
                        <i class="ti ti-network fs-8 text-muted mb-2 d-block"></i>
                        <h6 class="fw-semibold">No tienes redes creadas</h6>
                        <p class="text-muted fs-2 mb-3">Crea tu primera red VPN para comenzar a conectar tus dispositivos.</p>
                        <a href="<?= site_url('networks') ?>" class="btn btn-primary btn-sm">
                            <i class="ti ti-plus me-1"></i> Crear Red
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle text-nowrap mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="fs-2 text-muted fw-semibold">Nombre</th>
                                    <th class="fs-2 text-muted fw-semibold">Rango CIDR</th>
                                    <th class="fs-2 text-muted fw-semibold text-center">Estado</th>
                                    <th class="fs-2 text-muted fw-semibold text-center">Fecha de Creación</th>
                                    <th class="fs-2 text-muted fw-semibold text-end">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($networks as $net): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="bg-primary-subtle text-primary rounded p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                                <i class="ti ti-network fs-5"></i>
                                            </div>
                                            <div>
                                                <h6 class="fw-semibold mb-0 fs-3"><?= esc($net->name) ?></h6>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <code class="text-dark fs-3 bg-light px-2 py-1 rounded"><?= esc($net->cidr) ?></code>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($net->active): ?>
                                            <span class="badge bg-success-subtle text-success fs-2">Activa</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger fs-2">Inactiva</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center text-muted fs-2">
                                        <?= date('d/m/Y', strtotime($net->created_at)) ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= site_url('networks/' . $net->id . '/devices') ?>" class="btn btn-sm bg-primary-subtle text-primary shadow-none">
                                            <i class="ti ti-devices me-1"></i> Ver Dispositivos
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- =====================================================================
     SCRIPTS DE APEXCHARTS
     ===================================================================== -->
<script src="<?= base_url('assets/libs/apexcharts/dist/apexcharts.min.js') ?>"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';

    // -------------------------------------------------------------
    // 1. Gráfica de Tráfico de Red (Area Spline)
    // -------------------------------------------------------------
    const trafficEl = document.querySelector("#chart-traffic-user");
    if (trafficEl) {
        const trafficCategories = <?= json_encode($telemetry['trafficLabels'] ?? []) ?>;
        const trafficRx = <?= json_encode($telemetry['trafficRx'] ?? []) ?>;
        const trafficTx = <?= json_encode($telemetry['trafficTx'] ?? []) ?>;

        const trafficOptions = {
            series: [
                { name: 'Descarga (RX)', data: trafficRx },
                { name: 'Subida (TX)', data: trafficTx }
            ],
            chart: {
                type: 'area',
                height: 330,
                toolbar: { show: false },
                fontFamily: 'inherit',
                foreColor: isDark ? '#94a3b8' : '#64748b'
            },
            colors: ['#10B981', '#F38020'],
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.45,
                    opacityTo: 0.05,
                    stops: [0, 95, 100]
                }
            },
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 2.5 },
            xaxis: {
                categories: trafficCategories,
                labels: {
                    style: { fontSize: '11px' },
                    rotate: -15,
                    trim: true
                },
                axisBorder: { show: false },
                axisTicks: { show: false }
            },
            yaxis: {
                labels: {
                    formatter: function (val) {
                        if (!val || val === 0) return '0 B';
                        const k = 1024;
                        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
                        const i = Math.floor(Math.log(val) / Math.log(k));
                        return parseFloat((val / Math.pow(k, i)).toFixed(1)) + ' ' + (sizes[i] || 'B');
                    }
                }
            },
            tooltip: {
                theme: isDark ? 'dark' : 'light',
                y: {
                    formatter: function (val) {
                        if (!val || val === 0) return '0 B';
                        const k = 1024;
                        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
                        const i = Math.floor(Math.log(val) / Math.log(k));
                        return parseFloat((val / Math.pow(k, i)).toFixed(2)) + ' ' + (sizes[i] || 'B');
                    }
                }
            },
            grid: {
                borderColor: isDark ? '#1e293b' : '#e2e8f0',
                strokeDashArray: 4
            },
            legend: {
                position: 'top',
                horizontalAlign: 'right',
                labels: { colors: isDark ? '#e2e8f0' : '#1e293b' }
            }
        };

        const trafficChart = new ApexCharts(trafficEl, trafficOptions);
        trafficChart.render();
    }

    // -------------------------------------------------------------
    // 2. Gráfica de Salud y Nodos Online (RadialBar)
    // -------------------------------------------------------------
    const healthEl = document.querySelector("#chart-health-user");
    if (healthEl) {
        const healthPercentage = <?= (int) ($telemetry['healthPercentage'] ?? 0) ?>;
        const healthColor = healthPercentage >= 70 ? '#10B981' : (healthPercentage >= 30 ? '#F38020' : '#EF4444');

        const healthOptions = {
            series: [healthPercentage],
            chart: {
                type: 'radialBar',
                height: 270,
                sparkline: { enabled: true }
            },
            plotOptions: {
                radialBar: {
                    startAngle: -125,
                    endAngle: 125,
                    hollow: {
                        margin: 0,
                        size: '72%',
                        background: 'transparent'
                    },
                    track: {
                        background: isDark ? '#1e293b' : '#f1f5f9',
                        strokeWidth: '97%'
                    },
                    dataLabels: {
                        name: {
                            show: true,
                            fontSize: '13px',
                            fontWeight: 600,
                            color: isDark ? '#94a3b8' : '#64748b',
                            offsetY: 60
                        },
                        value: {
                            offsetY: -10,
                            fontSize: '32px',
                            fontWeight: 700,
                            color: isDark ? '#ffffff' : '#0f172a',
                            formatter: function (val) {
                                return val + '%';
                            }
                        }
                    }
                }
            },
            colors: [healthColor],
            stroke: { lineCap: 'round' },
            labels: ['Nodos Online']
        };

        const healthChart = new ApexCharts(healthEl, healthOptions);
        healthChart.render();
    }
});
</script>
