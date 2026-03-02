<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php'; // Asegura que SITE_URL y DB_ constants estén disponibles
// Los error_log temporales se han comentado para evitar ruido en logs de producción,
// pero pueden descomentarse si se necesita depurar en el servidor.

if (!estaLogueado()) {
    redireccionar('/auth/login.php?redirect=' . urlencode('/mi/publicaciones.php'));
}
iniciarSesion();

if (empty($_POST['csrf']) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
    redireccionar('/mi/publicaciones.php?msg=csrf');
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    redireccionar('/mi/publicaciones.php?msg=badid');
}

$db = getDB();

// Verificar pertenencia: Solo buscar si el ID existe y pertenece al usuario logueado.
// No nos importa su estado (activo/eliminado) porque haremos un borrado permanente.
$st = $db->prepare("SELECT id FROM productos WHERE id=? AND usuario_id=? LIMIT 1");
$st->execute([$id, $_SESSION['usuario_id']]);
$own = $st->fetch();

if (!$own) {
    redireccionar('/mi/publicaciones.php?msg=forbidden'); // No existe o no pertenece al usuario
}

$done = false;
try {
    // Realizar HARD DELETE directamente
    // Paso 1: Eliminar imágenes relacionadas con la publicación
    $db->prepare("DELETE FROM producto_imagenes WHERE producto_id=?")->execute([$id]);

    // Paso 2: Eliminar la publicación de la tabla 'productos'
    $db->prepare("DELETE FROM productos WHERE id=? AND usuario_id=?")->execute([$id, $_SESSION['usuario_id']]);
    $done = true; // Si llegamos aquí, la eliminación fue exitosa
} catch (Exception $e) {
    // Manejar cualquier error de la base de datos durante la eliminación
    // Puedes agregar un error_log aquí para registrar el error si lo deseas
    // error_log("Error al eliminar publicación ID " . $id . ": " . $e->getMessage());
}

redireccionar('/mi/publicaciones.php?msg=' . ($done ? 'deleted' : 'fail'));
?>
