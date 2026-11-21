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
            <a href="<?= site_url('networks') ?>" class="btn btn-sm bg-primary-subtle text-primary border-0 btn-square shadow-none" title="Volver a Redes">
              <i class="ti ti-arrow-left"></i>
            </a>
            <a href="<?= site_url('networks/' . $network->id . '/devices/create') ?>" class="btn btn-primary btn-sm border-0 d-flex align-items-center gap-1 shadow-none">
              <i class="ti ti-plus"></i>
              <span>Nuevo Nodo</span>
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
          <?php $netDevicesCount = $totalDevicesCount ?? count($devices); ?>
          <div class="row g-3">
            <div class="col-12 col-sm-6 col-md-3">
              <div class="border p-3 rounded">
                <span class="text-muted d-block small mb-1">Rango CIDR</span>
                <span class="net-mono text-body fw-medium"><?= esc($network->cidr) ?></span>
              </div>
            </div>

            <div class="col-12 col-sm-6 col-md-3">
              <div class="border p-3 rounded">
                <span class="text-muted d-block small mb-1">Estado de Red</span>
                <span class="net-mono fw-medium <?= $network->active ? 'text-body' : 'text-danger' ?>"><?= $network->active ? 'Activa' : 'Inactiva' ?></span>
              </div>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
              <div class="border p-3 rounded">
                <span class="text-muted d-block small mb-1">Total Nodos</span>
                <span class="net-mono text-body fw-medium"><?= $netDevicesCount ?></span>
              </div>
            </div>

            <!-- Capacidad de IPs basada en /24 -->
            <div class="col-12 col-sm-6 col-md-3">
              <div class="border p-3 rounded">
                <span class="text-muted d-block small mb-1">Capacidad (/24)</span>
                <span class="net-mono text-body fw-medium"><?= $netDevicesCount ?> / 254</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- =====================================================================
       TABLA DE NODOS / DISPOSITIVOS
       ===================================================================== -->
  <div class="row">
    <div class="col-12">
      <div class="card">
        <div class="card-body">
          <div class="d-flex align-items-center gap-2 mb-3">
            <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center network-icon-circle">
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
            <div class="text-center py-5">
              <div class="round-48 d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle mb-2">
                <i class="ti ti-devices fs-5"></i>
              </div>
              <h6 class="fw-medium mb-1">Aún no hay nodos</h6>
              <p class="text-muted fs-3 mb-0">Registra tu primer dispositivo para empezar a generar archivos de configuración Nebula (.yml).</p>
            </div>
          <?php else: ?>
            <div class="table-responsive d-none d-md-block">
              <table class="table table-hover align-middle text-nowrap net-table" id="devices-table">
                <thead>
                  <tr class="text-muted fw-semibold">
                    <th scope="col">Dispositivo</th>
                    <th scope="col" class="text-center">IP red</th>
                    <th scope="col" class="text-center">IP física</th>
                    <th scope="col" class="text-center">Última conexión</th>
                    <th scope="col" class="text-end"><span class="visually-hidden">Acciones</span></th>
                  </tr>
                </thead>
                <tbody class="border-top">
                  <?php foreach ($devices as $dev): ?>
                    <tr data-row-href="<?= site_url('devices/show/' . $dev->id) ?>" class="cursor-pointer">
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
                            <h6 class="fw-medium mb-0">
                              <a href="<?= site_url('devices/show/' . $dev->id) ?>" class="<?= $dev->active ? 'text-reset text-primary-hover' : 'text-muted' ?> text-decoration-none">
                                <?= esc($dev->name) ?>
                              </a>
                            </h6>
                          </div>
                        </div>
                      </td>
                      <td class="text-center">
                        <span class="net-mono"><?= esc($dev->ip_address) ?></span>
                      </td>
                      <td class="text-center device-status-cell text-muted" data-pubkey="<?= esc($dev->public_key) ?>" data-active="<?= $dev->active ? '1' : '0' ?>">—</td>
                      <td class="text-center text-muted device-last-seen-cell" data-pubkey="<?= esc($dev->public_key) ?>">
                        <?php if ($dev->last_seen): ?>
                          <h6 class="fw-semibold mb-0 text-login-time"><?= date('d/m/Y', strtotime($dev->last_seen)) ?></h6>
                          <span class="d-block fw-normal text-muted text-login-time"><?= date('H:i', strtotime($dev->last_seen)) ?></span>
                        <?php else: ?>
                          <span class="text-muted">—</span>
                        <?php endif; ?>
                      </td>
                      <td class="text-end" data-stop-propagation="true">
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
                              <a href="javascript:void(0)" class="dropdown-item d-flex align-items-center gap-2 btn-send-email-config" data-id="<?= $dev->id ?>">
                                <i class="ti ti-mail"></i> Enviar por Email
                              </a>
                            </li>
                            <li>
                              <a href="<?= site_url('devices/download/' . $dev->id) ?>" class="dropdown-item d-flex align-items-center gap-2">
                                <i class="ti ti-download"></i> Descargar .yml
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
                <div class="mobile-card-item device-card-item" data-row-href="<?= site_url('devices/show/' . $dev->id) ?>">
                  <div class="mobile-card-header">
                    <div class="d-flex align-items-center gap-2">
                      <div class="network-icon-circle d-flex align-items-center justify-content-center rounded-circle device-icon-status-wrapper flex-shrink-0 <?= $dev->active ? 'bg-danger-subtle text-danger' : 'bg-light text-muted' ?>"
                           data-pubkey="<?= esc($dev->public_key) ?>"
                           data-active="<?= $dev->active ? '1' : '0' ?>">
                        <i class="ti <?= $iconClass ?> fs-5"></i>
                      </div>
                      <div>
                        <h6 class="mobile-card-title"><?= esc($dev->name) ?></h6>
                        <span class="net-mono"><?= esc($dev->ip_address) ?></span>
                      </div>
                    </div>
                    <div class="d-flex align-items-center" data-stop-propagation="true">
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
                            <a href="javascript:void(0)" class="dropdown-item d-flex align-items-center gap-2 btn-send-email-config" data-id="<?= $dev->id ?>">
                              <i class="ti ti-mail"></i> Enviar por Email
                            </a>
                          </li>
                          <li>
                            <a href="<?= site_url('devices/download/' . $dev->id) ?>" class="dropdown-item d-flex align-items-center gap-2">
                              <i class="ti ti-download"></i> Descargar .yml
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
                      <span class="mobile-card-label">IP pública</span>
                      <span class="device-status-cell mobile-card-value text-muted net-mono" data-pubkey="<?= esc($dev->public_key) ?>" data-active="<?= $dev->active ? '1' : '0' ?>">—</span>
                    </div>
                    <div class="mobile-card-row">
                      <span class="mobile-card-label">Última conexión</span>
                      <span class="device-last-seen-cell mobile-card-value text-muted" data-pubkey="<?= esc($dev->public_key) ?>">
                        <?= $dev->last_seen ? date('d/m/Y H:i', strtotime($dev->last_seen)) : '—' ?>
                      </span>
                    </div>
                    <div class="mobile-card-row">
                      <span class="mobile-card-label">Tipo</span>
                      <span class="mobile-card-value text-muted"><?= esc($dev->device_type ?? 'pc') ?> (<?= esc($dev->os ?? 'linux') ?>)</span>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <?php if (!empty($pagerLinks)): ?>
            <div class="mt-4 d-flex justify-content-center">
              <?= $pagerLinks ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

