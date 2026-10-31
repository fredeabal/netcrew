  <!-- =====================================================================
       CABECERA Y BREADCRUMB (NAVEGACIÓN)
       ===================================================================== -->
  <div class="card border shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body px-4 py-3">
      <div class="row align-items-center">
        <div class="col-12 col-md-8 text-center text-md-start">
          <h4 class="fw-semibold mb-2 mb-md-8"><?= esc($network->name) ?></h4>
          <nav aria-label="breadcrumb" class="d-flex justify-content-center justify-content-md-start">
            <ol class="breadcrumb mb-0">
              <li class="breadcrumb-item">
                <a class="text-muted text-decoration-none" href="<?= site_url() ?>">Inicio</a>
              </li>
              <li class="breadcrumb-item">
                <a class="text-muted text-decoration-none" href="<?= site_url('networks') ?>">Redes</a>
              </li>
              <li class="breadcrumb-item" aria-current="page">Nodos</li>
            </ol>
          </nav>
        </div>
        <div class="col-12 col-md-4 d-flex justify-content-center justify-content-md-end align-items-center mt-3 mt-md-0">
          <div class="d-flex align-items-center gap-2">
            <a href="<?= site_url('networks') ?>" class="btn bg-primary-subtle text-primary border-0 btn-square shadow-none">
              <i class="ti ti-arrow-left fs-5"></i>
            </a>
            <a href="<?= site_url('networks/' . $network->id . '/devices/create') ?>" class="btn btn-primary border-0 d-flex align-items-center gap-2 px-4 shadow-none">
              <i class="ti ti-plus fs-5"></i> Nuevo Nodo
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- =====================================================================
       INFORMACIÓN GENERAL DE LA RED Y TABLA DE NODOS
       ===================================================================== -->
  <div class="row">
    <div class="col-12">
      <!-- Tarjeta Principal -->
      <div class="card">
        <div class="card-body">
          <!-- Detalles de la Red -->
          <div class="row g-3 mb-4">
            <div class="col-12 col-sm-6 col-md-3">
              <div class="border p-3 rounded">
                <span class="text-muted d-block small mb-1">Rango CIDR</span>
                <code class="fw-semibold text-primary"><?= esc($network->cidr) ?></code>
              </div>
            </div>

            <div class="col-12 col-sm-6 col-md-3">
              <div class="border p-3 rounded">
                <span class="text-muted d-block small mb-1">Estado de Red</span>
                <code class="fw-semibold <?= $network->active ? 'text-primary' : 'text-danger' ?>">
                  <?= $network->active ? 'Activa' : 'Inactiva' ?>
                </code>
              </div>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
              <div class="border p-3 rounded">
                <span class="text-muted d-block small mb-1">Total Nodos</span>
                <code class="fw-semibold text-primary"><?= count($devices) ?></code>
              </div>
            </div>

            <!-- Capacidad de IPs basada en /24 -->
            <div class="col-12 col-sm-6 col-md-3">
              <div class="border p-3 rounded">
                <span class="text-muted d-block small mb-1">Capacidad (/24)</span>
                <code class="fw-semibold text-primary"><?= count($devices) ?> / 254</code>
              </div>
            </div>
          </div>

          <!-- Tabla de Nodos / Dispositivos -->
          <div class="d-flex align-items-center gap-2 mb-4">
            <div class="bg-light-primary text-primary rounded d-flex align-items-center justify-content-center network-icon-circle">
              <i class="ti ti-devices fs-6"></i>
            </div>
            <h5 class="fw-semibold mb-0">Administrar Nodos</h5>
          </div>

          <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-3 w-100">
            <div class="position-relative w-100 w-sm-auto search-box-container">
              <input type="text" id="search-devices" class="form-control" placeholder="Buscar nodo por nombre o IP...">
              <i class="ti ti-search search-icon text-muted"></i>
            </div>
          </div>
          <?php if (empty($devices)): ?>
            <div class="alert alert-dark text-center py-5 rounded border-0" role="alert">
              <i class="ti ti-devices fs-8 text-muted d-block mb-2"></i>
              <h6 class="fw-semibold text-dark">No hay nodos registrados</h6>
              <p class="text-muted mb-0 small">Registra tu primer dispositivo para empezar a generar archivos de configuración de WireGuard.</p>
            </div>
          <?php else: ?>
            <div class="table-responsive d-none d-md-block">
              <table class="table table-hover align-middle text-nowrap text-center" id="devices-table">
                <thead>
                  <tr class="text-muted fw-semibold">
                    <th scope="col" class="text-start">Nombre del Dispositivo</th>
                    <th scope="col" class="text-center">IP Red</th>
                    <th scope="col" class="text-center">IP Física</th>
                    <th scope="col" class="text-center">Última Conexión</th>
                    <th scope="col" class="text-center">Tráfico (↑/↓)</th>
                    <th scope="col" class="text-end">Acciones</th>
                  </tr>
                </thead>
                <tbody class="border-top">
                  <?php foreach ($devices as $dev): ?>
                    <tr onclick="window.location='<?= site_url('devices/show/' . $dev->id) ?>'" class="cursor-pointer">
                      <td class="text-start">
                        <div class="d-flex align-items-center">
                          <div class="network-icon-circle d-flex align-items-center justify-content-center rounded-circle me-3 device-icon-status-wrapper <?= $dev->active ? 'bg-danger-subtle text-danger' : 'bg-light text-muted' ?>"
                               data-pubkey="<?= esc($dev->public_key) ?>"
                               data-active="<?= $dev->active ? '1' : '0' ?>">
                             <?php
                               $typeIconsMap = [
                                 'pc'     => 'ti-device-laptop',
                                 'server' => 'ti-server',
                                 'mobile' => 'ti-device-mobile',
                                 'tablet' => 'ti-device-tablet',
                                 'router' => 'ti-router'
                               ];
                               $iconClass = $typeIconsMap[$dev->device_type ?? 'pc'] ?? 'ti-device-laptop';
                             ?>
                             <i class="ti <?= $iconClass ?> fs-5"></i>
                          </div>
                          <div>
                            <h6 class="fw-semibold mb-0">
                              <a href="<?= site_url('devices/show/' . $dev->id) ?>" class="<?= $dev->active ? 'text-reset text-primary-hover' : 'text-muted' ?> text-decoration-none">
                                <?= esc($dev->name) ?>
                              </a>
                            </h6>
                          </div>
                        </div>
                      </td>
                      <td class="text-center">
                        <code class="fs-3 text-muted"><?= esc($dev->ip_address) ?></code>
                      </td>
                      <td class="text-center device-status-cell bg-danger-subtle text-danger fs-3" data-pubkey="<?= esc($dev->public_key) ?>" data-active="<?= $dev->active ? '1' : '0' ?>">
                        -
                      </td>
                      <td class="text-center text-muted device-last-seen-cell" data-pubkey="<?= esc($dev->public_key) ?>">
                        <?php if ($dev->last_seen): ?>
                          <div><?= date('d/m/Y', strtotime($dev->last_seen)) ?></div>
                          <small class="text-muted d-block"><?= date('H:i', strtotime($dev->last_seen)) ?></small>
                        <?php else: ?>
                          <span class="text-muted">—</span>
                        <?php endif; ?>
                      </td>
                      <td class="text-center device-traffic-cell" data-pubkey="<?= esc($dev->public_key) ?>">
                        <span class="text-muted fs-3">—</span>
                      </td>
                      <td class="text-end" onclick="event.stopPropagation();">
                        <div class="dropdown">
                          <button class="btn btn-sm bg-transparent border-0 text-muted shadow-none p-1" type="button" data-bs-toggle="dropdown" aria-expanded="false" data-bs-popper-config='{"strategy":"fixed"}'>
                            <i class="ti ti-dots-vertical fs-5"></i>
                          </button>
                          <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                              <a href="<?= site_url('devices/show/' . $dev->id) ?>" class="dropdown-item d-flex align-items-center gap-2">
                                <i class="ti ti-info-circle"></i> Detalles
                              </a>
                            </li>
                            <li>
                              <a href="javascript:void(0)" class="dropdown-item d-flex align-items-center gap-2 btn-view-config" data-id="<?= $dev->id ?>">
                                <i class="ti ti-eye"></i> Ver Conf / QR
                              </a>
                            </li>
                            <li>
                              <a href="<?= site_url('devices/download/' . $dev->id) ?>" class="dropdown-item d-flex align-items-center gap-2">
                                <i class="ti ti-download"></i> Descargar .conf
                              </a>
                            </li>
                            <li>
                              <hr class="dropdown-divider">
                            </li>
                            <li>
                              <a href="<?= site_url('devices/edit/' . $dev->id) ?>" class="dropdown-item d-flex align-items-center gap-2">
                                <i class="ti ti-pencil"></i> Editar
                              </a>
                            </li>
                            <li>
                              <form action="<?= site_url('devices/toggle-active/' . $dev->id) ?>" method="post" data-confirm="<?= $dev->active ? '¿Desactivar este nodo? Perderá acceso a la VPN.' : '¿Activar este nodo?' ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="dropdown-item d-flex align-items-center gap-2 w-100 border-0 text-start">
                                  <i class="ti <?= $dev->active ? 'ti-power' : 'ti-circle-check' ?>"></i> <?= $dev->active ? 'Desactivar Nodo' : 'Activar Nodo' ?>
                                </button>
                              </form>
                            </li>
                            <li>
                              <form action="<?= site_url('devices/delete/' . $dev->id) ?>" method="post" data-confirm="¿Estás seguro de que deseas eliminar este nodo? Esto invalidará su clave de acceso.">
                                <?= csrf_field() ?>
                                <button type="submit" class="dropdown-item d-flex align-items-center gap-2 w-100 border-0 text-start">
                                  <i class="ti ti-trash"></i> Eliminar Nodo
                                </button>
                              </form>
                            </li>
                          </ul>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <!-- VISTA MÓVIL: TARJETAS (d-block d-md-none) -->
            <div class="d-block d-md-none" id="devicesCardsContainer">
              <?php foreach ($devices as $dev): ?>
                <?php
                  $typeIconsMap = [
                    'pc'     => 'ti-device-laptop',
                    'server' => 'ti-server',
                    'mobile' => 'ti-device-mobile',
                    'tablet' => 'ti-device-tablet',
                    'router' => 'ti-router'
                  ];
                  $iconClass = $typeIconsMap[$dev->device_type ?? 'pc'] ?? 'ti-device-laptop';
                ?>
                <div class="mobile-card-item device-card-item" onclick="window.location='<?= site_url('devices/show/' . $dev->id) ?>'">
                  <div class="mobile-card-header">
                    <div class="d-flex align-items-center gap-2">
                      <div class="network-icon-circle d-flex align-items-center justify-content-center rounded-circle device-icon-status-wrapper flex-shrink-0 <?= $dev->active ? 'bg-danger-subtle text-danger' : 'bg-light text-muted' ?>"
                           data-pubkey="<?= esc($dev->public_key) ?>"
                           data-active="<?= $dev->active ? '1' : '0' ?>">
                        <i class="ti <?= $iconClass ?> fs-5"></i>
                      </div>
                      <div>
                        <h6 class="mobile-card-title"><?= esc($dev->name) ?></h6>
                        <code class="text-primary fs-2"><?= esc($dev->ip_address) ?></code>
                      </div>
                    </div>
                    <div class="d-flex align-items-center" onclick="event.stopPropagation();">
                      <div class="dropdown">
                        <button class="btn btn-sm bg-transparent border-0 text-muted shadow-none p-1" type="button" data-bs-toggle="dropdown" aria-expanded="false" data-bs-popper-config='{"strategy":"fixed"}'>
                          <i class="ti ti-dots-vertical fs-5"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                          <li>
                            <a href="<?= site_url('devices/show/' . $dev->id) ?>" class="dropdown-item d-flex align-items-center gap-2">
                              <i class="ti ti-info-circle"></i> Detalles
                            </a>
                          </li>
                          <li>
                            <a href="javascript:void(0)" class="dropdown-item d-flex align-items-center gap-2 btn-view-config" data-id="<?= $dev->id ?>">
                              <i class="ti ti-eye"></i> Ver Conf / QR
                            </a>
                          </li>
                          <li>
                            <a href="<?= site_url('devices/download/' . $dev->id) ?>" class="dropdown-item d-flex align-items-center gap-2">
                              <i class="ti ti-download"></i> Descargar .conf
                            </a>
                          </li>
                          <li>
                            <hr class="dropdown-divider">
                          </li>
                          <li>
                            <a href="<?= site_url('devices/edit/' . $dev->id) ?>" class="dropdown-item d-flex align-items-center gap-2">
                              <i class="ti ti-pencil"></i> Editar
                            </a>
                          </li>
                          <li>
                            <form action="<?= site_url('devices/toggle-active/' . $dev->id) ?>" method="post" data-confirm="<?= $dev->active ? '¿Desactivar este nodo? Perderá acceso a la VPN.' : '¿Activar este nodo?' ?>">
                              <?= csrf_field() ?>
                              <button type="submit" class="dropdown-item d-flex align-items-center gap-2 w-100 border-0 text-start">
                                <i class="ti <?= $dev->active ? 'ti-power' : 'ti-circle-check' ?>"></i> <?= $dev->active ? 'Desactivar Nodo' : 'Activar Nodo' ?>
                              </button>
                            </form>
                          </li>
                          <li>
                            <form action="<?= site_url('devices/delete/' . $dev->id) ?>" method="post" data-confirm="¿Estás seguro de que deseas eliminar este nodo? Esto invalidará su clave de acceso.">
                              <?= csrf_field() ?>
                              <button type="submit" class="dropdown-item d-flex align-items-center gap-2 w-100 border-0 text-start">
                                <i class="ti ti-trash"></i> Eliminar Nodo
                              </button>
                            </form>
                          </li>
                        </ul>
                      </div>
                    </div>
                  </div>
                  <div class="mobile-card-body">
                    <div class="mobile-card-row">
                      <span class="mobile-card-label"><i class="ti ti-world"></i> IP Pública:</span>
                      <span class="device-status-cell mobile-card-value text-muted" data-pubkey="<?= esc($dev->public_key) ?>" data-active="<?= $dev->active ? '1' : '0' ?>">-</span>
                    </div>
                    <div class="mobile-card-row">
                      <span class="mobile-card-label"><i class="ti ti-arrows-sort"></i> Tráfico (↑/↓):</span>
                      <span class="device-traffic-cell mobile-card-value text-muted" data-pubkey="<?= esc($dev->public_key) ?>">—</span>
                    </div>
                    <div class="mobile-card-row">
                      <span class="mobile-card-label"><i class="ti ti-clock"></i> Última Conexión:</span>
                      <span class="device-last-seen-cell mobile-card-value text-muted" data-pubkey="<?= esc($dev->public_key) ?>">
                        <?= $dev->last_seen ? date('d/m/Y H:i', strtotime($dev->last_seen)) : '—' ?>
                      </span>
                    </div>
                    <div class="mobile-card-row">
                      <span class="mobile-card-label"><i class="ti ti-devices"></i> Tipo:</span>
                      <span class="mobile-card-value text-muted text-uppercase"><?= esc($dev->device_type ?? 'pc') ?> (<?= esc($dev->os ?? 'linux') ?>)</span>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

