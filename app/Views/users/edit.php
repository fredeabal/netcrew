<!-- =====================================================================
     CABECERA Y BREADCRUMB (NAVEGACIÓN)
     ===================================================================== -->
<div class="card border shadow-none position-relative overflow-hidden mb-4">
  <div class="card-body px-4 py-3">
    <div class="row align-items-center">
      <div class="col-8">
        <h4 class="fw-semibold mb-1">Editar Usuario</h4>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
              <a class="text-muted text-decoration-none" href="<?= base_url() ?>">Inicio</a>
            </li>
            <li class="breadcrumb-item">
              <a class="text-muted text-decoration-none" href="<?= base_url('users') ?>">Usuarios</a>
            </li>
            <li class="breadcrumb-item text-muted" aria-current="page">Editar</li>
          </ol>
        </nav>
      </div>
      <div class="col-4 d-flex justify-content-end align-items-center">
        <a href="<?= base_url('users') ?>" class="btn btn-sm bg-primary-subtle text-primary border-0 btn-square shadow-none" title="Volver al listado">
          <i class="ti ti-arrow-left fs-5"></i>
        </a>
      </div>
    </div>
  </div>
</div>

<!-- =====================================================================
     FORMULARIO DE EDICIÓN DE USUARIO
     ===================================================================== -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body p-3 p-md-4">

                <form action="<?= url_to('\App\Controllers\UsersController::update', $user->id) ?>" method="post">
                    <?= csrf_field() ?>

                    <!-- SECCIÓN 1: INFORMACIÓN DEL USUARIO -->
                    <div class="mb-4">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center network-icon-circle flex-shrink-0">
                                <i class="ti ti-user fs-5"></i>
                            </div>
                            <div>
                                <h6 class="fw-semibold mb-0">Información del usuario</h6>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="username" class="form-label fw-semibold">Nombre de Usuario</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-transparent text-muted"><i class="ti ti-user"></i></span>
                                    <input type="text" class="form-control" id="username" name="username" value="<?= old('username', esc($user->username)) ?>">
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="email" class="form-label fw-semibold">Correo Electrónico</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-transparent text-muted"><i class="ti ti-mail"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" value="<?= old('email', esc($user->email)) ?>">
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="group" class="form-label fw-semibold">Rol / Grupo</label>
                                <?php 
                                    $userGroup = !empty($user->getGroups()) ? $user->getGroups()[0] : '';
                                ?>
                                <?php if (!empty($isSelf)): ?>
                                    <select class="form-select" id="group" disabled>
                                        <?php foreach ($groups as $key => $group): ?>
                                            <option value="<?= esc($key) ?>" selected><?= esc($group['title']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="group" value="<?= esc($userGroup) ?>">
                                    <small class="text-muted fs-2">Tu propio rol no puede ser modificado.</small>
                                <?php else: ?>
                                    <select class="select2 form-select" id="group" name="group">
                                        <option value="">Selecciona un rol...</option>
                                        <?php foreach ($groups as $key => $group): ?>
                                            <option value="<?= esc($key) ?>" <?= old('group', $userGroup) == $key ? 'selected' : '' ?>><?= esc($group['title']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="phone" class="form-label fw-semibold">Teléfono</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-transparent text-muted"><i class="ti ti-phone"></i></span>
                                    <input type="tel" class="form-control" id="phone" name="phone" placeholder="Ej. +34 600 000 000" value="<?= old('phone', esc($user->phone ?? '')) ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN 2: SEGURIDAD Y ACCESO -->
                    <div class="mb-4">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center network-icon-circle flex-shrink-0">
                                <i class="ti ti-shield-lock fs-5"></i>
                            </div>
                            <div>
                                <h6 class="fw-semibold mb-0">Seguridad y acceso</h6>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="password" class="form-label fw-semibold">Nueva Contraseña</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-transparent text-muted"><i class="ti ti-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" placeholder="Dejar en blanco para conservar actual">
                                </div>
                                <div class="form-text text-muted fs-2">Opcional. Mínimo 8 caracteres.</div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="pass_confirm" class="form-label fw-semibold">Confirmar Nueva Contraseña</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-transparent text-muted"><i class="ti ti-lock-check"></i></span>
                                    <input type="password" class="form-control" id="pass_confirm" name="pass_confirm" placeholder="Repite la nueva contraseña">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN 3: ESTADO DE LA CUENTA -->
                    <div class="mb-4">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center network-icon-circle flex-shrink-0">
                                <i class="ti ti-toggle-right fs-5"></i>
                            </div>
                            <div>
                                <h6 class="fw-semibold mb-0">Estado de la cuenta</h6>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="form-check form-switch p-0 d-flex align-items-center gap-2">
                                    <?php if (!empty($isSelf)): ?>
                                        <input class="form-check-input switch-custom-size ms-0" type="checkbox" role="switch" id="active" checked disabled>
                                        <input type="hidden" name="active" value="1">
                                        <label class="form-check-label fw-semibold mb-0" for="active">
                                            Cuenta Activa
                                        </label>
                                        <span class="text-muted small ms-2 d-none d-sm-inline">(No puedes desactivar tu propia cuenta activa)</span>
                                    <?php else: ?>
                                        <input class="form-check-input switch-custom-size ms-0" type="checkbox" role="switch" id="active" name="active" value="1" <?= old('active', !$user->isBanned()) ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-semibold cursor-pointer mb-0" for="active">
                                            Cuenta Activa
                                        </label>
                                        <span class="text-muted small ms-2 d-none d-sm-inline">(Desactivar la cuenta impedirá que el usuario inicie sesión)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- BOTONERA DE ACCIONES: RESPONSIVE Y CENTRADOS -->
                    <div class="mt-4 pt-3 border-top">
                        <div class="row g-2 justify-content-center align-items-center">
                            <div class="col-6 col-sm-auto">
                                <a href="<?= url_to('\App\Controllers\UsersController::index') ?>" class="btn btn-dark btn-sm w-100 px-sm-4 py-2 text-nowrap d-flex align-items-center justify-content-center">
                                    <i class="ti ti-x me-1"></i>Cancelar
                                </a>
                            </div>
                            <div class="col-6 col-sm-auto">
                                <button type="submit" class="btn btn-primary btn-sm w-100 px-sm-4 py-2 text-nowrap d-flex align-items-center justify-content-center">
                                    <i class="ti ti-device-floppy me-1"></i>Guardar Cambios
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
