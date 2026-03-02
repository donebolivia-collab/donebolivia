<?php
/**
 * DETALLE DE SECTOR - ARQUITECTURA RELACIONAL V2 (DRY & OPTIMIZED)
 * Basada en Bloques Dinámicos (feria_bloques) y Posiciones Relacionales.
 */
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';

// --- 1. GESTIÓN DE CONTEXTO ---
$slug = $_GET['slug'] ?? '';
$current_dept_code = $_GET['dept'] ?? ($_COOKIE['done_dept'] ?? 'LPZ');

// Validación básica de ciudad
$valid_depts = ['LPZ', 'ALT', 'SCZ', 'CBA', 'ORU', 'PTS', 'TJA', 'CHQ', 'BEN', 'PND'];
if (!in_array($current_dept_code, $valid_depts)) $current_dept_code = 'LPZ';

if (isset($_GET['dept'])) setcookie('done_dept', $current_dept_code, time() + 86400 * 30, "/");

$departments_names = [
    'LPZ' => 'La Paz', 'ALT' => 'El Alto', 'SCZ' => 'Santa Cruz', 'CBA' => 'Cochabamba',
    'ORU' => 'Oruro', 'PTS' => 'Potosí', 'TJA' => 'Tarija', 'CHQ' => 'Chuquisaca',
    'BEN' => 'Beni', 'PND' => 'Pando'
];
$dept_name = $departments_names[$current_dept_code] ?? 'Bolivia';

if (empty($slug)) { header("Location: /feria.php"); exit; }

$db = getDB();

// --- 2. RECUPERACIÓN DE DATOS ESTRUCTURADOS ---

