<!-- =====================================================================
     CABECERA Y BREADCRUMB (NAVEGACIÓN)
     ===================================================================== -->
<div class="card border shadow-none position-relative overflow-hidden mb-4">
  <div class="card-body px-4 py-3">
    <div class="row align-items-center">
      <div class="col-9">
        <h4 class="fw-semibold mb-8">Crear Nuevo Usuario</h4>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item">
              <a class="text-muted text-decoration-none" href="<?= base_url() ?>">Inicio</a>
            </li>
            <li class="breadcrumb-item">
              <a class="text-muted text-decoration-none" href="<?= base_url('users') ?>">Usuarios</a>
            </li>
            <li class="breadcrumb-item" aria-current="page">Crear</li>
          </ol>
        </nav>
      </div>
      <div class="col-3 d-flex justify-content-end align-items-center">
      </div>
    </div>
  </div>
</div>

<!-- =====================================================================
     FORMULARIO DE CREACIÓN DE USUARIO
     ===================================================================== -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">

                <form action="<?= url_to('\App\Controllers\UsersController::store') ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="username" class="form-label">Nombre de Usuario</label>
                        <input type="text" class="form-control" id="username" name="username" value="<?= old('username') ?>">
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo Electrónico</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= old('email') ?>">
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Teléfono</label>
                        <input type="tel" class="form-control" id="phone" name="phone" value="<?= old('phone') ?>">
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña</label>
                        <input type="password" class="form-control" id="password" name="password">
                    </div>
                    <div class="mb-3">
                        <label for="pass_confirm" class="form-label">Confirmar Contraseña</label>
                        <input type="password" class="form-control" id="pass_confirm" name="pass_confirm">
                    </div>
                    <div class="mb-3">
                        <label for="group" class="form-label">Rol / Grupo</label>
                        <select class="form-control" id="group" name="group">
                            <option value="">Selecciona un rol...</option>
                            <?php foreach ($groups as $key => $group): ?>
                                <option value="<?= esc($key) ?>" <?= old('group') == $key ? 'selected' : '' ?>><?= esc($group['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-6 mt-4 ms-2 form-check form-switch px-4 py-6">
                        <input class="form-check-input switch-custom-size" type="checkbox" role="switch" id="active" name="active" value="1" <?= old('active', 1) ? 'checked' : '' ?>>
                        <label class="form-check-label ms-2 pt-0 fw-semibold cursor-pointer" for="active">
                            Cuenta Activa
                        </label>
                    </div>
                    <div class="d-flex justify-content-center mt-4">
                        <a href="<?= url_to('\App\Controllers\UsersController::index') ?>" class="btn btn-danger px-4 me-2">
                            <i class="ti ti-x me-1"></i>Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="ti ti-device-floppy me-1"></i>Crear
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
