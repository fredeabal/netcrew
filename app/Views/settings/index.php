<!-- =====================================================================
     CABECERA Y BREADCRUMB (NAVEGACIÓN)
     ===================================================================== -->
<div class="card border shadow-none position-relative overflow-hidden mb-4">
  <div class="card-body px-4 py-3">
    <div class="row align-items-center">
      <div class="col-12 col-md-8 text-center text-md-start">
        <h4 class="fw-semibold mb-2 mb-md-0">Configuración</h4>
        <nav aria-label="breadcrumb" class="d-flex justify-content-center justify-content-md-start">
          <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= base_url('dashboard') ?>">Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">Configuración</li>
          </ol>
        </nav>
      </div>
      <div class="col-12 col-md-4 text-center text-md-end mt-2 mt-md-0">
        <?php $hasBinary = !empty($binary_status['local']) || !empty($binary_status['remote']); ?>
        <div class="d-inline-flex align-items-center gap-2 px-2 py-1 rounded-2 <?= $hasBinary ? 'status-pill-success' : 'status-pill-warning' ?> fs-2 fw-semibold">
          <span class="status-indicator-dot <?= $hasBinary ? 'status-online' : 'status-offline' ?>"></span>
          <span><?= $hasBinary ? 'Online' : 'No online' ?></span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- =====================================================================
     NAVEGACIÓN POR PESTAÑAS (TABS)
     ===================================================================== -->
<ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link <?= $currentTab === 'nebula' ? 'active' : '' ?>"
            id="tab-nebula-btn"
            data-bs-toggle="tab"
            data-bs-target="#tab-nebula"
            type="button"
            role="tab"
            aria-controls="tab-nebula"
            aria-selected="<?= $currentTab === 'nebula' ? 'true' : 'false' ?>">
      <i class="ti ti-radar me-1"></i> Nebula P2P
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link <?= $currentTab === 'smtp' ? 'active' : '' ?>"
            id="tab-smtp-btn"
            data-bs-toggle="tab"
            data-bs-target="#tab-smtp"
            type="button"
            role="tab"
            aria-controls="tab-smtp"
            aria-selected="<?= $currentTab === 'smtp' ? 'true' : 'false' ?>">
      <i class="ti ti-mail-fast me-1"></i> Servidor SMTP
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link <?= $currentTab === 'audit' ? 'active' : '' ?>"
            id="tab-audit-btn"
            data-bs-toggle="tab"
            data-bs-target="#tab-audit"
            type="button"
            role="tab"
            aria-controls="tab-audit"
            aria-selected="<?= $currentTab === 'audit' ? 'true' : 'false' ?>">
      <i class="ti ti-history me-1"></i> Auditoría
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link <?= $currentTab === 'maintenance' ? 'active' : '' ?>"
            id="tab-maintenance-btn"
            data-bs-toggle="tab"
            data-bs-target="#tab-maintenance"
            type="button"
            role="tab"
            aria-controls="tab-maintenance"
            aria-selected="<?= $currentTab === 'maintenance' ? 'true' : 'false' ?>">
      <i class="ti ti-tools me-1"></i> Mantenimiento
    </button>
  </li>
</ul>

<!-- =====================================================================
     CONTENIDO DE LAS PESTAÑAS
     ===================================================================== -->
<div class="tab-content" id="settingsTabsContent">
  <div class="tab-pane fade <?= $currentTab === 'nebula' ? 'show active' : '' ?>" id="tab-nebula" role="tabpanel" aria-labelledby="tab-nebula-btn">
    <?= view('settings/_nebula', get_defined_vars()) ?>
  </div>

  <div class="tab-pane fade <?= $currentTab === 'smtp' ? 'show active' : '' ?>" id="tab-smtp" role="tabpanel" aria-labelledby="tab-smtp-btn">
    <?= view('settings/_smtp', get_defined_vars()) ?>
  </div>

  <div class="tab-pane fade <?= $currentTab === 'audit' ? 'show active' : '' ?>" id="tab-audit" role="tabpanel" aria-labelledby="tab-audit-btn">
    <?= view('settings/_audit', get_defined_vars()) ?>
  </div>

  <div class="tab-pane fade <?= $currentTab === 'maintenance' ? 'show active' : '' ?>" id="tab-maintenance" role="tabpanel" aria-labelledby="tab-maintenance-btn">
    <?= view('settings/_maintenance', get_defined_vars()) ?>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  // Sincronizar pestaña activa con ?tab=... en la URL
  const tabButtons = document.querySelectorAll('#settingsTabs button[data-bs-toggle="tab"]');
  tabButtons.forEach(btn => {
    btn.addEventListener('shown.bs.tab', function (e) {
      const targetId = e.target.getAttribute('data-bs-target').replace('#tab-', '');
      const newUrl = new URL(window.location.href);
      newUrl.searchParams.set('tab', targetId);
      window.history.replaceState({}, '', newUrl.toString());
    });
  });
});
</script>
