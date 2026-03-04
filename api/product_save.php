<?php
/**
 * Product Save API - Endpoint Unificado y Atómico
 * Reemplaza a crear_producto_completo.php y editar_producto_completo.php
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/ubicaciones_bolivia.php';
require_once __DIR__ . '/services/ProductService.php';
require_once __DIR__ . '/services/ImageService.php';

// Verificar autenticación
if (!estaLogueado()) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

try {
    // Obtener y normalizar datos de entrada
    $data = $_POST;
    
    // Procesar archivos
    $files = [];
    if (!empty($_FILES['imagenes'])) {
        $files = $_FILES['imagenes'];
    } elseif (!empty($_FILES['imagenes_nuevas'])) {
        $files = $_FILES['imagenes_nuevas'];
    }
    
    // Validar que tengamos datos básicos
    if (empty($data)) {
        throw new Exception('No se recibieron datos del formulario');
    }
    
    // Normalizar estado
    if (!empty($data['estado'])) {
        $data['estado'] = normalizarEstadoProducto($data['estado']);
    }
    
    // Obtener conexión a BD
    $db = getDB();
    $userId = $_SESSION['usuario_id'];
    
    // Crear servicios
    $productService = new ProductService($db);
    
    // Ejecutar guardado atómico
    $result = $productService->saveProduct($data, $files, $userId);
    
    // Devolver respuesta
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Product Save API Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]
    ]);
}

/**
 * Normaliza el estado del producto
 */
function normalizarEstadoProducto($estado) {
    $mapa = [
        'nuevo' => 'Nuevo',
        'como nuevo' => 'Como Nuevo',
        'buen estado' => 'Buen Estado',
        'aceptable' => 'Aceptable',
        'como_nuevo' => 'Como Nuevo',
        'buen_estado' => 'Buen Estado'
    ];
    
    $key = strtolower(trim($estado));
    return $mapa[$key] ?? $estado;
}
?>
