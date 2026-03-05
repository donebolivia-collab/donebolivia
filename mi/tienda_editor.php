<?php
/**
 * Editor de Tienda - Panel de Control Profesional (Full Screen App)
 * Permite la gestión completa de la identidad, apariencia y productos de la tienda.
 */

// 1. Configuración de Errores (Producción: Desactivado)
// ini_set('display_errors', 0);
// error_reporting(E_ALL);

// 2. Manejo de Sesión y Zona Horaria
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('America/La_Paz');

// 3. Includes Críticos con Verificación
$base_path = dirname(__DIR__); // d:\ndb\htdocs
$includes = [
    $base_path . '/config/database.php',
    $base_path . '/includes/functions.php',
    $base_path . '/config/ubicaciones_bolivia.php'
];

foreach ($includes as $file) {
    if (!file_exists($file)) {
        die("<h1>Error Crítico</h1><p>Falta el archivo del sistema: " . basename($file) . "</p>");
    }
    require_once $file;
}

// 4. Verificación de Autenticación
if (!function_exists('estaLogueado') || !estaLogueado()) {
    header('Location: /auth/login.php?redirect=' . urlencode('/mi/tienda_editor.php'));
    exit;
}

try {
    $db = getDB();
    $usuario_id = $_SESSION['usuario_id'];

    // 5. Obtener Tienda y Datos de Sector (JOIN para obtener categoria_default_id)
    $stmt = $db->prepare("
        SELECT t.*, s.categoria_default_id 
        FROM tiendas t
        LEFT JOIN feria_sectores s ON t.categoria = s.slug
        WHERE t.usuario_id = ? 
        LIMIT 1
    ");
    $stmt->execute([$usuario_id]);
    $tienda = $stmt->fetch();

    if (!$tienda) {
        header('Location: /mi/crear_tienda.php');
        exit;
    }

    // CARGA DE DATOS PARA EDITOR (TODOS: Activos e Inactivos)
    $stmt_productos = $db->prepare("
        SELECT p.*, pi.nombre_archivo as imagen_principal,
               (SELECT GROUP_CONCAT(badge_id SEPARATOR ',') FROM producto_badges
                WHERE producto_id = p.id) AS badges
        FROM productos p
        LEFT JOIN producto_imagenes pi ON p.id = pi.producto_id AND pi.es_principal = 1
        WHERE p.usuario_id = ? 
        ORDER BY p.fecha_publicacion DESC
        LIMIT 50
    ");
    $stmt_productos->execute([$tienda['usuario_id']]);
    $productos = $stmt_productos->fetchAll();
    
    // Procesar badges para cada producto
    foreach ($productos as &$producto) {
        if (!empty($producto['badges'])) {
            $producto['badges'] = explode(',', $producto['badges']);
        } else {
            $producto['badges'] = [];
        }
    }

    $menu_items = [];
    if (!empty($tienda['menu_items'])) {
        $decoded = json_decode($tienda['menu_items'], true);
        if (is_array($decoded)) $menu_items = $decoded;
    }

    $color_primario = !empty($tienda['color_primario']) ? $tienda['color_primario'] : '#1a73e8';
    $tema_actual = !empty($tienda['tema']) ? $tienda['tema'] : 'claro';

    // Secciones
    $secciones_menu = [];
    foreach ($menu_items as $item) {
        if (!empty($item['label'])) $secciones_menu[] = $item['label'];
    }

    $categorias = function_exists('obtenerCategorias') ? obtenerCategorias() : [];
    $departamentos = function_exists('obtenerDepartamentosBolivia') ? obtenerDepartamentosBolivia() : [];

    // Cargar todos los badges activos para el selector
    $stmt_all_badges = $db->query("SELECT id, nombre, svg_path FROM badges WHERE activo = 1 ORDER BY orden ASC");
    $available_badges = $stmt_all_badges->fetchAll(PDO::FETCH_ASSOC);

    // --- LÓGICA DE UBICACIÓN ---
    $deptCode = 'SCZ'; 
    $munCode = 'SCZ-001'; 
    
    // Ciudades para Feria Virtual (Códigos legacy)
    $feria_ciudades = [
        'LPZ' => 'La Paz', 'ALT' => 'El Alto', 'SCZ' => 'Santa Cruz', 
        'CBA' => 'Cochabamba', 'ORU' => 'Oruro', 'PTS' => 'Potosí', 
        'TJA' => 'Tarija', 'CHQ' => 'Chuquisaca', 'BEN' => 'Beni', 'PND' => 'Pando'
    ];
    
    // Configuración de Identidad Visual 2.0
    $estilo_bordes = !empty($tienda['estilo_bordes']) ? $tienda['estilo_bordes'] : 'suave';
    $estilo_fondo = !empty($tienda['estilo_fondo']) ? $tienda['estilo_fondo'] : 'blanco';
    $tipografia = !empty($tienda['tipografia']) ? $tienda['tipografia'] : 'system';
    $tamano_texto = !empty($tienda['tamano_texto']) ? $tienda['tamano_texto'] : 'normal';
    $estilo_tarjetas = !empty($tienda['estilo_tarjetas']) ? $tienda['estilo_tarjetas'] : 'borde';
    $estilo_fotos = !empty($tienda['estilo_fotos']) ? $tienda['estilo_fotos'] : 'cuadrado';

    // Resolver Ubicación
    if (!empty($tienda['departamento'])) {
        $d = trim($tienda['departamento']);
        if (isset($departamentos[$d])) {
            $deptCode = $d; 
        }
    }

} catch (Throwable $e) {
    die("<h1>Error del Sistema</h1><p>" . htmlspecialchars($e->getMessage()) . "</p><pre>" . $e->getTraceAsString() . "</pre>");
}

$titulo = "Editor: " . $tienda['nombre'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($titulo); ?></title>
    <!-- CSS Base -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="/assets/css/buttons-pro.css?v=1.0" rel="stylesheet">
    <link href="/assets/css/image-uploader.css?v=<?php echo time(); ?>" rel="stylesheet">
    <link href="/assets/css/modules/badges.css?v=<?php echo time(); ?>" rel="stylesheet">
    <link href="/assets/css/modules/drawer.css?v=<?php echo time(); ?>" rel="stylesheet">
    <link href="/assets/css/modules/sidebar.css?v=<?php echo time(); ?>" rel="stylesheet">
    <link href="/assets/css/modules/buttons.css?v=<?php echo time(); ?>" rel="stylesheet">
    <link href="/assets/css/editor-tienda.css?v=<?php echo time(); ?>" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&family=Plus+Jakarta+Sans:wght@400;500;700&family=Manrope:wght@400;500;700&family=Outfit:wght@300;500;700&family=Poppins:wght@300;400;500;600&family=Space+Mono&family=Roboto:wght@300;400;500;700&family=Lora:wght@400;600&family=Montserrat:wght@400;600;800&display=swap" rel="stylesheet">
    <script src="https://js.puter.com/v2/"></script>

</head>
<body>

<!-- LAYOUT PRINCIPAL: SPLIT SCREEN -->
<div class="editor-split-container" id="editorContainer">
    
    <!-- SIDEBAR DE CONTROL -->
    <aside class="editor-sidebar" id="editorSidebar">
        
        <div class="sidebar-header" style="position:relative; z-index:50;">
            <button class="btn-icon-mini" onclick="toggleSidebar()" title="Ocultar Panel" style="background:transparent; border:none; font-size:16px;">
                <i class="fas fa-bars"></i>
            </button>
            <h2 style="margin-left:10px;">Editor</h2>
            
            <div class="device-toggles-mini" style="margin-left: auto; display:flex; gap:5px;">
                <a href="/tienda/<?php echo $tienda['slug']; ?>" target="_blank" class="btn-eye-link" title="Ver en vivo" style="text-decoration:none; display:flex; align-items:center;">
                    <i class="fas fa-eye"></i>
                </a>
                <button class="device-btn active" onclick="setCanvasSize('desktop')" title="Escritorio"><i class="fas fa-desktop"></i></button>
                <button class="device-btn" onclick="setCanvasSize('mobile')" title="Móvil"><i class="fas fa-mobile-alt"></i></button>
            </div>
        </div>

        <div id="statusBar" class="save-indicator-bar" style="padding: 5px 16px; background:#00a650; border-bottom:1px solid #e2e8f0; display:flex; justify-content:flex-end; transition: background-color 0.3s ease; position:relative; z-index:50;">
             <span id="autoSaveStatus" style="font-size:13px; font-weight:600; display:flex; align-items:center; gap:5px; color: white;"></span>
        </div>

        <div class="sidebar-content">
            
            <!-- MARCA Y APARIENCIA (NUEVO UNIFICADO) -->
            <div class="accordion-item">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <span><i class="fas fa-store"></i> Identidad Visual</span>
                    <i class="fas fa-chevron-down arrow"></i>
                </div>
                <div class="accordion-body">
                    <!-- Nombre -->
                    <div class="control-group">
                        <div class="control-group-header">
                            <label>Nombre</label>
                            <label class="switch" title="Mostrar nombre en la tienda">
                                <input type="checkbox" id="mostrar_nombre_tienda" <?php echo (!isset($tienda['mostrar_nombre']) || $tienda['mostrar_nombre']) ? 'checked' : ''; ?>>
                                <span class="slider round"></span>
                            </label>
                        </div>
                        <input type="text" id="storeName" class="sidebar-input" value="<?php echo htmlspecialchars($tienda['nombre']); ?>">
                    </div>

                    <!-- Logo -->
                    <div class="control-group">
                        <div class="control-group-header">
                            <label>Logo</label>
                            <label class="switch" title="Mostrar logo en la tienda">
                                <input type="checkbox" id="mostrar_logo_principal" <?php echo !empty($tienda['mostrar_logo']) ? 'checked' : ''; ?>>
                                <span class="slider round"></span>
                            </label>
                        </div>
                        <!-- LOGO PRINCIPAL (Estandarizado image-uploader.css) -->
                        <div id="brand-logo-uploader" class="image-uploader landscape contain-mode <?php echo !empty($tienda['logo_principal']) ? 'has-image' : ''; ?>">
                            <input type="file" id="logoPrincipalInput" hidden accept="image/png,image/jpeg,image/webp,image/gif">
                            
                            <!-- Preview -->
                            <img src="<?php echo !empty($tienda['logo_principal']) ? '/uploads/logos/'.htmlspecialchars($tienda['logo_principal']) : ''; ?>" id="principalLogoPreview" class="image-preview" style="<?php echo !empty($tienda['logo_principal']) ? 'display:block;' : 'display:none;'; ?>">
                            
                            <!-- Placeholder -->
                            <div id="principalLogoPlaceholder" class="image-placeholder" style="<?php echo !empty($tienda['logo_principal']) ? 'display:none;' : 'display:flex;'; ?>">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            
                            <!-- Botón Eliminar -->
                            <button id="deletePrincipalLogoBtn" class="btn-delete-image" type="button"><i class="fas fa-times"></i></button>
                        </div>
                    </div>

                    <!-- COLOR DE MARCA (MOVED) -->
                    <div class="control-group" id="colorGroup">
                        <label>Color de Marca</label>
                        <div class="ui-dropdown" id="colorDropdown">
                            <div class="ui-button" onclick="toggleUI('colorDropdown')">
                                <div id="currentColorPreview" class="current-color-preview" style="background-color: <?php echo $color_primario; ?>;"></div>
                                <span class="marker-label">Seleccionar Color</span>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div class="ui-menu">
                                <div class="color-picker-grid" id="colorSwatches"></div>
                                <div class="hex-input-container">
                                    <span class="hex-input-addon" id="hexColorAddon"></span>
                                    <input type="text" id="hexColorInput" class="hex-input" maxlength="7">
                                </div>
                                <div id="colorLockMsg" style="display:none; font-size:11px; color:#64748b; margin-top:12px; background:#f1f5f9; padding:8px; border-radius:6px; border:1px solid #e2e8f0; text-align:center;">
                                    <i class="fas fa-info-circle"></i> Tema restringido
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- BARRA DE NAVEGACIÓN (NEW - FUNCTIONAL) -->
                    <div class="control-group">
                        <label>Barra de Navegación</label>
                        <div class="ui-dropdown" id="navbarStyleDropdown">
                            <input type="hidden" id="navbarStyleValue" value="<?php echo $tienda['navbar_style'] ?? 'blanco'; ?>">
                            <div class="ui-trigger" onclick="toggleUI('navbarStyleDropdown')">
                                <span class="trigger-label" id="navbarStyleLabel"><?php echo ($tienda['navbar_style'] ?? 'blanco') === 'marca' ? 'Color de Marca' : 'Blanco'; ?></span>
                                <i class="fas fa-chevron-down chevron"></i>
                            </div>
                            <div class="ui-menu">
                                <div class="ui-option <?php echo ($tienda['navbar_style'] ?? 'blanco') === 'blanco' ? 'selected' : ''; ?>" onclick="selectUIOption('navbarStyleDropdown', 'blanco', 'Blanco', setNavbarStyle)">
                                    Blanco
                                </div>
                                <div class="ui-option <?php echo ($tienda['navbar_style'] ?? 'blanco') === 'marca' ? 'selected' : ''; ?>" onclick="selectUIOption('navbarStyleDropdown', 'marca', 'Color de Marca', setNavbarStyle)">
                                    Color de Marca
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- FONDO DE PÁGINA (MOVED & MODIFIED) -->
                    <div class="control-group">
                        <label>Fondo de Página</label>
                        <div class="ui-dropdown" id="bgDropdown">
                            <input type="hidden" id="bgValue" value="<?php echo $estilo_fondo; ?>">
                            <div class="ui-trigger" onclick="toggleUI('bgDropdown')">
                                <span class="trigger-label" id="bgLabel">
                                    <?php 
                                        if($estilo_fondo === 'blanco') echo 'Blanco';
                                        elseif($estilo_fondo === 'tintado') echo 'Color de Marca';
                                        elseif($estilo_fondo === 'gris') echo 'Gris';
                                        else echo 'Blanco'; // Default visual
                                    ?>
                                </span>
                                <i class="fas fa-chevron-down chevron"></i>
                            </div>
                            <div class="ui-menu">
                                <div class="ui-option <?php echo $estilo_fondo==='blanco'?'selected':''; ?>" data-action="select-ui-option" data-parent="bgDropdown" data-value="blanco" data-label="Blanco" data-callback="setBackground">
                                    Blanco
                                </div>
                                <div class="ui-option <?php echo $estilo_fondo==='tintado'?'selected':''; ?>" data-action="select-ui-option" data-parent="bgDropdown" data-value="tintado" data-label="Color de Marca" data-callback="setBackground">
                                    Color de Marca
                                </div>
                                <div class="ui-option <?php echo $estilo_fondo==='gris'?'selected':''; ?>" data-action="select-ui-option" data-parent="bgDropdown" data-value="gris" data-label="Gris" data-callback="setBackground">
                                    Gris
                                </div>
                            </div>
                        </div>
                    </div>


                </div>
            </div>

            <!-- PERSONALIZACIÓN -->
            <div class="accordion-item">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <span><i class="fas fa-paint-brush"></i> Personalización</span>
                    <i class="fas fa-chevron-down arrow"></i>
                </div>
                <div class="accordion-body">
                    <input type="hidden" id="storeDescription" value="<?php echo htmlspecialchars($tienda['descripcion']); ?>">
                    <div class="control-separator"></div>

                    <!-- 4. BORDES (UI DROPDOWN - NO ICONS) -->
                    <div class="control-group">
                        <label>Bordes</label>
                        <div class="ui-dropdown" id="borderDropdown">
                            <input type="hidden" id="borderValue" value="<?php echo $estilo_bordes; ?>">
                            <div class="ui-trigger" onclick="toggleUI('borderDropdown')">
                                <span class="trigger-label" id="borderLabel">
                                    <?php 
                                        if($estilo_bordes === 'recto') echo 'Rectangular';
                                        elseif($estilo_bordes === 'suave' || $estilo_bordes === 'redondo') echo 'Redondeado';
                                        elseif($estilo_bordes === 'pill') echo 'Pildora';
                                        else echo 'Seleccionar';
                                    ?>
                                </span>
                                <i class="fas fa-chevron-down chevron"></i>
                            </div>
                            <div class="ui-menu">
                                <div class="ui-option <?php echo $estilo_bordes==='recto'?'selected':''; ?>" data-action="select-ui-option" data-parent="borderDropdown" data-value="recto" data-label="Rectangular" data-callback="setBorder">
                                    Rectangular
                                </div>
                                <div class="ui-option <?php echo ($estilo_bordes==='suave'||$estilo_bordes==='redondo')?'selected':''; ?>" data-action="select-ui-option" data-parent="borderDropdown" data-value="suave" data-label="Redondeado" data-callback="setBorder">
                                    Redondeado
                                </div>
                                <div class="ui-option <?php echo $estilo_bordes==='pill'?'selected':''; ?>" data-action="select-ui-option" data-parent="borderDropdown" data-value="pill" data-label="Pildora" data-callback="setBorder">
                                    Pildora
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 5. ESTILO DE TARJETAS (UI DROPDOWN - NO ICONS) -->
                    <div class="control-group">
                        <label>Estilo de Tarjetas</label>
                        <div class="ui-dropdown" id="cardDropdown">
                            <input type="hidden" id="cardValue" value="<?php echo $estilo_tarjetas; ?>">
                            <div class="ui-trigger" onclick="toggleUI('cardDropdown')">
                                <span class="trigger-label" id="cardLabel">
                                    <?php 
                                        if($estilo_tarjetas === 'elevada') echo 'Flotante';
                                        elseif($estilo_tarjetas === 'borde') echo 'Con borde';
                                        elseif($estilo_tarjetas === 'flat') echo 'Sin borde';
                                        else echo 'Seleccionar';
                                    ?>
                                </span>
                                <i class="fas fa-chevron-down chevron"></i>
                            </div>
                            <div class="ui-menu">
                                <div class="ui-option <?php echo $estilo_tarjetas==='elevada'?'selected':''; ?>" data-action="select-ui-option" data-parent="cardDropdown" data-value="elevada" data-label="Flotante" data-callback="setCardStyle">
                                    Flotante
                                </div>
                                <div class="ui-option <?php echo $estilo_tarjetas==='borde'?'selected':''; ?>" data-action="select-ui-option" data-parent="cardDropdown" data-value="borde" data-label="Con borde" data-callback="setCardStyle">
                                    Con borde
                                </div>
                                <div class="ui-option <?php echo $estilo_tarjetas==='flat'?'selected':''; ?>" data-action="select-ui-option" data-parent="cardDropdown" data-value="flat" data-label="Sin borde" data-callback="setCardStyle">
                                    Sin borde
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- NUEVO: FORMATO DE FOTOS -->
                    <div class="control-group">
                        <label>Formato de Fotos</label>
                        <div class="ui-dropdown" id="photoDropdown">
                            <input type="hidden" id="photoValue" value="<?php echo $estilo_fotos; ?>">
                            <div class="ui-trigger" onclick="toggleUI('photoDropdown')">
                                <span class="trigger-label" id="photoLabel">
                                    <?php 
                                        if($estilo_fotos === 'cuadrado') echo 'Cuadrado';
                                        elseif($estilo_fotos === 'vertical') echo 'Vertical';
                                        elseif($estilo_fotos === 'horizontal') echo 'Horizontal';
                                        elseif($estilo_fotos === 'natural') echo 'Sin recorte';
                                        else echo 'Seleccionar';
                                    ?>
                                </span>
                                <i class="fas fa-chevron-down chevron"></i>
                            </div>
                            <div class="ui-menu">
                                <div class="ui-option <?php echo $estilo_fotos==='cuadrado'?'selected':''; ?>" data-action="select-ui-option" data-parent="photoDropdown" data-value="cuadrado" data-label="Cuadrado" data-callback="setPhotoStyle">
                                    Cuadrado
                                </div>
                                <div class="ui-option <?php echo $estilo_fotos==='vertical'?'selected':''; ?>" data-action="select-ui-option" data-parent="photoDropdown" data-value="vertical" data-label="Vertical" data-callback="setPhotoStyle">
                                    Vertical
                                </div>
                                <div class="ui-option <?php echo $estilo_fotos==='horizontal'?'selected':''; ?>" data-action="select-ui-option" data-parent="photoDropdown" data-value="horizontal" data-label="Horizontal" data-callback="setPhotoStyle">
                                    Horizontal
                                </div>
                                <div class="ui-option <?php echo $estilo_fotos==='natural'?'selected':''; ?>" data-action="select-ui-option" data-parent="photoDropdown" data-value="natural" data-label="Sin recorte" data-callback="setPhotoStyle">
                                    Sin recorte
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- NUEVO: DENSIDAD DE CUADRÍCULA (CORREGIDO Y CON OPCIÓN AUTOMÁTICO) -->
                    <div class="control-group">
                        <label>Columnas de Productos</label>
                        <div class="ui-dropdown" id="gridDropdown">
                            <?php
                                $grid_density_db = $tienda['grid_density'] ?? 3;
                                $grid_density_val = ($grid_density_db == 0) ? 'auto' : $grid_density_db;

                                if ($grid_density_val === 'auto') {
                                    $grid_label = 'Automático';
                                } elseif ($grid_density_val == 2) {
                                    $grid_label = '2 Columnas';
                                } elseif ($grid_density_val == 4) {
                                    $grid_label = '4 Columnas';
                                } else {
                                    $grid_label = '3 Columnas';
                                }
                            ?>
                            <input type="hidden" id="gridValue" value="<?php echo $grid_density_val; ?>">
                            <div class="ui-trigger" onclick="toggleUI('gridDropdown')">
                                <span class="trigger-label" id="gridLabel">
                                    <?php echo $grid_label; ?>
                                </span>
                                <i class="fas fa-chevron-down chevron"></i>
                            </div>
                            <div class="ui-menu">
                                <div class="ui-option <?php echo $grid_density_val === 'auto' ? 'selected' : ''; ?>" data-action="select-ui-option" data-parent="gridDropdown" data-value="auto" data-label="Automático" data-callback="setGridDensity">
                                    Automático
                                </div>
                                <div class="ui-option <?php echo $grid_density_val == 2 ? 'selected' : ''; ?>" data-action="select-ui-option" data-parent="gridDropdown" data-value="2" data-label="2 Columnas" data-callback="setGridDensity">
                                    2 Columnas
                                </div>
                                <div class="ui-option <?php echo $grid_density_val == 3 ? 'selected' : ''; ?>" data-action="select-ui-option" data-parent="gridDropdown" data-value="3" data-label="3 Columnas" data-callback="setGridDensity">
                                    3 Columnas
                                </div>
                                <div class="ui-option <?php echo $grid_density_val == 4 ? 'selected' : ''; ?>" data-action="select-ui-option" data-parent="gridDropdown" data-value="4" data-label="4 Columnas" data-callback="setGridDensity">
                                    4 Columnas
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 6. TIPOGRAFIA (UI DROPDOWN) -->
                    <div class="control-group">
                        <label>Tipografía</label>
                        <div class="ui-dropdown" id="fontDropdown">
                            <input type="hidden" id="fontValue" value="<?php echo $tipografia; ?>">
                            <div class="ui-trigger" onclick="toggleUI('fontDropdown')">
                                <span class="trigger-label" id="fontLabel">
                                    <?php 
                                        $fonts = [
                                        'system' => 'Predeterminado',
                                        'inter' => 'Inter',
                                        'jakarta' => 'Plus Jakarta',
                                        'manrope' => 'Manrope',
                                        'modern' => 'Poppins',
                                        'tech' => 'Space Mono',
                                        'minimal' => 'Roboto',
                                        'classic' => 'Lora',
                                        'bold' => 'Montserrat',
                                        'outfit' => 'Outfit'
                                    ];
                                    echo isset($fonts[$tipografia]) ? $fonts[$tipografia] : 'Predeterminado';
                                    ?>
                                </span>
                                <i class="fas fa-chevron-down chevron"></i>
                            </div>
                            <div class="ui-menu">
                                <?php foreach($fonts as $key => $label): ?>
                                <div class="ui-option <?php echo $tipografia===$key?'selected':''; ?>" data-action="select-ui-option" data-parent="fontDropdown" data-value="<?php echo $key; ?>" data-label="<?php echo $label; ?>" data-callback="setFont">
                                     <span><?php echo $label; ?></span>
                                 </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- 7. TAMAÑO DE TEXTO (UI DROPDOWN - NO ICONS) -->
                    <div class="control-group">
                        <label>Tamaño de Texto</label>
                        <div class="ui-dropdown" id="sizeDropdown">
                            <input type="hidden" id="sizeValue" value="<?php echo $tamano_texto; ?>">
                            <div class="ui-trigger" onclick="toggleUI('sizeDropdown')">
                                <span class="trigger-label" id="sizeLabel">
                                    <?php 
                                        if($tamano_texto === 'small') echo 'Pequeño';
                                        elseif($tamano_texto === 'large') echo 'Grande';
                                        else echo 'Normal';
                                    ?>
                                </span>
                                <i class="fas fa-chevron-down chevron"></i>
                            </div>
                            <div class="ui-menu">
                                <div class="ui-option <?php echo $tamano_texto==='small'?'selected':''; ?>" data-action="select-ui-option" data-parent="sizeDropdown" data-value="small" data-label="Pequeño" data-callback="setTextSize">
                                    Pequeño
                                </div>
                                <div class="ui-option <?php echo ($tamano_texto==='normal' || !$tamano_texto)?'selected':''; ?>" data-action="select-ui-option" data-parent="sizeDropdown" data-value="normal" data-label="Normal" data-callback="setTextSize">
                                    Normal
                                </div>
                                <div class="ui-option <?php echo $tamano_texto==='large'?'selected':''; ?>" data-action="select-ui-option" data-parent="sizeDropdown" data-value="large" data-label="Grande" data-callback="setTextSize">
                                    Grande
                                </div>
                            </div>
                        </div>
                    </div>
                    
                </div>
            </div>

            <!-- CONTACTO -->
            <div class="accordion-item">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <span><i class="fas fa-address-card"></i> Canales de Contacto</span>
                    <i class="fas fa-chevron-down arrow"></i>
                </div>
                <div class="accordion-body">
                    <div class="control-group">
                        <label>WhatsApp</label>
                        <input type="text" id="inputWhatsapp" class="sidebar-input" value="<?php echo htmlspecialchars($tienda['whatsapp'] ?? ''); ?>">
                    </div>
                    <div class="control-group">
                        <label>Correo Electrónico</label>
                        <input type="email" id="inputEmail" class="sidebar-input" value="<?php echo htmlspecialchars($tienda['email_contacto'] ?? ''); ?>">
                    </div>
                    <div class="control-group">
                        <label>Dirección</label>
                        <input type="text" id="inputDireccion" class="sidebar-input" value="<?php echo htmlspecialchars($tienda['direccion'] ?? ''); ?>">
                    </div>
                    <div class="control-group">
                        <label>Ubicación y Redes Sociales</label>
                        <input type="hidden" id="inputFacebook" value="<?php echo htmlspecialchars($tienda['facebook_url'] ?? ''); ?>">
                        <input type="hidden" id="inputInstagram" value="<?php echo htmlspecialchars($tienda['instagram_url'] ?? ''); ?>">
                        <input type="hidden" id="inputTiktok" value="<?php echo htmlspecialchars($tienda['tiktok_url'] ?? ''); ?>">
                        <input type="hidden" id="inputTelegram" value="<?php echo htmlspecialchars($tienda['telegram_user'] ?? ''); ?>">
                        <input type="hidden" id="inputYoutube" value="<?php echo htmlspecialchars($tienda['youtube_url'] ?? ''); ?>">
                        <input type="hidden" id="inputMaps" value="<?php echo htmlspecialchars($tienda['google_maps_url'] ?? ''); ?>">
                        <div class="social-icons-row">
                            <button class="btn-social-icon maps <?php echo !empty($tienda['google_maps_url']) ? 'active' : ''; ?>" data-action="handle-location" title="Ubicación en Google Maps">
                                <i class="fas fa-map-marker-alt"></i>
                                <span class="check-badge"><i class="fas fa-check"></i></span>
                            </button>
                            <button class="btn-social-icon tiktok <?php echo !empty($tienda['tiktok_url']) ? 'active' : ''; ?>" data-action="edit-social" data-social="tiktok" title="TikTok">
                                <i class="fab fa-tiktok"></i>
                                <span class="check-badge"><i class="fas fa-check"></i></span>
                            </button>
                            <button class="btn-social-icon instagram <?php echo !empty($tienda['instagram_url']) ? 'active' : ''; ?>" data-action="edit-social" data-social="instagram" title="Instagram">
                                <i class="fab fa-instagram"></i>
                                <span class="check-badge"><i class="fas fa-check"></i></span>
                            </button>
                            <button class="btn-social-icon facebook <?php echo !empty($tienda['facebook_url']) ? 'active' : ''; ?>" data-action="edit-social" data-social="facebook" title="Facebook">
                                <i class="fab fa-facebook-f"></i>
                                <span class="check-badge"><i class="fas fa-check"></i></span>
                            </button>
                            <button class="btn-social-icon youtube <?php echo !empty($tienda['youtube_url']) ? 'active' : ''; ?>" data-action="edit-social" data-social="youtube" title="YouTube">
                                <i class="fab fa-youtube"></i>
                                <span class="check-badge"><i class="fas fa-check"></i></span>
                            </button>
                            <button class="btn-social-icon telegram <?php echo !empty($tienda['telegram_user']) ? 'active' : ''; ?>" data-action="edit-social" data-social="telegram" title="Telegram">
                                <i class="fab fa-telegram-plane"></i>
                                <span class="check-badge"><i class="fas fa-check"></i></span>
                            </button>
                        </div>
                        <div id="locationStatus" style="font-size:11px; color:#64748b; margin-top:6px; display:none;"></div>
                    </div>
                </div>
            </div>

            <!-- SECCIONES -->
            <div class="accordion-item">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <span><i class="fas fa-th-large"></i> Secciones</span>
                    <i class="fas fa-chevron-down arrow"></i>
                </div>
                <div class="accordion-body">
                    <!-- Preferencias -->
                    <label class="preferencias-title">Preferencias</label>
                    <div class="panel-toggles-row" style="display: flex; gap: 10px; margin-bottom: 20px;">
                        <!-- 1. Inicio -->
                        <button class="btn-panel-toggle" onclick="openHomeDrawer()" title="Inicio">
                            <i class="fas fa-home"></i>
                        </button>
                        <!-- 2. Secciones -->
                        <button class="btn-panel-toggle" onclick="openSectionsDrawer()" title="Secciones">
                            <i class="fas fa-cog"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- PRUEBAS DE TABLA -->
            <div class="accordion-item">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <span><i class="fas fa-flask"></i> Pruebas de Tabla</span>
                    <i class="fas fa-chevron-down arrow"></i>
                </div>
                <div class="accordion-body">
                    <div class="control-group">
                        <label style="display: block; font-size: 11px; font-weight: 700; color: #000000; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px;">PRUEBAS A/B ACTIVAS</label>
                        <div style="font-size: 12px; color: #000000; margin-top: 0; margin-bottom: 12px;">Tabla de pruebas para experimentos y configuraciones.</div>
                        
                        <!-- TABLA ENTERPRISE - SIN ENCABEZADO Y CON IMÁGENES -->
                        <div class="table-tests-container" style="background: white; border-radius: 8px; overflow: hidden; border: none; width: 100%; margin: 0; padding: 0;">
                            <table class="table-tests" style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                                <tbody id="tableTestsBody">
                                    <!-- Fila 1 - BLANCA -->
                                    <tr class="table-row" style="background: white; border-bottom: 1px solid #f1f5f9;">
                                        <td style="padding: 12px 8px 12px 0; vertical-align: middle; width: 80px;">
                                            <div style="display: flex; align-items: center; gap: 8px; width: 100%; margin-left: 0;">
                                                <input type="checkbox" id="test1Active" checked style="width: 16px; height: 16px; margin: 0; padding: 0; position: relative; left: 0; transform: translateX(0);">
                                                <img src="https://picsum.photos/seed/test1/24/24.jpg" alt="Test 1" style="width: 24px; height: 24px; border-radius: 4px; object-fit: cover;">
                                            </div>
                                        </td>
                                        <td style="padding: 12px; vertical-align: middle; width: auto; overflow: hidden;">
                                            <span style="font-weight: 500; color: #1f293b; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 13px;" title="Tecno Camon 18 Pro Más">Tecno Camon 18 Pro Más</span>
                                        </td>
                                        <td style="padding: 12px; vertical-align: middle; width: 60px; text-align: right; padding-right: 8px;">
                                            <div class="dropdown-table" style="position: relative; display: inline-block; width: 100%; text-align: right;">
                                                <button class="dropdown-trigger-ellipsis" onclick="toggleTableDropdown('test1')" style="background: none; border: none; padding: 8px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; border-radius: 4px; transition: background 0.2s; margin-left: auto;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='none'">
                                                    <i class="fas fa-ellipsis-v" style="color: #64748b; font-size: 14px;"></i>
                                                </button>
                                                <div id="test1Dropdown" class="dropdown-menu-table" style="display: none; position: absolute; top: 100%; left: -140px; z-index: 1000; background: white; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); min-width: 140px; border: 1px solid #e2e8f0;">
                                                    <div class="dropdown-item" style="padding: 10px 12px; cursor: pointer; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid #f1f5f9;">
                                                        <i class="fas fa-edit" style="color: #64748b; font-size: 12px;"></i>
                                                        <span>Editar prueba</span>
                                                    </div>
                                                    <div class="dropdown-item" style="padding: 10px 12px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                                                        <i class="fas fa-trash" style="color: #ef4444; font-size: 12px;"></i>
                                                        <span>Eliminar prueba</span>
                                                    </div>
                                                    <div class="dropdown-item" style="padding: 10px 12px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                                                        <i class="fas fa-eye-slash" style="color: #64748b; font-size: 12px;"></i>
                                                        <span>Ocultar prueba</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    
                                    <!-- Fila 2 - GRIS CLARA -->
                                    <tr class="table-row" style="background: #f8fafc; border-bottom: 1px solid #f1f5f9;">
                                        <td style="padding: 12px 8px 12px 0; vertical-align: middle; width: 80px;">
                                            <div style="display: flex; align-items: center; gap: 8px; width: 100%; margin-left: 0;">
                                                <input type="checkbox" id="test2Active" style="width: 16px; height: 16px; margin: 0; padding: 0; position: relative; left: 0; transform: translateX(0);">
                                                <img src="https://picsum.photos/seed/test2/24/24.jpg" alt="Test 2" style="width: 24px; height: 24px; border-radius: 4px; object-fit: cover;">
                                            </div>
                                        </td>
                                        <td style="padding: 12px; vertical-align: middle; width: auto; overflow: hidden;">
                                            <span style="font-weight: 500; color: #1f293b; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 13px;" title="Beta Test v2.0">Beta Test v2.0</span>
                                        </td>
                                        <td style="padding: 12px; vertical-align: middle; width: 60px; text-align: right; padding-right: 8px;">
                                            <div class="dropdown-table" style="position: relative; display: inline-block; width: 100%; text-align: right;">
                                                <button class="dropdown-trigger-ellipsis" onclick="toggleTableDropdown('test2')" style="background: none; border: none; padding: 8px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; border-radius: 4px; transition: background 0.2s; margin-left: auto;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='none'">
                                                    <i class="fas fa-ellipsis-v" style="color: #64748b; font-size: 14px;"></i>
                                                </button>
                                                <div id="test2Dropdown" class="dropdown-menu-table" style="display: none; position: absolute; top: 100%; left: -140px; z-index: 1000; background: white; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); min-width: 140px; border: 1px solid #e2e8f0;">
                                                    <div class="dropdown-item" style="padding: 10px 12px; cursor: pointer; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid #f1f5f9;">
                                                        <i class="fas fa-edit" style="color: #64748b; font-size: 12px;"></i>
                                                        <span>Editar prueba</span>
                                                    </div>
                                                    <div class="dropdown-item" style="padding: 10px 12px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                                                        <i class="fas fa-trash" style="color: #ef4444; font-size: 12px;"></i>
                                                        <span>Eliminar prueba</span>
                                                    </div>
                                                    <div class="dropdown-item" style="padding: 10px 12px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                                                        <i class="fas fa-eye-slash" style="color: #64748b; font-size: 12px;"></i>
                                                        <span>Ocultar prueba</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    
                                    <!-- Fila 3 - BLANCA -->
                                    <tr class="table-row" style="background: white; border-bottom: 1px solid #f1f5f9;">
                                        <td style="padding: 12px 8px 12px 0; vertical-align: middle; width: 80px;">
                                            <div style="display: flex; align-items: center; gap: 8px; width: 100%; margin-left: 0;">
                                                <input type="checkbox" id="test3Active" style="width: 16px; height: 16px; margin: 0; padding: 0; position: relative; left: 0; transform: translateX(0);">
                                                <img src="https://picsum.photos/seed/test3/24/24.jpg" alt="Test 3" style="width: 24px; height: 24px; border-radius: 4px; object-fit: cover;">
                                            </div>
                                        </td>
                                        <td style="padding: 12px; vertical-align: middle; width: auto; overflow: hidden;">
                                            <span style="font-weight: 500; color: #1f293b; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 13px;" title="Experimento Responsive v1.5">Experimento Responsive v1.5</span>
                                        </td>
                                        <td style="padding: 12px; vertical-align: middle; width: 60px; text-align: right; padding-right: 8px;">
                                            <div class="dropdown-table" style="position: relative; display: inline-block; width: 100%; text-align: right;">
                                                <button class="dropdown-trigger-ellipsis" onclick="toggleTableDropdown('test3')" style="background: none; border: none; padding: 8px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; border-radius: 4px; transition: background 0.2s; margin-left: auto;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='none'">
                                                    <i class="fas fa-ellipsis-v" style="color: #64748b; font-size: 14px;"></i>
                                                </button>
                                                <div id="test3Dropdown" class="dropdown-menu-table" style="display: none; position: absolute; top: 100%; left: -140px; z-index: 1000; background: white; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); min-width: 140px; border: 1px solid #e2e8f0;">
                                                    <div class="dropdown-item" style="padding: 10px 12px; cursor: pointer; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid #f1f5f9;">
                                                        <i class="fas fa-edit" style="color: #64748b; font-size: 12px;"></i>
                                                        <span>Editar prueba</span>
                                                    </div>
                                                    <div class="dropdown-item" style="padding: 10px 12px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                                                        <i class="fas fa-trash" style="color: #ef4444; font-size: 12px;"></i>
                                                        <span>Eliminar prueba</span>
                                                    </div>
                                                    <div class="dropdown-item" style="padding: 10px 12px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                                                        <i class="fas fa-eye-slash" style="color: #64748b; font-size: 12px;"></i>
                                                        <span>Ocultar prueba</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    
                                    <!-- Fila 4 - GRIS CLARA -->
                                    <tr class="table-row" style="background: #f8fafc; border-bottom: 1px solid #f1f5f9;">
                                        <td style="padding: 12px 8px 12px 0; vertical-align: middle; width: 80px;">
                                            <div style="display: flex; align-items: center; gap: 8px; width: 100%; margin-left: 0;">
                                                <input type="checkbox" id="test4Active" style="width: 16px; height: 16px; margin: 0; padding: 0; position: relative; left: 0; transform: translateX(0);">
                                                <img src="https://picsum.photos/seed/test4/24/24.jpg" alt="Test 4" style="width: 24px; height: 24px; border-radius: 4px; object-fit: cover;">
                                            </div>
                                        </td>
                                        <td style="padding: 12px; vertical-align: middle; width: auto; overflow: hidden;">
                                            <span style="font-weight: 500; color: #1f293b; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 13px;" title="Test de Carga Optimizada">Test de Carga Optimizada</span>
                                        </td>
                                        <td style="padding: 12px; vertical-align: middle; width: 60px; text-align: right; padding-right: 8px;">
                                            <div class="dropdown-table" style="position: relative; display: inline-block; width: 100%; text-align: right;">
                                                <button class="dropdown-trigger-ellipsis" onclick="toggleTableDropdown('test4')" style="background: none; border: none; padding: 8px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; border-radius: 4px; transition: background 0.2s; margin-left: auto;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='none'">
                                                    <i class="fas fa-ellipsis-v" style="color: #64748b; font-size: 14px;"></i>
                                                </button>
                                                <div id="test4Dropdown" class="dropdown-menu-table" style="display: none; position: absolute; top: 100%; left: -140px; z-index: 1000; background: white; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); min-width: 140px; border: 1px solid #e2e8f0;">
                                                    <div class="dropdown-item" style="padding: 10px 12px; cursor: pointer; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid #f1f5f9;">
                                                        <i class="fas fa-edit" style="color: #64748b; font-size: 12px;"></i>
                                                        <span>Editar prueba</span>
                                                    </div>
                                                    <div class="dropdown-item" style="padding: 10px 12px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                                                        <i class="fas fa-trash" style="color: #ef4444; font-size: 12px;"></i>
                                                        <span>Eliminar prueba</span>
                                                    </div>
                                                    <div class="dropdown-item" style="padding: 10px 12px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                                                        <i class="fas fa-eye-slash" style="color: #64748b; font-size: 12px;"></i>
                                                        <span>Ocultar prueba</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    
                                    <!-- Fila 5 - BLANCA -->
                                    <tr class="table-row" style="background: white; border-bottom: 1px solid #f1f5f9;">
                                        <td style="padding: 12px 8px 12px 0; vertical-align: middle; width: 80px;">
                                            <div style="display: flex; align-items: center; gap: 8px; width: 100%; margin-left: 0;">
                                                <input type="checkbox" id="test5Active" style="width: 16px; height: 16px; margin: 0; padding: 0; position: relative; left: 0; transform: translateX(0);">
                                                <img src="https://picsum.photos/seed/test5/24/24.jpg" alt="Test 5" style="width: 24px; height: 24px; border-radius: 4px; object-fit: cover;">
                                            </div>
                                        </td>
                                        <td style="padding: 12px; vertical-align: middle; width: auto; overflow: hidden;">
                                            <span style="font-weight: 500; color: #1f293b; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 13px;" title="Experimento UI/UX Moderna">Experimento UI/UX Moderna</span>
                                        </td>
                                        <td style="padding: 12px; vertical-align: middle; width: 60px; text-align: right; padding-right: 8px;">
                                            <div class="dropdown-table" style="position: relative; display: inline-block; width: 100%; text-align: right;">
                                                <button class="dropdown-trigger-ellipsis" onclick="toggleTableDropdown('test5')" style="background: none; border: none; padding: 8px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; border-radius: 4px; transition: background 0.2s; margin-left: auto;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='none'">
                                                    <i class="fas fa-ellipsis-v" style="color: #64748b; font-size: 14px;"></i>
                                                </button>
                                                <div id="test5Dropdown" class="dropdown-menu-table" style="display: none; position: absolute; top: 100%; left: -140px; z-index: 1000; background: white; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); min-width: 140px; border: 1px solid #e2e8f0;">
                                                    <div class="dropdown-item" style="padding: 10px 12px; cursor: pointer; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid #f1f5f9;">
                                                        <i class="fas fa-edit" style="color: #64748b; font-size: 12px;"></i>
                                                        <span>Editar prueba</span>
                                                    </div>
                                                    <div class="dropdown-item" style="padding: 10px 12px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                                                        <i class="fas fa-trash" style="color: #ef4444; font-size: 12px;"></i>
                                                        <span>Eliminar prueba</span>
                                                    </div>
                                                    <div class="dropdown-item" style="padding: 10px 12px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                                                        <i class="fas fa-eye-slash" style="color: #64748b; font-size: 12px;"></i>
                                                        <span>Ocultar prueba</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    
                                    <!-- Fila 6 - GRIS CLARA -->
                                    <tr class="table-row" style="background: #f8fafc; border-bottom: 1px solid #f1f5f9;">
                                        <td style="padding: 12px 8px 12px 0; vertical-align: middle; width: 80px;">
                                            <div style="display: flex; align-items: center; gap: 8px; width: 100%; margin-left: 0;">
                                                <input type="checkbox" id="test6Active" style="width: 16px; height: 16px; margin: 0; padding: 0; position: relative; left: 0; transform: translateX(0);">
                                                <img src="https://picsum.photos/seed/test6/24/24.jpg" alt="Test 6" style="width: 24px; height: 24px; border-radius: 4px; object-fit: cover;">
                                            </div>
                                        </td>
                                        <td style="padding: 12px; vertical-align: middle; width: auto; overflow: hidden;">
                                            <span style="font-weight: 500; color: #1f293b; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 13px;" title="Test de Conversión Mobile First">Test de Conversión Mobile First</span>
                                        </td>
                                        <td style="padding: 12px; vertical-align: middle; width: 60px; text-align: right; padding-right: 8px;">
                                            <div class="dropdown-table" style="position: relative; display: inline-block; width: 100%; text-align: right;">
                                                <button class="dropdown-trigger-ellipsis" onclick="toggleTableDropdown('test6')" style="background: none; border: none; padding: 8px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; border-radius: 4px; transition: background 0.2s; margin-left: auto;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='none'">
                                                    <i class="fas fa-ellipsis-v" style="color: #64748b; font-size: 14px;"></i>
                                                </button>
                                                <div id="test6Dropdown" class="dropdown-menu-table" style="display: none; position: absolute; top: 100%; left: -140px; z-index: 1000; background: white; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); min-width: 140px; border: 1px solid #e2e8f0;">
                                                    <div class="dropdown-item" style="padding: 10px 12px; cursor: pointer; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid #f1f5f9;">
                                                        <i class="fas fa-edit" style="color: #64748b; font-size: 12px;"></i>
                                                        <span>Editar prueba</span>
                                                    </div>
                                                    <div class="dropdown-item" style="padding: 10px 12px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                                                        <i class="fas fa-trash" style="color: #ef4444; font-size: 12px;"></i>
                                                        <span>Eliminar prueba</span>
                                                    </div>
                                                    <div class="dropdown-item" style="padding: 10px 12px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                                                        <i class="fas fa-eye-slash" style="color: #64748b; font-size: 12px;"></i>
                                                        <span>Ocultar prueba</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    
                                    <!-- Fila 7 - BLANCA -->
                                    <tr class="table-row" style="background: white; border-bottom: none;">
                                        <td style="padding: 12px 8px 12px 0; vertical-align: middle; width: 80px;">
                                            <div style="display: flex; align-items: center; gap: 8px; width: 100%; margin-left: 0;">
                                                <input type="checkbox" id="test7Active" style="width: 16px; height: 16px; margin: 0; padding: 0; position: relative; left: 0; transform: translateX(0);">
                                                <img src="https://picsum.photos/seed/test7/24/24.jpg" alt="Test 7" style="width: 24px; height: 24px; border-radius: 4px; object-fit: cover;">
                                            </div>
                                        </td>
                                        <td style="padding: 12px; vertical-align: middle; width: auto; overflow: hidden;">
                                            <span style="font-weight: 500; color: #1f293b; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 13px;" title="Test de Rendimiento API">Test de Rendimiento API</span>
                                        </td>
                                        <td style="padding: 12px; vertical-align: middle; width: 60px; text-align: right; padding-right: 8px;">
                                            <div class="dropdown-table" style="position: relative; display: inline-block; width: 100%; text-align: right;">
                                                <button class="dropdown-trigger-ellipsis" onclick="toggleTableDropdown('test7')" style="background: none; border: none; padding: 8px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; border-radius: 4px; transition: background 0.2s; margin-left: auto;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='none'">
                                                    <i class="fas fa-ellipsis-v" style="color: #64748b; font-size: 14px;"></i>
                                                </button>
                                                <div id="test7Dropdown" class="dropdown-menu-table" style="display: none; position: absolute; top: 100%; left: -140px; z-index: 1000; background: white; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); min-width: 140px; border: 1px solid #e2e8f0;">
                                                    <div class="dropdown-item" style="padding: 10px 12px; cursor: pointer; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid #f1f5f9;">
                                                        <i class="fas fa-edit" style="color: #64748b; font-size: 12px;"></i>
                                                        <span>Editar prueba</span>
                                                    </div>
                                                    <div class="dropdown-item" style="padding: 10px 12px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                                                        <i class="fas fa-trash" style="color: #ef4444; font-size: 12px;"></i>
                                                        <span>Eliminar prueba</span>
                                                    </div>
                                                    <div class="dropdown-item" style="padding: 10px 12px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                                                        <i class="fas fa-eye-slash" style="color: #64748b; font-size: 12px;"></i>
                                                        <span>Ocultar prueba</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PRODUCTOS -->
            <div class="accordion-item">
                <div class="accordion-header" onclick="toggleAccordion(this)">
                    <span><i class="fas fa-box-open"></i> Gestión de Productos</span>
                    <i class="fas fa-chevron-down arrow"></i>
                </div>
                <div class="accordion-body">

                    <!-- ACCIÓN PRINCIPAL: AÑADIR A TIENDA -->
                    <label style="display: block; font-size: 11px; font-weight: 700; color: #000000; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px;">AÑADIR PRODUCTO</label>
                                        <p style="font-size: 12px; color: #000000; margin-top: 0; margin-bottom: 12px;">Elige la sección donde quieres añadir tu producto.</p>
                    <div class="products-action-row" style="display: flex; gap: 8px; align-items: center; margin-bottom: 24px;">
                        <!-- Dropdown Filtro (Ocupa espacio restante) -->
                        <div style="flex: 1;">
                            <div class="ui-dropdown" id="sectionFilterDropdown">
                                <input type="hidden" id="masterSectionFilter" onchange="applySectionFilter(this.value)">
                                <div class="ui-trigger" onclick="toggleUI('sectionFilterDropdown')">
                                    <span class="trigger-label" id="sectionFilterLabel">Inicio (Todos)</span>
                                    <i class="fas fa-chevron-down chevron"></i>
                                </div>
                                <div class="ui-menu" id="sectionFilterMenu">
                                    <!-- Opciones dinámicas -->
                                </div>
                            </div>
                        </div>
                        
                        <!-- Botón Añadir (+) -->
                        <button id="btnNewProductContext" class="btn-add-square" onclick="openProductDrawer()" title="Nuevo Producto">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>

                    <!-- Preferencias -->
                    <label class="preferencias-title">Preferencias</label>
                    <div class="panel-toggles-row" style="display: flex; gap: 10px; margin-bottom: 20px;">
                        <!-- 3. Inventario -->
                        <button class="btn-panel-toggle" onclick="openInventoryDrawer()" title="Inventario">
                            <i class="fas fa-clipboard-list"></i>
                        </button>
                        <!-- 4. Feria Virtual -->
                        <button class="btn-panel-toggle" onclick="openFeriaDrawer()" title="Ubicación en Feria Virtual">
                            <i class="fas fa-map-marked-alt"></i>
                        </button>
                    </div>
                    
                    <!-- LISTA (ELIMINADA) -->
                    <!-- <div id="sidebarProductList" class="sidebar-list"></div> -->
                </div>
            </div>

        </div>
        
        <!-- DRAWER INICIO (CORREGIDO) -->
        <div id="homeDrawer" class="product-drawer">
            <div class="drawer-header">
                <div style="display:flex; align-items:center; gap:8px;">
                    <i class="fas fa-home" style="color:#22226B; font-size:16px;"></i>
                    <h3 style="margin:0; font-size: 15px; font-weight: 700; color: #1e293b;">Inicio</h3>
                </div>
                <button onclick="closeHomeDrawer()" style="background:none; border:none; font-size:18px; cursor:pointer; color:#22226B;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="drawer-body">

                <!-- BLOQUE 1: PORTADA (SLIDER) - CORREGIDO -->
                <div class="control-group">
                    <div class="control-group-header">
                        <label>Carrusel</label>
                        <label class="switch" title="Activar/Desactivar">
                            <input type="checkbox" id="bannerActive" onchange="updateBannerState()" {{ (!empty($tienda['mostrar_banner']) && $tienda['mostrar_banner'] == 1) ? 'checked' : '' }}>
                            <span class="slider round"></span>
                        </label>
                    </div>
                    <div class="control-group-content" id="bannerBlockContent" style="padding-top: 15px;">
                        <div style="font-size:11px; color:#64748b; margin-bottom:10px;">
                            
                        </div>
                        
                        <!-- CARRUSEL ENTERPRISE PARA BANNERS -->
                        <div class="banner-carousel-container">
                            <div class="carousel-header">
                                <div class="carousel-indicators">
                                    <span class="indicator active" data-slide="0">1</span>
                                    <span class="indicator" data-slide="1">2</span>
                                    <span class="indicator" data-slide="2">3</span>
                                    <span class="indicator" data-slide="3">4</span>
                                </div>
                            </div>
                            
                            <div class="carousel-viewport">
                                <div class="carousel-track" id="bannerCarouselTrack">
                                    <!-- SLIDE 1 -->
                                    <div class="carousel-slide active" data-slide="0">
                                        <div class="slide-content">
                                            <div id="bannerSlotContainer1" class="banner-slot-container">
                                                <div id="bannerUploader1" class="image-uploader wide <?php echo !empty($tienda['banner_imagen']) ? 'has-image' : ''; ?>">
                                                    <input type="file" id="bannerInput1" accept="image/*" hidden>
                                                    <button type="button" id="btnDeleteBanner1" class="btn-delete-image" title="Eliminar"><i class="fas fa-times"></i></button>
                                                    <?php if (!empty($tienda['banner_imagen'])): ?>
                                                        <img src="/uploads/<?php echo htmlspecialchars($tienda['banner_imagen']); ?>?v=<?php echo time(); ?>" id="bannerPreviewImg1" class="image-preview" style="display:block;">
                                                        <div class="image-placeholder" id="bannerPlaceholder1" style="display:none;"><i class="fas fa-cloud-upload-alt"></i></div>
                                                    <?php else: ?>
                                                        <div class="image-placeholder" id="bannerPlaceholder1"><i class="fas fa-cloud-upload-alt"></i></div>
                                                        <img src="" id="bannerPreviewImg1" class="image-preview" style="display:none;">
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- SLIDE 2 -->
                                    <div class="carousel-slide" data-slide="1">
                                        <div class="slide-content">
                                            <div id="bannerSlotContainer2" class="banner-slot-container">
                                                <div id="bannerUploader2" class="image-uploader wide <?php echo !empty($tienda['banner_imagen_2']) ? 'has-image' : ''; ?>">
                                                    <input type="file" id="bannerInput2" accept="image/*" hidden>
                                                    <button type="button" id="btnDeleteBanner2" class="btn-delete-image" title="Eliminar"><i class="fas fa-times"></i></button>
                                                    <?php if (!empty($tienda['banner_imagen_2'])): ?>
                                                        <img src="/uploads/<?php echo htmlspecialchars($tienda['banner_imagen_2']); ?>?v=<?php echo time(); ?>" id="bannerPreviewImg2" class="image-preview" style="display:block;">
                                                        <div class="image-placeholder" id="bannerPlaceholder2" style="display:none;"><i class="fas fa-cloud-upload-alt"></i></div>
                                                    <?php else: ?>
                                                        <div class="image-placeholder" id="bannerPlaceholder2"><i class="fas fa-cloud-upload-alt"></i></div>
                                                        <img src="" id="bannerPreviewImg2" class="image-preview" style="display:none;">
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- SLIDE 3 -->
                                    <div class="carousel-slide" data-slide="2">
                                        <div class="slide-content">
                                            <div id="bannerSlotContainer3" class="banner-slot-container">
                                                <div id="bannerUploader3" class="image-uploader wide <?php echo !empty($tienda['banner_imagen_3']) ? 'has-image' : ''; ?>">
                                                    <input type="file" id="bannerInput3" accept="image/*" hidden>
                                                    <button type="button" id="btnDeleteBanner3" class="btn-delete-image" title="Eliminar"><i class="fas fa-times"></i></button>
                                                    <?php if (!empty($tienda['banner_imagen_3'])): ?>
                                                        <img src="/uploads/<?php echo htmlspecialchars($tienda['banner_imagen_3']); ?>?v=<?php echo time(); ?>" id="bannerPreviewImg3" class="image-preview" style="display:block;">
                                                        <div class="image-placeholder" id="bannerPlaceholder3" style="display:none;"><i class="fas fa-cloud-upload-alt"></i></div>
                                                    <?php else: ?>
                                                        <div class="image-placeholder" id="bannerPlaceholder3"><i class="fas fa-cloud-upload-alt"></i></div>
                                                        <img src="" id="bannerPreviewImg3" class="image-preview" style="display:none;">
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- SLIDE 4 -->
                                    <div class="carousel-slide" data-slide="3">
                                        <div class="slide-content">
                                            <div id="bannerSlotContainer4" class="banner-slot-container">
                                                <div id="bannerUploader4" class="image-uploader wide <?php echo !empty($tienda['banner_imagen_4']) ? 'has-image' : ''; ?>">
                                                    <input type="file" id="bannerInput4" accept="image/*" hidden>
                                                    <button type="button" id="btnDeleteBanner4" class="btn-delete-image" title="Eliminar"><i class="fas fa-times"></i></button>
                                                    <?php if (!empty($tienda['banner_imagen_4'])): ?>
                                                        <img src="/uploads/<?php echo htmlspecialchars($tienda['banner_imagen_4']); ?>?v=<?php echo time(); ?>" id="bannerPreviewImg4" class="image-preview" style="display:block;">
                                                        <div class="image-placeholder" id="bannerPlaceholder4" style="display:none;"><i class="fas fa-cloud-upload-alt"></i></div>
                                                    <?php else: ?>
                                                        <div class="image-placeholder" id="bannerPlaceholder4"><i class="fas fa-cloud-upload-alt"></i></div>
                                                        <img src="" id="bannerPreviewImg4" class="image-preview" style="display:none;">
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- BLOQUE 3: SECCIONES DESTACADAS - CORREGIDO -->
                <div class="control-group">
                    <div class="control-group-header">
                        <label>Secciones Destacadas</label>
                        <label class="switch">
                            <input type="checkbox" id="featuredSectionsActive" onchange="toggleFeaturedSections(this.checked)" <?php echo (!empty($tienda['secciones_destacadas_activo']) && $tienda['secciones_destacadas_activo'] == 1) ? 'checked' : ''; ?>>
                            <span class="slider round"></span>
                        </label>
                    </div>
                    <div class="control-group-content" id="featuredSectionsContent" style="padding-top: 15px; <?php echo (empty($tienda['secciones_destacadas_activo']) || $tienda['secciones_destacadas_activo'] == 0) ? 'display:none;' : '' ?>">
                        <div class="control-group" style="margin-top:0;">
                            <div style="font-size:11px; color:#64748b; margin-bottom:10px;">Todas las secciones son visibles. Elige el estilo de visualización para tus clientes.</div>
                            <div class="ui-dropdown" id="featuredStyleDropdown">
                                <div class="ui-trigger" onclick="toggleUI('featuredStyleDropdown')">
                                    <span class="trigger-label" id="featuredStyleLabel">
                                        <?php echo (empty($tienda['secciones_destacadas_estilo']) || $tienda['secciones_destacadas_estilo'] === 'grid') ? 'Cuadrícula' : 'Carrusel'; ?>
                                    </span>
                                    <i class="fas fa-chevron-down chevron"></i>
                                </div>
                                <div class="ui-menu">
                                    <div class="ui-option <?php echo (empty($tienda['secciones_destacadas_estilo']) || $tienda['secciones_destacadas_estilo'] === 'grid') ? 'selected' : ''; ?>" onclick="selectUIOption('featuredStyleDropdown', 'grid', 'Cuadrícula', setFeaturedSectionsStyle)">Cuadrícula</div>
                                    <div class="ui-option <?php echo ($tienda['secciones_destacadas_estilo'] === 'carousel') ? 'selected' : ''; ?>" onclick="selectUIOption('featuredStyleDropdown', 'carousel', 'Carrusel', setFeaturedSectionsStyle)">Carrusel</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </div>

        <!-- DRAWER SECCIONES MOVIDO DENTRO DE SIDEBAR -->
        <div id="sectionsDrawer" class="product-drawer">
            <div class="drawer-header">
                <div style="display:flex; align-items:center; gap:8px;">
                    <i class="fas fa-cog" style="color:#22226B; font-size:16px;"></i>
                    <h3 style="margin:0; font-size: 15px; font-weight: 700; color: #1e293b;">Secciones</h3>
                </div>
                <button onclick="closeSectionsDrawer()" style="background:none; border:none; font-size:18px; cursor:pointer; color:#22226B; transition:color 0.2s;" onmouseover="this.style.color='#1e1e5e'" onmouseout="this.style.color='#22226B'">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="drawer-body">
                <!-- CREAR NUEVA -->
                <div class="new-section-box" style="background: #ffffff; border: 1px dashed #22226B; border-radius: 12px; padding: 16px; margin-bottom: 24px; box-shadow: 0 4px 12px rgba(34, 34, 107, 0.05);">
                    <label style="display: block; font-size: 11px; font-weight: 700; color: #22226B; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px;">CREAR NUEVA SECCIÓN</label>
                    <div class="input-group-pro" style="display: flex; gap: 8px;">
                        <input type="text" id="drawerNewSectionInput" placeholder="Ej: Zapatos, Ofertas..." style="flex: 1; border: 1px solid #22226B; border-radius: 8px; padding: 10px 12px; font-size: 14px; outline: none; transition: all 0.2s;">
                        <button onclick="addNewSectionFromDrawer()" style="width: 42px; background: #22226B; border: none; border-radius: 8px; font-size: 18px; color: white; cursor: pointer; transition: background 0.2s;"><i class="fas fa-plus" style="font-size: 14px;"></i></button>
                    </div>
                </div>

                <!-- LISTA -->
                <div class="active-sections-box">
                    <div id="drawerSectionsList" class="sections-list-pro" style="display: flex; flex-direction: column; gap: 10px;">
                        <!-- Dinámico -->
                    </div>
                </div>
            </div>
        </div>

        <!-- DRAWER INVENTARIO (NUEVO) -->
        <div id="inventoryDrawer" class="product-drawer">
            <div class="drawer-header">
                <div style="display:flex; align-items:center; gap:8px;">
                    <i class="fas fa-clipboard-list" style="color:#22226B; font-size:16px;"></i>
                    <h3 style="margin:0; font-size: 15px; font-weight: 700; color: #1e293b;">Inventario</h3>
                </div>
                <button onclick="closeInventoryDrawer()" style="background:none; border:none; font-size:18px; cursor:pointer; color:#22226B;">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- FILTROS FIJOS (SUB-HEADER) -->
            <div style="padding: 16px 20px 0 20px; background: white; border-bottom: 1px solid #f1f5f9; position: sticky; top: 0; z-index: 10;">
                <!-- Buscador (Fila 1) -->
                <div style="position: relative; margin-bottom: 12px;">
                    <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 13px;"></i>
                    <input type="text" id="invSearch" class="modern-input" placeholder="Buscar producto..." style="padding-left: 34px; height: 40px;" oninput="filterInventory()">
                </div>
                
                <!-- Filtros Dropdowns (Fila 2 - Separada) -->
                <div style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 16px;">
                    <!-- Filtro Sección (Bloque Completo) -->
                    <div class="ui-dropdown" id="invSectionFilter" style="width: 100%;">
                        <input type="hidden" id="invSectionVal" onchange="filterInventory()">
                        <div class="ui-trigger" onclick="toggleUI('invSectionFilter')" style="height: 36px; padding: 0 10px; font-size: 12px; width: 100%;">
                            <span class="trigger-label" id="invSectionLabel" style="text-transform: capitalize;">Todas las secciones</span>
                            <i class="fas fa-chevron-down chevron" style="font-size: 10px;"></i>
                        </div>
                        <div class="ui-menu" id="invSectionMenu" style="width: 100%;">
                            <!-- Dinámico -->
                        </div>
                    </div>
                    
                    <!-- Filtro Orden y Estado (Unificado) -->
                    <div class="ui-dropdown" id="invSortFilter" style="width: 100%;">
                        <input type="hidden" id="invSortVal" value="recent" onchange="filterInventory()">
                        <div class="ui-trigger" onclick="toggleUI('invSortFilter')" style="height: 36px; padding: 0 10px; font-size: 12px; width: 100%;">
                            <span class="trigger-label" id="invSortLabel">Más Recientes</span>
                            <i class="fas fa-chevron-down chevron" style="font-size: 10px;"></i>
                        </div>
                        <div class="ui-menu" style="width: 100%;">
                            <div class="ui-header-option" style="padding:8px 12px; font-size:10px; font-weight:700; color:#94a3b8; text-transform:uppercase;">Orden</div>
                            <div class="ui-option selected" onclick="selectUIOption('invSortFilter', 'recent', 'Más Recientes', (v)=>{document.getElementById('invSortVal').value=v; filterInventory();})">Más Recientes</div>
                            <div class="ui-option" onclick="selectUIOption('invSortFilter', 'oldest', 'Más Antiguos', (v)=>{document.getElementById('invSortVal').value=v; filterInventory();})">Más Antiguos</div>
                            <div class="ui-option" onclick="selectUIOption('invSortFilter', 'price_asc', 'Precio: Bajo a Alto', (v)=>{document.getElementById('invSortVal').value=v; filterInventory();})">Precio: Bajo a Alto</div>
                            <div class="ui-option" onclick="selectUIOption('invSortFilter', 'price_desc', 'Precio: Alto a Bajo', (v)=>{document.getElementById('invSortVal').value=v; filterInventory();})">Precio: Alto a Bajo</div>
                            
                            <div style="height:1px; background:#f1f5f9; margin:4px 0;"></div>
                            <div class="ui-option" style="color:#e11d48;" onclick="selectUIOption('invSortFilter', 'out_stock', 'Solo Sin Stock', (v)=>{document.getElementById('invSortVal').value=v; filterInventory();})">
                                <i class="fas fa-box-open" style="margin-right:6px; font-size:11px;"></i> Solo Sin Stock
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="drawer-body" style="padding-top: 10px;">
                <div id="inventoryList" style="display: flex; flex-direction: column; gap: 10px;">
                    <!-- Lista Dinámica de Productos -->
                </div>
            </div>
            
            <div class="drawer-footer" style="justify-content: space-between;">
                <span id="invCount" style="font-size: 12px; color: #64748b; font-weight: 500;">0 productos</span>
                <!-- BOTÓN NUEVO (ELIMINADO) -->
                <!-- <button class="btn-modern primary" onclick="openProductDrawer(null)" style="padding: 6px 16px; font-size: 13px; display: flex; align-items: center; gap: 6px;">
                    <i class="fas fa-plus"></i> Nuevo
                </button> -->
            </div>
        </div>

        <!-- DRAWER FERIA VIRTUAL (NUEVO) -->
        <div id="feriaDrawer" class="product-drawer">
            <div class="drawer-header">
                <div style="display:flex; align-items:center; gap:8px;">
                    <i class="fas fa-map-marked-alt" style="color:#22226B; font-size:16px;"></i>
                    <h3 style="margin:0; font-size: 15px; font-weight: 700; color: #1e293b;">Ubicación en Feria Virtual</h3>
                </div>
                <button onclick="closeFeriaDrawer()" style="background:none; border:none; font-size:18px; cursor:pointer; color:#22226B;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="drawer-body">
                <div class="control-group">
                    <label>Icono para la Feria (Cuadrado)</label>
                    <div style="font-size: 11px; color: #64748b; margin-top: -4px; margin-bottom: 12px;">Este ícono representa a tu tienda en todo el ecosistema de la feria virtual.</div>
                    <div style="display: flex; justify-content: center;">
                        <!-- LOGO FERIA (Estandarizado image-uploader.css) -->
                        <div id="feriaLogoContainer" class="image-uploader small square <?php echo !empty($tienda['logo']) ? 'has-image' : ''; ?>">
                            <input type="file" id="logoUploadInput" accept="image/*" hidden>
                            <button type="button" id="btnDeleteFeriaLogo" class="btn-delete-image" title="Eliminar"><i class="fas fa-times"></i></button>
                            
                            <?php if (!empty($tienda['logo'])): ?>
                                <img src="/uploads/logos/<?php echo htmlspecialchars($tienda['logo']); ?>?t=<?php echo time(); ?>" id="logoPreview" class="image-preview" style="display:block;">
                                <div class="image-placeholder" id="logoPlaceholder" style="display:none;"><i class="fas fa-cloud-upload-alt"></i></div>
                            <?php else: ?>
                                <div class="image-placeholder" id="logoPlaceholder"><i class="fas fa-cloud-upload-alt"></i></div>
                                <img src="" id="logoPreview" class="image-preview" style="display:none;">
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="control-separator"></div>
                <!-- SELECCIÓN CIUDAD (UI DROPDOWN) -->
                <div class="control-group">
                    <label>Ciudad</label>
                    <div class="ui-dropdown" id="ciudadDropdown">
                        <input type="hidden" id="feriaCiudad" value="<?php echo $feria_ciudad; ?>" onchange="cargarSectores()">
                        <div class="ui-trigger" onclick="toggleUI('ciudadDropdown')">
                            <span class="trigger-label" id="ciudadLabel">
                                <?php 
                                    $ciudades = [
                                        'scz' => 'Santa Cruz',
                                        'lpz' => 'La Paz',
                                        'cba' => 'Cochabamba',
                                        'tar' => 'Tarija',
                                        'oru' => 'Oruro',
                                        'pot' => 'Potosí',
                                        'chu' => 'Chuquisaca',
                                        'ben' => 'Beni',
                                        'pan' => 'Pando'
                                    ];
                                    $codeNorm = strtolower($feria_ciudad);
                                    echo isset($ciudades[$codeNorm]) ? $ciudades[$codeNorm] : '-- Seleccionar Ciudad --';
                                ?>
                            </span>
                            <i class="fas fa-chevron-down chevron"></i>
                        </div>
                        <div class="ui-menu">
                            <?php foreach($ciudades as $key => $label): ?>
                            <div class="ui-option <?php echo strtolower($feria_ciudad)===$key?'selected':''; ?>" onclick="selectUIOption('ciudadDropdown', '<?php echo $key; ?>', '<?php echo $label; ?>', (val)=>{ document.getElementById('feriaCiudad').value=val; cargarSectores(); })">
                                <?php echo $label; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- SELECCIÓN SECTOR (UI DROPDOWN - DINAMICO) -->
                <div class="control-group">
                    <label>Sector (Categoría)</label>
                    <div class="ui-dropdown" id="sectorDropdown">
                        <input type="hidden" id="feriaSector" onchange="cargarBloques()">
                        <div class="ui-trigger disabled" id="sectorTrigger" onclick="toggleUI('sectorDropdown')">
                            <span class="trigger-label" id="sectorLabel">-- Primero selecciona ciudad --</span>
                            <i class="fas fa-chevron-down chevron"></i>
                        </div>
                        <div class="ui-menu" id="sectorMenu">
                        </div>
                    </div>
                </div>

                <!-- SELECCIÓN BLOQUE (UI DROPDOWN - DINAMICO) -->
                <div class="control-group">
                    <label>Bloque</label>
                    <div class="ui-dropdown" id="bloqueDropdown">
                        <input type="hidden" id="feriaBloque" onchange="cargarGridPuestos()">
                        <div class="ui-trigger disabled" id="bloqueTrigger" onclick="toggleUI('bloqueDropdown')">
                            <span class="trigger-label" id="bloqueLabel">-- Primero selecciona sector --</span>
                            <i class="fas fa-chevron-down chevron"></i>
                        </div>
                        <div class="ui-menu" id="bloqueMenu">
                        </div>
                    </div>
                </div>

                <!-- 2. Grid Visual de Puestos (Estilo Admin) -->
                <div id="feriaGridContainer" style="display:none; margin-top:15px;">
                    <label style="font-size:12px; font-weight:600; color:#334155; margin-bottom:8px; display:block;">
                        Selecciona un puesto libre:
                    </label>
                    
                    <div class="feria-slots-grid" id="feriaSlotsGrid">
                        <div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Cargando mapa...</div>
                    </div>

                    <div class="leyenda-mapa" style="display:flex; gap:10px; margin-top:8px; font-size:10px; color:#000000;">
                        <span style="display:flex; align-items:center;"><span style="width:8px; height:8px; background:#22c55e; border-radius:50%; margin-right:4px;"></span> Tu Puesto</span>
                        <span style="display:flex; align-items:center;"><span style="width:8px; height:8px; background:#e2e8f0; border:1px dashed #cbd5e1; border-radius:2px; margin-right:4px;"></span> Libre</span>
                        <span style="display:flex; align-items:center;"><span style="width:8px; height:8px; background:#cbd5e1; border-radius:2px; margin-right:4px;"></span> Ocupado</span>
                    </div>
                </div>

                <div id="feriaStatus" style="font-size:11px; margin-top:10px; text-align:center; min-height:15px;"></div>
            </div>
            
            <div class="drawer-footer" style="justify-content: flex-end;">
                 <span style="font-size: 11px; color: #10b981; font-weight: 600; display:flex; align-items:center; gap:5px;">
                    <i class="fas fa-check"></i> Guardado automático
                 </span>
            </div>
        </div>

        <!-- PRODUCT DRAWER (MOVIDO DENTRO DE SIDEBAR) -->
        <div id="productDrawer" class="product-drawer">
            <div class="drawer-header">
                <h3 id="drawerTitle" style="margin:0; font-size: 15px; font-weight: 700; color: #1e293b;">Nuevo Producto</h3>
                <button onclick="closeProductDrawer()" style="background:none; border:none; font-size:18px; cursor:pointer; color:#22226B; transition:color 0.2s;" onmouseover="this.style.color='#1e1e5e'" onmouseout="this.style.color='#22226B'">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="drawer-body">
                <form id="formProducto" onsubmit="event.preventDefault();">
                    <input type="hidden" id="prodId">
                    <div class="form-grid">
                        <div class="control-group">
                            <label class="label">Título</label>
                            <input type="text" id="prodTitulo" class="sidebar-input" placeholder="Ej: Camisa Oxford">
                        </div>
                        <div class="control-group">
                            <label class="label">Precio (Bs)</label>
                            <input type="number" id="prodPrecio" class="sidebar-input" placeholder="0.00" step="0.01">
                        </div>
                    </div>
                    <div class="form-grid" style="margin-top:16px;">
                        <!-- SECCIÓN AUTOMÁTICA (Contextual) -->
                        <div class="control-group">
                            <label class="label">Sección</label>
                            <div class="ui-dropdown" id="prodCatTiendaDropdown">
                                <input type="hidden" id="prodCategoriaTienda" class="sidebar-input">
                                <div class="ui-trigger" onclick="toggleUI('prodCatTiendaDropdown')">
                                    <span class="trigger-label" id="prodCatTiendaLabel">Inicio (General)</span>
                                    <i class="fas fa-chevron-down chevron"></i>
                                </div>
                                <div class="ui-menu" id="prodCatTiendaMenu">
                                    <div class="ui-option" onclick="selectUIOption('prodCatTiendaDropdown', '', 'Inicio (General)', (val)=>{ document.getElementById('prodCategoriaTienda').value=val; })">Inicio (General)</div>
                                    <!-- Se llena dinámicamente con JS -->
                                </div>
                            </div>
                        </div>

                        <div class="control-group">
                            <label class="label">Categoría (Filtro)</label>
                            <div class="ui-dropdown" id="prodCatIdDropdown">
                                <input type="hidden" id="prodCategoriaId" onchange="cargarSubcategorias(this.value)">
                                <div class="ui-trigger" onclick="toggleUI('prodCatIdDropdown')">
                                    <span class="trigger-label" id="prodCatIdLabel">-- Seleccionar --</span>
                                    <i class="fas fa-chevron-down chevron"></i>
                                </div>
                                <div class="ui-menu">
                                    <div class="ui-option" onclick="selectUIOption('prodCatIdDropdown', '', '-- Seleccionar --', (val)=>{ document.getElementById('prodCategoriaId').value=val; cargarSubcategorias(val); })">-- Seleccionar --</div>
                                    <?php foreach ($categorias as $c): ?>
                                        <div class="ui-option" onclick="selectUIOption('prodCatIdDropdown', '<?php echo $c['id']; ?>', '<?php echo htmlspecialchars($c['nombre']); ?>', (val)=>{ document.getElementById('prodCategoriaId').value=val; cargarSubcategorias(val); })">
                                            <?php echo htmlspecialchars($c['nombre']); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="control-group">
                            <label class="label">Subcategoría</label>
                            <div class="ui-dropdown" id="prodSubcatIdDropdown">
                                <input type="hidden" id="prodSubcategoriaId">
                                <div class="ui-trigger disabled" id="prodSubcatTrigger" onclick="toggleUI('prodSubcatIdDropdown')">
                                    <span class="trigger-label" id="prodSubcatLabel">-- Primero selecciona categoría --</span>
                                    <i class="fas fa-chevron-down chevron"></i>
                                </div>
                                <div class="ui-menu" id="prodSubcatMenu">
                                    <!-- Dinámico -->
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-grid" style="margin-top:16px;">
                        <div class="control-group">
                            <label class="label">Estado</label>
                            <div class="ui-dropdown" id="prodCondicionDropdown">
                                <input type="hidden" id="prodCondicion" class="sidebar-input" value="Nuevo">
                                <div class="ui-trigger" onclick="toggleUI('prodCondicionDropdown')">
                                    <span class="trigger-label" id="prodCondicionLabel">Nuevo</span>
                                    <i class="fas fa-chevron-down chevron"></i>
                                </div>
                                <div class="ui-menu">
                                    <div class="ui-option selected" onclick="selectUIOption('prodCondicionDropdown', 'Nuevo', 'Nuevo', (val)=>{ document.getElementById('prodCondicion').value=val; })">Nuevo</div>
                                    <div class="ui-option" onclick="selectUIOption('prodCondicionDropdown', 'Como Nuevo', 'Como Nuevo', (val)=>{ document.getElementById('prodCondicion').value=val; })">Como Nuevo</div>
                                    <div class="ui-option" onclick="selectUIOption('prodCondicionDropdown', 'Buen Estado', 'Buen Estado', (val)=>{ document.getElementById('prodCondicion').value=val; })">Buen Estado</div>
                                    <div class="ui-option" onclick="selectUIOption('prodCondicionDropdown', 'Aceptable', 'Aceptable', (val)=>{ document.getElementById('prodCondicion').value=val; })">Aceptable</div>
                                </div>
                            </div>
                        </div>
                        <div class="control-group">
                            <label class="label">Badges</label>
                            <div id="badgesMultiSelect"></div>
                            <input type="hidden" id="badgesInput" name="badges[]" value="">
                        </div>
                    </div>
                    <div class="control-group" style="margin-top:16px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                            <label class="label" style="margin-bottom:0;">Descripción</label>
                            <button type="button" class="btn-text-ai" onclick="generarDescripcionIA()">
                                <i class="fas fa-magic"></i> TextAI
                            </button>
                        </div>
                        <textarea id="prodDescripcion" class="sidebar-input" rows="5" placeholder="Detalles del producto..."></textarea>
                    </div>
                    <div class="control-group" style="margin-top:16px;">
                        <label class="label">Imágenes (Máx 5)</label>
                        
                        <!-- Grid de Previsualización (Mantenemos lógica actual) -->
                        <div id="prodImgPreview" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 12px;"></div>
                        
                        <!-- Zona de Carga (Estilo Unificado) -->
                        <div id="prodImgZone" class="image-uploader landscape" style="display:flex; align-items:center; justify-content:center;">
                            <input type="file" id="prodImagenes" multiple accept="image/*" hidden>
                            <div class="image-placeholder">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="drawer-footer">
                <button class="btn-modern secondary" onclick="closeProductDrawer()" style="width: 48%;">Cancelar</button>
                <button class="btn-modern primary" id="btnGuardarProducto" onclick="guardarProducto()" style="width: 48%;">Guardar Producto</button>
            </div>
        </div>
    </aside>

    <!-- LIVE CANVAS -->
    <main class="editor-canvas-container">
        <button id="expandBtn" onclick="toggleSidebar()" style="position:absolute; top:20px; left:20px; z-index:100; background:white; border:1px solid #ccc; padding:10px; border-radius:50%; box-shadow:0 4px 12px rgba(0,0,0,0.1); cursor:pointer; display:none;">
            <i class="fas fa-pen"></i>
        </button>
        <div class="canvas-wrapper">
            <iframe id="storeFrame" src="/tienda/<?php echo htmlspecialchars($tienda['slug']); ?>?editor_mode=1" class="store-iframe desktop"></iframe>
        </div>
    </main>

</div>

<!-- MODAL SOCIALES -->
<div id="socialModal" class="modal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header"><h3>Redes Sociales</h3><button onclick="document.getElementById('socialModal').style.display='none'">&times;</button></div>
        <div class="modal-body">
            <input type="hidden" id="inputFacebook" value="<?php echo htmlspecialchars($tienda['facebook_url'] ?? ''); ?>">
            <input type="hidden" id="inputInstagram" value="<?php echo htmlspecialchars($tienda['instagram_url'] ?? ''); ?>">
            <input type="hidden" id="inputTiktok" value="<?php echo htmlspecialchars($tienda['tiktok_url'] ?? ''); ?>">
            <div class="control-group"><label>Facebook</label><input type="text" class="sidebar-input" onchange="updateSocialHidden('facebook', this.value)" value="<?php echo htmlspecialchars($tienda['facebook_url'] ?? ''); ?>"></div>
            <div class="control-group"><label>Instagram</label><input type="text" class="sidebar-input" onchange="updateSocialHidden('instagram', this.value)" value="<?php echo htmlspecialchars($tienda['instagram_url'] ?? ''); ?>"></div>
        </div>
    </div>
</div>

<!-- DRAWER SECCIONES MOVIDO DENTRO DE SIDEBAR -->

<!-- PRODUCT DRAWER -->


<script>


    <?php
    $initial_state = [
        'color' => $color_primario,
        'mostrar_logo' => !isset($tienda['mostrar_logo']) || (bool)$tienda['mostrar_logo'],
        'mostrar_nombre' => !isset($tienda['mostrar_nombre']) || (bool)$tienda['mostrar_nombre'],
        'opacidad' => !empty($tienda['opacidad_botones']) ? intval($tienda['opacidad_botones']) : 12,
        'estiloBordes' => $estilo_bordes,
        'estiloFondo' => $estilo_fondo,
        'estiloFotos' => $estilo_fotos,
        'tipografia' => $tipografia,
        'tamanoTexto' => $tamano_texto,
        'estiloTarjetas' => $estilo_tarjetas,
        'logo_principal' => $tienda['logo_principal'] ?? null,
        'seccionesDestacadas' => [
            'activo' => !empty($tienda['secciones_destacadas_activo']) && $tienda['secciones_destacadas_activo'] == 1,
            'estilo' => !empty($tienda['secciones_destacadas_estilo']) ? $tienda['secciones_destacadas_estilo'] : 'grid'
        ],
        'banner' => [
            'activo' => !empty($tienda['mostrar_banner']),
            'imagenes' => [
                !empty($tienda['banner_imagen']) ? '/uploads/'.$tienda['banner_imagen'].'?v='.time() : null,
                !empty($tienda['banner_imagen_2']) ? '/uploads/'.$tienda['banner_imagen_2'].'?v='.time() : null,
                !empty($tienda['banner_imagen_3']) ? '/uploads/'.$tienda['banner_imagen_3'].'?v='.time() : null,
                !empty($tienda['banner_imagen_4']) ? '/uploads/'.$tienda['banner_imagen_4'].'?v='.time() : null
            ]
        ],
        'deptCode' => $deptCode,
        'munCode' => $munCode,
        'categoriaDefaultId' => !empty($tienda['categoria_default_id']) ? intval($tienda['categoria_default_id']) : null,
        'navbarStyle' => $tienda['navbar_style'] ?? 'blanco',
        'gridDensity' => (($tienda['grid_density'] ?? 3) == 0) ? 'auto' : ($tienda['grid_density'] ?? 3)
    ];
    ?>
    window.tiendaState = <?php echo json_encode($initial_state, JSON_PRETTY_PRINT); ?>;
    window.menuItems = <?php echo json_encode($menu_items); ?>;
    window.allProducts = <?php echo json_encode($productos); ?>;
    window.availableBadges = <?php echo json_encode($available_badges); ?>;
    window.storeInfo = {
        name: <?php echo json_encode($tienda['nombre']); ?>,
        slug: <?php echo json_encode($tienda['slug']); ?>,
        id: <?php echo json_encode($tienda['usuario_id']); ?>,
        logo: <?php echo json_encode(!empty($tienda['logo']) ? '/uploads/logos/'.$tienda['logo'].'?t='.time() : ''); ?>
    };
</script>
<script>
/**
 * Carrusel Enterprise para Banners
 * Funcionalidad de navegación y control del carrusel
 */
class BannerCarousel {
    constructor() {
        this.currentSlide = 0;
        this.totalSlides = 4; // Actualizado a 4 banners
        this.track = null;
        this.slides = [];
        this.indicators = [];
        this.prevBtn = null;
        this.nextBtn = null;
        
        this.init();
    }
    
    init() {
        // Esperar a que el DOM esté listo
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setupCarousel());
        } else {
            this.setupCarousel();
        }
    }
    
    setupCarousel() {
        this.track = document.getElementById('bannerCarouselTrack');
        if (!this.track) return;
        
        this.slides = this.track.querySelectorAll('.carousel-slide');
        this.indicators = document.querySelectorAll('.indicator');
        
        
        // Configurar eventos en indicadores
        this.indicators.forEach((indicator, index) => {
            indicator.addEventListener('click', () => this.goToSlide(index));
        });
        
        // Actualizar estado inicial
        this.updateCarousel();
        
        // Configurar navegación con teclado
        document.addEventListener('keydown', (e) => {
            if (e.target.closest('.banner-carousel-container')) {
                if (e.key === 'ArrowLeft') this.navigate('prev');
                if (e.key === 'ArrowRight') this.navigate('next');
            }
        });
        
        // Soporte para touch/swipe en móviles
        this.setupTouchSupport();
        
        console.log('Banner Carousel initialized successfully (indicators only)');
    }
    
    navigate(direction) {
        if (direction === 'prev') {
            this.currentSlide = this.currentSlide > 0 ? this.currentSlide - 1 : this.totalSlides - 1;
        } else {
            this.currentSlide = this.currentSlide < this.totalSlides - 1 ? this.currentSlide + 1 : 0;
        }
        
        this.updateCarousel();
    }
    
    goToSlide(index) {
        if (index >= 0 && index < this.totalSlides) {
            this.currentSlide = index;
            this.updateCarousel();
        }
    }
    
    updateCarousel() {
        // Actualizar posición del track
        const translateX = -this.currentSlide * 100;
        this.track.style.transform = `translateX(${translateX}%)`;
        
        // Actualizar slides
        this.slides.forEach((slide, index) => {
            slide.classList.toggle('active', index === this.currentSlide);
        });
        
        // Actualizar indicadores
        this.indicators.forEach((indicator, index) => {
            indicator.classList.toggle('active', index === this.currentSlide);
        });
        
        // Actualizar estado de botones
        if (this.prevBtn) this.prevBtn.disabled = false;
        if (this.nextBtn) this.nextBtn.disabled = false;
    }
    
    setupTouchSupport() {
        let startX = 0;
        let endX = 0;
        let threshold = 50; // Umbral para swipe
        
        this.track.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
        }, { passive: true });
        
        this.track.addEventListener('touchend', (e) => {
            endX = e.changedTouches[0].clientX;
            const diff = startX - endX;
            
            if (Math.abs(diff) > threshold) {
                if (diff > 0) {
                    this.navigate('next'); // Swipe izquierda = siguiente
                } else {
                    this.navigate('prev'); // Swipe derecha = anterior
                }
            }
        }, { passive: true });
    }
}

