<!DOCTYPE html>
<html lang="es" dir="ltr" data-bs-theme="dark">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <title>Conectar a VPN - <?= esc($device->name) ?> | NetCrew</title>
  <link rel="shortcut icon" type="image/png" href="<?= base_url('assets/images/logos/favicon.png') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/styles.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/custom.css') ?>" />
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" integrity="sha512-CNgIRecGo7nphbeZepKmjzdRNO39UnUq205AC9041zguTkFcUcLhy5F5GyUH4ud55xqdXY2NWKRh6936UO32UQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
</head>
<body class="connect-page">

  <div class="connect-card p-4 p-md-5 text-center">
    <div class="mb-4">
      <img src="<?= base_url('assets/images/logos/light-logo.svg') ?>" alt="NetCrew" height="38" />
    </div>

    <h4 class="fw-semibold text-white mb-2"><?= esc($device->name) ?></h4>
    <p class="text-muted small mb-4">
      Escanea el código QR o descarga el archivo <code>.yml</code> para importarlo en la app oficial de Nebula.
    </p>

    <div class="d-flex justify-content-center mb-4">
      <div class="p-3 bg-white rounded-3 shadow-sm d-inline-block">
        <div id="qrcode"></div>
      </div>
    </div>

    <div class="bg-dark p-3 rounded-3 text-start small mb-4 border border-secondary border-opacity-25">
      <div class="d-flex justify-content-between mb-1">
        <span class="text-muted">Red:</span>
        <span class="text-white fw-semibold"><?= esc($network->name) ?></span>
      </div>
      <div class="d-flex justify-content-between mb-1">
        <span class="text-muted">IP asignada:</span>
        <span class="net-mono"><?= esc($device->ip_address) ?></span>
      </div>
      <div class="d-flex justify-content-between">
        <span class="text-muted">Enrutamiento:</span>
        <span class="text-primary"><?= ($device->routing_mode ?? 'split') === 'full' ? 'Todo el Tráfico (Internet)' : 'Red Local' ?></span>
      </div>
    </div>

    <div class="d-grid gap-2">
      <a id="btnPrimaryInstaller" href="<?= site_url('connect/' . $token . '/download?platform=yml') ?>" class="btn btn-primary py-2 fw-semibold d-inline-flex align-items-center justify-content-center gap-2">
        <i class="ti ti-download" id="primaryInstallerIcon"></i>
        <span id="primaryInstallerLabel">Descargar configuración (.yml)</span>
      </a>
      <small id="installerHint" class="text-muted d-block mt-1"></small>
    </div>

    <div class="mt-3 pt-3 border-top border-secondary border-opacity-25">
      <span class="text-muted small d-block mb-2">¿Configurando otro dispositivo?</span>
      <div class="d-flex flex-wrap justify-content-center gap-2">
        <a href="<?= site_url('connect/' . $token . '/download?platform=mac') ?>" class="btn btn-outline-primary btn-sm">macOS</a>
        <a href="<?= site_url('connect/' . $token . '/download?platform=windows') ?>" class="btn btn-outline-primary btn-sm">Windows</a>
        <a href="<?= site_url('connect/' . $token . '/download?platform=linux') ?>" class="btn btn-outline-primary btn-sm">Linux</a>
        <a href="<?= site_url('connect/' . $token . '/download?platform=yml') ?>" class="btn btn-outline-secondary btn-sm">.yml</a>
      </div>
    </div>

    <div class="mt-4">
      <small class="text-muted d-block connect-note">
        Este enlace temporal expira en 60 minutos por razones de seguridad.
      </small>
    </div>
  </div>

  <script>
    document.addEventListener("DOMContentLoaded", function () {
      const configText = <?= json_encode($config) ?>;
      const baseDownloadUrl = <?= json_encode(site_url('connect/' . $token . '/download')) ?>;

      new QRCode(document.getElementById("qrcode"), {
        text: configText,
        width: 220,
        height: 220,
        colorDark : "#000000",
        colorLight : "#ffffff",
        correctLevel : QRCode.CorrectLevel.M
      });

      const ua = navigator.userAgent || '';
      const platform = (navigator.platform || '').toLowerCase();
      const btn = document.getElementById('btnPrimaryInstaller');
      const lbl = document.getElementById('primaryInstallerLabel');
      const ico = document.getElementById('primaryInstallerIcon');
      const hint = document.getElementById('installerHint');

      if (/iphone|ipad|ipod/i.test(ua)) {
        btn.href = baseDownloadUrl + '?platform=yml';
        ico.className = 'ti ti-brand-apple';
        lbl.textContent = 'Descargar perfil para iPhone / iPad (.yml)';
        hint.textContent = 'Instala la app gratuita Nebula en App Store e importa el archivo.';
      } else if (/android/i.test(ua)) {
        btn.href = baseDownloadUrl + '?platform=yml';
        ico.className = 'ti ti-brand-android';
        lbl.textContent = 'Descargar perfil para Android (.yml)';
        hint.textContent = 'Instala la app gratuita Nebula en Google Play e importa el archivo.';
      } else if (platform.includes('mac') || /macintosh|mac os x/i.test(ua)) {
        btn.href = baseDownloadUrl + '?platform=mac';
        ico.className = 'ti ti-brand-apple';
        lbl.textContent = 'Descargar Configurador para Mac (.zip)';
        hint.textContent = 'Abre el ZIP y haz doble clic en Instalar-NetCrew.';
      } else if (platform.includes('win') || /windows/i.test(ua)) {
        btn.href = baseDownloadUrl + '?platform=windows';
        ico.className = 'ti ti-brand-windows';
        lbl.textContent = 'Descargar Configurador para Windows (.bat)';
        hint.textContent = 'Haz doble clic en el archivo descargado para instalar y conectar.';
      } else if (platform.includes('linux') || /linux/i.test(ua)) {
        btn.href = baseDownloadUrl + '?platform=linux';
        ico.className = 'ti ti-brand-ubuntu';
        lbl.textContent = 'Descargar Configurador para Linux (.sh)';
        hint.textContent = 'Ejecuta: bash instalar-netcrew-*.sh';
      }
    });
  </script>
</body>
</html>

