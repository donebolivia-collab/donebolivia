<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    $db = getDB();
    
    // Evitar contar múltiples visitas seguidas en la misma sesión
    session_start();
    $viewed_key = 'viewed_product_' . $id;
    
    if (!isset($_SESSION[$viewed_key])) {
        $stmt = $db->prepare("UPDATE productos SET visitas = visitas + 1 WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION[$viewed_key] = true;
        $counted = true;
    } else {
        $counted = false;
    }

    // Obtener contador actual
    $stmt = $db->prepare("SELECT visitas FROM productos WHERE id = ?");
    $stmt->execute([$id]);
    $visitas = $stmt->fetchColumn();

    echo json_encode([
        'success' => true, 
        'visitas' => $visitas,
        'counted' => $counted
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
