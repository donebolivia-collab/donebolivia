<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../admin_functions.php';
require_once '../../includes/auth.php';

// Verificar sesión de admin
if (session_status() === PHP_SESSION_NONE) session_start();
requiereAdmin();

// Obtener datos
$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;
$estado = $data['estado'] ?? null;
$token = $data['csrf_token'] ?? '';
// Opcional: acción adicional (ej: suspender_tienda)
$accion_adicional = $data['accion_adicional'] ?? null;

// Validar CSRF
if (!auth_csrf_check($token)) {
    echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido']);
    exit;
}

if (!$id || !$estado) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

// Estados válidos
$estados_validos = ['pendiente', 'revisado', 'sancionado', 'descartado'];
if (!in_array($estado, $estados_validos)) {
    echo json_encode(['success' => false, 'message' => 'Estado inválido']);
    exit;
}

try {
    $db = getDB();
    $db->beginTransaction();

    // 1. Actualizar estado del reporte
    $stmt = $db->prepare("UPDATE denuncias_tiendas SET estado = ? WHERE id = ?");
    $stmt->execute([$estado, $id]);

    // 2. Ejecutar acciones automáticas de "Mano Dura"
    $mensaje_extra = "";
    
    if ($estado === 'sancionado' && $accion_adicional === 'suspender_tienda') {
        // Obtener ID de la tienda asociada al reporte
        $stmt_tienda = $db->prepare("SELECT tienda_id FROM denuncias_tiendas WHERE id = ?");
        $stmt_tienda->execute([$id]);
        $tienda_id = $stmt_tienda->fetchColumn();

        if ($tienda_id) {
            // SUSPENDER LA TIENDA AUTOMÁTICAMENTE
            $stmt_suspend = $db->prepare("UPDATE tiendas SET estado = 'suspendido' WHERE id = ?");
            $stmt_suspend->execute([$tienda_id]);
            
            // Opcional: Desactivar todos sus productos también (Tierra quemada)
            // $db->prepare("UPDATE productos SET activo = 0 WHERE usuario_id = (SELECT usuario_id FROM tiendas WHERE id = ?)")->execute([$tienda_id]);
            
            registrarAccionAdmin('suspender_tienda_por_denuncia', 'tiendas', $tienda_id, "Reporte ID: $id");
            $mensaje_extra = " y la tienda ha sido SUSPENDIDA";
        }
    }

    $db->commit();
    
    echo json_encode(['success' => true, 'message' => 'Reporte actualizado' . $mensaje_extra]);
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error al actualizar: ' . $e->getMessage()]);
}
?>