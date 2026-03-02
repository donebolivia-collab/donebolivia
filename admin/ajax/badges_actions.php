<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../admin_functions.php';
require_once __DIR__ . '/../../includes/auth.php'; // Seguridad CSRF

header('Content-Type: application/json');

// Funciones de ayuda para este script
function eliminarArchivo($rutaRelativa) {
    if (empty($rutaRelativa)) return;
    $rutaAbsoluta = realpath(__DIR__ . '/../../' . $rutaRelativa);
    if ($rutaAbsoluta && file_exists($rutaAbsoluta)) {
        unlink($rutaAbsoluta);
    }
}

// Verificar que es un admin
if (!esAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

// El token ahora viene de $_POST, no de JSON
if (!auth_csrf_check($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Error de seguridad (CSRF token inválido)']);
    exit;
}

$response = ['success' => false, 'message' => 'Acción no válida'];
$accion = $_POST['accion'] ?? '';
$data = $_POST;

$db = getDB();

try {
    // Lógica de subida de archivo
    $rutaSVG = $data['svg_path'] ?? ''; // Ruta actual por defecto
    if (isset($_FILES['svg_file']) && $_FILES['svg_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['svg_file'];

        // 1. Validar tipo de archivo
        if ($file['type'] !== 'image/svg+xml') {
            throw new Exception('El archivo debe ser de tipo SVG.');
        }

        // 2. Generar nombre único y ruta
        $directorioDestino = 'assets/img/badges/';
        $nombreArchivo = 'badge_' . uniqid() . '_' . time() . '.svg';
        $rutaCompleta = __DIR__ . '/../../' . $directorioDestino . $nombreArchivo;

        // 3. Mover archivo
        if (move_uploaded_file($file['tmp_name'], $rutaCompleta)) {
            // Si la subida es exitosa, actualizamos la ruta para la BD
            $rutaSVG = $directorioDestino . $nombreArchivo;
            
            // Si estamos actualizando, eliminar el archivo antiguo
            if ($accion === 'actualizar' && !empty($data['svg_path'])) {
                eliminarArchivo($data['svg_path']);
            }
        } else {
            throw new Exception('Error al mover el archivo subido.');
        }
    }

    switch ($accion) {
        case 'crear':
            if (empty($rutaSVG)) {
                throw new Exception('Es obligatorio subir un archivo SVG para una nueva insignia.');
            }
            $stmt = $db->prepare(
                "INSERT INTO badges (nombre, slug, descripcion, svg_path, orden, activo) VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $data['nombre'], $data['slug'], $data['descripcion'],
                $rutaSVG, // Nueva ruta del archivo
                $data['orden'], $data['activo']
            ]);
            $response = ['success' => true, 'id' => $db->lastInsertId()];
            registrarAccionAdmin('crear_badge', 'badges', $response['id']);
            break;

        case 'actualizar':
            $stmt = $db->prepare(
                "UPDATE badges SET nombre = ?, slug = ?, descripcion = ?, svg_path = ?, orden = ?, activo = ? WHERE id = ?"
            );
            $stmt->execute([
                $data['nombre'], $data['slug'], $data['descripcion'],
                $rutaSVG, // Ruta nueva o la anterior si no se subió archivo
                $data['orden'], $data['activo'], $data['id']
            ]);
            $response = ['success' => true];
            registrarAccionAdmin('actualizar_badge', 'badges', $data['id']);
            break;

        case 'eliminar':
            // Primero, obtener la ruta del SVG para poder eliminar el archivo
            $stmt = $db->prepare("SELECT svg_path FROM badges WHERE id = ?");
            $stmt->execute([$data['id']]);
            $rutaParaEliminar = $stmt->fetchColumn();

            // Por seguridad, primero eliminamos las asociaciones en producto_badges
            $stmt = $db->prepare("DELETE FROM producto_badges WHERE badge_id = ?");
            $stmt->execute([$data['id']]);
            
            // Ahora eliminamos el badge principal
            $stmt = $db->prepare("DELETE FROM badges WHERE id = ?");
            $stmt->execute([$data['id']]);

            // Si todo fue bien en la BD, eliminamos el archivo físico
            eliminarArchivo($rutaParaEliminar);
            
            $response = ['success' => true];
            registrarAccionAdmin('eliminar_badge', 'badges', $data['id']);
            break;

        case 'toggle_activo':
            $stmt = $db->prepare("UPDATE badges SET activo = ? WHERE id = ?");
            $stmt->execute([$data['activo'], $data['id']]);
            $response = ['success' => true];
            $accion_log = $data['activo'] ? 'activar_badge' : 'desactivar_badge';
            registrarAccionAdmin($accion_log, 'badges', $data['id']);
            break;
    }
} catch (Exception $e) {
    $response = ['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()];
}

echo json_encode($response);
?>