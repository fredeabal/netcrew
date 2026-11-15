<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-body p-3 p-md-4">
        <form action="<?= base_url('settings/nebula/update') ?>" method="POST" id="formNebulaSettings">
          <?= csrf_field() ?>

          <!-- SECCIÓN 1: LIGHTHOUSE PÚBLICO -->
          <div class="mb-4">
            <div class="d-flex align-items-center gap-2 mb-3">
              <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center network-icon-circle flex-shrink-0">
                <i class="ti ti-radar fs-5"></i>
              </div>
              <div>
                <h6 class="fw-semibold mb-0">Servidor Lighthouse (Descubrimiento P2P)</h6>
              </div>
            </div>

            <div class="row g-3">
              <div class="col-12 col-md-8">
                <label for="nebula_lighthouse_host" class="form-label fw-semibold">IP o Dominio Público del Lighthouse</label>
                <div class="input-group">
                  <span class="input-group-text bg-transparent text-muted"><i class="ti ti-world"></i></span>
                  <input type="text" class="form-control" id="nebula_lighthouse_host" name="nebula_lighthouse_host" value="<?= old('nebula_lighthouse_host', esc($nebula_lighthouse_host ?? '')) ?>" placeholder="Ej: vpn.tudominio.com">
                </div>
                <div class="form-text fs-2 text-muted">
                  Endpoint efectivo actual: <code><?= esc(($resolved_endpoint['host'] ?: 'Sin definir') . ':' . $resolved_endpoint['port']) ?></code>
                </div>
              </div>

              <div class="col-12 col-md-4">
                <label for="nebula_lighthouse_port" class="form-label fw-semibold">Puerto UDP Lighthouse</label>
                <div class="input-group">
                  <span class="input-group-text bg-transparent text-muted"><i class="ti ti-hash"></i></span>
                  <input type="number" class="form-control" id="nebula_lighthouse_port" name="nebula_lighthouse_port" value="<?= old('nebula_lighthouse_port', esc($nebula_lighthouse_port ?? 4242)) ?>" placeholder="4242">
                </div>
              </div>

              <div class="col-12 col-md-6">
                <label for="nebula_cipher" class="form-label fw-semibold">Algoritmo de Cifrado</label>
                <select class="select2 form-control custom-select" id="nebula_cipher" name="nebula_cipher" style="width: 100%">
                  <option value="aes" <?= old('nebula_cipher', $nebula_cipher ?? 'aes') === 'aes' ? 'selected' : '' ?>>AES-256-GCM (Recomendado / Aceleración Hardware)</option>
                  <option value="chacha20" <?= old('nebula_cipher', $nebula_cipher ?? 'aes') === 'chacha20' ? 'selected' : '' ?>>ChaCha20-Poly1305</option>
                </select>
              </div>

              <div class="col-12 col-md-6 d-flex align-items-end">
                <div class="form-check form-switch mb-2">
                  <input class="form-check-input" type="checkbox" role="switch" id="nebula_relay_enabled" name="nebula_relay_enabled" value="1" <?= old('nebula_relay_enabled', $nebula_relay_enabled ?? 1) ? 'checked' : '' ?>>
                  <label class="form-check-label fw-semibold" for="nebula_relay_enabled">
                    Habilitar Relay de respaldo (para NAT simétrico estricto)
                  </label>
                  <div class="form-text fs-2 text-muted">Si el P2P directo falla por un firewall corporativo, usa el Lighthouse como puente.</div>
                </div>
              </div>

              <div class="col-12">
                <label for="nebula_restricted_cidrs" class="form-label fw-semibold">Redes Restringidas (CIDRs bloqueados)</label>
                <input type="text" class="form-control" id="nebula_restricted_cidrs" name="nebula_restricted_cidrs" value="<?= old('nebula_restricted_cidrs', esc($nebula_restricted_cidrs ?? '')) ?>" placeholder="Ej: 192.168.1.0/24, 10.0.0.0/24">
                <div class="form-text fs-2 text-muted">Subredes separadas por comas que los usuarios no podrán asignar al crear nuevas redes.</div>
              </div>
            </div>
          </div>

          <hr class="my-4">

          <!-- SECCIÓN 2: SSH OPCIONAL SI nebula-cert ESTÁ EN SERVIDOR REMOTO -->
          <div class="mb-4">
            <div class="d-flex align-items-center gap-2 mb-3">
              <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center network-icon-circle flex-shrink-0">
                <i class="ti ti-terminal-2 fs-5"></i>
              </div>
              <div>
                <h6 class="fw-semibold mb-0">Acceso SSH Remoto (Opcional)</h6>
                <small class="text-muted">Solo necesario si <code>nebula-cert</code> no está instalado en este servidor web y se ejecuta en el Lighthouse remoto.</small>
              </div>
            </div>

            <div class="row g-3">
              <div class="col-12 col-md-6">
                <label for="nebula_ssh_host" class="form-label fw-semibold">IP o Host SSH</label>
                <input type="text" class="form-control" id="nebula_ssh_host" name="nebula_ssh_host" value="<?= old('nebula_ssh_host', esc($nebula_ssh_host ?? '')) ?>" placeholder="Ej: 192.168.0.123">
              </div>
              <div class="col-12 col-md-3">
                <label for="nebula_ssh_port" class="form-label fw-semibold">Puerto SSH</label>
                <input type="number" class="form-control" id="nebula_ssh_port" name="nebula_ssh_port" value="<?= old('nebula_ssh_port', esc($nebula_ssh_port ?? 22)) ?>" placeholder="22">
              </div>
              <div class="col-12 col-md-3">
                <label for="nebula_ssh_user" class="form-label fw-semibold">Usuario SSH</label>
                <input type="text" class="form-control" id="nebula_ssh_user" name="nebula_ssh_user" value="<?= old('nebula_ssh_user', esc($nebula_ssh_user ?? '')) ?>" placeholder="root">
              </div>

              <div class="col-12 col-md-4">
                <label for="nebula_ssh_auth_type" class="form-label fw-semibold">Autenticación SSH</label>
                <select class="select2 form-control custom-select" id="nebula_ssh_auth_type" name="nebula_ssh_auth_type" style="width: 100%">
                  <option value="password" <?= old('nebula_ssh_auth_type', $nebula_ssh_auth_type ?? 'password') === 'password' ? 'selected' : '' ?>>Contraseña</option>
                  <option value="key" <?= old('nebula_ssh_auth_type', $nebula_ssh_auth_type ?? 'password') === 'key' ? 'selected' : '' ?>>Llave Privada</option>
                </select>
              </div>
              <div class="col-12 col-md-4">
                <label for="nebula_ssh_password" class="form-label fw-semibold">Contraseña SSH</label>
                <input type="password" class="form-control" id="nebula_ssh_password" name="nebula_ssh_password" value="<?= esc($nebula_ssh_password ?? '') ?>" autocomplete="new-password">
              </div>
              <div class="col-12 col-md-4">
                <label for="nebula_ssh_private_key" class="form-label fw-semibold">Llave Privada SSH</label>
                <textarea class="form-control" id="nebula_ssh_private_key" name="nebula_ssh_private_key" rows="2" placeholder="-----BEGIN OPENSSH PRIVATE KEY-----"><?= esc($nebula_ssh_private_key ?? '') ?></textarea>
              </div>
            </div>
          </div>

          <div class="mt-4 pt-3 border-top">
            <div class="row g-2 justify-content-center align-items-center">
              <div class="col-6 col-sm-auto">
                <button type="button" id="btnTestNebula" class="btn btn-outline-primary btn-sm w-100 px-sm-4 text-nowrap d-inline-flex align-items-center justify-content-center gap-1">
                  <i class="ti ti-plug-connected"></i>Comprobar nebula-cert
                </button>
              </div>
              <div class="col-6 col-sm-auto">
                <button type="submit" class="btn btn-primary btn-sm w-100 px-sm-4 text-nowrap d-inline-flex align-items-center justify-content-center gap-1">
                  <i class="ti ti-device-floppy"></i>Guardar Cambios
                </button>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script {csp-script-nonce}>
document.addEventListener('DOMContentLoaded', function() {
  const btnTest = document.getElementById('btnTestNebula');
  if (btnTest) {
    btnTest.addEventListener('click', function() {
      btnTest.disabled = true;
      fetch('<?= base_url('settings/nebula/test') ?>', {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': '<?= csrf_hash() ?>'
        }
      })
      .then(r => {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then(data => {
        const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        Swal.fire({
          icon: data.success ? 'success' : 'error',
          title: data.success ? 'Online' : 'No online',
          text: data.success ? 'El servicio está disponible.' : (data.message || 'El servicio no está disponible.'),
          background: isDark ? '#0b1114' : '#f8f9fa',
          color: isDark ? '#ffffff' : '#0b1114',
          confirmButtonColor: '#F38020'
        });
      })
      .catch(() => {
        const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'Error al comprobar el estado de Nebula.',
          background: isDark ? '#0b1114' : '#f8f9fa',
          color: isDark ? '#ffffff' : '#0b1114',
          confirmButtonColor: '#F38020'
        });
      })
      .finally(() => { btnTest.disabled = false; });
    });
  }
});
</script>
