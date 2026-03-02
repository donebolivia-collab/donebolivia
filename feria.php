<?php
/**
 * FERIA VIRTUAL DONE! - BENTO GRID EDITION
 * Organización moderna en bloques temáticos.
 */
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';

// 1. LÓGICA DE DEPARTAMENTO (Persistencia)
$departments = [
    'LPZ' => 'La Paz', 'ALT' => 'El Alto', 'SCZ' => 'Santa Cruz', 'CBA' => 'Cochabamba',
    'ORU' => 'Oruro', 'PTS' => 'Potosí', 'TJA' => 'Tarija', 'CHQ' => 'Chuquisaca',
    'BEN' => 'Beni', 'PND' => 'Pando'
];

$current_dept_code = isset($_GET['dept']) ? $_GET['dept'] : (isset($_COOKIE['done_dept']) ? $_COOKIE['done_dept'] : 'LPZ');
if (!array_key_exists($current_dept_code, $departments)) $current_dept_code = 'LPZ';

if (isset($_GET['dept'])) setcookie('done_dept', $current_dept_code, time() + (86400 * 30), "/");

$current_dept_name = $departments[$current_dept_code];

// 2. OBTENER PUESTOS OCUPADOS
$occupied_slots = [];
$user_has_store = false;
$user_store_data = null;

if (isset($_SESSION['usuario_id'])) {
    try {
        $db = getDB();
        $stmtUser = $db->prepare("SELECT id, nombre, logo FROM tiendas WHERE usuario_id = ? LIMIT 1");
        $stmtUser->execute([$_SESSION['usuario_id']]);
        $user_store_data = $stmtUser->fetch(PDO::FETCH_ASSOC);
        if ($user_store_data) $user_has_store = true;
    } catch (Exception $e) {}
}

try {
    $db = getDB();
    // REFACTORIZACIÓN: Usar JOIN con tabla tiendas para obtener datos reales
    // Evitamos leer columnas redundantes (tienda_nombre, tienda_logo, tienda_url) de feria_posiciones
    // CORRECCIÓN FINAL: Usar 'slot_numero' en lugar de 'posicion_index' (legacy) para ubicar la tienda
    $sql = "SELECT 
                p.slot_numero, 
                p.estado,
                s.slug as sector_slug,
                s.capacidad,
                t.nombre as tienda_nombre,
                t.slug as tienda_url,
                t.logo as tienda_logo
            FROM feria_posiciones p
            JOIN feria_sectores s ON p.sector_id = s.id
            LEFT JOIN tiendas t ON p.usuario_id = t.usuario_id
            WHERE p.ciudad = :ciudad 
              AND p.estado = 'ocupado'"; // Eliminado filtro activo=1 para consistencia con sector_detalle

    $stmt = $db->prepare($sql);
    $stmt->execute([':ciudad' => $current_dept_code]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results as $row) {
        $img_url = null; // Por defecto NULL para mostrar ícono
        if (!empty($row['tienda_logo'])) {
            $clean_logo = preg_replace('/\?.*/', '', $row['tienda_logo']);
            if (strpos($clean_logo, 'http') === 0) {
                $img_url = $clean_logo;
            } else {
                if (strpos($clean_logo, '/') !== 0) $clean_logo = '/uploads/logos/' . $clean_logo;
                $phys_path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $_SERVER['DOCUMENT_ROOT'] . $clean_logo);
                if (file_exists($phys_path)) $img_url = $clean_logo; 
            }
        }
        
        // AJUSTE CRÍTICO: slot_numero es 1-based (1..12), pero el array de renderizado es 0-based (0..11)
        // Restamos 1 para alinear.
        $idx = intval($row['slot_numero']) - 1;
        if ($idx >= 0) {
            $occupied_slots[$row['sector_slug']][$idx] = [
                'name' => $row['tienda_nombre'],
                'url'  => '/tienda/' . $row['tienda_url'],
                'img'  => $img_url
            ];
        }
    }
} catch (Exception $e) {}

// DATOS DE SECTORES
$sectores = [];
try {
    $db = getDB();
    $stmtSectores = $db->query("SELECT * FROM feria_sectores WHERE activo = 1 ORDER BY orden ASC");
    $dbSectores = $stmtSectores->fetchAll(PDO::FETCH_ASSOC);

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

    foreach ($dbSectores as $row) {
        $slug = $row['slug'];
        $aliases = isset($global_aliases[$slug]) ? $global_aliases[$slug] : [];
        $dbImage = $row['imagen_banner'] ?? null;
        
        $sectores[$slug] = [
            'title' => $row['titulo'],
            'desc' => $row['descripcion'],
            'color' => $row['color_hex'],
            'image' => getBannerImage($slug, $aliases, $dbImage), // Función global optimizada
            'capacity' => $row['capacidad']
        ];
    }
} catch (Exception $e) {}

