<!-- =====================================================================
     CABECERA Y BREADCRUMB (NAVEGACIÓN)
     ===================================================================== -->
<div class="card border shadow-none position-relative overflow-hidden mb-4">
  <div class="card-body px-4 py-3">
    <div class="row align-items-center">
      <div class="col-8">
        <h4 class="fw-semibold mb-1">Editar Empresa</h4>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
              <a class="text-muted text-decoration-none" href="<?= base_url() ?>">Inicio</a>
            </li>
            <li class="breadcrumb-item">
              <a class="text-muted text-decoration-none" href="<?= base_url('companies') ?>">Empresas</a>
            </li>
            <li class="breadcrumb-item text-muted" aria-current="page">Editar</li>
          </ol>
        </nav>
      </div>
      <div class="col-4 d-flex justify-content-end align-items-center">
        <a href="<?= base_url('companies') ?>" class="btn btn-sm bg-primary-subtle text-primary border-0 btn-square shadow-none" title="Volver a Empresas">
          <i class="ti ti-arrow-left fs-5"></i>
        </a>
      </div>
    </div>
  </div>
</div>

<!-- =====================================================================
     FORMULARIO DE EDICIÓN DE EMPRESA
     ===================================================================== -->
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-body p-3 p-md-4">
        <form action="<?= site_url('companies/update/' . $company->id) ?>" method="post">
          <?= csrf_field() ?>

          <div class="row g-3">
            <div class="col-12 col-md-6">
              <label for="name" class="form-label fw-semibold">Nombre de la Empresa</label>
              <input type="text" class="form-control" id="name" name="name" value="<?= esc(old('name', $company->name)) ?>" placeholder="Ej: Acme Corp" required>
            </div>
            <div class="col-12 col-md-6">
              <label for="slug" class="form-label fw-semibold">Identificador (Slug)</label>
              <input type="text" class="form-control" id="slug" name="slug" value="<?= esc(old('slug', $company->slug)) ?>" required>
              <div class="form-text fs-2 text-muted">Solo letras, números y guiones.</div>
            </div>
            <div class="col-12 col-md-6">
              <label for="max_networks" class="form-label fw-semibold">Límite de Redes</label>
              <input type="number" min="0" class="form-control" id="max_networks" name="max_networks" value="<?= esc(old('max_networks', (string) ($company->max_networks ?? 0))) ?>">
              <div class="form-text fs-2 text-muted">0 = sin límite.</div>
            </div>
            <div class="col-12 col-md-6">
              <label for="max_devices" class="form-label fw-semibold">Límite de Dispositivos</label>
              <input type="number" min="0" class="form-control" id="max_devices" name="max_devices" value="<?= esc(old('max_devices', (string) ($company->max_devices ?? 0))) ?>">
              <div class="form-text fs-2 text-muted">0 = sin límite.</div>
            </div>
          </div>

          <div class="mt-4">
            <div class="form-check form-switch p-0 d-flex align-items-center gap-2">
              <input class="form-check-input switch-custom-size ms-0" type="checkbox" role="switch" id="active" name="active" value="1" <?= old('active', $company->active) ? 'checked' : '' ?>>
              <label class="form-check-label fw-semibold cursor-pointer mb-0" for="active">
                Empresa Activa
              </label>
            </div>
          </div>

          <div class="mt-4 pt-3 border-top">
            <div class="row g-2 justify-content-center align-items-center">
              <div class="col-6 col-sm-auto">
                <a href="<?= base_url('companies') ?>" class="btn btn-dark btn-sm w-100 px-sm-4 py-2 text-nowrap d-flex align-items-center justify-content-center">
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
