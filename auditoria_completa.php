<?php
// AUDITORÍA COMPLETA DEL SISTEMA DE PRODUCTOS
require_once __DIR__ . '/config/database.php';

$db = getDB();

// CAMBIA ESTO por tu ID real
$usuario_id = $_GET['user_id'] ?? 1;

echo "<!DOCTYPE html><html><head><title>Auditoría de Productos</title>";
echo "<style>
    body { font-family: Arial; margin: 20px; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    .error { background-color: #ffebee; color: #c62828; }
    .warning { background-color: #fff3e0; color: #f57c00; }
    .success { background-color: #e8f5e8; color: #2e7d32; }
    .info { background-color: #e3f2fd; color: #1565c0; }
    h1, h2, h3 { color: #333; }
    .summary { background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0; }
</style></head><body>";

echo "<h1>🔍 AUDITORÍA COMPLETA DEL SISTEMA</h1>";
echo "<div class='summary'><strong>Usuario ID:</strong> $usuario_id</div>";

// 1. TOTAL DE PRODUCTOS
echo "<h2>📊 1. TOTAL DE PRODUCTOS</h2>";
$stmt = $db->prepare("SELECT COUNT(*) as total FROM productos WHERE usuario_id = ?");
$stmt->execute([$usuario_id]);
$total = $stmt->fetch();
echo "<p>Total de productos en BD: <strong>{$total['total']}</strong></p>";

// 2. PRODUCTOS POR ESTADO
echo "<h2>📈 2. PRODUCTOS POR ESTADO</h2>";
$stmt = $db->prepare("
    SELECT activo, COUNT(*) as cantidad 
    FROM productos 
    WHERE usuario_id = ?
    GROUP BY activo
");
$stmt->execute([$usuario_id]);
$estados = $stmt->fetchAll();
echo "<table>";
echo "<tr><th>Estado</th><th>Cantidad</th></tr>";
foreach ($estados as $estado) {
    $estado_text = $estado['activo'] ? 'ACTIVO (Visible)' : 'INACTIVO (Oculto)';
    $class = $estado['activo'] ? 'success' : 'warning';
    echo "<tr class='$class'>";
    echo "<td>$estado_text</td><td>{$estado['cantidad']}</td>";
    echo "</tr>";
}
echo "</table>";

// 3. POSIBLES DUPLICADOS
echo "<h2>⚠️ 3. ANÁLISIS DE DUPLICADOS</h2>";
$stmt = $db->prepare("
    SELECT titulo, COUNT(*) as duplicados, GROUP_CONCAT(id) as ids
    FROM productos 
    WHERE usuario_id = ?
    GROUP BY titulo, descripcion
    HAVING COUNT(*) > 1
");
$stmt->execute([$usuario_id]);
$duplicados = $stmt->fetchAll();

if (count($duplicados) > 0) {
    echo "<p class='error'>⚠️ SE ENCONTRARON " . count($duplicados) . " POSIBLES DUPLICADOS:</p>";
    echo "<table>";
    echo "<tr><th>Título</th><th>Duplicados</th><th>IDs</th></tr>";
    foreach ($duplicados as $dup) {
        echo "<tr class='error'>";
        echo "<td>" . htmlspecialchars($dup['titulo']) . "</td>";
        echo "<td>{$dup['duplicados']}</td>";
        echo "<td>{$dup['ids']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='success'>✅ No se encontraron duplicados</p>";
}

// 4. DETALLE COMPLETO
echo "<h2>📋 4. DETALLE COMPLETO DE PRODUCTOS</h2>";
$stmt = $db->prepare("
    SELECT p.id, p.titulo, p.activo, p.fecha_publicacion, p.precio,
           COUNT(pi.id) as num_imagenes
    FROM productos p
    LEFT JOIN producto_imagenes pi ON p.id = pi.producto_id
    WHERE p.usuario_id = ?
    GROUP BY p.id
    ORDER BY p.id DESC
    LIMIT 20
");
$stmt->execute([$usuario_id]);
$productos = $stmt->fetchAll();

echo "<table>";
echo "<tr><th>ID</th><th>Título</th><th>Precio</th><th>Imágenes</th><th>Estado</th><th>Fecha</th></tr>";

foreach ($productos as $producto) {
    $estado_class = $producto['activo'] ? 'success' : 'warning';
    $estado_text = $producto['activo'] ? 'ACTIVO' : 'INACTIVO';
    $imagenes_class = $producto['num_imagenes'] > 0 ? 'success' : 'error';
    
    echo "<tr>";
    echo "<td>{$producto['id']}</td>";
    echo "<td>" . htmlspecialchars(substr($producto['titulo'], 0, 50)) . "</td>";
    echo "<td>\${$producto['precio']}</td>";
    echo "<td class='$imagenes_class'>{$producto['num_imagenes']}</td>";
    echo "<td class='$estado_class'>$estado_text</td>";
    echo "<td>{$producto['fecha_publicacion']}</td>";
    echo "</tr>";
}
echo "</table>";

// 5. RECOMENDACIONES
echo "<h2>💡 5. RECOMENDACIONES</h2>";
echo "<div class='summary'>";
echo "<h3>Si hay duplicados:</h3>";
echo "<p>Ejecuta: <code>DELETE FROM productos WHERE id IN ([IDS_DUPLICADOS]) AND id NOT IN (SELECT MIN(id) FROM productos GROUP BY titulo);</code></p>";
echo "<h3>Si hay productos INACTIVOS:</h3>";
echo "<p>Pueden estar causando confusión en el frontend. Considera activarlos o eliminarlos.</p>";
echo "<h3>Si faltan imágenes:</h3>";
echo "<p>Productos sin imágenes pueden causar errores de renderizado.</p>";
echo "</div>";

echo "</body></html>";
?>
