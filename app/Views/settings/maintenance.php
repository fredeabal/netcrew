<!-- =====================================================================
     CABECERA Y BREADCRUMB (NAVEGACIÓN)
     ===================================================================== -->
<div class="card shadow-none border position-relative overflow-hidden mb-4">
    <div class="card-body px-4 py-3">
        <div class="row align-items-center">
            <div class="col-8">
                <h4 class="fw-semibold mb-8">Mantenimiento del Sistema</h4>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= base_url('dashboard') ?>">Dashboard</a></li>
                        <li class="breadcrumb-item text-muted" aria-current="page">Mantenimiento</li>
                    </ol>
                </nav>
            </div>
            <div class="col-4 text-end">
                <span class="badge <?= ($stats['isWgUp'] ?? false) ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' ?> px-3 py-2 fs-2 fw-semibold">
                    <i class="ti <?= ($stats['isWgUp'] ?? false) ? 'ti-check' : 'ti-alert-circle' ?> me-1"></i>
                    WireGuard <?= ($stats['isWgUp'] ?? false) ? 'Online' : 'Inactivo' ?>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- =====================================================================
     MÉTRICAS Y ESTADO DE ALMACENAMIENTO DEL SISTEMA
     ===================================================================== -->
<div class="row mb-2">
    <!-- Base de Datos -->
    <div class="col-sm-6 col-xl-3 mb-4">
        <div class="card border shadow-none h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="card-subtitle text-muted mb-0">Base de Datos</h6>
                    <div class="bg-primary-subtle text-primary rounded p-2">
                        <i class="ti ti-database fs-6"></i>
                    </div>
                </div>
                <h3 class="card-title mb-0 fw-semibold"><?= esc($stats['dbSize'] ?? '0 B') ?></h3>
                <p class="card-text text-muted fs-2 mt-2">Tamaño de la base SQLite.</p>
            </div>
        </div>
    </div>

    <!-- Sesiones -->
    <div class="col-sm-6 col-xl-3 mb-4">
        <div class="card border shadow-none h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="card-subtitle text-muted mb-0">Sesiones Guardadas</h6>
                    <div class="bg-info-subtle text-info rounded p-2">
                        <i class="ti ti-users fs-6"></i>
                    </div>
                </div>
                <h3 class="card-title mb-0 fw-semibold"><?= esc($stats['sessionCount'] ?? 0) ?></h3>
                <p class="card-text text-muted fs-2 mt-2">Ocupan <?= esc($stats['sessionSize'] ?? '0 B') ?> en disco.</p>
            </div>
        </div>
    </div>

    <!-- Logs -->
    <div class="col-sm-6 col-xl-3 mb-4">
        <div class="card border shadow-none h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="card-subtitle text-muted mb-0">Logs del Sistema</h6>
                    <div class="bg-warning-subtle text-warning rounded p-2">
                        <i class="ti ti-file-text fs-6"></i>
                    </div>
                </div>
                <h3 class="card-title mb-0 fw-semibold"><?= esc($stats['logsCount'] ?? 0) ?></h3>
                <p class="card-text text-muted fs-2 mt-2">Acumulan <?= esc($stats['logsSize'] ?? '0 B') ?> en disco.</p>
            </div>
        </div>
    </div>

    <!-- Caché -->
    <div class="col-sm-6 col-xl-3 mb-4">
        <div class="card border shadow-none h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="card-subtitle text-muted mb-0">Caché Temporal</h6>
                    <div class="bg-success-subtle text-success rounded p-2">
                        <i class="ti ti-archive fs-6"></i>
                    </div>
                </div>
                <h3 class="card-title mb-0 fw-semibold"><?= esc($stats['cacheCount'] ?? 0) ?></h3>
                <p class="card-text text-muted fs-2 mt-2">Ocupan <?= esc($stats['cacheSize'] ?? '0 B') ?> en disco.</p>
            </div>
        </div>
    </div>
</div>

<!-- =====================================================================
     OPCIONES DE MANTENIMIENTO
     ===================================================================== -->
