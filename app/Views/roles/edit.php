<div class="container-fluid">
    <!-- =====================================================================
         CABECERA Y BREADCRUMB (NAVEGACIÓN)
         ===================================================================== -->
    <div class="card shadow-none border position-relative overflow-hidden mb-4">
        <div class="card-body px-4 py-3">
            <div class="row align-items-center">
                <div class="col-9">
                    <h4 class="fw-semibold mb-8">Editar Permisos</h4>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item">
                                <a class="text-muted text-decoration-none" href="<?= base_url() ?>">Inicio</a>
                            </li>
                            <li class="breadcrumb-item">
                                <a class="text-muted text-decoration-none" href="<?= base_url('roles') ?>">Roles</a>
                            </li>
                            <li class="breadcrumb-item" aria-current="page">Editar Permisos</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- =====================================================================
         FORMULARIO DE PERMISOS
         ===================================================================== -->
    <div class="row">
        <div class="col-12">
            <form action="<?= base_url('roles/update/' . esc($groupName)) ?>" method="POST">
                <?= csrf_field() ?>

                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title fw-semibold mb-4">Rol: <?= esc($groupInfo['title']) ?></h5>
                        <p class="text-muted mb-4"><?= esc($groupInfo['description']) ?></p>

                        <!-- Listado de permisos agrupados -->
                        <?php foreach ($groupedPermissions as $categoryName => $perms): ?>
                            <div class="mb-5 border-bottom pb-4">
                                <h6 class="fw-bold mb-3 text-primary"><?= esc($categoryName) ?></h6>
                                <div class="row">
                                    <?php foreach ($perms as $perm): ?>
                                        <div class="col-md-6 col-lg-3 mb-3">
                                            <div class="form-check form-switch px-4 py-2 border rounded h-100">
                                                <input class="form-check-input ms-0 me-3 switch-custom-size" 
                                                       type="checkbox" 
                                                       role="switch" 
                                                       name="permissions[]" 
                                                       id="perm_<?= esc(str_replace('.', '_', $perm['key'])) ?>" 
                                                       value="<?= esc($perm['key']) ?>" 
                                                       <?= $perm['checked'] ? 'checked' : '' ?>>
                                                <label class="form-check-label fw-medium cursor-pointer" 
                                                       for="perm_<?= esc(str_replace('.', '_', $perm['key'])) ?>">
                                                    <?= esc($perm['friendly']) ?>
                                                </label>
                                                <div class="fs-2 text-muted ms-5 mt-1"><?= esc($perm['description']) ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- Acciones -->
                        <div class="d-flex justify-content-center mt-5">
                            <a href="<?= base_url('roles') ?>" class="btn btn-danger px-4 me-2">
                                <i class="ti ti-x me-1"></i>Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="ti ti-device-floppy me-1"></i>Guardar
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
