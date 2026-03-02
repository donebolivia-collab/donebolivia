<?php
require_once '../config/database.php';
$db = getDB();

try {
    // Agregar columna suspension_fin si no existe
    $db->exec("ALTER TABLE tiendas ADD COLUMN suspension_fin DATETIME NULL DEFAULT NULL");
    echo "Columna suspension_fin agregada con éxito.";
} catch (Exception $e) {
    echo "Nota: " . $e->getMessage();
}
?>