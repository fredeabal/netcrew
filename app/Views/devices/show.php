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
$wgConnected = ((int)$device->active === 1) && $wg && !empty($wg['connected']);

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
      <div class="col-12 col-md-4 d-flex justify-content-center justify-content-md-end align-items-center mt-3 mt-md-0">
        <div class="d-flex align-items-center gap-2">
          <a href="<?= site_url('networks/' . $network->id . '/devices') ?>" class="btn btn-sm bg-primary-subtle text-primary border-0 btn-square shadow-none" title="Volver a la red">
            <i class="ti ti-arrow-left"></i>
          </a>
          <?php if (auth()->user()->can('devices.edit') || auth()->user()->inGroup('superadmin')): ?>
            <a href="<?= site_url('devices/edit/' . $device->id) ?>" class="btn btn-primary btn-sm border-0 d-flex align-items-center gap-1 shadow-none">
              <i class="ti ti-pencil"></i>
              <span>Editar Nodo</span>
            </a>
          <?php endif; ?>
        </div>
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

        <div class="row g-3">
          <div class="col-12 col-sm-6 col-lg-3">
            <div class="border p-3 rounded h-100">
              <span class="text-muted d-block small mb-1">IP en la Red</span>
              <span class="net-mono text-body fw-medium"><?= esc($device->ip_address) ?></span>
            </div>
          </div>
          <div class="col-12 col-sm-6 col-lg-3">
            <div class="border p-3 rounded h-100">
              <span class="text-muted d-block small mb-1">CIDR</span>
              <span class="net-mono text-body fw-medium"><?= esc($network->cidr) ?></span>
            </div>
          </div>
          <div class="col-12 col-sm-6 col-lg-3">
            <div class="border p-3 rounded h-100">
              <span class="text-muted d-block small mb-1">Red</span>
              <span class="fw-semibold small text-primary"><?= esc($network->name) ?></span>
            </div>
          </div>

          <div class="col-12 col-sm-6 col-lg-3">
            <div class="border p-3 rounded h-100">
              <span class="text-muted d-block small mb-1">Tipo</span>
              <span class="fw-semibold small text-primary">
                <i class="ti <?= $typeIcons[$deviceType] ?? 'ti-device-laptop' ?> me-1"></i>
                <?= $typeLabels[$deviceType] ?? esc($deviceType) ?>
              </span>
            </div>
          </div>
          <div class="col-12 col-sm-6 col-lg-3">
            <div class="border p-3 rounded h-100">
              <span class="text-muted d-block small mb-1">Enrutamiento</span>
              <span class="fw-semibold small <?= ($device->routing_mode ?? 'split') === 'full' ? 'text-danger' : 'text-primary' ?>">
                <i class="ti <?= ($device->routing_mode ?? 'split') === 'full' ? 'ti-world' : 'ti-arrows-split' ?> me-1"></i>
                <?= ($device->routing_mode ?? 'split') === 'full' ? 'Full-Tunnel (Internet)' : 'Split-Tunnel (Local)' ?>
              </span>
            </div>
          </div>

          <div class="col-12 col-sm-6 col-lg-3">
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
          <div class="col-12 col-sm-6 col-lg-3">
            <div class="border p-3 rounded h-100">
              <span class="text-muted d-block small mb-1">Creado</span>
              <span class="d-flex justify-content-between align-items-center">
                <span class="fw-semibold small text-primary"><?= date('d/m/Y', strtotime($device->created_at)) ?></span>
                <small class="small text-primary"><?= date('H:i', strtotime($device->created_at)) ?></small>
              </span>
            </div>
          </div>
          <div class="col-12 col-sm-6 col-lg-3">
            <div class="border p-3 rounded h-100">
              <span class="text-muted d-block small mb-1">Expiración</span>
              <?php if (!empty($device->expires_at)): ?>
                <?php $isExpired = strtotime($device->expires_at) < time(); ?>
                <span class="fw-semibold small <?= $isExpired ? 'text-danger' : 'text-warning' ?>">
                  <i class="ti <?= $isExpired ? 'ti-clock-x' : 'ti-clock' ?> me-1"></i>
                  <?= date('d/m/Y H:i', strtotime($device->expires_at)) ?>
                  <?= $isExpired ? '(Expirado)' : '' ?>
                </span>
              <?php else: ?>
                <span class="fw-semibold small text-primary">
                  <i class="ti ti-infinity me-1"></i> Permanente
                </span>
              <?php endif; ?>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<!-- =====================================================================
     ESTADO DE CONEXIÓN NEBULA P2P
     Esta sección solo se renderiza si existen datos de conexión ($wg).
     Muestra estado, IP pública, puerto y último handshake.
     ===================================================================== -->
