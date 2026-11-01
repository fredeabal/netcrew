<!-- =====================================================================
     CABECERA Y BREADCRUMB (NAVEGACIÓN)
     ===================================================================== -->
<div class="card border shadow-none position-relative overflow-hidden mb-3 mb-md-4">
  <div class="card-body px-3 px-md-4 py-3">
    <div class="d-flex align-items-center justify-content-between gap-2">
      <div>
        <h4 class="fw-semibold mb-1">Panel de Usuario</h4>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
              <a class="text-muted text-decoration-none" href="<?= base_url() ?>">Inicio</a>
            </li>
            <li class="breadcrumb-item active text-muted" aria-current="page">Dashboard</li>
          </ol>
        </nav>
      </div>
      <div class="flex-shrink-0 text-end">
        <div class="d-inline-flex align-items-center gap-2 px-2 py-1 rounded-2 <?= ($telemetry['isInterfaceUp'] ?? false) ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' ?> fs-2 fw-semibold" title="WireGuard <?= ($telemetry['isInterfaceUp'] ?? false) ? 'Online' : 'Inactivo' ?>">
          <span class="status-indicator-dot <?= ($telemetry['isInterfaceUp'] ?? false) ? 'status-online' : 'status-offline' ?>"></span>
          <span class="d-none d-sm-inline">WireGuard <?= ($telemetry['isInterfaceUp'] ?? false) ? 'Online' : 'Inactivo' ?></span>
          <span class="d-inline d-sm-none"><?= ($telemetry['isInterfaceUp'] ?? false) ? 'Activo' : 'Off' ?></span>
        </div>
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
<div class="row g-2 g-md-3 mb-3 mb-md-4">
    <!-- Card Mis Redes -->
    <div class="col-4">
        <div class="card border shadow-none h-100 mb-0 dash-stat-card">
            <div class="card-body p-2 p-sm-3 p-md-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted fs-2 fw-semibold text-truncate">Mis Redes</span>
                    <div class="dash-stat-icon bg-primary-subtle text-primary">
                        <i class="ti ti-network"></i>
                    </div>
                </div>
                <h3 class="dash-stat-number fw-bold text-body mb-0 dash-figure"><?= esc($networksCount ?? 0) ?></h3>
            </div>
        </div>
    </div>

    <!-- Card Redes Activas -->
    <div class="col-4">
        <div class="card border shadow-none h-100 mb-0 dash-stat-card">
            <div class="card-body p-2 p-sm-3 p-md-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted fs-2 fw-semibold text-truncate">Activas</span>
                    <div class="dash-stat-icon bg-success-subtle text-success">
                        <i class="ti ti-activity"></i>
                    </div>
                </div>
                <h3 class="dash-stat-number fw-bold text-body mb-0 dash-figure"><?= esc($activeNetworksCount) ?></h3>
            </div>
        </div>
    </div>

    <!-- Card Dispositivos -->
    <div class="col-4">
        <div class="card border shadow-none h-100 mb-0 dash-stat-card">
            <div class="card-body p-2 p-sm-3 p-md-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted fs-2 fw-semibold text-truncate">Dispositivos</span>
                    <div class="dash-stat-icon bg-info-subtle text-info">
                        <i class="ti ti-devices"></i>
                    </div>
                </div>
                <h3 class="dash-stat-number fw-bold text-body mb-0 dash-figure"><?= esc($devicesCount ?? 0) ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- =====================================================================
     COMPARATIVA DE MIS REDES Y TIPOS DE DISPOSITIVO (APEXCHARTS)
     ===================================================================== -->
