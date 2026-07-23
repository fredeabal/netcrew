<!-- =====================================================================
     CABECERA Y BREADCRUMB (NAVEGACIÓN)
     Muestra el título del panel de usuario y la ruta de navegación.
     ===================================================================== -->
<div class="card border shadow-none position-relative overflow-hidden mb-4">
  <div class="card-body px-4 py-3">
    <div class="row align-items-center">
      <div class="col-9">
        <h4 class="fw-semibold mb-8">Panel de Usuario</h4>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item">
              <a class="text-muted text-decoration-none" href="<?= base_url() ?>">Inicio</a>
            </li>
            <li class="breadcrumb-item" aria-current="page">Dashboard</li>
          </ol>
        </nav>
      </div>
    </div>
  </div>
</div>

<?php
// ---------------------------------------------------------------------
// Lógica para contar cuántas redes del usuario están actualmente activas
// ---------------------------------------------------------------------
$activeNetworksCount = 0;
if (!empty($networks)) {
    foreach ($networks as $net) {
        if ($net->active) $activeNetworksCount++;
    }
}
?>

<!-- =====================================================================
     TARJETAS DE ESTADÍSTICAS (MÉTRICAS DEL USUARIO)
     Muestra el resumen de las redes y dispositivos pertenecientes al usuario.
     ===================================================================== -->
<div class="row">
    <!-- Card Mis Redes -->
    <div class="col-md-4 mb-4">
        <div class="card border shadow-none h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="card-subtitle text-muted mb-0">Mis Redes</h6>
                    <div class="bg-primary-subtle text-primary rounded p-2">
                        <i class="ti ti-network fs-6"></i>
                    </div>
                </div>
                <h3 class="card-title mb-0 fw-semibold"><?= esc($networksCount ?? 0) ?></h3>
                <p class="card-text text-muted fs-2 mt-2">Redes VPN totales creadas.</p>
            </div>
        </div>
    </div>

    <!-- Card Redes Activas -->
    <div class="col-md-4 mb-4">
        <div class="card border shadow-none h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="card-subtitle text-muted mb-0">Redes Activas</h6>
                    <div class="bg-success-subtle text-success rounded p-2">
                        <i class="ti ti-activity fs-6"></i>
                    </div>
                </div>
                <h3 class="card-title mb-0 fw-semibold"><?= esc($activeNetworksCount) ?></h3>
                <p class="card-text text-muted fs-2 mt-2">Redes actualmente encendidas y operativas.</p>
            </div>
        </div>
    </div>

    <!-- Card Dispositivos -->
    <div class="col-md-4 mb-4">
        <div class="card border shadow-none h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="card-subtitle text-muted mb-0">Dispositivos</h6>
                    <div class="bg-info-subtle text-info rounded p-2">
                        <i class="ti ti-devices fs-6"></i>
                    </div>
                </div>
                <h3 class="card-title mb-0 fw-semibold"><?= esc($devicesCount ?? 0) ?></h3>
                <p class="card-text text-muted fs-2 mt-2">Total de nodos en tus redes.</p>
            </div>
        </div>
    </div>
</div>
