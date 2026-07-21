<!DOCTYPE html>
<html lang="es" dir="ltr" data-color-theme="Blue_Theme" data-layout="vertical">
<script>
  (function() {
    var saved = localStorage.getItem('theme');
    var theme = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    document.documentElement.setAttribute('data-bs-theme', theme);
  })();
</script>
<head>
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Revisa tu correo - Sistema</title>
  <link rel="shortcut icon" type="image/png" href="<?= base_url('assets/images/logos/favicon.png') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/styles.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/custom.css') ?>" />
</head>
<body>

  <div id="main-wrapper" class="auth-customizer-none">
    <div class="position-relative overflow-hidden min-vh-100 w-100 d-flex align-items-center justify-content-center">
      <div class="d-flex align-items-center justify-content-center w-100">
        <div class="row justify-content-center w-100">
          <div class="col-md-8 col-lg-6 col-xxl-3 auth-card">
            <div class="card mb-0">
              <div class="card-body text-center">
                <a href="<?= base_url() ?>" class="text-nowrap logo-img text-center d-block mb-5 w-100">
                  <img src="<?= base_url('assets/images/logos/dark-logo.svg') ?>" class="dark-logo" alt="Logo-Dark" />
                  <img src="<?= base_url('assets/images/logos/light-logo.svg') ?>" class="light-logo" alt="Logo-light" />
                </a>

                <div class="mb-4">
                </div>

                <h2 class="mb-3 fs-7 fw-bolder">Revisa tu correo</h2>
                <p class="mb-4 text-muted">
                  Hemos enviado un enlace de recuperación a tu bandeja de entrada.
                </p>

                <p class="fs-2 text-muted mb-4">
                  <i>No olvides revisar tu carpeta de spam si no lo encuentras.</i>
                </p>
                
                <a href="<?= url_to('login') ?>" class="btn btn-primary w-100 py-8 mb-2 rounded-2">
                  Volver al inicio de sesión
                </a>

              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="<?= base_url('assets/libs/jquery/dist/jquery.min.js') ?>"></script>
  <script src="<?= base_url('assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js') ?>"></script>
  <script src="<?= base_url('assets/js/theme/app.init.js') ?>"></script>
  <script src="<?= base_url('assets/js/theme/theme.js') ?>"></script>
  <script src="<?= base_url('assets/js/theme/app.min.js') ?>"></script>
</body>
</html>