<div class="row">
    <!-- Gráfica de Tráfico Acumulado por Red VPN -->
    <div class="col-xl-8 col-lg-7 mb-4">
        <div class="card border shadow-none h-100">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
                    <div>
                        <h5 class="card-title fw-semibold mb-1">Tráfico Acumulado por Red VPN</h5>
                        <p class="card-subtitle text-muted fs-2 mb-0">Comparativa de consumo entre tus redes</p>
                    </div>
                    <div class="d-flex align-items-center gap-1 flex-wrap">
                        <span class="badge bg-success-subtle text-success px-2 py-1">
                            <i class="ti ti-arrow-down me-1"></i>RX <?= esc($telemetry['formattedRx'] ?? '0 B') ?>
                        </span>
                        <span class="badge bg-warning-subtle text-warning px-2 py-1">
                            <i class="ti ti-arrow-up me-1"></i>TX <?= esc($telemetry['formattedTx'] ?? '0 B') ?>
                        </span>
                    </div>
                </div>

                <?php if (empty($telemetry['networkLabels'])): ?>
                    <div class="text-center py-5">
                        <div class="bg-light-subtle rounded-circle d-inline-flex p-3 mb-3 text-muted">
                            <i class="ti ti-network fs-8"></i>
                        </div>
                        <h6 class="fw-semibold mb-1">Sin redes registradas</h6>
                        <p class="text-muted fs-2 mb-0">Crea tu primera red VPN para visualizar su comparativa de consumo.</p>
                    </div>
                <?php else: ?>
                    <div id="chart-network-traffic-user" class="dash-chart-lg"></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Distribución por Tipo de Dispositivo (Donut) -->
    <div class="col-xl-4 col-lg-5 mb-4">
        <div class="card border shadow-none h-100">
            <div class="card-body p-3 p-md-4 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <h5 class="card-title fw-semibold mb-0">Tipos de Dispositivo</h5>
                        <span class="badge bg-primary-subtle text-primary"><?= esc($telemetry['totalDevices'] ?? 0) ?> nodos</span>
                    </div>
                    <p class="card-subtitle text-muted fs-2 mb-3">Distribución de tus equipos conectados</p>
                </div>

                <div class="position-relative d-flex justify-content-center my-2">
                    <?php if (($telemetry['totalDevices'] ?? 0) === 0): ?>
                        <div class="text-center py-4">
                            <i class="ti ti-devices fs-8 text-muted d-block mb-2"></i>
                            <span class="text-muted fs-2">Sin dispositivos registrados</span>
                        </div>
                    <?php else: ?>
                        <div id="chart-device-types-user" class="dash-chart-md w-100"></div>
                    <?php endif; ?>
                </div>

                <div class="border-top pt-3 mt-2">
                    <div class="row text-center g-0 dash-health-row">
                        <div class="col-4">
                            <div class="dash-health-cell">
                                <span class="d-block text-primary fw-medium fs-4 dash-figure">
                                    <?= esc($telemetry['typePc'] ?? 0) ?>
                                </span>
                                <span class="text-muted fs-2 fw-medium">PC / Laptop</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="dash-health-cell">
                                <span class="d-block text-body fw-medium fs-4 dash-figure">
                                    <?= esc($telemetry['typeServer'] ?? 0) ?>
                                </span>
                                <span class="text-muted fs-2 fw-medium">Servidores</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="dash-health-cell">
                                <span class="d-block text-muted fw-medium fs-4 dash-figure">
                                    <?= esc(($telemetry['typeMobile'] ?? 0) + ($telemetry['typeRouter'] ?? 0)) ?>
                                </span>
                                <span class="text-muted fs-2 fw-medium">Móvil / Router</span>
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
            <div class="card-body p-3 p-md-4">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h5 class="card-title fw-semibold mb-1">Mis Redes VPN</h5>
                        <p class="card-subtitle text-muted fs-2 mb-0">Redes privadas creadas bajo tu cuenta</p>
                    </div>
                    <div class="d-none d-md-flex align-items-center gap-2">
                        <?php if (auth()->user()->can('networks.create') || auth()->user()->inGroup('superadmin')): ?>
                            <a href="<?= site_url('networks/create') ?>" class="btn btn-sm btn-primary border-0 d-inline-flex align-items-center px-2 py-1 shadow-none" title="Nueva Red">
                                <i class="ti ti-plus"></i>
                                <span class="d-none d-sm-inline ms-1">Nueva Red</span>
                            </a>
                        <?php endif; ?>
                        <a href="<?= site_url('networks') ?>" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center px-2 py-1">
                            <span>Gestionar Redes</span><i class="ti ti-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>

                <?php if (empty($networks)): ?>
                    <div class="text-center py-4">
                        <i class="ti ti-network fs-8 text-muted mb-2 d-block"></i>
                        <h6 class="fw-semibold">No tienes redes creadas</h6>
                        <p class="text-muted fs-2 mb-3">Crea tu primera red VPN para comenzar a conectar tus dispositivos.</p>
                        <a href="<?= site_url('networks/create') ?>" class="btn btn-primary btn-sm">
                            <i class="ti ti-plus me-1"></i> Crear Red
                        </a>
                    </div>
                <?php else: ?>
                    <!-- VISTA ESCRITORIO: TABLA (d-none d-md-block) -->
                    <div class="table-responsive overflow-visible d-none d-md-block">
                        <table class="table table-hover align-middle text-nowrap text-center mb-0" id="networks-table">
                            <thead>
                                <tr class="text-muted fw-semibold">
                                    <th scope="col" class="text-start">Nombre</th>
                                    <th scope="col" class="text-center">Rango CIDR</th>
                                    <th scope="col" class="text-center">Propietario</th>
                                    <th scope="col" class="text-center">Nodos</th>
                                    <th scope="col" class="text-center">Creada</th>
                                    <th scope="col" class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="border-top">
                                <?php foreach ($networks as $net): ?>
                                    <?php $canViewDevices = auth()->user()->can('devices.view') || auth()->user()->inGroup('superadmin'); ?>
                                    <tr <?= $canViewDevices ? 'onclick="window.location=\'' . site_url('networks/' . $net->id . '/devices') . '\'" class="cursor-pointer"' : '' ?>>
                                        <td class="text-start">
                                            <div class="d-flex align-items-center">
                                                <div class="d-flex align-items-center justify-content-center <?= $net->active ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?> rounded-circle me-3 network-icon-circle">
                                                    <i class="ti ti-network fs-5"></i>
                                                </div>
                                                <div>
                                                    <h6 class="fw-semibold mb-1">
                                                        <?php if ($canViewDevices): ?>
                                                            <a href="<?= site_url('networks/' . $net->id . '/devices') ?>" class="text-reset text-primary-hover text-decoration-none">
                                                                <?= esc($net->name) ?>
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="text-reset">
                                                                <?= esc($net->name) ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </h6>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center text-muted">
                                            <span class="net-mono"><?= esc($net->cidr) ?></span>
                                        </td>
                                        <td class="text-center text-muted">
                                            <?= $net->owner_username ? esc($net->owner_username) : 'Sin propietario' ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="net-mono text-body"><?= esc($net->device_count ?? 0) ?></span>
                                        </td>
                                        <td class="text-center">
                                            <h6 class="fw-semibold mb-0 text-login-time"><?= date('d/m/Y', strtotime($net->created_at)) ?></h6>
                                            <span class="fw-normal text-muted text-login-time"><?= date('H:i', strtotime($net->created_at)) ?></span>
                                        </td>
                                        <td class="text-end" onclick="event.stopPropagation();">
                                            <div class="dropdown">
                                                <button class="btn btn-sm bg-transparent border-0 text-muted shadow-none p-1" type="button" data-bs-toggle="dropdown" aria-expanded="false" data-bs-popper-config='{"strategy":"fixed"}'>
                                                    <i class="ti ti-dots-vertical fs-5"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <?php if ($canViewDevices): ?>
                                                    <li>
                                                        <a href="<?= site_url('networks/' . $net->id . '/devices') ?>" class="dropdown-item d-flex align-items-center gap-2">
                                                            <i class="ti ti-devices"></i> Administrar Nodos
                                                        </a>
                                                    </li>
                                                    <?php endif; ?>
                                                    <?php if (auth()->user()->can('networks.edit') || auth()->user()->inGroup('superadmin')): ?>
                                                    <li>
                                                        <a href="<?= site_url('networks/edit/' . $net->id) ?>" class="dropdown-item d-flex align-items-center gap-2">
                                                            <i class="ti ti-pencil"></i> Editar
                                                        </a>
                                                    </li>
                                                    <?php endif; ?>
                                                    <?php if (auth()->user()->can('networks.delete') || auth()->user()->inGroup('superadmin')): ?>
                                                    <li>
                                                        <form action="<?= site_url('networks/delete/' . $net->id) ?>" method="post" data-confirm="¿Estás seguro de que deseas eliminar esta red?">
                                                            <?= csrf_field() ?>
                                                            <button type="submit" class="dropdown-item d-flex align-items-center gap-2 text-danger w-100 border-0 bg-transparent text-start">
                                                                <i class="ti ti-trash"></i> Eliminar
                                                            </button>
                                                        </form>
                                                    </li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- VISTA MÓVIL: TARJETAS (d-block d-md-none) -->
                    <div class="d-block d-md-none" id="userNetworksCardsContainer">
                        <?php foreach ($networks as $net): ?>
                            <?php $canViewDevices = auth()->user()->can('devices.view') || auth()->user()->inGroup('superadmin'); ?>
                            <div class="mobile-card-item network-card-item" <?= $canViewDevices ? 'onclick="window.location=\'' . site_url('networks/' . $net->id . '/devices') . '\'"' : '' ?>>
                                <div class="mobile-card-header">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="d-flex align-items-center justify-content-center <?= $net->active ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?> rounded-circle network-icon-circle flex-shrink-0">
                                            <i class="ti ti-network fs-5"></i>
                                        </div>
                                        <div>
                                            <h6 class="mobile-card-title"><?= esc($net->name) ?></h6>
                                            <span class="net-mono"><?= esc($net->cidr) ?></span>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center" onclick="event.stopPropagation();">
                                        <div class="dropdown">
                                            <button class="btn btn-sm bg-transparent border-0 text-muted shadow-none p-1" type="button" data-bs-toggle="dropdown" aria-expanded="false" data-bs-popper-config='{"strategy":"fixed"}'>
                                                <i class="ti ti-dots-vertical fs-5"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <?php if ($canViewDevices): ?>
                                                <li>
                                                    <a href="<?= site_url('networks/' . $net->id . '/devices') ?>" class="dropdown-item d-flex align-items-center gap-2">
                                                        <i class="ti ti-devices"></i> Administrar Nodos
                                                    </a>
                                                </li>
                                                <?php endif; ?>
                                                <?php if (auth()->user()->can('networks.edit') || auth()->user()->inGroup('superadmin')): ?>
                                                <li>
                                                    <a href="<?= site_url('networks/edit/' . $net->id) ?>" class="dropdown-item d-flex align-items-center gap-2">
                                                        <i class="ti ti-pencil"></i> Editar
                                                    </a>
                                                </li>
                                                <?php endif; ?>
                                                <?php if (auth()->user()->can('networks.delete') || auth()->user()->inGroup('superadmin')): ?>
                                                <li>
                                                    <form action="<?= site_url('networks/delete/' . $net->id) ?>" method="post" data-confirm="¿Estás seguro de que deseas eliminar esta red?">
                                                        <?= csrf_field() ?>
                                                        <button type="submit" class="dropdown-item d-flex align-items-center gap-2 text-danger w-100 border-0 bg-transparent text-start">
                                                            <i class="ti ti-trash"></i> Eliminar
                                                        </button>
                                                    </form>
                                                </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="mobile-card-body">
                                    <div class="mobile-card-row">
                                        <span class="mobile-card-label">Propietario</span>
                                        <span class="mobile-card-value text-muted"><?= $net->owner_username ? esc($net->owner_username) : 'Sin propietario' ?></span>
                                    </div>
                                    <div class="mobile-card-row">
                                        <span class="mobile-card-label">Nodos</span>
                                        <span class="mobile-card-value">
                                            <span class="net-mono text-body"><?= esc($net->device_count ?? 0) ?></span>
                                        </span>
                                    </div>
                                    <div class="mobile-card-row">
                                        <span class="mobile-card-label">Creada</span>
                                        <span class="mobile-card-value text-muted"><?= date('d/m/Y H:i', strtotime($net->created_at)) ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- =====================================================================
     SCRIPTS DE APEXCHARTS (VISTA EJECUTIVA POR REDES Y EQUIPOS)
     ===================================================================== -->
