<?php
/**
 * API: Obtener datos de un producto para edición
 */
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (!estaLogueado()) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

try {
    $producto_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if (!$producto_id) {
        throw new Exception('ID de producto inválido');
    }

    $db = getDB();
    $usuario_id = $_SESSION['usuario_id'];

    // Obtener producto completo verificando que pertenece al usuario
    $stmt = $db->prepare("
        SELECT id, titulo, descripcion, precio, estado,
               categoria_id, subcategoria_id, categoria_tienda,
               departamento_codigo, municipio_codigo,
               departamento_nombre, municipio_nombre,
               activo, envio_gratis,
               (SELECT GROUP_CONCAT(badge_id SEPARATOR ',') FROM producto_badges
                WHERE producto_id = id) AS badges
        FROM productos
        WHERE id = ? AND usuario_id = ?
    ");
    $stmt->execute([$producto_id, $usuario_id]);
    $producto = $stmt->fetch();

    if (!$producto) {
        throw new Exception('Producto no encontrado');
    }

    // Forzar tipos de datos para evitar problemas con JS
    if($producto) {
        $producto['envio_gratis'] = (int)$producto['envio_gratis'];
        $producto['activo'] = (int)$producto['activo'];
        $producto['precio'] = (float)$producto['precio'];
        
        // Procesar badges
        if (!empty($producto['badges'])) {
            $producto['badges'] = explode(',', $producto['badges']);
        } else {
            $producto['badges'] = [];
        }
    }

    // Obtener imágenes del producto
    $stmt_img = $db->prepare("
        SELECT id, nombre_archivo, es_principal
        FROM producto_imagenes
        WHERE producto_id = ?
        ORDER BY es_principal DESC, orden ASC
    ");
    $stmt_img->execute([$producto_id]);
    $producto['imagenes'] = $stmt_img->fetchAll();

    echo json_encode([
        'success' => true,
        'producto' => $producto
    ]);

} catch (Exception $e) {
    error_log("Error en obtener_producto.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
