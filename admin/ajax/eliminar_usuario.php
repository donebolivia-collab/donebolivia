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
$token = $data['csrf_token'] ?? '';

// Verificar CSRF
if (!auth_csrf_check($token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Error de seguridad CSRF']);
    exit;
}

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    $resultado = eliminarUsuario($id);

    if ($resultado) {
        echo json_encode([
            'success' => true,
            'message' => 'Usuario eliminado correctamente'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al eliminar usuario']);
    }
} catch (Exception $e) {
    error_log("Error en eliminar_usuario: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
?>
