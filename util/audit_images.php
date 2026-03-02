<?php
/**
 * Auditoría de Imágenes Huérfanas (Modo Seguro - Solo Lectura)
 * Escanea los directorios de subida y compara con la base de datos.
 */

// SIMULAR ENTORNO HTTP PARA EVITAR ERRORES EN CLI
if (php_sapi_name() === 'cli') {
    $_SERVER['HTTP_HOST'] = 'localhost';
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Aumentar tiempo de ejecución para escaneos largos
set_time_limit(300);

header('Content-Type: text/plain; charset=utf-8');

echo "INICIO DE AUDITORÍA DE IMÁGENES\n";
echo "================================\n";

try {
    $db = getDB();
    
    // 1. Obtener todas las imágenes registradas en la BD (Lista Blanca)
    echo "1. Recopilando referencias en Base de Datos...\n";
    
    $whitelist = [];
    
    // Tabla TIENDAS
    $stmt = $db->query("SELECT logo, logo_principal, banner_imagen, banner_imagen_2, banner_imagen_3, imagen_acerca_1, imagen_acerca_2 FROM tiendas");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        foreach ($row as $img) {
            if (!empty($img)) $whitelist[$img] = true;
        }
    }
    
    // Tabla PRODUCTOS (producto_imagenes)
    $stmt = $db->query("SELECT nombre_archivo FROM producto_imagenes");
    while ($row = $stmt->fetch(PDO::FETCH_COLUMN)) {
        if (!empty($row)) $whitelist[$row] = true;
    }

    echo "   -> Total imágenes referenciadas en BD: " . count($whitelist) . "\n\n";

    // 2. Escanear Directorios (Sistema de Archivos)
    $dirs_to_scan = [
        __DIR__ . '/../uploads/',
        __DIR__ . '/../uploads/logos/'
    ];
    
    $total_files = 0;
    $orphans = [];
    $orphans_size = 0;
    
    echo "2. Escaneando archivos en disco...\n";
    
    foreach ($dirs_to_scan as $dir) {
        if (!is_dir($dir)) {
            echo "   [SKIP] Directorio no existe: $dir\n";
            continue;
        }
        
        echo "   -> Escaneando: " . basename($dir) . "/\n";
        
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $path = $dir . $file;
            if (is_dir($path)) continue; // No entramos recursivamente por ahora
            
            $total_files++;
            
            // Verificar si está en whitelist
            if (!isset($whitelist[$file])) {
                $size = filesize($path);
                $orphans[] = [
                    'path' => $path,
                    'name' => $file,
                    'size' => $size,
                    'date' => date("Y-m-d H:i:s", filemtime($path))
                ];
                $orphans_size += $size;
            }
        }
    }
    
    echo "\nRESUMEN DE AUDITORÍA\n";
    echo "--------------------\n";
    echo "Total Archivos en Disco: $total_files\n";
    echo "Imágenes en Uso (BD):    " . count($whitelist) . "\n";
    echo "Imágenes Huérfanas:      " . count($orphans) . "\n";
    echo "Espacio Recuperable:     " . round($orphans_size / 1024 / 1024, 2) . " MB\n\n";
    
    if (count($orphans) > 0) {
        echo "DETALLE DE HUÉRFANOS (Primeros 50):\n";
        $count = 0;
        foreach ($orphans as $orphan) {
            echo " [DELETE] " . $orphan['name'] . " (" . round($orphan['size']/1024, 1) . " KB) - " . $orphan['date'] . "\n";
            $count++;
            if ($count >= 50) {
                echo " ... y " . (count($orphans) - 50) . " más.\n";
                break;
            }
        }
    } else {
        echo "¡Felicidades! No se encontraron imágenes huérfanas.\n";
    }

} catch (Exception $e) {
    echo "ERROR CRÍTICO: " . $e->getMessage() . "\n";
}
?>