<div class="row">
    <!-- Tarjeta de Mantenimiento General -->
    <div class="col-12 mb-4">
        <div class="card border bg-light-primary">
            <div class="card-body">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <i class="ti ti-wand fs-6 text-primary"></i>
                        <h5 class="card-title fw-bold text-primary mb-0">Mantenimiento Completo</h5>
                    </div>
                    <p class="card-text text-muted mb-0">Ejecuta todas las acciones de mantenimiento simultáneamente: limpia sesiones inactivas, archivos de depuración, logs, caché temporal, optimiza la base de datos (VACUUM) y sincroniza los peers y reglas de firewall de WireGuard.</p>
                </div>
                <div class="mt-3">
                    <form action="<?= base_url('settings/maintenance/clear-all') ?>" method="POST" data-confirm="¿Estás seguro de que deseas ejecutar el mantenimiento completo del sistema? Se limpiarán temporales, caché y se optimizará la base de datos.">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-primary py-2 shadow-none">
                            <i class="ti ti-wand me-1"></i> Ejecutar Mantenimiento Completo
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= SECCIÓN: DATOS Y RESPALDOS ================= -->

    <!-- Respaldos (Backup) -->
    <div class="col-12 mb-4">
        <div class="card shadow-none border">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="p-2 rounded bg-light-primary text-primary d-flex align-items-center justify-content-center">
                        <i class="ti ti-database fs-7"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0">Respaldo y Restauración de Base de Datos</h5>
                        <p class="text-muted fs-2 mb-0">Copia de seguridad en caliente con VACUUM y restauración íntegra</p>
                    </div>
                </div>
                <p class="text-muted mb-4">Descarga una copia de seguridad completa del sistema actual en formato SQLite o sube un archivo de respaldo para restaurarlo. <strong>Atención:</strong> Al restaurar, se reemplazará la base de datos actual con la del respaldo.</p>
                
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="h-100 p-4 border rounded d-flex flex-column justify-content-between">
                            <div>
                                <h6 class="fw-bold mb-2">Crear Copia de Seguridad</h6>
                                <p class="text-muted fs-3 mb-4">Genera una instantánea consistente e inmutable de la base de datos para conservarla en un lugar seguro.</p>
                            </div>
                            <div>
                                <a href="<?= base_url('settings/maintenance/backup/download') ?>" class="btn btn-outline-primary px-4 py-2">
                                    <i class="ti ti-download me-1"></i> Descargar Respaldo DB
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="h-100 p-4 border rounded d-flex flex-column justify-content-between">
                            <div>
                                <h6 class="fw-bold mb-2">Restaurar Copia de Seguridad</h6>
                                <p class="text-muted fs-3 mb-4">Sube un archivo de respaldo SQLite anterior (<code>.db</code> o <code>.sqlite</code>) para restaurar la configuración completa.</p>
                            </div>
                            <div>
                                <form action="<?= base_url('settings/maintenance/backup/restore') ?>" method="POST" enctype="multipart/form-data" data-confirm="Esta acción es irreversible y reemplazará toda la base de datos actual con la del respaldo. Por seguridad tu sesión será cerrada. ¿Deseas continuar?">
                                    <?= csrf_field() ?>
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <input type="file" name="backup_file" id="backup_file" class="d-none" accept=".db,.sqlite">
                                        <label for="backup_file" class="btn btn-primary px-4 mb-0 py-2 cursor-pointer shadow-none">
                                            <i class="ti ti-upload me-1"></i> Seleccionar Archivo
                                        </label>
                                        <span id="file-name" class="text-muted fs-3">Ningún archivo seleccionado</span>
                                        <button type="submit" class="btn btn-danger px-4 py-2 d-none shadow-none" id="btn-submit-restore">
                                            <i class="ti ti-restore me-1"></i> Confirmar Restauración
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= SECCIÓN: RED Y CONECTIVIDAD WIREGUARD ================= -->

    <!-- Sincronizar Servidor VPN -->
    <div class="col-md-4 mb-4">
        <div class="card h-100 shadow-none border">
            <div class="card-body d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="p-2 rounded bg-light-primary text-primary d-flex align-items-center justify-content-center">
                            <i class="ti ti-refresh fs-7"></i>
                        </div>
                        <h5 class="fw-bold mb-0">Reconciliar Peers</h5>
                    </div>
                    <p class="text-muted fs-2">Sincroniza los peers entre la base de datos y WireGuard. Elimina nodos huérfanos/fantasmas del servidor y restaura los nodos activos faltantes.</p>
                </div>
                <div class="mt-3">
                    <form action="<?= base_url('settings/maintenance/clear-ghost-peers') ?>" method="POST" data-confirm="¿Deseas reconciliar los peers con el servidor WireGuard? Se eliminarán los nodos inactivos y se restaurarán los activos.">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-primary w-100 shadow-none">
                            <i class="ti ti-refresh me-1"></i> Sincronizar Peers
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Reparar Firewall y Enrutamiento (NUEVO) -->
    <div class="col-md-4 mb-4">
        <div class="card h-100 shadow-none border">
            <div class="card-body d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="p-2 rounded bg-success-subtle text-success d-flex align-items-center justify-content-center">
                            <i class="ti ti-shield-check fs-7"></i>
                        </div>
                        <h5 class="fw-bold mb-0">Reparar Firewall</h5>
                    </div>
                    <p class="text-muted fs-2">Reconstruye el script de firewall en el servidor, activa <code>ip_forward=1</code> y reaplica las reglas de reenvío para que los nodos puedan comunicarse entre sí.</p>
                </div>
                <div class="mt-3">
                    <form action="<?= base_url('settings/maintenance/sync-firewall') ?>" method="POST" data-confirm="¿Deseas reconstruir y reaplicar las reglas de firewall y reenvío de paquetes en el servidor WireGuard?">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-success w-100 shadow-none">
                            <i class="ti ti-shield-check me-1"></i> Reparar Firewall / Forward
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Reiniciar WireGuard -->
    <div class="col-md-4 mb-4">
        <div class="card h-100 shadow-none border">
            <div class="card-body d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="p-2 rounded bg-danger-subtle text-danger d-flex align-items-center justify-content-center">
                            <i class="ti ti-power fs-7"></i>
                        </div>
                        <h5 class="fw-bold mb-0">Reiniciar Servicio</h5>
                    </div>
                    <p class="text-muted fs-2">Fuerza el reinicio de la interfaz del demonio WireGuard en el servidor Linux si algún servicio no responde adecuadamente.</p>
                </div>
                <div class="mt-3">
                    <form action="<?= base_url('settings/maintenance/restart-wireguard') ?>" method="POST" data-confirm="¿Estás seguro de que deseas reiniciar el servicio WireGuard? Las conexiones activas se reconectarán brevemente.">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-danger w-100 shadow-none">
                            <i class="ti ti-power me-1"></i> Reiniciar WireGuard
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const fileInput = document.getElementById('backup_file');
    const fileNameSpan = document.getElementById('file-name');
    const submitBtn = document.getElementById('btn-submit-restore');
    
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            if (this.files && this.files.length > 0) {
                fileNameSpan.textContent = this.files[0].name;
                submitBtn.classList.remove('d-none');
            } else {
                fileNameSpan.textContent = 'Ningún archivo seleccionado';
                submitBtn.classList.add('d-none');
            }
        });
    }
});
</script>
