<!DOCTYPE html>
<html lang="es" dir="ltr" data-bs-theme="dark">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <title>Conectar a VPN - <?= esc($device->name) ?> | NetCrew</title>
  <link rel="shortcut icon" type="image/png" href="<?= base_url('assets/images/logos/favicon.png') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/styles.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/custom.css') ?>" />
</head>
<body class="connect-page">

  <div class="connect-card p-4 p-md-5 text-center">
    <div class="mb-4">
      <img src="<?= base_url('assets/images/logos/light-logo.svg') ?>" alt="NetCrew" height="38" />
    </div>

    <h4 class="fw-semibold text-white mb-2"><?= esc($device->name) ?></h4>
    <p class="text-muted small mb-4">
      Descarga el archivo <code>.yml</code> o pega este enlace en <strong>NetCrew Client</strong> para conectarte.
    </p>

    <div class="bg-dark p-3 rounded-3 text-start small mb-4 border border-secondary border-opacity-25">
      <div class="d-flex justify-content-between mb-1">
        <span class="text-muted">Red:</span>
        <span class="text-white fw-semibold"><?= esc($network->name) ?></span>
      </div>
      <div class="d-flex justify-content-between">
        <span class="text-muted">IP asignada:</span>
        <span class="net-mono"><?= esc($device->ip_address) ?></span>
      </div>
    </div>

    <div class="text-start mb-3">
      <label for="sharePlatformSelect" class="form-label small fw-semibold text-muted">Tu dispositivo</label>
      <select id="sharePlatformSelect" class="form-select">
        <option value="desktop">macOS / Windows / Linux (NetCrew Client)</option>
        <option value="linux-cli">Linux Servidor / Headless (Terminal)</option>
        <option value="mobile">iPhone / iPad / Android</option>
      </select>
    </div>

    <div id="shareDesktopSection" class="text-start mb-3">
      <div class="d-grid gap-2">
        <a href="<?= site_url('connect/' . $token . '/download?platform=yml') ?>" class="btn btn-primary py-2 fw-semibold d-inline-flex align-items-center justify-content-center gap-2">
          <i class="ti ti-download"></i>
          <span>Descargar perfil (.yml)</span>
        </a>
        <button type="button" id="btnCopyAppLink" class="btn btn-outline-primary py-2 fw-semibold d-inline-flex align-items-center justify-content-center gap-2">
          <i class="ti ti-copy"></i>
          <span>Copiar enlace para NetCrew Client</span>
        </button>
      </div>
      <div class="text-center mt-3">
        <small class="text-muted d-block">
          ¿Aún no tienes la app?
          <a href="https://github.com/fredeabal/netcrew-app/releases" target="_blank" rel="noopener" class="text-primary text-decoration-none fw-semibold">
            Descargar NetCrew Client <i class="ti ti-external-link"></i>
          </a>
        </small>
      </div>
    </div>

    <div id="shareCmdSection" class="text-start mb-3 d-none">
      <small id="shareCmdHint" class="text-muted d-block mb-2">Abre Terminal en tu servidor Linux, pega este comando y pulsa Enter:</small>
      <pre class="p-3 rounded bg-dark border border-secondary border-opacity-25 net-mono small text-break mb-2" style="white-space: pre-wrap;"><code id="shareCmdText"></code></pre>
      <div class="d-grid gap-2">
        <button type="button" id="btnCopyShareCmd" class="btn btn-primary py-2 fw-semibold d-inline-flex align-items-center justify-content-center gap-2">
          <i class="ti ti-copy"></i>
          <span>Copiar Comando</span>
        </button>
      </div>
    </div>

    <div id="shareMobileSection" class="d-none">
      <div class="d-grid gap-2">
        <a href="<?= site_url('connect/' . $token . '/download?platform=yml') ?>" class="btn btn-primary py-2 fw-semibold d-inline-flex align-items-center justify-content-center gap-2">
          <i class="ti ti-download"></i>
          <span>Descargar configuración (.yml)</span>
        </a>
        <small class="text-muted d-block mt-1">Instala la app gratuita <strong>Nebula</strong> en App Store o Google Play e importa el archivo.</small>
      </div>
    </div>

    <div class="mt-4">
      <small class="text-muted d-block connect-note">
        Este enlace temporal expira en 60 minutos por razones de seguridad.
      </small>
    </div>
  </div>

  <script {csp-script-nonce}>
    document.addEventListener("DOMContentLoaded", function () {
      const baseDownloadUrl = <?= json_encode(site_url('connect/' . $token . '/download')) ?>;

      const selectEl = document.getElementById('sharePlatformSelect');
      const desktopSec = document.getElementById('shareDesktopSection');
      const cmdSec = document.getElementById('shareCmdSection');
      const mobSec = document.getElementById('shareMobileSection');
      const cmdHint = document.getElementById('shareCmdHint');
      const cmdText = document.getElementById('shareCmdText');
      const btnCopy = document.getElementById('btnCopyShareCmd');
      const btnCopyAppLink = document.getElementById('btnCopyAppLink');

      const ua = navigator.userAgent || '';
      if (/iphone|ipad|ipod|android/i.test(ua)) {
        selectEl.value = 'mobile';
      } else {
        selectEl.value = 'desktop';
      }

      function renderShareUI() {
        const p = selectEl.value;
        desktopSec.classList.add('d-none');
        cmdSec.classList.add('d-none');
        mobSec.classList.add('d-none');

        if (p === 'mobile') {
          mobSec.classList.remove('d-none');
          return;
        }
        if (p === 'desktop') {
          desktopSec.classList.remove('d-none');
          return;
        }

        cmdSec.classList.remove('d-none');
        cmdHint.textContent = 'Abre Terminal en tu servidor Linux, pega este comando y pulsa Enter:';
        cmdText.textContent = `sudo bash <(curl -fsSL "${baseDownloadUrl}?platform=linux")`;
      }

      selectEl.addEventListener('change', renderShareUI);
      renderShareUI();

      if (btnCopyAppLink) {
        btnCopyAppLink.addEventListener('click', function () {
          if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(window.location.href).then(() => {
              btnCopyAppLink.innerHTML = '<i class="ti ti-check"></i><span>¡Enlace copiado!</span>';
              setTimeout(() => {
                btnCopyAppLink.innerHTML = '<i class="ti ti-copy"></i><span>Copiar enlace para NetCrew Client</span>';
              }, 2500);
            });
          }
        });
      }

      btnCopy.addEventListener('click', function () {
        const text = cmdText.textContent;
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(text).then(() => {
            btnCopy.innerHTML = '<i class="ti ti-check"></i><span>¡Copiado!</span>';
            setTimeout(() => {
              btnCopy.innerHTML = '<i class="ti ti-copy"></i><span>Copiar Comando</span>';
            }, 2500);
          });
        }
      });
    });
  </script>
</body>
</html>

