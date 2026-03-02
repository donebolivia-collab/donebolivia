<?php
// Script para encontrar productos duplicados
require_once __DIR__ . '/config/database.php';

$db = getDB();
$usuario_id = 1; // CAMBIA ESTO por tu ID real

echo "<h2>Buscando productos duplicados...</h2>";

// Buscar duplicados por título y descripción
$stmt = $db->prepare("
    SELECT 
        id,
        titulo,
        usuario_id,
        activo,
        fecha_publicacion,
        ROW_NUMBER() OVER (
            PARTITION BY titulo, descripcion 
            ORDER BY id DESC
        ) as row_num
    FROM productos 
    WHERE usuario_id = ?
");
$stmt->execute([$usuario_id]);
$productos = $stmt->fetchAll();

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>ID</th><th>Título</th><th>Estado</th><th>Fecha</th><th>#</th></tr>";

$duplicados = [];
foreach ($productos as $producto) {
    $row_class = '';
    if ($producto['row_num'] > 1) {
        $row_class = 'style="background-color: #ffcccc;"';
        $duplicados[] = $producto;
    }
    
    echo "<tr $row_class>";
    echo "<td>{$producto['id']}</td>";
    echo "<td>" . htmlspecialchars($producto['titulo']) . "</td>";
    echo "<td>" . ($producto['activo'] ? 'Activo' : 'Inactivo') . "</td>";
    echo "<td>{$producto['fecha_publicacion']}</td>";
    echo "<td>{$producto['row_num']}</td>";
    echo "</tr>";
}

echo "</table>";

if (!empty($duplicados)) {
    echo "<h2 style='color: red;'>¡ENCONTRADOS " . count($duplicados) . " DUPLICADOS!</h2>";
    echo "<h3>Para limpiar:</h3>";
    echo "<pre>";
    echo "DELETE FROM productos WHERE id IN (";
    $ids = [];
    foreach ($duplicados as $dup) {
        if ($dup['row_num'] > 1) {
            $ids[] = $dup['id'];
        }
    }
    echo implode(', ', $ids);
    echo ") AND row_num > 1;";
    echo "</pre>";
} else {
    echo "<h2 style='color: green;'>No se encontraron duplicados</h2>";
}
?>
