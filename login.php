<?php
// Minimal alias para evitar errores de ruta sin tocar tu login real.
$target = __DIR__ . '/auth/login.php';
if (is_file($target)) {
  require $target; 
  exit;
}
http_response_code(404);
echo 'No se encontró /auth/login.php';
