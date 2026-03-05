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

// Verificar archivo y tipo
if (!isset($_FILES['imagen']) || $_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No se recibió ninguna imagen válida']);
    exit;
}
if (!isset($_POST['tipo'])) {
    echo json_encode(['success' => false, 'message' => 'Tipo de imagen no especificado']);
    exit;
}

$tipo = $_POST['tipo'];
$allowed_types = ['logo', 'logo_principal', 'acerca_1', 'acerca_2', 'banner', 'banner_2', 'banner_3', 'banner_4'];
if (!in_array($tipo, $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Tipo de imagen no válido']);
    exit;
}

// Mapear tipo a columna de BD
$column_map = [
    'acerca_1' => 'imagen_acerca_1',
    'acerca_2' => 'imagen_acerca_2',
    'banner' => 'banner_imagen',
    'banner_2' => 'banner_imagen_2',
    'banner_3' => 'banner_imagen_3',
    'banner_4' => 'banner_imagen_4',
    'logo_principal' => 'logo_principal'
];
$columna_bd = $column_map[$tipo];

$archivo = $_FILES['imagen'];

// Validar MIME
$tipos_permitidos = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $archivo['tmp_name']);
finfo_close($finfo);

if (!in_array($mime_type, $tipos_permitidos)) {
    echo json_encode(['success' => false, 'message' => 'Solo se permiten imágenes JPG, PNG o WebP']);
    exit;
}

// Validar tamaño (5MB max para tiendas)
$max_size = 5 * 1024 * 1024; 
if ($archivo['size'] > $max_size) {
    echo json_encode(['success' => false, 'message' => 'La imagen no debe superar 5MB']);
    exit;
}

