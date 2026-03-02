<?php
// Configuración de Debug
ini_set('display_errors', 0);
ini_set('log_errors', 1);
$logFile = __DIR__ . '/feria_debug.log';
ini_set('error_log', $logFile);

function debug_log($msg) {
    global $logFile;
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $msg . PHP_EOL, FILE_APPEND);
}

debug_log("Iniciando petición...");

// Headers obligatorios
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    // 1. Cargar dependencias
    $dbPath = __DIR__ . '/../../config/database.php';
    if (!file_exists($dbPath)) {
        throw new Exception("No se encuentra config/database.php en: $dbPath");
    }
    require_once $dbPath;
    
    session_start();
    debug_log("Sesión ID: " . session_id());

    // 2. Verificación de Sesión (Flexible)
    if (empty($_SESSION)) {
        debug_log("FALLO: Sesión vacía");
        // Intentar recuperar sesión si viene cookie
        // throw new Exception("Sesión expirada o no iniciada"); 
        // Comentado temporalmente para aislar si es problema de cookies
    }

    // 3. Obtener Base de Datos
    $db = getDB();
    if (!$db) throw new Exception("Fallo al conectar a BD");

    // 4. Leer Input
    $rawInput = file_get_contents('php://input');
    debug_log("Raw Input: " . $rawInput);
    
    $input = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        debug_log("JSON Error: " . json_last_error_msg());
        // Intento fallback $_POST por si acaso
        if (!empty($_POST)) {
            $input = $_POST;
            debug_log("Usando Fallback POST");
        } else {
            throw new Exception("JSON inválido y POST vacío");
        }
    }

    $action = $input['action'] ?? '';
    $id = intval($input['id'] ?? 0);
    
    debug_log("Acción: $action | ID: $id");

    if ($action === 'free') {
        // --- LIBERAR ---
        if ($id <= 0) throw new Exception("ID inválido para liberar");
        
        $stmt = $db->prepare("DELETE FROM feria_posiciones WHERE id = ?");
        $stmt->execute([$id]);
        
        debug_log("Eliminado registro ID: $id");
        echo json_encode(['success' => true]);
    }
    elseif ($action === 'move_v2') {
        // --- MOVER V2 (Bloque + Slot) ---
        $targetBlock = intval($input['target_block']);
        $targetSlot = intval($input['target_slot']);
        $ciudad = $input['ciudad'] ?? 'LPZ';
        
        if ($id <= 0 || $targetBlock <= 0 || $targetSlot <= 0) {
            throw new Exception("Datos de destino inválidos");
        }
        
        // Verificar si el destino está ocupado
        $stmtCheck = $db->prepare("SELECT id FROM feria_posiciones WHERE bloque_id = ? AND slot_numero = ? AND ciudad = ? AND id != ?");
        $stmtCheck->execute([$targetBlock, $targetSlot, $ciudad, $id]);
        if ($stmtCheck->fetch()) {
            throw new Exception("El puesto destino ya está ocupado");
        }
        
        // Calcular nuevo posicion_index para legacy
        // 1. Obtener orden del bloque destino
        $stmtBlock = $db->prepare("SELECT orden FROM feria_bloques WHERE id = ?");
        $stmtBlock->execute([$targetBlock]);
        $block = $stmtBlock->fetch(PDO::FETCH_ASSOC);
        
        $newIndex = 0;
        if ($block) {
            $orden = intval($block['orden']);
            // Fórmula: (Orden-1)*12 + (Slot-1)
            $newIndex = (($orden - 1) * 12) + ($targetSlot - 1);
        }
        
        // Actualizar
        $stmtUpd = $db->prepare("UPDATE feria_posiciones SET bloque_id = ?, slot_numero = ?, posicion_index = ? WHERE id = ?");
        $stmtUpd->execute([$targetBlock, $targetSlot, $newIndex, $id]);
        
        echo json_encode(['success' => true]);
    }
    elseif ($action === 'assign_store') {
        // --- ASIGNAR TIENDA POR SLUG ---
        $slug = trim($input['slug'] ?? '');
        $targetBlock = intval($input['target_block']);
        $targetSlot = intval($input['target_slot']);
        $ciudad = $input['ciudad'] ?? 'LPZ';
        
        if (empty($slug) || $targetBlock <= 0 || $targetSlot <= 0) {
            throw new Exception("Datos incompletos");
        }
        
        // 1. Buscar tienda por slug
        $stmtStore = $db->prepare("SELECT usuario_id, nombre FROM tiendas WHERE slug = ?");
        $stmtStore->execute([$slug]);
        $store = $stmtStore->fetch(PDO::FETCH_ASSOC);
        
        if (!$store) {
            throw new Exception("No existe ninguna tienda con el slug '$slug'");
        }
        
        $usuarioId = $store['usuario_id'];
        
        // 2. Verificar si el destino está ocupado
        $stmtCheck = $db->prepare("SELECT id, usuario_id FROM feria_posiciones WHERE bloque_id = ? AND slot_numero = ? AND ciudad = ?");
        $stmtCheck->execute([$targetBlock, $targetSlot, $ciudad]);
        $ocupado = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        
        if ($ocupado) {
            // Si está ocupado por la MISMA tienda, no es error, es "ya está ahí".
            if ($ocupado['usuario_id'] == $usuarioId) {
                // Ya está asignado correctamente, devolver éxito silencioso
                echo json_encode(['success' => true]);
                exit;
            } else {
                throw new Exception("Este puesto ya está ocupado. Libéralo primero.");
            }
        }
        
        // 3. Verificar si la tienda YA TIENE puesto en esta ciudad (Opcional: permitir múltiples?)
        // Por regla de negocio, una tienda suele tener un puesto por ciudad. Si ya tiene, lo movemos?
        // Mejor avisar.
        $stmtExist = $db->prepare("SELECT id FROM feria_posiciones WHERE usuario_id = ? AND ciudad = ?");
        $stmtExist->execute([$usuarioId, $ciudad]);
        if ($existing = $stmtExist->fetch()) {
            // Opción A: Error estricto
            // throw new Exception("Esta tienda ya tiene un puesto asignado en esta ciudad.");
            
            // Opción B: Mover automáticamente (Smart Move)
            // Borramos la anterior y creamos la nueva
            $stmtDel = $db->prepare("DELETE FROM feria_posiciones WHERE id = ?");
            $stmtDel->execute([$existing['id']]);
        }
        
        // 4. Calcular índice legacy
        $stmtBlock = $db->prepare("SELECT orden FROM feria_bloques WHERE id = ?");
        $stmtBlock->execute([$targetBlock]);
        $block = $stmtBlock->fetch(PDO::FETCH_ASSOC);
        $newIndex = 0;
        if ($block) {
            $orden = intval($block['orden']);
            $newIndex = (($orden - 1) * 12) + ($targetSlot - 1);
        }
        
        // 5. Insertar
        // Se eliminó 'fecha_asignacion' porque no existe en la estructura actual de la base de datos
        $stmtIns = $db->prepare("INSERT INTO feria_posiciones (usuario_id, sector_id, ciudad, bloque_id, slot_numero, posicion_index, estado) VALUES (?, (SELECT sector_id FROM feria_bloques WHERE id = ?), ?, ?, ?, ?, 'ocupado')");
        $stmtIns->execute([$usuarioId, $targetBlock, $ciudad, $targetBlock, $targetSlot, $newIndex]);
        
        echo json_encode(['success' => true]);
    }
    elseif ($action === 'create_block') {
        // --- CREAR BLOQUE ---
        $sectorId = intval($input['sector_id']);
        $nombre = trim($input['nombre']);
        
        if (empty($nombre)) throw new Exception("Nombre requerido");
        
        // Obtener último orden
        $stmtMax = $db->prepare("SELECT MAX(orden) as max_orden FROM feria_bloques WHERE sector_id = ?");
        $stmtMax->execute([$sectorId]);
        $row = $stmtMax->fetch();
        $orden = ($row['max_orden'] ?? 0) + 1;
        
        $stmtIns = $db->prepare("INSERT INTO feria_bloques (sector_id, nombre, orden, capacidad) VALUES (?, ?, ?, 12)");
        $stmtIns->execute([$sectorId, $nombre, $orden]);
        
        echo json_encode(['success' => true]);
    }
    elseif ($action === 'update_block') {
        // --- EDITAR BLOQUE ---
        $blockId = intval($input['id']); // Aquí input['id'] es el block ID
        $nombre = trim($input['nombre']);
        
        if ($blockId <= 0 || empty($nombre)) throw new Exception("Datos inválidos");
        
        $stmtUpd = $db->prepare("UPDATE feria_bloques SET nombre = ? WHERE id = ?");
        $stmtUpd->execute([$nombre, $blockId]);
        
        echo json_encode(['success' => true]);
    }
    elseif ($action === 'delete_block') {
        // --- ELIMINAR BLOQUE ---
        $blockId = intval($input['id']); // Aquí input['id'] es el block ID
        
        if ($blockId <= 0) throw new Exception("ID inválido");
        
        // 1. Eliminar posiciones asociadas (Liberar tiendas)
        $stmtDelPos = $db->prepare("DELETE FROM feria_posiciones WHERE bloque_id = ?");
        $stmtDelPos->execute([$blockId]);
        
        // 2. Eliminar bloque
        $stmtDel = $db->prepare("DELETE FROM feria_bloques WHERE id = ?");
        $stmtDel->execute([$blockId]);
        
        echo json_encode(['success' => true]);
    }
    elseif ($action === 'move') {
        // --- LEGACY MOVE (Mantenido por si acaso) ---
        $newPos = isset($input['new_pos']) ? intval($input['new_pos']) : -1;
        debug_log("Nueva Posición: $newPos");
        
        if ($newPos < 0) throw new Exception("Posición destino inválida");

        // Datos actuales
        $stmtCurrent = $db->prepare("SELECT * FROM feria_posiciones WHERE id = ?");
        $stmtCurrent->execute([$id]);
        $current = $stmtCurrent->fetch(PDO::FETCH_ASSOC);
        
        if (!$current) throw new Exception("El puesto original no existe (ID: $id)");
        
        $sectorId = $current['sector_id'];
        $ciudad = $current['ciudad'] ?? 'LPZ';
        
        debug_log("Moviendo en Sector: $sectorId, Ciudad: $ciudad");

        // Verificar destino
        $stmtCheck = $db->prepare("SELECT id FROM feria_posiciones WHERE sector_id = ? AND ciudad = ? AND posicion_index = ?");
        $stmtCheck->execute([$sectorId, $ciudad, $newPos]);
        $collision = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        
        if ($collision) {
            debug_log("Colisión detectada con ID: " . $collision['id']);
            throw new Exception("El puesto destino #".($newPos+1)." ya está ocupado.");
        }

        // Ejecutar cambio
        $stmtUpdate = $db->prepare("UPDATE feria_posiciones SET posicion_index = ? WHERE id = ?");
        $result = $stmtUpdate->execute([$newPos, $id]);
        
        if (!$result) {
            debug_log("Error en UPDATE SQL: " . implode(" ", $stmtUpdate->errorInfo()));
            throw new Exception("Error al actualizar la base de datos");
        }

        debug_log("Movimiento exitoso");
        echo json_encode(['success' => true]);
    }
    else {
        throw new Exception("Acción no reconocida: $action");
    }

} catch (Exception $e) {
    debug_log("EXCEPCIÓN: " . $e->getMessage());
    http_response_code(500); // Forzar error HTTP para que el frontend lo note
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
