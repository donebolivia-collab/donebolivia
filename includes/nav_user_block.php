<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$isLogged = !empty($_SESSION['usuario_id']) || !empty($_SESSION['user_id']) || !empty($_SESSION['uid']);
$userName = $_SESSION['usuario_nombre'] ?? $_SESSION['user_name'] ?? null;
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<div class="d-flex align-items-center gap-2">
<?php if ($isLogged): ?>
  <span class="text-white fw-semibold me-2">Hola, <?php echo e($userName ?: 'Usuario'); ?></span>
  <a class="btn-top" href="/auth/logout.php">Salir</a>
  <a class="btn-top" href="/vender.php">Publicar anuncio</a>
<?php else: ?>
  <a class="btn-top" href="/signup.php">Crear Cuenta</a>
  <a class="btn-top" href="/login.php">Ingresar</a>
  <a class="btn-top" href="/login.php?next=/publicar/">Publicar anuncio</a>
<?php endif; ?>
</div>
