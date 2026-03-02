<?php
/**
 * API: Eliminar logo de tienda
 */
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (!estaLogueado()) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

try {
    $db = getDB();
    $usuario_id = $_SESSION['usuario_id'];

    // Obtener tienda y logo actual
    $stmt = $db->prepare("SELECT id, logo FROM tiendas WHERE usuario_id = ? LIMIT 1");
    $stmt->execute([$usuario_id]);
    $tienda = $stmt->fetch();

    if (!$tienda) {
        throw new Exception('Tienda no encontrada');
    }

    // Eliminar archivo físico si existe
    if (!empty($tienda['logo'])) {
        $ruta_archivo = __DIR__ . '/../uploads/logos/' . $tienda['logo'];
        if (file_exists($ruta_archivo)) {
            unlink($ruta_archivo);
        } else {
            // Intentar borrar de la ruta antigua por si acaso
            $ruta_antigua = __DIR__ . '/../uploads/' . $tienda['logo'];
            if (file_exists($ruta_antigua)) {
                unlink($ruta_antigua);
            }
        }
    }

    // Actualizar BD para eliminar referencia al logo
    $stmt = $db->prepare("UPDATE tiendas SET logo = NULL WHERE id = ?");
    $stmt->execute([$tienda['id']]);

    echo json_encode([
        'success' => true,
        'message' => 'Logo eliminado correctamente'
    ]);

} catch (Exception $e) {
    error_log("Error en eliminar_logo.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
