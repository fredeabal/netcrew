<!-- =====================================================================
     CABECERA Y BREADCRUMB (NAVEGACIÓN)
     ===================================================================== -->
<div class="card border shadow-none position-relative overflow-hidden mb-4">
  <div class="card-body px-4 py-3">
    <div class="row align-items-center">
      <div class="col-12 col-md-8 text-center text-md-start">
        <h4 class="fw-semibold mb-2 mb-md-8">Roles y Permisos</h4>
        <nav aria-label="breadcrumb" class="d-flex justify-content-center justify-content-md-start">
          <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
              <a class="text-muted text-decoration-none" href="<?= base_url() ?>">Inicio</a>
            </li>
            <li class="breadcrumb-item" aria-current="page">Roles</li>
          </ol>
        </nav>
      </div>
      <div class="col-12 col-md-4 d-flex justify-content-center justify-content-md-end align-items-center mt-3 mt-md-0">
      </div>
    </div>
  </div>
</div>

<!-- =====================================================================
     LISTADO DE ROLES
     ===================================================================== -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap" id="roles-table">
                        <thead>
                            <tr>
                                <th class="text-start">Rol</th>
                                <th class="text-start d-none d-md-table-cell">Descripción</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($groups as $key => $group): ?>
                            <tr onclick="window.location='<?= base_url('roles/edit/' . esc($key)) ?>'" class="cursor-pointer">
                                <td class="text-start">
                                    <div class="fw-bold text-start text-primary-hover"><?= esc($group['title']) ?></div>
                                </td>
                                <td class="text-start d-none d-md-table-cell">
                                    <?= esc($group['description']) ?>
                                </td>
                                <td class="text-end" onclick="event.stopPropagation();">
                                    <div class="dropdown">
                                        <button class="btn btn-sm bg-transparent border-0 text-muted shadow-none p-1" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="ti ti-dots-vertical fs-5"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <a href="<?= base_url('roles/edit/' . esc($key)) ?>" class="dropdown-item d-flex align-items-center gap-2">
                                                    <i class="ti ti-edit"></i> Gestionar Permisos
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
