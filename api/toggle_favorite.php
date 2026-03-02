<?php
session_start();
header('Content-Type: application/json');

// Incluir la conexión a la base de datos y las funciones auxiliares
// Asumo que tu archivo database.php está en ../config/database.php
require_once __DIR__ . '/../config/database.php';
// Asumo que tu archivo functions.php está en ../includes/functions.php y contiene estaLogueado()
require_once __DIR__ . '/../includes/functions.php'; 

// Verificar si el usuario está logueado
// Usamos la función estaLogueado() de functions.php
if (!estaLogueado()) {
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado.']);
    exit();
}

$user_id = $_SESSION['usuario_id']; // Obtenemos el ID del usuario de la sesión

// Obtener el ID del producto de la solicitud POST
if (!isset($_POST['product_id']) || !filter_var($_POST['product_id'], FILTER_VALIDATE_INT)) {
    echo json_encode(['success' => false, 'message' => 'ID de producto no válido o no proporcionado.']);
    exit();
}

$product_id = (int)$_POST['product_id'];

// Crear una instancia de la conexión a la base de datos
$database = new Database();
$db = $database->getConnection();

try {
    // Verificar si el producto ya es favorito del usuario
    $query = "SELECT COUNT(*) FROM favoritos WHERE usuario_id = :user_id AND producto_id = :product_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':product_id', $product_id);
    $stmt->execute();
    $is_favorite = $stmt->fetchColumn() > 0;

    if ($is_favorite) {
        // Si ya es favorito, eliminarlo
        $query = "DELETE FROM favoritos WHERE usuario_id = :user_id AND producto_id = :product_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':product_id', $product_id);
        $stmt->execute();
        echo json_encode(['success' => true, 'action' => 'removed', 'message' => 'Producto eliminado de favoritos.']);
    } else {
        // Si no es favorito, agregarlo
        $query = "INSERT INTO favoritos (usuario_id, producto_id) VALUES (:user_id, :product_id)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':product_id', $product_id);
        $stmt->execute();
        echo json_encode(['success' => true, 'action' => 'added', 'message' => 'Producto agregado a favoritos.']);
    }

} catch (PDOException $e) {
    // Manejar errores de la base de datos
    error_log("Error al togglear favorito: " . $e->getMessage()); // Para logs del servidor
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
}
?>
