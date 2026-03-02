<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!estaLogueado()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id']) || !isset($data['producto_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
    exit;
}

$imagen_id = (int)$data['id'];
$producto_id = (int)$data['producto_id'];

try {
    $db = getDB();
    
    // Verificar que el producto pertenece al usuario
    $stmt = $db->prepare("SELECT id FROM productos WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$producto_id, $_SESSION['usuario_id']]);
    
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Sin permiso']);
        exit;
    }
    
    // Obtener nombre del archivo
    $stmt = $db->prepare("SELECT nombre_archivo FROM producto_imagenes WHERE id = ? AND producto_id = ?");
    $stmt->execute([$imagen_id, $producto_id]);
    $imagen = $stmt->fetch();
    
    if (!$imagen) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Imagen no encontrada']);
        exit;
    }
    
    // Eliminar archivo físico
    $ruta = __DIR__ . '/../uploads/' . $imagen['nombre_archivo'];
    if (file_exists($ruta)) {
        unlink($ruta);
    }
    
    // Eliminar de base de datos
    $stmt = $db->prepare("DELETE FROM producto_imagenes WHERE id = ?");
    $stmt->execute([$imagen_id]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error del servidor']);
    error_log("Error al eliminar imagen: " . $e->getMessage());
}
