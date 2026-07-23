    <!-- =====================================================================
         BARRA LATERAL DE NAVEGACIÓN (ASIDE / SIDEBAR)
         Menú principal de la aplicación, generado dinámicamente según permisos.
         ===================================================================== -->
    <!-- Inicio del Menú Lateral -->
    <aside class="left-sidebar with-vertical">
      <div><!-- ---------------------------------- -->
        <!-- Inicio del Layout Vertical del Menú Lateral -->
        <!-- ---------------------------------- -->
        <div class="brand-logo d-flex align-items-center justify-content-between">
          <a href="<?= base_url() ?>" class="text-nowrap logo-img">
            <img src="<?= base_url('assets/') ?>images/logos/dark-logo.svg" class="dark-logo" alt="Logo-Dark" />
            <img src="<?= base_url('assets/') ?>images/logos/light-logo.svg" class="light-logo" alt="Logo-light" />
          </a>
          <a href="javascript:void(0)" class="sidebartoggler ms-auto text-decoration-none fs-5 d-block d-xl-none">
            <i class="ti ti-x"></i>
          </a>
        </div>

        <nav class="sidebar-nav scroll-sidebar" data-simplebar>
          <ul id="sidebarnav">
            <li class="nav-small-cap">
              <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
              <span class="hide-menu">Menú</span>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="<?= base_url() ?>" aria-expanded="false">
                <span>
                  <i class="ti ti-aperture"></i>
                </span>
                <span class="hide-menu">Dashboard</span>
              </a>
            </li>
            <?php if (auth()->user()->can('admin.users') || auth()->user()->can('users.create')): ?>
            <li class="sidebar-item">
              <a class="sidebar-link" href="<?= base_url('users') ?>" aria-expanded="false">
                <span>
                  <i class="ti ti-users"></i>
                </span>
                <span class="hide-menu">Usuarios</span>
              </a>
            </li>
            <?php endif; ?>
            <?php if (auth()->user()->can('networks.view')): ?>
            <li class="sidebar-item">
              <a class="sidebar-link" href="<?= base_url('networks') ?>" aria-expanded="false">
                <span>
                  <i class="ti ti-network"></i>
                </span>
                <span class="hide-menu">Redes</span>
              </a>
            </li>
            <?php endif; ?>
            <?php if (auth()->user()->can('admin.settings') || auth()->user()->can('admin.roles')): ?>
            <li class="nav-small-cap mt-4">
              <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
              <span class="hide-menu">Administración</span>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link has-arrow" href="javascript:void(0)" aria-expanded="false">
                <span class="d-flex">
                  <i class="ti ti-settings"></i>
                </span>
                <span class="hide-menu">Configuración</span>
              </a>
              <ul aria-expanded="false" class="collapse first-level">
                <?php if (auth()->user()->can('admin.roles')): ?>
                <li class="sidebar-item">
                  <a href="<?= base_url('roles') ?>" class="sidebar-link">
                    <div class="round-16 d-flex align-items-center justify-content-center">
                      <i class="ti ti-circle"></i>
                    </div>
                    <span class="hide-menu">Roles</span>
                  </a>
                </li>
                <?php endif; ?>
                 <?php if (auth()->user()->can('admin.settings')): ?>
                 <li class="sidebar-item">
                   <a href="<?= base_url('settings/smtp') ?>" class="sidebar-link">
                     <div class="round-16 d-flex align-items-center justify-content-center">
                       <i class="ti ti-circle"></i>
                     </div>
                     <span class="hide-menu">Ajustes SMTP</span>
                   </a>
                 </li>
                  <li class="sidebar-item">
                    <a href="<?= base_url('settings/wireguard') ?>" class="sidebar-link">
                      <div class="round-16 d-flex align-items-center justify-content-center">
                        <i class="ti ti-circle"></i>
                      </div>
                      <span class="hide-menu">Ajustes WireGuard</span>
                    </a>
                  </li>
                  <li class="sidebar-item">
                    <a href="<?= base_url('settings/maintenance') ?>" class="sidebar-link">
                      <div class="round-16 d-flex align-items-center justify-content-center">
                        <i class="ti ti-circle"></i>
                      </div>
                      <span class="hide-menu">Mantenimiento</span>
                    </a>
                  </li>
                 <?php endif; ?>
              </ul>
            </li>
            <?php endif; ?>
          </ul>
        </nav>



        <!-- Fin del Layout Vertical del Menú Lateral -->
        <!-- ---------------------------------- -->
      </div>
    </aside>
    <!-- Fin del Menú Lateral -->
    <div class="page-wrapper">
