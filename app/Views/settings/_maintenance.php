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
                    <p class="card-text text-muted mb-0">Limpia sesiones, logs y caché, y optimiza la base de datos (VACUUM).</p>
                </div>
                <div class="mt-3">
                    <form action="<?= base_url('settings/maintenance/clear-all') ?>" method="POST" data-confirm="¿Estás seguro de que deseas ejecutar el mantenimiento completo del sistema? Se limpiarán temporales, caché y se optimizará la base de datos.">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-primary btn-sm py-2 shadow-none">
                            <i class="ti ti-wand me-1"></i> Ejecutar Mantenimiento Completo
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Respaldos (Backup) -->
    <div class="col-12 mb-4 d-none d-md-block">
        <div class="card shadow-none border">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="p-2 rounded bg-light-primary text-primary d-flex align-items-center justify-content-center">
                        <i class="ti ti-database fs-7"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0">Respaldo y Restauración de Base de Datos</h5>
                    </div>
                </div>
                <p class="text-muted mb-4">Descarga o restaura una copia SQLite completa. <strong>Atención:</strong> Al restaurar se reemplazará la base de datos actual.</p>

                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="h-100 p-4 border rounded d-flex flex-column justify-content-between">
                            <div>
                                <h6 class="fw-bold mb-2">Crear Copia de Seguridad</h6>
                                <p class="text-muted fs-3 mb-4">Genera una instantánea consistente e inmutable de la base de datos para conservarla en un lugar seguro.</p>
                            </div>
                            <div>
                                <a href="<?= base_url('settings/maintenance/backup/download') ?>" class="btn btn-outline-primary btn-sm px-3 py-2">
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
                                        <label for="backup_file" class="btn btn-primary btn-sm px-3 mb-0 py-2 cursor-pointer shadow-none">
                                            <i class="ti ti-upload me-1"></i> Seleccionar Archivo
                                        </label>
                                        <span id="file-name" class="text-muted fs-3">Ningún archivo seleccionado</span>
                                        <button type="submit" class="btn btn-danger btn-sm px-3 py-2 d-none shadow-none" id="btn-submit-restore">
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
</div>

<script {csp-script-nonce}>
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
