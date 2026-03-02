<?php
// Script para encontrar tu user_id
require_once __DIR__ . '/config/database.php';

echo "<h2>🔍 BUSCAR TU USER_ID</h2>";

// Si estás logueado, muestra tu ID actual
if (isset($_SESSION['usuario_id'])) {
    echo "<p style='color: green; font-size: 18px;'>✅ Tu User ID es: <strong>" . $_SESSION['usuario_id'] . "</strong></p>";
} else {
    echo "<p style='color: orange;'>⚠️ No hay sesión activa</p>";
}

// Mostrar todos los usuarios (para desarrollo)
echo "<h3>📋 Todos los usuarios en el sistema:</h3>";
$db = getDB();
$stmt = $db->query("SELECT id, nombre, email FROM usuarios ORDER BY id DESC LIMIT 10");
$usuarios = $stmt->fetchAll();

echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>ID</th><th>Nombre</th><th>Email</th></tr>";

foreach ($usuarios as $usuario) {
    echo "<tr>";
    echo "<td>{$usuario['id']}</td>";
    echo "<td>" . htmlspecialchars($usuario['nombre']) . "</td>";
    echo "<td>" . htmlspecialchars($usuario['email']) . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h3>🚀 Para usar:</h3>";
echo "<p>Ve a: <code>auditoria_completa.php?user_id=[TU_ID]</code></p>";
echo "<p>O reemplaza la línea en el script: <code>\$usuario_id = [TU_ID];</code></p>";
?>
