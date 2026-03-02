<?php
// API ESTRUCTURAL - REINGENIERÍA V2
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

$sector_slug = $_GET['sector'] ?? '';
$city = $_GET['city'] ?? 'SCZ';

if (!$sector_slug) {
    echo json_encode(['error' => 'Falta sector']);
    exit;
}

try {
    $db = getDB();

    // 1. Obtener Sector
    $stmtSec = $db->prepare("SELECT id FROM feria_sectores WHERE slug = ? AND activo = 1 ORDER BY id DESC LIMIT 1");
    $stmtSec->execute([$sector_slug]);
    $sector = $stmtSec->fetch(PDO::FETCH_ASSOC);

    if (!$sector) {
        echo json_encode(['error' => 'Sector no encontrado']);
        exit;
    }

    $sector_id = $sector['id'];

    // 2. Obtener BLOQUES del Sector (Jerarquía)
    $stmtBloques = $db->prepare("SELECT id, nombre, orden, capacidad FROM feria_bloques WHERE sector_id = ? ORDER BY orden ASC");
    $stmtBloques->execute([$sector_id]);
    $bloques = $stmtBloques->fetchAll(PDO::FETCH_ASSOC);

    // Si no hay bloques (caso raro post-migración), devolver estructura vacía o error controlado
    if (empty($bloques)) {
        // Fallback temporal si la migración no corrió
        $bloques = []; 
    }

    // 3. Obtener OCUPACIÓN (Tiendas)
    // REINGENIERÍA: Recuperación Robusta
    // Obtenemos TODO del sector y ciudad, luego mapeamos inteligentemente.
    $stmtPos = $db->prepare("
        SELECT 
            p.bloque_id,
            p.slot_numero,
            p.posicion_index,
            p.estado,
            p.usuario_id,
            COALESCE(t.nombre, p.tienda_nombre) as nombre,
            COALESCE(t.slug, p.tienda_url) as slug,
            CASE WHEN t.id IS NOT NULL THEN t.logo ELSE p.tienda_logo END as logo
        FROM feria_posiciones p
        LEFT JOIN tiendas t ON p.usuario_id = t.usuario_id
        WHERE p.sector_id = ? AND p.ciudad = ? AND p.estado = 'ocupado'
    ");
    $stmtPos->execute([$sector_id, $city]);
    $ocupados = $stmtPos->fetchAll(PDO::FETCH_ASSOC);

    // MAPEO INTELIGENTE (Fix "Tienda Invisible")
    // Si bloque_id es NULL, lo calculamos basado en posicion_index
    $mapa = [];
    
    // Mapeo auxiliar de OrdenBloque -> BloqueID
    $ordenToId = [];
    foreach ($bloques as $b) $ordenToId[$b['orden']] = $b['id'];

    foreach ($ocupados as $oc) {
        $b_id = $oc['bloque_id'];
        $s_num = $oc['slot_numero'];

        // Fallback: Calcular si falta data relacional
        if (!$b_id || !$s_num) {
            $idx = intval($oc['posicion_index']);
            $orden = floor($idx / 12) + 1; // Asumiendo cap 12
            $s_num = ($idx % 12) + 1;
            $b_id = $ordenToId[$orden] ?? null;
        }

        if ($b_id && $s_num) {
            $mapa[$b_id][$s_num] = $oc;
        }
    }

    // 4. Construir Respuesta Jerárquica
    $structure = [];
    foreach ($bloques as $b) {
        $b_id = $b['id'];
        $slots = [];
        
        // Generar slots 1..Capacidad
        for ($i = 1; $i <= $b['capacidad']; $i++) {
            if (isset($mapa[$b_id][$i])) {
                $info = $mapa[$b_id][$i];
                $slots[] = [
                    'numero' => $i,
                    'status' => 'occupied',
                    'info'   => [
                        'nombre'     => $info['nombre'],
                        'slug'       => $info['slug'],
                        'usuario_id' => $info['usuario_id'],
                        'logo'       => $info['logo']
                    ]
                ];
            } else {
                $slots[] = [
                    'numero' => $i,
                    'status' => 'free'
                ];
            }
        }

        $structure[] = [
            'id'     => $b['id'],
            'nombre' => $b['nombre'],
            'orden'  => $b['orden'],
            'slots'  => $slots
        ];
    }

    echo json_encode(['structure' => $structure]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>