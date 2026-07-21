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
  <title>Recuperar Contraseña - Sistema</title>
  <link rel="shortcut icon" type="image/png" href="<?= base_url('assets/images/logos/favicon.png') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/styles.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/custom.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/libs/sweetalert2/dist/sweetalert2.min.css') ?>" />
</head>
<body>

  <div id="main-wrapper" class="auth-customizer-none">
    <div class="position-relative overflow-hidden min-vh-100 w-100 d-flex align-items-center justify-content-center">
      <div class="d-flex align-items-center justify-content-center w-100">
        <div class="row justify-content-center w-100">
          <div class="col-md-8 col-lg-6 col-xxl-3 auth-card">
            <div class="card mb-0">
              <div class="card-body">
                <a href="<?= base_url() ?>" class="text-nowrap logo-img text-center d-block mb-5 w-100">
                  <img src="<?= base_url('assets/images/logos/dark-logo.svg') ?>" class="dark-logo" alt="Logo-Dark" />
                  <img src="<?= base_url('assets/images/logos/light-logo.svg') ?>" class="light-logo" alt="Logo-light" />
                </a>

                <h2 class="mb-1 fs-7 fw-bolder text-center">Recuperar Contraseña</h2>
                <p class="mb-4 text-center text-muted">Ingresa tu correo y te enviaremos un enlace de recuperación para iniciar sesión al instante.</p>



                <form action="<?= url_to('magic-link') ?>" method="post" novalidate>
                  <?= csrf_field() ?>

                  <div class="mb-4">
                    <label for="email" class="form-label">Correo Electrónico</label>
                    <input type="email" class="form-control" id="email" name="email" autocomplete="email" placeholder="<?= lang('Auth.email') ?>" value="<?= old('email') ?>">
                  </div>
                  
                  <button type="submit" class="btn btn-primary w-100 py-8 mb-4 rounded-2">Enviar Enlace</button>
                  
                  <div class="d-flex align-items-center justify-content-center">
                    <a class="text-muted fs-3" href="<?= url_to('login') ?>">Volver al inicio de sesión</a>
                  </div>
                </form>

              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="<?= base_url('assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js') ?>"></script>
  <script src="<?= base_url('assets/libs/simplebar/dist/simplebar.min.js') ?>"></script>
  <script src="<?= base_url('assets/js/theme/app.init.js') ?>"></script>
  <script src="<?= base_url('assets/js/theme/theme.js') ?>"></script>
  <script src="<?= base_url('assets/js/theme/app.min.js') ?>"></script>
  <script src="<?= base_url('assets/libs/sweetalert2/dist/sweetalert2.min.js') ?>"></script>
  <script>
    document.addEventListener("DOMContentLoaded", function() {
      const toastMessage = <?= json_encode(session()->getFlashdata('message') ?? session()->getFlashdata('success')) ?>;
      const toastError = <?= json_encode(session()->getFlashdata('error')) ?>;
      const toastErrors = <?= json_encode(session()->getFlashdata('errors')) ?>;
      
      const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
      
      const systemAlert = Swal.mixin({
        position: 'center',
        showConfirmButton: false,
        buttonsStyling: false,
        timer: 5000,
        timerProgressBar: true,
        background: isDark ? '#0b1114' : '#f8f9fa',
        color: isDark ? '#fff' : '#0b1114',
        showCloseButton: false
      });
      
      if (toastMessage) {
        systemAlert.fire({ icon: 'success', title: '¡Completado!', html: `<div class="text-center">${toastMessage}</div>`, iconColor: '#10B981' });
      }
      if (toastError) {
        systemAlert.fire({ icon: 'error', title: 'Error', html: `<div class="text-center">${toastError}</div>`, iconColor: '#b31b34' });
      }
      if (toastErrors) {
        const errorContent = typeof toastErrors === 'object' && toastErrors !== null
          ? (Array.isArray(toastErrors) ? toastErrors : Object.values(toastErrors)).join('<br>') 
          : toastErrors;
        systemAlert.fire({ icon: 'error', title: 'Error de Validación', html: `<div class="text-center">${errorContent}</div>`, iconColor: '#b31b34' });
      }
    });
  </script>
</body>
</html>
