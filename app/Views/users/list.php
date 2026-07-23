<!-- =====================================================================
     CABECERA Y BREADCRUMB (NAVEGACIÓN)
     ===================================================================== -->
<div class="card border shadow-none position-relative overflow-hidden mb-4">
  <div class="card-body px-4 py-3">
    <div class="row align-items-center">
      <div class="col-12 col-md-8 text-center text-md-start">
        <h4 class="fw-semibold mb-2 mb-md-8">Gestión de Usuarios</h4>
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
                    <div class="alert alert-dark text-center py-4" role="alert">
                        <?= !empty($search) ? 'No se encontraron usuarios para tu búsqueda.' : 'No hay otros usuarios registrados en el sistema.' ?>
                    </div>
                <?php else: ?>
                <div class="table-responsive-lg">
                    <table class="table table-hover align-middle text-nowrap text-center" id="users-table">
                        <thead>
                            <tr>
                                <th class="text-start">Usuario</th>
                                <th class="text-start d-none d-md-table-cell">Email</th>
                                <th class="text-center d-none d-md-table-cell">Rol</th>
                                <th class="text-center d-none d-lg-table-cell">Último Acceso</th>
                                <th class="text-center d-none d-md-table-cell">Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                             <tr onclick="window.location='<?= site_url('users/edit/' . $user->id) ?>'" class="cursor-pointer">
                                 <td>
                                     <div class="d-flex align-items-center">
                                         <img src="<?= !empty($user->profile_pic) ? base_url('uploads/profile/' . esc($user->profile_pic)) : base_url('assets/images/profile/user-default.png') ?>" alt="Avatar" class="rounded-circle me-3 object-fit-cover" width="40" height="40" />
                                         <div class="text-start">
                                             <div class="fw-bold">
                                                 <a href="<?= site_url('users/edit/' . $user->id) ?>" class="text-reset text-primary-hover text-decoration-none">
                                                     <?= esc($user->username) ?>
                                                 </a>
                                             </div>
                                             <div class="text-muted d-md-none text-login-time"><?= esc($user->email) ?></div>
                                         </div>
                                     </div>
                                 </td>
                                <td class="text-start d-none d-md-table-cell">
                                    <?= esc($user->email) ?>
                                </td>
                                <td class="text-muted text-center d-none d-md-table-cell">
                                    <?= esc($user->groupTitle) ?>
                                </td>
                                <td class="text-center d-none d-lg-table-cell">
                                    <h6 class="fs-3 fw-semibold mb-0"><?= esc($user->lastLoginDate) ?></h6>
                                    <?php if (!empty($user->lastLoginTime)): ?>
                                        <span class="fw-normal text-muted text-login-time"><?= esc($user->lastLoginTime) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center d-none d-md-table-cell">
                                    <?php if ($user->active): ?>
                                        <span class="badge bg-primary badge-status">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger badge-status">Suspendido</span>
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
      const rows = document.querySelectorAll('table tbody tr');
      
      rows.forEach(row => {
        // En users/list.php, el nombre de usuario está en el div del primer td
        const usernameEl = row.querySelector('td:first-child h6');
        const emailEl = row.querySelector('td:nth-child(2)');
        
        const usernameText = usernameEl ? usernameEl.textContent.toLowerCase() : '';
        const emailText = emailEl ? emailEl.textContent.toLowerCase() : '';
        
        if (usernameText.includes(filter) || emailText.includes(filter)) {
          row.classList.remove('d-none');
        } else {
          row.classList.add('d-none');
        }
      });
    });
  }
});
</script>