<script src="<?= base_url('assets/libs/apexcharts/dist/apexcharts.min.js') ?>"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';

    // -------------------------------------------------------------
    // 1. Gráfica de Barras: Tráfico Acumulado por Red VPN
    // -------------------------------------------------------------
    const netTrafficEl = document.querySelector("#chart-network-traffic-user");
    if (netTrafficEl) {
        const netCategories = <?= json_encode($telemetry['networkLabels'] ?? []) ?>;
        const netRx = <?= json_encode($telemetry['networkRx'] ?? []) ?>;
        const netTx = <?= json_encode($telemetry['networkTx'] ?? []) ?>;

        const netTrafficOptions = {
            series: [
                { name: 'Descarga (RX)', data: netRx },
                { name: 'Subida (TX)', data: netTx }
            ],
            chart: {
                type: 'bar',
                height: 330,
                toolbar: { show: false },
                fontFamily: 'inherit',
                foreColor: isDark ? '#94a3b8' : '#64748b'
            },
            colors: ['#10B981', '#F38020'],
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '48%',
                    borderRadius: 5,
                    borderRadiusApplication: 'end'
                }
            },
            dataLabels: { enabled: false },
            stroke: {
                show: true,
                width: 2,
                colors: ['transparent']
            },
            xaxis: {
                categories: netCategories,
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

        const netTrafficChart = new ApexCharts(netTrafficEl, netTrafficOptions);
        netTrafficChart.render();
    }

    // -------------------------------------------------------------
    // 2. Gráfica Donut: Distribución por Tipo de Dispositivo
    // -------------------------------------------------------------
    const deviceTypesEl = document.querySelector("#chart-device-types-user");
    if (deviceTypesEl) {
        const typeLabels = <?= json_encode($telemetry['deviceTypeLabels'] ?? ['PC / Portátil', 'Servidores', 'Móviles / Tablets', 'Routers']) ?>;
        const typeSeries = <?= json_encode($telemetry['deviceTypeSeries'] ?? [0, 0, 0, 0]) ?>;

        const deviceTypesOptions = {
            series: typeSeries,
            labels: typeLabels,
            chart: {
                type: 'donut',
                height: 255,
                fontFamily: 'inherit',
                foreColor: isDark ? '#94a3b8' : '#64748b'
            },
            colors: ['#F38020', '#10B981', '#3B82F6', '#8B5CF6'],
            stroke: {
                width: 2,
                colors: [isDark ? '#111c2d' : '#ffffff']
            },
            dataLabels: { enabled: false },
            plotOptions: {
                pie: {
                    donut: {
                        size: '72%',
                        labels: {
                            show: true,
                            name: {
                                show: true,
                                fontSize: '12px',
                                fontWeight: 600,
                                color: isDark ? '#94a3b8' : '#64748b',
                                offsetY: -4
                            },
                            value: {
                                show: true,
                                fontSize: '26px',
                                fontWeight: 700,
                                color: isDark ? '#ffffff' : '#0f172a',
                                offsetY: 6
                            },
                            total: {
                                show: true,
                                label: 'Equipos',
                                fontSize: '12px',
                                fontWeight: 600,
                                color: isDark ? '#94a3b8' : '#64748b',
                                formatter: function (w) {
                                    return w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                }
                            }
                        }
                    }
                }
            },
            legend: {
                position: 'bottom',
                fontSize: '12px',
                labels: { colors: isDark ? '#cbd5e1' : '#475569' }
            },
            tooltip: {
                theme: isDark ? 'dark' : 'light'
            }
        };

        const deviceTypesChart = new ApexCharts(deviceTypesEl, deviceTypesOptions);
        deviceTypesChart.render();
    }
});
</script>
