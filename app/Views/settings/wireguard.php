<!-- =====================================================================
     CABECERA Y BREADCRUMB (NAVEGACIÓN)
     ===================================================================== -->
<div class="card border shadow-none position-relative overflow-hidden mb-4">
    <div class="card-body px-4 py-3">
        <div class="row align-items-center">
            <div class="col-12 col-md-8 text-center text-md-start">
                <h4 class="fw-semibold mb-2 mb-md-0">Ajustes Globales WireGuard</h4>
                <nav aria-label="breadcrumb" class="d-flex justify-content-center justify-content-md-start">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= base_url('dashboard') ?>">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Configuración WireGuard</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- =====================================================================
     FORMULARIO DE CONFIGURACIÓN WIREGUARD
     ===================================================================== -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body p-3 p-md-4">
                <?php if (!empty($credential_decrypt_error)): ?>
                    <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" role="alert">
                        <i class="ti ti-alert-triangle fs-6 flex-shrink-0"></i>
                        <div>
                            <strong>La clave de cifrado de la aplicación (.env) ha cambiado.</strong><br>
                            No se pudo desencriptar la credencial SSH anterior. Por favor, reintroduce tu contraseña o llave privada SSH a continuación y pulsa <strong>Guardar Cambios</strong> para restablecer la comunicación con WireGuard.
                        </div>
                    </div>
                <?php endif; ?>

                <form action="<?= base_url('settings/wireguard/update') ?>" method="POST" id="formWireguardSettings">
                    <?= csrf_field() ?>

                    <!-- SECCIÓN 1: ACCESO ADMINISTRATIVO SSH -->
                    <div class="mb-4">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center network-icon-circle flex-shrink-0">
                                <i class="ti ti-server fs-5"></i>
                            </div>
                            <div>
                                <h6 class="fw-semibold mb-0">Conexión SSH con el servidor</h6>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-md-8">
                                <label for="wg_ssh_host" class="form-label fw-semibold">IP o Host del Servidor</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-transparent text-muted"><i class="ti ti-world"></i></span>
                                    <input type="text" class="form-control" id="wg_ssh_host" name="wg_ssh_host" value="<?= old('wg_ssh_host', esc($wg_ssh_host ?? '')) ?>" placeholder="Ej: 192.168.0.123">
                                </div>
                            </div>
                            <div class="col-12 col-md-4">
                                <label for="wg_ssh_port" class="form-label fw-semibold">Puerto SSH</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-transparent text-muted"><i class="ti ti-hash"></i></span>
                                    <input type="number" class="form-control" id="wg_ssh_port" name="wg_ssh_port" value="<?= old('wg_ssh_port', esc($wg_ssh_port ?? '22')) ?>" placeholder="22">
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="wg_ssh_auth_type" class="form-label fw-semibold">Método de Autenticación</label>
                                <select class="select2 form-control custom-select" id="wg_ssh_auth_type" name="wg_ssh_auth_type" onchange="toggleAuthFields()">
                                    <option value="password" <?= old('wg_ssh_auth_type', $wg_ssh_auth_type ?? 'password') === 'password' ? 'selected' : '' ?>>Contraseña</option>
                                    <option value="key" <?= old('wg_ssh_auth_type', $wg_ssh_auth_type ?? 'password') === 'key' ? 'selected' : '' ?>>Llave Privada SSH</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="wg_ssh_user" class="form-label fw-semibold">Usuario SSH</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-transparent text-muted"><i class="ti ti-user"></i></span>
                                    <input type="text" class="form-control" id="wg_ssh_user" name="wg_ssh_user" value="<?= old('wg_ssh_user', esc($wg_ssh_user ?? '')) ?>" placeholder="Ej: root">
                                </div>
                            </div>

                            <div class="col-12" id="auth_password_row">
                                <label for="wg_ssh_password" class="form-label fw-semibold">Contraseña SSH</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-transparent text-muted"><i class="ti ti-lock"></i></span>
                                    <input type="password" class="form-control" id="wg_ssh_password" name="wg_ssh_password" placeholder="<?= !empty($wg_ssh_password) ? '•••••••• (Dejar en blanco para mantener la actual)' : 'Introduce la contraseña SSH' ?>">
                                </div>
                            </div>

                            <div class="col-12 d-none" id="auth_key_row">
                                <label for="wg_ssh_private_key" class="form-label fw-semibold">Llave Privada SSH</label>
                                <textarea class="form-control font-monospace fs-2" id="wg_ssh_private_key" name="wg_ssh_private_key" rows="4" placeholder="<?= !empty($wg_ssh_private_key) ? '•••••••• (Dejar en blanco para mantener la actual)' : 'Pega aquí tu llave privada (-----BEGIN OPENSSH PRIVATE KEY----- ...)' ?>"></textarea>
                            </div>
                        </div>
                    </div>


                    <!-- SECCIÓN 2: RED Y PARÁMETROS WIREGUARD -->
                    <div class="mb-4">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center network-icon-circle flex-shrink-0">
                                <i class="ti ti-shield-lock fs-5"></i>
                            </div>
                            <div>
                                <h6 class="fw-semibold mb-0">Parámetros del servicio WireGuard</h6>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="wg_interface" class="form-label fw-semibold">Interfaz WireGuard</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-transparent text-muted"><i class="ti ti-network"></i></span>
                                    <input type="text" class="form-control" id="wg_interface" name="wg_interface" value="<?= old('wg_interface', esc($wg_interface ?? 'wg0')) ?>" placeholder="Ej: wg0">
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="wg_endpoint" class="form-label fw-semibold">Endpoint Público del Servidor</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-transparent text-muted"><i class="ti ti-antenna"></i></span>
                                    <input type="text" class="form-control" id="wg_endpoint" name="wg_endpoint" value="<?= old('wg_endpoint', esc($wg_endpoint ?? '')) ?>" placeholder="vpn.tudominio.com:51820">
                                </div>
                                <div class="form-text text-muted mt-1 fs-2">
                                    <i class="ti ti-info-circle me-1"></i>Host o IP pública y puerto UDP (ej: <code>vpn.tudominio.com:51820</code>).
                                </div>
                            </div>

                            <?php if (!empty($wg_public_key)): ?>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Clave pública del servidor WireGuard</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-transparent text-muted"><i class="ti ti-key"></i></span>
                                        <input type="text" class="form-control font-monospace fs-2" value="<?= esc($wg_public_key) ?>" readonly>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="col-12">
                                <label for="wg_restricted_cidrs" class="form-label fw-semibold">Redes Restringidas (Bloqueadas)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-transparent text-muted"><i class="ti ti-ban"></i></span>
                                    <input type="text" class="form-control" id="wg_restricted_cidrs" name="wg_restricted_cidrs" value="<?= old('wg_restricted_cidrs', esc($wg_restricted_cidrs ?? '')) ?>" placeholder="Ej: 192.168.0.0, 192.168.1.0, 172.16.0.0">
                                </div>
                                <div class="form-text text-muted mt-2 d-flex align-items-start gap-1 fs-2">
                                    <i class="ti ti-info-circle text-primary flex-shrink-0 mt-1"></i>
                                    <span>Separa por comas las redes a las que no se debe conceder acceso (ej: <code>192.168.1.0, 10.0.0.0</code>).</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- BOTONES DE ACCIÓN: RESPONSIVE Y CENTRADOS -->
                    <div class="mt-4 pt-3 border-top">
                        <div class="row g-2 justify-content-center align-items-center">
                            <div class="col-6 col-sm-auto">
                                <a href="<?= base_url('dashboard') ?>" class="btn btn-dark btn-sm w-100 px-sm-4 py-2 text-nowrap d-flex align-items-center justify-content-center h-100">
                                    <i class="ti ti-x me-1"></i>Cancelar
                                </a>
                            </div>
                            <div class="col-6 col-sm-auto">
                                <button type="submit" formaction="<?= base_url('settings/wireguard/test') ?>" formmethod="POST" class="btn btn-warning btn-sm text-white w-100 px-sm-4 py-2 text-nowrap d-flex align-items-center justify-content-center h-100">
                                    <i class="ti ti-plug-connected me-1"></i>Probar
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

<script>
function toggleAuthFields() {
    const authType = document.getElementById('wg_ssh_auth_type').value;
    const passwordRow = document.getElementById('auth_password_row');
    const keyRow = document.getElementById('auth_key_row');

    if (authType === 'key') {
        passwordRow.classList.add('d-none');
        keyRow.classList.remove('d-none');
    } else {
        passwordRow.classList.remove('d-none');
        keyRow.classList.add('d-none');
    }
}

// Ejecutar al cargar la página para reflejar el estado actual
document.addEventListener('DOMContentLoaded', function() {
    toggleAuthFields();
    if (window.jQuery) {
        $('#wg_ssh_auth_type').on('select2:select change', toggleAuthFields);
    }
});
</script>
