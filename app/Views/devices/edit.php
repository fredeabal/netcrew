<!-- =====================================================================
     CABECERA Y BREADCRUMB (NAVEGACIÓN)
     ===================================================================== -->
<div class="card border shadow-none position-relative overflow-hidden mb-4">
  <div class="card-body px-4 py-3">
    <div class="row align-items-center">
      <div class="col-12 col-md-8 text-center text-md-start">
        <h4 class="fw-semibold mb-2 mb-md-0">Editar Nodo: <?= esc($device->name) ?></h4>
        <nav aria-label="breadcrumb" class="d-flex justify-content-center justify-content-md-start">
          <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
              <a class="text-muted text-decoration-none" href="<?= site_url() ?>">Inicio</a>
            </li>
            <li class="breadcrumb-item">
              <a class="text-muted text-decoration-none" href="<?= site_url('networks') ?>">Redes</a>
            </li>
            <li class="breadcrumb-item">
              <a class="text-muted text-decoration-none" href="<?= site_url('networks/' . $network->id . '/devices') ?>"><?= esc($network->name) ?></a>
            </li>
            <li class="breadcrumb-item">
              <a class="text-muted text-decoration-none" href="<?= site_url('devices/show/' . $device->id) ?>"><?= esc($device->name) ?></a>
            </li>
            <li class="breadcrumb-item active" aria-current="page">Editar</li>
          </ol>
        </nav>
      </div>
      <div class="col-12 col-md-4 d-flex justify-content-center justify-content-md-end mt-3 mt-md-0">
        <a href="<?= site_url('devices/show/' . $device->id) ?>" class="btn bg-primary-subtle text-primary border-0 btn-square shadow-none" title="Volver a Detalles">
          <i class="ti ti-arrow-left fs-5"></i>
        </a>
      </div>
    </div>
  </div>
</div>

<!-- =====================================================================
     FORMULARIO DE EDICIÓN DE NODO
     ===================================================================== -->
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <form action="<?= site_url('devices/update/' . $device->id) ?>" method="post">
          <?= csrf_field() ?>

          <div class="mb-3">
            <label for="name" class="form-label">Nombre del Dispositivo</label>
            <input type="text" class="form-control" id="name" name="name" value="<?= old('name', esc($device->name)) ?>" required minlength="2" maxlength="100">
          </div>

          <div class="mb-3">
            <label for="device_type" class="form-label">Tipo de Dispositivo</label>
            <?php $currentType = old('device_type', $device->device_type ?? 'pc'); ?>
            <select class="select2 form-control custom-select" id="device_type" name="device_type">
              <option value="pc" <?= $currentType === 'pc' ? 'selected' : '' ?>>Computadora / Laptop (PC)</option>
              <option value="server" <?= $currentType === 'server' ? 'selected' : '' ?>>Servidor</option>
              <option value="mobile" <?= $currentType === 'mobile' ? 'selected' : '' ?>>Teléfono</option>
              <option value="tablet" <?= $currentType === 'tablet' ? 'selected' : '' ?>>Tablet</option>
              <option value="router" <?= $currentType === 'router' ? 'selected' : '' ?>>Router / Firewall</option>
            </select>
          </div>

          <div class="mb-3">
            <label for="routing_mode" class="form-label">Modo de Enrutamiento (Túnel)</label>
            <?php $currentRouting = old('routing_mode', $device->routing_mode ?? 'split'); ?>
            <select class="select2 form-control custom-select" id="routing_mode" name="routing_mode">
              <option value="split" <?= $currentRouting === 'split' ? 'selected' : '' ?>>Split-Tunnel (Solo red VPN local)</option>
              <option value="full" <?= $currentRouting === 'full' ? 'selected' : '' ?>>Full-Tunnel (Todo el tráfico / Salida a Internet por VPN - 0.0.0.0/0)</option>
            </select>
            <small class="text-muted d-block mt-1">Si cambias el modo, el usuario deberá descargar o escanear la nueva configuración.</small>
          </div>

          <div class="mb-3">
            <label for="expiration" class="form-label">
              Expiración del Acceso
              <?php if (!empty($device->expires_at)): ?>
                <?php $isExpired = strtotime($device->expires_at) < time(); ?>
                <span class="ms-1 small <?= $isExpired ? 'text-danger' : 'text-warning' ?>">
                  (<?= $isExpired ? 'Expirado: ' : 'Vence: ' ?><?= date('d/m/Y H:i', strtotime($device->expires_at)) ?>)
                </span>
              <?php else: ?>
                <span class="ms-1 small text-primary">(Permanente)</span>
              <?php endif; ?>
            </label>
            <select class="select2 form-control custom-select" id="expiration" name="expiration">
              <option value="keep" selected>Mantener actual (<?= !empty($device->expires_at) ? date('d/m/Y H:i', strtotime($device->expires_at)) : 'Permanente' ?>)</option>
              <option value="never">Permanente (Sin expiración)</option>
              <option value="1d">Extender 1 Día (24 Horas)</option>
              <option value="7d">Extender 7 Días (1 Semana)</option>
              <option value="30d">Extender 30 Días (1 Mes)</option>
            </select>
          </div>

          <div class="d-flex justify-content-center gap-2 mt-4">
            <a href="<?= site_url('devices/show/' . $device->id) ?>" class="btn btn-danger px-4">
              <i class="ti ti-x me-1"></i>Cancelar
            </a>
            <button type="submit" class="btn btn-primary px-4">
              <i class="ti ti-device-floppy me-1"></i>Guardar Cambios
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
