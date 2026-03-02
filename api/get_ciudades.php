<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$departamento = $_GET['departamento'] ?? '';

if (empty($departamento)) {
    echo json_encode([]);
    exit;
}

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, nombre FROM ciudades WHERE departamento = ? ORDER BY nombre");
    $stmt->execute([$departamento]);
    $ciudades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($ciudades);
} catch (Exception $e) {
    echo json_encode([]);
}