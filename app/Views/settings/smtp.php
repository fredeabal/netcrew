<!-- =====================================================================
     CABECERA Y BREADCRUMB (NAVEGACIÓN)
     ===================================================================== -->
    <div class="card shadow-none border position-relative overflow-hidden mb-4">
        <div class="card-body px-4 py-3">
            <div class="row align-items-center">
                <div class="col-9">
                    <h4 class="fw-semibold mb-8">Ajustes SMTP</h4>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= base_url('dashboard') ?>">Dashboard</a></li>
                            <li class="breadcrumb-item" aria-current="page">Configuración SMTP</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- =====================================================================
         FORMULARIO DE CONFIGURACIÓN SMTP
         ===================================================================== -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form action="<?= base_url('settings/smtp/update') ?>" method="POST">
                        <?= csrf_field() ?>

                        <div class="row mb-3">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label for="fromEmail" class="form-label fw-semibold">Correo Remitente</label>
                                <input type="email" class="form-control" id="fromEmail" name="fromEmail" value="<?= old('fromEmail', esc($fromEmail ?? '')) ?>" placeholder="Ej: no-reply@tudominio.com">
                            </div>
                            <div class="col-md-6">
                                <label for="fromName" class="form-label fw-semibold">Nombre del Remitente</label>
                                <input type="text" class="form-control" id="fromName" name="fromName" value="<?= old('fromName', esc($fromName ?? '')) ?>" placeholder="Ej: Mi Empresa">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label for="smtp_host" class="form-label fw-semibold">Servidor SMTP (Host)</label>
                                <input type="text" class="form-control" id="smtp_host" name="smtp_host" value="<?= old('smtp_host', esc($smtp_host ?? '')) ?>" placeholder="Ej: smtp.gmail.com" required>
                            </div>
                            <div class="col-md-6">
                                <label for="smtp_port" class="form-label fw-semibold">Puerto SMTP</label>
                                <input type="number" class="form-control" id="smtp_port" name="smtp_port" value="<?= old('smtp_port', esc($smtp_port ?? '')) ?>" placeholder="Ej: 587" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label for="smtp_user" class="form-label fw-semibold">Usuario SMTP</label>
                                <input type="text" class="form-control" id="smtp_user" name="smtp_user" value="<?= old('smtp_user', esc($smtp_user ?? '')) ?>" placeholder="ej. correo@tudominio.com">
                            </div>
                            <div class="col-md-6">
                                <label for="smtp_pass" class="form-label fw-semibold">Contraseña SMTP</label>
                                <input type="password" class="form-control" id="smtp_pass" name="smtp_pass" placeholder="Dejar en blanco para no cambiarla">
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label for="smtp_crypto" class="form-label fw-semibold">Cifrado (Crypto)</label>
                                <select class="form-select" id="smtp_crypto" name="smtp_crypto">
                                    <option value="" <?= old('smtp_crypto', $smtp_crypto ?? '') === '' ? 'selected' : '' ?>>Ninguno</option>
                                    <option value="tls" <?= old('smtp_crypto', $smtp_crypto ?? '') === 'tls' ? 'selected' : '' ?>>TLS</option>
                                    <option value="ssl" <?= old('smtp_crypto', $smtp_crypto ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="mailType_display" class="form-label fw-semibold">Formato del Correo</label>
                                <input type="text" class="form-control" id="mailType_display" value="HTML" readonly disabled>
                                <input type="hidden" name="mailType" value="html">
                            </div>
                        </div>

                        <div class="d-flex justify-content-center mt-4">
                            <a href="<?= base_url('dashboard') ?>" class="btn btn-danger px-4 me-2">
                                <i class="ti ti-x me-1"></i>Cancelar
                            </a>
                            <button type="submit" formaction="<?= base_url('settings/smtp/test') ?>" formmethod="POST" class="btn btn-warning text-white px-4 me-2">
                                <i class="ti ti-send me-1"></i>Probar
                            </button>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="ti ti-device-floppy me-1"></i>Guardar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