// Función global para navegación (usada en onclick)
function navigateBannerCarousel(direction) {
    if (window.bannerCarousel) {
        window.bannerCarousel.navigate(direction);
    }
}

// Inicializar el carrusel cuando se cargue la página
document.addEventListener('DOMContentLoaded', () => {
    window.bannerCarousel = new BannerCarousel();
    
    // Funciones para la tabla de pruebas
    window.toggleTableDropdown = function(testId) {
        const dropdown = document.getElementById(testId + 'Dropdown');
        const allDropdowns = document.querySelectorAll('.dropdown-menu-table');
        
        // Cerrar otros dropdowns
        allDropdowns.forEach(d => {
            if (d !== dropdown) {
                d.style.display = 'none';
            }
        });
        
        // Toggle actual
        if (dropdown.style.display === 'none') {
            dropdown.style.display = 'block';
        } else {
            dropdown.style.display = 'none';
        }
    };
    
    window.editTableTest = function(testId) {
        console.log('Editar prueba:', testId);
        // Solo mostrar opción, sin funcionalidad real
    };
    
    window.deleteTableTest = function(testId) {
        console.log('Eliminar prueba:', testId);
        // Solo mostrar opción, sin funcionalidad real
    };
    
    window.hideTableTest = function(testId) {
        console.log('Ocultar prueba:', testId);
        // Solo mostrar opción, sin funcionalidad real
    };
    
    // Cerrar dropdowns al hacer clic fuera
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown-table')) {
            document.querySelectorAll('.dropdown-menu-table').forEach(d => {
                d.style.display = 'none';
            });
        }
    });
});
</script>

