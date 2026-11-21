<!-- =====================================================================
     CABECERA Y BREADCRUMB (NAVEGACIÓN)
     ===================================================================== -->
<div class="card border shadow-none position-relative overflow-hidden mb-3 mb-md-4">
  <div class="card-body px-3 px-md-4 py-3">
    <div class="d-flex align-items-center justify-content-between gap-2">
      <div>
        <h4 class="fw-semibold mb-1">
          Panel de Administración
          <?php if (empty($isSuperAdmin) && !empty($companyName)): ?>
            <span class="badge bg-primary-subtle text-primary ms-2 align-middle"><?= esc($companyName) ?></span>
          <?php endif; ?>
        </h4>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
              <a class="text-muted text-decoration-none" href="<?= base_url() ?>">Inicio</a>
            </li>
            <li class="breadcrumb-item active text-muted" aria-current="page">Dashboard</li>
          </ol>
        </nav>
      </div>
      <?php $serviceLabel = 'Servicio VPN'; ?>
      <div class="flex-shrink-0 text-end">
        <div class="d-inline-flex align-items-center gap-2 px-2 py-1 rounded-2 <?= ($telemetry['isInterfaceUp'] ?? false) ? 'status-pill-success' : 'status-pill-warning' ?> fs-2 fw-semibold" title="<?= $serviceLabel ?> <?= ($telemetry['isInterfaceUp'] ?? false) ? 'Online' : 'No online' ?>">
          <span class="status-indicator-dot <?= ($telemetry['isInterfaceUp'] ?? false) ? 'status-online' : 'status-offline' ?>"></span>
          <span class="d-none d-sm-inline"><?= $serviceLabel ?> <?= ($telemetry['isInterfaceUp'] ?? false) ? 'Online' : 'No online' ?></span>
          <span class="d-inline d-sm-none"><?= ($telemetry['isInterfaceUp'] ?? false) ? 'Online' : 'No online' ?></span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- =====================================================================
     TARJETAS DE ESTADÍSTICAS
     ===================================================================== -->
<?php $statColClass = !empty($isSuperAdmin) ? 'col-6 col-md-3' : 'col-4'; ?>
<div class="row g-2 g-md-3 mb-3 mb-md-4">
    <?php if (!empty($isSuperAdmin)): ?>
    <!-- Card Empresas Registradas -->
    <div class="<?= $statColClass ?>">
        <div class="card border shadow-none h-100 mb-0 dash-stat-card">
            <div class="card-body p-2 p-sm-3 p-md-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted fs-2 fw-semibold text-truncate">Empresas</span>
                    <div class="dash-stat-icon bg-warning-subtle text-warning">
                        <i class="ti ti-building"></i>
                    </div>
                </div>
                <h3 class="dash-stat-number fw-bold text-body mb-0 dash-figure"><?= esc($totalCompanies ?? 0) ?></h3>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Card Usuarios Registrados -->
    <div class="<?= $statColClass ?>">
        <div class="card border shadow-none h-100 mb-0 dash-stat-card">
            <div class="card-body p-2 p-sm-3 p-md-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted fs-2 fw-semibold text-truncate">Usuarios</span>
                    <div class="dash-stat-icon bg-primary-subtle text-primary">
                        <i class="ti ti-users"></i>
                    </div>
                </div>
                <h3 class="dash-stat-number fw-bold text-body mb-0 dash-figure"><?= esc($totalUsers ?? 0) ?></h3>
            </div>
        </div>
    </div>

    <!-- Card Redes Totales -->
    <div class="<?= $statColClass ?>">
        <div class="card border shadow-none h-100 mb-0 dash-stat-card">
            <div class="card-body p-2 p-sm-3 p-md-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted fs-2 fw-semibold text-truncate">Redes VPN</span>
                    <div class="dash-stat-icon bg-info-subtle text-info">
                        <i class="ti ti-network"></i>
                    </div>
                </div>
                <h3 class="dash-stat-number fw-bold text-body mb-0 dash-figure"><?= esc($totalNetworks ?? 0) ?></h3>
            </div>
        </div>
    </div>

    <!-- Card Dispositivos Totales -->
    <div class="<?= $statColClass ?>">
        <div class="card border shadow-none h-100 mb-0 dash-stat-card">
            <div class="card-body p-2 p-sm-3 p-md-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted fs-2 fw-semibold text-truncate">Dispositivos</span>
                    <div class="dash-stat-icon bg-success-subtle text-success">
                        <i class="ti ti-devices"></i>
                    </div>
                </div>
                <h3 class="dash-stat-number fw-bold text-body mb-0 dash-figure"><?= esc($totalDevices ?? 0) ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- =====================================================================
     ÚLTIMAS REDES REGISTRADAS
     ===================================================================== -->
