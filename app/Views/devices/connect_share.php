<!DOCTYPE html>
<html lang="es" dir="ltr" data-bs-theme="dark">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Conectar a VPN - <?= esc($device->name) ?> | NetCrew</title>
  <link rel="shortcut icon" type="image/png" href="<?= base_url('assets/images/logos/favicon.png') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/styles.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/custom.css') ?>" />
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
  <style>
    body {
      background-color: #0b1114;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .connect-card {
      max-width: 520px;
      width: 100%;
      background: #141c22;
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 16px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    }
  </style>
</head>
<body>

  <div class="connect-card p-4 p-md-5 text-center">
    <div class="mb-4">
      <img src="<?= base_url('assets/images/logos/light-logo.svg') ?>" alt="NetCrew" height="38" />
    </div>

    <h4 class="fw-bold text-white mb-2"><?= esc($device->name) ?></h4>
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
        <code class="text-primary"><?= esc($device->ip_address) ?></code>
      </div>
      <div class="d-flex justify-content-between">
        <span class="text-muted">Enrutamiento:</span>
        <span class="text-info"><?= ($device->routing_mode ?? 'split') === 'full' ? 'Todo el Tráfico (Internet)' : 'Red Local' ?></span>
      </div>
    </div>

    <div class="d-grid gap-2">
      <a href="<?= site_url('connect/' . $token . '/download') ?>" class="btn btn-primary py-2 fw-semibold">
        <i class="ti ti-download me-1"></i> Descargar archivo .conf
      </a>
    </div>

    <div class="mt-4">
      <small class="text-muted d-block" style="font-size: 11px;">
        Este enlace temporal expira en 60 minutos por razones de seguridad.
      </small>
    </div>
  </div>

  <script>
    document.addEventListener("DOMContentLoaded", function () {
      new QRCode(document.getElementById("qrcode"), {
        text: <?= json_encode($config) ?>,
        width: 220,
        height: 220,
        colorDark : "#000000",
        colorLight : "#ffffff",
        correctLevel : QRCode.CorrectLevel.M
      });
    });
  </script>
</body>
</html>