// Fallback hardcoded si DB vacía
if (empty($sectores)) {
    // Definir sectores básicos (DRY)
    $defaults = [
        'tech' => ['Celulares', 'Las mejores tiendas y marcas'],
        'fashion' => ['Ropa', 'Moda Nacional y Americana'],
        'home' => ['Muebles', 'Hogar, Camas y Roperos'],
        'toys' => ['Juguetes', 'Niños y Coleccionables'],
        'tools' => ['Herramientas', 'Ferretería y Construcción'],
        'auto' => ['Vehículos', 'Autopartes y Accesorios'],
        'electro' => ['Electrodomésticos', 'Línea Blanca y TV'],
        'realestate' => ['Inmuebles', 'Terrenos y Casas']
    ];
    foreach($defaults as $slug => $info) {
        $sectores[$slug] = [
            'title' => $info[0], 'desc' => $info[1], 'color' => '#007AFF', 'capacity' => 12,
            'image' => getBannerImage($slug, [$slug])
        ];
    }
}
?>

<link href="/assets/css/feria.css?v=<?php echo filemtime(__DIR__ . '/assets/css/feria.css'); ?>" rel="stylesheet">

<div class="feria-layout">
    <!-- 0. TARJETA CENTRAL PRO -->
    <div class="feria-pro-card-container">
        <div class="feria-pro-card">
            <h1 class="pro-card-title">Feria Virtual Done!</h1>
            <p class="pro-card-desc">Explora las mejores tiendas de Bolivia en un solo lugar.</p>
            
            <div class="pro-unified-bar">
                <div class="pro-dept-wrapper">
                    <div id="deptTrigger" class="pro-dept-trigger">
                        <i class="fas fa-map-marker-alt pro-dept-icon"></i>
                        <span id="currentDept"><?php echo $current_dept_name; ?></span>
                        <i class="fas fa-chevron-down pro-chevron"></i>
                    </div>
                    <div id="deptMenu" class="dept-menu">
                        <?php foreach ($departments as $code => $name): ?>
                            <a href="?dept=<?php echo $code; ?>" class="dept-item"><?php echo $name; ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="pro-divider"></div>
                <form action="/feria_search.php" method="GET" class="pro-search-wrapper" style="position: relative;">
                    <i class="fas fa-search pro-search-icon"></i>
                    <input type="text" id="searchInputFeria" name="q" placeholder="Buscar tienda..." class="pro-search-input" value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>">
                    <button type="button" id="voiceSearchBtnFeria" class="voice-search-btn" style="display: none;" title="Buscar por voz">
                        <i class="fas fa-microphone"></i>
                        <span class="voice-ripple"></span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- 2. BENTO GRID (Optimizado) -->
    <div class="bento-grid">
        <?php 
        $sector_counter = 0;
        foreach ($sectores as $key => $sec): 
            $sector_counter++;
            $imgSrc = !empty($sec['image']) ? $sec['image'] : ''; // SIN timestamp para caché
            $isAboveFold = ($sector_counter <= 2);
            $loadingAttr = $isAboveFold ? 'eager' : 'lazy';
            $fetchPriority = $isAboveFold ? 'high' : 'auto';
            $decoding = $isAboveFold ? 'sync' : 'async';
        ?>
            <div class="sector-block" style="--sector-color: <?php echo $sec['color']; ?>; content-visibility: auto;">
                <div class="sector-header-split">
                    <div class="split-text-col">
                        <h2 class="sector-title-pro"><?php echo $sec['title']; ?></h2>
                        <p class="sector-desc-pro"><?php echo $sec['desc']; ?></p>
                    </div>
                    <div class="split-image-col">
                        <div class="image-box">
                            <?php if ($imgSrc): ?>
                                <img src="<?php echo $imgSrc; ?>" 
                                     alt="<?php echo htmlspecialchars($sec['title']); ?>" 
                                     loading="<?php echo $loadingAttr; ?>"
                                     fetchpriority="<?php echo $fetchPriority; ?>"
                                     decoding="<?php echo $decoding; ?>"
                                     class="fade-in-fast"
                                     width="300" height="300"> <!-- Dimensiones explícitas para CLS -->
                            <?php else: ?>
                                <div class="image-placeholder" style="background-color: <?php echo $sec['color']; ?>; opacity: 0.2; width: 100%; height: 100%;"></div>
                            <?php endif; ?>
                            
                            <a href="/sector_detalle?slug=<?php echo $key; ?>&dept=<?php echo $current_dept_code; ?>" class="view-all-pill" title="Ver todo">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 9V3h6"></path><path d="M3 3l7 7"></path><path d="M21 9V3h-6"></path><path d="M21 3l-7 7"></path>
                                    <path d="M21 15v6h-6"></path><path d="M21 21l-7-7"></path><path d="M3 15v6h6"></path><path d="M3 21l7-7"></path>
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="stores-inner-grid">
                    <?php 
                    for ($i = 0; $i < $sec['capacity']; $i++) {
                        $store = isset($occupied_slots[$key][$i]) ? $occupied_slots[$key][$i] : null;
                        if ($store) {
                            $final_img = $store['img'];
                            ?>
                            <a href="<?php echo $store['url']; ?>" class="store-item real" title="<?php echo $store['name']; ?>" target="_blank">
                                <div class="store-logo-wrap">
                                    <?php if (empty($final_img) || $final_img === '/assets/img/default-store.png'): ?>
                                        <i class="fas fa-store" style="font-size: 48px; color: #e5e5e5;"></i>
                                    <?php else: ?>
                                        <img src="<?php echo $final_img . '?v=' . time(); ?>" class="store-img fade-in-fast" loading="lazy" width="50" height="50">
                                    <?php endif; ?>
                                </div>
                            </a>
                            <?php
                        } else {
                            echo '<div class="store-item empty" title="Espacio Disponible" style="cursor: default;"><span class="empty-text">LIBRE</span></div>';
                        }
                    }
                    ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- MODAL FERIA -->
