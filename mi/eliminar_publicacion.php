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
    $db->beginTransaction();
    
    // PASO 1: Obtener todas las imágenes asociadas ANTES de eliminar registros
    $stmt_imgs = $db->prepare("SELECT nombre_archivo FROM producto_imagenes WHERE producto_id = ?");
    $stmt_imgs->execute([$id]);
    $imagenes = $stmt_imgs->fetchAll(PDO::FETCH_COLUMN);
    
    // PASO 2: Eliminar archivos físicos del servidor
    $imagenes_eliminadas = 0;
    foreach ($imagenes as $imagen) {
        $ruta_completa = __DIR__ . '/../uploads/' . $imagen;
        if (file_exists($ruta_completa)) {
            if (unlink($ruta_completa)) {
                $imagenes_eliminadas++;
            } else {
                error_log("No se pudo eliminar archivo: " . $ruta_completa);
            }
        }
    }
    
    // PASO 3: Eliminar registros de base de datos en orden correcto (respetando FK)
    // 3.1 Eliminar imágenes de la BD
    $stmt_del_imgs = $db->prepare("DELETE FROM producto_imagenes WHERE producto_id = ?");
    $stmt_del_imgs->execute([$id]);
    
    // 3.2 Eliminar badges asociados
    $stmt_del_badges = $db->prepare("DELETE FROM producto_badges WHERE producto_id = ?");
    $stmt_del_badges->execute([$id]);
    
    // 3.3 Eliminar reportes asociados
    $stmt_del_reports = $db->prepare("DELETE FROM denuncias WHERE producto_id = ?");
    $stmt_del_reports->execute([$id]);
    
    // 3.4 Eliminar el producto final
    $stmt_del_producto = $db->prepare("DELETE FROM productos WHERE id = ? AND usuario_id = ?");
    $stmt_del_producto->execute([$id, $_SESSION['usuario_id']]);
    
    $db->commit();
    $done = true;
    
    // Auditoría de eliminación para monitoreo
    error_log("Producto ID $id eliminado completamente por usuario {$_SESSION['usuario_id']}. Imágenes físicas eliminadas: $imagenes_eliminadas");
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error CRÍTICO eliminando publicación ID $id: " . $e->getMessage());
    $done = false;
}

redireccionar('/mi/publicaciones.php?msg=' . ($done ? 'deleted' : 'fail'));
?>