// A. Sector
$stmt = $db->prepare("SELECT * FROM feria_sectores WHERE slug = ? AND activo = 1 LIMIT 1");
$stmt->execute([$slug]);
$sector = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sector) {
    echo "<div class='container py-5 text-center'><h1>Sector no encontrado</h1><a href='/feria.php' class='btn btn-primary'>Volver</a></div>";
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Optimización de Imagen de Sector (DRY: Usando getBannerImage de functions.php)
$global_aliases = [
    'tech' => ['sector_celulares', 'dispositivos'],
    'fashion' => ['sector_ropa', 'prendas'],
    'home' => ['sector_muebles', 'muebles'],
    'toys' => ['sector_juguetes', 'juguetes'],
    'tools' => ['sector_herramientas', 'herramientas'],
    'auto' => ['sector_vehiculos', 'vehiculos'],
    'electro' => ['sector_electro', 'electrodomesticos'],
    'realestate' => ['sector_inmuebles', 'inmuebles']
];
$aliases = isset($global_aliases[$slug]) ? $global_aliases[$slug] : [];
if (!empty($sector['imagen_banner'])) {
    $db_filename = pathinfo($sector['imagen_banner'], PATHINFO_FILENAME);
    array_unshift($aliases, $db_filename);
}
// Sobrescribir imagen con la detectada en disco
$sector['imagen_banner'] = getBannerImage($slug, $aliases);


// B. Bloques del Sector (Ordenados)
$stmtB = $db->prepare("SELECT * FROM feria_bloques WHERE sector_id = ? ORDER BY orden ASC");
$stmtB->execute([$sector['id']]);
$bloques = $stmtB->fetchAll(PDO::FETCH_ASSOC);

// C. Tiendas (Posiciones Ocupadas)
// REFACTORIZACIÓN: Usar JOIN con tabla tiendas para obtener datos reales
// Evitamos leer columnas redundantes (tienda_nombre, tienda_logo, tienda_url) de feria_posiciones
$sql = "SELECT 
            p.*, 
            t.nombre as nombre, 
            t.slug as slug, 
            t.logo as logo,
            b.nombre as bloque_nombre
        FROM feria_posiciones p
        LEFT JOIN tiendas t ON p.usuario_id = t.usuario_id
        LEFT JOIN feria_bloques b ON p.bloque_id = b.id
        WHERE p.sector_id = ? AND p.ciudad = ?";
$stmtP = $db->prepare($sql);
$stmtP->execute([$sector['id'], $current_dept_code]);
$raw_positions = $stmtP->fetchAll(PDO::FETCH_ASSOC);

// Mapeo: [bloque_id][slot_numero] => datos_tienda
$stores_map = [];
foreach ($raw_positions as $pos) {
    // Normalización de Logo (DRY: Usando processStoreLogo de functions.php)
    $img_url = processStoreLogo($pos['logo']);
    
    // Casting estricto a INT para evitar problemas de índices string
    $bid = intval($pos['bloque_id']);
    $snum = intval($pos['slot_numero']);

    // REINGENIERÍA V2: SELF-HEALING DE MAPEO (Legacy Fallback)
    if ($bid === 0 && isset($pos['posicion_index'])) {
        $idx = intval($pos['posicion_index']); // 0-based global index
        
        // Calcular Orden del Bloque (1-based)
        $target_order = floor($idx / 12) + 1;
        
        // Calcular Slot (1-based)
        $snum = ($idx % 12) + 1;
        
        // Buscar el ID real del bloque que corresponde a este orden
        foreach ($bloques as $b) {
            if (intval($b['orden']) === $target_order) {
                $bid = intval($b['id']);
                break;
            }
        }
        
        // Si no encontramos bloque (caso raro), asignamos al primero como fallback
        if ($bid === 0 && !empty($bloques)) {
             $bid = intval($bloques[0]['id']);
        }
    }
    
    $stores_map[$bid][$snum] = [
        'nombre' => $pos['nombre'] ?: 'Tienda',
        'slug'   => $pos['slug'] ?: '#',
        'logo'   => $img_url
    ];
}
?>

<link href="/assets/css/feria.css?v=<?php echo filemtime(__DIR__ . '/assets/css/feria.css'); ?>" rel="stylesheet">
<style>
    /* Estilos específicos mínimos que no vale la pena extraer si son únicos de esta vista */
    .feria-mode body { background: #f8f9fa; }
    .sector-header-split { height: 140px; flex-shrink: 0; background: white; }
    .split-text-col { width: 50%; padding: 20px 24px; display: flex; flex-direction: column; justify-content: center; }
    .split-image-col { width: 50%; position: relative; overflow: hidden; }
    .image-box { width: 100%; height: 100%; position: relative; overflow: hidden; background: #eff1f3; }
    .stores-inner-grid-wrapper { padding: 0 24px 24px 24px; flex-grow: 1; display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; padding-top: 24px; }
</style>
<script>document.body.classList.add('feria-mode');</script>

<div class="feria-layout">
    
    <!-- Header Contexto -->
    <div class="d-flex justify-content-between align-items-center mb-4 px-2">
        <div>
            <h1 class="h3 fw-bold mb-1" style="color: var(--text-primary);">
                <i class="fas fa-map-marker-alt text-danger me-2"></i><?php echo htmlspecialchars($dept_name); ?>
            </h1>
            <p class="text-muted mb-0">
                Sector: <strong style="color: <?php echo htmlspecialchars($sector['color_hex']); ?>"><?php echo htmlspecialchars($sector['titulo']); ?></strong>
            </p>
        </div>
        <a href="/feria.php?dept=<?php echo $current_dept_code; ?>" class="btn btn-outline-dark rounded-pill px-4">
            <i class="fas fa-arrow-left me-2"></i> Volver
        </a>
    </div>

    <!-- GRID DE BLOQUES -->
    <div class="bento-grid" style="grid-template-columns: repeat(auto-fill, minmax(420px, 1fr)); gap: 24px;">
        
        <?php if (empty($bloques)): ?>
            <div class="col-12 text-center py-5">
                <p class="text-muted">No hay bloques definidos para este sector.</p>
            </div>
        <?php endif; ?>

        <?php foreach ($bloques as $bloque): 
            $b_id = intval($bloque['id']);
            $capacidad = intval($bloque['capacidad']) ?: 12;
            $count = isset($stores_map[$b_id]) ? count($stores_map[$b_id]) : 0;
        ?>
            <div class="sector-block" style="--sector-color: <?php echo htmlspecialchars($sector['color_hex']); ?>; display: flex; flex-direction: column;">
                
                <!-- Header Bloque -->
                <div class="sector-header-split">
                    <div class="split-text-col">
                        <h2 class="sector-title-pro"><?php echo htmlspecialchars($bloque['nombre']); ?></h2>
                        <p class="sector-desc-pro"><?php echo $count; ?> tiendas aquí</p>
                    </div>
                    <div class="split-image-col">
                        <div class="image-box">
                             <?php 
                             $bloque_img = !empty($sector['imagen_banner']) ? $sector['imagen_banner'] : '';
                             ?>
                             
                             <?php if (!empty($bloque_img)): ?>
                                <img src="<?php echo htmlspecialchars($bloque_img); ?>" 
                                     alt="Sector" 
                                     style="width: 100%; height: 100%; object-fit: cover; display: block; filter: brightness(1.02) contrast(1.02); mix-blend-mode: multiply;">
                             <?php else: ?>
                                <div style="width:100%; height:100%; background: linear-gradient(135deg, <?php echo htmlspecialchars($sector['color_hex']); ?> 0%, #ffffff 100%); opacity: 0.8;"></div>
                             <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Grid de Tiendas -->
                <div class="stores-inner-grid-wrapper">
                    <?php for ($i = 1; $i <= $capacidad; $i++): 
                        $store = null;
                        if (isset($stores_map[$b_id]) && array_key_exists($i, $stores_map[$b_id])) {
                            $store = $stores_map[$b_id][$i];
                        }
                    ?>
                        <?php if ($store): ?>
                            <!-- TIENDA OCUPADA -->
                            <a href="/tienda/<?php echo htmlspecialchars($store['slug']); ?>"  
                               class="store-item real" 
                               target="_blank"
                               title="<?php echo htmlspecialchars($store['nombre']); ?>">
                                
                                <div class="store-logo-wrap">
                                    <?php if (empty($store['logo']) || $store['logo'] === '/assets/img/default-store.png'): ?>
                                        <i class="fas fa-store" style="font-size: 48px; color: #e5e5e5;"></i>
                                    <?php else: ?>
                                        <img src="<?php echo htmlspecialchars($store['logo']) . '?v=' . time(); ?>" 
                                             class="store-img" 
                                             alt="Logo Tienda"
                                             loading="lazy">
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php else: ?>
                            <!-- ESPACIO LIBRE -->
                            <div class="store-item empty" style="cursor: default;">
                                <span class="empty-text">LIBRE</span>
                            </div>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>

            </div>
        <?php endforeach; ?>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
