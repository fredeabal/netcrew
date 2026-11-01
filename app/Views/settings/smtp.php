<!-- =====================================================================
     CABECERA Y BREADCRUMB (NAVEGACIÓN)
     ===================================================================== -->
<div class="card border shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body px-4 py-3">
        <div class="row align-items-center">
            <div class="col-12 col-md-8 text-center text-md-start">
                <h4 class="fw-semibold mb-2 mb-md-0">Ajustes SMTP</h4>
                <nav aria-label="breadcrumb" class="d-flex justify-content-center justify-content-md-start">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= base_url('dashboard') ?>">Inicio</a></li>
                        <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= base_url('dashboard') ?>">Ajustes</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Configuración SMTP</li>
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
            <div class="card-body p-3 p-md-4">
                <form action="<?= base_url('settings/smtp/update') ?>" method="POST">
                    <?= csrf_field() ?>

                    <!-- SECCIÓN 1: REMITENTE -->
                    <div class="mb-4">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center network-icon-circle flex-shrink-0">
                                <i class="ti ti-mail fs-5"></i>
                            </div>
                            <div>
                                <h6 class="fw-semibold mb-0">Identidad del remitente</h6>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="fromEmail" class="form-label fw-semibold">Correo remitente</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-transparent text-muted"><i class="ti ti-at"></i></span>
                                    <input type="email" class="form-control" id="fromEmail" name="fromEmail" value="<?= old('fromEmail', esc($fromEmail ?? '')) ?>" placeholder="Ej: no-reply@tudominio.com">
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="fromName" class="form-label fw-semibold">Nombre del remitente</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-transparent text-muted"><i class="ti ti-user"></i></span>
                                    <input type="text" class="form-control" id="fromName" name="fromName" value="<?= old('fromName', esc($fromName ?? '')) ?>" placeholder="Ej: Mi Empresa">
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4 opacity-10">

                    <!-- SECCIÓN 2: SERVIDOR Y CREDENCIALES -->
                    <div class="mb-4">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center network-icon-circle flex-shrink-0">
                                <i class="ti ti-server fs-5"></i>
                            </div>
                            <div>
                                <h6 class="fw-semibold mb-0">Servidor saliente y credenciales</h6>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-md-8">
                                <label for="smtp_host" class="form-label fw-semibold">Servidor SMTP (Host)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-transparent text-muted"><i class="ti ti-world"></i></span>
                                    <input type="text" class="form-control" id="smtp_host" name="smtp_host" value="<?= old('smtp_host', esc($smtp_host ?? '')) ?>" placeholder="Ej: smtp.gmail.com" required>
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <label for="smtp_port" class="form-label fw-semibold">Puerto SMTP</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-transparent text-muted"><i class="ti ti-hash"></i></span>
                                    <input type="number" class="form-control" id="smtp_port" name="smtp_port" value="<?= old('smtp_port', esc($smtp_port ?? '')) ?>" placeholder="Ej: 587" required>
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="smtp_user" class="form-label fw-semibold">Usuario SMTP</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-transparent text-muted"><i class="ti ti-user-check"></i></span>
                                    <input type="text" class="form-control" id="smtp_user" name="smtp_user" value="<?= old('smtp_user', esc($smtp_user ?? '')) ?>" placeholder="Ej: correo@tudominio.com">
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="smtp_pass" class="form-label fw-semibold">Contraseña SMTP</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-transparent text-muted"><i class="ti ti-lock"></i></span>
                                    <input type="password" class="form-control" id="smtp_pass" name="smtp_pass" placeholder="Dejar en blanco para no cambiarla">
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4 opacity-10">

                    <!-- SECCIÓN 3: SEGURIDAD Y FORMATO -->
                    <div class="mb-4">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center network-icon-circle flex-shrink-0">
                                <i class="ti ti-shield-lock fs-5"></i>
                            </div>
                            <div>
                                <h6 class="fw-semibold mb-0">Seguridad y formato</h6>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="smtp_crypto" class="form-label fw-semibold">Cifrado (Crypto)</label>
                                <select class="select2 form-control custom-select" id="smtp_crypto" name="smtp_crypto">
                                    <option value="" <?= old('smtp_crypto', $smtp_crypto ?? '') === '' ? 'selected' : '' ?>>Ninguno</option>
                                    <option value="tls" <?= old('smtp_crypto', $smtp_crypto ?? '') === 'tls' ? 'selected' : '' ?>>TLS</option>
                                    <option value="ssl" <?= old('smtp_crypto', $smtp_crypto ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="mailType_display" class="form-label fw-semibold">Formato del correo</label>
                                <input type="text" class="form-control net-mono" id="mailType_display" value="HTML" readonly disabled>
                                <input type="hidden" name="mailType" value="html">
                            </div>
                        </div>
                    </div>

                    <!-- BOTONERA DE ACCIONES: RESPONSIVE Y CENTRADOS -->
                    <div class="mt-4 pt-3 border-top">
                        <div class="row g-2 justify-content-center align-items-center">
                            <div class="col-6 col-sm-auto">
                                <a href="<?= base_url('dashboard') ?>" class="btn btn-dark btn-sm w-100 px-sm-4 py-2 text-nowrap d-flex align-items-center justify-content-center h-100">
                                    <i class="ti ti-x me-1"></i>Cancelar
                                </a>
                            </div>
                            <div class="col-6 col-sm-auto">
                                <button type="submit" formaction="<?= base_url('settings/smtp/test') ?>" formmethod="POST" class="btn btn-warning btn-sm text-white w-100 px-sm-4 py-2 text-nowrap d-flex align-items-center justify-content-center h-100">
                                    <i class="ti ti-send me-1"></i>Probar
                                </button>
                            </div>
                            <div class="col-12 col-sm-auto">
                                <button type="submit" class="btn btn-primary btn-sm w-100 px-sm-4 py-2 text-nowrap d-flex align-items-center justify-content-center">
                                    <i class="ti ti-device-floppy me-1"></i>Guardar Cambios
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
