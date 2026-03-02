<?php
/**
 * API Backend: Feria Editor (Gestión de Ubicaciones)
 * Permite a los usuarios seleccionar y gestionar la posición de su tienda en la Feria Virtual.
 */

// 1. Configuración y Seguridad
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Verificar sesión
if (!estaLogueado()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$action = $_GET['action'] ?? '';
$usuario_id = $_SESSION['usuario_id'];
$db = getDB();

try {
    switch ($action) {
        
        // --- 1. Obtener Sectores Disponibles ---
        case 'get_sectores':
            $stmt = $db->query("SELECT id, titulo, color_hex FROM feria_sectores WHERE activo = 1 ORDER BY orden ASC");
            $sectores = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $sectores]);
            break;

        // --- 2. Obtener Bloques de un Sector ---
        case 'get_bloques':
            $sector_id = intval($_GET['sector_id'] ?? 0);
            if ($sector_id <= 0) throw new Exception("Sector inválido");

            $stmt = $db->prepare("SELECT id, nombre, capacidad, orden FROM feria_bloques WHERE sector_id = ? ORDER BY orden ASC");
            $stmt->execute([$sector_id]);
            $bloques = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $bloques]);
            break;

        // --- 3. Obtener Estado de Puestos en un Bloque (Grid) ---
        case 'get_puestos':
            $bloque_id = intval($_GET['bloque_id'] ?? 0);
            $ciudad = $_GET['ciudad'] ?? 'LPZ'; // Default La Paz

            if ($bloque_id <= 0) throw new Exception("Bloque inválido");

            // Obtener capacidad del bloque
            $stmtCap = $db->prepare("SELECT capacidad FROM feria_bloques WHERE id = ?");
            $stmtCap->execute([$bloque_id]);
            $bloque = $stmtCap->fetch(PDO::FETCH_ASSOC);
            $capacidad = $bloque ? intval($bloque['capacidad']) : 12;

            // Obtener puestos ocupados (con info básica de la tienda para mostrar logos)
            $stmtPos = $db->prepare("
                SELECT p.slot_numero, p.usuario_id, t.nombre as tienda_nombre, t.logo as tienda_logo
                FROM feria_posiciones p
                LEFT JOIN tiendas t ON p.usuario_id = t.usuario_id
                WHERE p.bloque_id = ? AND p.ciudad = ?
            ");
            $stmtPos->execute([$bloque_id, $ciudad]);
            $ocupados = [];
            while ($row = $stmtPos->fetch(PDO::FETCH_ASSOC)) {
                $ocupados[$row['slot_numero']] = $row;
            }

            // Construir respuesta estructurada para el grid
            $slots = [];
            for ($i = 1; $i <= $capacidad; $i++) {
                if (isset($ocupados[$i])) {
                    $isMyStore = ($ocupados[$i]['usuario_id'] == $usuario_id);
                    $slots[] = [
                        'numero' => $i,
                        'estado' => $isMyStore ? 'propio' : 'ocupado',
                        'tienda' => [
                            'nombre' => $ocupados[$i]['tienda_nombre'] ?? 'Tienda',
                            'logo' => !empty($ocupados[$i]['tienda_logo']) ? '/uploads/logos/' . $ocupados[$i]['tienda_logo'] . '?v=' . time() : null
                        ]
                    ];
                } else {
                    $slots[] = [
                        'numero' => $i,
                        'estado' => 'libre',
                        'tienda' => null
                    ];
                }
            }

            echo json_encode(['success' => true, 'data' => $slots]);
            break;

        // --- 4. Asignar Tienda a un Puesto (Move & Assign) ---
        case 'assign_puesto':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Método inválido");
            
            $input = json_decode(file_get_contents('php://input'), true);
            $ciudad = $input['ciudad'] ?? '';
            $sector_id = intval($input['sector_id'] ?? 0);
            $bloque_id = intval($input['bloque_id'] ?? 0);
            $slot_numero = intval($input['slot_numero'] ?? 0);

            if (empty($ciudad) || $sector_id <= 0 || $bloque_id <= 0 || $slot_numero <= 0) {
                throw new Exception("Datos incompletos");
            }

            $db->beginTransaction();

            try {
                // A. Verificar si el puesto destino está realmente libre
                $stmtCheck = $db->prepare("SELECT id FROM feria_posiciones WHERE bloque_id = ? AND slot_numero = ? AND ciudad = ? FOR UPDATE");
                $stmtCheck->execute([$bloque_id, $slot_numero, $ciudad]);
                if ($stmtCheck->fetch()) {
                    throw new Exception("El puesto seleccionado ya está ocupado por otra tienda.");
                }

                // B. Eliminar mi posición anterior (si existe) en CUALQUIER lugar de la feria
                // Política: Una tienda = Un puesto único en toda la feria (para evitar duplicados masivos)
                $stmtDel = $db->prepare("DELETE FROM feria_posiciones WHERE usuario_id = ?");
                $stmtDel->execute([$usuario_id]);

                // C. Insertar nueva posición
                $stmtIns = $db->prepare("
                    INSERT INTO feria_posiciones (usuario_id, sector_id, bloque_id, slot_numero, ciudad, estado)
                    VALUES (?, ?, ?, ?, ?, 'ocupado')
                ");
                $stmtIns->execute([$usuario_id, $sector_id, $bloque_id, $slot_numero, $ciudad]);

                // D. Actualizar metadatos en tabla tiendas (para búsquedas rápidas)
                // Obtener slug del sector para guardar en 'categoria' (legacy compatibility)
                /* 
                   Nota: En el sistema legacy, 'categoria' en tiendas es un VARCHAR (slug), no ID.
                   Hacemos un mapeo rápido o consulta.
                */
                $stmtSlug = $db->prepare("SELECT slug FROM feria_sectores WHERE id = ?");
                $stmtSlug->execute([$sector_id]);
                $sectorData = $stmtSlug->fetch(PDO::FETCH_ASSOC);
                $categoriaSlug = $sectorData ? $sectorData['slug'] : 'varios';

                $stmtUpdateStore = $db->prepare("UPDATE tiendas SET ciudad = ?, categoria = ? WHERE usuario_id = ?");
                $stmtUpdateStore->execute([$ciudad, $categoriaSlug, $usuario_id]);

                $db->commit();
                echo json_encode(['success' => true, 'message' => '¡Tienda ubicada correctamente!']);

            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        // --- 5. Obtener Ubicación Actual (Para pre-cargar selects) ---
        case 'get_my_location':
            $stmt = $db->prepare("
                SELECT p.ciudad, p.sector_id, p.bloque_id, p.slot_numero, s.titulo as sector_nombre, b.nombre as bloque_nombre
                FROM feria_posiciones p
                JOIN feria_sectores s ON p.sector_id = s.id
                JOIN feria_bloques b ON p.bloque_id = b.id
                WHERE p.usuario_id = ? LIMIT 1
            ");
            $stmt->execute([$usuario_id]);
            $location = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($location) {
                echo json_encode(['success' => true, 'data' => $location]);
            } else {
                echo json_encode(['success' => true, 'data' => null]); // No tiene ubicación
            }
            break;

        default:
            throw new Exception("Acción no válida");
    }

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
