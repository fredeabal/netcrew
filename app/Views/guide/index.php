<div class="container-fluid">
  <!-- =====================================================================
       CABECERA Y BREADCRUMB (NAVEGACIÓN)
       ===================================================================== -->
  <!-- Cabecera de la Guía -->
  <div class="card border shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body px-4 py-3">
      <div class="row align-items-center">
        <div class="col-9">
          <h4 class="fw-semibold mb-8">Guía de Uso</h4>
          <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
              <li class="breadcrumb-item">
                <a class="text-muted text-decoration-none" href="<?= base_url() ?>">Inicio</a>
              </li>
              <li class="breadcrumb-item" aria-current="page">Guía de Uso</li>
            </ol>
          </nav>
        </div>
      </div>
    </div>
  </div>
  <!-- =====================================================================
       CONTENIDO DE LA GUÍA (PASOS)
       ===================================================================== -->
  <!-- Contenedor de Pasos -->
  <div class="row">
    <!-- Paso 1: Crear la red -->
    <div class="col-lg-4 col-md-6 mb-4">
      <div class="card h-100 border border-dark-border-subtle bg-dark-subtle shadow-sm hover-up">
        <div class="card-body p-4">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <span class="badge bg-primary text-white rounded-pill px-3 py-1 fs-2">Paso 1</span>
            <i class="ti ti-network text-primary fs-7"></i>
          </div>
          <h5 class="fw-bold mb-3">Crear tu Red Privada</h5>
          
          <ul class="list-unstyled mb-0 fs-3 text-muted">
            <li class="d-flex align-items-start gap-2 mb-3">
              <i class="ti ti-circle-check text-primary fs-5 mt-1"></i>
              <span>Ve al menú <strong>Redes</strong> en el panel izquierdo y haz clic en <strong>Nueva Red</strong>.</span>
            </li>
            <li class="d-flex align-items-start gap-2 mb-3">
              <i class="ti ti-circle-check text-primary fs-5 mt-1"></i>
              <span>Asigna un nombre descriptivo (ej: <code class="text-primary">Sucursal Madrid</code>) y define un rango IP de inicio (ej: <code class="text-primary">10.50.0.0</code>).</span>
            </li>
            <li class="d-flex align-items-start gap-2">
              <i class="ti ti-circle-check text-primary fs-5 mt-1"></i>
              <span>El sistema aplicará automáticamente una máscara <strong>/24</strong>, permitiendo hasta 253 dispositivos simultáneos.</span>
            </li>
          </ul>
        </div>
        <div class="card-footer bg-transparent border-0 px-4 pb-4 pt-0">
          <div class="badge bg-primary-subtle text-primary text-wrap text-start fw-normal lh-base fs-2">
            <i class="ti ti-alert-circle"></i> <strong>Importante:</strong> Evita utilizar rangos IP que ya estén en uso en tu red local (LAN) para prevenir conflictos de enrutamiento.
          </div>
        </div>
      </div>
    </div>

    <!-- Paso 2: Registrar Nodos -->
    <div class="col-lg-4 col-md-6 mb-4">
      <div class="card h-100 border border-dark-border-subtle bg-dark-subtle shadow-sm hover-up">
        <div class="card-body p-4">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <span class="badge bg-primary text-white rounded-pill px-3 py-1 fs-2">Paso 2</span>
            <i class="ti ti-device-laptop text-primary fs-7"></i>
          </div>
          <h5 class="fw-bold mb-3">Añadir Nodos a la Red</h5>
          
          <ul class="list-unstyled mb-0 fs-3 text-muted">
            <li class="d-flex align-items-start gap-2 mb-3">
              <i class="ti ti-circle-check text-primary fs-5 mt-1"></i>
              <span>Accede a tu red recién creada y selecciona <strong>Administrar Nodos</strong>.</span>
            </li>
            <li class="d-flex align-items-start gap-2 mb-3">
              <i class="ti ti-circle-check text-primary fs-5 mt-1"></i>
              <span>Haz clic en <strong>Nuevo Nodo</strong>, asígnale un nombre identificativo (ej: <code class="text-primary">Servidor BD</code>) y define su tipo.</span>
            </li>
            <li class="d-flex align-items-start gap-2">
              <i class="ti ti-circle-check text-primary fs-5 mt-1"></i>
              <span>El sistema generará de forma automática sus claves criptográficas y le asignará una IP estática (ej: <code class="text-primary">10.50.0.2</code>).</span>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Paso 3: Conectar a WireGuard -->
    <div class="col-lg-4 col-md-12 mb-4">
      <div class="card h-100 border border-dark-border-subtle bg-dark-subtle shadow-sm hover-up">
        <div class="card-body p-4">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <span class="badge bg-primary text-white rounded-pill px-3 py-1 fs-2">Paso 3</span>
            <i class="ti ti-shield-lock text-primary fs-7"></i>
          </div>
          <h5 class="fw-bold mb-3">Establecer la Conexión VPN</h5>
          
          <ul class="list-unstyled mb-0 fs-3 text-muted">
            <li class="d-flex align-items-start gap-2 mb-3">
              <i class="ti ti-circle-check text-primary fs-5 mt-1"></i>
              <span>Descarga e instala el <a href="https://www.wireguard.com/install/" target="_blank" class="text-primary text-decoration-underline fw-bold">cliente oficial de WireGuard <i class="ti ti-external-link fs-3"></i></a> en tu dispositivo móvil o equipo de escritorio.</span>
            </li>
            <li class="d-flex align-items-start gap-2 mb-3">
              <i class="ti ti-circle-check text-primary fs-5 mt-1"></i>
              <span>En la tabla de Nodos, abre el menú de opciones <i class="ti ti-dots-vertical text-muted"></i> del dispositivo para obtener su código QR o archivo de configuración.</span>
            </li>
            <li class="d-flex align-items-start gap-2 mb-3">
              <i class="ti ti-circle-check text-primary fs-5 mt-1"></i>
              <span><strong>Dispositivos móviles:</strong> Abre WireGuard, añade un nuevo túnel y escanea el código QR mostrado en pantalla.</span>
            </li>
            <li class="d-flex align-items-start gap-2 mb-3">
              <i class="ti ti-circle-check text-primary fs-5 mt-1"></i>
              <span><strong>Equipos de escritorio:</strong> Importa el archivo <code class="text-primary">.conf</code> descargado directamente en tu cliente WireGuard.</span>
            </li>
            <li class="d-flex align-items-start gap-2">
              <i class="ti ti-circle-check text-primary fs-5 mt-1"></i>
              <span>Activa la conexión en tu cliente WireGuard para enlazar el dispositivo a la red de forma segura.</span>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>
