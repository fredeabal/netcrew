  <!-- Nodos de la Red -->
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
            <button type="button" class="btn btn-primary border-0 d-flex align-items-center gap-2 px-4 shadow-none" data-bs-toggle="modal" data-bs-target="#modal-add-device">
              <i class="ti ti-plus fs-5"></i> Nuevo Nodo
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

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
            <div class="table-responsive">
              <table class="table table-hover align-middle text-nowrap text-center" id="devices-table">
                <thead>
                  <tr class="text-muted fw-semibold">
                    <th scope="col" class="text-start">Nombre del Dispositivo</th>
                    <th scope="col" class="text-center d-none d-md-table-cell">IP Red</th>
                    <th scope="col" class="text-center d-none d-md-table-cell">IP Física</th>
                    <th scope="col" class="text-center d-none d-md-table-cell">Última Conexión</th>
                    <th scope="col" class="text-center d-none d-md-table-cell">Tráfico (↑/↓)</th>
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
                      <td class="text-center d-none d-md-table-cell">
                        <code class="fs-3 text-muted"><?= esc($dev->ip_address) ?></code>
                      </td>
                      <td class="text-center d-none d-md-table-cell device-status-cell bg-danger-subtle text-danger fs-3" data-pubkey="<?= esc($dev->public_key) ?>" data-active="<?= $dev->active ? '1' : '0' ?>">
                        -
                      </td>
                      <td class="text-center d-none d-md-table-cell text-muted device-last-seen-cell" data-pubkey="<?= esc($dev->public_key) ?>">
                        <?php if ($dev->last_seen): ?>
                          <div><?= date('d/m/Y', strtotime($dev->last_seen)) ?></div>
                          <small class="text-muted d-block"><?= date('H:i', strtotime($dev->last_seen)) ?></small>
                        <?php else: ?>
                          <span class="text-muted">—</span>
                        <?php endif; ?>
                      </td>
                      <td class="text-center d-none d-md-table-cell device-traffic-cell" data-pubkey="<?= esc($dev->public_key) ?>">
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
                              <a href="javascript:void(0)" class="dropdown-item d-flex align-items-center gap-2 btn-edit-device" data-id="<?= $dev->id ?>" data-name="<?= esc($dev->name) ?>" data-type="<?= esc($dev->device_type ?? 'pc') ?>">
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
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

<!-- MODAL: AGREGAR NUEVO DISPOSITIVO -->
<div class="modal fade" id="modal-add-device" tabindex="-1" aria-labelledby="modalAddDeviceLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-semibold" id="modalAddDeviceLabel">Agregar Nuevo Nodo</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="form-add-device-page">
        <div class="modal-body py-4">
          <?= csrf_field() ?>
          <input type="hidden" name="network_id" value="<?= $network->id ?>">
          <div class="mb-3">
            <label for="device-name" class="form-label">Nombre del Dispositivo</label>
            <input type="text" class="form-control" id="device-name" name="name" placeholder="Ej: Laptop Oficina" required minlength="2" maxlength="100">
            <small class="text-muted d-block mb-3">Se generará su IP privada y el par de llaves WireGuard.</small>
          </div>
          <div class="mb-3">
            <label for="device-type" class="form-label">Tipo de Dispositivo</label>
            <select class="form-select" id="device-type" name="device_type">
              <option value="pc">Computadora / Laptop (PC)</option>
              <option value="server">Servidor</option>
              <option value="mobile">Teléfono</option>
              <option value="tablet">Tablet</option>
              <option value="router">Router / Firewall</option>
            </select>
          </div>
        </div>
        <div class="modal-footer border-top-0 pt-0">
          <button type="button" class="btn btn-danger border-0" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary border-0" id="btn-save-device">Generar Nodo</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL: EDITAR DISPOSITIVO -->
