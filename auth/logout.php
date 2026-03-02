<?php
require_once '../includes/functions.php';
iniciarSesion();

// Destruir todas las variables de sesión
$_SESSION = array();

// Destruir la cookie de sesión si existe
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destruir cookie de recordar si existe
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Destruir la sesión
unset($_SESSION['usuario_id'], $_SESSION['usuario_nombre'], $_SESSION['usuario_email'], $_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['uid']);
session_destroy();

// Redireccionar al login con mensaje
redireccionar('login.php?mensaje=logout');
?>
