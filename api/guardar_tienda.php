<?php
/**
 * API: Guardar cambios de tienda (Editor Visual)
 */
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Verificar autenticación
if (!estaLogueado()) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

try {
    // Obtener datos del request
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        throw new Exception('Datos inválidos');
    }

    $db = getDB();
    $usuario_id = $_SESSION['usuario_id'];

    // Verificar que la tienda pertenece al usuario
    $stmt = $db->prepare("SELECT id FROM tiendas WHERE usuario_id = ? LIMIT 1");
    $stmt->execute([$usuario_id]);
    $tienda = $stmt->fetch();

    if (!$tienda) {
        throw new Exception('Tienda no encontrada');
    }

    // AUTO-MIGRACIÓN SILENCIOSA: Verificar columnas nuevas
    try {
        // Opacidad
        $checkCol = $db->query("SHOW COLUMNS FROM tiendas LIKE 'opacidad_botones'");
        if ($checkCol->rowCount() == 0) {
            $db->exec("ALTER TABLE tiendas ADD COLUMN opacidad_botones INT DEFAULT 12");
        }

        // Imágenes Acerca de Nosotros
        $checkCol1 = $db->query("SHOW COLUMNS FROM tiendas LIKE 'imagen_acerca_1'");
        if ($checkCol1->rowCount() == 0) {
            $db->exec("ALTER TABLE tiendas ADD COLUMN imagen_acerca_1 VARCHAR(255) DEFAULT NULL");
        }

        $checkCol2 = $db->query("SHOW COLUMNS FROM tiendas LIKE 'imagen_acerca_2'");
        if ($checkCol2->rowCount() == 0) {
            $db->exec("ALTER TABLE tiendas ADD COLUMN imagen_acerca_2 VARCHAR(255) DEFAULT NULL");
        }

        // Youtube
        $checkCol3 = $db->query("SHOW COLUMNS FROM tiendas LIKE 'youtube_url'");
        if ($checkCol3->rowCount() == 0) {        
            $db->exec("ALTER TABLE tiendas ADD COLUMN youtube_url VARCHAR(255) DEFAULT NULL");      
        }

        // Email Contacto
        $checkCol4 = $db->query("SHOW COLUMNS FROM tiendas LIKE 'email_contacto'");
        if ($checkCol4->rowCount() == 0) {        
            $db->exec("ALTER TABLE tiendas ADD COLUMN email_contacto VARCHAR(255) DEFAULT NULL");      
        }

        // Direccion
        $checkCol5 = $db->query("SHOW COLUMNS FROM tiendas LIKE 'direccion'");
        if ($checkCol5->rowCount() == 0) {        
            $db->exec("ALTER TABLE tiendas ADD COLUMN direccion TEXT DEFAULT NULL");      
        }

        // Google Maps URL
        $checkCol6 = $db->query("SHOW COLUMNS FROM tiendas LIKE 'google_maps_url'");
        if ($checkCol6->rowCount() == 0) {        
            $db->exec("ALTER TABLE tiendas ADD COLUMN google_maps_url TEXT DEFAULT NULL");      
        }

        // Nuevos campos de personalización (Identidad Visual 2.0)
        // Estilo Bordes
        $checkCol7 = $db->query("SHOW COLUMNS FROM tiendas LIKE 'estilo_bordes'");
        if ($checkCol7->rowCount() == 0) {
            $db->exec("ALTER TABLE tiendas ADD COLUMN estilo_bordes VARCHAR(50) DEFAULT 'suave'");
        }

        // Estilo Fondo
        $checkCol8 = $db->query("SHOW COLUMNS FROM tiendas LIKE 'estilo_fondo'");
        if ($checkCol8->rowCount() == 0) {
            $db->exec("ALTER TABLE tiendas ADD COLUMN estilo_fondo VARCHAR(50) DEFAULT 'blanco'");
        }

        // Tipografia
        $checkCol9 = $db->query("SHOW COLUMNS FROM tiendas LIKE 'tipografia'");
        if ($checkCol9->rowCount() == 0) {
            $db->exec("ALTER TABLE tiendas ADD COLUMN tipografia VARCHAR(50) DEFAULT 'system'");
        }

        // Estilo Tarjetas
        $checkCol10 = $db->query("SHOW COLUMNS FROM tiendas LIKE 'estilo_tarjetas'");
        if ($checkCol10->rowCount() == 0) {
            $db->exec("ALTER TABLE tiendas ADD COLUMN estilo_tarjetas VARCHAR(50) DEFAULT 'elevada'");
        }

        // Tamaño de Texto
        $checkCol11 = $db->query("SHOW COLUMNS FROM tiendas LIKE 'tamano_texto'");
        if ($checkCol11->rowCount() == 0) {
            $db->exec("ALTER TABLE tiendas ADD COLUMN tamano_texto VARCHAR(20) DEFAULT 'normal'");
        }

        // Estilo de Fotos (NUEVO)
        $checkCol12 = $db->query("SHOW COLUMNS FROM tiendas LIKE 'estilo_fotos'");
        if ($checkCol12->rowCount() == 0) {
            $db->exec("ALTER TABLE tiendas ADD COLUMN estilo_fotos VARCHAR(50) DEFAULT 'cuadrado'");
        }

        // --- HERO BANNER (PORTADA PRO) ---
        // 1. Mostrar Banner
        $checkCol13 = $db->query("SHOW COLUMNS FROM tiendas LIKE 'mostrar_banner'");
        if ($checkCol13->rowCount() == 0) {
            $db->exec("ALTER TABLE tiendas ADD COLUMN mostrar_banner INT DEFAULT 0");
        }
        // 2. Imagen Banner
        $checkCol14 = $db->query("SHOW COLUMNS FROM tiendas LIKE 'banner_imagen'");
        if ($checkCol14->rowCount() == 0) {
            $db->exec("ALTER TABLE tiendas ADD COLUMN banner_imagen VARCHAR(255) DEFAULT NULL");
        }
        // 2.1 Banner 2
        $checkCol14_2 = $db->query("SHOW COLUMNS FROM tiendas LIKE 'banner_imagen_2'");
        if ($checkCol14_2->rowCount() == 0) {
            $db->exec("ALTER TABLE tiendas ADD COLUMN banner_imagen_2 VARCHAR(255) DEFAULT NULL");
        }
        // 2.2 Banner 3
        $checkCol14_3 = $db->query("SHOW COLUMNS FROM tiendas LIKE 'banner_imagen_3'");
        if ($checkCol14_3->rowCount() == 0) {
            $db->exec("ALTER TABLE tiendas ADD COLUMN banner_imagen_3 VARCHAR(255) DEFAULT NULL");
        }

        // 3. Título Banner (LEGACY - Se mantiene para no romper, pero no se usa en UI nueva)
        $checkCol15 = $db->query("SHOW COLUMNS FROM tiendas LIKE 'banner_titulo'");
        $checkCol15 = $db->query("SHOW COLUMNS FROM tiendas LIKE 'banner_titulo'");
        if ($checkCol15->rowCount() == 0) {
            $db->exec("ALTER TABLE tiendas ADD COLUMN banner_titulo VARCHAR(100) DEFAULT NULL");
        }
        // 4. Subtítulo Banner
        $checkCol16 = $db->query("SHOW COLUMNS FROM tiendas LIKE 'banner_subtitulo'");
        if ($checkCol16->rowCount() == 0) {
            $db->exec("ALTER TABLE tiendas ADD COLUMN banner_subtitulo VARCHAR(200) DEFAULT NULL");
        }
        // 5. Texto Botón
        $checkCol17 = $db->query("SHOW COLUMNS FROM tiendas LIKE 'banner_texto_boton'");
        if ($checkCol17->rowCount() == 0) {
            $db->exec("ALTER TABLE tiendas ADD COLUMN banner_texto_boton VARCHAR(50) DEFAULT 'Ver Productos'");
        }

        // SECCIONES DESTACADAS
        $checkCol18 = $db->query("SHOW COLUMNS FROM tiendas LIKE 'secciones_destacadas_activo'");
        if ($checkCol18->rowCount() == 0) {
            $db->exec("ALTER TABLE tiendas ADD COLUMN secciones_destacadas_activo INT DEFAULT 0");
        }

        $checkCol19 = $db->query("SHOW COLUMNS FROM tiendas LIKE 'secciones_destacadas_estilo'");
        if ($checkCol19->rowCount() == 0) {
            $db->exec("ALTER TABLE tiendas ADD COLUMN secciones_destacadas_estilo VARCHAR(50) DEFAULT 'grid'");
        }

        // Densidad de Cuadrícula (NUEVO)
        $checkCol20 = $db->query("SHOW COLUMNS FROM tiendas LIKE 'grid_density'");
        if ($checkCol20->rowCount() == 0) {
            $db->exec("ALTER TABLE tiendas ADD COLUMN grid_density INT DEFAULT 3");
        }

    } catch (Exception $ex) {
        // Ignorar error si no se puede alterar la tabla
        error_log("Error auto-migración: " . $ex->getMessage());
    }

    // Preparar campos para actualizar
    $updates = [];
    $params = [];

    if (isset($data['nombre'])) {
        $updates[] = "nombre = ?";
        $params[] = trim($data['nombre']);        
    }

    if (isset($data['descripcion'])) {
        $updates[] = "descripcion = ?";
        $params[] = trim($data['descripcion']);   
    }

    if (isset($data['menu_items'])) {
        $updates[] = "menu_items = ?";
        $params[] = $data['menu_items'];
    }

    if (isset($data['whatsapp'])) {
        $updates[] = "whatsapp = ?";
        $params[] = trim($data['whatsapp']);      
    }

    if (isset($data['email_contacto'])) {
        $updates[] = "email_contacto = ?";
        $params[] = trim($data['email_contacto']);      
    }

    if (isset($data['direccion'])) {
        $updates[] = "direccion = ?";
        $params[] = trim($data['direccion']);      
    }

    if (isset($data['google_maps_url'])) {
        $updates[] = "google_maps_url = ?";
        $params[] = trim($data['google_maps_url']);      
    }

    if (isset($data['color_primario'])) {
        $updates[] = "color_primario = ?";
        $params[] = trim($data['color_primario']);
    }

    if (isset($data['opacidad_botones'])) {
        $updates[] = "opacidad_botones = ?";
        $params[] = intval($data['opacidad_botones']);
    }

    // Identidad Visual 2.0
    if (isset($data['estilo_bordes'])) {
        $updates[] = "estilo_bordes = ?";
        $params[] = trim($data['estilo_bordes']);
    }
    if (isset($data['estilo_fondo'])) {
        $updates[] = "estilo_fondo = ?";
        $params[] = trim($data['estilo_fondo']);
    }
    if (isset($data['tipografia'])) {
        $updates[] = "tipografia = ?";
        $params[] = trim($data['tipografia']);
    }
    if (isset($data['estilo_tarjetas'])) {
        $updates[] = "estilo_tarjetas = ?";
        $params[] = trim($data['estilo_tarjetas']);
    }
    if (isset($data['tamano_texto'])) {
        $updates[] = "tamano_texto = ?";
        $params[] = trim($data['tamano_texto']);
    }

    if (isset($data['estilo_fotos'])) {
        $updates[] = "estilo_fotos = ?";
        $params[] = trim($data['estilo_fotos']);
    }

    // --- HERO BANNER ---
    if (isset($data['mostrar_banner'])) {
        $updates[] = "mostrar_banner = ?";
        $params[] = intval($data['mostrar_banner']);
    }
    if (isset($data['banner_titulo'])) {
        $updates[] = "banner_titulo = ?";
        $params[] = trim($data['banner_titulo']);
    }
    if (isset($data['banner_subtitulo'])) {
        $updates[] = "banner_subtitulo = ?";
        $params[] = trim($data['banner_subtitulo']);
    }
    if (isset($data['banner_texto_boton'])) {
        $updates[] = "banner_texto_boton = ?";
        $params[] = trim($data['banner_texto_boton']);
    }

    // --- SECCIONES DESTACADAS ---
    if (isset($data['secciones_destacadas_activo'])) {
        $updates[] = "secciones_destacadas_activo = ?";
        $params[] = intval($data['secciones_destacadas_activo']);
    }
    if (isset($data['secciones_destacadas_estilo'])) {
        $updates[] = "secciones_destacadas_estilo = ?";
        $params[] = trim($data['secciones_destacadas_estilo']);
    }

    if (isset($data['gridDensity'])) {
        $updates[] = "grid_density = ?";
        $params[] = intval($data['gridDensity']);
    }

    if (isset($data['tema'])) {
        $updates[] = "tema = ?";
        $params[] = trim($data['tema']);
    }

    // Redes Sociales
    if (isset($data['facebook_url'])) {
        $updates[] = "facebook_url = ?";
        $params[] = trim($data['facebook_url']);
    }
    if (isset($data['instagram_url'])) {
        $updates[] = "instagram_url = ?";
        $params[] = trim($data['instagram_url']);
    }
    if (isset($data['tiktok_url'])) {
        $updates[] = "tiktok_url = ?";
        $params[] = trim($data['tiktok_url']);
    }
    if (isset($data['telegram_user'])) {
        $updates[] = "telegram_user = ?";
        $params[] = trim($data['telegram_user']);
    }
    if (isset($data['youtube_url'])) {
        $updates[] = "youtube_url = ?";
        $params[] = trim($data['youtube_url']);
    }

    // NUEVOS CAMPOS DE IDENTIDAD DE MARCA
    if (isset($data['mostrar_logo'])) {
        $updates[] = "mostrar_logo = ?";
        $params[] = intval($data['mostrar_logo']);
    }
    if (isset($data['mostrar_nombre'])) {
        $updates[] = "mostrar_nombre = ?";
        $params[] = intval($data['mostrar_nombre']);
    }

    if (isset($data['navbar_style'])) {
        $updates[] = "navbar_style = ?";
        $params[] = trim($data['navbar_style']);
    }

    // [CRITICAL FIX] Añadir soporte para guardar el logo principal
    if (array_key_exists('logo_principal', $data)) {
        $updates[] = "logo_principal = ?";
        $params[] = $data['logo_principal']; // Puede ser un string con el nombre o null
    }

    if (empty($updates)) {
        throw new Exception('No hay cambios para guardar');
    }

    // Ejecutar UPDATE
    $params[] = $tienda['id'];
    $sql = "UPDATE tiendas SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $db->prepare($sql);

    // [FIX] Bindeo explícito de parámetros para corregir el error de persistencia ("Bug F5")
    // en ciertas configuraciones de servidor, donde execute($params) puede fallar silenciosamente.
    $i = 1;
    foreach ($params as $param) {
        $stmt->bindValue($i++, $param);
    }
    
    $stmt->execute();

    echo json_encode([
        'success' => true,
        'message' => 'Cambios guardados correctamente'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
