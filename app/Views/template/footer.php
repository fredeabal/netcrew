        </div>
      </div>
    </div>
  <!-- =====================================================================
       PIE DE PÁGINA Y SCRIPTS GLOBALES (FOOTER)
       ===================================================================== -->
  <!-- Importar Archivos Js -->
  <script src="<?= base_url('assets/') ?>libs/jquery/dist/jquery.min.js"></script>
  <script src="<?= base_url('assets/') ?>libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script src="<?= base_url('assets/') ?>libs/simplebar/dist/simplebar.min.js"></script>
  <script src="<?= base_url('assets/') ?>js/theme/app.init.js"></script>
  <script src="<?= base_url('assets/') ?>js/theme/theme.js"></script>
  <script src="<?= base_url('assets/') ?>js/theme/app.min.js"></script>
  <script src="<?= base_url('assets/') ?>js/theme/sidebarmenu.js"></script>

  <!-- solar icons -->
  <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>

  <!-- highlight.js (code view) -->
  <script src="<?= base_url('assets/') ?>js/highlights/highlight.min.js"></script>
  <script>
  hljs.initHighlightingOnLoad();


  document.querySelectorAll("pre.code-view > code").forEach((codeBlock) => {
    codeBlock.textContent = codeBlock.innerHTML;
  });
</script>

  <!-- Persistencia de tema en localStorage -->
  <script>
    document.querySelectorAll('.dark-layout').forEach(function(el) {
      el.addEventListener('click', function() {
        localStorage.setItem('theme', 'dark');
      });
    });
    document.querySelectorAll('.light-layout').forEach(function(el) {
      el.addEventListener('click', function() {
        localStorage.setItem('theme', 'light');
      });
    });

    // Auto-dismiss standard alerts
    setTimeout(function() {
      var alerts = document.querySelectorAll('.alert-dismissible');
      alerts.forEach(function(alertElement) {
        if (typeof bootstrap !== 'undefined') {
          var bsAlert = new bootstrap.Alert(alertElement);
          bsAlert.close();
        } else {
          alertElement.style.display = 'none';
        }
      });
    }, 3000);

    // SweetAlert2 global submit interceptor (for forms)
    document.addEventListener("submit", function(e) {
      let form = e.target;
      if (form.hasAttribute("data-confirm") && !form.dataset.confirmed) {
        e.preventDefault();
        const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        Swal.fire({
          title: '¿Confirmas esta acción?',
          text: form.getAttribute("data-confirm"),
          icon: 'warning',
          background: isDark ? '#0b1114' : '#f8f9fa',
          color: isDark ? '#ffffff' : '#0b1114',
          iconColor: '#F38020',
          showCancelButton: true,
          reverseButtons: true,
          customClass: {
            confirmButton: 'btn btn-primary ms-2',
            cancelButton: 'btn btn-danger'
          },
          buttonsStyling: false,
          confirmButtonText: 'Sí, confirmar',
          cancelButtonText: 'Cancelar'
        }).then((result) => {
          if (result.isConfirmed) {
            form.dataset.confirmed = "true";
            form.submit();
          }
        });
      }
    });

    // SweetAlert2 global click interceptor (for links/buttons con data-confirm)
    document.addEventListener("click", function(e) {
      let confirmEl = e.target.closest("[data-confirm]");
      if (confirmEl) {
        let form = confirmEl.closest("form");
        
        if (!form || !form.hasAttribute("data-confirm")) {
          e.preventDefault();
          const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
          Swal.fire({
            title: '¿Confirmas esta acción?',
            text: confirmEl.getAttribute("data-confirm"),
            icon: 'warning',
            background: isDark ? '#0b1114' : '#f8f9fa',
            color: isDark ? '#ffffff' : '#0b1114',
            iconColor: '#F38020',
            showCancelButton: true,
            reverseButtons: true,
            customClass: {
              confirmButton: 'btn btn-primary ms-2',
              cancelButton: 'btn btn-danger'
            },
            buttonsStyling: false,
            confirmButtonText: 'Sí, confirmar',
            cancelButtonText: 'Cancelar'
          }).then((result) => {
            if (result.isConfirmed) {
              if (form) {
                form.dataset.confirmed = "true";
                form.submit();
              } else if (confirmEl.tagName === 'A') {
                window.location.href = confirmEl.href;
              }
            }
          });
        }
      }
    });

    // Si el sistema cambia de tema y el usuario no ha elegido manualmente, seguir al sistema
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
      if (!localStorage.getItem('theme')) {
        var theme = e.matches ? 'dark' : 'light';
        document.documentElement.setAttribute('data-bs-theme', theme);
      }
    });

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
  <script src="<?= base_url('assets/libs/sweetalert2/dist/sweetalert2.min.js') ?>"></script>
</body>

</html>