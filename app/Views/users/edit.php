<!-- =====================================================================
     CABECERA Y BREADCRUMB (NAVEGACIÓN)
     ===================================================================== -->
<div class="card border shadow-none position-relative overflow-hidden mb-4">
  <div class="card-body px-4 py-3">
    <div class="row align-items-center">
      <div class="col-8">
        <h4 class="fw-semibold mb-2 mb-md-8">Editar Usuario</h4>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
              <a class="text-muted text-decoration-none" href="<?= base_url() ?>">Inicio</a>
            </li>
            <li class="breadcrumb-item">
              <a class="text-muted text-decoration-none" href="<?= base_url('users') ?>">Usuarios</a>
            </li>
            <li class="breadcrumb-item" aria-current="page">Editar</li>
          </ol>
        </nav>
      </div>
      <div class="col-4 d-flex justify-content-end align-items-center">
        <a href="<?= base_url('users') ?>" class="btn btn-outline-secondary d-flex align-items-center gap-1">
          <i class="ti ti-arrow-left"></i>
          <span class="d-none d-sm-inline">Volver</span>
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
            <div class="card-body p-4">

                <form action="<?= url_to('\App\Controllers\UsersController::update', $user->id) ?>" method="post">
                    <?= csrf_field() ?>

                    <!-- 1. Información del Usuario -->
                    <h6 class="fw-semibold text-primary mb-3 border-bottom pb-2">
                        <i class="ti ti-user me-1"></i> Información del Usuario
                    </h6>

                    <div class="row mb-3">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label for="username" class="form-label fw-semibold">Nombre de Usuario</label>
                            <input type="text" class="form-control" id="username" name="username" value="<?= old('username', esc($user->username)) ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label fw-semibold">Correo Electrónico</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= old('email', esc($user->email)) ?>">
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label for="group" class="form-label fw-semibold">Rol / Grupo</label>
                            <select class="form-select" id="group" name="group">
                                <option value="">Selecciona un rol...</option>
                                <?php 
                                    $userGroup = !empty($user->getGroups()) ? $user->getGroups()[0] : '';
                                ?>
                                <?php foreach ($groups as $key => $group): ?>
                                    <option value="<?= esc($key) ?>" <?= old('group', $userGroup) == $key ? 'selected' : '' ?>><?= esc($group['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label fw-semibold">Teléfono</label>
                            <input type="tel" class="form-control" id="phone" name="phone" placeholder="Ej. +34 600 000 000" value="<?= old('phone', esc($user->phone ?? '')) ?>">
                        </div>
                    </div>

                    <!-- 2. Seguridad y Acceso -->
                    <h6 class="fw-semibold text-primary mb-3 border-bottom pb-2 mt-4">
                        <i class="ti ti-lock me-1"></i> Seguridad y Acceso
                    </h6>

                    <div class="row mb-4">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label for="password" class="form-label fw-semibold">Nueva Contraseña</label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Dejar en blanco para conservar actual">
                            <small class="text-muted fs-2">Opcional. Mínimo 8 caracteres.</small>
                        </div>
                        <div class="col-md-6">
                            <label for="pass_confirm" class="form-label fw-semibold">Confirmar Nueva Contraseña</label>
                            <input type="password" class="form-control" id="pass_confirm" name="pass_confirm" placeholder="Repite la nueva contraseña">
                        </div>
                    </div>

                    <!-- 3. Estado de la Cuenta -->
                    <h6 class="fw-semibold text-primary mb-3 border-bottom pb-2 mt-4">
                        <i class="ti ti-toggle-right me-1"></i> Estado de la Cuenta
                    </h6>

                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="form-check form-switch p-0 d-flex align-items-center gap-2">
                                <input class="form-check-input switch-custom-size ms-0" type="checkbox" role="switch" id="active" name="active" value="1" <?= old('active', !$user->isBanned()) ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold cursor-pointer mb-0" for="active">
                                    Cuenta Activa
                                </label>
                                <span class="text-muted small ms-2 d-none d-sm-inline">(Desactivar la cuenta impedirá que el usuario inicie sesión)</span>
                            </div>
                        </div>
                    </div>

                    <!-- Acciones -->
                    <div class="d-flex justify-content-center align-items-center gap-3 mt-4 pt-3 border-top">
                        <a href="<?= url_to('\App\Controllers\UsersController::index') ?>" class="btn btn-dark px-4">
                            <i class="ti ti-x me-1"></i>Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="ti ti-device-floppy me-1"></i>Modificar Usuario
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