<?php if ($wg !== null): ?>
<div class="row">
  <div class="col-12">
    <div class="card mb-4">
      <div class="card-body">
        <div class="d-flex align-items-center gap-2 mb-3">
          <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center network-icon-circle flex-shrink-0">
            <i class="ti ti-activity fs-5"></i>
          </div>
          <h5 class="fw-semibold mb-0">Estado del nodo</h5>
        </div>

        <div class="row g-3">
          <div class="col-12 col-sm-6 col-lg-3">
            <div class="border p-3 rounded h-100">
              <span class="text-muted d-block small mb-1">Estado</span>
              <?php if ((int)($device->active ?? 1) !== 1): ?>
                <span class="fw-semibold text-muted">
                  <i class="ti ti-power me-1"></i>Inactivo
                </span>
              <?php else: ?>
                <span class="fw-semibold <?= $wgConnected ? 'text-success' : 'text-danger' ?>">
                  <i class="ti <?= $wgConnected ? 'ti-circle-check' : 'ti-circle-x' ?> me-1"></i>
                  <?= $wgConnected ? 'En línea' : 'Offline' ?>
                </span>
              <?php endif; ?>
            </div>
          </div>

          <div class="col-12 col-sm-6 col-lg-3">
            <div class="border p-3 rounded h-100">
              <span class="text-muted d-block small mb-1">IP Pública</span>
              <span class="net-mono text-body fw-medium"><?= $wg['public_ip'] ? esc($wg['public_ip']) : '—' ?></span>
            </div>
          </div>

          <div class="col-12 col-sm-6 col-lg-3">
            <div class="border p-3 rounded h-100">
              <span class="text-muted d-block small mb-1">Puerto</span>
              <span class="net-mono text-body fw-medium"><?= $wg['port'] ? esc($wg['port']) : '—' ?></span>
            </div>
          </div>

          <div class="col-12 col-sm-6 col-lg-3">
            <div class="border p-3 rounded h-100">
              <span class="text-muted d-block small mb-1">Último Handshake</span>
              <span class="fw-semibold small text-primary">
                <?= $timeAgoView($wg['latest_handshake'], $wg['remote_time']) ?>
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
     CONFIGURADOR AUTOMÁTICO POR PLATAFORMA (SELECT + COMANDO)
     ===================================================================== -->
