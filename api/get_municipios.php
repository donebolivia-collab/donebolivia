<?php
require_once __DIR__ . '/../config/ubicaciones_bolivia.php';

header('Content-Type: application/json; charset=utf-8');

$departamento = $_GET['departamento'] ?? '';

if (empty($departamento)) {
    echo json_encode([
        'success' => false,
        'message' => 'Departamento no especificado',
        'data' => []
    ]);
    exit;
}

try {
    $municipios = obtenerMunicipiosDeDepartamento($departamento);
    
    echo json_encode([
        'success' => true,
        'data' => $municipios
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener municipios',
        'data' => []
    ]);
}