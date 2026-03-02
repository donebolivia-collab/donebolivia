<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar que el usuario esté logueado
iniciarSesion();
if (!estaLogueado()) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión']);
    exit;
}

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Verificar que se subió un archivo
if (!isset($_FILES['foto_perfil']) || $_FILES['foto_perfil']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No se recibió ninguna imagen']);
    exit;
}

$archivo = $_FILES['foto_perfil'];

// Validar tipo de archivo
$tipos_permitidos = ['image/jpeg', 'image/jpg', 'image/png'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $archivo['tmp_name']);
finfo_close($finfo);

if (!in_array($mime_type, $tipos_permitidos)) {
    echo json_encode(['success' => false, 'message' => 'Solo se permiten imágenes JPG y PNG']);
    exit;
}

// Validar tamaño (máximo 2MB)
$max_size = 2 * 1024 * 1024; // 2MB en bytes
if ($archivo['size'] > $max_size) {
    echo json_encode(['success' => false, 'message' => 'La imagen no debe superar 2MB']);
    exit;
}

try {
    $db = getDB();
    $usuario_id = $_SESSION['usuario_id'];
    
    // Obtener foto anterior para eliminarla
    $stmt = $db->prepare("SELECT foto_perfil FROM usuarios WHERE id = ?");
    $stmt->execute([$usuario_id]);
    $usuario = $stmt->fetch();
    $foto_anterior = $usuario['foto_perfil'] ?? null;
    
    // Crear directorio si no existe
    $upload_dir = __DIR__ . '/../uploads/perfiles/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generar nombre único para el archivo
    $extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
    $nombre_archivo = 'perfil_' . $usuario_id . '_' . time() . '.' . $extension;
    $ruta_completa = $upload_dir . $nombre_archivo;
    
    // Mover archivo subido
    if (!move_uploaded_file($archivo['tmp_name'], $ruta_completa)) {
        echo json_encode(['success' => false, 'message' => 'Error al guardar la imagen']);
        exit;
    }
    
    // Redimensionar imagen para optimizar
    redimensionarImagen($ruta_completa, 300, 300);
    
    // Actualizar base de datos
    $stmt = $db->prepare("UPDATE usuarios SET foto_perfil = ? WHERE id = ?");
    $stmt->execute([$nombre_archivo, $usuario_id]);
    // Reflejar inmediatamente en la sesión para que el header y vistas lo muestren aunque la DB no tenga la columna
    $_SESSION['foto_perfil'] = $nombre_archivo;
    
    // Eliminar foto anterior si existe
    if ($foto_anterior && file_exists($upload_dir . $foto_anterior)) {
        unlink($upload_dir . $foto_anterior);
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Foto de perfil actualizada',
        'foto_url' => '/uploads/perfiles/' . $nombre_archivo
    ]);
    
} catch (Exception $e) {
    error_log("Error en upload_profile_photo: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error del sistema']);
}

/**
 * Redimensionar imagen manteniendo proporción
 */
function redimensionarImagen($ruta, $ancho_max, $alto_max) {
    $info = getimagesize($ruta);
    $mime = $info['mime'];
    
    switch ($mime) {
        case 'image/jpeg':
            $imagen = imagecreatefromjpeg($ruta);
            break;
        case 'image/png':
            $imagen = imagecreatefrompng($ruta);
            break;
        default:
            return false;
    }
    
    $ancho_original = imagesx($imagen);
    $alto_original = imagesy($imagen);
    
    // Calcular nuevas dimensiones manteniendo proporción
    $ratio = min($ancho_max / $ancho_original, $alto_max / $alto_original);
    $nuevo_ancho = round($ancho_original * $ratio);
    $nuevo_alto = round($alto_original * $ratio);
    
    // Crear imagen redimensionada
    $imagen_nueva = imagecreatetruecolor($nuevo_ancho, $nuevo_alto);
    
    // Preservar transparencia para PNG
    if ($mime == 'image/png') {
        imagealphablending($imagen_nueva, false);
        imagesavealpha($imagen_nueva, true);
    }
    
    imagecopyresampled($imagen_nueva, $imagen, 0, 0, 0, 0, $nuevo_ancho, $nuevo_alto, $ancho_original, $alto_original);
    
    // Guardar imagen
    switch ($mime) {
        case 'image/jpeg':
            imagejpeg($imagen_nueva, $ruta, 85);
            break;
        case 'image/png':
            imagepng($imagen_nueva, $ruta, 8);
            break;
    }
    
    imagedestroy($imagen);
    imagedestroy($imagen_nueva);
    
    return true;
}