<!-- =====================================================================
     CABECERA Y BREADCRUMB (NAVEGACIÓN)
     ===================================================================== -->
<div class="card border shadow-none position-relative overflow-hidden mb-4">
  <div class="card-body px-4 py-3">
    <div class="row align-items-center">
      <div class="col-12 col-md-8 text-center text-md-start">
        <h4 class="fw-semibold mb-2 mb-md-8">Gestión de Redes</h4>
        <nav aria-label="breadcrumb" class="d-flex justify-content-center justify-content-md-start">
          <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
              <a class="text-muted text-decoration-none" href="<?= site_url() ?>">Inicio</a>
            </li>
            <li class="breadcrumb-item" aria-current="page">Redes</li>
          </ol>
        </nav>
      </div>
      <div class="col-12 col-md-4 d-flex justify-content-center justify-content-md-end align-items-center mt-3 mt-md-0">
        <?php if (auth()->user()->can('networks.create') || auth()->user()->inGroup('superadmin')): ?>
          <a href="<?= site_url('networks/create') ?>" class="btn btn-primary border-0 d-flex align-items-center gap-1">
            <i class="ti ti-plus"></i>
            <span>Nueva Red</span>
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- =====================================================================
     LISTADO DE REDES Y BÚSQUEDA
     ===================================================================== -->
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <div class="d-flex flex-wrap justify-content-end align-items-center mb-4 gap-3">
          <div class="d-flex align-items-center gap-2 w-100 w-md-auto search-form-responsive ms-auto">
            <div class="position-relative w-100 search-box-container">
              <input type="text" id="search-networks" class="form-control" placeholder="Buscar red...">
              <i class="ti ti-search search-icon text-muted"></i>
            </div>
          </div>
        </div>

        <?php if (empty($networks)): ?>
          <div class="alert alert-dark text-center py-4" role="alert">
            <?= !empty($search) ? 'No se encontraron redes para tu búsqueda.' : 'No tienes ninguna red registrada.' ?>
          </div>
        <?php else: ?>

        <div class="table-responsive overflow-visible d-none d-md-block">
          <table class="table table-hover align-middle text-nowrap text-center" id="networks-table">
            <thead>
              <tr class="text-muted fw-semibold">
                <th scope="col" class="text-start">Nombre</th>
                <th scope="col" class="text-center">Rango CIDR</th>
                <th scope="col" class="text-center">Propietario</th>
                <th scope="col" class="text-center">Nodos</th>
                <th scope="col" class="text-center">Fecha de Creación</th>
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
                    <code class="text-primary"><?= esc($net->cidr) ?></code>
                  </td>
                  <td class="text-center text-muted">
                    <?= $net->owner_username ? esc($net->owner_username) : 'Sin propietario' ?>
                  </td>
                  <td class="text-center">
                    <span class="badge bg-light-primary text-primary fw-semibold fs-3 rounded-pill px-3 py-1">
                      <?= esc($net->device_count) ?>
                    </span>
                  </td>
                  <td class="text-center">
                    <h6 class="fs-3 fw-semibold mb-0"><?= date('d/m/Y', strtotime($net->created_at)) ?></h6>
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
        <div class="d-block d-md-none" id="networksCardsContainer">
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
                    <code class="text-primary fs-2"><?= esc($net->cidr) ?></code>
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
                  <span class="mobile-card-label"><i class="ti ti-user"></i> Propietario:</span>
                  <span class="mobile-card-value text-muted"><?= $net->owner_username ? esc($net->owner_username) : 'Sin propietario' ?></span>
                </div>
                <div class="mobile-card-row">
                  <span class="mobile-card-label"><i class="ti ti-devices"></i> Nodos:</span>
                  <span class="mobile-card-value">
                    <span class="badge bg-light-primary text-primary fw-semibold rounded-pill px-2 py-1">
                      <?= esc($net->device_count) ?>
                    </span>
                  </span>
                </div>
                <div class="mobile-card-row">
                  <span class="mobile-card-label"><i class="ti ti-calendar"></i> Creado:</span>
                  <span class="mobile-card-value text-muted"><?= date('d/m/Y H:i', strtotime($net->created_at)) ?></span>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="mt-4 d-flex justify-content-center relative-z0">
          <?= $pager->links('networks', 'premium') ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- =====================================================================
     SCRIPT DE BÚSQUEDA EN TIEMPO REAL
     ===================================================================== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  const searchInput = document.getElementById('search-networks');
  if (searchInput) {
    searchInput.addEventListener('input', function() {
      const filter = this.value.toLowerCase().trim();
      const rows = document.querySelectorAll('#networks-table tbody tr');
      const cards = document.querySelectorAll('.network-card-item');
      
      rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.classList.toggle('d-none', !text.includes(filter));
      });

      cards.forEach(card => {
        const text = card.textContent.toLowerCase();
        card.classList.toggle('d-none', !text.includes(filter));
      });
    });
  }
});
</script>


