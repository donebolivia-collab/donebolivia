<?php
/**
 * DONE! - Componentes Reutilizables
 * Funciones para generar HTML de componentes comunes
 */

/**
 * Renderizar tarjeta de categoría
 */
function renderCategoryCard($categoria, $icono, $href, $slug) {
    // Buscar imagen disponible en varias extensiones
    $exts = ['jpg', 'jpeg', 'png', 'webp'];
    $bgUrl = '';
    
    foreach ($exts as $ext) {
        $tryUrl = '/assets/img/cats/' . $slug . '.' . $ext;
        $tryFs = $_SERVER['DOCUMENT_ROOT'] . $tryUrl;
        if (file_exists($tryFs)) {
            $bgUrl = $tryUrl;
            break;
        }
    }
    
    if (empty($bgUrl)) {
        $bgUrl = '/assets/img/cats/' . $slug . '.png'; // Fallback
    }
    
    $pngUrl = '/assets/img/cats/' . $slug . '.png';
    $pngFs = $_SERVER['DOCUMENT_ROOT'] . $pngUrl;
    $src = file_exists($pngFs) ? $pngUrl : $bgUrl;
    
    ob_start();
    ?>
    <a class="yx-card yx-card--caption yx-card--bg-gray yx-card--<?php echo $slug; ?>" href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="cap-thumb" <?php if ($categoria === 'Prendas') echo 'style="display:flex;align-items:flex-end;padding:0 4px 6px;"'; ?>>
            <?php if (!empty($src)): ?>
                <img class="cap-img" 
                     src="<?php echo htmlspecialchars($src, ENT_QUOTES, 'UTF-8'); ?>" 
                     alt="<?php echo htmlspecialchars($categoria, ENT_QUOTES, 'UTF-8'); ?>"
                     loading="lazy"
                     <?php if ($categoria === 'Prendas') echo 'style="max-height:96%"'; ?>>
            <?php else: ?>
                <div class="ico"><?php echo $icono; ?></div>
            <?php endif; ?>
        </div>
        <div class="cap-label"><?php echo htmlspecialchars($categoria); ?></div>
    </a>
    <?php
    return ob_get_clean();
}

/**
 * Renderizar tarjeta de producto
 */
function renderProductCard($producto, $showFavorite = false) {
    $titulo = htmlspecialchars($producto['titulo'] ?? '', ENT_QUOTES, 'UTF-8');
    $precio = isset($producto['precio']) ? formatearPrecio($producto['precio']) : 'Consultar';
    $imagen = $producto['imagen_principal'] ?? $producto['img'] ?? '';
    $id = $producto['id'] ?? 0;
    $href = '/products/view_product.php?id=' . $id;
    
    ob_start();
    ?>
    <div class="product-card">
        <a href="<?php echo $href; ?>" class="product-card-link">
            <?php if (!empty($imagen)): ?>
                <img src="/uploads/<?php echo htmlspecialchars($imagen, ENT_QUOTES, 'UTF-8'); ?>" 
                     alt="<?php echo $titulo; ?>" 
                     class="product-image"
                     loading="lazy">
            <?php else: ?>
                <div class="product-no-image">
                    <i class="fas fa-image"></i>
                    <p>Sin imagen</p>
                </div>
            <?php endif; ?>
            
            <div class="product-info">
                <h3 class="product-title" title="<?php echo $titulo; ?>"><?php echo $titulo; ?></h3>
                <p class="product-price"><?php echo $precio; ?></p>
                
                <!-- Badges de Envío -->
                                
                <?php if (isset($producto['ciudad_nombre'])): ?>
                    <p class="product-location">
                        <i class="fas fa-map-marker-alt"></i>
                        <?php echo htmlspecialchars($producto['ciudad_nombre']); ?>
                    </p>
                <?php endif; ?>
            </div>
        </a>
        
        <?php if ($showFavorite && estaLogueado()): ?>
            <button class="btn-favorite" data-product-id="<?php echo $id; ?>">
                <i class="far fa-heart"></i>
            </button>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Obtener slug de categoría
 */
function getCategorySlug($nombre) {
    $slugMap = [
        'Vehículos' => 'vehiculos',
        'Vehiculos' => 'vehiculos',
        'Dispositivos' => 'dispositivos',
        'Electrodomésticos' => 'electrodomesticos',
        'Electrodomesticos' => 'electrodomesticos',
        'Prendas' => 'prendas',
        'Muebles' => 'muebles',
        'Inmuebles' => 'inmuebles',
        'Juguetes' => 'juguetes',
        'Herramientas' => 'herramientas',
    ];
    
    return $slugMap[$nombre] ?? strtolower(preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $nombre)));
}

/**
 * Obtener icono de categoría
 */
function getCategoryIcon($nombre) {
    $icons = [
        'Vehículos' => '🚗',
        'Vehiculos' => '🚗',
        'Dispositivos' => '📱',
        'Electrodomésticos' => '🏠',
        'Electrodomesticos' => '🏠',
        'Prendas' => '👕',
        'Muebles' => '🪑',
        'Inmuebles' => '🏘️',
        'Juguetes' => '🧸',
        'Herramientas' => '🛠️',
    ];
    
    return $icons[$nombre] ?? '📦';
}
?>
