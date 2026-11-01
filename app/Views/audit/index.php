<!-- =====================================================================
     CABECERA Y BREADCRUMB
     ===================================================================== -->
<div class="card border shadow-none position-relative overflow-hidden mb-4">
  <div class="card-body px-4 py-3">
    <div class="row align-items-center">
      <div class="col-12 col-md-8 text-center text-md-start">
        <h4 class="fw-semibold mb-2 mb-md-0">Registro de Auditoría</h4>
        <nav aria-label="breadcrumb" class="d-flex justify-content-center justify-content-md-start">
          <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= site_url() ?>">Inicio</a></li>
            <li class="breadcrumb-item active" aria-current="page">Auditoría</li>
          </ol>
        </nav>
      </div>
      <div class="col-12 col-md-4 d-flex justify-content-center justify-content-md-end align-items-center mt-3 mt-md-0">
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <div class="d-flex flex-wrap justify-content-end align-items-center mb-4 gap-3">
          <div class="d-flex align-items-center gap-2 w-100 w-md-auto search-form-responsive ms-auto">
            <form method="get" action="<?= site_url('audit-logs') ?>" class="w-100">
              <div class="position-relative w-100 search-box-container">
                <input type="text" name="q" id="search-audit" class="form-control" placeholder="Buscar por usuario, acción, IP..." value="<?= esc($search ?? '') ?>" autocomplete="off">
                <i class="ti ti-search search-icon text-muted"></i>
              </div>
            </form>
          </div>
        </div>

        <?php if (empty($logs)): ?>
          <div class="text-center py-5">
            <i class="ti ti-history fs-8 text-muted d-block mb-2"></i>
            <h6 class="fw-semibold"><?= !empty($search) ? 'Sin resultados' : 'No hay registros de auditoría aún' ?></h6>
            <p class="text-muted mb-0 small"><?= !empty($search) ? 'Ningún registro coincide con tu búsqueda' : 'Los eventos de seguridad, cambios en redes, nodos y accesos aparecerán registrados aquí.' ?></p>
          </div>
        <?php else: ?>
          <?php
            // Helper para determinar estilos, icono y texto uniforme para cada acción
            $getAuditBadgeMeta = function($action) {
                $act = strtoupper(str_replace(['.', '-'], '_', $action));

                if (str_contains($act, 'LOGIN_SUCCESS') || str_contains($act, 'AUTH_SUCCESS')) {
                    return [
                        'class' => 'bg-success-subtle text-success',
                        'icon'  => 'ti-circle-check',
                        'label' => 'Inicio Sesión'
                    ];
                }
                if (str_contains($act, 'LOGIN_FAIL') || str_contains($act, 'AUTH_FAIL')) {
                    return [
                        'class' => 'bg-danger-subtle text-danger',
                        'icon'  => 'ti-alert-circle',
                        'label' => 'Fallo Acceso'
                    ];
                }
                if (str_contains($act, 'LOGOUT')) {
                    return [
                        'class' => 'bg-warning-subtle text-warning',
                        'icon'  => 'ti-logout',
                        'label' => 'Cierre Sesión'
                    ];
                }
                if (str_contains($act, 'DEVICE_CREATE')) {
                    return [
                        'class' => 'bg-primary-subtle text-primary',
                        'icon'  => 'ti-plus',
                        'label' => 'Nodo Creado'
                    ];
                }
                if (str_contains($act, 'DEVICE_UPDATE') || str_contains($act, 'DEVICE_UPDATED')) {
                    return [
                        'class' => 'bg-info-subtle text-info',
                        'icon'  => 'ti-pencil',
                        'label' => 'Nodo Editado'
                    ];
                }
                if (str_contains($act, 'DEVICE_DELETE')) {
                    return [
                        'class' => 'bg-danger-subtle text-danger',
                        'icon'  => 'ti-trash',
                        'label' => 'Nodo Borrado'
                    ];
                }
                if (str_contains($act, 'CREATE')) {
                    return [
                        'class' => 'bg-primary-subtle text-primary',
                        'icon'  => 'ti-plus',
                        'label' => 'Creación'
                    ];
                }
                if (str_contains($act, 'UPDATE') || str_contains($act, 'EDIT')) {
                    return [
                        'class' => 'bg-info-subtle text-info',
                        'icon'  => 'ti-pencil',
                        'label' => 'Modificación'
                    ];
                }
                if (str_contains($act, 'DELETE')) {
                    return [
                        'class' => 'bg-danger-subtle text-danger',
                        'icon'  => 'ti-trash',
                        'label' => 'Eliminación'
                    ];
                }

                return [
                    'class' => 'bg-secondary-subtle text-secondary',
                    'icon'  => 'ti-activity',
                    'label' => ucwords(strtolower(str_replace(['_', '.'], ' ', $action)))
                ];
            };
          ?>

          <!-- VISTA ESCRITORIO: TABLA (d-none d-md-block) -->
          <div class="table-responsive d-none d-md-block">
            <table class="table table-hover align-middle net-table" id="audit-table">
              <thead>
                <tr class="text-muted fw-semibold">
                  <th>Fecha</th>
                  <th>Usuario</th>
                  <th>Acción</th>
                  <th>Detalle</th>
                  <th class="text-end">IP</th>
                </tr>
              </thead>
              <tbody class="border-top">
                <?php foreach ($logs as $log): ?>
                  <?php $meta = $getAuditBadgeMeta($log->action); ?>
                  <tr>
                    <td>
                      <h6 class="fw-semibold mb-0 text-login-time"><?= date('d/m/Y', strtotime($log->created_at)) ?></h6>
                      <span class="d-block fw-normal text-muted text-login-time"><?= date('H:i:s', strtotime($log->created_at)) ?></span>
                    </td>
                    <td>
                      <span class="fw-semibold text-body">
                        <?= esc($log->username ?? 'Sistema') ?>
                      </span>
                    </td>
                    <td>
                      <span class="badge <?= $meta['class'] ?> fw-semibold badge-action">
                        <?= esc($meta['label']) ?>
                      </span>
                    </td>
                    <td>
                      <span class="small text-muted"><?= esc($log->description) ?></span>
                    </td>
                    <td class="text-end">
                      <code class="small text-muted"><?= esc($log->ip_address) ?></code>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <!-- VISTA MÓVIL: CARDS (d-block d-md-none) -->
          <div class="d-block d-md-none" id="auditCardsContainer">
            <?php foreach ($logs as $log): ?>
              <?php $meta = $getAuditBadgeMeta($log->action); ?>
              <div class="mobile-card-item audit-card-item">
                <div class="mobile-card-header">
                  <div class="d-flex align-items-center gap-2">
                    <span class="badge <?= $meta['class'] ?> fw-semibold badge-action">
                      <?= esc($meta['label']) ?>
                    </span>
                  </div>
                  <div class="text-end">
                    <span class="fw-semibold d-block text-login-time"><?= date('d/m/Y', strtotime($log->created_at)) ?></span>
                    <span class="fw-normal text-muted text-login-time"><?= date('H:i:s', strtotime($log->created_at)) ?></span>
                  </div>
                </div>
                <div class="mobile-card-body">
                  <?php if (!empty($log->description)): ?>
                    <p class="mb-2 fs-3 text-body">
                      <?= esc($log->description) ?>
                    </p>
                  <?php endif; ?>
                  <div class="d-flex align-items-center justify-content-between pt-1 border-top border-opacity-10 border-white">
                    <div class="text-muted fs-2">
                      <span class="fw-semibold text-reset"><?= esc($log->username ?? 'Sistema') ?></span>
                    </div>
                    <code class="text-muted fs-2">
                      <i class="ti ti-world me-1"></i><?= esc($log->ip_address) ?>
                    </code>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <?php if (isset($pager) && $pager->getPageCount('audit') > 1): ?>
          <div class="mt-4 d-flex justify-content-center">
            <?= $pager->links('audit', 'premium') ?>
          </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- =====================================================================
     SCRIPT DE BÚSQUEDA EN TIEMPO REAL
     ===================================================================== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  const searchInput = document.getElementById('search-audit');
  if (searchInput) {
    searchInput.addEventListener('input', function() {
      const filter = this.value.toLowerCase().trim();
      const rows = document.querySelectorAll('#audit-table tbody tr');
      const cards = document.querySelectorAll('.audit-card-item');
      
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
});
</script>

