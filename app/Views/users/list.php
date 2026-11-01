<!-- =====================================================================
     CABECERA Y BREADCRUMB (NAVEGACIÓN)
     ===================================================================== -->
<div class="card border shadow-none position-relative overflow-hidden mb-4">
  <div class="card-body px-4 py-3">
    <div class="row align-items-center">
      <div class="col-12 col-md-8 text-center text-md-start">
        <h4 class="fw-semibold mb-2 mb-md-0">Gestión de Usuarios</h4>
        <nav aria-label="breadcrumb" class="d-flex justify-content-center justify-content-md-start">
          <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
              <a class="text-muted text-decoration-none" href="<?= site_url() ?>">Inicio</a>
            </li>
            <li class="breadcrumb-item" aria-current="page">Usuarios</li>
          </ol>
        </nav>
      </div>
      <div class="col-12 col-md-4 d-flex justify-content-center justify-content-md-end align-items-center mt-3 mt-md-0">
        <a href="<?= site_url('users/create') ?>" class="btn btn-primary border-0 d-flex align-items-center gap-1">
          <i class="ti ti-plus"></i>
          <span>Nuevo Usuario</span>
        </a>
      </div>
    </div>
  </div>
</div>

<!-- =====================================================================
     LISTADO DE USUARIOS Y BÚSQUEDA
     ===================================================================== -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-end align-items-center mb-4 gap-3">
                    <div class="d-flex align-items-center gap-2 w-100 w-md-auto search-form-responsive ms-auto">
                        <div class="position-relative w-100 search-box-container">
                            <input type="text" id="search-users" class="form-control" placeholder="Buscar usuario...">
                            <i class="ti ti-search search-icon text-muted"></i>
                        </div>
                    </div>
                </div>

                <?php if (empty($users)): ?>
                    <div class="text-center py-5">
                        <h6 class="fw-medium mb-1"><?= !empty($search) ? 'Sin resultados' : 'Aún no hay usuarios' ?></h6>
                        <p class="text-muted fs-3 mb-0"><?= !empty($search) ? 'Ningún usuario coincide con tu búsqueda' : 'No hay otros usuarios registrados en el sistema' ?></p>
                    </div>
                <?php else: ?>
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-hover align-middle text-nowrap net-table" id="users-table">
                        <thead>
                            <tr>
                                <th class="text-start">Usuario</th>
                                <th class="text-center">Email</th>
                                <th class="text-center">Rol</th>
                                <th class="text-center">Último acceso</th>
                                <th class="text-center">Estado</th>
                                <th class="text-end"><span class="visually-hidden">Acciones</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                             <tr onclick="window.location='<?= site_url('users/edit/' . $user->id) ?>'" class="cursor-pointer">
                                 <td>
                                     <div class="d-flex align-items-center">
                                         <img src="<?= !empty($user->profile_pic) ? base_url('uploads/profile/' . esc($user->profile_pic)) : base_url('assets/images/profile/user-default.png') ?>" alt="Avatar" class="rounded-circle me-3 object-fit-cover" width="40" height="40" />
                                         <div class="text-start">
                                             <div class="fw-medium">
                                                 <a href="<?= site_url('users/edit/' . $user->id) ?>" class="text-reset text-primary-hover text-decoration-none">
                                                     <?= esc($user->username) ?>
                                                 </a>
                                             </div>
                                         </div>
                                     </div>
                                 </td>
                                <td class="text-center text-muted"><?= esc($user->email) ?></td>
                                <td class="text-center"><?= esc($user->groupTitle) ?></td>
                                <td class="text-center">
                                    <h6 class="fw-semibold mb-0 text-login-time"><?= esc($user->lastLoginDate) ?></h6>
                                    <?php if (!empty($user->lastLoginTime)): ?>
                                        <span class="fw-normal text-muted text-login-time"><?= esc($user->lastLoginTime) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($user->active): ?>
                                        <span class="badge bg-success-subtle text-success badge-status">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger badge-status">Suspendido</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end" onclick="event.stopPropagation();">
                                    <div class="dropdown">
                                        <button class="btn btn-sm bg-transparent border-0 text-muted shadow-none p-1" type="button" data-bs-toggle="dropdown" aria-expanded="false" data-bs-popper-config='{"strategy":"fixed"}'>
                                            <i class="ti ti-dots-vertical fs-5"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <a href="<?= site_url('users/edit/' . $user->id) ?>" class="dropdown-item d-flex align-items-center gap-2">
                                                    <i class="ti ti-pencil"></i> Editar
                                                </a>
                                            </li>
                                            <?php if (auth()->user()->can('users.delete') || auth()->user()->inGroup('superadmin')): ?>
                                            <li>
                                                <form action="<?= site_url('users/delete/' . $user->id) ?>" method="post" data-confirm="¿Estás seguro de que deseas eliminar al usuario <?= esc($user->username) ?>?">
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
                <div class="d-block d-md-none" id="usersCardsContainer">
                  <?php foreach ($users as $user): ?>
                    <div class="mobile-card-item user-card-item" onclick="window.location='<?= site_url('users/edit/' . $user->id) ?>'">
                      <div class="mobile-card-header">
                        <div class="d-flex align-items-center gap-2">
                          <img src="<?= !empty($user->profile_pic) ? base_url('uploads/profile/' . esc($user->profile_pic)) : base_url('assets/images/profile/user-default.png') ?>" alt="Avatar" class="rounded-circle object-fit-cover flex-shrink-0 border border-2 <?= $user->active ? 'border-primary' : 'border-danger' ?>" width="36" height="36" />
                          <div>
                            <h6 class="mobile-card-title mb-0"><?= esc($user->username) ?></h6>
                            <span class="text-muted fs-2"><?= esc($user->email) ?></span>
                          </div>
                        </div>
                        <div class="d-flex align-items-center" onclick="event.stopPropagation();">
                          <div class="dropdown">
                            <button class="btn btn-sm bg-transparent border-0 text-muted shadow-none p-1" type="button" data-bs-toggle="dropdown" aria-expanded="false" data-bs-popper-config='{"strategy":"fixed"}'>
                              <i class="ti ti-dots-vertical fs-5"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                              <li>
                                <a href="<?= site_url('users/edit/' . $user->id) ?>" class="dropdown-item d-flex align-items-center gap-2">
                                  <i class="ti ti-pencil"></i> Editar
                                </a>
                              </li>
                              <?php if (auth()->user()->can('users.delete') || auth()->user()->inGroup('superadmin')): ?>
                              <li>
                                <form action="<?= site_url('users/delete/' . $user->id) ?>" method="post" data-confirm="¿Estás seguro de que deseas eliminar al usuario <?= esc($user->username) ?>?">
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
                          <span class="mobile-card-label">Rol</span>
                          <span class="mobile-card-value text-muted"><?= esc($user->groupTitle) ?></span>
                        </div>
                        <div class="mobile-card-row">
                          <span class="mobile-card-label">Último acceso</span>
                          <span class="mobile-card-value net-mono">
                            <?= esc($user->lastLoginDate) ?> <?= !empty($user->lastLoginTime) ? esc($user->lastLoginTime) : '' ?>
                          </span>
                        </div>
                        <div class="mobile-card-row">
                          <span class="mobile-card-label">Estado</span>
                          <span class="mobile-card-value">
                            <?php if ($user->active): ?>
                              <span class="badge bg-success-subtle text-success">Activo</span>
                            <?php else: ?>
                              <span class="badge bg-danger-subtle text-danger">Suspendido</span>
                            <?php endif; ?>
                          </span>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>

                <?php if (isset($pager) && $pager->getPageCount('users') > 1): ?>
                <div class="mt-4 d-flex justify-content-center">
                    <?= $pager->links('users', 'premium') ?>
                </div>
                <?php endif; ?>
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
  const searchInput = document.getElementById('search-users');
  if (searchInput) {
    searchInput.addEventListener('input', function() {
      const filter = this.value.toLowerCase().trim();
      const rows = document.querySelectorAll('#users-table tbody tr');
      const cards = document.querySelectorAll('.user-card-item');
      
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


