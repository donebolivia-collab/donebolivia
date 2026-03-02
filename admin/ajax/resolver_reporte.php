<?php
session_start();
require_once '../../config/database.php';
require_once '../admin_functions.php';
require_once '../../includes/auth.php'; // Agregado: Seguridad CSRF

header('Content-Type: application/json');

// Verificar autenticación
if (!esAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Obtener datos
$data = json_decode(file_get_contents('php://input'), true);
$reporte_id = intval($data['reporte_id'] ?? 0);
$producto_id = intval($data['producto_id'] ?? 0);
$token = $data['csrf_token'] ?? '';

// Verificar CSRF
if (!auth_csrf_check($token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Error de seguridad CSRF']);
    exit;
}

if (!$reporte_id || !$producto_id) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

try {
    $db = getDB();
    $db->beginTransaction();

    // Marcar reporte como resuelto
    $stmt = $db->prepare("UPDATE reportes SET estado = 'resuelto' WHERE id = ?");
    $stmt->execute([$reporte_id]);

    // Desactivar producto
    $stmt = $db->prepare("UPDATE productos SET activo = 0 WHERE id = ?");
    $stmt->execute([$producto_id]);

    $db->commit();

    registrarAccionAdmin('resolver_reporte', 'reportes', $reporte_id, "Producto $producto_id desactivado");

    echo json_encode([
        'success' => true,
        'message' => 'Reporte resuelto y producto desactivado'
    ]);
} catch (Exception $e) {
    $db->rollBack();
    error_log("Error en resolver_reporte: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
?>
