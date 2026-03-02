<?php
// /signup.php — abre el register correcto sin importar dónde esté
$try = [
  __DIR__ . '/auth/register.php',
  __DIR__ . '/auth/auth/register.php',
  __DIR__ . '/account/register.php',
  __DIR__ . '/usuarios/register.php',
];
foreach ($try as $p) {
  if (is_file($p)) { require $p; exit; }
}
http_response_code(404);
echo 'Register no encontrado. Revise la ruta: /auth/register.php';
