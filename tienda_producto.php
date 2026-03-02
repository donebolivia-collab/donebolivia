<?php
/**
 * DONE! - Vista de Producto Optimizada para SEO Social
 * Versión 3.0: Detección de Bots y Ruta Amigable
 */

// ---------------------------------------------------------
// 1. DETECCIÓN DE CRAWLERS (WhatsApp, Facebook, Twitter)
// Servimos HTML puro y ultra-rápido para asegurar la tarjeta
// ---------------------------------------------------------
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$isBot = (stripos($userAgent, 'facebookexternalhit') !== false || 
          stripos($userAgent, 'WhatsApp') !== false || 
          stripos($userAgent, 'twitterbot') !== false ||
          stripos($userAgent, 'TelegramBot') !== false);

if ($isBot) {
    // Conexión directa y rápida solo para leer datos
    require_once 'config/database.php';
    
    $tienda_slug = $_GET['slug'] ?? '';
    $producto_id = $_GET['producto_id'] ?? 0;
    
    if ($tienda_slug && $producto_id) {
        try {
            $db = getDB();
            
            // Query optimizada: Solo lo necesario para la tarjeta
            $stmt = $db->prepare("
                SELECT p.titulo, p.descripcion, p.precio, 
                       t.nombre as tienda_nombre, t.logo,
                       (SELECT nombre_archivo FROM producto_imagenes WHERE producto_id = p.id ORDER BY es_principal DESC LIMIT 1) as imagen
                FROM productos p
                JOIN tiendas t ON t.slug = ?
                WHERE p.id = ?
            ");
            $stmt->execute([$tienda_slug, $producto_id]);
            $data = $stmt->fetch();
            
            if ($data) {
                $titulo = $data['titulo'];
                $precio = number_format($data['precio'], 2, '.', ',');
                $desc = mb_strimwidth(strip_tags($data['descripcion']), 0, 150, "...");
                
                // Construir URLs absolutas
                $imgUrl = !empty($data['imagen']) ? 'https://donebolivia.com/uploads/' . $data['imagen'] : 
                          (!empty($data['logo']) ? 'https://donebolivia.com/uploads/logos/' . $data['logo'] : 'https://donebolivia.com/assets/img/logo-default.png');
                
                $pageUrl = "https://donebolivia.com/tienda/$tienda_slug/producto/$producto_id";
                
                // SALIDA HTML PURO PARA EL BOT
                ?>
                <!DOCTYPE html>
                <html>
                <head>
                    <meta property="og:type" content="product">
                    <meta property="og:title" content="<?php echo htmlspecialchars($titulo . " | Bs. " . $precio); ?>">
                    <meta property="og:description" content="<?php echo htmlspecialchars($desc); ?>. De: <?php echo htmlspecialchars($data['tienda_nombre']); ?>">
                    <meta property="og:image" content="<?php echo $imgUrl; ?>">
                    <meta property="og:image:width" content="1200">
                    <meta property="og:image:height" content="630">
                    <meta property="og:url" content="<?php echo $pageUrl; ?>">
                    <meta name="twitter:card" content="summary_large_image">
                </head>
                <body>
                    <h1><?php echo htmlspecialchars($titulo); ?></h1>
                    <img src="<?php echo $imgUrl; ?>">
                </body>
                </html>
                <?php
                exit; // IMPORTANTE: Detener ejecución aquí para el bot
            }
        } catch (Exception $e) {
            // Silencio en error de bot
        }
    }
}

// ---------------------------------------------------------
// 2. CARGA NORMAL PARA USUARIOS (Si no es bot, sigue aquí)
// ---------------------------------------------------------
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Obtener slug de la tienda y ID del producto
$tienda_slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
$producto_id = isset($_GET['producto_id']) ? (int)$_GET['producto_id'] : 0;

if (!$tienda_slug || !$producto_id) {
    header('Location: /');
    exit;
}

try {
    $db = getDB();

    // Obtener información de la tienda
    $stmt = $db->prepare("SELECT * FROM tiendas WHERE slug = ? AND estado = 'activo'");
    $stmt->execute([$tienda_slug]);
    $tienda = $stmt->fetch();

    if (!$tienda) {
        header('Location: /');
        exit;
    }

    // Obtener información del producto
    $stmt = $db->prepare("
        SELECT p.*, u.nombre as vendedor_nombre, u.telefono, u.email as vendedor_email,
               u.fecha_registro as vendedor_desde, u.calificacion_promedio, u.total_ventas,
               u.foto_perfil as vendedor_foto,
               cat.nombre as categoria_nombre, cat.icono as categoria_icono,
               sub.nombre as subcategoria_nombre
        FROM productos p
        JOIN usuarios u ON p.usuario_id = u.id
        JOIN categorias cat ON p.categoria_id = cat.id
        LEFT JOIN subcategorias sub ON p.subcategoria_id = sub.id
        WHERE p.id = ? AND p.activo = 1 AND p.usuario_id = ?
    ");
    $stmt->execute([$producto_id, $tienda['usuario_id']]);
    $producto = $stmt->fetch();

    if (!$producto) {
        header('Location: /tienda/' . $tienda_slug);
        exit;
    }

    // Obtener imágenes del producto
    $stmt = $db->prepare("
        SELECT * FROM producto_imagenes
        WHERE producto_id = ?
        ORDER BY es_principal DESC, orden ASC
    ");
    $stmt->execute([$producto_id]);
    $imagenes = $stmt->fetchAll();

    // Incrementar vistas
    incrementarVistas($producto_id);

    // Verificar si está en favoritos
    $esFavorito = false;
    if (estaLogueado()) {
        $stmtFav = $db->prepare("SELECT 1 FROM favoritos WHERE usuario_id = ? AND producto_id = ?");
        $stmtFav->execute([$_SESSION['usuario_id'], $producto_id]);
        $esFavorito = $stmtFav->fetch() ? true : false;
    }

    // Decodificar menú
    $menu_items = !empty($tienda['menu_items']) ? json_decode($tienda['menu_items'], true) : [['label' => 'Todos', 'url' => '#']];

} catch (Exception $e) {
    error_log("Error al obtener producto de tienda: " . $e->getMessage());
    header('Location: /tienda/' . $tienda_slug);
    exit;
}

$titulo = $producto['titulo'] . ' - ' . $tienda['nombre'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($titulo); ?> - Done! Bolivia</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="/assets/css/product-view.css?v=1.0" rel="stylesheet">
    <style>
        :root {
            --color-primario: <?php echo htmlspecialchars($tienda['color_primario'] ?? '#FF6B35'); ?>;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f5f5;
            color: #1a1a1a;
            line-height: 1.6;
        }

        /* Navbar de Tienda */
        .store-navbar {
            background: white;
            border-bottom: 1px solid #e0e0e0;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .navbar-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0;
        }

        .store-header {
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid #f0f0f0;
        }

        .store-logo {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #f0f0f0;
        }

        .store-logo-placeholder {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--color-primario);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }

        .store-name {
            font-size: 18px;
            font-weight: 600;
            color: #1a1a1a;
        }

        .store-menu {
            display: flex;
            gap: 8px;
            padding: 12px 20px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
        }

        .store-menu::-webkit-scrollbar {
            display: none;
        }

        .menu-item {
            padding: 8px 16px;
            border-radius: 20px;
            border: 1px solid #e0e0e0;
            background: white;
            color: #666;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            white-space: nowrap;
            transition: all 0.2s;
            cursor: pointer;
        }

        .menu-item:hover {
            border-color: var(--color-primario);
            color: var(--color-primario);
        }

        .menu-item.active {
            background: var(--color-primario);
            color: white;
            border-color: var(--color-primario);
        }

        /* Breadcrumb */
        .breadcrumb {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #666;
        }

        .breadcrumb a {
            color: #666;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            color: var(--color-primario);
        }

        .breadcrumb i {
            font-size: 12px;
        }

        /* Contenedor principal */
        .pv-container {
            max-width: 1200px;
            margin: 0 auto 40px;
            padding: 0 20px;
        }

        /* Botón volver */
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            color: #666;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 20px;
            transition: all 0.2s;
        }

        .btn-back:hover {
            border-color: var(--color-primario);
            color: var(--color-primario);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .breadcrumb {
                padding: 0 16px;
                margin: 16px auto;
            }

            .pv-container {
                padding: 0 16px;
            }

            .store-header {
                padding: 12px 16px;
            }

            .store-menu {
                padding: 12px 16px;
            }
        }
    </style>
</head>
<body>

    <!-- Navbar de la Tienda -->
    <nav class="store-navbar">
        <div class="navbar-container">
            <div class="store-header">
                <?php if (!empty($tienda['logo'])): ?>
                    <img src="/uploads/logos/<?php echo htmlspecialchars($tienda['logo']); ?>" class="store-logo" alt="<?php echo htmlspecialchars($tienda['nombre']); ?>">
                <?php else: ?>
                    <div class="store-logo-placeholder">
                        <i class="fas fa-store"></i>
                    </div>
                <?php endif; ?>
                <span class="store-name"><?php echo htmlspecialchars($tienda['nombre']); ?></span>
            </div>

            <div class="store-menu">
                <?php foreach ($menu_items as $index => $item): ?>
                    <a href="/tienda/<?php echo htmlspecialchars($tienda_slug); ?>#productos" class="menu-item">
                        <?php echo htmlspecialchars($item['label']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </nav>

    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <a href="/tienda/<?php echo htmlspecialchars($tienda_slug); ?>">
            <i class="fas fa-store"></i> <?php echo htmlspecialchars($tienda['nombre']); ?>
        </a>
        <i class="fas fa-chevron-right"></i>
        <span><?php echo htmlspecialchars($producto['titulo']); ?></span>
    </div>

    <div class="pv-container">
        <!-- Botón volver a la tienda -->
        <a href="/tienda/<?php echo htmlspecialchars($tienda_slug); ?>" class="btn-back">
            <i class="fas fa-arrow-left"></i>
            Volver a la tienda
        </a>

        <!-- 1. TÍTULO + UBICACIÓN -->
        <div class="pv-card pv-header">
            <h1 class="pv-title"><?php echo htmlspecialchars($producto['titulo']); ?></h1>
            <div class="pv-meta">
                <div class="pv-meta-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>
                        <?php
                        $ubicacion = '';
                        if (!empty($producto['municipio_nombre']) && !empty($producto['departamento_nombre'])) {
                            $ubicacion = htmlspecialchars($producto['municipio_nombre']) . ', ' . htmlspecialchars($producto['departamento_nombre']);
                        } elseif (!empty($producto['departamento_nombre'])) {
                            $ubicacion = htmlspecialchars($producto['departamento_nombre']);
                        } else {
                            $ubicacion = 'Bolivia';
                        }
                        echo $ubicacion;
                        ?>
                    </span>
                </div>
                <div class="pv-meta-item">
                    <i class="fas fa-clock"></i>
                    <span>Publicado hace <?php echo tiempoTranscurrido($producto['fecha_publicacion']); ?></span>
                </div>
                <div class="pv-meta-item">
                    <i class="fas fa-eye"></i>
                    <span><?php echo number_format($producto['vistas']); ?> vistas</span>
                </div>
            </div>
        </div>

        <!-- 2. GALERÍA DE IMÁGENES -->
        <section class="pv-gallery">
            <?php if (!empty($imagenes)): ?>
                <div class="pv-gallery-main">
                    <img src="/uploads/<?php echo $imagenes[0]['nombre_archivo']; ?>"
                         alt="<?php echo htmlspecialchars($producto['titulo']); ?>"
                         id="pvMainImage"
                         class="pv-main-img">

                    <?php if (count($imagenes) > 1): ?>
                    <button class="pv-nav pv-nav--prev" onclick="pvPrevImage()">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="pv-nav pv-nav--next" onclick="pvNextImage()">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                    <div class="pv-counter">
                        <span id="pvCounter">1</span>/<?php echo count($imagenes); ?>
                    </div>
                    <?php endif; ?>

                    <div class="pv-float-actions">
                        <button class="pv-float-btn" onclick="pvShare()" title="Compartir">
                            <i class="fas fa-share-alt"></i>
                        </button>
                        <?php if (estaLogueado()): ?>
                        <button class="pv-float-btn pv-fav-btn <?php echo $esFavorito ? 'active' : ''; ?>"
                                data-id="<?php echo $producto_id; ?>"
                                title="<?php echo $esFavorito ? 'Quitar de favoritos' : 'Guardar en favoritos'; ?>">
                            <i class="<?php echo $esFavorito ? 'fas' : 'far'; ?> fa-heart"></i>
                        </button>
                        <?php else: ?>
                        <a href="/auth/login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>"
                           class="pv-float-btn" title="Inicia sesión para guardar">
                            <i class="far fa-heart"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (count($imagenes) > 1): ?>
                <div class="pv-thumbs">
                    <?php foreach ($imagenes as $i => $img): ?>
                    <button class="pv-thumb <?php echo $i === 0 ? 'active' : ''; ?>"
                            onclick="pvGoToImage(<?php echo $i; ?>)">
                        <img src="/uploads/<?php echo $img['nombre_archivo']; ?>" alt="Miniatura" loading="lazy">
                    </button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="pv-no-image">
                    <i class="fas fa-image"></i>
                    <p>Sin imagen</p>
                </div>
            <?php endif; ?>
        </section>

        <!-- 3. DESCRIPCIÓN -->
        <div class="pv-card pv-description">
            <h2 class="pv-section-title">Descripción</h2>
            <div class="pv-description-text">
                <?php echo nl2br(htmlspecialchars($producto['descripcion'])); ?>
            </div>
        </div>

        <!-- 4. PRECIO -->
        <div class="pv-card pv-price-section">
            <h2 class="pv-section-title">Precio</h2>
            <div class="pv-price"><?php echo formatearPrecio($producto['precio']); ?></div>
            <div class="pv-price-literal"><?php echo convertirPrecioALiteral($producto['precio']); ?></div>

            <?php if (!empty($producto['envio_gratis'])): ?>
            <div style="display: flex; gap: 8px; margin-top: 12px; flex-wrap: wrap;">
                <span class="badge-delivery-green" style="font-size: 13px; padding: 4px 8px;">
                    <img src="/assets/img/delivery.png" class="truck-icon" alt=""> Envío Gratis
                </span>
            </div>
            <?php endif; ?>
        </div>

        <!-- 5. VENDEDOR + CONTACTO -->
        <div class="pv-card pv-seller">
            <h2 class="pv-section-title">Vendedor</h2>
            <div class="pv-seller-card">
                <div class="pv-seller-avatar">
                    <?php if (!empty($producto['vendedor_foto'])): ?>
                    <img src="/uploads/perfiles/<?php echo htmlspecialchars($producto['vendedor_foto']); ?>" alt="">
                    <?php else: ?>
                    <i class="fas fa-user-circle"></i>
                    <?php endif; ?>
                </div>
                <div class="pv-seller-info">
                    <h3 class="pv-seller-name"><?php echo htmlspecialchars($producto['vendedor_nombre']); ?></h3>
                    <div class="pv-seller-rating">
                        <?php
                        $rating = $producto['calificacion_promedio'] ?? 0;
                        for ($i = 1; $i <= 5; $i++): ?>
                            <i class="<?php echo $i <= $rating ? 'fas' : 'far'; ?> fa-star"></i>
                        <?php endfor; ?>
                        <span>(<?php echo number_format($rating, 1); ?>)</span>
                    </div>
                    <div class="pv-seller-meta">
                        <span><?php echo $producto['total_ventas'] ?? 0; ?> ventas</span>
                        <span>•</span>
                        <span>Miembro desde <?php echo date('Y', strtotime($producto['vendedor_desde'])); ?></span>
                    </div>
                </div>
            </div>
            <a href="<?php echo generarEnlaceWhatsApp($producto['telefono'], 'Hola, vi tu producto "' . $producto['titulo'] . '" en tu tienda de Done! Bolivia: ' . 'https://donebolivia.com/tienda/' . $tienda_slug . '/producto/' . $producto_id); ?>"
               target="_blank"
               class="pv-btn-whatsapp">
                <i class="fab fa-whatsapp"></i>
                Contactar por WhatsApp
            </a>
        </div>

        <!-- 6. CONSEJOS DE SEGURIDAD -->
        <div class="pv-card pv-safety">
            <h2 class="pv-section-title">
                <i class="fas fa-shield-alt"></i>
                Consejos de seguridad
            </h2>
            <ul class="pv-safety-list">
                <li><i class="fas fa-check"></i> Reúnete en lugares públicos y seguros</li>
                <li><i class="fas fa-check"></i> Inspecciona el producto antes de pagar</li>
                <li><i class="fas fa-check"></i> No hagas pagos por adelantado</li>
                <li><i class="fas fa-check"></i> Confía en tu instinto</li>
            </ul>
        </div>

        <!-- 7. BOTÓN DE REPORTE -->
        <?php if (estaLogueado() && $_SESSION['usuario_id'] != $producto['usuario_id']): ?>
        <div class="pv-card pv-report-section">
            <button class="pv-report-btn" onclick="alert('Función de reporte disponible próximamente')">
                <i class="fas fa-flag"></i>
                Reportar este anuncio
            </button>
        </div>
        <?php endif; ?>

    </div>

    <script>
    // ===== GALERÍA DE IMÁGENES =====
    const pvImages = <?php echo json_encode(array_column($imagenes, 'nombre_archivo')); ?>;
    let pvIndex = 0;

    function pvGoToImage(index) {
        pvIndex = index;
        document.getElementById('pvMainImage').src = '/uploads/' + pvImages[index];
        document.getElementById('pvCounter').textContent = index + 1;

        document.querySelectorAll('.pv-thumb').forEach((t, i) => {
            t.classList.toggle('active', i === index);
        });
    }

    function pvNextImage() {
        pvGoToImage((pvIndex + 1) % pvImages.length);
    }

    function pvPrevImage() {
        pvGoToImage((pvIndex - 1 + pvImages.length) % pvImages.length);
    }

    // ===== COMPARTIR =====
    function pvShare() {
        const data = {
            title: <?php echo json_encode($producto["titulo"]); ?>,
            text: <?php echo json_encode($producto["titulo"] . ' - ' . formatearPrecio($producto["precio"])); ?>,
            url: window.location.href
        };

        if (navigator.share) {
            navigator.share(data).catch(() => {});
        } else {
            navigator.clipboard.writeText(window.location.href).then(() => {
                alert('¡Enlace copiado!');
            }).catch(() => {
                prompt('Copia este enlace:', window.location.href);
            });
        }
    }

    // ===== FAVORITOS =====
    document.addEventListener('DOMContentLoaded', () => {
        const favBtn = document.querySelector('.pv-fav-btn');
        if (!favBtn) return;

        favBtn.addEventListener('click', function() {
            const id = this.dataset.id;

            fetch('/api/toggle_favorite.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'product_id=' + id
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    this.classList.toggle('active', data.is_favorite);
                    const icon = this.querySelector('i');
                    icon.className = data.is_favorite ? 'fas fa-heart' : 'far fa-heart';
                    this.title = data.is_favorite ? 'Quitar de favoritos' : 'Guardar en favoritos';
                }
            });
        });
    });

    // ===== NAVEGACIÓN CON TECLADO =====
    document.addEventListener('keydown', (e) => {
        if (pvImages.length > 1) {
            if (e.key === 'ArrowLeft') pvPrevImage();
            if (e.key === 'ArrowRight') pvNextImage();
        }
    });

    // ===== SWIPE EN MÓVIL =====
    (function() {
        const gallery = document.querySelector('.pv-gallery-main');
        if (!gallery || pvImages.length <= 1) return;

        let startX = 0;

        gallery.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
        }, { passive: true });

        gallery.addEventListener('touchend', (e) => {
            const diff = startX - e.changedTouches[0].clientX;
            if (Math.abs(diff) > 50) {
                diff > 0 ? pvNextImage() : pvPrevImage();
            }
        }, { passive: true });
    })();
    </script>

</body>
</html>
