<?php
// /vender.php — Atajo para publicar productos
// Redirige al formulario oficial de "Publicar anuncio"
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Si no está logueado, login primero y luego volver a publicar
if (empty($_SESSION['usuario_id'])) {
    $next = '/products/add_product.php';
    header('Location: /auth/login.php?redirect=' . urlencode($next));
    exit;
}

// Redirección directa al formulario de publicación
header('Location: /products/add_product.php');
exit;
