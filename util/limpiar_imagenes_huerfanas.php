<?php
/**
 * Script para limpiar imágenes huérfanas del servidor
 * Imágenes que no están asociadas a ningún producto en la base de datos
 */

require_once __DIR__ . '/../config/database.php';

echo "<h1>🧹 Limpieza de Imágenes Huérfanas</h1>";
echo "<p>Este script eliminará las imágenes que no están asociadas a ningún producto.</p>";

try {
    $db = getDB();
    
    // 1. Obtener todas las imágenes en la base de datos
    $stmt = $db->prepare("SELECT nombre_archivo FROM producto_imagenes");
    $stmt->execute();
    $imagenes_db = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h2>📊 Análisis de Imágenes</h2>";
    echo "<p>Imágenes en la base de datos: " . count($imagenes_db) . "</p>";
    
    // 2. Obtener todas las imágenes físicas en el servidor
    $directorio_uploads = __DIR__ . '/../uploads/productos/';
    $imagenes_fisicas = [];
    
    if (is_dir($directorio_uploads)) {
        $archivos = scandir($directorio_uploads);
        foreach ($archivos as $archivo) {
            if ($archivo !== '.' && $archivo !== '..') {
                $imagenes_fisicas[] = $archivo;
            }
        }
    }
    
    echo "<p>Imágenes físicas en el servidor: " . count($imagenes_fisicas) . "</p>";
    
    // 3. Encontrar imágenes huérfanas (físicas pero no en BD)
    $imagenes_huerfanas = array_diff($imagenes_fisicas, $imagenes_db);
    
    echo "<h2>🔍 Imágenes Huérfanas Encontradas</h2>";
    echo "<p>Total de imágenes huérfanas: " . count($imagenes_huerfanas) . "</p>";
    
    if (empty($imagenes_huerfanas)) {
        echo "<p style='color: green;'>✅ No hay imágenes huérfanas. Todo está en orden.</p>";
    } else {
        echo "<h3>📋 Lista de imágenes huérfanas:</h3>";
        echo "<ul style='max-height: 300px; overflow-y: auto; border: 1px solid #ccc; padding: 10px;'>";
        foreach ($imagenes_huerfanas as $imagen) {
            $ruta_completa = $directorio_uploads . $imagen;
            $tamano = file_exists($ruta_completa) ? filesize($ruta_completa) : 0;
            echo "<li>" . htmlspecialchars($imagen) . " (" . number_format($tamano / 1024, 2) . " KB)</li>";
        }
        echo "</ul>";
        
        // 4. Botón para eliminar (si se pasa por POST)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_huerfanas'])) {
            echo "<h2>🗑️ Eliminando imágenes huérfanas...</h2>";
            
            $eliminadas = 0;
            $errores = 0;
            $espacio_liberado = 0;
            
            foreach ($imagenes_huerfanas as $imagen) {
                $ruta_completa = $directorio_uploads . $imagen;
                
                if (file_exists($ruta_completa)) {
                    $tamano = filesize($ruta_completa);
                    
                    if (unlink($ruta_completa)) {
                        $eliminadas++;
                        $espacio_liberado += $tamano;
                        echo "<p style='color: green;'>✅ Eliminada: " . htmlspecialchars($imagen) . "</p>";
                    } else {
                        $errores++;
                        echo "<p style='color: red;'>❌ Error al eliminar: " . htmlspecialchars($imagen) . "</p>";
                    }
                }
            }
            
            echo "<h3>📈 Resumen de la limpieza:</h3>";
            echo "<ul>";
            echo "<li>Imágenes eliminadas: <strong>" . $eliminadas . "</strong></li>";
            echo "<li>Errores: <strong>" . $errores . "</strong></li>";
            echo "<li>Espacio liberado: <strong>" . number_format($espacio_liberado / 1024 / 1024, 2) . " MB</strong></li>";
            echo "</ul>";
            
            if ($eliminadas > 0) {
                echo "<p style='color: green; font-weight: bold;'>🎉 ¡Limpieza completada con éxito!</p>";
            }
        } else {
            echo "<form method='post' style='margin-top: 20px;'>";
            echo "<input type='hidden' name='eliminar_huerfanas' value='1'>";
            echo "<button type='submit' style='background: #dc3545; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;' onclick='return confirm(\"¿Estás seguro de eliminar estas " . count($imagenes_huerfanas) . " imágenes? Esta acción no se puede deshacer.\")'>";
            echo "🗑️ Eliminar " . count($imagenes_huerfanas) . " imágenes huérfanas";
            echo "</button>";
            echo "</form>";
        }
    }
    
    echo "<hr>";
    echo "<p><a href='javascript:history.back()'>← Volver</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 20px auto;
    padding: 20px;
    background: #f5f5f5;
}
h1, h2, h3 {
    color: #333;
}
form {
    text-align: center;
}
button:hover {
    background: #c82333 !important;
}
</style>
