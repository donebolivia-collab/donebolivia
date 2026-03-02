<?php
date_default_timezone_set('America/La_Paz'); // <-- Nueva línea para establecer la zona horaria

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
iniciarSesion();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo isset($titulo) ? $titulo . ' - ' : ''; ?>Done!</title>
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome optimizado - Solo iconos sólidos (200 KB en lugar de 900 KB) -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.0/css/fontawesome.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.0/css/solid.min.css">
  <!-- CSS unificado y optimizado -->
  <link href="/assets/css/bundle.min.css?v=<?php echo APP_VERSION; ?>" rel="stylesheet">
  <link href="/assets/css/header.css?v=<?php echo APP_VERSION; ?>" rel="stylesheet">
  <link href="/assets/css/style.css?v=<?php echo APP_VERSION; ?>" rel="stylesheet">
  <link href="/assets/css/form-upload-fix.css?v=<?php echo APP_VERSION; ?>" rel="stylesheet">
  <link href="/assets/css/mobile-menu.css?v=<?php echo APP_VERSION; ?>" rel="stylesheet">
  <!-- Botones profesionales -->
  <link href="/assets/css/buttons-pro.css?v=<?php echo APP_VERSION; ?>" rel="stylesheet">

  <!-- Scripts optimizados y minificados -->
  <script src="/assets/js/lazy-load.min.js?v=<?php echo APP_VERSION; ?>" defer></script>
  <script src="/assets/js/favorites-share-fix.min.js?v=<?php echo APP_VERSION; ?>" defer></script>
  <script src="/assets/js/user-menu.min.js?v=<?php echo APP_VERSION; ?>" defer></script>
  <script src="/assets/js/form-wizard.min.js?v=<?php echo APP_VERSION; ?>" defer></script>
  <script src="/assets/js/mobile-menu.min.js?v=<?php echo APP_VERSION; ?>" defer></script>
  <?php if (isset($extra_head)) echo $extra_head; ?>
