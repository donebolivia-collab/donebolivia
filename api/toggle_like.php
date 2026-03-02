<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

// Obtener input JSON
$input = json_decode(file_get_contents('php://input'), true);
$id = isset($input['id']) ? intval($input['id']) : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

$ip_address = $_SERVER['REMOTE_ADDR'];
$user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

try {
    $db = getDB();
    
    // Verificar si ya dio like
    $stmt = $db->prepare("SELECT id FROM producto_likes WHERE producto_id = ? AND ip_address = ?");
    $stmt->execute([$id, $ip_address]);
    $existing = $stmt->fetch();

    if ($existing) {
        // QUITAR LIKE
        $db->beginTransaction();
        
        $stmt = $db->prepare("DELETE FROM producto_likes WHERE id = ?");
        $stmt->execute([$existing['id']]);
        
        $stmt = $db->prepare("UPDATE productos SET likes = GREATEST(likes - 1, 0) WHERE id = ?");
        $stmt->execute([$id]);
        
        $db->commit();
        $liked = false;
    } else {
        // DAR LIKE
        $db->beginTransaction();
        
        $stmt = $db->prepare("INSERT INTO producto_likes (producto_id, ip_address, user_agent) VALUES (?, ?, ?)");
        $stmt->execute([$id, $ip_address, $user_agent]);
        
        $stmt = $db->prepare("UPDATE productos SET likes = likes + 1 WHERE id = ?");
        $stmt->execute([$id]);
        
        $db->commit();
        $liked = true;
    }

    // Obtener nuevo contador
    $stmt = $db->prepare("SELECT likes FROM productos WHERE id = ?");
    $stmt->execute([$id]);
    $likes = $stmt->fetchColumn();

    echo json_encode([
        'success' => true, 
        'likes' => $likes,
        'liked' => $liked
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