<div class="modal fade" id="modal-edit-device" tabindex="-1" aria-labelledby="modalEditDeviceLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-semibold" id="modalEditDeviceLabel">Editar Nodo</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="form-edit-device-page">
        <div class="modal-body py-4">
          <?= csrf_field() ?>
          <input type="hidden" name="device_id" id="edit-device-id" value="">
          <div class="mb-3">
            <label for="edit-device-name" class="form-label">Nombre del Dispositivo</label>
            <input type="text" class="form-control" id="edit-device-name" name="name" placeholder="Ej: Laptop Oficina" required minlength="2" maxlength="100">
          </div>
          <div class="mb-3">
            <label for="edit-device-type" class="form-label">Tipo de Dispositivo</label>
            <select class="form-select" id="edit-device-type" name="device_type">
              <option value="pc">Computadora / Laptop (PC)</option>
              <option value="server">Servidor</option>
              <option value="mobile">Teléfono</option>
              <option value="tablet">Tablet</option>
              <option value="router">Router / Firewall</option>
            </select>
          </div>
        </div>
        <div class="modal-footer border-top-0 pt-0">
          <button type="button" class="btn btn-danger border-0" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary border-0" id="btn-update-device">Guardar Cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL: VER CONFIGURACIÓN / QR -->
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

        <div class="d-flex gap-2 justify-content-center mt-4">
          <button type="button" class="btn btn-danger border-0" data-bs-dismiss="modal">Cerrar</button>
          <a href="#" class="btn btn-primary border-0 d-flex align-items-center gap-2" id="btn-download-config-modal">
            <i class="ti ti-download"></i> Descargar archivo .conf
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Librería QR -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
  let qrcode = null;
  const qrcodeContainer = document.getElementById("qrcode-container-page");
  const btnDownloadConfigModal = document.getElementById("btn-download-config-modal");
  
  const modalViewConfig = new bootstrap.Modal(document.getElementById('modal-view-config'));
  const modalAddDevice = new bootstrap.Modal(document.getElementById('modal-add-device'));

  // Manejo de la previsualización del QR y archivo de configuración
  document.querySelectorAll('.btn-view-config').forEach(btn => {
    btn.addEventListener('click', function() {
      const devId = this.dataset.id;
      
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

  // Envío del formulario de agregar dispositivo mediante AJAX para mostrar la configuración al instante
  const formAddDevice = document.getElementById('form-add-device-page');
  formAddDevice.addEventListener('submit', function (e) {
    e.preventDefault();
    
    const formData = new FormData(formAddDevice);
    const btnSubmit = document.getElementById('btn-save-device');
    btnSubmit.disabled = true;
    btnSubmit.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Generando...`;

    fetch('<?= site_url('devices/store') ?>', {
      method: 'POST',
      body: formData,
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
    .then(response => response.json())
    .then(res => {
      btnSubmit.disabled = false;
      btnSubmit.innerHTML = 'Generar Nodo';
      
      if (res.success) {
        // Cerrar modal de agregar
        modalAddDevice.hide();
        formAddDevice.reset();

        // Mostrar SweetAlert Toast de éxito
        const Toast = Swal.mixin({
          toast: true,
          position: 'bottom',
          showConfirmButton: false,
          timer: 5000,
          timerProgressBar: true,
          background: '#121a1f',
          color: '#fff'
        });

        Toast.fire({
          icon: 'success',
          title: res.message
        });

        // Cargar inmediatamente la configuración generada en el modal correspondiente
        qrcodeContainer.innerHTML = "";

        fetch(`<?= site_url('devices/details-json/') ?>${res.device.id}`)
          .then(r => r.json())
          .then(cRes => {
            if (cRes.success) {
              btnDownloadConfigModal.href = `<?= site_url('devices/download/') ?>${cRes.device.id}`;
              
              qrcode = new QRCode(qrcodeContainer, {
                text: cRes.device.config,
                width: 250,
                height: 250,
                colorDark : "#000000",
                colorLight : "#ffffff",
                correctLevel : QRCode.CorrectLevel.M
              });

              // Mostrar modal de configuración generada
              modalViewConfig.show();

              // Recargar la página al cerrar el modal de configuración para ver el nuevo nodo en la tabla
              document.getElementById('modal-view-config').addEventListener('hidden.bs.modal', function () {
                window.location.reload();
              }, { once: true });
            } else {
              window.location.reload();
            }
          });
      } else {
        Swal.fire({
          icon: 'error',
          title: 'Error de Validación',
          text: res.message || 'No se pudo crear el dispositivo.'
        });
      }
    })
    .catch(err => {
      btnSubmit.disabled = false;
      btnSubmit.innerHTML = 'Generar Nodo';
      console.error(err);
      Swal.fire({
        icon: 'error',
        title: 'Error de Red',
        text: 'Ocurrió un error de red al intentar guardar.'
      });
    });
  });

  // ---------------------------------------------------------------------
  // Editar dispositivo
  // ---------------------------------------------------------------------
  const modalEditEl = document.getElementById('modal-edit-device');
  const modalEdit = modalEditEl ? new bootstrap.Modal(modalEditEl) : null;

  document.querySelectorAll('.btn-edit-device').forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      document.getElementById('edit-device-id').value = this.dataset.id;
      document.getElementById('edit-device-name').value = this.dataset.name;
      document.getElementById('edit-device-type').value = this.dataset.type;
      modalEdit.show();
    });
  });

  const formEdit = document.getElementById('form-edit-device-page');
  if (formEdit) {
    formEdit.addEventListener('submit', function(e) {
      e.preventDefault();
      const btnSubmit = document.getElementById('btn-update-device');
      btnSubmit.disabled = true;
      btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Guardando...';

      const formData = new FormData(this);
      const deviceId = formData.get('device_id');

      fetch('<?= base_url('devices/update/') ?>' + deviceId, {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
      .then(response => response.json())
      .then(res => {
        btnSubmit.disabled = false;
        btnSubmit.innerHTML = 'Guardar Cambios';
        if (res.success) {
          modalEdit.hide();
          Swal.fire({
            icon: 'success',
            title: '¡Actualizado!',
            text: res.message,
            timer: 2000,
            showConfirmButton: false
          }).then(() => {
            window.location.reload();
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: res.message || 'No se pudo actualizar.'
          });
        }
      })
      .catch(err => {
        btnSubmit.disabled = false;
        btnSubmit.innerHTML = 'Guardar Cambios';
        Swal.fire({
          icon: 'error',
          title: 'Error de Red',
          text: 'Ocurrió un error al intentar guardar los cambios.'
        });
      });
    });
  }

  // Buscador de nodos en tiempo real (nombre o IP)
  const searchInput = document.getElementById('search-devices');
  if (searchInput) {
    searchInput.addEventListener('input', function() {
      const filter = this.value.toLowerCase().trim();
      const rows = document.querySelectorAll('#devices-table tbody tr');
      
      rows.forEach(row => {
        const nameEl = row.querySelector('.text-start h6');
        const ipEl = row.querySelector('.text-center code');
        
        const nameText = nameEl ? nameEl.textContent.toLowerCase() : '';
        const ipText = ipEl ? ipEl.textContent.toLowerCase() : '';
        
        if (nameText.includes(filter) || ipText.includes(filter)) {
          row.classList.remove('d-none');
        } else {
          row.classList.add('d-none');
        }
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
            
            if (active === '1') {
              const peer = peers[pubkey];
              if (peer && peer.connected) {
                const publicIp = peer.endpoint ? peer.endpoint.split(':')[0] : 'IP desconocida';
                cell.innerHTML = `<code class="fs-3 text-dark">${publicIp}</code>`;
                cell.className = "text-center d-none d-md-table-cell device-status-cell";
              } else {
                cell.innerHTML = `<span class="text-muted fs-3">-</span>`;
                cell.className = "text-center d-none d-md-table-cell device-status-cell text-muted fs-3";
              }
            } else {
              cell.innerHTML = `<span class="text-muted fs-3">-</span>`;
              cell.className = "text-center d-none d-md-table-cell device-status-cell text-muted fs-3";
            }
          });

          lastSeenCells.forEach(cell => {
            const pubkey = cell.dataset.pubkey;
            const peer = peers[pubkey];

            if (peer && peer.last_seen_date && peer.last_seen_time) {
              cell.innerHTML = `<div>${peer.last_seen_date}</div><small class="text-muted d-block">${peer.last_seen_time}</small>`;
            }
          });

          trafficCells.forEach(cell => {
            const pubkey = cell.dataset.pubkey;
            const peer = peers[pubkey];

            if (peer && (peer.rx > 0 || peer.tx > 0)) {
              cell.innerHTML = `
                <div class="text-info small fw-semibold" title="Subida (Enviado)"><i class="ti ti-arrow-up"></i> ${formatBytes(peer.rx)}</div>
                <div class="text-success small fw-semibold mt-1" title="Descarga (Recibido)"><i class="ti ti-arrow-down"></i> ${formatBytes(peer.tx)}</div>
              `;
            } else {
              cell.innerHTML = `<span class="text-muted fs-3">—</span>`;
            }
          });

          iconWrappers.forEach(wrapper => {
            const pubkey = wrapper.dataset.pubkey;
            const active = wrapper.dataset.active;
            
            if (active === '1') {
              const peer = peers[pubkey];
              if (peer && peer.connected) {
                wrapper.className = "network-icon-circle d-flex align-items-center justify-content-center rounded-circle me-3 device-icon-status-wrapper bg-success-subtle text-success";
              } else {
                wrapper.className = "network-icon-circle d-flex align-items-center justify-content-center rounded-circle me-3 device-icon-status-wrapper bg-danger-subtle text-danger";
              }
            } else {
              wrapper.className = "network-icon-circle d-flex align-items-center justify-content-center rounded-circle me-3 device-icon-status-wrapper bg-light text-muted";
            }
          });
        } else {
          cells.forEach(cell => {
            cell.innerHTML = `<span class="text-muted fs-3">-</span>`;
            cell.className = "text-center d-none d-md-table-cell device-status-cell text-muted fs-3";
          });
          trafficCells.forEach(cell => {
            cell.innerHTML = `<span class="text-muted fs-3">—</span>`;
          });
          iconWrappers.forEach(wrapper => {
            if (wrapper.dataset.active === '1') {
              wrapper.className = "network-icon-circle d-flex align-items-center justify-content-center rounded-circle me-3 device-icon-status-wrapper bg-danger-subtle text-danger";
            } else {
              wrapper.className = "network-icon-circle d-flex align-items-center justify-content-center rounded-circle me-3 device-icon-status-wrapper bg-light text-muted";
            }
          });
        }
      })
      .catch(err => {
        console.error(err);
        cells.forEach(cell => {
          cell.innerHTML = `<span class="text-muted fs-3">-</span>`;
          cell.className = "text-center d-none d-md-table-cell device-status-cell text-muted fs-3";
        });
        trafficCells.forEach(cell => {
          cell.innerHTML = `<span class="text-muted fs-3">—</span>`;
        });
        iconWrappers.forEach(wrapper => {
          if (wrapper.dataset.active === '1') {
            wrapper.className = "network-icon-circle d-flex align-items-center justify-content-center rounded-circle me-3 device-icon-status-wrapper bg-danger-subtle text-danger";
          } else {
            wrapper.className = "network-icon-circle d-flex align-items-center justify-content-center rounded-circle me-3 device-icon-status-wrapper bg-light text-muted";
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
