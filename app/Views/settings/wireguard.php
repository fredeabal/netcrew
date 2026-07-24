<div class="container-fluid">
    <!-- =====================================================================
         CABECERA Y BREADCRUMB (NAVEGACIÓN)
         ===================================================================== -->
    <div class="card shadow-none border position-relative overflow-hidden mb-4">
        <div class="card-body px-4 py-3">
            <div class="row align-items-center">
                <div class="col-9">
                    <h4 class="fw-semibold mb-8">Ajustes Globales WireGuard</h4>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= base_url('dashboard') ?>">Dashboard</a></li>
                            <li class="breadcrumb-item" aria-current="page">Configuración WireGuard</li>
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
                <div class="card-body">
                    <form action="<?= base_url('settings/wireguard/update') ?>" method="POST">
                        <?= csrf_field() ?>

                        <div class="row mb-3">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label for="wg_ssh_host" class="form-label fw-semibold">IP Del Servidor</label>
                                <input type="text" class="form-control" id="wg_ssh_host" name="wg_ssh_host" value="<?= old('wg_ssh_host', esc($wg_ssh_host ?? '')) ?>" placeholder="Ej: 192.168.0.123">
                            </div>
                            <div class="col-md-6">
                                <label for="wg_ssh_port" class="form-label fw-semibold">Puerto SSH</label>
                                <input type="number" class="form-control" id="wg_ssh_port" name="wg_ssh_port" value="<?= old('wg_ssh_port', esc($wg_ssh_port ?? '22')) ?>" placeholder="22">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label for="wg_ssh_auth_type" class="form-label fw-semibold">Método de Autenticación SSH</label>
                                <select class="form-select" id="wg_ssh_auth_type" name="wg_ssh_auth_type" onchange="toggleAuthFields()">
                                    <option value="password" <?= old('wg_ssh_auth_type', $wg_ssh_auth_type ?? 'password') === 'password' ? 'selected' : '' ?>>Contraseña</option>
                                    <option value="key" <?= old('wg_ssh_auth_type', $wg_ssh_auth_type ?? 'password') === 'key' ? 'selected' : '' ?>>Llave Privada SSH</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="wg_ssh_user" class="form-label fw-semibold">Usuario SSH</label>
                                <input type="text" class="form-control" id="wg_ssh_user" name="wg_ssh_user" value="<?= old('wg_ssh_user', esc($wg_ssh_user ?? '')) ?>" placeholder="Ej: root">
                            </div>
                        </div>

                        <div class="row mb-3" id="auth_password_row">
                            <div class="col-12">
                                <label for="wg_ssh_password" class="form-label fw-semibold">Contraseña SSH</label>
                                <input type="password" class="form-control" id="wg_ssh_password" name="wg_ssh_password" placeholder="<?= !empty($wg_ssh_password) ? 'Dejar en blanco para mantener la actual' : 'Contraseña SSH' ?>">
                            </div>
                        </div>

                        <div class="row mb-3 d-none" id="auth_key_row">
                            <div class="col-12">
                                <label for="wg_ssh_private_key" class="form-label fw-semibold">Llave Privada SSH</label>
                                <textarea class="form-control" id="wg_ssh_private_key" name="wg_ssh_private_key" rows="5" placeholder="<?= !empty($wg_ssh_private_key) ? 'Dejar en blanco para mantener la actual' : 'Pega aquí tu llave privada (ej: -----BEGIN OPENSSH PRIVATE KEY----- ...)' ?>"></textarea>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label for="wg_interface" class="form-label fw-semibold">Interfaz WireGuard por Defecto</label>
                                <input type="text" class="form-control" id="wg_interface" name="wg_interface" value="<?= old('wg_interface', esc($wg_interface ?? 'wg0')) ?>" placeholder="Ej: wg0">
                            </div>
                            <div class="col-md-6">
                                <label for="wg_endpoint" class="form-label fw-semibold">Endpoint Público del Servidor (IP/Host:Puerto)</label>
                                <input type="text" class="form-control" id="wg_endpoint" name="wg_endpoint" value="<?= old('wg_endpoint', esc($wg_endpoint ?? '')) ?>" placeholder="Ej: vpn.tudominio.com:51820">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-12">
                                <label for="wg_restricted_cidrs" class="form-label fw-semibold">Redes Restringidas (Bloqueadas)</label>
                                <input type="text" class="form-control" id="wg_restricted_cidrs" name="wg_restricted_cidrs" value="<?= old('wg_restricted_cidrs', esc($wg_restricted_cidrs ?? '')) ?>" placeholder="Ej: 192.168.1.0, 10.0.0.0">
                                <div class="mt-2">
                                  <span class="badge bg-primary-subtle text-primary text-wrap text-start fw-normal lh-base fs-2"><i class="ti ti-alert-circle"></i> <strong>Importante:</strong> Separa por comas las redes locales del servidor o de salida a Internet. (Ejemplo: 192.168.1.0, 10.0.0.0)</span>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-center mt-4">
                            <a href="<?= base_url('dashboard') ?>" class="btn btn-danger px-4 me-2">
                                <i class="ti ti-x me-1"></i>Cancelar
                            </a>
                            <button type="submit" formaction="<?= base_url('settings/wireguard/test') ?>" formmethod="POST" class="btn btn-warning text-white px-4 me-2">
                                <i class="ti ti-plug-connected me-1"></i>Probar
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
document.addEventListener('DOMContentLoaded', toggleAuthFields);
</script>
