<?php
/**
 * API: Toggle Producto Activo/Inactivo
 * Permite ocultar o mostrar un producto de la tienda
 */

// 1. Configuración
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// 2. Auth Check
session_start();
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// 3. Validar Input
$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['id']) || !isset($input['activo'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$id = intval($input['id']);
$activo = intval($input['activo']); // 0 o 1

try {
    $db = getDB();
    
    // 4. Verificar propiedad (Seguridad)
    $stmtCheck = $db->prepare("SELECT id FROM productos WHERE id = ? AND usuario_id = ?");
    $stmtCheck->execute([$id, $_SESSION['usuario_id']]);
    if (!$stmtCheck->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Producto no encontrado o no te pertenece']);
        exit;
    }

    // 5. Actualizar
    $stmt = $db->prepare("UPDATE productos SET activo = ? WHERE id = ?");
    $stmt->execute([$activo, $id]);

    echo json_encode(['success' => true, 'nuevo_estado' => $activo]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}
