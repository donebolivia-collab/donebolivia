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
$producto_id = intval($data['producto_id'] ?? 0);
$reporte_id = intval($data['reporte_id'] ?? 0);
$token = $data['csrf_token'] ?? '';

// Verificar CSRF
if (!auth_csrf_check($token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Error de seguridad CSRF']);
    exit;
}

if (!$producto_id || !$reporte_id) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

try {
    $db = getDB();
    $admin_id = $_SESSION['usuario_id'];

    // Desactivar producto
    $stmt = $db->prepare("UPDATE productos SET activo = 0 WHERE id = ?");
    $stmt->execute([$producto_id]);

    // Actualizar reporte a resuelto
    $stmt = $db->prepare("
        UPDATE denuncias
        SET estado = 'resuelto',
            admin_id = ?,
            fecha_revision = NOW(),
            admin_notas = 'Producto desactivado por reporte'
        WHERE id = ?
    ");
    $stmt->execute([$admin_id, $reporte_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Producto desactivado y reporte marcado como resuelto'
    ]);

} catch (Exception $e) {
    error_log("Error en desactivar_producto_reportado: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
?>
