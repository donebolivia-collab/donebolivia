<?php
// Funciones auxiliares para CAMBALACHE

// Cargar sistema de caché
require_once __DIR__ . '/cache.php';

// Iniciar sesión si no está iniciada
function iniciarSesion() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
}

// Verificar si el usuario está logueado
function estaLogueado() {
    iniciarSesion();
    return isset($_SESSION['usuario_id']);
}

// Obtener datos del usuario actual
function obtenerUsuarioActual() {
    if (!estaLogueado()) return null;
    
    $db = getDB();
    $stmt = $db->prepare("
        SELECT u.*, 
               COALESCE(c.nombre, u.municipio_nombre) as ciudad_nombre, 
               COALESCE(c.departamento, u.departamento_nombre) as departamento 
        FROM usuarios u 
        LEFT JOIN ciudades c ON u.ciudad_id = c.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION["usuario_id"]]);
    $usuario = $stmt->fetch();
    
    // Asegurar que siempre haya un nombre para mostrar
    if ($usuario && empty($usuario["nombre"])) {
        $usuario["nombre"] = explode("@", $usuario["email"])[0];
    }
    
    return $usuario;
}


// Limpiar y validar entrada
function limpiarEntrada($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Validar email
function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Validar teléfono boliviano
function validarTelefono($telefono) {
    // Acepta formatos: 70123456, +59170123456, 59170123456
    $patron = '/^(\+?591)?[67]\d{7}$/';
    return preg_match($patron, $telefono);
}

// Generar hash de contraseña
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Verificar contraseña
function verificarPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Subir imagen (OPTIMIZADA PROFESIONALMENTE A WEBP - CON DETECCIÓN DE DUPLICADOS)
function subirImagen($archivo, $carpeta = 'productos', $rutaBase = null, $producto_id = null) {
    // Si no se define ruta base, usar UPLOAD_PATH por defecto
    $base = $rutaBase ?? UPLOAD_PATH;
    
    // Si la carpeta es vacía, no añadir barra extra
    $subcarpeta = $carpeta ? $carpeta . '/' : '';
    $directorioDestino = $base . $subcarpeta;
    
    // Crear directorio si no existe
    if (!file_exists($directorioDestino)) {
        mkdir($directorioDestino, 0755, true);
    }
    
    // Validar archivo usando MIME type real
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $archivo['tmp_name']);
    finfo_close($finfo);

    $tiposPermitidos = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    if (!in_array($mime_type, $tiposPermitidos)) {
        return ['error' => 'Tipo de archivo no permitido'];
    }
    
    // Definir límite de tamaño inicial (para rechazar archivos absurdamente grandes antes de procesar)
    $limitSize = 20 * 1024 * 1024; // 20MB Límite duro
    if ($archivo['size'] > $limitSize) {
        return ['error' => "El archivo es demasiado grande (Máximo 20MB)"];
    }
    
    // NUEVO: Calcular hash del archivo para detectar duplicados
    $hash_archivo = hash_file('sha256', $archivo['tmp_name']);
    
    // NUEVO: Verificar si ya existe un archivo con el mismo hash
    if ($carpeta === 'productos' && $producto_id) {
        $stmt = getDB()->prepare("
            SELECT pi.nombre_archivo 
            FROM producto_imagenes pi 
            JOIN productos p ON pi.producto_id = p.id
            WHERE p.usuario_id = ? AND pi.hash_archivo = ?
        ");
        $stmt->execute([$_SESSION['usuario_id'] ?? 0, $hash_archivo]);
        $duplicado = $stmt->fetch();
        
        if ($duplicado) {
            return [
                'success' => true, 
                'archivo' => $duplicado['nombre_archivo'],
                'duplicado' => true,
                'mensaje' => 'Imagen ya existente (reutilizada)'
            ];
        }
    }
    
    // Generar nombre único .webp
    $nombreBase = uniqid() . '_' . time();
    $nombreArchivo = $nombreBase . '.webp';
    $rutaCompleta = $directorioDestino . $nombreArchivo;
    
    // PROCESAMIENTO DE IMAGEN (GD LIBRARY)
    try {
        // 1. Cargar imagen original según tipo
        $imagenOriginal = null;
        switch ($mime_type) {
            case 'image/jpeg':
            case 'image/jpg':
                $imagenOriginal = imagecreatefromjpeg($archivo['tmp_name']);
                break;
            case 'image/png':
                $imagenOriginal = imagecreatefrompng($archivo['tmp_name']);
                // Preservar transparencia para PNG si es necesario (aunque WebP lo soporta mejor)
                imagepalettetotruecolor($imagenOriginal);
                imagealphablending($imagenOriginal, true);
                imagesavealpha($imagenOriginal, true);
                break;
            case 'image/webp':
                $imagenOriginal = imagecreatefromwebp($archivo['tmp_name']);
                break;
        }

        if (!$imagenOriginal) {
            throw new Exception("No se pudo cargar la imagen original.");
        }

        // 2. Redimensionar si es muy grande (Max 1200px)
        $anchoMax = 1200;
        $altoMax = 1200;
        $anchoOriginal = imagesx($imagenOriginal);
        $altoOriginal = imagesy($imagenOriginal);
        
        $nuevoAncho = $anchoOriginal;
        $nuevoAlto = $altoOriginal;

        // Calcular nuevas dimensiones manteniendo aspecto
        if ($anchoOriginal > $anchoMax || $altoOriginal > $altoMax) {
            $ratio = $anchoOriginal / $altoOriginal;
            if ($anchoOriginal > $altoOriginal) {
                $nuevoAncho = $anchoMax;
                $nuevoAlto = $anchoMax / $ratio;
            } else {
                $nuevoAlto = $altoMax;
                $nuevoAncho = $altoMax * $ratio;
            }
        }

        $imagenFinal = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
        
        // Manejar transparencia para WebP
        imagealphablending($imagenFinal, false);
        imagesavealpha($imagenFinal, true);
        $transparent = imagecolorallocatealpha($imagenFinal, 255, 255, 255, 127);
        imagefilledrectangle($imagenFinal, 0, 0, $nuevoAncho, $nuevoAlto, $transparent);

        // Copiar y redimensionar
        imagecopyresampled($imagenFinal, $imagenOriginal, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $anchoOriginal, $altoOriginal);

        // 3. Guardar como WebP (Calidad 80 - Balance perfecto peso/calidad)
        if (imagewebp($imagenFinal, $rutaCompleta, 80)) {
            // Limpiar memoria
            imagedestroy($imagenOriginal);
            imagedestroy($imagenFinal);
            
            // NUEVO: Guardar hash en la base de datos para futuras detecciones
            if ($carpeta === 'productos' && $producto_id) {
                try {
                    $db = getDB();
                    $stmt = $db->prepare("
                        UPDATE producto_imagenes 
                        SET hash_archivo = ? 
                        WHERE nombre_archivo = ? AND producto_id = ?
                    ");
                    $stmt->execute([$hash_archivo, $subcarpeta . $nombreArchivo, $producto_id]);
                } catch (Exception $e) {
                    error_log("Error guardando hash de imagen: " . $e->getMessage());
                }
            }
            
            return [
                'success' => true, 
                'archivo' => $subcarpeta . $nombreArchivo,
                'hash' => $hash_archivo,
                'duplicado' => false
            ];
        } else {
            throw new Exception("Error al guardar la imagen como WebP.");
        }

    } catch (Exception $e) {
        // Limpiar memoria en caso de error
        if (isset($imagenOriginal)) imagedestroy($imagenOriginal);
        if (isset($imagenFinal)) imagedestroy($imagenFinal);
        
        // Eliminar archivo temporal si se creó
        if (file_exists($rutaCompleta)) {
            unlink($rutaCompleta);
        } else {
            return ['error' => 'Error crítico al subir el archivo'];
        }
    }
}

// Formatear precio (siempre entero, sin decimales)
function formatearPrecio($precio) {
    // Redondear al entero más cercano (Bolivia no usa decimales en precios)
    $precioEntero = round($precio);
    return 'Bs. ' . number_format($precioEntero, 0, ',', '.');
}

// Formatear fecha
function formatearFecha($fecha) {
    $fechaObj = new DateTime($fecha);
    return $fechaObj->format('d/m/Y H:i');
}

// Tiempo transcurrido
function tiempoTranscurrido($fecha) {
    $ahora = new DateTime();
    $fechaObj = new DateTime($fecha);
    $diferencia = $ahora->diff($fechaObj);
    
    if ($diferencia->days > 0) {
        return $diferencia->days . ' día' . ($diferencia->days > 1 ? 's' : '');
    } elseif ($diferencia->h > 0) {
        return $diferencia->h . ' hora' . ($diferencia->h > 1 ? 's' : '');
    } elseif ($diferencia->i > 0) {
        return $diferencia->i . ' minuto' . ($diferencia->i > 1 ? 's' : '');
    } else {
        return 'Hace un momento';
    }
}

// Generar enlace de WhatsApp
function generarEnlaceWhatsApp($telefono, $mensaje) {
    // Limpiar número de teléfono
    $telefono = preg_replace('/[^0-9]/', '', $telefono);
    
    // Agregar código de país si no lo tiene
    if (!str_starts_with($telefono, '591')) {
        $telefono = '591' . $telefono;
    }
    
    $mensajeCodificado = urlencode($mensaje);
    return "https://wa.me/{$telefono}?text={$mensajeCodificado}";
}

// Obtener categorías (con caché)
function obtenerCategorias() {
    $cache = getCache();
    
    return $cache->remember('categorias', function() {
        $db = getDB();
        $stmt = $db->query("SELECT * FROM categorias WHERE activo = 1 ORDER BY nombre");
        return $stmt->fetchAll();
    }, 3600); // Caché por 1 hora
}

// Obtener subcategorías por categoría (SIN caché temporalmente para debug)
function obtenerSubcategorias($categoria_id) {
    // Caché deshabilitado temporalmente para asegurar datos frescos
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM subcategorias WHERE categoria_id = ? ORDER BY nombre");
    $stmt->execute([$categoria_id]);
    return $stmt->fetchAll();
}

// Obtener ciudades (con caché)
function obtenerCiudades() {
    $cache = getCache();
    
    return $cache->remember('ciudades', function() {
        $db = getDB();
        $stmt = $db->query("SELECT * FROM ciudades ORDER BY nombre");
        return $stmt->fetchAll();
    }, 7200); // Caché por 2 horas (cambian poco)
}

// Obtener productos destacados
function obtenerProductosDestacados($limite = 8) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT p.*, u.nombre as vendedor_nombre, u.telefono,
               cat.nombre as categoria_nombre, pi.nombre_archivo as imagen_principal
        FROM productos p
        JOIN usuarios u ON p.usuario_id = u.id
        JOIN categorias cat ON p.categoria_id = cat.id
        LEFT JOIN producto_imagenes pi ON p.id = pi.producto_id AND pi.es_principal = 1
        WHERE p.activo = 1
        ORDER BY p.destacado DESC, p.fecha_publicacion DESC
        LIMIT ?
    ");
    $stmt->execute([$limite]);
    return $stmt->fetchAll();
}

// Buscar productos
function buscarProductos($termino = '', $categoria_id = null, $ciudad_id = null, $precio_min = null, $precio_max = null, $pagina = 1, $por_pagina = 12, $subcategoria_id = null, $departamento_codigo = null, $orden = 'reciente') {
    $db = getDB();

    $where = ["p.activo = 1"];
    $params = [];

    if (!empty($termino)) {
        // Lógica Fuzzy Search (Búsqueda Indulgente)
        // 1. LIKE estándar para coincidencias parciales
        // 2. SOUNDEX para coincidencias fonéticas (ej: "Samsun" encuentra "Samsung")
        $where[] = "(p.titulo LIKE ? OR p.descripcion LIKE ? OR SOUNDEX(p.titulo) = SOUNDEX(?))";
        $params[] = "%{$termino}%";
        $params[] = "%{$termino}%";
        $params[] = $termino;
    }

    if ($categoria_id) {
        $where[] = "p.categoria_id = ?";
        $params[] = $categoria_id;
    }

    if ($subcategoria_id) {
        $where[] = "p.subcategoria_id = ?";
        $params[] = $subcategoria_id;
    }

    // Filtro por departamento
    if (!empty($departamento_codigo)) {
        $where[] = "p.departamento_codigo = ?";
        $params[] = $departamento_codigo;
    }

    if ($precio_min) {
        $where[] = "p.precio >= ?";
        $params[] = $precio_min;
    }

    if ($precio_max) {
        $where[] = "p.precio <= ?";
        $params[] = $precio_max;
    }

    $whereClause = implode(" AND ", $where);
    $offset = ($pagina - 1) * $por_pagina;

    // Determinar ordenamiento
    $orderClause = "p.destacado DESC, p.fecha_publicacion DESC"; // Por defecto
    switch ($orden) {
        case 'precio_bajo':
            $orderClause = "p.precio ASC, p.fecha_publicacion DESC";
            break;
        case 'precio_alto':
            $orderClause = "p.precio DESC, p.fecha_publicacion DESC";
            break;
        case 'reciente':
        default:
            $orderClause = "p.fecha_publicacion DESC";
            break;
    }

    // Contar total
    $stmtCount = $db->prepare("
        SELECT COUNT(*) as total
        FROM productos p
        WHERE {$whereClause}
    ");
    $stmtCount->execute($params);
    $total = $stmtCount->fetch()['total'];
    
    // Obtener productos
    $params[] = $por_pagina;
    $params[] = $offset;
    
    $stmt = $db->prepare("
        SELECT p.*, u.nombre as vendedor_nombre, u.telefono,
               cat.nombre as categoria_nombre, pi.nombre_archivo as imagen_principal
        FROM productos p
        JOIN usuarios u ON p.usuario_id = u.id
        JOIN categorias cat ON p.categoria_id = cat.id
        LEFT JOIN producto_imagenes pi ON p.id = pi.producto_id AND pi.es_principal = 1
        WHERE {$whereClause}
        ORDER BY {$orderClause}
        LIMIT ? OFFSET ?
    ");
    $stmt->execute($params);
    $productos = $stmt->fetchAll();

    if (!empty($productos)) {
        // Obtener todos los badges para los productos obtenidos
        $producto_ids = array_map(function($p) { return $p['id']; }, $productos);
        $placeholders = implode(',', array_fill(0, count($producto_ids), '?'));

        $sql_badges = "SELECT producto_id, badge_id FROM producto_badges WHERE producto_id IN ($placeholders)";
        $stmt_badges = $db->prepare($sql_badges);
        $stmt_badges->execute($producto_ids);
        $badges_map = [];
        while ($row = $stmt_badges->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($badges_map[$row['producto_id']])) {
                $badges_map[$row['producto_id']] = [];
            }
            $badges_map[$row['producto_id']][] = $row['badge_id'];
        }

        // Adjuntar los badges a cada producto
        for ($i = 0; $i < count($productos); $i++) {
            $productos[$i]['badges'] = $badges_map[$productos[$i]['id']] ?? [];
        }
    }
    
    return [
        'productos' => $productos,
        'total' => $total,
        'paginas' => ceil($total / $por_pagina),
        'pagina_actual' => $pagina
    ];
}

// Incrementar vistas de producto
function incrementarVistas($producto_id) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE productos SET vistas = vistas + 1 WHERE id = ?");
    $stmt->execute([$producto_id]);
}

// Redireccionar
function redireccionar($url) {
    if (!headers_sent()) {
        header("Location: " . $url);
        exit();
    }
    $u = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><meta charset="utf-8">';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . $u . '"></noscript>';
    echo '<script>location.replace(' . json_encode($url) . ');</script>';
    exit();
}

// Convertir precio a literal (siempre entero, sin centavos)
function convertirPrecioALiteral($precio) {
    // Redondear al entero más cercano (Bolivia no usa centavos en precios)
    $precioEntero = round($precio);
    $texto = numeroATexto($precioEntero) . " bolivianos";
    return ucfirst($texto);
}

// Convertir número a texto en español
function numeroATexto($numero) {
    $unidades = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve'];
    $decenas = ['', '', 'veinte', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
    $especiales = ['diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve'];
    $veintitantos = ['veinte', 'veintiuno', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco', 'veintiséis', 'veintisiete', 'veintiocho', 'veintinueve'];
    $centenas = ['', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos'];
    
    if ($numero == 0) return 'cero';
    if ($numero == 100) return 'cien';
    
    $resultado = '';
    
    // Millones
    if ($numero >= 1000000) {
        $millones = floor($numero / 1000000);
        if ($millones == 1) {
            $resultado .= 'un millón ';
        } else {
            $resultado .= numeroATexto($millones) . ' millones ';
        }
        $numero %= 1000000;
    }
    
    // Miles
    if ($numero >= 1000) {
        $miles = floor($numero / 1000);
        if ($miles == 1) {
            $resultado .= 'mil ';
        } else {
            $resultado .= numeroATexto($miles) . ' mil ';
        }
        $numero %= 1000;
    }
    
    // Centenas
    if ($numero >= 100) {
        $centena = floor($numero / 100);
        $resultado .= $centenas[$centena] . ' ';
        $numero %= 100;
    }
    
    // Decenas y unidades
    if ($numero >= 30) {
        $decena = floor($numero / 10);
        $unidad = $numero % 10;
        $resultado .= $decenas[$decena];
        if ($unidad > 0) {
            $resultado .= ' y ' . $unidades[$unidad];
        }
    } elseif ($numero >= 20) {
        // 20-29: veintiuno, veintidós, etc.
        $resultado .= $veintitantos[$numero - 20];
    } elseif ($numero >= 10) {
        // 10-19: diez, once, doce, etc.
        $resultado .= $especiales[$numero - 10];
    } elseif ($numero > 0) {
        // 1-9
        $resultado .= $unidades[$numero];
    }
    
    return trim($resultado);
}

// Mostrar mensaje de error o éxito
function mostrarMensaje($tipo, $mensaje) {
    $clase = $tipo === 'error' ? 'alert-danger' : 'alert-success';
    return "<div class='alert {$clase} alert-dismissible fade show' role='alert'>
                {$mensaje}
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
}

// Imprime los estilos para los badges (para usar en el <head> de la página)
function imprimirEstilosBadges() {
    // comentado para debug
}

// Renderiza los badges de un producto y devuelve el HTML
function renderizarBadgesProducto($producto) {
    if (empty($producto['badges'])) {
        return '';
    }

    global $db;
    if (!$db) {
        $db = getDB();
    }

    // 1. Obtener los IDs de los badges del producto
    $badge_ids = is_array($producto['badges']) ? $producto['badges'] : json_decode($producto['badges'], true);
    if (empty($badge_ids) || !is_array($badge_ids)) {
        return '';
    }
    
    // 2. Crear placeholders para la consulta SQL (?,?,?)
    $placeholders = implode(',', array_fill(0, count($badge_ids), '?'));

    // 3. Consultar la tabla `badges` para obtener la información de todos los badges necesarios en una sola consulta
    $stmt = $db->prepare("SELECT slug, nombre, svg_path FROM badges WHERE id IN ($placeholders) AND activo = 1 ORDER BY orden ASC");
    $stmt->execute($badge_ids);
    $badges_info = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($badges_info)) {
        return '';
    }

    // 4. Construir el HTML
    $output = '<div class="product-badges-shein">';
    foreach ($badges_info as $badge) {
        $output .= sprintf(
            '<img src="/%s?v=%s" class="badge-completo" alt="%s" title="%s">',
            htmlspecialchars($badge['svg_path']),
            time(), // Cache busting
            htmlspecialchars($badge['nombre']),
            htmlspecialchars($badge['nombre'])
        );
    }
    $output .= '</div>';

    return $output;
}

// Obtener tienda de un usuario (para header)
function obtenerTiendaUsuario($usuario_id) {
    $cache = getCache();
    // Cachear resultado para no saturar BD en cada carga de página
    return $cache->remember("tienda_user_{$usuario_id}", function() use ($usuario_id) {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, nombre, slug FROM tiendas WHERE usuario_id = ? LIMIT 1");
        $stmt->execute([$usuario_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }, 600); // 10 minutos de caché
}

// Obtener imagen de banner para Feria (Optimizado para Performance)
function getBannerImage($slug, $aliases = [], $dbPath = null) {
    // 1. Prioridad: Ruta directa de BD
    if (!empty($dbPath)) {
        // Limpiar query strings si existen
        $cleanPath = preg_replace('/\?.*/', '', $dbPath);
        
        // Verificar existencia física (Manejo de rutas relativas)
        $docRoot = dirname(__DIR__); // Asumiendo que includes está a un nivel de la raíz
        // Normalizar separadores para Windows
        $physPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $docRoot . $cleanPath);
        
        if (file_exists($physPath)) {
            return $cleanPath; // Retornar ruta web limpia (sin ?v=...)
        }
    }

    // 2. Fallback: Búsqueda en sistema de archivos (Solo si falla DB)
    $base_path = '/assets/img/feria_banners/';
    $local_path = dirname(__DIR__) . $base_path;
    $local_path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $local_path);

    // Lista de prefijos a buscar
    $search_prefixes = array_merge([$slug], $aliases); 

    foreach ($search_prefixes as $prefix) {
        // Buscar archivos que empiecen con el prefijo
        $pattern = $local_path . $prefix . '*.*';
        $matches = glob($pattern);
        
        if (!empty($matches)) {
            // Ordenar por fecha de modificación (más reciente primero)
            usort($matches, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            
            $filename = basename($matches[0]);
            return $base_path . $filename;
        }
    }
    
    // 3. Placeholder final
    return $base_path . 'placeholder.png';
}

// Procesar Logo de Tienda (Normalización y Verificación Física)
function processStoreLogo($logoPath) {
    // Default
    $defaultLogo = null; // CAMBIO: Null para permitir fallback de ícono en frontend
    
    if (empty($logoPath)) {
        return $defaultLogo;
    }

    // 1. Limpieza básica: quitar query strings viejos (?v=...)
    $clean_logo = preg_replace('/\?.*/', '', $logoPath);
    
    // 2. Si es URL absoluta (http/https), retornarla tal cual
    if (strpos($clean_logo, 'http') === 0) {
        return $clean_logo;
    }

    // 3. Normalización de ruta relativa
    // Si no empieza con /, asumimos que está en /uploads/logos/
    if (strpos($clean_logo, '/') !== 0) {
        $candidate_rel = '/uploads/logos/' . $clean_logo;
    } else {
        $candidate_rel = $clean_logo;
    }

    // 4. Verificación de existencia física (Self-Healing)
    $docRoot = dirname(__DIR__); // Asumiendo estructura estándar
    $phys_path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $docRoot . $candidate_rel);

    if (file_exists($phys_path)) {
        // Si existe, retornamos con timestamp para evitar caché agresivo en cambios
        return $candidate_rel; // . '?v=' . time(); // Comentado para mejor caché del navegador
    } else {
        // Log opcional para debug (invisible al usuario)
        // error_log("Imagen no encontrada: " . $phys_path);
        return $defaultLogo;
    }
}
?>
