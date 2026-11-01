<!-- =====================================================================
     CABECERA Y BREADCRUMB (NAVEGACIÓN)
     ===================================================================== -->
<div class="card border shadow-none position-relative overflow-hidden mb-4">
  <div class="card-body px-4 py-3">
    <div class="row align-items-center">
      <div class="col-8">
        <h4 class="fw-semibold mb-1">Mi Perfil</h4>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
              <a class="text-muted text-decoration-none" href="<?= base_url() ?>">Inicio</a>
            </li>
            <li class="breadcrumb-item text-muted" aria-current="page">Mi Perfil</li>
          </ol>
        </nav>
      </div>
      <div class="col-4 d-flex justify-content-end align-items-center">
        <a href="<?= base_url('dashboard') ?>" class="btn btn-sm bg-primary-subtle text-primary border-0 btn-square shadow-none" title="Volver al panel">
          <i class="ti ti-arrow-left fs-5"></i>
        </a>
      </div>
    </div>
  </div>
</div>

<!-- =====================================================================
     FORMULARIO DE PERFIL DE USUARIO
     ===================================================================== -->
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-body p-3 p-md-4">

        <form action="<?= base_url('profile/update') ?>" method="POST" enctype="multipart/form-data">
          <?= csrf_field() ?>

          <!-- FOTO DE PERFIL / AVATAR -->
          <div class="text-center mb-4 pb-4 border-bottom">
            <div class="position-relative d-inline-block mb-3">
              <?php $avatarUrl = !empty($user->profile_pic) ? base_url('uploads/profile/' . $user->profile_pic) : base_url('assets/images/profile/user-default.png'); ?>
              <img src="<?= $avatarUrl ?>" class="rounded-circle border border-2 border-primary-subtle object-fit-cover shadow-sm" width="100" height="100" alt="Avatar" id="avatar-preview" />
            </div>
            <div>
              <label for="profile_pic" class="btn btn-primary btn-sm px-3 shadow-sm cursor-pointer">
                <i class="ti ti-upload me-1"></i> Seleccionar Nueva Foto
              </label>
              <input class="form-control d-none" type="file" id="profile_pic" name="profile_pic" accept="image/*" onchange="previewImage(event)">
              <div class="form-text text-muted fs-2 mt-2">Formatos recomendados: JPG, PNG o WEBP. Máx. 2MB.</div>
            </div>
          </div>

          <!-- SECCIÓN 1: INFORMACIÓN PERSONAL -->
          <div class="mb-4">
            <div class="d-flex align-items-center gap-2 mb-3">
              <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center network-icon-circle flex-shrink-0">
                <i class="ti ti-user fs-5"></i>
              </div>
              <div>
                <h6 class="fw-semibold mb-0">Información personal</h6>
              </div>
            </div>

            <div class="row g-3">
              <div class="col-12 col-md-6">
                <label for="username" class="form-label fw-semibold">Nombre de Usuario</label>
                <div class="input-group">
                  <span class="input-group-text bg-transparent text-muted"><i class="ti ti-user"></i></span>
                  <input type="text" class="form-control" id="username" name="username" value="<?= old('username', esc($user->username)) ?>" required>
                </div>
              </div>

              <div class="col-12 col-md-6">
                <label for="name" class="form-label fw-semibold">Nombre Completo</label>
                <div class="input-group">
                  <span class="input-group-text bg-transparent text-muted"><i class="ti ti-id"></i></span>
                  <input type="text" class="form-control" id="name" name="name" value="<?= old('name', esc($user->name ?? '')) ?>" placeholder="Ej: Juan Pérez">
                </div>
              </div>

              <div class="col-12 col-md-6">
                <label for="email" class="form-label fw-semibold">Correo Electrónico</label>
                <div class="input-group">
                  <span class="input-group-text bg-transparent text-muted"><i class="ti ti-mail"></i></span>
                  <input type="email" class="form-control" id="email" name="email" value="<?= old('email', esc($user->getIdentities()[0]->secret ?? '')) ?>" required>
                </div>
              </div>

              <div class="col-12 col-md-6">
                <label for="phone" class="form-label fw-semibold">Teléfono</label>
                <div class="input-group">
                  <span class="input-group-text bg-transparent text-muted"><i class="ti ti-phone"></i></span>
                  <input type="tel" class="form-control" id="phone" name="phone" value="<?= old('phone', esc($user->phone ?? '')) ?>" placeholder="+34 600 123 456">
                </div>
                <div class="form-text text-muted fs-2">Incluye prefijo de país (ej. +34).</div>
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
                <label for="password_confirm" class="form-label fw-semibold">Confirmar Nueva Contraseña</label>
                <div class="input-group">
                  <span class="input-group-text bg-transparent text-muted"><i class="ti ti-lock-check"></i></span>
                  <input type="password" class="form-control" id="password_confirm" name="password_confirm" placeholder="Repite la contraseña">
                </div>
              </div>
            </div>
          </div>

          <!-- SECCIÓN 3: APARIENCIA Y PREFERENCIAS -->
          <div class="mb-4">
            <div class="d-flex align-items-center gap-2 mb-3">
              <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center network-icon-circle flex-shrink-0">
                <i class="ti ti-palette fs-5"></i>
              </div>
              <div>
                <h6 class="fw-semibold mb-0">Apariencia y tema</h6>
              </div>
            </div>

            <div class="row g-3">
              <div class="col-12 col-md-6">
                <label for="theme" class="form-label fw-semibold">Preferencia de Tema</label>
                <select class="select2 form-control custom-select" id="theme" name="theme">
                  <option value="system" <?= old('theme', esc($user->theme ?? 'system')) === 'system' ? 'selected' : '' ?>>Automático (preferencia del sistema)</option>
                  <option value="dark" <?= old('theme', esc($user->theme ?? 'system')) === 'dark' ? 'selected' : '' ?>>Modo oscuro</option>
                  <option value="light" <?= old('theme', esc($user->theme ?? 'system')) === 'light' ? 'selected' : '' ?>>Modo claro</option>
                </select>
              </div>
            </div>
          </div>

          <!-- BOTONERA DE ACCIONES: RESPONSIVE Y CENTRADOS -->
          <div class="mt-4 pt-3 border-top">
            <div class="row g-2 justify-content-center align-items-center">
              <div class="col-6 col-sm-auto">
                <a href="<?= base_url('dashboard') ?>" class="btn btn-dark btn-sm w-100 px-sm-4 py-2 text-nowrap d-flex align-items-center justify-content-center">
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

<!-- =====================================================================
     SCRIPT DE PREVISUALIZACIÓN DE IMAGEN
     ===================================================================== -->
<script>
function previewImage(event) {
  const reader = new FileReader();
  reader.onload = function(){
    const output = document.getElementById('avatar-preview');
    if (output) {
      output.src = reader.result;
    }
  };
  if(event.target.files && event.target.files[0]) {
    reader.readAsDataURL(event.target.files[0]);
  }
}
</script>
