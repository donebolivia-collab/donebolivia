<?php
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    
    // 1. Agregar columnas a 'productos' si no existen
    $columnsToAdd = [
        'visitas' => "INT DEFAULT 0",
        'likes' => "INT DEFAULT 0"
    ];

    $stmt = $db->query("DESCRIBE productos");
    $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columnsToAdd as $col => $def) {
        if (!in_array($col, $existingColumns)) {
            $db->exec("ALTER TABLE productos ADD COLUMN $col $def");
            echo "Columna '$col' agregada a tabla 'productos'.<br>";
        } else {
            echo "Columna '$col' ya existe en 'productos'.<br>";
        }
    }

    // 2. Crear tabla 'producto_likes'
    $sql = "CREATE TABLE IF NOT EXISTS producto_likes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        producto_id INT NOT NULL,
        ip_address VARCHAR(45),
        user_agent VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_like (producto_id, ip_address),
        INDEX idx_producto (producto_id)
    )";
    
    $db->exec($sql);
    echo "Tabla 'producto_likes' verificada/creada.<br>";

    echo "<h3>Configuración de métricas completada con éxito.</h3>";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
