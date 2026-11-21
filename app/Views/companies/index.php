<!-- =====================================================================
     CABECERA Y BREADCRUMB (NAVEGACIÓN)
     ===================================================================== -->
<div class="card border shadow-none position-relative overflow-hidden mb-4">
  <div class="card-body px-4 py-3">
    <div class="row align-items-center">
      <div class="col-12 col-md-8 text-center text-md-start">
        <h4 class="fw-semibold mb-2 mb-md-0">Gestión de Empresas</h4>
        <nav aria-label="breadcrumb" class="d-flex justify-content-center justify-content-md-start">
          <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
              <a class="text-muted text-decoration-none" href="<?= site_url() ?>">Inicio</a>
            </li>
            <li class="breadcrumb-item" aria-current="page">Empresas</li>
          </ol>
        </nav>
      </div>
      <div class="col-12 col-md-4 d-flex justify-content-center justify-content-md-end align-items-center mt-3 mt-md-0">
        <a href="<?= site_url('companies/create') ?>" class="btn btn-primary btn-sm border-0 d-flex align-items-center gap-1 shadow-none">
          <i class="ti ti-plus"></i>
          <span>Nueva Empresa</span>
        </a>
      </div>
    </div>
  </div>
</div>

<!-- =====================================================================
     LISTADO DE EMPRESAS Y BÚSQUEDA
     ===================================================================== -->
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <div class="d-flex flex-wrap justify-content-end align-items-center mb-4 gap-3">
          <div class="d-flex align-items-center gap-2 w-100 w-md-auto search-form-responsive ms-auto">
            <div class="position-relative w-100 search-box-container">
              <input type="text" id="search-companies" class="form-control" placeholder="Buscar empresa..." value="<?= esc($search ?? '') ?>">
              <i class="ti ti-search search-icon text-muted"></i>
            </div>
          </div>
        </div>

        <?php if (empty($companies)): ?>
          <div class="text-center py-5">
            <div class="round-48 d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle mb-2">
              <i class="ti ti-building fs-5"></i>
            </div>
            <h6 class="fw-medium mb-1"><?= !empty($search) ? 'Sin resultados' : 'Aún no hay empresas' ?></h6>
            <p class="text-muted fs-3 mb-0"><?= !empty($search) ? 'Ninguna empresa coincide con tu búsqueda' : 'Crea la primera empresa para empezar a asignar usuarios y redes' ?></p>
          </div>
        <?php else: ?>

        <div class="table-responsive overflow-visible d-none d-md-block">
          <table class="table table-hover align-middle text-nowrap net-table" id="companies-table">
            <thead>
              <tr class="text-muted fw-semibold">
                <th scope="col">Empresa</th>
                <th scope="col" class="text-center">Usuarios</th>
                <th scope="col" class="text-center">Redes</th>
                <th scope="col" class="text-center">Dispositivos</th>
                <th scope="col" class="text-center">Estado</th>
                <th scope="col" class="text-end"><span class="visually-hidden">Acciones</span></th>
              </tr>
            </thead>
            <tbody class="border-top">
              <?php foreach ($companies as $company): ?>
                <tr data-row-href="<?= site_url('companies/edit/' . $company->id) ?>" class="cursor-pointer">
                  <td>
                    <div class="d-flex align-items-center gap-3">
                      <div class="d-flex align-items-center justify-content-center <?= $company->active ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?> rounded-circle network-icon-circle flex-shrink-0" title="<?= $company->active ? 'Activa' : 'Suspendida' ?>">
                        <i class="ti ti-building fs-5"></i>
                      </div>
                      <div>
                        <a href="<?= site_url('companies/edit/' . $company->id) ?>" class="net-name text-decoration-none"><?= esc($company->name) ?></a>
                        <span class="d-block text-muted fs-2 net-mono"><?= esc($company->slug) ?></span>
                      </div>
                    </div>
                  </td>
                  <td class="text-center"><span class="net-mono text-body"><?= esc($company->users_count ?? 0) ?></span></td>
                  <td class="text-center">
                    <span class="net-mono text-body"><?= esc($company->networks_count ?? 0) ?></span>
                    <?php if (!empty($company->max_networks)): ?>
                      <span class="text-muted fs-2">/ <?= esc($company->max_networks) ?></span>
                    <?php endif; ?>
                  </td>
                  <td class="text-center">
                    <span class="net-mono text-body"><?= esc($company->devices_count ?? 0) ?></span>
                    <?php if (!empty($company->max_devices)): ?>
                      <span class="text-muted fs-2">/ <?= esc($company->max_devices) ?></span>
                    <?php endif; ?>
                  </td>
                  <td class="text-center">
                    <?php if ($company->active): ?>
                      <span class="badge bg-success-subtle text-success badge-status">Activa</span>
                    <?php else: ?>
                      <span class="badge bg-danger-subtle text-danger badge-status">Suspendida</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end" data-stop-propagation="true">
                    <div class="dropdown">
                      <button class="btn btn-sm bg-transparent border-0 text-muted shadow-none p-1" type="button" data-bs-toggle="dropdown" aria-expanded="false" data-bs-popper-config='{"strategy":"fixed"}'>
                        <i class="ti ti-dots-vertical fs-5"></i>
                      </button>
                      <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                          <a href="<?= site_url('companies/edit/' . $company->id) ?>" class="dropdown-item d-flex align-items-center gap-2">
                            <i class="ti ti-pencil"></i> Editar
                          </a>
                        </li>
                        <li>
                          <form action="<?= site_url('companies/toggle-active/' . $company->id) ?>" method="post">
                            <?= csrf_field() ?>
                            <button type="submit" class="dropdown-item d-flex align-items-center gap-2 w-100 border-0 bg-transparent text-start">
                              <i class="ti <?= $company->active ? 'ti-ban text-warning' : 'ti-check text-success' ?>"></i>
                              <?= $company->active ? 'Suspender' : 'Activar' ?>
                            </button>
                          </form>
                        </li>
                        <li>
                          <form action="<?= site_url('companies/delete/' . $company->id) ?>" method="post" data-confirm="¿Estás seguro de que deseas eliminar la empresa <?= esc($company->name) ?>?">
                            <?= csrf_field() ?>
                            <button type="submit" class="dropdown-item d-flex align-items-center gap-2 text-danger w-100 border-0 bg-transparent text-start">
                              <i class="ti ti-trash"></i> Eliminar
                            </button>
                          </form>
                        </li>
                      </ul>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- VISTA MÓVIL: TARJETAS (d-block d-md-none) -->
        <div class="d-block d-md-none" id="companiesCardsContainer">
          <?php foreach ($companies as $company): ?>
            <div class="mobile-card-item company-card-item" data-row-href="<?= site_url('companies/edit/' . $company->id) ?>">
              <div class="mobile-card-header">
                <div class="d-flex align-items-center gap-2">
                  <div class="d-flex align-items-center justify-content-center <?= $company->active ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?> rounded-circle network-icon-circle flex-shrink-0">
                    <i class="ti ti-building fs-5"></i>
                  </div>
                  <div>
                    <h6 class="mobile-card-title mb-0"><?= esc($company->name) ?></h6>
                    <span class="net-mono text-muted"><?= esc($company->slug) ?></span>
                  </div>
                </div>
                <div class="d-flex align-items-center" data-stop-propagation="true">
                  <div class="dropdown">
                    <button class="btn btn-sm bg-transparent border-0 text-muted shadow-none p-1" type="button" data-bs-toggle="dropdown" aria-expanded="false" data-bs-popper-config='{"strategy":"fixed"}'>
                      <i class="ti ti-dots-vertical fs-5"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                      <li>
                        <a href="<?= site_url('companies/edit/' . $company->id) ?>" class="dropdown-item d-flex align-items-center gap-2">
                          <i class="ti ti-pencil"></i> Editar
                        </a>
                      </li>
                      <li>
                        <form action="<?= site_url('companies/toggle-active/' . $company->id) ?>" method="post">
                          <?= csrf_field() ?>
                          <button type="submit" class="dropdown-item d-flex align-items-center gap-2 w-100 border-0 bg-transparent text-start">
                            <i class="ti <?= $company->active ? 'ti-ban text-warning' : 'ti-check text-success' ?>"></i>
                            <?= $company->active ? 'Suspender' : 'Activar' ?>
                          </button>
                        </form>
                      </li>
                      <li>
                        <form action="<?= site_url('companies/delete/' . $company->id) ?>" method="post" data-confirm="¿Estás seguro de que deseas eliminar la empresa <?= esc($company->name) ?>?">
                          <?= csrf_field() ?>
                          <button type="submit" class="dropdown-item d-flex align-items-center gap-2 text-danger w-100 border-0 bg-transparent text-start">
                            <i class="ti ti-trash"></i> Eliminar
                          </button>
                        </form>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
              <div class="mobile-card-body">
                <div class="mobile-card-row">
                  <span class="mobile-card-label">Usuarios</span>
                  <span class="mobile-card-value net-mono text-body"><?= esc($company->users_count ?? 0) ?></span>
                </div>
                <div class="mobile-card-row">
                  <span class="mobile-card-label">Redes</span>
                  <span class="mobile-card-value net-mono text-body"><?= esc($company->networks_count ?? 0) ?><?= !empty($company->max_networks) ? ' / ' . esc($company->max_networks) : '' ?></span>
                </div>
                <div class="mobile-card-row">
                  <span class="mobile-card-label">Dispositivos</span>
                  <span class="mobile-card-value net-mono text-body"><?= esc($company->devices_count ?? 0) ?><?= !empty($company->max_devices) ? ' / ' . esc($company->max_devices) : '' ?></span>
                </div>
                <div class="mobile-card-row">
                  <span class="mobile-card-label">Estado</span>
                  <span class="mobile-card-value">
                    <?php if ($company->active): ?>
                      <span class="badge bg-success-subtle text-success">Activa</span>
                    <?php else: ?>
                      <span class="badge bg-danger-subtle text-danger">Suspendida</span>
                    <?php endif; ?>
                  </span>
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
     SCRIPT DE BÚSQUEDA EN TIEMPO REAL
     ===================================================================== -->
<script {csp-script-nonce}>
document.addEventListener('DOMContentLoaded', function() {
  const searchInput = document.getElementById('search-companies');
  if (searchInput) {
    searchInput.addEventListener('input', function() {
      const filter = this.value.toLowerCase().trim();
      const rows = document.querySelectorAll('#companies-table tbody tr');
      const cards = document.querySelectorAll('.company-card-item');

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
