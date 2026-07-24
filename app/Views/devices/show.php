<?php
// ---------------------------------------------------------------------
// Etiquetas legibles para los tipos de dispositivos
// ---------------------------------------------------------------------
$typeLabels = [
    'pc'     => 'Computadora / Laptop',
    'server' => 'Servidor',
    'mobile' => 'Teléfono',
    'tablet' => 'Tablet',
    'router' => 'Router / Firewall'
];
// ---------------------------------------------------------------------
// Iconos asociados a cada tipo de dispositivo (se usan en la vista)
// ---------------------------------------------------------------------
$typeIcons  = [
    'pc'     => 'ti-device-laptop',
    'server' => 'ti-server',
    'mobile' => 'ti-device-mobile',
    'tablet' => 'ti-device-tablet',
    'router' => 'ti-router'
];

// ---------------------------------------------------------------------
// Asignación de valores por defecto y verificación de estado
// ---------------------------------------------------------------------
$deviceType  = $device->device_type ?? 'pc';
$wgConnected = $wg && $wg['connected'];

// ---------------------------------------------------------------------
// Función auxiliar (helper) para formatear bytes a un formato legible
// ---------------------------------------------------------------------
$formatBytesView = static function (int $bytes): string {
    if ($bytes <= 0) return '0 B';
    $sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = (int)floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 1) . ' ' . $sizes[$i];
};

// ---------------------------------------------------------------------
// Función auxiliar (helper) para mostrar el tiempo transcurrido "Hace X..."
// ---------------------------------------------------------------------
$timeAgoView = static function (?int $ts, int $ref): string {
    if (!$ts) return '—';
    $sec = $ref - $ts;
    if ($sec < 10)  return 'Hace instantes';
    if ($sec < 60)  return "Hace {$sec} seg";
    $m = intdiv($sec, 60);
    if ($m < 60)    return "Hace {$m} min";
    $h = intdiv($m, 60);
    if ($h < 24)    return "Hace {$h} h";
    return "Hace " . intdiv($h, 24) . " día(s)";
};
?>

<!-- =====================================================================
     CABECERA Y BREADCRUMB (NAVEGACIÓN)
     ===================================================================== -->
<div class="card border shadow-none position-relative overflow-hidden mb-4">
  <div class="card-body px-4 py-3">
    <div class="row align-items-center">
      <div class="col-12 col-md-8 text-center text-md-start">
        <h4 class="fw-semibold mb-2 mb-md-0"><?= esc($device->name) ?></h4>
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
            <li class="breadcrumb-item active" aria-current="page"><?= esc($device->name) ?></li>
          </ol>
        </nav>
      </div>
      <div class="col-12 col-md-4 d-flex justify-content-center justify-content-md-end mt-3 mt-md-0">
        <a href="<?= site_url('networks/' . $network->id . '/devices') ?>" class="btn bg-primary-subtle text-primary border-0 btn-square shadow-none">
          <i class="ti ti-arrow-left fs-5"></i>
        </a>
      </div>
    </div>
  </div>
</div>

<!-- =====================================================================
     INFORMACIÓN PRINCIPAL DEL DISPOSITIVO
     ===================================================================== -->
