<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../admin_functions.php';
require_once '../../includes/auth.php'; // Para CSRF

// Verificar sesión de admin y CSRF
if (session_status() === PHP_SESSION_NONE) session_start();
requiereAdmin();

// Obtener datos
$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;
$estado = $data['estado'] ?? null;
$token = $data['csrf_token'] ?? '';
$suspension_dias = $data['suspension_dias'] ?? null; // Nuevo campo

// ... (validaciones CSRF e ID igual)

try {
    $db = getDB();
    
    $fecha_fin = null;
    if ($estado === 'suspendido' && $suspension_dias && is_numeric($suspension_dias)) {
        // Calcular fecha futura
        $fecha_fin = date('Y-m-d H:i:s', strtotime("+$suspension_dias days"));
    } else if ($estado === 'activo') {
        // Si reactivamos, limpiamos la suspensión
        $fecha_fin = null; 
    }

    // Actualizar estado y fecha de fin
    $stmt = $db->prepare("UPDATE tiendas SET estado = ?, suspension_fin = ? WHERE id = ?");
    $stmt->execute([$estado, $fecha_fin, $id]);
    
    // Si la columna no existía, el SQL fallará silenciosamente o lanzará error dependiendo del modo.
    // Para robustez, asumiremos que ya ejecutaste el setup_bans.php
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    // Si falla por columna desconocida, intentamos update simple para compatibilidad
    try {
        $db->prepare("UPDATE tiendas SET estado = ? WHERE id = ?")->execute([$estado, $id]);
        echo json_encode(['success' => true, 'message' => 'Actualizado (sin fecha automática por falta de columna)']);
    } catch (Exception $e2) {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar']);
    }
}
?>