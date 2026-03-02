<?php
/**
 * API: Eliminar Logo de Tienda
 */
session_start();
header('Content-Type: application/json');

// Includes básicos
$base_path = dirname(__DIR__);
require_once $base_path . '/config/database.php';
require_once $base_path . '/includes/functions.php';

// Verificar Auth
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    $db = getDB();
    $usuario_id = $_SESSION['usuario_id'];

    // Actualizar campo logo a NULL en tabla TIENDAS
    $stmt = $db->prepare("UPDATE tiendas SET logo = NULL WHERE usuario_id = ?");
    $stmt->execute([$usuario_id]);

    // REFACTORIZACIÓN: Ya no es necesario actualizar feria_posiciones.
    // La sincronización se maneja automáticamente vía JOIN en las consultas de lectura.
    // $stmtFeria = $db->prepare("UPDATE feria_posiciones SET tienda_logo = NULL WHERE usuario_id = ?");
    // $stmtFeria->execute([$usuario_id]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>