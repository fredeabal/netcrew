    <!-- =====================================================================
         CABECERA Y BREADCRUMB (NAVEGACIÓN)
         ===================================================================== -->
    <div class="card shadow-none border position-relative overflow-hidden mb-4">
        <div class="card-body px-4 py-3">
            <div class="row align-items-center">
                <div class="col-8">
                    <h4 class="fw-semibold mb-1">Editar Permisos</h4>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a class="text-muted text-decoration-none" href="<?= base_url() ?>">Inicio</a>
                            </li>
                            <li class="breadcrumb-item">
                                <a class="text-muted text-decoration-none" href="<?= base_url('roles') ?>">Roles</a>
                            </li>
                            <li class="breadcrumb-item text-muted" aria-current="page">Editar Permisos</li>
                        </ol>
                    </nav>
                </div>
                <div class="col-4 d-flex justify-content-end align-items-center">
                    <a href="<?= base_url('roles') ?>" class="btn btn-sm bg-primary-subtle text-primary border-0 btn-square shadow-none" title="Volver a Roles">
                        <i class="ti ti-arrow-left"></i>
                    </a>
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
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center network-icon-circle flex-shrink-0">
                                <i class="ti ti-shield fs-6"></i>
                            </div>
                            <div>
                                <h5 class="fw-semibold mb-0"><?= esc($groupInfo['title']) ?></h5>
                                <span class="net-mono text-muted fs-2"><?= esc($groupName) ?></span>
                            </div>
                        </div>
                        <p class="text-muted mb-4"><?= esc($groupInfo['description']) ?></p>

                        <!-- Listado de permisos agrupados -->
                        <?php foreach ($groupedPermissions as $categoryName => $perms): ?>
                            <div class="mb-5 border-bottom pb-4">
                                <h6 class="fw-semibold mb-3"><?= esc($categoryName) ?></h6>
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
                                                       <?= $perm['checked'] ? 'checked' : '' ?>
                                                       <?= $groupName === 'superadmin' ? 'disabled' : '' ?>>
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
                        <div class="mt-4 pt-3 border-top">
                            <div class="row g-2 justify-content-center align-items-center">
                                <div class="col-6 col-sm-auto">
                                    <a href="<?= base_url('roles') ?>" class="btn btn-dark btn-sm w-100 px-sm-4 py-2 text-nowrap d-flex align-items-center justify-content-center">
                                        <i class="ti ti-x me-1"></i>Cancelar
                                    </a>
                                </div>
                                <div class="col-6 col-sm-auto">
                                    <button type="submit" class="btn btn-primary btn-sm w-100 px-sm-4 py-2 text-nowrap d-flex align-items-center justify-content-center">
                                        <i class="ti ti-device-floppy me-1"></i>Guardar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
