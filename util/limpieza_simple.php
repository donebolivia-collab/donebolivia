<?php
/**
 * LIMPIEZA SIMPLE - Eliminar todas las imágenes de productos sin conexión BD
 * 
 * Para emergencias cuando no hay conexión a BD pero necesitas limpiar
 */

echo "🧹 LIMPIEZA SIMPLE - CARPETA PRODUCTOS\n";
echo "⚠️  ESTE SCRIPT ELIMINARÁ TODOS LOS ARCHIVOS EN uploads/productos/\n";
echo str_repeat("=", 60) . "\n\n";

$uploadPath = __DIR__ . '/../uploads/productos/';

// Verificar que existe el directorio
if (!is_dir($uploadPath)) {
    echo "❌ Directorio no encontrado: $uploadPath\n";
    exit(1);
}

// Escanear archivos
$archivos = glob($uploadPath . '*.{jpg,jpeg,png,webp}', GLOB_BRACE);

if (empty($archivos)) {
    echo "✅ No hay archivos para eliminar\n";
    exit(0);
}

echo "📁 Archivos encontrados: " . count($archivos) . "\n\n";

// Calcular tamaño total
$total_size = 0;
foreach ($archivos as $archivo) {
    $total_size += filesize($archivo);
}

echo "💾 Espacio total: " . round($total_size / 1024 / 1024, 2) . " MB\n\n";

// Mostrar primeros 10 archivos como muestra
echo "📋 MUESTRA DE ARCHIVOS:\n";
echo str_repeat("-", 40) . "\n";
$mostrados = 0;
foreach ($archivos as $archivo) {
    if ($mostrados >= 10) break;
    $nombre = basename($archivo);
    $size_kb = round(filesize($archivo) / 1024, 2);
    echo "• $nombre ($size_kb KB)\n";
    $mostrados++;
}
if (count($archivos) > 10) {
    echo "... y " . (count($archivos) - 10) . " archivos más\n";
}
echo str_repeat("-", 40) . "\n\n";

// Confirmación
echo "⚠️  ¿DESEAS ELIMINAR TODOS LOS ARCHIVOS? (escribe 'ELIMINAR' para confirmar): ";
$handle = fopen("php://stdin", "r");
$respuesta = trim(fgets($handle));
fclose($handle);

if ($respuesta === 'ELIMINAR') {
    echo "\n🗑️  ELIMINANDO ARCHIVOS...\n";
    
    $eliminados = 0;
    $errores = 0;
    
    foreach ($archivos as $archivo) {
        if (unlink($archivo)) {
            $eliminados++;
        } else {
            $errores++;
            echo "❌ Error eliminando: " . basename($archivo) . "\n";
        }
    }
    
    echo "\n📊 RESUMEN FINAL:\n";
    echo "• Eliminados: $eliminados\n";
    echo "• Errores: $errores\n";
    echo "• Espacio liberado: " . round($total_size / 1024 / 1024, 2) . " MB\n";
    
} else {
    echo "\n❌ OPERACIÓN CANCELADA (respuesta: '$respuesta')\n";
}

echo "\n🎯 PROCESO FINALIZADO\n";
?>
