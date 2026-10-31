<!-- =====================================================================
     CABECERA Y BREADCRUMB (NAVEGACIÓN)
     ===================================================================== -->
<div class="card border shadow-none position-relative overflow-hidden mb-4">
  <div class="card-body px-4 py-3">
    <div class="row align-items-center">
      <div class="col-12 col-md-8 text-center text-md-start">
        <h4 class="fw-semibold mb-2 mb-md-0">Nuevo Nodo</h4>
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
            <li class="breadcrumb-item active" aria-current="page">Nuevo Nodo</li>
          </ol>
        </nav>
      </div>
      <div class="col-12 col-md-4 d-flex justify-content-center justify-content-md-end mt-3 mt-md-0">
        <a href="<?= site_url('networks/' . $network->id . '/devices') ?>" class="btn bg-primary-subtle text-primary border-0 btn-square shadow-none" title="Volver a la red">
          <i class="ti ti-arrow-left fs-5"></i>
        </a>
      </div>
    </div>
  </div>
</div>

<!-- =====================================================================
     FORMULARIO DE CREACIÓN DE NODO
     ===================================================================== -->
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <form action="<?= site_url('devices/store') ?>" method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="network_id" value="<?= $network->id ?>">

          <div class="mb-3">
            <label for="name" class="form-label">Nombre del Dispositivo</label>
            <input type="text" class="form-control" id="name" name="name" value="<?= old('name') ?>" placeholder="Ej: Laptop Oficina" required minlength="2" maxlength="100">
            <small class="text-muted d-block mt-1">Se generará automáticamente su IP privada y el par de llaves WireGuard.</small>
          </div>

          <div class="mb-3">
            <label for="device_type" class="form-label">Tipo de Dispositivo</label>
            <select class="select2 form-control custom-select" id="device_type" name="device_type">
              <option value="pc" <?= old('device_type') === 'pc' ? 'selected' : '' ?>>Computadora / Laptop (PC)</option>
              <option value="server" <?= old('device_type') === 'server' ? 'selected' : '' ?>>Servidor</option>
              <option value="mobile" <?= old('device_type') === 'mobile' ? 'selected' : '' ?>>Teléfono</option>
              <option value="tablet" <?= old('device_type') === 'tablet' ? 'selected' : '' ?>>Tablet</option>
              <option value="router" <?= old('device_type') === 'router' ? 'selected' : '' ?>>Router / Firewall</option>
            </select>
          </div>

          <div class="mb-3">
            <label for="routing_mode" class="form-label">Modo de Enrutamiento (Túnel)</label>
            <select class="select2 form-control custom-select" id="routing_mode" name="routing_mode">
              <option value="split" <?= old('routing_mode') === 'split' ? 'selected' : '' ?>>Split-Tunnel (Solo red VPN local)</option>
              <option value="full" <?= old('routing_mode') === 'full' ? 'selected' : '' ?>>Full-Tunnel (Todo el tráfico de Internet por VPN - 0.0.0.0/0)</option>
            </select>
            <small class="text-muted d-block mt-1">Split-Tunnel permite navegar por tu Internet habitual y solo usar la VPN para la red interna. Full-Tunnel cifra todo el tráfico.</small>
          </div>

          <div class="mb-3">
            <label for="expiration" class="form-label">Expiración del Acceso</label>
            <select class="select2 form-control custom-select" id="expiration" name="expiration">
              <option value="never" <?= old('expiration') === 'never' ? 'selected' : '' ?>>Permanente (Sin expiración)</option>
              <option value="1d" <?= old('expiration') === '1d' ? 'selected' : '' ?>>1 Día (24 Horas)</option>
              <option value="7d" <?= old('expiration') === '7d' ? 'selected' : '' ?>>7 Días (1 Semana)</option>
              <option value="30d" <?= old('expiration') === '30d' ? 'selected' : '' ?>>30 Días (1 Mes)</option>
            </select>
            <small class="text-muted d-block mt-1">Al expirar, el nodo se desactivará automáticamente y perderá acceso.</small>
          </div>

          <div class="d-flex justify-content-center gap-2 mt-4">
            <a href="<?= site_url('networks/' . $network->id . '/devices') ?>" class="btn btn-danger px-4">
              <i class="ti ti-x me-1"></i>Cancelar
            </a>
            <button type="submit" class="btn btn-primary px-4">
              <i class="ti ti-device-floppy me-1"></i>Generar Nodo
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