<div class="row">
  <div class="col-12">
    <div class="card mb-4">
      <div class="card-body">

        <div class="row g-3 mb-4">
          <div class="col-12 col-md-3">
            <div class="border p-3 rounded h-100">
              <span class="text-muted d-block small mb-1">IP en la Red</span>
              <code class="fw-semibold text-primary"><?= esc($device->ip_address) ?></code>
            </div>
          </div>
          <div class="col-12 col-md-3">
            <div class="border p-3 rounded h-100">
              <span class="text-muted d-block small mb-1">CIDR</span>
              <code class="fw-semibold text-primary"><?= esc($network->cidr) ?></code>
            </div>
          </div>

          <div class="col-12 col-md-3">
            <div class="border p-3 rounded h-100">
              <span class="text-muted d-block small mb-1">Tipo</span>
              <span class="fw-semibold small text-primary">
                <i class="ti <?= $typeIcons[$deviceType] ?? 'ti-device-laptop' ?> me-1"></i>
                <?= $typeLabels[$deviceType] ?? esc($deviceType) ?>
              </span>
            </div>
          </div>
          <div class="col-12 col-md-3">
            <div class="border p-3 rounded h-100">
              <span class="text-muted d-block small mb-1">Última Conexión</span>
              <?php if ($device->last_seen): ?>
                <span class="d-flex justify-content-between align-items-center">
                  <span class="fw-semibold small text-primary"><?= date('d/m/Y', strtotime($device->last_seen)) ?></span>
                  <small class="small text-primary"><?= date('H:i', strtotime($device->last_seen)) ?></small>
                </span>
              <?php else: ?>
                <span class="text-muted small">—</span>
              <?php endif; ?>
            </div>
          </div>
          <div class="col-12 col-md-3">
            <div class="border p-3 rounded h-100">
              <span class="text-muted d-block small mb-1">Creado</span>
              <span class="d-flex justify-content-between align-items-center">
                <span class="fw-semibold small text-primary"><?= date('d/m/Y', strtotime($device->created_at)) ?></span>
                <small class="small text-primary"><?= date('H:i', strtotime($device->created_at)) ?></small>
              </span>
            </div>
          </div>
          <div class="col-12 col-md-3">
            <div class="border p-3 rounded h-100">
              <span class="text-muted d-block small mb-1">Red</span>
              <span class="fw-semibold small text-primary"><?= esc($network->name) ?></span>
            </div>
          </div>
          <div class="col-12 col-md-3">
            <div class="border p-3 rounded h-100">
              <span class="text-muted d-block small mb-1">Keepalive</span>
              <span class="fw-semibold small text-primary">25 seg</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- =====================================================================
     ESTADO DE CONEXIÓN WIREGUARD
     Esta sección solo se renderiza si existen datos de conexión ($wg).
     Muestra IP pública, puerto, handshake y tráfico (subida/bajada).
     ===================================================================== -->
<?php if ($wg !== null): ?>
<div class="row">
  <div class="col-12">
    <div class="card mb-4">
      <div class="card-body">
        <div class="d-flex align-items-center gap-2 mb-3">
          <div class="bg-light-primary text-primary rounded d-flex align-items-center justify-content-center network-icon-circle">
            <i class="ti ti-activity fs-6"></i>
          </div>
          <h5 class="fw-semibold mb-0">Estado del nodo</h5>
        </div>

        <div class="row g-3">
          <div class="col-12 col-md-3">
            <div class="border p-3 rounded h-100">
              <span class="text-muted d-block small mb-1">Estado</span>
              <span class="fw-semibold <?= $wgConnected ? 'text-success' : 'text-danger' ?>">
                <i class="ti <?= $wgConnected ? 'ti-circle-check' : 'ti-circle-x' ?> me-1"></i>
                <?= $wgConnected ? 'En línea' : 'Offline' ?>
              </span>
            </div>
          </div>

          <div class="col-12 col-md-3">
            <div class="border p-3 rounded h-100">
              <span class="text-muted d-block small mb-1">IP Pública</span>
              <code class="fw-semibold text-primary"><?= $wg['public_ip'] ? esc($wg['public_ip']) : '—' ?></code>
            </div>
          </div>

          <div class="col-12 col-md-3">
            <div class="border p-3 rounded h-100">
              <span class="text-muted d-block small mb-1">Puerto</span>
              <code class="fw-semibold text-primary"><?= $wg['port'] ? esc($wg['port']) : '—' ?></code>
            </div>
          </div>

          <div class="col-12 col-md-3">
            <div class="border p-3 rounded h-100">
              <span class="text-muted d-block small mb-1">Último Handshake</span>
              <span class="fw-semibold small text-primary">
                <?= $timeAgoView($wg['latest_handshake'], $wg['remote_time']) ?>
              </span>
            </div>
          </div>

          <div class="col-12 col-md-6">
            <div class="border p-3 rounded h-100 text-center">
              <i class="ti ti-arrow-down text-success d-block mb-1 fs-5"></i>
              <span class="text-muted d-block small mb-1">Descarga (Recibido)</span>
              <span class="fw-semibold text-success">
                <?= $formatBytesView((int)$wg['tx_bytes']) ?>
              </span>
            </div>
          </div>

          <div class="col-12 col-md-6">
            <div class="border p-3 rounded h-100 text-center">
              <i class="ti ti-arrow-up text-info d-block mb-1 fs-5"></i>
              <span class="text-muted d-block small mb-1">Subida (Enviado)</span>
              <span class="fw-semibold text-info">
                <?= $formatBytesView((int)$wg['rx_bytes']) ?>
              </span>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- =====================================================================
     ACCIONES DEL DISPOSITIVO
     ===================================================================== -->