<div id="feriaModal" class="feria-modal-overlay">
    <div class="feria-modal-content">
        <button class="feria-modal-close" onclick="closeFeriaModal()">&times;</button>
        <div id="modal-guest" class="modal-state" style="display: none;">
            <div class="modal-icon-wrap"><i class="fas fa-user-plus"></i></div>
            <h3>Únete a la Feria Virtual</h3>
            <p>Para ocupar este puesto y mostrar tu marca aquí, primero necesitas crear una cuenta.</p>
            <div class="modal-actions">
                <a href="/auth/register" class="btn-modal primary">Crear Cuenta</a>
                <a href="/auth/login" class="btn-modal secondary">Ya tengo cuenta</a>
            </div>
        </div>
        <div id="modal-user" class="modal-state" style="display: none;">
            <div class="modal-icon-wrap orange"><i class="fas fa-store"></i></div>
            <h3>¡Casi listo!</h3>
            <p>Ya tienes cuenta, pero necesitas <strong>Crear tu Tienda Virtual</strong>.</p>
            <div class="modal-actions">
                <a id="btn-create-store" href="#" class="btn-modal primary">Crear Tienda Ahora</a>
                <button onclick="closeFeriaModal()" class="btn-modal secondary">Cancelar</button>
            </div>
        </div>
        <div id="modal-owner" class="modal-state" style="display: none;">
            <div class="modal-icon-wrap green"><i class="fas fa-map-marker-alt"></i></div>
            <h3>Ocupar este puesto</h3>
            <p>¿Quieres asignar tu tienda <strong><span id="owner-store-name"></span></strong> a este lugar en <span id="target-sector-name"></span>?</p>
            <form action="/mi/crear_tienda" method="POST">
                <input type="hidden" name="feria_sector" id="input-sector">
                <input type="hidden" name="feria_city" id="input-city">
                <input type="hidden" name="feria_pos" id="input-pos">
                <input type="hidden" name="confirmar_mudanza" value="1">
                <div class="modal-actions">
                    <button type="submit" class="btn-modal primary">✅ Confirmar y Ocupar</button>
                    <button type="button" onclick="closeFeriaModal()" class="btn-modal secondary">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Configuración Global para JS
    window.feriaConfig = {
        isLoggedIn: <?php echo isset($_SESSION['usuario_id']) ? 'true' : 'false'; ?>,
        hasStore: <?php echo $user_has_store ? 'true' : 'false'; ?>,
        storeName: "<?php echo $user_has_store ? htmlspecialchars($user_store_data['nombre']) : ''; ?>",
        currentCity: "<?php echo $current_dept_code; ?>"
    };
</script>
<script src="/assets/js/feria.js?v=<?php echo filemtime(__DIR__ . '/assets/js/feria.js'); ?>" defer></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
