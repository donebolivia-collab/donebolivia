<?php
/**
 * LIMPIEZA DE EMERGENCIA - Eliminar todas las imágenes de productos huérfanas
 * 
 * ESTE SCRIPT ES PARA SITUACIONES CRÍTICAS donde hay basura acumulada
 * y no hay productos reales que proteger.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

echo "🚨 LIMPIEZA DE EMERGENCIA - IMÁGENES DE PRODUCTOS\n";
echo "⚠️  ESTE SCRIPT ELIMINARÁ TODAS LAS IMÁGENES HUÉRFANAS\n";
echo str_repeat("=", 60) . "\n\n";

try {
    $db = getDB();
    $uploadPath = UPLOAD_PATH . 'productos/';
    
    // 1. Obtener todas las imágenes en la BD
    echo "📋 Obteniendo imágenes referenciadas en BD...\n";
    $stmt = $db->query("SELECT nombre_archivo FROM producto_imagenes");
    $imagenes_bd = [];
    while ($row = $stmt->fetch()) {
        $imagenes_bd[] = $row['nombre_archivo'];
    }
    echo "✅ Imágenes en BD: " . count($imagenes_bd) . "\n\n";
    
    // 2. Escanear archivos físicos
    echo "📁 Escaneando archivos físicos...\n";
    $archivos_fisicos = glob($uploadPath . '*.{jpg,jpeg,png,webp}', GLOB_BRACE);
    echo "✅ Archivos físicos: " . count($archivos_fisicos) . "\n\n";
    
    // 3. Identificar huérfanos
    echo "👻 Identificando imágenes huérfanas...\n";
    $huérfanos = [];
    foreach ($archivos_fisicos as $archivo) {
        $nombre = basename($archivo);
        $ruta_relativa = 'productos/' . $nombre;
        
        if (!in_array($ruta_relativa, $imagenes_bd)) {
            $huérfanos[] = [
                'archivo' => $nombre,
                'ruta' => $archivo,
                'tamaño' => filesize($archivo)
            ];
        }
    }
    
    echo "🎯 Imágenes huérfanas encontradas: " . count($huérfanos) . "\n\n";
    
    // 4. Mostrar detalles
    if (!empty($huérfanos)) {
        $total_size = 0;
        echo "📋 DETALLE DE ARCHIVOS HUÉRFANOS:\n";
        echo str_repeat("-", 60) . "\n";
        
        foreach ($huérfanos as $huérfano) {
            $size_kb = round($huérfano['tamaño'] / 1024, 2);
            echo "• {$huérfano['archivo']} ({$size_kb} KB)\n";
            $total_size += $huérfano['tamaño'];
        }
        
        echo str_repeat("-", 60) . "\n";
        echo "📊 Espacio total a liberar: " . round($total_size / 1024 / 1024, 2) . " MB\n\n";
        
        // 5. Confirmación y eliminación
        echo "⚠️  ¿DESEAS ELIMINAR ESTOS ARCHIVOS? (s/N): ";
        $handle = fopen("php://stdin", "r");
        $respuesta = trim(fgets($handle));
        fclose($handle);
        
        if (strtolower($respuesta) === 's') {
            echo "\n🗑️  ELIMINANDO ARCHIVOS...\n";
            $eliminados = 0;
            $errores = 0;
            
            foreach ($huérfanos as $huérfano) {
                if (unlink($huérfano['ruta'])) {
                    $eliminados++;
                    echo "✅ Eliminado: {$huérfano['archivo']}\n";
                } else {
                    $errores++;
                    echo "❌ Error eliminando: {$huérfano['archivo']}\n";
                }
            }
            
            echo "\n📊 RESUMEN FINAL:\n";
            echo "• Eliminados: $eliminados\n";
            echo "• Errores: $errores\n";
            echo "• Espacio liberado: " . round($total_size / 1024 / 1024, 2) . " MB\n";
            
        } else {
            echo "\n❌ OPERACIÓN CANCELADA\n";
        }
        
    } else {
        echo "✅ NO HAY IMÁGENES HUÉRFANAS\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🎯 LIMPIEZA COMPLETADA\n";
?>