<!-- =====================================================================
     MODAL: VER CONFIGURACIÓN / QR
     ===================================================================== -->
<div class="modal fade" id="modal-view-config" tabindex="-1" aria-labelledby="modalViewConfigLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-semibold" id="modalViewConfigLabel">Configuración de WireGuard</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body py-4 text-center">
        <p class="text-muted small">Escanea este código QR con la app móvil oficial de WireGuard o descarga el archivo de configuración para tu cliente de escritorio.</p>
        
        <div class="d-flex flex-column align-items-center mb-4">
          <div class="qr-wrapper mb-3 p-3 bg-light rounded display-inline-block">
            <div id="qrcode-container-page" class="qr-container"></div>
          </div>
        </div>

        <div class="d-flex flex-wrap gap-2 justify-content-center mt-4">
          <button type="button" class="btn btn-dark border-0" id="btn-copy-share-link">
            <i class="ti ti-link me-1"></i> Copiar Enlace Temporal
          </button>
          <button type="button" class="btn btn-info border-0" id="btn-send-email-config">
            <i class="ti ti-mail me-1"></i> Enviar por Email
          </button>
          <a href="#" class="btn btn-primary border-0 d-flex align-items-center gap-2" id="btn-download-config-modal">
            <i class="ti ti-download"></i> Descargar .conf
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- =====================================================================
     SCRIPTS DE FUNCIONALIDAD (AJAX, QR Y TIEMPO REAL)
     ===================================================================== -->
