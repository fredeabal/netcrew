<!-- =====================================================================
     CABECERA Y BREADCRUMB (NAVEGACIÓN)
     ===================================================================== -->
<div class="card border shadow-none position-relative overflow-hidden mb-4">
  <div class="card-body px-4 py-3">
    <div class="row align-items-center">
      <div class="col-8">
        <h4 class="fw-semibold mb-1">Guía de Uso</h4>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
              <a class="text-muted text-decoration-none" href="<?= base_url() ?>">Inicio</a>
            </li>
            <li class="breadcrumb-item text-muted" aria-current="page">Guía de Uso</li>
          </ol>
        </nav>
      </div>
      <div class="col-4 d-flex justify-content-end align-items-center">
        <a href="<?= base_url('dashboard') ?>" class="btn btn-sm bg-primary-subtle text-primary border-0 btn-square shadow-none" title="Volver al panel">
          <i class="ti ti-arrow-left fs-5"></i>
        </a>
      </div>
    </div>
  </div>
</div>

<!-- =====================================================================
     PASOS DE CONFIGURACIÓN (TARJETAS NETCREW)
     ===================================================================== -->
<div class="row">
  <!-- Paso 1: Crear la red -->
  <div class="col-lg-4 col-md-12 mb-4">
    <div class="card h-100 border shadow-none d-flex flex-column">
      <div class="card-body p-4 flex-grow-1">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <span class="badge bg-primary-subtle text-primary fw-semibold px-3 py-2 fs-2">Paso 1</span>
          <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center network-icon-circle flex-shrink-0">
            <i class="ti ti-network fs-5"></i>
          </div>
        </div>

        <h5 class="fw-semibold mb-2">Crear tu Red Privada</h5>
        <p class="text-muted small mb-4">Define el segmento VPN y el rango IP asignado.</p>

        <ul class="list-unstyled d-flex flex-column gap-3 mb-0 fs-3 text-muted">
          <li class="d-flex align-items-start gap-2">
            <span class="badge bg-primary-subtle text-primary rounded-circle p-1 d-flex align-items-center justify-content-center flex-shrink-0 mt-1" style="width:20px;height:20px;font-size:11px;">1</span>
            <span>Ve al menú <strong class="text-body">Redes</strong> en el panel izquierdo y haz clic en <strong>Nueva Red</strong>.</span>
          </li>
          <li class="d-flex align-items-start gap-2">
            <span class="badge bg-primary-subtle text-primary rounded-circle p-1 d-flex align-items-center justify-content-center flex-shrink-0 mt-1" style="width:20px;height:20px;font-size:11px;">2</span>
            <span>Asigna un nombre descriptivo (ej: <code class="text-primary font-monospace">Sucursal Madrid</code>) y define un rango IP de inicio (ej: <code class="text-primary font-monospace">10.50.0.0</code>).</span>
          </li>
          <li class="d-flex align-items-start gap-2">
            <span class="badge bg-primary-subtle text-primary rounded-circle p-1 d-flex align-items-center justify-content-center flex-shrink-0 mt-1" style="width:20px;height:20px;font-size:11px;">3</span>
            <span>El sistema aplicará automáticamente una máscara <strong class="text-body font-monospace">/24</strong>, permitiendo hasta 253 dispositivos simultáneos.</span>
          </li>
        </ul>
      </div>

      <div class="card-footer bg-transparent border-top-0 px-4 pb-4 pt-0 mt-auto">
        <div class="p-3 rounded-2 bg-primary-subtle d-flex gap-2 align-items-start">
          <i class="ti ti-info-circle text-primary fs-5 flex-shrink-0 mt-1"></i>
          <span class="fs-2 text-primary">
            Evita rangos que coincidan con tu red local (LAN) para prevenir conflictos de enrutamiento.
          </span>
        </div>
      </div>
    </div>
  </div>

  <!-- Paso 2: Registrar Nodos -->
  <div class="col-lg-4 col-md-12 mb-4">
    <div class="card h-100 border shadow-none d-flex flex-column">
      <div class="card-body p-4 flex-grow-1">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <span class="badge bg-primary-subtle text-primary fw-semibold px-3 py-2 fs-2">Paso 2</span>
          <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center network-icon-circle flex-shrink-0">
            <i class="ti ti-device-laptop fs-5"></i>
          </div>
        </div>

        <h5 class="fw-semibold mb-2">Añadir Nodos a la Red</h5>
        <p class="text-muted small mb-4">Añade servidores, ordenadores o smartphones al segmento.</p>

        <ul class="list-unstyled d-flex flex-column gap-3 mb-0 fs-3 text-muted">
          <li class="d-flex align-items-start gap-2">
            <span class="badge bg-primary-subtle text-primary rounded-circle p-1 d-flex align-items-center justify-content-center flex-shrink-0 mt-1" style="width:20px;height:20px;font-size:11px;">1</span>
            <span>Accede a tu red recién creada y selecciona <strong class="text-body">Administrar Nodos</strong>.</span>
          </li>
          <li class="d-flex align-items-start gap-2">
            <span class="badge bg-primary-subtle text-primary rounded-circle p-1 d-flex align-items-center justify-content-center flex-shrink-0 mt-1" style="width:20px;height:20px;font-size:11px;">2</span>
            <span>Haz clic en <strong>Nuevo Nodo</strong>, asígnale un nombre (ej: <code class="text-primary font-monospace">Servidor BD</code>) y define su tipo.</span>
          </li>
          <li class="d-flex align-items-start gap-2">
            <span class="badge bg-primary-subtle text-primary rounded-circle p-1 d-flex align-items-center justify-content-center flex-shrink-0 mt-1" style="width:20px;height:20px;font-size:11px;">3</span>
            <span>El sistema generará de forma automática sus claves criptográficas y le asignará una IP estática (ej: <code class="text-primary font-monospace">10.50.0.2</code>).</span>
          </li>
        </ul>
      </div>

      <div class="card-footer bg-transparent border-top-0 px-4 pb-4 pt-0 mt-auto">
        <div class="p-3 rounded-2 bg-primary-subtle d-flex gap-2 align-items-start">
          <i class="ti ti-key text-primary fs-5 flex-shrink-0 mt-1"></i>
          <span class="fs-2 text-primary">
            Las claves criptográficas se gestionan y aíslan de forma segura en cada nodo.
          </span>
        </div>
      </div>
    </div>
  </div>

  <!-- Paso 3: Conectar a WireGuard -->
  <div class="col-lg-4 col-md-12 mb-4">
    <div class="card h-100 border shadow-none d-flex flex-column">
      <div class="card-body p-4 flex-grow-1">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <span class="badge bg-primary-subtle text-primary fw-semibold px-3 py-2 fs-2">Paso 3</span>
          <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center network-icon-circle flex-shrink-0">
            <i class="ti ti-shield-lock fs-5"></i>
          </div>
        </div>

        <h5 class="fw-semibold mb-2">Establecer la Conexión VPN</h5>
        <p class="text-muted small mb-4">Enlaza tus equipos usando el cliente oficial de WireGuard.</p>

        <ul class="list-unstyled d-flex flex-column gap-3 mb-0 fs-3 text-muted">
          <li class="d-flex align-items-start gap-2">
            <span class="badge bg-primary-subtle text-primary rounded-circle p-1 d-flex align-items-center justify-content-center flex-shrink-0 mt-1" style="width:20px;height:20px;font-size:11px;">1</span>
            <span>Descarga e instala el <a href="https://www.wireguard.com/install/" target="_blank" rel="noopener" class="text-primary text-decoration-none fw-semibold">cliente oficial de WireGuard <i class="ti ti-external-link fs-2"></i></a> en tu dispositivo.</span>
          </li>
          <li class="d-flex align-items-start gap-2">
            <span class="badge bg-primary-subtle text-primary rounded-circle p-1 d-flex align-items-center justify-content-center flex-shrink-0 mt-1" style="width:20px;height:20px;font-size:11px;">2</span>
            <span>En la tabla de Nodos, abre el menú de opciones <i class="ti ti-dots-vertical text-muted"></i> del dispositivo para ver su código QR o archivo de configuración.</span>
          </li>
          <li class="d-flex align-items-start gap-2">
            <span class="badge bg-primary-subtle text-primary rounded-circle p-1 d-flex align-items-center justify-content-center flex-shrink-0 mt-1" style="width:20px;height:20px;font-size:11px;">3</span>
            <span><strong>Móviles:</strong> Escanea el QR con la cámara de la app. <strong>Desktop:</strong> Importa el archivo <code class="text-primary font-monospace">.conf</code> descargado.</span>
          </li>
        </ul>
      </div>

      <div class="card-footer bg-transparent border-top-0 px-4 pb-4 pt-0 mt-auto">
        <div class="p-3 rounded-2 bg-primary-subtle d-flex gap-2 align-items-start">
          <i class="ti ti-circle-check text-primary fs-5 flex-shrink-0 mt-1"></i>
          <span class="fs-2 text-primary">
            Listo. El nodo aparecerá conectado y con tráfico en tiempo real.
          </span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- =====================================================================
     BOTONERA DE ACCIONES: RESPONSIVE Y CENTRADOS (LIBRO DE DISEÑO)
     ===================================================================== -->
<div class="mt-2 pt-3 border-top">
  <div class="row g-2 justify-content-center align-items-center">
    <div class="col-6 col-sm-auto">
      <a href="<?= base_url('dashboard') ?>" class="btn btn-dark btn-sm w-100 px-sm-4 py-2 text-nowrap d-flex align-items-center justify-content-center">
        <i class="ti ti-arrow-left me-1"></i>Volver al Panel
      </a>
    </div>
    <div class="col-6 col-sm-auto">
      <a href="<?= base_url('networks') ?>" class="btn btn-primary btn-sm w-100 px-sm-4 py-2 text-nowrap d-flex align-items-center justify-content-center">
        <i class="ti ti-network me-1"></i>Ir a Redes
      </a>
    </div>
  </div>
</div>
