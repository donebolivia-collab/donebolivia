<?php
// router.php - Manejador de URLs Amigables para PHP Built-in Server

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// 1. Si el archivo existe físicamente, servirlo directamente (imágenes, css, js)
if (file_exists(__DIR__ . $uri) && !is_dir(__DIR__ . $uri)) {
    return false; 
}

// 2. Si la URL termina en / (directorio), buscar index.php
if (is_dir(__DIR__ . $uri)) {
    if (file_exists(__DIR__ . $uri . '/index.php')) {
        include __DIR__ . $uri . '/index.php';
        return;
    }
}

// 3. Reglas de Reescritura (Simulación de .htaccess)

// Caso: /tienda/slug/producto/id
if (preg_match('#^/tienda/([a-z0-9\-]+)/producto/([0-9]+)/?$#', $uri, $matches)) {
    $_GET['slug'] = $matches[1];
    $_GET['producto_id'] = $matches[2];
    include __DIR__ . '/tienda_producto.php';
    return;
}

// Caso: /tienda/slug
if (preg_match('#^/tienda/([a-z0-9\-]+)/?$#', $uri, $matches)) {
    $_GET['slug'] = $matches[1];
    include __DIR__ . '/tienda_pro.php';
    return;
}

// 4. URL Amigable Genérica (Ocultar .php)
// Si piden /feria, buscar /feria.php
$phpFile = __DIR__ . $uri . '.php';
if (file_exists($phpFile)) {
    include $phpFile;
    return;
}

// 5. Si nada coincide, 404
http_response_code(404);
include __DIR__ . '/404.php'; // Asegúrate de tener un 404.php
