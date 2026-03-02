<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../admin_functions.php';
require_once '../../includes/auth.php';

// Verificar sesión de admin
if (session_status() === PHP_SESSION_NONE) session_start();
requiereAdmin();

// Obtener datos
$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;
$token = $data['csrf_token'] ?? '';

// Validar CSRF
if (!auth_csrf_check($token)) {
    echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido']);
    exit;
}

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID de tienda no proporcionado']);
    exit;
}

try {
    $db = getDB();
    
    // Verificar que la tienda existe antes de intentar borrar
    $stmt = $db->prepare("SELECT usuario_id, nombre, logo FROM tiendas WHERE id = ?");
    $stmt->execute([$id]);
    $tienda = $stmt->fetch();
    
    if (!$tienda) {
        throw new Exception("La tienda no existe");
    }

    $db->beginTransaction();

    // 1. Obtener y eliminar imágenes de productos del servidor
    $stmt_imgs = $db->prepare("
        SELECT pi.nombre_archivo 
        FROM producto_imagenes pi
        JOIN productos p ON pi.producto_id = p.id
        WHERE p.usuario_id = ?
    ");
    $stmt_imgs->execute([$tienda['usuario_id']]);
    $imagenes = $stmt_imgs->fetchAll(PDO::FETCH_COLUMN);

    foreach ($imagenes as $img) {
        $ruta = UPLOAD_PATH . $img;
        if (file_exists($ruta)) {
            @unlink($ruta); // Borrar archivo físico
        }
    }

    // 2. Eliminar logo de la tienda si existe
    if ($tienda['logo']) {
        $ruta_logo = UPLOAD_PATH . $tienda['logo'];
        if (file_exists($ruta_logo)) {
            @unlink($ruta_logo);
        }
    }

    // 3. Eliminar imágenes de la base de datos (Cascade delete debería encargarse, pero por seguridad)
    // Nota: Si tus FK tienen ON DELETE CASCADE, esto es automático. Si no, lo hacemos manual.
    // Asumiremos eliminación manual para asegurar limpieza.
    $db->prepare("DELETE pi FROM producto_imagenes pi JOIN productos p ON pi.producto_id = p.id WHERE p.usuario_id = ?")->execute([$tienda['usuario_id']]);

    // 4. Eliminar reportes de productos
    $db->prepare("DELETE d FROM denuncias d JOIN productos p ON d.producto_id = p.id WHERE p.usuario_id = ?")->execute([$tienda['usuario_id']]);

    // 5. Eliminar productos
    $db->prepare("DELETE FROM productos WHERE usuario_id = ?")->execute([$tienda['usuario_id']]);

    // 6. Eliminar reportes de la tienda
    $db->prepare("DELETE FROM denuncias_tiendas WHERE tienda_id = ?")->execute([$id]);

    // 7. FINALMENTE: Eliminar la tienda
    $stmt_del = $db->prepare("DELETE FROM tiendas WHERE id = ?");
    $stmt_del->execute([$id]);

    // Registrar acción en log
    registrarAccionAdmin('eliminar_tienda', 'tiendas', $id, "Tienda eliminada permanentemente: " . $tienda['nombre']);

    $db->commit();
    echo json_encode(['success' => true, 'message' => 'Tienda y todos sus datos eliminados correctamente']);

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log("Error eliminando tienda $id: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al eliminar: ' . $e->getMessage()]);
}
?>