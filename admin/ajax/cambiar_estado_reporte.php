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
$id = intval($data['id'] ?? 0);
$estado = $data['estado'] ?? '';
$token = $data['csrf_token'] ?? '';

// Verificar CSRF
if (!auth_csrf_check($token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Error de seguridad CSRF']);
    exit;
}

if (!$id || !in_array($estado, ['pendiente', 'revisado', 'resuelto', 'rechazado'])) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

try {
    $db = getDB();
    $admin_id = $_SESSION['usuario_id'];

    $stmt = $db->prepare("
        UPDATE denuncias
        SET estado = ?,
            admin_id = ?,
            fecha_revision = NOW()
        WHERE id = ?
    ");
    $resultado = $stmt->execute([$estado, $admin_id, $id]);

    if ($resultado) {
        echo json_encode([
            'success' => true,
            'message' => 'Estado actualizado correctamente'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar estado']);
    }
} catch (Exception $e) {
    error_log("Error en cambiar_estado_reporte: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
?>