<!-- Librería QR -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
  let qrcode = null;
  const qrcodeContainer = document.getElementById("qrcode-container-page");
  const btnDownloadConfigModal = document.getElementById("btn-download-config-modal");
  
  const modalViewConfig = new bootstrap.Modal(document.getElementById('modal-view-config'));
  const modalAddDevice = new bootstrap.Modal(document.getElementById('modal-add-device'));

  let currentActiveDeviceId = null;
  const btnShareLink = document.getElementById("btn-copy-share-link");
  const btnSendEmail = document.getElementById("btn-send-email-config");

  if (btnShareLink) {
    btnShareLink.addEventListener("click", function() {
      if (!currentActiveDeviceId) return;
      btnShareLink.disabled = true;
      btnShareLink.innerHTML = `<span class="spinner-border spinner-border-sm"></span> Generando...`;

      fetch(`<?= site_url('devices/generate-share-link/') ?>${currentActiveDeviceId}`, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
      .then(r => r.json())
      .then(res => {
        btnShareLink.disabled = false;
        btnShareLink.innerHTML = `<i class="ti ti-link me-1"></i> Copiar Enlace Temporal`;
        if (res.success) {
          navigator.clipboard.writeText(res.share_url).then(() => {
            Swal.fire({
              icon: 'success',
              title: '¡Enlace Copiado al Portapapeles!',
              html: `<p class="mb-2">El destinatario puede abrir este enlace para ver el QR o descargar el .conf sin iniciar sesión:</p><input type="text" class="form-control text-center text-primary" value="${res.share_url}" readonly><small class="text-muted d-block mt-2">Válido durante 60 minutos.</small>`,
              confirmButtonColor: '#F38020'
            });
          });
        } else {
          Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'No se pudo generar enlace' });
        }
      })
      .catch(() => {
        btnShareLink.disabled = false;
        btnShareLink.innerHTML = `<i class="ti ti-link me-1"></i> Copiar Enlace Temporal`;
      });
    });
  }

  if (btnSendEmail) {
    btnSendEmail.addEventListener("click", function() {
      if (!currentActiveDeviceId) return;
      Swal.fire({
        title: 'Enviar Configuración por Correo',
        text: 'Ingresa la dirección de correo a la que deseas enviar el archivo .conf adjunto:',
        input: 'email',
        inputPlaceholder: 'usuario@empresa.com',
        showCancelButton: true,
        confirmButtonText: 'Enviar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#F38020',
        cancelButtonColor: '#dc3545',
        showLoaderOnConfirm: true,
        preConfirm: (email) => {
          let formData = new FormData();
          formData.append('email', email);
          return fetch(`<?= site_url('devices/send-email/') ?>${currentActiveDeviceId}`, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
          })
          .then(response => response.json())
          .then(data => {
            if (!data.success) {
              throw new Error(data.message || 'Error al enviar');
            }
            return data;
          })
          .catch(error => {
            Swal.showValidationMessage(error.message);
          });
        },
        allowOutsideClick: () => !Swal.isLoading()
      }).then((result) => {
        if (result.isConfirmed) {
          Swal.fire({
            icon: 'success',
            title: '¡Correo Enviado!',
            text: result.value.message || 'Se ha enviado la configuración por correo exitosamente.',
            confirmButtonColor: '#F38020'
          });
        }
      });
    });
  }

  // Manejo de la previsualización del QR y archivo de configuración
  document.querySelectorAll('.btn-view-config').forEach(btn => {
    btn.addEventListener('click', function() {
      const devId = this.dataset.id;
      currentActiveDeviceId = devId;
      
      // Limpiar contenedor QR previo
      qrcodeContainer.innerHTML = "";
      btnDownloadConfigModal.href = "#";

      fetch(`<?= site_url('devices/details-json/') ?>${devId}`)
        .then(response => response.json())
        .then(res => {
          if (res.success) {
            btnDownloadConfigModal.href = `<?= site_url('devices/download/') ?>${res.device.id}`;
            
            // Generar código QR
            qrcode = new QRCode(qrcodeContainer, {
              text: res.device.config,
              width: 250,
              height: 250,
              colorDark : "#000000",
              colorLight : "#ffffff",
              correctLevel : QRCode.CorrectLevel.M
            });

            modalViewConfig.show();
          } else {
            Swal.fire({
              icon: 'error',
              title: 'Error',
              text: res.message || 'No se pudo cargar la configuración.'
            });
          }
        })
        .catch(err => {
          console.error(err);
          Swal.fire({
            icon: 'error',
            title: 'Error de Red',
            text: 'Ocurrió un error al conectar con el servidor.'
          });
        });
    });
  });



  // Buscador de nodos en tiempo real (nombre o IP)
  const searchInput = document.getElementById('search-devices');
  if (searchInput) {
    searchInput.addEventListener('input', function() {
      const filter = this.value.toLowerCase().trim();
      const rows = document.querySelectorAll('#devices-table tbody tr');
      const cards = document.querySelectorAll('.device-card-item');
      
      rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.classList.toggle('d-none', !text.includes(filter));
      });

      cards.forEach(card => {
        const text = card.textContent.toLowerCase();
        card.classList.toggle('d-none', !text.includes(filter));
      });
    });
  }

  // ---------------------------------------------------------------------
  // Estado en tiempo real de conexión WireGuard
  // ---------------------------------------------------------------------
  let isRefreshing = false;
  function updateRealtimeStatuses() {
    if (isRefreshing) return;
    isRefreshing = true;

    const cells = document.querySelectorAll('.device-status-cell');
    const iconWrappers = document.querySelectorAll('.device-icon-status-wrapper');
    const lastSeenCells = document.querySelectorAll('.device-last-seen-cell');
    const trafficCells = document.querySelectorAll('.device-traffic-cell');

    function formatBytes(bytes) {
      if (!bytes || bytes === 0) return '0 B';
      const k = 1024;
      const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    fetch(`<?= site_url('devices/realtime-status/' . $network->id) ?>`)
      .then(response => response.json())
      .then(res => {
        if (res.success) {
          const peers = res.peers || {};
          cells.forEach(cell => {
            const pubkey = cell.dataset.pubkey;
            const active = cell.dataset.active;
            const isTd = cell.tagName === 'TD';
            
            if (active === '1') {
              const peer = peers[pubkey];
              if (peer && peer.connected) {
                const publicIp = peer.endpoint ? peer.endpoint.split(':')[0] : 'IP desconocida';
                const codeEl = document.createElement('code');
                codeEl.className = 'fs-3 text-dark';
                codeEl.textContent = publicIp;
                cell.innerHTML = '';
                cell.appendChild(codeEl);
                cell.className = isTd ? "text-center device-status-cell" : "device-status-cell mobile-card-value";
              } else {
                cell.innerHTML = `<span class="text-muted fs-3">-</span>`;
                cell.className = isTd ? "text-center device-status-cell text-muted fs-3" : "device-status-cell mobile-card-value text-muted";
              }
            } else {
              cell.innerHTML = `<span class="text-muted fs-3">-</span>`;
              cell.className = isTd ? "text-center device-status-cell text-muted fs-3" : "device-status-cell mobile-card-value text-muted";
            }
          });

          lastSeenCells.forEach(cell => {
            const pubkey = cell.dataset.pubkey;
            const peer = peers[pubkey];

            if (peer && peer.last_seen_date && peer.last_seen_time) {
              if (cell.tagName === 'TD') {
                cell.innerHTML = `<div>${peer.last_seen_date}</div><small class="text-muted d-block">${peer.last_seen_time}</small>`;
              } else {
                cell.innerHTML = `<span>${peer.last_seen_date} <span class="text-muted ms-1">${peer.last_seen_time}</span></span>`;
              }
            }
          });

          trafficCells.forEach(cell => {
            const pubkey = cell.dataset.pubkey;
            const peer = peers[pubkey];

            if (peer && (peer.rx > 0 || peer.tx > 0)) {
              if (cell.tagName === 'TD') {
                cell.innerHTML = `
                  <div class="text-info small fw-semibold" title="Subida (Enviado)"><i class="ti ti-arrow-up"></i> ${formatBytes(peer.rx)}</div>
                  <div class="text-success small fw-semibold mt-1" title="Descarga (Recibido)"><i class="ti ti-arrow-down"></i> ${formatBytes(peer.tx)}</div>
                `;
              } else {
                cell.innerHTML = `
                  <span class="d-inline-flex align-items-center gap-2">
                    <span class="text-info fw-semibold" title="Subida (Enviado)"><i class="ti ti-arrow-up"></i> ${formatBytes(peer.rx)}</span>
                    <span class="text-success fw-semibold" title="Descarga (Recibido)"><i class="ti ti-arrow-down"></i> ${formatBytes(peer.tx)}</span>
                  </span>
                `;
              }
            } else {
              cell.innerHTML = `<span class="text-muted">—</span>`;
            }
          });

          iconWrappers.forEach(wrapper => {
            const pubkey = wrapper.dataset.pubkey;
            const active = wrapper.dataset.active;
            
            if (active === '1') {
              const peer = peers[pubkey];
              if (peer && peer.connected) {
                wrapper.className = "network-icon-circle d-flex align-items-center justify-content-center rounded-circle device-icon-status-wrapper bg-success-subtle text-success";
              } else {
                wrapper.className = "network-icon-circle d-flex align-items-center justify-content-center rounded-circle device-icon-status-wrapper bg-danger-subtle text-danger";
              }
            } else {
              wrapper.className = "network-icon-circle d-flex align-items-center justify-content-center rounded-circle device-icon-status-wrapper bg-light text-muted";
            }
          });
        } else {
          cells.forEach(cell => {
            const isTd = cell.tagName === 'TD';
            cell.innerHTML = `<span class="text-muted fs-3">-</span>`;
            cell.className = isTd ? "text-center device-status-cell text-muted fs-3" : "device-status-cell mobile-card-value text-muted";
          });
          trafficCells.forEach(cell => {
            cell.innerHTML = `<span class="text-muted fs-3">—</span>`;
          });
          iconWrappers.forEach(wrapper => {
            if (wrapper.dataset.active === '1') {
              wrapper.className = "network-icon-circle d-flex align-items-center justify-content-center rounded-circle device-icon-status-wrapper bg-danger-subtle text-danger";
            } else {
              wrapper.className = "network-icon-circle d-flex align-items-center justify-content-center rounded-circle device-icon-status-wrapper bg-light text-muted";
            }
          });
        }
      })
      .catch(err => {
        console.error(err);
        cells.forEach(cell => {
          const isTd = cell.tagName === 'TD';
          cell.innerHTML = `<span class="text-muted fs-3">-</span>`;
          cell.className = isTd ? "text-center device-status-cell text-muted fs-3" : "device-status-cell mobile-card-value text-muted";
        });
        trafficCells.forEach(cell => {
          cell.innerHTML = `<span class="text-muted fs-3">—</span>`;
        });
        iconWrappers.forEach(wrapper => {
          if (wrapper.dataset.active === '1') {
            wrapper.className = "network-icon-circle d-flex align-items-center justify-content-center rounded-circle device-icon-status-wrapper bg-danger-subtle text-danger";
          } else {
            wrapper.className = "network-icon-circle d-flex align-items-center justify-content-center rounded-circle device-icon-status-wrapper bg-light text-muted";
          }
        });
      })
      .finally(() => {
        isRefreshing = false;
      });
  }

  // Consultar estado al cargar
  updateRealtimeStatuses();

  // Auto-refresh cada 15 segundos para no sobrecargar de llamadas SSH pero mantenerlo vivo
  const statusInterval = setInterval(updateRealtimeStatuses, 15000);

  // Limpiar intervalo al salir de la página por si acaso
  window.addEventListener('beforeunload', () => {
    clearInterval(statusInterval);
  });
});
</script>