<?php if (!empty($device->active) && !empty($network->active)): ?>
<div class="row">
  <div class="col-12">
    <div class="card mb-4 install-client-card">
      <div class="card-body">
        <div class="d-flex align-items-center gap-2 mb-3">
          <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center network-icon-circle flex-shrink-0">
            <i class="ti ti-terminal-2 fs-5"></i>
          </div>
          <div>
            <h5 class="fw-semibold mb-0">Instalar y Conectar Cliente</h5>
            <small class="text-muted">Selecciona el sistema operativo del dispositivo para obtener el comando o archivo de conexión.</small>
          </div>
        </div>

        <div class="install-controls-row">
          <div>
            <label for="installPlatformSelect" class="form-label fw-semibold">Sistema Operativo</label>
            <select class="select2 form-control custom-select" id="installPlatformSelect">
              <option value="mac">macOS (Apple Silicon / Intel)</option>
              <option value="windows">Windows (10 / 11)</option>
              <option value="linux">Linux (Ubuntu / Debian / etc.)</option>
              <option value="mobile">iOS / Android (Móvil / Tablet)</option>
            </select>
          </div>

          <div class="install-actions">
            <button type="button" id="btnGenerateCommand" class="btn btn-primary btn-sm install-action-btn">
              <i class="ti ti-wand" aria-hidden="true"></i>
              <span>Generar</span>
            </button>
            <a id="lnkDownloadYml" href="<?= site_url('devices/download/' . $device->id . '?platform=yml') ?>" class="btn btn-outline-primary btn-sm install-action-btn">
              <i class="ti ti-download" aria-hidden="true"></i>
              <span id="lblDownloadFile">Descargar</span>
            </a>
          </div>
        </div>

        <div id="installCommandBox" class="install-terminal mt-3">
          <div class="install-terminal-header">
            <small class="fw-semibold text-muted d-inline-flex align-items-center gap-1" id="installCommandHint">
              <i class="ti ti-terminal text-primary" aria-hidden="true"></i>
              <span>Pega este comando en Terminal (macOS):</span>
            </small>
          </div>
          <div class="install-terminal-body">
            <span class="install-terminal-prompt" aria-hidden="true">$</span>
            <pre class="install-terminal-pre" tabindex="0"><code id="installCommandPreview">bash &lt;(curl -fsSL "<?= esc(site_url('connect/TOKEN/download?platform=mac-sh')) ?>")</code></pre>
            <span class="copy">
              <button type="button" id="btnInlineCopyCmd" class="copy-button" command="--copy" commandfor="installCommandPreview" aria-label="Copiar comando de instalación">
                <i class="ti ti-copy icon-copy fs-5" aria-hidden="true"></i>
                <i class="ti ti-check icon-check fs-5" aria-hidden="true"></i>
              </button>
              <span class="tip" role="status"></span>
            </span>
          </div>
        </div>

        <div id="installMobileBox" class="install-mobile-steps mt-3 d-none">
          <div class="d-flex flex-column gap-2 small text-muted">
            <div class="d-flex align-items-start gap-2">
              <span class="install-step-badge bg-primary-subtle text-primary">1</span>
              <span>Instala la app gratuita <strong>Nebula</strong> desde App Store o Google Play.</span>
            </div>
            <div class="d-flex align-items-start gap-2">
              <span class="install-step-badge bg-primary-subtle text-primary">2</span>
              <span>Pulsa <strong>Descargar .yml</strong> e importa el archivo en la app.</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script {csp-script-nonce}>
