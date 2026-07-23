<!-- =====================================================================
     CABECERA Y BREADCRUMB (NAVEGACIÓN)
     Muestra el título de mantenimiento del sistema y la ruta de navegación.
     ===================================================================== -->
    <!-- Card del Breadcrumb -->
    <div class="card shadow-none border position-relative overflow-hidden mb-4">
        <div class="card-body px-4 py-3">
            <div class="row align-items-center">
                <div class="col-9">
                    <h4 class="fw-semibold mb-8">Mantenimiento del Sistema</h4>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= base_url('dashboard') ?>">Dashboard</a></li>
                            <li class="breadcrumb-item text-muted" aria-current="page">Mantenimiento</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- =====================================================================
         OPCIONES DE MANTENIMIENTO
         Tarjetas con acciones para limpiar sesiones, debugbar, logs y gestionar backups.
         ===================================================================== -->
    <div class="row">
        <!-- Tarjeta de Mantenimiento General -->
        <div class="col-12 mb-4">
            <div class="card border bg-light-primary">
                <div class="card-body d-flex flex-column flex-md-row align-items-md-center justify-content-md-between gap-3">
                    <div>
                        <h5 class="card-title fw-bold text-primary mb-1">Mantenimiento Completo</h5>
                        <p class="card-text text-muted mb-0">Ejecuta todas las acciones de limpieza simultáneamente (sesiones inactivas, debugs, logs y peers fantasmas).</p>
                    </div>
                    <form action="<?= base_url('settings/maintenance/clear-all') ?>" method="POST" class="d-inline">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-primary px-4 py-2">
                            Ejecutar Mantenimiento Completo
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Opciones Individuales -->
        <!-- 1. Sincronizar Servidor VPN -->
        <div class="col-md-6 mb-4">
            <div class="card h-100 shadow-none border">
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="p-2 rounded bg-light-warning text-warning d-flex align-items-center justify-content-center">
                                <i class="ti ti-refresh fs-7"></i>
                            </div>
                            <h5 class="fw-bold mb-0">Sincronizar Servidor VPN</h5>
                        </div>
                        <p class="text-muted">Reconcilia los peers entre la base de datos y el servidor. Elimina peers inactivos/fantasmas del servidor y restaura automáticamente los nodos activos que falten.</p>
                    </div>
                    <div class="mt-3">
                        <form action="<?= base_url('settings/maintenance/clear-ghost-peers') ?>" method="POST">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-warning text-white w-100">
                                Sincronizar y Limpiar Servidor
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Sesiones inactivas -->
        <div class="col-md-6 mb-4">
            <div class="card h-100 shadow-none border">
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="p-2 rounded bg-light-info text-info d-flex align-items-center justify-content-center">
                                <i class="ti ti-users fs-7"></i>
                            </div>
                            <h5 class="fw-bold mb-0">Sesiones de Usuario</h5>
                        </div>
                        <p class="text-muted">Elimina archivos de sesión antiguos y temporales del servidor. Tu sesión activa actual se conservará para evitar que seas desconectado.</p>
                    </div>
                    <div class="mt-3">
                        <form action="<?= base_url('settings/maintenance/clear-sessions') ?>" method="POST">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-info w-100">
                             Limpiar Sesiones
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Debugbar -->
        <div class="col-md-6 mb-4">
            <div class="card h-100 shadow-none border">
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="p-2 rounded bg-light-danger text-danger d-flex align-items-center justify-content-center">
                                <i class="ti ti-bug fs-7"></i>
                            </div>
                            <h5 class="fw-bold mb-0">Debugbar de CodeIgniter</h5>
                        </div>
                        <p class="text-muted">Vacía la caché y los archivos generados por la barra de herramientas de depuración (Debugbar) para liberar espacio en disco.</p>
                    </div>
                    <div class="mt-3">
                        <form action="<?= base_url('settings/maintenance/clear-debugbar') ?>" method="POST">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-danger w-100">
                             Limpiar Debugbar
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Logs del Sistema -->
        <div class="col-md-6 mb-4">
            <div class="card h-100 shadow-none border">
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="p-2 rounded bg-light-success text-success d-flex align-items-center justify-content-center">
                                <i class="ti ti-file-text fs-7"></i>
                            </div>
                            <h5 class="fw-bold mb-0">Logs del Sistema</h5>
                        </div>
                        <p class="text-muted">Elimina los archivos históricos de registro de errores de CodeIgniter que se acumulan en la carpeta writable.</p>
                    </div>
                    <div class="mt-3">
                        <form action="<?= base_url('settings/maintenance/clear-logs') ?>" method="POST">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-success w-100">
                             Limpiar Historial de Logs
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- 5. Respaldos (Backup) -->
        <div class="col-12 mb-4">
            <div class="card shadow-none border">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="p-2 rounded bg-light-primary text-primary d-flex align-items-center justify-content-center">
                            <i class="ti ti-database fs-7"></i>
                        </div>
                        <h5 class="fw-bold mb-0">Respaldo y Restauración de Base de Datos</h5>
                    </div>
                    <p class="text-muted mb-4">Descarga una copia de seguridad completa del sistema actual en formato SQLite o sube un archivo de respaldo para restaurarlo. <strong>Atención:</strong> Al restaurar, se destruirán todos los datos configurados en este momento.</p>
                    
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="h-100 p-4 border rounded d-flex flex-column justify-content-between">
                                <div>
                                    <h6 class="fw-bold mb-2">Crear Copia de Seguridad</h6>
                                    <p class="text-muted fs-3 mb-4">Descarga una copia completa de la base de datos actual para guardarla en un lugar seguro.</p>
                                </div>
                                <div>
                                    <a href="<?= base_url('settings/maintenance/backup/download') ?>" class="btn btn-outline-primary px-4 py-2">
                                        <i class="ti ti-download me-1"></i> Descargar Base de Datos
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="h-100 p-4 border rounded d-flex flex-column justify-content-between">
                                <div>
                                    <h6 class="fw-bold mb-2">Restaurar Copia de Seguridad</h6>
                                    <p class="text-muted fs-3 mb-4">Sube un archivo de respaldo SQLite anterior (`.db` o `.sqlite`) para restaurar toda la configuración del sistema.</p>
                                </div>
                                <div>
                                    <form action="<?= base_url('settings/maintenance/backup/restore') ?>" method="POST" enctype="multipart/form-data" data-confirm="Esta acción es irreversible y reemplazará toda la base de datos actual con la del respaldo. ¿Estás seguro de que quieres continuar?">
                                        <?= csrf_field() ?>
                                        <div class="d-flex flex-wrap align-items-center gap-2">
                                            <input type="file" name="backup_file" id="backup_file" class="d-none" accept=".db,.sqlite">
                                            <label for="backup_file" class="btn btn-primary px-4 mb-0 py-2 cursor-pointer">
                                                <i class="ti ti-upload me-1"></i> Seleccionar Archivo
                                            </label>
                                            <span id="file-name" class="text-muted fs-3">Ningún archivo seleccionado</span>
                                            <button type="submit" class="btn btn-danger px-4 py-2 d-none" id="btn-submit-restore">
                                                Restaurar
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