<script src="/assets/js/ImageUploader.js?v=<?php echo time(); ?>"></script>
<script src="/assets/js/image-uploader-safe-init.js?v=<?php echo time(); ?>"></script>
<script src="/assets/js/ui-multiselect.js?v=<?php echo time(); ?>"></script>
<script src="/assets/js/editor-utils.js"></script>
<script src="/assets/js/modules/badge-system.js?v=<?php echo time(); ?>"></script>
<script src="/assets/js/modules/ui-components.js?v=<?php echo time(); ?>"></script>
<script src="/assets/js/modules/iframe-communicator.js?v=<?php echo time(); ?>"></script>
<script src="/assets/js/modules/image-manager.js?v=<?php echo time(); ?>"></script>
<script src="/assets/js/modules/product-editor-core.js?v=<?php echo time(); ?>"></script>
<script src="/assets/js/editor-tienda.js?v=<?php echo time(); ?>"></script>
<script src="/assets/js/modules/realtime-communicator.js?v=<?php echo time(); ?>"></script>
<script src="/assets/js/modules/product-sync-manager.js?v=<?php echo time(); ?>"></script>
<script src="/assets/js/modules/editor-integration.js?v=<?php echo time(); ?>"></script>
<link rel="stylesheet" href="/assets/css/ui-multiselect.css?v=<?php echo time(); ?>">
</body>
</html>