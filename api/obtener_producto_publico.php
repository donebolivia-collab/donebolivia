<?php
/**
 * API PÚBLICA: Obtener datos de un producto para el modal de tienda
 * No requiere autenticación de usuario, solo ID de producto válido
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

try {
    $producto_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if (!$producto_id) {
        throw new Exception('ID inválido');
    }

    $db = getDB();

    // 1. Obtener info del producto + datos del vendedor (para WhatsApp)
    $stmt = $db->prepare("
        SELECT p.*, 
               u.telefono as telefono_vendedor,
               c.nombre as categoria_nombre
        FROM productos p
        JOIN usuarios u ON p.usuario_id = u.id
        LEFT JOIN categorias c ON p.categoria_id = c.id
        WHERE p.id = ? AND p.activo = 1
    ");
    $stmt->execute([$producto_id]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$producto) {
        throw new Exception('Producto no encontrado');
    }

    // 2. Obtener imágenes
    $stmt_img = $db->prepare("
        SELECT nombre_archivo 
        FROM producto_imagenes 
        WHERE producto_id = ? 
        ORDER BY es_principal DESC, orden ASC
    ");
    $stmt_img->execute([$producto_id]);
    $imagenes = $stmt_img->fetchAll(PDO::FETCH_ASSOC);

    // 3. Obtener badges del producto
    $stmt_badges = $db->prepare("
        SELECT badge_id 
        FROM producto_badges 
        WHERE producto_id = ?
    ");
    $stmt_badges->execute([$producto_id]);
    $badges = $stmt_badges->fetchAll(PDO::FETCH_COLUMN);
    $producto['badges'] = $badges;

    // 4. Formatear datos extra para el frontend
    $producto['precio_formateado'] = formatearPrecio($producto['precio']);
    $producto['precio_literal'] = convertirPrecioALiteral($producto['precio']);
    
    // 4. Incrementar vistas (con control de sesión)
    session_start();
    $viewed_key = 'viewed_product_' . $producto_id;
    if (!isset($_SESSION[$viewed_key])) {
        incrementarVistas($producto_id); // Usa la función base, pero protegida por sesión aquí
        $_SESSION[$viewed_key] = true;
        $producto['visitas']++; // Reflejar incremento en la respuesta
    }

    // 5. Verificar si el usuario dio like
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $stmt_like = $db->prepare("SELECT id FROM producto_likes WHERE producto_id = ? AND ip_address = ?");
    $stmt_like->execute([$producto_id, $ip_address]);
    $user_has_liked = $stmt_like->fetch() ? true : false;

    echo json_encode([
        'success' => true,
        'producto' => $producto,
        'imagenes' => $imagenes,
        'user_has_liked' => $user_has_liked
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
