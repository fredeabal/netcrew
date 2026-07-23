<!-- =====================================================================
     CABECERA Y BREADCRUMB (NAVEGACIÓN)
     ===================================================================== -->
<div class="card border shadow-none position-relative overflow-hidden mb-4">
  <div class="card-body px-4 py-3">
    <div class="row align-items-center">
      <div class="col-9">
        <h4 class="fw-semibold mb-8">Mi Perfil</h4>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item">
              <a class="text-muted text-decoration-none" href="<?= base_url() ?>">Inicio</a>
            </li>
            <li class="breadcrumb-item" aria-current="page">Mi Perfil</li>
          </ol>
        </nav>
      </div>
      <div class="col-3 d-flex justify-content-end align-items-center">
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
            <div class="card-body">

        <form action="<?= base_url('profile/update') ?>" method="POST" enctype="multipart/form-data">
          <?= csrf_field() ?>

          <div class="text-center mb-4">
            <!-- Contenedor del Avatar -->
            <?php $avatarUrl = !empty($user->profile_pic) ? base_url('uploads/profile/' . $user->profile_pic) : base_url('assets/images/profile/user-default.png'); ?>
            <img src="<?= $avatarUrl ?>" class="rounded-circle border border-2 border-primary mb-3 shadow-sm object-fit-cover" width="120" height="120" alt="Avatar" id="avatar-preview" />
            
            <div class="mt-2">
              <label for="profile_pic" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
                <i class="ti ti-upload me-1"></i> Seleccionar Nueva Foto
              </label>
              <input class="form-control d-none" type="file" id="profile_pic" name="profile_pic" accept="image/*" onchange="previewImage(event)">
              <div class="form-text mt-2 opacity-75">Formatos: JPG, PNG, WEBP. Máx. 2MB.</div>
            </div>
          </div>

          <hr class="my-4 border-light-subtle opacity-25">

          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="username" class="form-label fw-semibold">Nombre de Usuario (Apodo)</label>
              <input type="text" class="form-control" id="username" name="username" value="<?= old('username', esc($user->username)) ?>">
            </div>
            
            <div class="col-md-6 mb-3">
              <label for="name" class="form-label fw-semibold">Nombre Completo</label>
              <input type="text" class="form-control" id="name" name="name" value="<?= old('name', esc($user->name ?? '')) ?>" placeholder="Ej: Juan Pérez">
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="email" class="form-label fw-semibold">Correo Electrónico</label>
              <input type="email" class="form-control" id="email" name="email" value="<?= old('email', esc($user->getIdentities()[0]->secret ?? '')) ?>">
              <div class="form-text opacity-75">Se usa para iniciar sesión.</div>
            </div>
            
            <div class="col-md-6 mb-3">
              <label for="phone" class="form-label fw-semibold">Número de Teléfono</label>
              <input type="text" class="form-control" id="phone" name="phone" value="<?= old('phone', esc($user->phone ?? '')) ?>" placeholder="Ej: 600123456">
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="password" class="form-label fw-semibold">Nueva Contraseña</label>
              <input type="password" class="form-control" id="password" name="password" placeholder="Dejar vacío si no deseas cambiarla">
            </div>
            
            <div class="col-md-6 mb-3">
              <label for="password_confirm" class="form-label fw-semibold">Confirmar Contraseña</label>
              <input type="password" class="form-control" id="password_confirm" name="password_confirm" placeholder="Repite la nueva contraseña">
            </div>
          </div>

          <div class="mb-4">
            <label for="theme" class="form-label fw-semibold">Tema (Apariencia)</label>
            <select class="form-select" id="theme" name="theme">
              <option value="system" <?= old('theme', esc($user->theme ?? 'system')) === 'system' ? 'selected' : '' ?>>Automático (Sistema)</option>
              <option value="dark" <?= old('theme', esc($user->theme ?? 'system')) === 'dark' ? 'selected' : '' ?>>Tema Oscuro</option>
              <option value="light" <?= old('theme', esc($user->theme ?? 'system')) === 'light' ? 'selected' : '' ?>>Tema Claro</option>
            </select>
          </div>

          <div class="d-flex justify-content-center mt-5">
            <a href="<?= base_url('dashboard') ?>" class="btn btn-danger px-4 me-2">Cancelar</a>
            <button type="submit" class="btn btn-primary px-4 shadow-sm">Guardar Cambios</button>
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
        output.src = reader.result;
    };
    if(event.target.files[0]) {
        reader.readAsDataURL(event.target.files[0]);
    }
}
</script>
