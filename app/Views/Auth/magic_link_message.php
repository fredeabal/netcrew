<!DOCTYPE html>
<html lang="es" dir="ltr" data-color-theme="Blue_Theme" data-layout="vertical">
<head>
  <script>
    (function() {
      var saved = localStorage.getItem('theme');
      var theme = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
      document.documentElement.setAttribute('data-bs-theme', theme);
    })();
  </script>
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>NetCrew | Revisa tu correo</title>
  <link rel="shortcut icon" type="image/png" href="<?= base_url('assets/images/logos/favicon.png') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/styles.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/custom.css') ?>" />
</head>
<body>

  <div id="main-wrapper" class="auth-customizer-none">
    <div class="position-relative overflow-hidden min-vh-100 w-100">
      <div class="position-relative z-index-5">
        <div class="row min-vh-100">
          <!-- Columna izquierda: Fondo Naranja y Partículas -->
          <div class="col-xl-7 col-xxl-8 d-none d-xl-flex flex-column justify-content-center align-items-center bg-primary-gradient position-relative">
            
            <!-- Animación de fondo: Red de nodos (Particles.js) -->
            <div id="particles-js" class="position-absolute w-100 h-100 top-0 start-0 z-index-1"></div>

            <div class="px-5 position-relative z-index-2" style="max-width: 650px; width: 100%;">
               <h1 class="text-white display-3 fw-bolder mb-4" style="line-height: 1.1; letter-spacing: -1.5px;">
                 Acceso remoto<br>simple y seguro.
               </h1>
               
               <p class="text-white fs-5 mb-0 opacity-75" style="max-width: 500px; line-height: 1.6;">
                 Conecta tus dispositivos autorizados desde cualquier lugar mediante una VPN Zero Trust basada en identidad.
               </p>
            </div>
          </div>
          
          <!-- Columna derecha: Mensaje de confirmación -->
          <div class="col-xl-5 col-xxl-4 bg-body d-flex justify-content-center align-items-center p-4">
            <div class="auth-max-width w-100 col-sm-8 col-md-6 col-xl-9 px-4 text-center">
              
              <!-- Logo visible en todas las pantallas encima del contenido -->
              <a href="<?= base_url() ?>" class="text-nowrap logo-img text-center d-block mb-5 w-100">
                <img src="<?= base_url('assets/images/logos/dark-logo.svg') ?>" class="dark-logo" alt="Logo-Dark" style="width: 260px; max-width: 100%; height: auto;" />
                <img src="<?= base_url('assets/images/logos/light-logo.svg') ?>" class="light-logo" alt="Logo-light" style="width: 260px; max-width: 100%; height: auto;" />
              </a>

              <div class="mb-4 d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle" style="width: 70px; height: 70px;">
                <i class="ti ti-mail-check fs-8"></i>
              </div>

              <h2 class="mb-2 fs-7 fw-bolder">Revisa tu correo</h2>
              <p class="mb-4 text-muted">
                Hemos enviado un enlace de recuperación a tu bandeja de entrada.
              </p>

              <div class="alert alert-info py-2 px-3 fs-2 text-start mb-4">
                <i class="ti ti-info-circle me-1"></i> No olvides revisar la carpeta de <strong>spam o correo no deseado</strong> si no lo ves en unos minutos.
              </div>
              
              <a href="<?= url_to('login') ?>" class="btn btn-primary w-100 py-8 mb-2 rounded-2">
                Volver al inicio de sesión
              </a>

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
  <script src="<?= base_url('assets/libs/particles.js/particles.min.js') ?>"></script>

  <script>
    document.addEventListener("DOMContentLoaded", function() {
      if (document.getElementById('particles-js')) {
        particlesJS("particles-js", {
          "particles": {
            "number": { "value": 80, "density": { "enable": true, "value_area": 800 } },
            "color": { "value": "#ffffff" },
            "shape": { "type": "circle" },
            "opacity": { "value": 0.5, "random": false },
            "size": { "value": 3, "random": true },
            "line_linked": {
              "enable": true,
              "distance": 150,
              "color": "#ffffff",
              "opacity": 0.4,
              "width": 1
            },
            "move": {
              "enable": true,
              "speed": 6,
              "direction": "none",
              "random": false,
              "straight": false,
              "out_mode": "out",
              "bounce": false
            }
          },
          "interactivity": {
            "detect_on": "canvas",
            "events": {
              "onhover": { "enable": true, "mode": "repulse" },
              "onclick": { "enable": true, "mode": "push" },
              "resize": true
            },
            "modes": {
              "repulse": { "distance": 200, "duration": 0.4 },
              "push": { "particles_nb": 4 }
            }
          },
          "retina_detect": true
        });
      }
    });
  </script>
</body>
</html>
