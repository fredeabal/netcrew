<!-- =====================================================================
     CABECERA Y BREADCRUMB (NAVEGACIÓN)
     ===================================================================== -->
<div class="card border shadow-none position-relative overflow-hidden mb-4">
  <div class="card-body px-4 py-3">
    <div class="row align-items-center">
      <div class="col-9">
        <h4 class="fw-semibold mb-8">Editar Red</h4>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item">
              <a class="text-muted text-decoration-none" href="<?= base_url() ?>">Inicio</a>
            </li>
            <li class="breadcrumb-item">
              <a class="text-muted text-decoration-none" href="<?= base_url('networks') ?>">Redes</a>
            </li>
            <li class="breadcrumb-item" aria-current="page">Editar</li>
          </ol>
        </nav>
      </div>
    </div>
  </div>
</div>

<!-- =====================================================================
     FORMULARIO DE EDICIÓN DE RED
     ===================================================================== -->
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <form action="<?= base_url('networks/update/' . $network->id) ?>" method="post">
          <?= csrf_field() ?>

          <div class="mb-3">
            <label for="name" class="form-label">Nombre de la Red</label>
            <input type="text" class="form-control" id="name" name="name" value="<?= old('name', esc($network->name)) ?>" placeholder="Ej: Red de Oficina">
          </div>

          <div class="mb-3">
            <label for="cidr" class="form-label">Rango de Red (IPv4)</label>
            <?php
               $current_cidr = old('cidr', esc($network->cidr));
               $current_ip = explode('/', $current_cidr)[0];
            ?>
            <div class="input-group">
              <input type="text" class="form-control" id="cidr" name="cidr" value="<?= $current_ip ?>" placeholder="Ej: 10.50.0.0">
              <span class="input-group-text bg-light text-muted">/24</span>
            </div>
            <div class="mt-2">
              <span class="badge bg-warning-subtle text-warning text-wrap text-start fw-normal lh-base fs-2"><i class="ti ti-alert-circle"></i> <strong>Importante:</strong> Asegúrate de que el rango no coincida con la red local de tu servidor u oficina para evitar conflictos de enrutamiento.</span>
            </div>
          </div>

          <div class="mb-3">
            <label for="dns" class="form-label">Servidores DNS</label>
            <input type="text" class="form-control" id="dns" name="dns" value="<?= old('dns', esc($network->dns ?? '1.1.1.1')) ?>" placeholder="Ej: 1.1.1.1, 8.8.8.8">
            <div class="form-text fs-2 text-muted">Puedes ingresar múltiples servidores DNS separados por comas.</div>
          </div>



          <?php if (auth()->user()->inGroup('superadmin', 'supervisor') && !empty($users)): ?>
            <div class="mb-3">
              <label for="owner_id" class="form-label">Propietario</label>
              <select class="form-select" id="owner_id" name="owner_id" required>
                <option value="">Seleccione un propietario</option>
                <?php foreach ($users as $user): ?>
                  <option value="<?= esc($user->id) ?>" <?= old('owner_id', $network->owner_id) == $user->id ? 'selected' : '' ?>>
                    <?= esc($user->username ?? $user->email) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endif; ?>


          <div class="mb-6 mt-4 ms-2 form-check form-switch px-4 py-6">
            <input class="form-check-input switch-custom-size" type="checkbox" role="switch" id="active" name="active" value="1" <?= old('active', $network->active) ? 'checked' : '' ?>>
            <label class="form-check-label ms-2 pt-0 fw-semibold cursor-pointer" for="active">
              Activar Red
            </label>
          </div>

          <div class="d-flex justify-content-center mt-4">
            <a href="<?= base_url('networks') ?>" class="btn btn-danger px-4 me-2">
              <i class="ti ti-x me-1"></i>Cancelar
            </a>
            <button type="submit" class="btn btn-primary px-4">
              <i class="ti ti-device-floppy me-1"></i>Guardar
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

