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
      Escanea el código QR con la app oficial de WireGuard o descarga el archivo <code>.conf</code>.
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
        <span class="text-info"><?= ($device->routing_mode ?? 'split') === 'full' ? 'Todo el Tráfico (Internet)' : 'Red Local' ?></span>
      </div>
    </div>

    <div class="d-grid gap-2">
      <button type="button" id="btnDownloadConf" class="btn btn-primary py-2 fw-semibold">
        <i class="ti ti-download me-1"></i> Descargar archivo .conf
      </button>
      <a id="lnkFallbackDownload" href="<?= site_url('connect/' . $token . '/download') ?>" class="d-none"></a>
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
      const fileName = <?= json_encode(url_title($device->name, '-', true) . '.conf') ?>;

      new QRCode(document.getElementById("qrcode"), {
        text: configText,
        width: 220,
        height: 220,
        colorDark : "#000000",
        colorLight : "#ffffff",
        correctLevel : QRCode.CorrectLevel.M
      });

      document.getElementById("btnDownloadConf").addEventListener("click", function () {
        try {
          const blob = new Blob([configText], { type: "text/plain;charset=utf-8" });
          const url = URL.createObjectURL(blob);
          const a = document.createElement("a");
          a.href = url;
          a.download = fileName;
          document.body.appendChild(a);
          a.click();
          document.body.removeChild(a);
          URL.revokeObjectURL(url);
        } catch (e) {
          window.location.href = document.getElementById("lnkFallbackDownload").href;
        }
      });
    });
  </script>
</body>
</html>

