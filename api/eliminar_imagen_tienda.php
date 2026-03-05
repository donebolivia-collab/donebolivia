<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar sesión
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión']);
    exit;
}

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Obtener datos
$data = json_decode(file_get_contents('php://input'), true);
$tipo = $data['tipo'] ?? '';

$allowed_types = ['banner', 'banner_2', 'banner_3', 'banner_4', 'logo_principal', 'logo'];
if (!in_array($tipo, $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Tipo de imagen no válido']);
    exit;
}

// Mapear tipo a columna de BD
$column_map = [
    'banner' => 'banner_imagen',
    'banner_2' => 'banner_imagen_2',
    'banner_3' => 'banner_imagen_3',
    'banner_4' => 'banner_imagen_4',
    'logo_principal' => 'logo_principal',
    'logo' => 'logo' // Corregido: Agregado mapeo faltante
];
$columna_bd = $column_map[$tipo];

try {
    $db = getDB();
    $usuario_id = $_SESSION['usuario_id'];
    
    // Verificar propiedad
    $stmt = $db->prepare("SELECT id FROM tiendas WHERE usuario_id = ?");
    $stmt->execute([$usuario_id]);
    $tienda = $stmt->fetch();
    
    if (!$tienda) {
        throw new Exception("Tienda no encontrada");
    }

    // Si es logo_principal o banner o logo, primero borramos el archivo físico
    // CAMBIO AUDITORÍA: Ahora TODOS los archivos son únicos, así que siempre debemos buscarlo y borrarlo.
    $stmtFile = $db->prepare("SELECT $columna_bd FROM tiendas WHERE id = ?");
    $stmtFile->execute([$tienda['id']]);
    $current_file = $stmtFile->fetchColumn();

    if ($current_file) {
        $subfolder = ($tipo === 'logo' || $tipo === 'logo_principal') ? 'logos/' : '';
        $file_path = __DIR__ . '/../uploads/' . $subfolder . $current_file;
        
        if (file_exists($file_path)) {
            unlink($file_path); // Borrar archivo físico
        }
    }
    
    // Actualizar BD a NULL (No borramos el archivo físico por seguridad/caché, o podríamos renombrarlo)
    // Para simplificar y asegurar que se "borra", lo seteamos a NULL.
    $stmtUpdate = $db->prepare("UPDATE tiendas SET $columna_bd = NULL WHERE id = ?");
    $stmtUpdate->execute([$tienda['id']]);
    
    echo json_encode(['success' => true, 'message' => 'Imagen eliminada']);

} catch (Exception $e) {
    error_log("Error eliminando imagen: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
?>