try {
    $db = getDB();
    $usuario_id = $_SESSION['usuario_id'];
    
    // Verificar propiedad de la tienda
    $stmt = $db->prepare("SELECT id, $columna_bd FROM tiendas WHERE usuario_id = ?");
    $stmt->execute([$usuario_id]);
    $tienda = $stmt->fetch();
    
    if (!$tienda) {
        throw new Exception("No tienes una tienda registrada");
    }
    
    $tienda_id = $tienda['id'];
    
    // Directorio de uploads base
    $base_upload_dir = __DIR__ . '/../uploads/';
    
    // Subcarpeta específica según tipo
    $subfolder = '';
    if ($tipo === 'logo' || $tipo === 'logo_principal') {
        $subfolder = 'logos/';
    }
    
    $target_dir = $base_upload_dir . $subfolder;
    
    // Crear directorios si no existen
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    // ---------------------------------------------------------
    // ARQUITECTURA DETERMINISTA (SOLUCIÓN DEFINITIVA)
    // ---------------------------------------------------------
    // Nombramos el archivo SIEMPRE igual para este ID.
    // Esto evita acumular basura y hace predecible la URL.
    // Usaremos WEBP para TODO (Ahorro de espacio y soporte transparencia)
    
    $extension = 'webp';

    $prefix = match($tipo) {
        'logo' => 'logo_tienda_',
        'acerca_1' => 'about1_tienda_',
        'acerca_2' => 'about2_tienda_',
        'banner' => 'banner_tienda_',
        'banner_2' => 'banner2_tienda_',
        'banner_3' => 'banner3_tienda_',
        'banner_4' => 'banner4_tienda_',
        default => 'img_tienda_'
    };

    if ($tipo === 'logo_principal') {
        // Obtenemos el ID de la tienda y generamos un nombre único
        // IMPORTANTE: NO usamos un nombre fijo para evitar problemas de caché agresivo en navegadores
        $nombre_archivo = 'logo_principal_' . $tienda_id . '_' . uniqid() . '.webp';
    } else {
        // Para otros tipos, usamos un nombre determinista pero con versionado query string en el frontend
        // Esto mantiene el servidor limpio.
        // ADVERTENCIA: Si el navegador cachea muy fuerte, el usuario no verá el cambio.
        // SOLUCIÓN: Cambiar a nombre único también para Banners y Logo Feria si persiste el problema.
        $prefix = match($tipo) {
            'logo' => 'logo_tienda_',
            'acerca_1' => 'about1_tienda_',
            'acerca_2' => 'about2_tienda_',
            'banner' => 'banner_tienda_',
            'banner_2' => 'banner2_tienda_',
            'banner_3' => 'banner3_tienda_',
            'banner_4' => 'banner4_tienda_',
            default => 'img_tienda_'
        };
        // CAMBIO AUDITORÍA: Usar uniqid() también aquí para forzar refresco real
        $nombre_archivo = $prefix . $tienda_id . '_' . uniqid() . '.' . $extension;
        
        // BORRAR ANTERIOR: Como ahora el nombre cambia, debemos borrar el viejo para no llenar el disco
        // Buscar archivos que coincidan con el patrón del prefijo y ID de tienda
        $files = glob($target_dir . $prefix . $tienda_id . '_*.' . $extension);
        foreach($files as $file){
            if(is_file($file)) unlink($file);
        }
    }
    $ruta_completa = $target_dir . $nombre_archivo;
    
    // PROCESAMIENTO DE IMAGEN (Gd Library)
    // Redimensionar y Convertir formato
    if ($tipo === 'logo_principal') {
        // Usamos la nueva función flexible
        procesarImagenFlexible($archivo['tmp_name'], $ruta_completa, 1024, 1024); // Máximo 1024x1024, sin recortar
    } else if ($tipo === 'logo') {
        // Logos siempre 500x500 WEBP
        procesarImagenEstandar($archivo['tmp_name'], $ruta_completa, 500, 500, 'webp');
    } elseif (strpos($tipo, 'banner') !== false) {
        // Banners (1, 2, 3) grandes: 1920x600 (Formato panorámico comercial)
        procesarImagenEstandar($archivo['tmp_name'], $ruta_completa, 1920, 600, 'webp');
    } else {
        // Otras fotos 1200x1200 WEBP
        procesarImagenEstandar($archivo['tmp_name'], $ruta_completa, 1200, 1200, 'webp');
    }
    
    // FORZAR LIMPIEZA DE CACHÉ DEL NAVEGADOR
    // Al devolver el nombre, le agregamos un parámetro ficticio
    // para que el frontend actualice la imagen inmediatamente.
    $nombre_para_frontend = $nombre_archivo . '?v=' . time();

    // Actualizar BD
    // Guardamos SOLO el nombre base, sin el query string
    $stmt = $db->prepare("UPDATE tiendas SET $columna_bd = ? WHERE id = ?");
    $stmt->execute([$nombre_archivo, $tienda_id]);
    
    // También actualizamos en feria_posiciones si es un logo
    if ($tipo === 'logo') {
        // REFACTORIZACIÓN: Ya no es necesario actualizar feria_posiciones.
        // La tabla feria_posiciones ahora se une con JOIN a la tabla tiendas en tiempo de lectura.
        // Esto elimina la duplicidad de datos y garantiza consistencia total.
        
        // $stmtFeria = $db->prepare("UPDATE feria_posiciones SET tienda_logo = ? WHERE usuario_id = ?");
        // $stmtFeria->execute([$nombre_archivo, $usuario_id]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Imagen actualizada correctamente',
        'filename' => $nombre_para_frontend // Frontend usará esto para refrescar <img> src
    ]);

} catch (Exception $e) {
    error_log("Error subida tienda: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Función Robustecida de Procesamiento
function procesarImagenEstandar($origen, $destino, $ancho_max, $alto_max, $formato_salida) {
    $info = @getimagesize($origen);
    if (!$info) throw new Exception("Archivo de imagen corrupto o no válido");
    
    $mime = $info['mime'];
    $ancho_original = $info[0];
    $alto_original = $info[1];
    
    switch ($mime) {
        case 'image/jpeg': $imagen = imagecreatefromjpeg($origen); break;
        case 'image/png': $imagen = imagecreatefrompng($origen); break;
        case 'image/webp': $imagen = imagecreatefromwebp($origen); break;
        default: throw new Exception("Formato no soportado para conversión");
    }
    
    // Calcular nuevas dimensiones manteniendo aspecto
    $ratio = min($ancho_max / $ancho_original, $alto_max / $alto_original);
    // Si es más pequeña que el máximo, no estirar (mantener original o escalar solo si es muy grande)
    if ($ratio > 1) $ratio = 1; 
    
    $nuevo_ancho = round($ancho_original * $ratio);
    $nuevo_alto = round($alto_original * $ratio);
    
    $imagen_nueva = imagecreatetruecolor($nuevo_ancho, $nuevo_alto);
    
    // Manejo de transparencia
    if ($formato_salida == 'png' || $formato_salida == 'webp') {
        imagealphablending($imagen_nueva, false);
        imagesavealpha($imagen_nueva, true);
        $transparent = imagecolorallocatealpha($imagen_nueva, 255, 255, 255, 127);
        imagefilledrectangle($imagen_nueva, 0, 0, $nuevo_ancho, $nuevo_alto, $transparent);
    } else {
        // Para JPG rellenar fondo blanco
        $blanco = imagecolorallocate($imagen_nueva, 255, 255, 255);
        imagefilledrectangle($imagen_nueva, 0, 0, $nuevo_ancho, $nuevo_alto, $blanco);
    }
    
    imagecopyresampled($imagen_nueva, $imagen, 0, 0, 0, 0, $nuevo_ancho, $nuevo_alto, $ancho_original, $alto_original);
    
    // Guardar
    $resultado = false;
    switch ($formato_salida) {
        case 'jpg': $resultado = imagejpeg($imagen_nueva, $destino, 90); break;
        case 'png': $resultado = imagepng($imagen_nueva, $destino, 8); break; // Nivel compresión 0-9
        case 'webp': $resultado = imagewebp($imagen_nueva, $destino, 90); break;
    }
    
    imagedestroy($imagen);
    imagedestroy($imagen_nueva);
    
    if (!$resultado) throw new Exception("Error al guardar la imagen procesada");
    return true;
}

// Nueva función para logos flexibles
function procesarImagenFlexible($origen, $destino, $ancho_max, $alto_max) {
    $info = @getimagesize($origen);
    if (!$info) throw new Exception("Archivo de imagen corrupto o no válido");
    
    $mime = $info['mime'];
    $ancho_original = $info[0];
    $alto_original = $info[1];
    
    switch ($mime) {
        case 'image/jpeg': $imagen = imagecreatefromjpeg($origen); break;
        case 'image/png': $imagen = imagecreatefrompng($origen); break;
        case 'image/webp': $imagen = imagecreatefromwebp($origen); break;
        default: throw new Exception("Formato no soportado para conversión");
    }
    
    $ratio = min($ancho_max / $ancho_original, $alto_max / $alto_original);
    if ($ratio >= 1) { // Si la imagen es más pequeña que el máximo, no la agrandamos
        $nuevo_ancho = $ancho_original;
        $nuevo_alto = $alto_original;
    } else { // Si es más grande, la reducimos proporcionalmente
        $nuevo_ancho = round($ancho_original * $ratio);
        $nuevo_alto = round($alto_original * $ratio);
    }
    
    $imagen_nueva = imagecreatetruecolor($nuevo_ancho, $nuevo_alto);
    
    // Manejo de transparencia para PNG y WEBP
    imagealphablending($imagen_nueva, false);
    imagesavealpha($imagen_nueva, true);
    $transparent = imagecolorallocatealpha($imagen_nueva, 255, 255, 255, 127);
    imagefilledrectangle($imagen_nueva, 0, 0, $nuevo_ancho, $nuevo_alto, $transparent);
    
    imagecopyresampled($imagen_nueva, $imagen, 0, 0, 0, 0, $nuevo_ancho, $nuevo_alto, $ancho_original, $alto_original);
    
    $resultado = imagewebp($imagen_nueva, $destino, 85); // Calidad 85
    
    imagedestroy($imagen);
    imagedestroy($imagen_nueva);
    
    if (!$resultado) throw new Exception("Error al guardar la imagen procesada como WEBP");
    return true;
}
?>