</head>
<body>
  <header class="yx-topbar">
    <div class="container-fluid">
      <div class="d-flex align-items-center justify-content-between">
        <!-- Left: Brand Home -->
        <a href="/" class="brand-home" aria-label="Inicio">
          <img src="/assets/img/doneback.svg" alt="Done!" class="brand-logo" fetchpriority="high">
        </a>
        
        <!-- Botón Hamburguesa (solo móvil) -->
        <button class="hamburger-btn" id="hamburgerBtn" aria-label="Menú">
          <i class="fas fa-bars"></i>
        </button>
        
        <!-- Right: Buttons (dynamic - desktop) -->
        <?php if (estaLogueado()): $usr = obtenerUsuarioActual(); ?>
        <div class="d-flex align-items-center gap-3 desktop-nav">
          <a class="rainbow-btn" href="/feria">
            <span>Feria Virtual</span>
          </a>
          
          <?php
          // Ocultar botón "Publicar anuncio" si ya estás en esa página O EN FERIA
          $currentPage = $_SERVER['REQUEST_URI'];
          $isPublishPage = (strpos($currentPage, '/products/add_product') !== false);
          $isFeriaPage = (strpos($currentPage, '/feria') !== false);
          
          if (!$isPublishPage && !$isFeriaPage):
          ?>
          <a class="btn-top btn-publish" href="/products/add_product">
            Publicar anuncio
          </a>
          <?php endif; ?>

          <!-- Menú de usuario -->
          <div class="user-menu-dropdown">
            <button class="btn-top user-menu-trigger" id="userMenuBtn" aria-expanded="false" aria-haspopup="true">
                <?php $fotoPerfilHeader = !empty($usr['foto_perfil']) ? $usr['foto_perfil'] : ($_SESSION['foto_perfil'] ?? null); ?>
                <?php if (!empty($fotoPerfilHeader)): ?>
                  <img src="/uploads/perfiles/<?php echo htmlspecialchars($fotoPerfilHeader); ?>"
                       alt="Foto de perfil"
                       style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover;">
                <?php else: ?>
                  <i class="fas fa-user-circle"></i>
                <?php endif; ?>
              <span class=user-name><?php echo htmlspecialchars(mb_convert_case(explode(' ', $usr['nombre'] ?? $usr['email'])[0], MB_CASE_TITLE, 'UTF-8')); ?></span>
              <i class="fas fa-chevron-down dropdown-arrow"></i>
            </button>
            
            <!-- Dropdown Menu -->
            <div class="user-dropdown-menu" id="userDropdownMenu" role="menu">
              <div class="dropdown-header">
                  <?php $fotoPerfilHeader2 = !empty($usr['foto_perfil']) ? $usr['foto_perfil'] : ($_SESSION['foto_perfil'] ?? null); ?>
                  <?php if (!empty($fotoPerfilHeader2)): ?>
                    <img src="/uploads/perfiles/<?php echo htmlspecialchars($fotoPerfilHeader2); ?>"
                         alt="Foto de perfil"
                         class="user-avatar-img">
                  <?php else: ?>
                    <i class="fas fa-user-circle user-avatar"></i>
                  <?php endif; ?>
                <div class="user-info">
                    <div class=user-full-name><?php echo htmlspecialchars($usr['nombre'] ?? explode('@', $usr['email'])[0]); ?></div>
                  <div class="user-email"><?php echo htmlspecialchars($usr['email'] ?? ''); ?></div>
              </div>
              </div>

              <div class="dropdown-divider"></div>

              <a href="/mi/perfil" class="dropdown-item" role="menuitem">
                <i class="fas fa-user"></i>
                <span>Mi perfil</span>
              </a>

              <a href="/mi/publicaciones" class="dropdown-item" role="menuitem">
                <i class="fas fa-box"></i>
                <span>Mis publicaciones</span>
              </a>

              <a href="/favorites" class="dropdown-item" role="menuitem">
                <i class="fas fa-heart"></i>
                <span>Mis favoritos</span>
              </a>

              <a href="/mi/business" class="dropdown-item" role="menuitem">
                <i class="fas fa-store"></i>
                <span>Mi tienda</span>
              </a>

              <div class="dropdown-divider"></div>

              <a href="/auth/logout" class="dropdown-item logout-item" role="menuitem">
                <i class="fas fa-sign-out-alt"></i>
                <span>Cerrar sesión</span>
              </a>
            </div>
          </div>

          <?php
          /* 
          // CÓDIGO ELIMINADO: Ya no mostramos el botón "Publicar anuncio" al final
          // porque ahora está antes del usuario.
          
          // Ocultar botón "Publicar anuncio" si ya estás en esa página O EN FERIA
          $currentPage = $_SERVER['REQUEST_URI'];
          $isPublishPage = (strpos($currentPage, '/products/add_product') !== false);
          $isFeriaPage = (strpos($currentPage, '/feria') !== false);
          
          if (!$isPublishPage && !$isFeriaPage):
          ?>
          <a class="btn-top btn-publish" href="/products/add_product">
            Publicar anuncio
          </a>
          <?php endif; */ ?>
        </div>
        <?php else: ?>
        <div class="d-flex align-items-center gap-3 desktop-nav">
          <a class="rainbow-btn" href="/feria">
            <span>Feria Virtual</span>
          </a>
          
          <?php
          // Ocultar botón "Publicar anuncio" si ya estás en esa página
          $currentPage = $_SERVER['REQUEST_URI'];
          $isPublishPage = (strpos($currentPage, '/products/add_product') !== false);
          $isLoginPage = (strpos($currentPage, '/auth/login') !== false); // Detectar Login
          
          if (!$isPublishPage && !$isLoginPage): // Ocultar si es Login
          ?>
          <a class="btn-top btn-publish" href="/auth/login?redirect=/products/add_product">
            Publicar anuncio
          </a>
          <?php endif; ?>

          <?php if (!$isLoginPage): // Ocultar si es Login ?>
          <a class="btn-top" href="/auth/login">
            Iniciar Sesión
          </a>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <!-- Menú lateral móvil -->
  <div class="mobile-menu-overlay" id="mobileMenuOverlay"></div>
  <nav class="mobile-side-menu" id="mobileSideMenu">
    <div class="mobile-menu-header">
      <img src="/assets/img/doneback.svg" alt="Done!" class="mobile-menu-logo">
      <button class="close-menu-btn" id="closeMenuBtn">
        <i class="fas fa-times"></i>
      </button>
    </div>
    
    <div class="mobile-menu-content">
      <?php if (estaLogueado()): $usr = obtenerUsuarioActual(); ?>
        <!-- Usuario logueado -->
        <div class="mobile-user-info">
          <?php $fotoPerfilMobile = !empty($usr['foto_perfil']) ? $usr['foto_perfil'] : ($_SESSION['foto_perfil'] ?? null); ?>
          <?php if (!empty($fotoPerfilMobile)): ?>
            <img src="/uploads/perfiles/<?php echo htmlspecialchars($fotoPerfilMobile); ?>" 
                 alt="Foto de perfil" 
                 class="mobile-user-avatar">
          <?php else: ?>
            <i class="fas fa-user-circle mobile-user-icon"></i>
          <?php endif; ?>
          <div class="mobile-user-details">
            <div class="mobile-user-name"><?php echo htmlspecialchars($usr['nombre'] ?? explode('@', $usr['email'])[0]); ?></div>
            <div class="mobile-user-email"><?php echo htmlspecialchars($usr['email'] ?? ''); ?></div>
          </div>
        </div>
        
        <div class="mobile-menu-divider"></div>
        
        <a href="/products/add_product.php" class="mobile-menu-item cta-item">
          <span>Publicar anuncio</span>
        </a>

        <!-- Botón Feria Virtual (Estilo Rainbow Móvil) -->
        <a href="/feria" class="mobile-menu-item rainbow-item" style="position: relative; overflow: hidden; font-weight: 700;">
          <span style="position: relative; z-index: 2; background: linear-gradient(90deg, #ff0000, #ff7f00, #ffff00, #00ff00, #0000ff, #4b0082, #9400d3); -webkit-background-clip: text; color: transparent; background-clip: text;">Feria Virtual</span>
          <i class="fas fa-store" style="color: #ff6b1a;"></i>
        </a>
        
        <a href="/" class="mobile-menu-item">
          <i class="fas fa-home"></i>
          <span>Inicio</span>
        </a>
        
        <a href="/mi/perfil.php" class="mobile-menu-item">
          <i class="fas fa-user"></i>
          <span>Mi perfil</span>
        </a>

        <a href="/mi/publicaciones.php" class="mobile-menu-item">
          <i class="fas fa-box"></i>
          <span>Mis publicaciones</span>
        </a>
        
        <a href="/favorites.php" class="mobile-menu-item">
          <i class="fas fa-heart"></i>
          <span>Mis favoritos</span>
        </a>

        <a href="/mi/business.php" class="mobile-menu-item">
          <i class="fas fa-store"></i>
          <span>Mi tienda</span>
        </a>
        
        <div class="mobile-menu-divider"></div>
        
        <a href="/auth/logout.php" class="mobile-menu-item logout-item">
          <i class="fas fa-sign-out-alt"></i>
          <span>Cerrar sesión</span>
        </a>
      <?php else: ?>
        <!-- Usuario no logueado -->
        <a href="/auth/login.php?redirect=/products/add_product.php" class="mobile-menu-item cta-item">
          <span>Publicar anuncio</span>
        </a>

        <!-- Botón Feria Virtual (Estilo Rainbow Móvil) -->
        <a href="/feria" class="mobile-menu-item rainbow-item" style="position: relative; overflow: hidden; font-weight: 700;">
          <span style="position: relative; z-index: 2; background: linear-gradient(90deg, #ff0000, #ff7f00, #ffff00, #00ff00, #0000ff, #4b0082, #9400d3); -webkit-background-clip: text; color: transparent; background-clip: text;">Feria Virtual</span>
          <i class="fas fa-store" style="color: #ff6b1a;"></i>
        </a>
        
        <a href="/" class="mobile-menu-item">
          <i class="fas fa-home"></i>
          <span>Inicio</span>
        </a>
        
        <a href="/auth/register.php" class="mobile-menu-item">
          <i class="fas fa-user-plus"></i>
          <span>Crear Cuenta</span>
        </a>
        
        <a href="/auth/login.php" class="mobile-menu-item">
          <i class="fas fa-sign-in-alt"></i>
          <span>Ingresar</span>
        </a>
      <?php endif; ?>
    </div>
  </nav>

  <main class="yx-wrap">
