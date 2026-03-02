<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/feria_debug.log');

require_once __DIR__ . '/../../config/database.php';
// IMPORTANTE: Cargar funciones para usar subirImagen() optimizada
require_once __DIR__ . '/../../includes/functions.php';
session_start();

header('Content-Type: application/json');

try {
    // Log request
    $rawInput = file_get_contents('php://input');
    error_log("Request received: " . $rawInput);
    error_log("Session ID: " . session_id());
    error_log("User ID: " . ($_SESSION['user_id'] ?? 'NULL'));

    // Normalizar ID de usuario para logs
    $userId = $_SESSION['usuario_id'] ?? $_SESSION['user_id'];

    // --- REINGENIERÍA PARA SOPORTE MULTIPART (ARCHIVOS) ---
    // Si viene $_POST, usamos $_POST (para subida de archivos)
    // Si no, intentamos leer JSON body (para acciones simples)
    if (!empty($_POST)) {
        $input = $_POST;
    } else {
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
    }

    // Detección de error de tamaño POST (Imagen muy pesada)
    if (empty($input) && $_SERVER['REQUEST_METHOD'] == 'POST' && empty($_POST) && empty($_FILES) && $_SERVER['CONTENT_LENGTH'] > 0) {
        $maxPost = ini_get('post_max_size');
        throw new Exception("El archivo es demasiado grande. El límite del servidor es $maxPost.");
    }

    if (empty($input)) {
        throw new Exception("No data received (Input vacío). Revise el tamaño del archivo o los datos enviados.");
    }

    $action = $input['action'] ?? '';
    $id = intval($input['id'] ?? 0);
    $db = getDB();

    if ($action === 'toggle') {
        $active = intval($input['active']);
        $stmt = $db->prepare("UPDATE feria_sectores SET activo = ? WHERE id = ?");
        $stmt->execute([$active, $id]);
        
        // Limpiar caché de navegador si existiera
        header("Cache-Control: no-cache, must-revalidate");
        
        echo json_encode(['success' => true]);
    } 
    elseif ($action === 'create' || $action === 'edit') {
        $titulo = $input['titulo'] ?? '';
        $slug = $input['slug'] ?? '';
        $desc = $input['descripcion'] ?? '';
        $color = $input['color'] ?? '#007AFF';
        // Nuevo: Categoría Default
        $catDefaultId = !empty($input['categoria_default_id']) ? intval($input['categoria_default_id']) : null;
        
        // Manejo de Imagen (Archivo o Texto)
        $imagen = $input['imagen_actual'] ?? '';
        
        // Procesar subida de archivo (AHORA USANDO LA FUNCIÓN OPTIMIZADA)
        if (isset($_FILES['imagen_file']) && $_FILES['imagen_file']['error'] === UPLOAD_ERR_OK) {
            
            // 1. Eliminar imagen anterior si existe (Solo en EDIT)
            if ($action === 'edit' && !empty($imagen)) {
                $oldImagePath = __DIR__ . '/../../' . ltrim($imagen, '/');
                // Seguridad: verificar que está dentro de assets
                if (file_exists($oldImagePath) && strpos(realpath($oldImagePath), realpath(__DIR__ . '/../../assets/')) === 0) {
                    unlink($oldImagePath);
                }
            }

            // 2. Usar la función maestra subirImagen()
            // Pasamos ruta base personalizada porque la feria no usa 'uploads/', usa 'assets/img/'
            $rutaBaseAssets = __DIR__ . '/../../assets/img/';
            
            $resultado = subirImagen($_FILES['imagen_file'], 'feria_banners', $rutaBaseAssets);
            
            if (isset($resultado['success'])) {
                // La función devuelve la ruta relativa a la base, ej: "feria_banners/foto.webp"
                // Nosotros necesitamos la ruta web completa: "/assets/img/feria_banners/foto.webp"
                $imagen = '/assets/img/' . $resultado['archivo'];
            } else {
                throw new Exception($resultado['error'] ?? "Error al subir imagen");
            }
        }
        
        if (empty($titulo) || empty($slug)) throw new Exception("Título y Slug son obligatorios");

        if ($action === 'create') {
            // CREATE
            // Obtener el último orden
            $stmtOrder = $db->query("SELECT MAX(orden) as max_orden FROM feria_sectores");
            $maxOrden = $stmtOrder->fetch()['max_orden'] ?? 0;
            $nuevoOrden = $maxOrden + 1;

            $stmt = $db->prepare("INSERT INTO feria_sectores (slug, titulo, descripcion, color_hex, imagen_banner, orden, categoria_default_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$slug, $titulo, $desc, $color, $imagen, $nuevoOrden, $catDefaultId]);
        } else {
            // EDIT
            if (empty($id)) throw new Exception("ID es obligatorio para editar");
            $stmt = $db->prepare("UPDATE feria_sectores SET slug = ?, titulo = ?, descripcion = ?, color_hex = ?, imagen_banner = ?, categoria_default_id = ? WHERE id = ?");
            $stmt->execute([$slug, $titulo, $desc, $color, $imagen, $catDefaultId, $id]);
        }
        
        echo json_encode(['success' => true]);
    }
    elseif ($action === 'delete') {
        // Verificar si tiene tiendas ocupadas (opcional, por ahora borramos todo)
        $stmt = $db->prepare("DELETE FROM feria_sectores WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
    }
    elseif ($action === 'delete_image') {
        // REINGENIERÍA: Acción específica para borrar imagen
        $stmt = $db->prepare("SELECT imagen_banner FROM feria_sectores WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row && !empty($row['imagen_banner'])) {
            $uploadDir = __DIR__ . '/../../assets/img/feria_banners/';
            $oldImagePath = __DIR__ . '/../../' . ltrim($row['imagen_banner'], '/');
            
            // Borrar archivo físico
            if (file_exists($oldImagePath) && strpos(realpath($oldImagePath), realpath($uploadDir)) === 0) {
                unlink($oldImagePath);
            }
            
            // Actualizar BD a NULL o vacío
            $stmtUpdate = $db->prepare("UPDATE feria_sectores SET imagen_banner = NULL WHERE id = ?");
            $stmtUpdate->execute([$id]);
        }
        echo json_encode(['success' => true]);
    }
    elseif ($action === 'reorder') {
        $direction = $input['direction']; // 'up' or 'down'
        
        // Obtener sector actual
        $stmtCurrent = $db->prepare("SELECT id, orden FROM feria_sectores WHERE id = ?");
        $stmtCurrent->execute([$id]);
        $current = $stmtCurrent->fetch(PDO::FETCH_ASSOC);
        
        if (!$current) throw new Exception("Sector no encontrado");
        
        $currentOrder = $current['orden'];
        
        // Buscar el vecino para intercambiar
        if ($direction === 'up') {
            $stmtNeighbor = $db->prepare("SELECT id, orden FROM feria_sectores WHERE orden < ? ORDER BY orden DESC LIMIT 1");
        } else {
            $stmtNeighbor = $db->prepare("SELECT id, orden FROM feria_sectores WHERE orden > ? ORDER BY orden ASC LIMIT 1");
        }
        $stmtNeighbor->execute([$currentOrder]);
        $neighbor = $stmtNeighbor->fetch(PDO::FETCH_ASSOC);
        
        if ($neighbor) {
            // Swap
            $db->beginTransaction();
            $stmtUpdate1 = $db->prepare("UPDATE feria_sectores SET orden = ? WHERE id = ?");
            $stmtUpdate1->execute([$neighbor['orden'], $current['id']]);
            
            $stmtUpdate2 = $db->prepare("UPDATE feria_sectores SET orden = ? WHERE id = ?");
            $stmtUpdate2->execute([$currentOrder, $neighbor['id']]);
            $db->commit();
        }
        
        echo json_encode(['success' => true]);
    }
    else {
        echo json_encode(['success' => false, 'message' => 'Acción inválida: ' . $action]);
    }
} catch (Exception $e) {
    error_log("Error in feria_actions.php: " . $e->getMessage());
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