document.addEventListener('DOMContentLoaded', function() {
  const selectEl = document.getElementById('installPlatformSelect');
  const cmdBox = document.getElementById('installCommandBox');
  const mobBox = document.getElementById('installMobileBox');
  const cmdPreview = document.getElementById('installCommandPreview');
  const cmdHint = document.getElementById('installCommandHint').querySelector('span');
  const btnGen = document.getElementById('btnGenerateCommand');
  const btnInlineCopy = document.getElementById('btnInlineCopyCmd');
  const lnkDl = document.getElementById('lnkDownloadYml');
  const lblDl = document.getElementById('lblDownloadFile');

  const baseConnect = <?= json_encode(rtrim(site_url('connect'), '/')) ?>;
  const baseDownload = <?= json_encode(site_url('devices/download/' . $device->id)) ?>;
  let activeToken = null;
  let lastCopyMessage = '';
  const copyTimers = new WeakMap();

  function buildCommand(platform, token) {
    const t = token || 'TOKEN';
    if (platform === 'mac') {
      return `bash <(curl -fsSL "${baseConnect}/${t}/download?platform=mac-sh")`;
    }
    if (platform === 'windows') {
      return `curl -fsSL "${baseConnect}/${t}/download?platform=windows" -o "%TEMP%\\netcrew.bat" && "%TEMP%\\netcrew.bat"`;
    }
    if (platform === 'linux') {
      return `sudo bash <(curl -fsSL "${baseConnect}/${t}/download?platform=linux")`;
    }
    return '';
  }

  function renderCommandPreview(cmd, token) {
    cmdPreview.textContent = '';
    if (token) {
      cmdPreview.textContent = cmd;
      return;
    }
    const parts = cmd.split('TOKEN');
    if (parts.length === 2) {
      cmdPreview.append(document.createTextNode(parts[0]));
      const tokSpan = document.createElement('span');
      tokSpan.className = 'cmd-token-placeholder';
      tokSpan.textContent = 'TOKEN';
      cmdPreview.append(tokSpan);
      cmdPreview.append(document.createTextNode(parts[1]));
    } else {
      cmdPreview.textContent = cmd;
    }
  }

  function reportCopy(button, ok) {
    const tip = button.nextElementSibling;
    clearTimeout(copyTimers.get(button));
    button.removeAttribute('data-copied');
    button.removeAttribute('data-failed');
    let message = ok ? 'Copiado' : 'Error al copiar';
    if (message === lastCopyMessage) message += '\u00A0';
    lastCopyMessage = message;
    tip.textContent = '';
    void button.offsetWidth;
    button.setAttribute(ok ? 'data-copied' : 'data-failed', '');
    requestAnimationFrame(() => (tip.textContent = message));
    copyTimers.set(button, setTimeout(() => {
      button.removeAttribute('data-copied');
      button.removeAttribute('data-failed');
      tip.textContent = '';
    }, 2000));
  }

  async function copyFromTarget(button, target) {
    if (!activeToken) {
      if (btnGen) btnGen.click();
      return;
    }
    const text = target.innerText.trim();
    if (!text) return;
    try {
      await navigator.clipboard.writeText(text);
      reportCopy(button, true);
    } catch {
      reportCopy(button, false);
    }
  }

  document.addEventListener('command', (event) => {
    if (event.command === '--copy' && event.source === btnInlineCopy) {
      copyFromTarget(event.source, event.target);
    }
  }, true);

  if (!('CommandEvent' in window)) {
    document.addEventListener('click', (event) => {
      const button = event.target.closest('button[command="--copy"]');
      if (!button || button !== btnInlineCopy) return;
      const target = document.getElementById(button.getAttribute('commandfor'));
      if (target) copyFromTarget(button, target);
    });
  }

  function updatePlatformUI() {
    const p = selectEl.value;
    if (p === 'mobile') {
      cmdBox.classList.add('d-none');
      btnGen.classList.add('d-none');
      mobBox.classList.remove('d-none');
      lnkDl.href = baseDownload + '?platform=yml';
      return;
    }

    mobBox.classList.add('d-none');
    cmdBox.classList.remove('d-none');
    btnGen.classList.remove('d-none');

    if (p === 'mac') {
      cmdHint.textContent = 'Pega este comando en Terminal (macOS):';
      lnkDl.href = baseDownload + '?platform=yml';
    } else if (p === 'windows') {
      cmdHint.textContent = 'Pega este comando en Símbolo del sistema (CMD):';
      lnkDl.href = baseDownload + '?platform=windows';
    } else if (p === 'linux') {
      cmdHint.textContent = 'Pega este comando en Terminal (Linux):';
      lnkDl.href = baseDownload + '?platform=linux';
    }

    renderCommandPreview(buildCommand(p, activeToken), activeToken);
  }

  if (selectEl) {
    selectEl.addEventListener('change', updatePlatformUI);
    if (typeof jQuery !== 'undefined') {
      jQuery(selectEl).on('change.select2', updatePlatformUI);
    }
    updatePlatformUI();
  }

  if (btnGen) {
    btnGen.addEventListener('click', function() {
      btnGen.disabled = true;
      const fd = new FormData();
      fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

      fetch('<?= site_url('devices/generate-share-link/' . $device->id) ?>', {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: fd
      })
      .then(async r => {
        const data = await r.json().catch(() => ({}));
        if (!r.ok) {
          throw new Error(data.message || ('Error HTTP ' + r.status));
        }
        return data;
      })
      .then(data => {
        if (!data.success || !data.token) {
          throw new Error(data.message || 'No se pudo generar el comando.');
        }
        activeToken = data.token;
        const cmd = buildCommand(selectEl.value, activeToken);
        renderCommandPreview(cmd, activeToken);
        if (navigator.clipboard && navigator.clipboard.writeText) {
          return navigator.clipboard.writeText(cmd).then(() => true).catch(() => false);
        }
        return false;
      })
      .then(copied => {
        if (copied && btnInlineCopy) {
          reportCopy(btnInlineCopy, true);
        }
        const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        Swal.fire({
          icon: 'success',
          title: copied ? 'Comando copiado' : 'Comando generado',
          text: copied ? 'Pégalo en la terminal del dispositivo para conectarlo automáticamente.' : 'Copia el comando mostrado en el recuadro.',
          timer: 3000,
          showConfirmButton: false,
          background: isDark ? '#0b1114' : '#f8f9fa',
          color: isDark ? '#ffffff' : '#0b1114'
        });
      })
      .catch(err => {
        const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: err.message || 'No se pudo generar el comando.',
          background: isDark ? '#0b1114' : '#f8f9fa',
          color: isDark ? '#ffffff' : '#0b1114',
          confirmButtonColor: '#F38020'
        });
      })
      .finally(() => { btnGen.disabled = false; });
    });
  }
});
</script>
<?php endif; ?>