<?php if (!empty($recentNetworks)): ?>
<div class="row">
    <div class="col-12 mb-4">
        <div class="card border shadow-none">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h5 class="card-title fw-semibold mb-1">Últimas Redes Creadas</h5>
                        <p class="card-subtitle text-muted fs-2 mb-0">Últimas redes VPN desplegadas en el sistema</p>
                    </div>
                    <div class="d-none d-md-flex align-items-center gap-2">
                        <?php if (auth()->user()->can('networks.create') || auth()->user()->inGroup('superadmin')): ?>
                            <a href="<?= site_url('networks/create') ?>" class="btn btn-sm btn-primary border-0 d-inline-flex align-items-center px-2 py-1 shadow-none" title="Nueva Red">
                                <i class="ti ti-plus"></i>
                                <span class="d-none d-sm-inline ms-1">Nueva Red</span>
                            </a>
                        <?php endif; ?>
                        <a href="<?= site_url('networks') ?>" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center px-2 py-1">
                            <span>Ver todas</span><i class="ti ti-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>

                <!-- VISTA ESCRITORIO: TABLA (d-none d-md-block) -->
                <div class="table-responsive overflow-visible d-none d-md-block">
                    <table class="table table-hover align-middle text-nowrap text-center mb-0" id="networks-table">
                        <thead>
                            <tr class="text-muted fw-semibold">
                                <th scope="col" class="text-start">Nombre</th>
                                <th scope="col" class="text-center">Rango CIDR</th>
                                <?php if (!empty($isSuperAdmin)): ?>
                                <th scope="col" class="text-center">Empresa</th>
                                <?php endif; ?>
                                <th scope="col" class="text-center">Propietario</th>
                                <th scope="col" class="text-center">Nodos</th>
                                <th scope="col" class="text-center">Creada</th>
                                <th scope="col" class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="border-top">
                            <?php foreach ($recentNetworks as $net): ?>
                                <?php $canViewDevices = auth()->user()->can('devices.view') || auth()->user()->inGroup('superadmin'); ?>
                                <tr <?= $canViewDevices ? 'data-row-href="' . site_url('networks/' . $net->id . '/devices') . '" class="cursor-pointer"' : '' ?>>
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
                                    <?php if (!empty($isSuperAdmin)): ?>
                                    <td class="text-center text-muted">
                                        <?= !empty($net->company_name) ? esc($net->company_name) : 'Sin empresa' ?>
                                    </td>
                                    <?php endif; ?>
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
                                    <td class="text-end" data-stop-propagation="true">
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
                <div class="d-block d-md-none" id="recentNetworksCardsContainer">
                    <?php foreach ($recentNetworks as $net): ?>
                        <?php $canViewDevices = auth()->user()->can('devices.view') || auth()->user()->inGroup('superadmin'); ?>
                        <div class="mobile-card-item network-card-item" <?= $canViewDevices ? 'data-row-href="' . site_url('networks/' . $net->id . '/devices') . '"' : '' ?>>
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
                                <div class="d-flex align-items-center" data-stop-propagation="true">
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
                                <?php if (!empty($isSuperAdmin)): ?>
                                <div class="mobile-card-row">
                                    <span class="mobile-card-label">Empresa</span>
                                    <span class="mobile-card-value text-muted"><?= !empty($net->company_name) ? esc($net->company_name) : 'Sin empresa' ?></span>
                                </div>
                                <?php endif; ?>
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

            </div>
        </div>
    </div>
</div>
<?php endif; ?>
