<?php
@ini_set('display_errors',0);
require_once __DIR__ . '/includes/functions.php';
iniciarSesion();
$target = '/mi/publicaciones.php';
if (!estaLogueado()) {
    redireccionar('/auth/login.php?redirect=' . urlencode($target));
}
redireccionar($target);
