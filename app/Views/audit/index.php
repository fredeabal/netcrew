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
          <div class="alert alert-dark text-center py-5 rounded border-0" role="alert">
            <i class="ti ti-history fs-8 text-muted d-block mb-2"></i>
            <h6 class="fw-semibold text-dark">No hay registros de auditoría aún</h6>
            <p class="text-muted mb-0 small">Los eventos de seguridad, cambios en redes, nodos y accesos aparecerán registrados aquí.</p>
          </div>
        <?php else: ?>
          <div class="table-responsive">
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
                  <tr>
                    <td>
                      <small class="text-muted"><?= date('d/m/Y H:i:s', strtotime($log->created_at)) ?></small>
                    </td>
                    <td>
                      <span class="badge bg-primary-subtle text-primary fw-semibold">
                        <i class="ti ti-user me-1"></i><?= esc($log->username ?? 'Sistema') ?>
                      </span>
                    </td>
                    <td>
                      <span class="badge bg-dark-subtle text-dark fw-semibold">
                        <?= esc($log->action) ?>
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
          <div class="mt-4">
            <?= $pager->links('audit', 'bootstrap_pager') ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

