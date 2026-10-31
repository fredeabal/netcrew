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
      <div class="col-12 col-md-4 d-flex justify-content-center justify-content-md-end mt-3 mt-md-0">
        <form method="get" action="<?= site_url('audit-logs') ?>" class="d-flex w-100 w-md-auto">
          <div class="input-group">
            <input type="text" name="q" class="form-control" placeholder="Buscar por usuario, acción, IP..." value="<?= esc($search ?? '') ?>">
            <button class="btn btn-primary" type="submit"><i class="ti ti-search"></i></button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <?php if (empty($logs)): ?>
          <div class="text-center py-5">
            <i class="ti ti-history fs-8 text-muted d-block mb-2"></i>
            <h6 class="fw-semibold">No hay registros de auditoría aún</h6>
            <p class="text-muted mb-0 small">Los eventos de seguridad, cambios en redes, nodos y accesos aparecerán registrados aquí.</p>
          </div>
        <?php else: ?>
          <?php
            // Helper para determinar estilos e icono semántico según la acción
            $getAuditBadgeMeta = function($action) {
                $act = strtoupper($action);
                if (str_contains($act, 'SUCCESS')) {
                    return ['class' => 'bg-success-subtle text-success', 'icon' => 'ti-circle-check'];
                }
                if (str_contains($act, 'FAIL') || str_contains($act, 'DELETE') || str_contains($act, 'ERROR')) {
                    return ['class' => 'bg-danger-subtle text-danger', 'icon' => 'ti-alert-circle'];
                }
                if (str_contains($act, 'LOGOUT')) {
                    return ['class' => 'bg-warning-subtle text-warning', 'icon' => 'ti-logout'];
                }
                if (str_contains($act, 'LOGIN')) {
                    return ['class' => 'bg-info-subtle text-info', 'icon' => 'ti-login'];
                }
                if (str_contains($act, 'CREATE')) {
                    return ['class' => 'bg-primary-subtle text-primary', 'icon' => 'ti-plus'];
                }
                if (str_contains($act, 'UPDATE') || str_contains($act, 'EDIT')) {
                    return ['class' => 'bg-info-subtle text-info', 'icon' => 'ti-pencil'];
                }
                return ['class' => 'bg-secondary-subtle text-secondary', 'icon' => 'ti-activity'];
            };
          ?>

          <!-- VISTA ESCRITORIO: TABLA (d-none d-md-block) -->
          <div class="table-responsive d-none d-md-block">
            <table class="table table-hover align-middle">
              <thead>
                <tr class="text-muted fw-semibold">
                  <th>Fecha y Hora</th>
                  <th>Usuario</th>
                  <th>Acción</th>
                  <th>Detalle</th>
                  <th>IP</th>
                </tr>
              </thead>
              <tbody class="border-top">
                <?php foreach ($logs as $log): ?>
                  <?php $meta = $getAuditBadgeMeta($log->action); ?>
                  <tr>
                    <td>
                      <small class="text-muted"><?= date('d/m/Y H:i:s', strtotime($log->created_at)) ?></small>
                    </td>
                    <td>
                      <span class="fw-semibold text-body">
                        <?= esc($log->username ?? 'Sistema') ?>
                      </span>
                    </td>
                    <td>
                      <span class="badge <?= $meta['class'] ?> fw-semibold badge-audit">
                        <i class="ti <?= $meta['icon'] ?> me-1"></i><?= esc($log->action) ?>
                      </span>
                    </td>
                    <td>
                      <span class="small text-muted"><?= esc($log->description) ?></span>
                    </td>
                    <td>
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
              <div class="mobile-card-item">
                <div class="mobile-card-header">
                  <div class="d-flex align-items-center gap-2">
                    <span class="badge <?= $meta['class'] ?> fw-semibold badge-audit">
                      <i class="ti <?= $meta['icon'] ?> me-1"></i><?= esc($log->action) ?>
                    </span>
                  </div>
                  <small class="text-muted fs-2">
                    <?= date('d/m/Y H:i', strtotime($log->created_at)) ?>
                  </small>
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

          <div class="mt-4">
            <?= $pager->links('audit', 'premium') ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