<div class="row">
  <div class="col-12">
    <div class="card mb-4">
      <div class="card-body">
        <div class="d-flex flex-wrap justify-content-center gap-2">

          <button type="button" class="btn btn-dark border-0 shadow-none text-white" onclick="pingDevice(<?= $device->id ?>)">
            <i class="ti ti-wifi me-1"></i>
            Hacer Ping
          </button>

          <form action="<?= site_url('devices/toggle-active/' . $device->id) ?>"
                method="post"
                data-confirm="<?= $device->active ? '¿Desactivar este nodo? Perderá acceso a la VPN.' : '¿Activar este nodo?' ?>">
            <?= csrf_field() ?>

            <button type="submit"
                    class="btn btn-primary border-0 shadow-none text-white">
              <i class="ti <?= $device->active ? 'ti-power' : 'ti-circle-check' ?> me-1"></i>
              <?= $device->active ? 'Desactivar' : 'Activar' ?>
            </button>
          </form>

          <form action="<?= site_url('devices/delete/' . $device->id) ?>"
                method="post"
                data-confirm="¿Estás seguro de que deseas eliminar este nodo? Esto invalidará su clave de acceso.">
            <?= csrf_field() ?>

            <button type="submit"
                    class="btn btn-danger border-0 shadow-none">
              <i class="ti ti-trash me-1"></i>
              Eliminar
            </button>
          </form>

        </div>
      </div>
    </div>
  </div>
</div>

<script>
function pingDevice(deviceId) {
    Swal.fire({
        title: 'Haciendo Ping...',
        text: 'Enviando paquetes ICMP al nodo, por favor espera (aprox. 4-8 segundos).',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    let formData = new FormData();
    formData.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

    fetch('<?= site_url('devices/ping/') ?>' + deviceId, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            Swal.fire({
                title: 'Ping Exitoso',
                html: `<pre class="text-start bg-dark text-success p-3 rounded mt-3" style="font-size: 13px; white-space: pre-wrap; overflow-x: auto;">${data.output}</pre>`,
                icon: 'success',
                confirmButtonColor: '#34c759',
                width: '600px'
            });
        } else {
            Swal.fire({
                title: 'Ping Fallido',
                html: `<p class="mb-2">${data.message}</p><pre class="text-start bg-dark text-danger p-3 rounded mt-3" style="font-size: 13px; white-space: pre-wrap; overflow-x: auto;">${data.output || 'Sin respuesta'}</pre>`,
                icon: 'error',
                confirmButtonColor: '#b31b34',
                width: '600px'
            });
        }
    })
    .catch(error => {
        Swal.fire('Error', 'No se pudo completar la solicitud de ping. Revisa tu conexión al servidor.', 'error');
    });
}
</script>
