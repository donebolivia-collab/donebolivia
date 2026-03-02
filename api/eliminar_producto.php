<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!estaLogueado()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID de producto requerido']);
    exit;
}

try {
    $db = getDB();
    $usuario_id = $_SESSION['usuario_id'];

    // Verificar que el producto pertenezca al usuario
    $stmt = $db->prepare("SELECT id FROM productos WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$id, $usuario_id]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Producto no encontrado o no autorizado']);
        exit;
    }

    // Eliminar imágenes asociadas (Opcional, pero recomendado para limpieza)
    // Por simplicidad, primero eliminamos de la BD. Los archivos físicos podrían quedar o borrarse aquí.
    // Vamos a borrar solo el registro en BD por ahora para ser seguros.
    
    $stmtDelete = $db->prepare("DELETE FROM productos WHERE id = ?");
    $stmtDelete->execute([$id]);

    echo json_encode(['success' => true, 'message' => 'Producto eliminado correctamente']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error al eliminar: ' . $e->getMessage()]);
}
