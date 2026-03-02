<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $email = isset($_GET['email']) ? trim($_GET['email']) : '';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Email no válido']);
        exit;
    }

    $db = getDB();
    $stmt = $db->prepare('SELECT 1 FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $exists = (bool) $stmt->fetchColumn();

    echo json_encode(['success' => true, 'exists' => $exists]);
} catch (Exception $e) {
    error_log('check_email_exists error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error del sistema']);
}
