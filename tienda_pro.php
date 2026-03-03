<?php
/**
 * DONE! - Vista Pública de Tienda Profesional
 * Versión Híbrida Corregida: Diseño del Respaldo (3:00 PM) + Lógica de Suspensión (11:30 PM)
 */

// CRÍTICO: Iniciar sesión PRIMERO
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Obtener slug
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

if (empty($slug)) {
    header('Location: /');
    exit;
}

try {
    $db = getDB();

    // 1. Obtener información de la tienda (SIN filtrar por estado activo para manejar suspensiones - Lógica 11:30 PM)
    $stmt = $db->prepare("SELECT * FROM tiendas WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug]);
    $tienda = $stmt->fetch();

    if (!$tienda) {
        http_response_code(404);
        echo "<!DOCTYPE html><html><head><title>Tienda no encontrada</title></head><body><h1>Tienda no encontrada</h1><p>La tienda que buscas no existe.</p></body></html>";
        exit;
    }

    // 2. Manejo de Suspensión (Lógica 11:30 PM)
    if ($tienda['estado'] === 'suspendido') {
        // Verificar si ya expiró la suspensión
        if (!empty($tienda['suspension_fin'])) {
            $fin = new DateTime($tienda['suspension_fin']);
            $ahora = new DateTime();
            if ($ahora > $fin) {
                // Auto-Unban: La suspensión expiró
                $stmtUpdate = $db->prepare("UPDATE tiendas SET estado = 'activo', suspension_fin = NULL WHERE id = ?");
                $stmtUpdate->execute([$tienda['id']]);
                
                // Actualizar estado local y continuar carga normal
                $tienda['estado'] = 'activo';
                // Recargar página para asegurar limpieza
                header("Refresh:0");
                exit;
            }
        }

        // Si sigue suspendida, mostrar vista de "No Disponible"
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Tienda no disponible - Done!</title>
        
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: #f8f9fa;
                    height: 100vh;
                    margin: 0;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: #333;
                }
                .container {
                    text-align: center;
                    padding: 40px;
                    background: white;
                    border-radius: 16px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
                    max-width: 500px;
                    width: 90%;
                }
                .icon {
                    font-size: 64px;
                    color: #ccc;
                    margin-bottom: 24px;
                }
                h1 {
                    font-size: 24px;
                    margin-bottom: 12px;
                    color: #1a1a1a;
                }
                p {
                    color: #666;
                    font-size: 16px;
                    line-height: 1.5;
                    margin-bottom: 0;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <i class="fas fa-store-slash icon"></i>
                <h1>Tienda no disponible</h1>
                <p>Esta tienda no se encuentra disponible temporalmente.</p>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

    // Si no está activa ni suspendida (ej: eliminada, pendiente)
    if ($tienda['estado'] !== 'activo') {
        http_response_code(404);
        echo "Tienda no encontrada o inactiva.";
        exit;
    }

    // 3. Incrementar visitas (Lógica Respaldo 3:00 PM)
    if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_id'] != $tienda['usuario_id']) {
        $stmt_visitas = $db->prepare("UPDATE tiendas SET visitas = visitas + 1 WHERE id = ?");
        $stmt_visitas->execute([$tienda['id']]);
    }

    // 4. Obtener productos (SIN DUPLICADOS - VERSIÓN CORREGIDA)
    $stmt_productos = $db->prepare("
        SELECT DISTINCT p.*,
               (SELECT nombre_archivo FROM producto_imagenes
                WHERE producto_id = p.id
                ORDER BY es_principal DESC, orden ASC
                LIMIT 1) AS imagen_principal,
               (SELECT GROUP_CONCAT(badge_id SEPARATOR ',') FROM producto_badges
                WHERE producto_id = p.id) AS badges
        FROM productos p
        WHERE p.usuario_id = ? AND p.activo = 1
        GROUP BY p.id
        ORDER BY p.destacado DESC, p.fecha_publicacion DESC
    ");
    $stmt_productos->execute([$tienda['usuario_id']]);
    $productos = $stmt_productos->fetchAll();
    
    // DEBUG: Mostrar array completo antes de procesar
    echo "<div style='background: orange; padding: 10px; margin: 10px 0;'>";
    echo "<strong>DEBUG ARRAY COMPLETO:</strong><br>";
    echo "<pre>";
    var_dump($productos);
    echo "</pre>";
    echo "</div>";
    
    // Procesar badges para cada producto
    foreach ($productos as &$producto) {
        if (!empty($producto['badges'])) {
            $producto['badges'] = explode(',', $producto['badges']);
        } else {
            $producto['badges'] = [];
        }
    }

// --- LÓGICA PARA SECCIONES DESTACADAS ---
$secciones_con_imagenes = [];
if (!empty($tienda['menu_items'])) {
    $menu_items_decoded = json_decode($tienda['menu_items'], true);
    $secciones_procesadas = []; // Para evitar duplicados

    foreach ($menu_items_decoded as $item) {
        if (!isset($item['label'])) continue;
        $nombre_seccion_lower = strtolower(trim($item['label']));

        if (in_array($nombre_seccion_lower, ['todos', 'inicio', ''])) continue;
        if (in_array($nombre_seccion_lower, $secciones_procesadas)) continue;

        $imagen_para_seccion = '/assets/img/placeholder-seccion.jpg'; // Placeholder por defecto
        
        // Buscar la primera imagen de un producto en esta sección
        foreach ($productos as $p) {
            $categoria_tienda_lower = isset($p['categoria_tienda']) ? strtolower(trim($p['categoria_tienda'])) : '';
            if ($categoria_tienda_lower === $nombre_seccion_lower && !empty($p['imagen_principal'])) {
                $imagen_para_seccion = '/uploads/' . $p['imagen_principal'];
                break; 
            }
        }
        
        $secciones_con_imagenes[] = [
            'label' => $item['label'],
            'imagen' => $imagen_para_seccion
        ];
        $secciones_procesadas[] = $nombre_seccion_lower;
    }
}

    // 5. Menú de navegación (RESTAURADO DEL RESPALDO 3:00 PM)
    // Se respeta la personalización del usuario en lugar de generar dinámicamente
    $menu_items = !empty($tienda['menu_items']) ? json_decode($tienda['menu_items'], true) : [['label' => 'Todos', 'url' => '#']];

    // 6. Sistema de Temas (Lógica Respaldo 3:00 PM)
    $opacidad = isset($tienda['opacidad_botones']) ? (int)$tienda['opacidad_botones'] : 12;
    // Asegurar rango válido (5% a 50%)
    $opacidad = max(5, min(50, $opacidad));
    $opacidad_decimal = $opacidad / 100;

    // Definir color del widget según el tema
    $color_widget = !empty($tienda['color_primario']) ? $tienda['color_primario'] : '#1a73e8'; 
    $color_widget_text = '#ffffff';

    // Función auxiliar para convertir HEX a RGB
    if (!function_exists('hex2rgb')) {
        function hex2rgb($hex) {
            $hex = str_replace("#", "", $hex);
            if(strlen($hex) == 3) {
                $r = hexdec(substr($hex,0,1).substr($hex,0,1));
                $g = hexdec(substr($hex,1,1).substr($hex,1,1));
                $b = hexdec(substr($hex,2,1).substr($hex,2,1));
            } else {
                $r = hexdec(substr($hex,0,2));
                $g = hexdec(substr($hex,2,2));
                $b = hexdec(substr($hex,4,2));
            }
            return "$r, $g, $b";
        }
    }
    $color_widget_rgb = hex2rgb($color_widget);

    // --- IDENTIDAD VISUAL 2.0 ---
    $estilo_bordes = $tienda['estilo_bordes'] ?? 'suave';
    $estilo_fondo = $tienda['estilo_fondo'] ?? 'blanco';
    $tipografia = $tienda['tipografia'] ?? 'system';
    $tamano_texto = $tienda['tamano_texto'] ?? 'normal';
    $estilo_tarjetas = $tienda['estilo_tarjetas'] ?? 'elevada';
    $estilo_fotos = $tienda['estilo_fotos'] ?? 'cuadrado';

    // 6. Grid Density (NUEVO)
    $grid_density = $tienda['grid_density'] ?? 3;
    $grid_template_columns = 'repeat(auto-fill, minmax(240px, 1fr))'; // Default para 'auto' (0)
    if ($grid_density > 0) {
        $grid_template_columns = "repeat({$grid_density}, 1fr)";
    }

    // 1. Bordes
    $radius_btn = match($estilo_bordes) {
        'recto' => '0px',
        'pill' => '50px',
        default => '8px'
    };
    $radius_card = match($estilo_bordes) {
        'recto' => '0px',
        'pill' => '24px',
        default => '12px'
    };

    // 2. Tipografía
    $font_family = match($tipografia) {
        'inter' => "'Inter', sans-serif",
        'jakarta' => "'Plus Jakarta Sans', sans-serif",
        'manrope' => "'Manrope', sans-serif",
        'tech' => "'Space Mono', monospace",
        'modern' => "'Poppins', sans-serif",
        'minimal' => "'Roboto', sans-serif",
        'classic' => "'Lora', serif",
        'bold' => "'Montserrat', sans-serif",
        'outfit' => "'Outfit', sans-serif",
        default => "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif"
    };

    // 3. Tarjetas
    $card_shadow = '0 10px 30px rgba(0,0,0,0.08)';
    $card_border = '1px solid var(--border-color)';
    
    if ($estilo_tarjetas === 'flat') {
        $card_shadow = 'none';
        $card_border = '1px solid var(--border-color)';
    } elseif ($estilo_tarjetas === 'borde') {
        $card_shadow = 'none';
        $card_border = '2px solid var(--border-color)';
    } elseif ($estilo_tarjetas === 'elevada') {
        $card_border = 'none';
    }

    // 4. Fondo (Patrones CSS)
    $bg_body_override = '';
    if ($estilo_fondo === 'tintado') {
        $bg_body_override = "background-color: rgba({$color_widget_rgb}, 0.03);";
    } elseif ($estilo_fondo === 'gris') {
        $bg_body_override = "background-color: #f1f5f9;"; // Gris suave estándar
    }

    // 5. Fotos
    $img_aspect = '1 / 1';
    $img_fit = 'cover';
    $img_height = 'auto'; // Controla altura fija si es necesario

    if ($estilo_fotos === 'vertical') {
        $img_aspect = '3 / 4';
    } elseif ($estilo_fotos === 'horizontal') {
        $img_aspect = '4 / 3';
    } elseif ($estilo_fotos === 'natural') {
        $img_aspect = '1 / 1'; // Contenedor cuadrado base
        $img_fit = 'contain';
    } else {
        // Cuadrado por defecto
        $img_aspect = '1 / 1';
    }

} catch (Exception $e) {
    error_log("Error en tienda_pro.php: " . $e->getMessage());
    http_response_code(500);
    echo "<!DOCTYPE html><html><head><title>Error</title></head><body><h1>Error al cargar la tienda</h1><p>Por favor intenta nuevamente.</p></body></html>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($tienda['nombre']); ?> - Done! Bolivia</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&family=Plus+Jakarta+Sans:wght@400;500;700&family=Manrope:wght@400;500;700&family=Outfit:wght@300;500;700&family=Poppins:wght@300;400;500;600&family=Space+Mono&family=Roboto:wght@300;400;500;700&family=Lora:wght@400;600&family=Montserrat:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/tienda-pro.css?v=<?php echo time(); ?>">
    <style>
        .store-header {
            display: flex;
            align-items: center;
            gap: 12px; /* Espacio entre logo y nombre */
        }
        .logo-container-principal {
            display: flex;
            align-items: center;
        }
        .store-logo-principal {
            /* Constraint applied DIRECTLY to the image */
            max-height: 48px; /* Standard navbar height */
            max-width: 200px; /* Prevent overly wide logos */
            width: auto;
            height: auto;
            object-fit: contain;
        }

        .menu-item {
            position: relative; /* CRUCIAL: Contenedor para el subrayado */
        }

        /* --- ESTILOS PARA NAVBAR DE MARCA --- */
        .navbar-marca {
            background-color: var(--color-widget) !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        /* Anula el fondo para la página activa (el hover no lo necesita con esta técnica) */
        .navbar-marca .menu-item.active {
            background-color: transparent !important;
        }

        /* Base del indicador de subrayado: invisible por defecto, con transición */
        .navbar-marca .menu-item::after {
            content: '';
            position: absolute;
            bottom: 4px; /* Ajusta la posición vertical del subrayado */
            left: 8px;
            right: 8px;
            height: 3px; /* Grosor del subrayado */
            border-radius: 2px;
            opacity: 0;
            transition: opacity 0.2s ease-in-out;
        }
        
        /* Subrayado visible para el item activo */
        .navbar-marca .menu-item.active::after {
            opacity: 1;
        }

        /* Subrayado semi-transparente al pasar el mouse sobre un item inactivo */
        .navbar-marca .menu-item:not(.active):hover::after {
            opacity: 0.5;
        }

        /* Color de texto y SUBRAYADO claro (para fondos oscuros) */
        .navbar-marca.navbar-text-light .store-name,
        .navbar-marca.navbar-text-light .menu-item {
            color: white !important;
        }
        .navbar-marca.navbar-text-light .menu-item::after {
            background-color: white !important;
        }

        /* Color de texto y SUBRAYADO oscuro (para fondos claros) */
        .navbar-marca.navbar-text-dark .store-name,
        .navbar-marca.navbar-text-dark .menu-item {
            color: #000000 !important;
        }
        .navbar-marca.navbar-text-dark .menu-item::after {
            background-color: #000000 !important;
        }

        .hidden-by-logic {
            display: none !important;
        }

        :root {
            /* Variable de Widget Dinámica según Tema */
            --color-widget: <?php echo htmlspecialchars($color_widget); ?>;
            --color-widget-rgb: <?php echo $color_widget_rgb; ?>; /* Nueva variable RGB */
            --color-widget-text: <?php echo htmlspecialchars($color_widget_text); ?>;
            
            /* Identidad Visual 2.0 */
            --border-radius-btn: <?php echo $radius_btn; ?>;
            --border-radius-card: <?php echo $radius_card; ?>;
            --font-family-main: <?php echo $font_family; ?>;
            --card-shadow: <?php echo $card_shadow; ?>;
            --card-border: <?php echo $card_border; ?>;
            --img-aspect-ratio: <?php echo $img_aspect; ?>;
            --img-object-fit: <?php echo $img_fit; ?>;

            /* [FIX] Grid Density */
            --product-grid-template: <?php echo $grid_template_columns; ?>;

            /* COLORES FIJOS PARA ELEMENTOS DE INTERFAZ */
            --spinner-color: #FF6B35;
            --thumb-active-border: #000000;
            --logo-placeholder-bg: #f0f0f0;
            --logo-placeholder-icon: #999999;

            /* Variables de Tema - Por defecto Claro */
            --bg-body: #ffffff;
            --bg-card: #ffffff;
            --text-main: #202124;
            --text-secondary: #5f6368;
            --border-color: #dadce0;
            --nav-bg: #ffffff;
            --nav-text: #202124;
            --input-bg: #f1f3f4;
            --input-border: #dadce0;
            
            /* Colores específicos de producto */
            --product-title: #202124;
            --product-price: #202124;
            --footer-text: #5f6368;

            /* Variables de Botones - Tinted Dinámico (SOLO TEMA CLARO) */
            /* El fondo es TINTADO DIRECTAMENTE para evitar sombras dobles */
            --btn-bg-hover: rgba(var(--color-widget-rgb), <?php echo $opacidad_decimal; ?>);
            --btn-bg-active: rgba(var(--color-widget-rgb), <?php echo $opacidad_decimal; ?>);
            --btn-text-active: #202124;
            --btn-border: transparent;
            /* Eliminamos la sombra para usar solo el background tintado */
            --btn-shadow: none;
        }

        /* ESCALADO DE FUENTES (Overrides) */
        body.size-small { font-size: 14px; }
        body.size-small .product-title { font-size: 14px; }
        body.size-small .product-price { font-size: 18px; }
        body.size-small .modal-title { font-size: 24px; }
        body.size-small .menu-item { font-size: 14px; }

        body.size-large { font-size: 18px; }
        body.size-large .product-title { font-size: 18px; }
        body.size-large .product-price { font-size: 24px; }
        body.size-large .modal-title { font-size: 32px; }
        body.size-large .menu-item { font-size: 18px; }

        /* BLINDAJE TOTAL DEL FOOTER DONE! */
        .done-footer-wrapper {
            background-color: #ffffff !important; /* Fondo blanco inmutable */
            border-top: 1px solid #e5e7eb !important;
            padding: 0 !important; /* Sin padding global para control total flex */
            margin-top: auto !important;
            width: 100% !important;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif !important;
            font-size: 12px !important; /* AJUSTADO a 12px */
            color: #6b7280 !important;
            text-align: center !important;
            position: relative !important;
            z-index: 9999 !important;
            height: 42px !important; /* Altura ajustada a 42px */
            display: flex !important;
            justify-content: center !important;
            align-items: center !important;
        }
        
        .done-footer-content {
            display: flex !important;
            justify-content: center !important;
            align-items: center !important;
            gap: 12px !important;
            line-height: normal !important; /* Reset de line-height */
            height: 100% !important;
        }

        .done-link {
            color: #6b7280 !important; /* Gris como el texto original */
            text-decoration: none !important;
            font-weight: 400 !important;
        }
        .done-link strong {
            font-weight: 700 !important;
            color: #374151 !important;
        }
        .done-link:hover {
            color: var(--color-widget) !important;
        }

        .done-report-link {
            color: #6b7280 !important;
            text-decoration: none !important;
            display: flex !important;
            align-items: center !important;
            gap: 4px !important;
        }
        .done-report-link:hover {
            text-decoration: underline !important;
        }





        body {
            font-family: var(--font-family-main);
            background: var(--bg-body);
            <?php echo $bg_body_override; ?>
            color: var(--text-main);
            min-height: 100vh;
        }

        /* --- HERO BANNER (PORTADA) INLINE FIX --- */
        /* ELIMINADO PARA USAR CSS EXTERNO DE SLIDER */

        /* ESTILOS DE MODO EDITOR */
        <?php if (isset($_GET['editor_mode'])): ?>
        .editable-highlight {
            position: relative;
            transition: all 0.2s ease;
            border-radius: 4px;
            padding: 2px 4px; /* Un poco de padding para que no quede pegado */
        }
        .editable-highlight:hover {
            background-color: rgba(255, 255, 255, 0.8);
            outline: 2px dashed var(--color-widget);
            cursor: text;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .editable-highlight:focus {
            background-color: #ffffff;
            outline: 2px solid var(--color-widget);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 10;
        }
        /* Indicador visual de elemento editable */
        .editable-highlight::after {
            content: "\f303"; /* Icono de lápiz FontAwesome */
            font-family: "Font Awesome 5 Free";
            font-weight: 900;
            position: absolute;
            top: -10px;
            right: -10px;
            font-size: 10px;
            background: var(--color-widget);
            color: var(--color-widget-text);
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.2s;
            pointer-events: none;
        }
        .editable-highlight:hover::after {
            opacity: 1;
        }


        <?php endif; ?>

        /* Fix for Ghost Badges */
        #ghostBadgesContainer {
            position: absolute;
            top: 8px;
            left: 8px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            z-index: 10; /* Aumentado para estar por encima */
        }
        #ghostBadgesContainer .badge-shein-floating {
             position: relative; /* Cambiado de absolute a relative */
             display: flex;
             align-items: center;
             background-color: rgba(255, 255, 255, 0.9);
             color: #222;
             padding: 3px 8px;
             border-radius: 4px;
             font-size: 11px;
             font-weight: 500;
             backdrop-filter: blur(5px);
             box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        #ghostBadgesContainer .badge-shein-floating .truck-icon {
            width: 16px;
            height: auto;
            margin-right: 4px;
        }
        #ghostBadgesContainer .oferta-badge { background-color: #f59e0b; color: white; }
        #ghostBadgesContainer .novedad-badge { background-color: #3b82f6; color: white; }
        #ghostBadgesContainer .oferta-badge i, #ghostBadgesContainer .novedad-badge i { margin-right: 4px; }


    </style>
</head>
<body class="<?php echo $tamano_texto !== 'normal' ? 'size-'.htmlspecialchars($tamano_texto) : ''; ?>">

    <nav class="store-navbar">
        <div class="navbar-container">
            <a href="/tienda/<?php echo htmlspecialchars($slug); ?>" class="store-header">
                <div id="principalLogoContainer" class="logo-container-principal <?php if (empty($tienda['mostrar_logo'])) echo 'hidden-by-logic'; ?>">
                    <img src="/uploads/logos/<?php echo htmlspecialchars($tienda['logo_principal']); ?>" id="principalLogoImage" class="store-logo-principal" alt="<?php echo htmlspecialchars($tienda['nombre']); ?>" style="<?php if (empty($tienda['logo_principal'])) echo 'display:none;'; ?>">
                    <?php if (isset($_GET['editor_mode'])): ?>
                    <div id="principalLogoPlaceholder" class="store-logo-placeholder-editor" style="width: 150px; height: 48px; border: 2px dashed #e2e8f0; border-radius: 8px; <?php if (!empty($tienda['logo_principal'])) echo 'display:none;'; ?>">
                        <i class="fas fa-image"></i>
                        <span>Tu logo se verá aquí</span>
                    </div>
                    <?php endif; ?>
                </div>
        <span class="store-name <?php if (isset($tienda['mostrar_nombre']) && intval($tienda['mostrar_nombre']) === 0) echo 'hidden-by-logic'; ?>"><?php echo htmlspecialchars($tienda['nombre']); ?></span>
            </a>

            <div class="store-menu">
                <!-- Botón INICIO fijo -->
                <a href="#productos" class="menu-item active" onclick="showSection('productos'); filterProducts('todos', this); return false;">
                    Inicio
                </a>

                <!-- Items generados del menú (RESTAURADO) -->
                <?php foreach ($menu_items as $index => $item): ?>
                    <a href="#productos" class="menu-item" onclick="showSection('productos', this); filterProducts('<?php echo strtolower(htmlspecialchars($item['label'])); ?>', this); return false;">
                        <?php echo htmlspecialchars(mb_convert_case($item['label'], MB_CASE_TITLE, "UTF-8")); ?>
                    </a>
                <?php endforeach; ?>

                <!-- NUEVO: Botón Contáctanos -->
                <a href="#contact" class="menu-item" onclick="showSection('contact', this); return false;">
                    Contáctanos
                </a>

                <!-- NUEVO: Botón Acerca de Nosotros -->
                <a href="#about" class="menu-item" onclick="showSection('about', this); return false;">
                    Acerca de Nosotros
                </a>
            </div>
        </div>
    </nav>

    <!-- SECCIÓN HERO BANNER (PORTADA) -->
    <!-- MOVIDO DENTRO DE #productos PARA SLIDER -->
    
    <div class="products-section" id="productos">
    
        <?php 
        $mostrar_banner = !empty($tienda['mostrar_banner']) ? (int)$tienda['mostrar_banner'] : 0;
        $banner_display = $mostrar_banner ? 'block' : 'none'; // Cambiado a block para slider
        if (isset($_GET['editor_mode']) && !$mostrar_banner) $banner_display = 'none';
        
        // Recopilar Banners
        $banners = [];
        if (!empty($tienda['banner_imagen'])) $banners[] = $tienda['banner_imagen'];
        if (!empty($tienda['banner_imagen_2'])) $banners[] = $tienda['banner_imagen_2'];
        if (!empty($tienda['banner_imagen_3'])) $banners[] = $tienda['banner_imagen_3'];
        ?>
        
        <div id="heroSliderContainer" class="slider-container banner-slider" data-user-enabled="<?php echo $mostrar_banner ? 'true' : 'false'; ?>" style="display: <?php echo $banner_display; ?>;">
            <div class="slider-wrapper">
                <?php if (!empty($banners)): ?>
                    <?php foreach($banners as $bImg): ?>
                        <div class="slide" style="background-image: url('/uploads/<?php echo htmlspecialchars($bImg); ?>?v=<?php echo time(); ?>');"></div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="slide hero-slide-placeholder">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                        <p>El banner principal de tu tienda se mostrará aquí.</p>
                        <p style="font-size: 12px; color: #94a3b8;">Sube una imagen desde el editor para activarlo.</p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="slider-arrow left"><i class="fas fa-chevron-left"></i></div>
            <div class="slider-arrow right"><i class="fas fa-chevron-right"></i></div>
            <div class="slider-dots"></div>
        </div>

        <div class="products-grid">
            <?php if (isset($_GET['editor_mode'])): ?>
            <!-- GHOST CARD (Editor Preview) -->
            <a href="#" class="product-card ghost-card" id="ghostCard" style="display:none; border: 2px dashed var(--color-widget); opacity: 0.8; animation: pulse 2s infinite;">
                <div class="product-image-container">
                    <div class="product-badges-shein" id="ghostBadgesContainer"></div>
                    <img id="ghostImg" src="" class="product-image" style="object-fit:cover;">
                    <div class="product-metrics">
                        <div class="metric-pill"><i class="fas fa-eye"></i> 0</div>
                    </div>
                </div>
                <div class="product-info">
                    <h3 class="product-title" id="ghostTitle">Nuevo Producto</h3>
                    <div class="product-price" id="ghostPrice">Bs. 0.00</div>
                </div>
            </a>
            <?php endif; ?>
            
            <?php if (!empty($productos)): ?>
                <?php 
                // CORRECCIÓN: Usar foreach normal para evitar bug de índice
                foreach ($productos as $index => $producto):
                ?>
                    <!-- DEBUG VISUAL FORZADO -->
                    <div style="background: red; color: white; padding: 5px; margin: 5px 0; font-size: 12px;">
                        PHP DEBUG: Index=<?php echo $index; ?> | ID=<?php echo $producto['id']; ?> | Titulo=<?php echo htmlspecialchars($producto['titulo']); ?>
                    </div>
                    
                    <!-- ENLACE SPA -->
                    <a href="/tienda_producto.php?slug=<?php echo htmlspecialchars($tienda['slug']); ?>&producto_id=<?php echo $producto['id']; ?>" 
                       class="product-card" 
                       id="product-card-<?php echo $producto['id']; ?>-<?php echo $index; ?>"
                       data-estado="<?php echo htmlspecialchars($producto['estado']); ?>" 
                       data-categoria="<?php echo htmlspecialchars($producto['categoria_id'] ?? 'sin-categoria'); ?>" 
                       data-categoria-tienda="<?php echo htmlspecialchars(strtolower($producto['categoria_tienda'] ?? '')); ?>"
                       data-debug-id="<?php echo $producto['id']; ?>"
                       data-debug-index="<?php echo $index; ?>"
                       data-debug-titulo="<?php echo htmlspecialchars($producto['titulo']); ?>"
                       onclick="openProductModal('<?php echo htmlspecialchars($tienda['slug']); ?>', <?php echo $producto['id']; ?>); return false;">
                        
                        <div class="product-image-container">
                            <?php if (!empty($producto['imagen_principal'])): ?>
                                <img src="/uploads/<?php echo htmlspecialchars($producto['imagen_principal']); ?>" class="product-image" alt="<?php echo htmlspecialchars($producto['titulo']); ?>" loading="lazy">
                            <?php else: ?>
                                <div class="product-image-placeholder">
                                    <i class="fas fa-image"></i>
                                </div>
                            <?php endif; ?>

                            <!-- METRICS PILLS -->
                            <div class="product-metrics">
                                <div class="metric-pill" id="grid-views-<?php echo $producto['id']; ?>-<?php echo $index; ?>">
                                    <i class="fas fa-eye"></i> <?php echo number_format($producto['visitas'] ?? 0); ?>
                                </div>
                                <div class="metric-pill likes" id="grid-likes-<?php echo $producto['id']; ?>-<?php echo $index; ?>">
                                    <i class="fas fa-heart"></i> <?php echo number_format($producto['likes'] ?? 0); ?>
                                </div>
                            </div>

                            <!-- BADGES FLOTANTES (COMENTADO TEMPORALMENTE) -->
                            <!-- <?php echo renderizarBadgesProducto($producto); ?> -->
                        </div>

                        <div class="product-info">
                            <?php 
                                // Sentence case
                                $titulo_lower = mb_strtolower($producto['titulo'], 'UTF-8');
                                $titulo_sentence = mb_strtoupper(mb_substr($titulo_lower, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($titulo_lower, 1, null, 'UTF-8');
                            ?>
                            <h3 class="product-title" title="<?php echo htmlspecialchars($titulo_sentence); ?>"><?php echo htmlspecialchars($titulo_sentence); ?></h3>
                            <div class="product-price"><?php echo formatearPrecio($producto['precio']); ?></div>
                            <div class="product-meta">
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- No products handled via CSS/JS usually but keeping placeholder if grid empty -->
            <?php endif; ?>
        </div>
        
        <?php if (empty($productos)): ?>
            <div class="no-products">
                <i class="fas fa-box-open" style="font-size: 48px; color: #ccc; margin-bottom: 16px;"></i>
                <p>No hay productos publicados aún.</p>
            </div>
        <?php endif; ?>

        <?php
        // RENDERIZAR SECCIONES DESTACADAS
        $secciones_destacadas_activo = !empty($tienda['secciones_destacadas_activo']) ? (int)$tienda['secciones_destacadas_activo'] : 0;
        $secciones_destacadas_estilo = $tienda['secciones_destacadas_estilo'] ?? 'grid';

        if ($secciones_destacadas_activo && !empty($secciones_con_imagenes)):
            $view_class = $secciones_destacadas_estilo === 'grid' ? 'grid-view' : 'carousel-view';
        ?>
            <div class="featured-sections-container <?php echo $view_class; ?>" id="secciones-destacadas">
                <h2 class="featured-sections-title">Secciones Destacadas</h2>
                
                <?php if ($secciones_destacadas_estilo === 'carousel'): ?>
                    <div class="slider-container secciones-slider">
                        <div class="slider-wrapper">
                            <?php foreach ($secciones_con_imagenes as $seccion):
                                $label_capitalizado = mb_convert_case($seccion['label'], MB_CASE_TITLE, 'UTF-8');
                            ?>
                                <div class="slide">
                                     <a href="#productos" class="featured-section-card" onclick="handleFeaturedSectionClick('<?php echo strtolower(htmlspecialchars($seccion['label'])); ?>'); return false;">
                                        <div class="featured-section-image-container">
                                            <div class="featured-section-bg" style="background-image: url('<?php echo htmlspecialchars($seccion['imagen']); ?>');"></div>
                                        </div>
                                        <span class="featured-section-name"><?php echo htmlspecialchars($label_capitalizado); ?></span>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="slider-arrow left"><i class="fas fa-chevron-left"></i></div>
                        <div class="slider-arrow right"><i class="fas fa-chevron-right"></i></div>
                        <div class="slider-dots"></div>
                    </div>
                <?php else: ?>
                    <!-- Grid Layout -->
                    <div class="featured-sections-grid">
                        <?php foreach ($secciones_con_imagenes as $seccion):
                            $label_capitalizado = mb_convert_case($seccion['label'], MB_CASE_TITLE, 'UTF-8');
                        ?>
                             <a href="#productos" class="featured-section-card" onclick="handleFeaturedSectionClick('<?php echo strtolower(htmlspecialchars($seccion['label'])); ?>'); return false;">
                                <div class="featured-section-image-container">
                                    <div class="featured-section-bg" style="background-image: url('<?php echo htmlspecialchars($seccion['imagen']); ?>');"></div>
                                </div>
                                <span class="featured-section-name"><?php echo htmlspecialchars($label_capitalizado); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- SECCIÓN ACERCA DE NOSOTROS (Rediseño PRO) -->
    <div id="about-section" class="products-section" style="display: none;">
        <div class="about-pro-container">
            
            <div class="about-header">
                <h2 class="contact-title">Acerca de Nosotros</h2>
            </div>

            <div class="about-grid">
                <!-- Columna Texto (Centrada y única) -->
                <div class="about-text-column">
                    <?php 
                        $is_editor = isset($_GET['editor_mode']);
                        // Usar la misma clase editable-highlight para consistencia
                        $editable_attr = $is_editor ? 'contenteditable="true" data-field="descripcion" class="about-description-text editable-highlight"' : 'class="about-description-text"';
                    ?>
                    <div <?php echo $editable_attr; ?> id="editableDescription">
                        <?php 
                            $descripcion = !empty($tienda['descripcion']) ? $tienda['descripcion'] : "Bienvenido a nuestra tienda. Somos un equipo apasionado por ofrecer productos de calidad que superen las expectativas de nuestros clientes. Trabajamos cada día con el compromiso de brindar soluciones confiables y accesibles; nuestra prioridad es que cada persona que confía en nosotros reciba excelencia en cada detalle.";
                            echo nl2br(htmlspecialchars($descripcion)); 
                        ?>
                    </div>
                </div>
            </div>

            <!-- Fila de Valores (Value Props) -->
            <div class="about-values-row">
                <div class="value-item">
                    <div class="value-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <h4>Calidad Garantizada</h4>
                    <p>Seleccionamos cuidadosamente cada producto para ofrecerte lo mejor.</p>
                </div>
                <div class="value-item">
                    <div class="value-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h4>Compra Segura</h4>
                    <p>Tu confianza es lo más importante para nosotros.</p>
                </div>
                <div class="value-item">
                    <div class="value-icon">
                        <i class="fas fa-shipping-fast"></i>
                    </div>
                    <h4>Entrega Eficiente</h4>
                    <p>Nos esforzamos por procesar tus pedidos con la mayor rapidez.</p>
                </div>
            </div>

        </div>
    </div>



    <!-- SECCIÓN CONTACTO CORPORATE (Estilo The7 Company) -->
    <div id="contact-section" class="products-section" style="display: none;">
        <div class="contact-corporate-container">
            
            <div class="contact-corporate-header">
                <h2 class="contact-title">Contáctanos</h2>
                <p class="contact-subtitle">Estamos aquí para ayudarte. Ponte en contacto con nosotros por cualquiera de estos medios.</p>
            </div>

            <div class="contact-info-cards four-columns">
                <!-- Card 1: Llámanos (WhatsApp) -->
                <?php if (!empty($tienda['whatsapp'])): ?>
                <div class="info-card">
                    <div class="info-icon-wrapper">
                        <i class="fas fa-phone-alt"></i>
                    </div>
                    <h3 class="info-title">Llámanos</h3>
                    <p class="info-text">Estamos disponibles para atenderte.</p>
                    <a href="https://wa.me/591<?php echo htmlspecialchars($tienda['whatsapp']); ?>" target="_blank" class="info-link big-black-link">
                        <?php echo htmlspecialchars($tienda['whatsapp']); ?>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Card 2: Correo -->
                <?php if (!empty($tienda['email_contacto'])): ?>
                <div class="info-card">
                    <div class="info-icon-wrapper">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <h3 class="info-title">Correo</h3>
                    <p class="info-text">Escríbenos para consultas detalladas.</p>
                    <a href="mailto:<?php echo htmlspecialchars($tienda['email_contacto']); ?>" class="info-link big-black-link">
                        <?php echo htmlspecialchars($tienda['email_contacto']); ?>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Card 3: Ubicación -->
                <?php if (!empty($tienda['direccion']) || !empty($tienda['google_maps_url'])): ?>
                <div class="info-card">
                    <div class="info-icon-wrapper">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <h3 class="info-title">Ubicación</h3>
                    <p class="info-text">
                        <?php echo !empty($tienda['direccion']) ? htmlspecialchars($tienda['direccion']) : 'Dirección disponible en mapa'; ?>
                    </p>
                    <a href="<?php echo !empty($tienda['google_maps_url']) ? htmlspecialchars($tienda['google_maps_url']) : '#'; ?>" target="_blank" class="info-link big-black-link" id="mapsLink" style="<?php echo empty($tienda['google_maps_url']) ? 'display:none;' : ''; ?>">
                        Ver en Google Maps
                    </a>
                </div>
                <?php endif; ?>

                <!-- Card 4: Síguenos (siempre renderizado para permitir actualización en vivo) -->
                <div class="info-card" id="socialCard">
                    <div class="info-icon-wrapper">
                        <i class="fas fa-share-alt"></i>
                    </div>
                    <h3 class="info-title">Síguenos</h3>
                    <p class="info-text">Nuestras redes sociales.</p>
                    
                    <div class="social-links-row">
                        <a href="<?php echo !empty($tienda['facebook_url']) ? htmlspecialchars($tienda['facebook_url']) : '#'; ?>" target="_blank" class="social-icon-btn facebook" title="Facebook" style="<?php echo empty($tienda['facebook_url']) ? 'display:none;' : ''; ?>">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="<?php echo !empty($tienda['instagram_url']) ? htmlspecialchars($tienda['instagram_url']) : '#'; ?>" target="_blank" class="social-icon-btn instagram" title="Instagram" style="<?php echo empty($tienda['instagram_url']) ? 'display:none;' : ''; ?>">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="<?php echo !empty($tienda['tiktok_url']) ? htmlspecialchars($tienda['tiktok_url']) : '#'; ?>" target="_blank" class="social-icon-btn tiktok" title="TikTok" style="<?php echo empty($tienda['tiktok_url']) ? 'display:none;' : ''; ?>">
                            <i class="fab fa-tiktok"></i>
                        </a>
                        <a href="<?php echo !empty($tienda['telegram_user']) ? 'https://t.me/' . htmlspecialchars($tienda['telegram_user']) : '#'; ?>" target="_blank" class="social-icon-btn telegram" title="Telegram" style="<?php echo empty($tienda['telegram_user']) ? 'display:none;' : ''; ?>">
                            <i class="fab fa-telegram-plane"></i>
                        </a>
                        <a href="<?php echo !empty($tienda['youtube_url']) ? htmlspecialchars($tienda['youtube_url']) : '#'; ?>" target="_blank" class="social-icon-btn youtube" title="YouTube" style="<?php echo empty($tienda['youtube_url']) ? 'display:none;' : ''; ?>">
                            <i class="fab fa-youtube"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- CTA Eliminado según solicitud (Captura 2) -->
        </div>
    </div>



    <!-- Footer Blindado Done! -->
    <footer class="done-footer-wrapper">
        <div class="done-footer-content">
            <span>Powered by <a href="https://donebolivia.com/" target="_blank" class="done-link"><strong>Done!</strong> Bolivia</a></span>
            <span style="opacity: 0.3;">|</span>
            <a href="#" onclick="openReportModal(); return false;" class="done-report-link">
                <i class="fas fa-flag"></i> Informar sobre esta tienda
            </a>
        </div>
    </footer>

    <!-- Modals -->
    <div id="productModal" class="modal-overlay">
        <div class="product-modal">
            <button class="modal-close" onclick="closeModal()">
                <i class="fas fa-times"></i>
            </button>
            
            <div id="modalLoading" class="modal-loading">
                <div class="spinner"></div>
                <p>Cargando producto...</p>
            </div>

            <div id="modalContent" class="modal-content-grid" style="display: none;">
                <div class="modal-gallery">
                    <div class="gallery-top-actions">
                         <button onclick="toggleLikeCurrent()" id="modalLikeBtn" class="btn-like-modal" title="Me gusta">
                            <i class="far fa-heart"></i>
                        </button>
                        <button onclick="shareProduct()" class="btn-share-modal" title="Compartir">
                            <i class="fas fa-share-alt"></i>
                        </button>
                    </div>

                    <div class="gallery-main-container">
                        <button class="gallery-nav-btn prev" id="galleryPrevBtn" onclick="navigateGallery(-1)">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <img id="modalMainImage" src="" class="modal-main-image" alt="Producto">
                        <button class="gallery-nav-btn next" id="galleryNextBtn" onclick="navigateGallery(1)">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                        <div class="gallery-counter" id="galleryCounter">1 / 1</div>
                    </div>
                    <div id="modalThumbs" class="modal-thumbs"></div>
                </div>

                <div class="modal-info">
                        <!-- BADGES DINÁMICOS PARA MODAL -->
                        <div id="modalBadges" class="product-badges-shein">
                            <!-- Los badges dinámicos se insertarán aquí con JavaScript -->
                        </div>
                        
                        <div class="modal-title-container">
                            <h2 id="modalTitle" class="modal-title">Título del Producto</h2>
                        </div>
                    
                    <div class="modal-price-container">
                        <div id="modalPrice" class="modal-price">Bs. 0.00</div>
                        <div id="modalPriceLiteral" class="modal-price-literal">Cero Bolivianos</div>
                    </div>

                    
                    <div id="modalDescription" class="modal-description">
                        Descripción del producto...
                    </div>

                    <div class="modal-actions">
                        <a href="#" id="modalWhatsappBtn" target="_blank" class="btn-whatsapp-modal">
                            <i class="fab fa-whatsapp"></i>
                            Contactar por WhatsApp
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL DE REPORTE -->
    <div id="reportModal" class="modal-overlay" style="z-index: 12000;">
        <div class="report-modal-card">
            <div class="report-header">
                <h3>Reportar tienda</h3>
                <button class="modal-close-simple" onclick="closeReportModal()">×</button>
            </div>
            <div class="report-body">
                <p class="report-instruction">Selecciona el motivo del reporte:</p>
                <div class="report-options">
                    <label class="report-option">
                        <input type="radio" name="report_reason" value="fraude_estafa">
                        <span class="option-content">
                            <i class="fas fa-exclamation-triangle"></i> Posible fraude o estafa
                        </span>
                    </label>
                    <label class="report-option">
                        <input type="radio" name="report_reason" value="productos_prohibidos">
                        <span class="option-content">
                            <i class="fas fa-ban"></i> Venta de productos prohibidos
                        </span>
                    </label>
                    <label class="report-option">
                        <input type="radio" name="report_reason" value="suplantacion">
                        <span class="option-content">
                            <i class="fas fa-user-secret"></i> Suplantación de identidad
                        </span>
                    </label>
                    <label class="report-option">
                        <input type="radio" name="report_reason" value="contenido_inapropiado">
                        <span class="option-content">
                            <i class="fas fa-images"></i> Contenido inapropiado u ofensivo
                        </span>
                    </label>
                    <label class="report-option">
                        <input type="radio" name="report_reason" value="spam">
                        <span class="option-content">
                            <i class="fas fa-bullhorn"></i> Spam o información falsa
                        </span>
                    </label>
                    <label class="report-option">
                        <input type="radio" name="report_reason" value="otro">
                        <span class="option-content">
                            <i class="fas fa-question-circle"></i> Otro motivo
                        </span>
                    </label>
                </div>
            </div>
            <div class="report-footer">
                <button class="btn-cancel" onclick="closeReportModal()">Cancelar</button>
                <button class="btn-submit-report" onclick="submitReport()">Enviar reporte</button>
            </div>
        </div>
    </div>

    <!-- WIDGET CONTACTO MULTICANAL -->
    <div class="kommo-widget-container">
        <div class="kommo-actions" id="kommoActions">
            <?php if (!empty($tienda['whatsapp'])): ?>
                <a href="https://wa.me/591<?php echo htmlspecialchars($tienda['whatsapp']); ?>?text=Hola, vi tu tienda en DoneBolivia" target="_blank" class="kommo-action-btn whatsapp" title="WhatsApp">
                    <i class="fab fa-whatsapp"></i>
                </a>
            <?php endif; ?>
            
            <?php if (!empty($tienda['telegram_user'])): ?>
                <a href="https://t.me/<?php echo htmlspecialchars($tienda['telegram_user']); ?>" target="_blank" class="kommo-action-btn telegram" title="Telegram">
                    <i class="fab fa-telegram-plane"></i>
                </a>
            <?php endif; ?>

            <?php if (!empty($tienda['tiktok_url'])): ?>
                <a href="<?php echo htmlspecialchars($tienda['tiktok_url']); ?>" target="_blank" class="kommo-action-btn tiktok" title="TikTok">
                    <i class="fab fa-tiktok"></i>
                </a>
            <?php endif; ?>

            <?php if (!empty($tienda['instagram_url'])): ?>
                <a href="<?php echo htmlspecialchars($tienda['instagram_url']); ?>" target="_blank" class="kommo-action-btn instagram" title="Instagram">
                    <i class="fab fa-instagram"></i>
                </a>
            <?php endif; ?>

            <?php if (!empty($tienda['facebook_url'])): ?>
                <a href="<?php echo htmlspecialchars($tienda['facebook_url']); ?>" target="_blank" class="kommo-action-btn facebook" title="Facebook">
                    <i class="fab fa-facebook-f"></i>
                </a>
            <?php endif; ?>

            <?php if (!empty($tienda['youtube_url'])): ?>
                <a href="<?php echo htmlspecialchars($tienda['youtube_url']); ?>" target="_blank" class="kommo-action-btn youtube" title="YouTube">
                    <i class="fab fa-youtube"></i>
                </a>
            <?php endif; ?>
        </div>
        
        <button class="kommo-main-launcher" id="kommoLauncher" onclick="toggleKommoWidget()">
            <div class="kommo-icon-open">
                <i class="fas fa-comment-dots"></i>
            </div>
            <div class="kommo-icon-close">
                <i class="fas fa-times"></i>
            </div>
        </button>
    </div>

    <!-- SCRIPT WIDGET -->
    <script src="/assets/js/tienda-pro.js?v=<?php echo time(); ?>"></script>
    
    <!-- COMENTADO main.js Y bundle.min.js PARA EVITAR DUPLICACIÓN -->
    <!-- <script src="/assets/js/main.js?v=fix-validation-2" defer></script> -->
    <!-- <script src="/assets/bundle.min.js?v=<?php echo time(); ?>" defer></script> -->

    <script>
        // --- LÓGICA DE NAVBAR STYLE (PÚBLICA Y EDITOR) ---
        function applyNavbarStyle(style) {
            const navbar = document.querySelector('.store-navbar');
            if (!navbar) return;

            // Limpiar clases previas
            navbar.classList.remove('navbar-marca', 'navbar-text-light', 'navbar-text-dark');

            if (style === 'marca') {
                navbar.classList.add('navbar-marca');
                
                // Decidir color de texto basado en contraste
                const bgColor = getComputedStyle(document.documentElement).getPropertyValue('--color-widget');
                const textColor = getContrastYIQ(bgColor);
                
                if (textColor === 'white') {
                    navbar.classList.add('navbar-text-light');
                } else {
                    navbar.classList.add('navbar-text-dark');
                }
            }
        }

        function getContrastYIQ(hexcolor){
            hexcolor = hexcolor.replace('#', '');
            var r = parseInt(hexcolor.substr(0,2),16);
            var g = parseInt(hexcolor.substr(2,2),16);
            var b = parseInt(hexcolor.substr(4,2),16);
            var yiq = ((r*299)+(g*587)+(b*114))/1000;
            return (yiq >= 128) ? 'black' : 'white';
        }

        // Aplicar estilo inicial al cargar la página (para todos los visitantes)
        document.addEventListener('DOMContentLoaded', () => {
            const initialNavbarStyle = <?php echo json_encode($tienda['navbar_style'] ?? 'blanco'); ?>;
            applyNavbarStyle(initialNavbarStyle);
        });
    </script>

    <?php if (isset($_GET['editor_mode'])): ?>
    <script>
    // LIVE PREVIEW LISTENER & SENDER (SOLO EDITOR)
    
    // 1. Enviar cambios al padre (Inline Editing Generalizado)
    document.querySelectorAll('[contenteditable="true"]').forEach(el => {
        // Visual Styles
        el.addEventListener('focus', function() {
            this.style.outline = '2px solid var(--color-widget)';
            this.style.backgroundColor = 'rgba(255,255,255,0.8)';
            this.style.borderRadius = '4px';
            this.style.minWidth = '20px';
        });
        
        el.addEventListener('blur', function() {
            this.style.outline = 'none';
            this.style.backgroundColor = 'transparent';
        });

        // Sync Logic
        el.addEventListener('input', function() {
            const field = this.getAttribute('data-field');
            if(field) {
                window.parent.postMessage({
                    type: 'syncFromFrame',
                    field: field,
                    value: this.innerText
                }, '*');
            }
        });
    });

    // 2. Scroll Handler
    window.addEventListener('message', function(event) {
        const data = event.data;
        if (!data) return;

        if (data.type === 'scrollTo') {
            const selector = data.payload.selector;
            const el = document.querySelector(selector);
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                // Optional: Flash highlight
                el.style.transition = 'background 0.5s';

            }
        } else if (data.type === 'filterProducts') { // NUEVA LÓGICA
            const sectionName = data.payload.section;
            
            // Find the button in the menu that corresponds to the section name
            const menuButtons = document.querySelectorAll('.store-menu .menu-item');
            let targetButton = null;

            if (sectionName.toLowerCase() === 'todos') {
                // Special case for 'todos', which corresponds to the 'Inicio' button text
                menuButtons.forEach(button => {
                    if (button.textContent.trim().toLowerCase() === 'inicio') {
                        targetButton = button;
                    }
                });
            } else {
                // Standard case for other sections
                menuButtons.forEach(button => {
                    const buttonText = button.textContent.trim().toLowerCase();
                    const targetText = sectionName.trim().toLowerCase();
                    if (buttonText === targetText) {
                        targetButton = button;
                    }
                });
            }

            if (targetButton) {
                // We found the button, now call the existing filterProducts function
                // which is globally available from tienda-pro.js
                filterProducts(sectionName, targetButton);

                // Also, scroll to the products section to make sure it's visible
                // FIX: Only scroll to the container for specific sections, not for 'todos' (Inicio)
                // This prevents the visual jump when the scrollbar appears on a long list.
                if (sectionName.toLowerCase() !== 'todos') {
                    const productsContainer = document.getElementById('productos');
                    if (productsContainer) {
                        productsContainer.scrollIntoView({
                            behavior: 'smooth'
                        });
                    }
                }
            } else {
                console.warn('Could not find a menu button for section:', sectionName);
            }
        }

        if (data.type === 'updateTheme') {
            const p = data.payload;
            const root = document.documentElement.style;

            // 1. COLOR
            if (p.color) {
                root.setProperty('--color-widget', p.color);
                // Calculate RGB for opacity layers
                const hex = p.color.replace('#', '');
                const r = parseInt(hex.substring(0,2), 16);
                const g = parseInt(hex.substring(2,4), 16);
                const b = parseInt(hex.substring(4,6), 16);
                const rgb = `${r}, ${g}, ${b}`;
                root.setProperty('--color-widget-rgb', rgb);
                
                // Update derived colors
                const opacity = <?php echo $opacidad_decimal; ?>;
                root.setProperty('--btn-bg-hover', `rgba(${rgb}, ${opacity})`);
                root.setProperty('--btn-bg-active', `rgba(${rgb}, ${opacity})`);

                // [FIX] Recalcular contraste de navbar en tiempo real
                const navbar = document.querySelector('.store-navbar');
                if (navbar && navbar.classList.contains('navbar-marca')) {
                    applyNavbarStyle('marca');
                }
            }

            // 3. BORDES
            if (p.bordes) {
                let rBtn = '8px', rCard = '12px';
                if (p.bordes === 'recto') { rBtn = '0px'; rCard = '0px'; }
                if (p.bordes === 'pill') { rBtn = '50px'; rCard = '24px'; }
                root.setProperty('--border-radius-btn', rBtn);
                root.setProperty('--border-radius-card', rCard);
            }

            // 4. FUENTE
            if (p.fuente) {
                let font = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
                if (p.fuente === 'inter') font = "'Inter', sans-serif";
                if (p.fuente === 'jakarta') font = "'Plus Jakarta Sans', sans-serif";
                if (p.fuente === 'manrope') font = "'Manrope', sans-serif";
                if (p.fuente === 'tech') font = "'Space Mono', monospace";
                if (p.fuente === 'modern') font = "'Poppins', sans-serif";
                if (p.fuente === 'minimal') font = "'Roboto', sans-serif";
                if (p.fuente === 'classic') font = "'Lora', serif";
                if (p.fuente === 'bold') font = "'Montserrat', sans-serif";
                if (p.fuente === 'outfit') font = "'Outfit', sans-serif";
                root.setProperty('--font-family-main', font);
            }

            // 7. TAMAÑO
            if (p.tamano) {
                document.body.classList.remove('size-small', 'size-normal', 'size-large');
                if(p.tamano === 'small') document.body.classList.add('size-small');
                if(p.tamano === 'large') document.body.classList.add('size-large');
            }

            // 5. TARJETAS
            if (p.tarjetas) {
                let shadow = '0 10px 30px rgba(0,0,0,0.08)';
                let border = '1px solid var(--border-color)';
                
                if (p.tarjetas === 'flat') { shadow = 'none'; border = '1px solid var(--border-color)'; }
                if (p.tarjetas === 'borde') { shadow = 'none'; border = '2px solid var(--border-color)'; }
                if (p.tarjetas === 'elevada') { border = 'none'; }
                
                root.setProperty('--card-shadow', shadow);
                root.setProperty('--card-border', border);
            }

            // 6. FONDO
            if (p.fondo) {
                document.body.style.backgroundImage = 'none';
                document.body.style.backgroundColor = '';
                
                if (p.fondo === 'tintado') {
                    // Recalculate tint based on current widget color
                    // Try to get from payload first, then from computed style
                    let rgb = '';
                    if (p.color) {
                         const hex = p.color.replace('#', '');
                         const r = parseInt(hex.substring(0,2), 16);
                         const g = parseInt(hex.substring(2,4), 16);
                         const b = parseInt(hex.substring(4,6), 16);
                         rgb = `${r}, ${g}, ${b}`;
                    } else {
                        const style = getComputedStyle(document.documentElement);
                        rgb = style.getPropertyValue('--color-widget-rgb').trim();
                    }
                    
                    if(rgb) document.body.style.backgroundColor = `rgba(${rgb}, 0.03)`;
                } else if (p.fondo === 'gris') {
                    document.body.style.backgroundColor = '#f1f5f9';
                } else {
                    // Fallback to white if 'blanco' or unknown
                    document.body.style.backgroundColor = '#ffffff';
                }
            }

            // 7. FOTOS
            if (p.fotos) {
                let aspect = '1 / 1';
                let fit = 'cover';
                
                if (p.fotos === 'vertical') aspect = '3 / 4';
                else if (p.fotos === 'horizontal') aspect = '4 / 3';
                else if (p.fotos === 'natural') { aspect = '1 / 1'; fit = 'contain'; }
                
                root.setProperty('--img-aspect-ratio', aspect);
                root.setProperty('--img-object-fit', fit);
            }

            // 8. BANNER (SLIDER V2)
            if (p.banner) {
                const b = p.banner;
                const container = document.getElementById('heroSliderContainer');
                
                if (b.activo !== undefined) {
                    container.style.display = b.activo ? 'block' : 'none';
                }
                
                if (b.imagenes && Array.isArray(b.imagenes)) {
                    // Re-render slider logic would be complex here, 
                    // simple reload for now or just update first image
                    // Better approach: Refresh page on banner change for full logic rebuild
                    if(window.refreshSlider) window.refreshSlider(b.imagenes);
                }
            }
        }

        // Helper function para verificar si existe archivo
        function fileExists(url) {
            try {
                const http = new XMLHttpRequest();
                http.open('HEAD', url, false);
                http.send();
                return http.status !== 404;
            } catch(e) {
                return false;
            }
        }

        if (data.type === 'updateText') {
            const payload = data.payload;
            const els = document.querySelectorAll(payload.selector);
            els.forEach(el => {
                if(payload.text !== undefined) {
                    el.textContent = payload.text;
                }
                if (payload.visible !== undefined) {
                    if (payload.visible) {
                        el.classList.remove('hidden-by-logic');
                    } else {
                        el.classList.add('hidden-by-logic');
                    }
                }
            });
        }
        
        if (data.type === 'updateContact') {
            const c = data.payload || {};
            try {
                const waLink = document.querySelector('#contact-section a.info-link[href^="https://wa.me"], .contact-info-cards a.info-link[href^="https://wa.me"]');
                const waCard = waLink ? waLink.closest('.info-card') : document.querySelector('#contact-section .info-card i.fa-phone-alt')?.closest('.info-card');
                if (waLink && c.whatsapp) {
                    waLink.href = 'https://wa.me/591' + c.whatsapp;
                    waLink.textContent = c.whatsapp;
                    if (waCard) waCard.style.display = 'block';
                } else if (waCard) {
                    waCard.style.display = 'none';
                }
            } catch(e){}
            try {
                const mailLink = document.querySelector('#contact-section a.info-link[href^="mailto:"], .contact-info-cards a.info-link[href^="mailto:"]');
                const mailCard = mailLink ? mailLink.closest('.info-card') : document.querySelector('#contact-section .info-card i.fa-envelope')?.closest('.info-card');
                if (mailLink && c.email) {
                    mailLink.href = 'mailto:' + c.email;
                    mailLink.textContent = c.email;
                    if (mailCard) mailCard.style.display = 'block';
                } else if (mailCard) {
                    mailCard.style.display = 'none';
                }
            } catch(e){}
            try {
                const addrIcon = document.querySelector('#contact-section .info-card i.fa-map-marker-alt');
                const addrCard = addrIcon ? addrIcon.closest('.info-card') : null;
                const addrText = addrCard ? addrCard.querySelector('p.info-text') : null;
                const mapLink = addrCard ? addrCard.querySelector('#mapsLink') : null;
                if (addrText) {
                    if (c.direccion) {
                        addrText.textContent = c.direccion;
                        if (addrCard) addrCard.style.display = 'block';
                    } else {
                        addrText.textContent = 'Dirección no especificada';
                        if (addrCard) addrCard.style.display = 'none';
                    }
                }
                if (mapLink) {
                    if (c.maps) {
                        mapLink.href = c.maps;
                        mapLink.style.display = 'inline-block';
                    } else {
                        mapLink.style.display = 'none';
                    }
                }
            } catch(e){}
            try {
                const socialRow = document.querySelector('#contact-section .social-links-row');
                const card = socialRow ? socialRow.closest('.info-card') : null;
                const nets = [
                    { key: 'facebook', sel: '.social-links-row .facebook' },
                    { key: 'instagram', sel: '.social-links-row .instagram' },
                    { key: 'tiktok', sel: '.social-links-row .tiktok' },
                    { key: 'telegram', sel: '.social-links-row .telegram' },
                    { key: 'youtube', sel: '.social-links-row .youtube' },
                ];
                let any = false;
                nets.forEach(n => {
                    const a = document.querySelector(n.sel);
                    const val = c[n.key];
                    if (a) {
                        if (val) {
                            a.href = n.key === 'telegram' ? ('https://t.me/' + val) : val;
                            a.style.display = 'inline-flex';
                            any = true;
                        } else {
                            a.style.display = 'none';
                        }
                    }
                });
                if (card) card.style.display = any ? 'block' : 'none';
            } catch(e){}
        }
        
        if (data.type === 'updateLogo') {
            // LOGO DESACTIVADO DEL BANNER - NO HACER NADA
        }

        if (data.type === 'previewProduct') {
            const p = data.payload;
            const ghost = document.getElementById('ghostCard');
            const grid = document.querySelector('.products-grid');
            
            if (!p.active) {
                if (ghost) ghost.style.display = 'none';
                return;
            }
            
            // Ensure grid exists
            if (!grid) {
                // Handle case where no products exist yet
                const container = document.getElementById('productos');
                // Remove no-products msg if exists
                const noProd = container.querySelector('.no-products');
                if(noProd) noProd.style.display = 'none';
                
                // If products-grid was not found, it means we replaced the PHP logic or it was hidden.
                // But my previous edit ensured .products-grid always exists.
            } 
            
            if(ghost) {
                // Ensure ghost is first
                if (grid && grid.firstElementChild !== ghost) {
                    grid.prepend(ghost);
                }
                
                ghost.style.display = 'block';
                document.getElementById('ghostTitle').textContent = p.titulo || 'Nuevo Producto';
                document.getElementById('ghostPrice').textContent = 'Bs. ' + (p.precio || '0.00');
                
                const badgesContainer = document.getElementById('ghostBadgesContainer');
                if (badgesContainer) {
                    badgesContainer.innerHTML = ''; // Clear previous badges
                    const timestamp = new Date().getTime(); // Cache busting

                    if (p.badges && Array.isArray(p.badges)) {
                        p.badges.forEach(badgeUrl => {
                            if (badgeUrl) { // Asegurarse de que la URL no esté vacía
                                const cacheBustedUrl = badgeUrl.split('?')[0] + '?v=' + timestamp;
                                badgesContainer.innerHTML += `<img src="${cacheBustedUrl}" class="badge-completo" alt="Badge">`;
                            }
                        });
                    }
                }
                
                const img = document.getElementById('ghostImg');
                if (p.imagen) {
                    img.src = p.imagen;
                } else {
                    // Use a clean SVG placeholder or transparent pixel if default image is missing
                    img.src = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='100%25' height='100%25' viewBox='0 0 800 800'%3E%3Crect fill='%23f1f5f9' width='800' height='800'/%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' font-family='sans-serif' font-size='48' fill='%23cbd5e1'%3EImagen%3C/text%3E%3C/svg%3E";
                }
            }
        }
        if (data.type === 'updateMenu') {
            const items = data.payload.items;
            const menuContainer = document.querySelector('.store-menu');
            if (menuContainer) {
                // Keep static items (Inicio, Contactanos, Acerca) and rebuild dynamic ones
                // OR rebuild entire structure if we know the order.
                // Current structure: Inicio (Static) -> Dynamic -> Contactanos -> Acerca
                
                // 1. Save static references if needed, or just rebuild from scratch
                // Rebuilding is safer to ensure order
                
                let html = `
                <!-- Botón INICIO fijo -->
                <a href="#productos" class="menu-item active" onclick="showSection('productos', this); return false;">
                    Inicio
                </a>`;
                
                // 2. Dynamic Items
                if (Array.isArray(items)) {
                    items.forEach(item => {
                        if (item.label.toLowerCase() === 'inicio' || item.label.toLowerCase() === 'todos') return;
                        
                        // Capitalize logic (Same as JS helper)
                        const label = item.label.toLowerCase().split(' ').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
                        
                        html += `
                        <a href="#productos" class="menu-item" onclick="showSection('productos', this); filterProducts('${item.label.toLowerCase()}', this); return false;">
                            ${label}
                        </a>`;
                    });
                }
                
                // 3. Static Footer Items
                html += `
                <a href="#contact" class="menu-item" onclick="showSection('contact', this); return false;">
                    Contáctanos
                </a>
                <a href="#about" class="menu-item" onclick="showSection('about', this); return false;">
                    Acerca de Nosotros
                </a>`;
                
                menuContainer.innerHTML = html;
            }
        }

        // --- [NUEVO] MANEJADOR UNIFICADO PARA LOGO ---
        if (data.type === 'updateLogoState') {
            const payload = data.payload;
            const container = document.getElementById('principalLogoContainer');
            const img = document.getElementById('principalLogoImage');
            const placeholder = document.getElementById('principalLogoPlaceholder'); // Esto solo existe en modo editor

            if (!container || !img) return; // Placeholder puede no existir, no es crítico

            if (payload.visible) {
                container.classList.remove('hidden-by-logic');
                if (payload.url) {
                    // Hay logo, mostrarlo
                    img.src = payload.url;
                    img.style.display = 'block';
                    if (placeholder) placeholder.style.display = 'none';
                } else {
                    // No hay logo, la visibilidad del placeholder depende de si existe (solo en editor)
                    img.style.display = 'none';
                    if (placeholder) placeholder.style.display = 'flex'; // Cambiado a flex para centrar contenido
                }
            } else {
                container.classList.add('hidden-by-logic');
            }
        }
        // --- FIN [NUEVO] ---

        // --- [NUEVO] MANEJADOR PARA NAVBAR STYLE ---
        if (data.type === 'setNavbarStyle') {
            applyNavbarStyle(data.payload.style);
        }

        // Bloquear navegación en modo editor
        if (new URLSearchParams(window.location.search).has('editor_mode')) {
            const headerLink = document.querySelector('.store-header');
            if(headerLink) {
                headerLink.addEventListener('click', (e) => e.preventDefault());
            }
        }
    });

    </script>
<?php endif; ?>
</body>
</html>