<!-- =====================================================================
     ACCIONES DEL DISPOSITIVO
     ===================================================================== -->
<div class="row">
  <div class="col-12">
    <div class="card mb-4">
      <div class="card-body">
        <div class="row g-2 justify-content-center">

          <div class="col-6 col-md-auto">
            <button type="button" class="btn btn-dark btn-sm border-0 shadow-none text-white w-100 h-100 d-flex align-items-center justify-content-center px-2 px-md-3 py-2 text-nowrap" onclick="pingDevice(<?= $device->id ?>)">
              <i class="ti ti-wifi me-1"></i>
              <span>Hacer Ping</span>
            </button>
          </div>

          <div class="col-6 col-md-auto">
            <form action="<?= site_url('devices/rotate-keys/' . $device->id) ?>"
                  method="post"
                  class="h-100 m-0"
                  data-confirm="¿Regenerar las llaves de este nodo? Esto invalidará la configuración y los certificados actuales. Tendrás que reconfigurar el cliente.">
              <?= csrf_field() ?>

              <button type="submit"
                      class="btn btn-warning btn-sm border-0 shadow-none text-white w-100 h-100 d-flex align-items-center justify-content-center px-2 px-md-3 py-2 text-nowrap">
                <i class="ti ti-refresh me-1"></i>
                <span>Regenerar Llaves</span>
              </button>
            </form>
          </div>

          <div class="col-6 col-md-auto">
            <form action="<?= site_url('devices/toggle-active/' . $device->id) ?>"
                  method="post"
                  class="h-100 m-0"
                  data-confirm="<?= $device->active ? '¿Desactivar este nodo? Perderá acceso a la VPN.' : '¿Activar este nodo?' ?>">
              <?= csrf_field() ?>

              <button type="submit"
                      class="btn btn-primary btn-sm border-0 shadow-none text-white w-100 h-100 d-flex align-items-center justify-content-center px-2 px-md-3 py-2 text-nowrap">
                <i class="ti <?= $device->active ? 'ti-power' : 'ti-circle-check' ?> me-1"></i>
                <span><?= $device->active ? 'Desactivar' : 'Activar' ?></span>
              </button>
            </form>
          </div>

          <div class="col-6 col-md-auto">
            <form action="<?= site_url('devices/delete/' . $device->id) ?>"
                  method="post"
                  class="h-100 m-0"
                  data-confirm="¿Estás seguro de que deseas eliminar este nodo? Esto invalidará su clave de acceso.">
              <?= csrf_field() ?>

              <button type="submit"
                      class="btn btn-danger btn-sm border-0 shadow-none w-100 h-100 d-flex align-items-center justify-content-center px-2 px-md-3 py-2 text-nowrap">
                <i class="ti ti-trash me-1"></i>
                <span>Eliminar</span>
              </button>
            </form>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>

<script {csp-script-nonce}>
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
        const escapeHtml = (str) => String(str || '').replace(/[&<>"']/g, m => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[m]));
        const safeOutput = escapeHtml(data.output || 'Sin respuesta');
        const safeMessage = escapeHtml(data.message || '');

        if (data.status === 'success') {
            Swal.fire({
                title: 'Ping Exitoso',
                html: `<pre class="swal-output text-start bg-dark text-success p-3 rounded mt-3">${safeOutput}</pre>`,
                icon: 'success',
                confirmButtonColor: '#34c759',
                width: '600px'
            });
        } else {
            Swal.fire({
                title: 'Ping Fallido',
                html: `<p class="mb-2">${safeMessage}</p><pre class="swal-output text-start bg-dark text-danger p-3 rounded mt-3">${safeOutput}</pre>`,
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