<!-- =====================================================================
     SCRIPTS DE FUNCIONALIDAD (AJAX, EMAIL Y TIEMPO REAL)
     ===================================================================== -->
<script {csp-script-nonce}>
document.addEventListener("DOMContentLoaded", function () {
  document.querySelectorAll('.btn-send-email-config').forEach(btn => {
    btn.addEventListener("click", function() {
      const devId = this.dataset.id;
      if (!devId) return;
      Swal.fire({
        title: 'Enviar Configuración por Correo',
        text: 'Ingresa la dirección de correo a la que deseas enviar el archivo .yml adjunto:',
        input: 'email',
        inputPlaceholder: 'usuario@empresa.com',
        showCancelButton: true,
        confirmButtonText: 'Enviar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#F38020',
        cancelButtonColor: '#dc3545',
        showLoaderOnConfirm: true,
        didOpen: () => {
          setTimeout(() => {
            const input = Swal.getInput();
            if (input) {
              input.focus();
            }
          }, 100);
        },
        preConfirm: (email) => {
          let formData = new FormData();
          formData.append('email', email);
          formData.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

          return fetch(`<?= site_url('devices/send-email/') ?>${devId}`, {
            method: 'POST',
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
              '<?= csrf_header() ?>': '<?= csrf_hash() ?>'
            },
            body: formData
          })
          .then(async response => {
            const data = await response.json().catch(() => null);
            if (!response.ok || !data || !data.success) {
              throw new Error((data && data.message) ? data.message : 'Error al enviar el correo');
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
  // Estado en tiempo real de conexión Nebula P2P
  // ---------------------------------------------------------------------
  let isRefreshing = false;
  function updateRealtimeStatuses() {
    if (isRefreshing) return;
    isRefreshing = true;

    const cells = document.querySelectorAll('.device-status-cell');
    const iconWrappers = document.querySelectorAll('.device-icon-status-wrapper');
    const lastSeenCells = document.querySelectorAll('.device-last-seen-cell');

    // Ruta relativa al origen actual: evita fallos si se accede por una IP/host distinto a app.baseURL
    fetch(`<?= parse_url(site_url('devices/realtime-status/' . $network->id), PHP_URL_PATH) ?>`, { credentials: 'same-origin' })
      .then(response => response.json())
      .then(res => {
        if (!res.success) {
          console.error('Estado en tiempo real no disponible:', res.message);
        }
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
                cell.textContent = '';
                const span = document.createElement('span');
                span.className = 'net-mono';
                span.textContent = publicIp;
                cell.appendChild(span);
                cell.className = isTd ? "text-center device-status-cell" : "device-status-cell mobile-card-value net-mono";
              } else {
                cell.innerHTML = `<span class="text-muted">—</span>`;
                cell.className = isTd ? "text-center device-status-cell text-muted" : "device-status-cell mobile-card-value text-muted";
              }
            } else {
              cell.innerHTML = `<span class="text-muted">—</span>`;
              cell.className = isTd ? "text-center device-status-cell text-muted" : "device-status-cell mobile-card-value text-muted";
            }
          });

          lastSeenCells.forEach(cell => {
            const pubkey = cell.dataset.pubkey;
            const peer = peers[pubkey];

            if (peer && peer.last_seen_date && peer.last_seen_time) {
              cell.textContent = '';
              if (cell.tagName === 'TD') {
                const h6 = document.createElement('h6');
                h6.className = 'fw-semibold mb-0 text-login-time';
                h6.textContent = peer.last_seen_date;
                const span = document.createElement('span');
                span.className = 'd-block fw-normal text-muted text-login-time';
                span.textContent = peer.last_seen_time;
                cell.append(h6, span);
              } else {
                const wrap = document.createElement('span');
                wrap.textContent = peer.last_seen_date + ' ';
                const timeSpan = document.createElement('span');
                timeSpan.className = 'text-muted ms-1';
                timeSpan.textContent = peer.last_seen_time;
                wrap.appendChild(timeSpan);
                cell.appendChild(wrap);
              }
            }
          });

          function setWrapperStatus(wrapper, state) {
            wrapper.classList.remove('bg-danger-subtle', 'text-danger', 'bg-success-subtle', 'text-success', 'bg-light', 'text-muted');
            if (state === 'online') {
              wrapper.classList.add('bg-success-subtle', 'text-success');
            } else if (state === 'offline') {
              wrapper.classList.add('bg-danger-subtle', 'text-danger');
            } else {
              wrapper.classList.add('bg-light', 'text-muted');
            }
          }

          iconWrappers.forEach(wrapper => {
            const pubkey = wrapper.dataset.pubkey;
            const active = wrapper.dataset.active;
            
            if (active === '1') {
              const peer = peers[pubkey];
              setWrapperStatus(wrapper, (peer && peer.connected) ? 'online' : 'offline');
            } else {
              setWrapperStatus(wrapper, 'inactive');
            }
          });
        } else {
          cells.forEach(cell => {
            const isTd = cell.tagName === 'TD';
            cell.innerHTML = `<span class="text-muted fs-3">-</span>`;
            cell.className = isTd ? "text-center device-status-cell text-muted fs-3" : "device-status-cell mobile-card-value text-muted";
          });
          iconWrappers.forEach(wrapper => {
            wrapper.classList.remove('bg-danger-subtle', 'text-danger', 'bg-success-subtle', 'text-success', 'bg-light', 'text-muted');
            if (wrapper.dataset.active === '1') {
              wrapper.classList.add('bg-danger-subtle', 'text-danger');
            } else {
              wrapper.classList.add('bg-light', 'text-muted');
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
        iconWrappers.forEach(wrapper => {
          wrapper.classList.remove('bg-danger-subtle', 'text-danger', 'bg-success-subtle', 'text-success', 'bg-light', 'text-muted');
          if (wrapper.dataset.active === '1') {
            wrapper.classList.add('bg-danger-subtle', 'text-danger');
          } else {
            wrapper.classList.add('bg-light', 'text-muted');
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
