<?php
// includes/auto_prepend.php
// Redirección segura si un POST de publicar/crear/anuncio termina en blanco.
// No modifica tu diseño ni tus scripts: se inyecta automáticamente vía .user.ini.

if (session_status() === PHP_SESSION_NONE) { @session_start(); }

// Iniciar buffer para inspeccionar salida final
if (!defined('__APG_OB_STARTED__')) {
  define('__APG_OB_STARTED__', 1);
  ob_start();
  register_shutdown_function(function(){
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    $uri    = strtolower($_SERVER['REQUEST_URI'] ?? '');
    $out    = ob_get_contents();
    $trim   = trim((string)$out);
    $isBlank = ($trim === '' || strlen($trim) < 10);

    $looksLikePublish = (
      strpos($uri, 'public') !== false ||   // publicar, publicacion, etc.
      strpos($uri, 'anuncio') !== false ||
      strpos($uri, 'crear') !== false
    );

    if ($method === 'POST' && $looksLikePublish && $isBlank) {
      // Destino recomendado: inicio (si no podemos construir la vista previa con ID).
      $target = '/index.php';

      // Limpia cualquier salida previa y redirige de forma robusta
      if (!headers_sent()) {
        ob_end_clean();
        header('Location: '.$target, true, 303);
        exit;
      } else {
        ob_end_clean();
        $u = htmlspecialchars($target, ENT_QUOTES, 'UTF-8');
        echo '<!doctype html><meta charset="utf-8">';
        echo '<noscript><meta http-equiv="refresh" content="0;url='.$u.'"></noscript>';
        echo '<script>location.replace(' . json_encode($target) . ');</script>';
        exit;
      }
    }

    // Si no aplica, deja fluir la salida original
  });
}