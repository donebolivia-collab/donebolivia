<?php
require_once '../includes/config/database.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$telefono = $_GET['telefono'] ?? '';
// Normalizar lo que recibimos (solo números)
$telefonoLimpio = preg_replace('/[^0-9]/', '', $telefono);

if (empty($telefonoLimpio)) {
    echo json_encode(['exists' => false]);
    exit;
}

try {
    $db = getDB();
    // Buscamos comparando el teléfono limpio con el teléfono de la BD (limpiando espacios y guiones en la BD también)
    // Esto asegura que "75872712" coincida con "758-727-12" o "758 727 12" en la base de datos
    $stmt = $db->prepare("SELECT id FROM usuarios WHERE REPLACE(REPLACE(telefono, ' ', ''), '-', '') = ?");
    $stmt->execute([$telefonoLimpio]);
    
    $exists = (bool)$stmt->fetch();
    
    echo json_encode(['exists' => $exists, 'debug_received' => $telefonoLimpio]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Error de servidor', 'msg' => $e->getMessage()]);
}