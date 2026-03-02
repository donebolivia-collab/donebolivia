<?php
// Simple endpoint JSON: devuelve subcategorías por categoria_id
// Cargar config y funciones con ruta absoluta para evitar problemas en hosting
$dbPath = $_SERVER['DOCUMENT_ROOT'] . '/includes/config/database.php';
if (!file_exists($dbPath)) { $dbPath = __DIR__ . '/../includes/config/database.php'; }
require_once $dbPath;

// Cargar funciones con ruta absoluta para evitar problemas en hosting
$fnPath = $_SERVER['DOCUMENT_ROOT'] . '/includes/functions.php';
if (!file_exists($fnPath)) {
    // fallback relativo
    $fnPath = __DIR__ . '/../includes/functions.php';
}
require_once $fnPath;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    if (!isset($_GET['categoria_id']) || !is_numeric($_GET['categoria_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'categoria_id inválido']);
        exit;
    }

    $categoriaId = (int) $_GET['categoria_id'];

    $db = getDB();
    // Traer nombre de la categoría
    $catStmt = $db->prepare('SELECT nombre FROM categorias WHERE id = ?');
    $catStmt->execute([$categoriaId]);
    $cat = $catStmt->fetch(PDO::FETCH_ASSOC);

    // Cargar todas las subcategorías de esa categoría
    $stmt = $db->prepare('SELECT id, nombre FROM subcategorias WHERE categoria_id = ? ORDER BY nombre ASC');
    $stmt->execute([$categoriaId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Para "Vehículos": ordenar de más largo a más corto, Otros al final
    if ($cat && in_array(strtolower($cat['nombre']), ['vehículos','vehiculos'])) {
        $permitidasOrden = [
            'Motocicletas',   // 12 caracteres
            'Buses/Micros',   // 12 caracteres (con /)
            'Automóviles',    // 11 caracteres
            'Bicicletas',     // 10 caracteres
            'Camionetas',     // 10 caracteres
            'Vagonetas',      // 9 caracteres
            'Camiones',       // 8 caracteres
            'Otros'           // Al final
        ];
        // Normalizar nombres equivalentes
        $rows = array_map(function($r){
            if ($r['nombre'] === 'Otros vehículos') { $r['nombre'] = 'Otros'; }
            return $r;
        }, $rows);
        // Filtrar solo permitidas
        $rows = array_values(array_filter($rows, function($r) use ($permitidasOrden){
            return in_array($r['nombre'], $permitidasOrden, true);
        }));
        // Eliminar duplicados
        $temp = [];
        foreach ($rows as $r) {
            $temp[$r['nombre']] = $r;
        }
        $rows = array_values($temp);
        // Ordenar según array permitidas
        usort($rows, function($a,$b) use ($permitidasOrden){
            return array_search($a['nombre'],$permitidasOrden) <=> array_search($b['nombre'],$permitidasOrden);
        });
    }

    // Para "Dispositivos": plural
    if ($cat && strtolower($cat['nombre']) === 'dispositivos') {
        $permitidasOrden = ['Celulares','Tablets','Relojes','Consolas','Laptops','PCs de escritorio','Otros'];
        $rows = array_values(array_filter($rows, function($r) use ($permitidasOrden){
            return in_array($r['nombre'], $permitidasOrden, true);
        }));
        usort($rows, function($a,$b) use ($permitidasOrden){
            return array_search($a['nombre'],$permitidasOrden) <=> array_search($b['nombre'],$permitidasOrden);
        });
    }

    // Para "Electrodomésticos": plural - orden correcto sin duplicados
    if ($cat && strtolower($cat['nombre']) === 'electrodomésticos') {
        $permitidasOrden = ['Aspiradoras','Cocinas','Lavadoras','Microondas','Refrigeradores','Televisores','Otros'];
        // Normalizar nombres equivalentes
        $rows = array_map(function($r){
            // Unificar "Cocina" (singular) a "Cocinas" (plural)
            if ($r['nombre'] === 'Cocina') { $r['nombre'] = 'Cocinas'; }
            return $r;
        }, $rows);
        // Filtrar solo permitidas
        $rows = array_values(array_filter($rows, function($r) use ($permitidasOrden){
            return in_array($r['nombre'], $permitidasOrden, true);
        }));
        // Eliminar duplicados (por si había "Cocina" Y "Cocinas" en BD)
        $temp = [];
        foreach ($rows as $r) {
            $temp[$r['nombre']] = $r; // La key elimina duplicados automáticamente
        }
        $rows = array_values($temp);
        // Ordenar según array permitidas
        usort($rows, function($a,$b) use ($permitidasOrden){
            return array_search($a['nombre'],$permitidasOrden) <=> array_search($b['nombre'],$permitidasOrden);
        });
    }

    // Para "Herramientas": orden específico con Otros al final
    if ($cat && strtolower($cat['nombre']) === 'herramientas') {
        $permitidasOrden = [
            'Herramientas Manuales',
            'Herramientas Eléctricas',
            'Herramientas Inalámbricas',
            'Herramientas Neumáticas',
            'Medición y Nivelación',
            'Jardinería y Exterior',
            'Seguridad y Protección',
            'Soldadura y Corte',
            'Almacenamiento de Herramientas',
            'Otros'
        ];
        // Normalizar nombres equivalentes
        $rows = array_map(function($r){
            // Normalizar variantes de nombres
            $nombre = $r['nombre'];
            if (stripos($nombre, 'Herramientas eléctricas') !== false) { $r['nombre'] = 'Herramientas Eléctricas'; }
            if (stripos($nombre, 'Herramientas inalámbricas') !== false) { $r['nombre'] = 'Herramientas Inalámbricas'; }
            if (stripos($nombre, 'Herramientas manuales') !== false) { $r['nombre'] = 'Herramientas Manuales'; }
            if (stripos($nombre, 'compresores') !== false || stripos($nombre, 'neumáticas') !== false || stripos($nombre, 'Neumáticas') !== false) {
                $r['nombre'] = 'Herramientas Neumáticas';
            }
            if (stripos($nombre, 'Medición') !== false || stripos($nombre, 'nivelación') !== false) {
                $r['nombre'] = 'Medición y Nivelación';
            }
            if (stripos($nombre, 'Jardinería') !== false) { $r['nombre'] = 'Jardinería y Exterior'; }
            if (stripos($nombre, 'Seguridad') !== false) { $r['nombre'] = 'Seguridad y Protección'; }
            if (stripos($nombre, 'Soldadura') !== false) { $r['nombre'] = 'Soldadura y Corte'; }
            if (stripos($nombre, 'Almacenamiento') !== false) { $r['nombre'] = 'Almacenamiento de Herramientas'; }
            return $r;
        }, $rows);
        // Filtrar solo permitidas
        $rows = array_values(array_filter($rows, function($r) use ($permitidasOrden){
            return in_array($r['nombre'], $permitidasOrden, true);
        }));
        // Eliminar duplicados
        $temp = [];
        foreach ($rows as $r) {
            $temp[$r['nombre']] = $r;
        }
        $rows = array_values($temp);
        // Ordenar según array permitidas
        usort($rows, function($a,$b) use ($permitidasOrden){
            return array_search($a['nombre'],$permitidasOrden) <=> array_search($b['nombre'],$permitidasOrden);
        });
    }

    // Para "Inmuebles": ordenar de más largo a más corto, Otros al final
    if ($cat && strtolower($cat['nombre']) === 'inmuebles') {
        $permitidasOrden = [
            'Departamentos',  // 13 caracteres
            'Habitaciones',   // 12 caracteres
            'Terrenos',       // 8 caracteres
            'Galpones',       // 8 caracteres
            'Oficinas',       // 8 caracteres
            'Locales',        // 7 caracteres
            'Casas',          // 5 caracteres
            'Otros'           // Al final
        ];
        // Filtrar solo permitidas
        $rows = array_values(array_filter($rows, function($r) use ($permitidasOrden){
            return in_array($r['nombre'], $permitidasOrden, true);
        }));
        // Eliminar duplicados
        $temp = [];
        foreach ($rows as $r) {
            $temp[$r['nombre']] = $r;
        }
        $rows = array_values($temp);
        // Ordenar según array permitidas
        usort($rows, function($a,$b) use ($permitidasOrden){
            return array_search($a['nombre'],$permitidasOrden) <=> array_search($b['nombre'],$permitidasOrden);
        });
    }

    // Para "Juguetes": ordenar de más largo a más corto, Otros al final
    if ($cat && strtolower($cat['nombre']) === 'juguetes') {
        $permitidasOrden = [
            'Didácticos',     // 10 caracteres
            'Vehículos',      // 9 caracteres
            'Peluches',       // 8 caracteres
            'Muñecas',        // 7 caracteres
            'Pelotas',        // 7 caracteres
            'Bloques',        // 7 caracteres
            'Acción',         // 6 caracteres
            'Bebés',          // 5 caracteres
            'Mesa',           // 4 caracteres
            'Otros'           // Al final
        ];
        // Normalizar nombres equivalentes
        $rows = array_map(function($r){
            $nombre = $r['nombre'];
            // Normalizar variantes
            if (stripos($nombre, 'Juegos de mesa') !== false) { $r['nombre'] = 'Mesa'; }
            if (stripos($nombre, 'Didáctico') !== false) { $r['nombre'] = 'Didácticos'; }
            if (stripos($nombre, 'Muñeca') !== false) { $r['nombre'] = 'Muñecas'; }
            if (stripos($nombre, 'Vehículo') !== false) { $r['nombre'] = 'Vehículos'; }
            if (stripos($nombre, 'Pelota') !== false) { $r['nombre'] = 'Pelotas'; }
            if (stripos($nombre, 'Bloque') !== false) { $r['nombre'] = 'Bloques'; }
            if (stripos($nombre, 'Acción') !== false) { $r['nombre'] = 'Acción'; }
            if (stripos($nombre, 'Peluche') !== false) { $r['nombre'] = 'Peluches'; }
            if (stripos($nombre, 'Bebé') !== false) { $r['nombre'] = 'Bebés'; }
            return $r;
        }, $rows);
        // Filtrar solo permitidas
        $rows = array_values(array_filter($rows, function($r) use ($permitidasOrden){
            return in_array($r['nombre'], $permitidasOrden, true);
        }));
        // Eliminar duplicados
        $temp = [];
        foreach ($rows as $r) {
            $temp[$r['nombre']] = $r;
        }
        $rows = array_values($temp);
        // Ordenar según array permitidas
        usort($rows, function($a,$b) use ($permitidasOrden){
            return array_search($a['nombre'],$permitidasOrden) <=> array_search($b['nombre'],$permitidasOrden);
        });
    }

    // Para "Muebles": ordenar de más largo a más corto, Otros al final
    if ($cat && strtolower($cat['nombre']) === 'muebles') {
        $permitidasOrden = [
            'Estantes y repisas',  // 17 caracteres
            'Sofás y sillones',    // 15 caracteres
            'Muebles de TV',       // 12 caracteres
            'Escritorios',         // 11 caracteres
            'Colchones',           // 9 caracteres
            'Roperos',             // 7 caracteres
            'Somier',              // 6 caracteres
            'Comedor',             // 7 caracteres
            'Sillas',              // 6 caracteres
            'Mesas',               // 5 caracteres
            'Catres',              // 6 caracteres
            'Otros'                // Al final
        ];
        // Normalizar nombres equivalentes
        $rows = array_map(function($r){
            $nombre = $r['nombre'];
            // Normalizar variantes
            if (stripos($nombre, 'Sofá') !== false || stripos($nombre, 'sillón') !== false) {
                $r['nombre'] = 'Sofás y sillones';
            }
            if (stripos($nombre, 'Estante') !== false || stripos($nombre, 'repisa') !== false) {
                $r['nombre'] = 'Estantes y repisas';
            }
            if (stripos($nombre, 'Escritorio') !== false) { $r['nombre'] = 'Escritorios'; }
            if (stripos($nombre, 'Colchón') !== false || stripos($nombre, 'Colchone') !== false) {
                $r['nombre'] = 'Colchones';
            }
            if (stripos($nombre, 'Ropero') !== false) { $r['nombre'] = 'Roperos'; }
            if (stripos($nombre, 'Somiers') !== false || stripos($nombre, 'Somier') !== false) {
                $r['nombre'] = 'Somier';
            }
            if (stripos($nombre, 'Mesa') !== false && stripos($nombre, 'TV') === false) {
                $r['nombre'] = 'Mesas';
            }
            if (stripos($nombre, 'Silla') !== false) { $r['nombre'] = 'Sillas'; }
            if (stripos($nombre, 'Catre') !== false) { $r['nombre'] = 'Catres'; }
            return $r;
        }, $rows);
        // Filtrar solo permitidas
        $rows = array_values(array_filter($rows, function($r) use ($permitidasOrden){
            return in_array($r['nombre'], $permitidasOrden, true);
        }));
        // Eliminar duplicados
        $temp = [];
        foreach ($rows as $r) {
            $temp[$r['nombre']] = $r;
        }
        $rows = array_values($temp);
        // Ordenar según array permitidas
        usort($rows, function($a,$b) use ($permitidasOrden){
            return array_search($a['nombre'],$permitidasOrden) <=> array_search($b['nombre'],$permitidasOrden);
        });
    }

    // Para "Prendas": ordenar de más largo a más corto, Otros al final (sin Accesorios)
    if ($cat && strtolower($cat['nombre']) === 'prendas') {
        $permitidasOrden = [
            'Sandalias',   // 9 caracteres
            'Tacones',     // 7 caracteres
            'Zapatos',     // 7 caracteres
            'Crocs',       // 5 caracteres
            'Joyas',       // 5 caracteres
            'Tenis',       // 5 caracteres
            'Ropa',        // 4 caracteres
            'Otros'        // Al final
        ];
        // Filtrar solo permitidas (elimina Accesorios)
        $rows = array_values(array_filter($rows, function($r) use ($permitidasOrden){
            return in_array($r['nombre'], $permitidasOrden, true);
        }));
        // Eliminar duplicados
        $temp = [];
        foreach ($rows as $r) {
            $temp[$r['nombre']] = $r;
        }
        $rows = array_values($temp);
        // Ordenar según array permitidas
        usort($rows, function($a,$b) use ($permitidasOrden){
            return array_search($a['nombre'],$permitidasOrden) <=> array_search($b['nombre'],$permitidasOrden);
        });
    }

    echo json_encode(['success' => true, 'data' => $rows]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('API subcategorias error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error del servidor',
        'details' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}
