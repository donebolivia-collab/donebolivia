<?php
$titulo = "Buscar Productos";
require_once '../includes/header.php';

// Parámetros de búsqueda
$termino = isset($_GET['q']) ? limpiarEntrada($_GET['q']) : '';
$categoria_id = isset($_GET['categoria']) ? (int)$_GET['categoria'] : null;
$subcategoria_id = isset($_GET['subcategoria']) ? (int)$_GET['subcategoria'] : null;
$departamento = isset($_GET['departamento']) ? limpiarEntrada($_GET['departamento']) : '';
$ciudad_id = isset($_GET['ciudad']) ? (int)$_GET['ciudad'] : null;
$precio_min = isset($_GET['precio_min']) ? (float)$_GET['precio_min'] : null;
$precio_max = isset($_GET['precio_max']) ? (float)$_GET['precio_max'] : null;
$estado = isset($_GET['estado']) ? limpiarEntrada($_GET['estado']) : '';
$orden = isset($_GET['orden']) ? limpiarEntrada($_GET['orden']) : 'reciente';
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;

// Realizar búsqueda
$resultados = buscarProductos($termino, $categoria_id, $ciudad_id, $precio_min, $precio_max, $pagina, 12, $subcategoria_id, $departamento, $orden);
$productos = $resultados['productos'];
$total_productos = $resultados['total'];
$total_paginas = $resultados['paginas'];
$pagina_actual = $resultados['pagina_actual'];

// Obtener datos para filtros
$categorias = obtenerCategorias();
$ciudades = obtenerCiudades();

// Título dinámico
if ($termino) {
    $titulo = "Resultados para: " . htmlspecialchars($termino);
} elseif ($categoria_id) {
    $categoria_nombre = '';
    foreach ($categorias as $cat) {
        if ($cat['id'] == $categoria_id) {
            $categoria_nombre = $cat['nombre'];
            break;
        }
    }
    $titulo = $categoria_nombre;
}
?>

<div class="search-container">
    <!-- Barra Unificada -->
    <div class="unified-search-wrapper">
        <form method="GET" action="" id="unifiedSearchForm" class="unified-search-form">
            <div class="unified-search-bar">
                <!-- Buscador -->
                <div class="search-input-wrapper">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text"
                           name="q"
                           class="search-input-unified"
                           placeholder="Buscar en Done!"
                           value="<?php echo htmlspecialchars($termino); ?>">
                </div>

                <!-- Separador -->
                <div class="filter-divider"></div>

                <!-- Categoría -->
                <select name="categoria" class="filter-select" id="categoriaSelect">
                    <option value="">Todas las categorías</option>
                    <?php foreach ($categorias as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>" <?php echo $categoria_id == $cat['id'] ? 'selected' : ''; ?>>
                        <?php echo $cat['nombre']; ?>
                    </option>
                    <?php endforeach; ?>
                </select>

                <!-- Separador -->
                <div class="filter-divider"></div>

                <!-- Departamento -->
                <select name="departamento" class="filter-select" id="departamentoSelect">
                    <option value="">Todos los departamentos</option>
                    <?php
                    require_once '../config/ubicaciones_bolivia.php';
                    $departamentos = obtenerDepartamentosBolivia();
                    $departamento_actual = isset($_GET['departamento']) ? $_GET['departamento'] : '';
                    foreach ($departamentos as $codigo => $nombre):
                    ?>
                    <option value="<?php echo $codigo; ?>" <?php echo $departamento_actual == $codigo ? 'selected' : ''; ?>>
                        <?php echo $nombre; ?>
                    </option>
                    <?php endforeach; ?>
                </select>

                <!-- Separador -->
                <div class="filter-divider"></div>

                <!-- Ordenar por -->
                <select name="orden" class="filter-select" id="ordenSelect">
                    <option value="reciente" <?php echo (!$orden || $orden == 'reciente') ? 'selected' : ''; ?>>Más recientes</option>
                    <option value="precio_bajo" <?php echo $orden == 'precio_bajo' ? 'selected' : ''; ?>>Menor a mayor precio</option>
                    <option value="precio_alto" <?php echo $orden == 'precio_alto' ? 'selected' : ''; ?>>Mayor a menor precio</option>
                </select>
            </div>
        </form>
    </div>

    <!-- Grid de productos -->
    <main class="search-content">
            <?php if (!empty($productos)): ?>
                <div class="products-grid" id="productsContainer">
                    <?php foreach ($productos as $producto): ?>
                    <div class="product-card" data-product-id="<?php echo $producto['id']; ?>">
                        <div class="product-image">
                            <?php if ($producto['imagen_principal']): ?>
                                <img src="/uploads/<?php echo $producto['imagen_principal']; ?>"
                                     alt="<?php echo htmlspecialchars($producto['titulo']); ?>"
                                     loading="lazy">
                            <?php else: ?>
                                <img src="/assets/images/no-image.jpg"
                                     alt="Sin imagen"
                                     loading="lazy">
                            <?php endif; ?>

                            <?php if ($producto['destacado']): ?>
                                <span class="product-badge">Destacado</span>
                            <?php endif; ?>
                        </div>

                        <div class="product-info">
                            <h3 class="product-title">
                                <a href="/products/view_product.php?id=<?php echo $producto['id']; ?>" title="<?php echo htmlspecialchars($producto['titulo']); ?>">
                                    <?php echo htmlspecialchars($producto['titulo']); ?>
                                </a>
                            </h3>
                            <div class="product-price"><?php echo formatearPrecio($producto['precio']); ?></div>

                            <!-- Badges de Envío -->
                            <?php if (!empty($producto['envio_gratis'])): ?>
                            <div class="product-badges" style="margin-bottom: 4px; display: flex; gap: 4px; flex-wrap: wrap;">
                                                            </div>
                            <?php endif; ?>

                            <div class="product-location">
                                <i class="fas fa-map-marker-alt"></i>
                                <?php
                                // Mostrar ubicación específica
                                if (!empty($producto['municipio_nombre']) && !empty($producto['departamento_nombre'])) {
                                    echo htmlspecialchars($producto['municipio_nombre']) . ', ' . htmlspecialchars($producto['departamento_nombre']);
                                } elseif (!empty($producto['departamento_nombre'])) {
                                    echo htmlspecialchars($producto['departamento_nombre']);
                                } elseif (!empty($producto['ciudad_nombre'])) {
                                    echo htmlspecialchars($producto['ciudad_nombre']);
                                } else {
                                    echo 'Bolivia';
                                }
                                ?>
                            </div>

                            <div class="product-meta">
                                <span class="product-date">
                                    <i class="fas fa-clock"></i>
                                    Hace <?php echo tiempoTranscurrido($producto['fecha_publicacion']); ?>
                                </span>
                                <span class="product-views">
                                    <i class="fas fa-eye"></i>
                                    <?php echo number_format($producto['vistas']); ?> vistas
                                </span>
                            </div>

                            <div class="product-seller">
                                <i class="fas fa-user"></i>
                                <span class="seller-name">
                                    <?php echo mb_convert_case(htmlspecialchars($producto['vendedor_nombre']), MB_CASE_TITLE, 'UTF-8'); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Paginación -->
                <?php if ($total_paginas > 1): ?>
                <nav aria-label="Navegación de productos" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <!-- Página anterior -->
                        <?php if ($pagina_actual > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina_actual - 1])); ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <!-- Páginas -->
                        <?php
                        $inicio = max(1, $pagina_actual - 2);
                        $fin = min($total_paginas, $pagina_actual + 2);
                        
                        if ($inicio > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => 1])); ?>">1</a>
                        </li>
                        <?php if ($inicio > 2): ?>
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                        <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $inicio; $i <= $fin; $i++): ?>
                        <li class="page-item <?php echo $i == $pagina_actual ? 'active' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $i])); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                        
                        <?php if ($fin < $total_paginas): ?>
                        <?php if ($fin < $total_paginas - 1): ?>
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $total_paginas])); ?>">
                                <?php echo $total_paginas; ?>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <!-- Página siguiente -->
                        <?php if ($pagina_actual < $total_paginas): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina_actual + 1])); ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>

            <?php else: ?>
                <!-- Sin resultados -->
                <div class="empty-state">
                    <div class="empty-state-content">
                        <svg class="empty-state-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/>
                            <path d="m21 21-4.35-4.35"/>
                        </svg>
                        <h3 class="empty-state-title">No se encontraron productos</h3>
                        <p class="empty-state-text">
                            <?php if ($termino): ?>
                                Intenta con otros términos de búsqueda o ajusta los filtros
                            <?php else: ?>
                                Prueba seleccionando diferentes categorías o departamentos
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>
    </main>
</div>

<style>
/* ================================================
   DISEÑO MODERNO - LIMPIO, MINIMALISTA, PROFESIONAL
   ================================================ */

.search-container {
    max-width: 1232px;
    margin: 0 auto;
    padding: 16px;
    background: transparent;
}

/* ===== HEADER ===== */
.search-header {
    background: #fff;
    padding: 12px 24px;
    margin-bottom: 16px;
    border-radius: 4px;
}

.search-title {
    font-size: 20px;
    font-weight: 500;
    color: #001a34;
    margin: 0 0 16px 0;
    line-height: 1.3;
}

/* ===== BARRA UNIFICADA ===== */
.unified-search-wrapper {
    background: #f5f5f5;
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 16px;
}

.unified-search-form {
    margin: 0;
}

.unified-search-bar {
    display: flex;
    align-items: center;
    gap: 0;
    background: white;
    padding: 10px 16px;
    border-radius: 8px;
    box-shadow: 0 0 0 0 transparent, 0 0 0 1px #dee2e6, 0 0 8px 2px rgba(0,0,0,0.08);
    border: none;
    transition: all 0.3s ease;
}

.unified-search-bar:hover {
    box-shadow: 0 4px 20px rgba(0,0,0,.15);
    transform: translateY(-2px);
}

.unified-search-bar:focus-within {
    box-shadow: 0 4px 20px rgba(0,0,0,.15);
    border-color: #ff6b1a;
}

/* Buscador */
.search-input-wrapper {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0 4px;
    min-width: 200px;
}

.search-icon {
    color: #9ca3af;
    font-size: 18px;
    flex-shrink: 0;
}

.search-input-unified {
    flex: 1;
    border: none;
    background: transparent;
    padding: 6px 0;
    font-size: 14px;
    color: #1f2937;
    outline: none;
}

.search-input-unified::placeholder {
    color: #9ca3af;
}

.search-input-unified:focus {
    color: #000;
}

/* Separadores */
.filter-divider {
    width: 1px;
    height: 24px;
    background: #e5e7eb;
    margin: 0 12px;
    flex-shrink: 0;
}

/* Selectores */
.filter-select {
    background: transparent;
    border: none;
    padding: 6px 24px 6px 8px;
    font-size: 14px;
    font-weight: 500;
    color: #374151;
    cursor: pointer;
    transition: all 0.2s ease;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 10 10'%3E%3Cpath fill='%23374151' d='M5 7L1 3h8z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 6px center;
    outline: none;
    min-width: 140px;
    flex-shrink: 0;
}

.filter-select:hover {
    color: #1f2937;
}

.filter-select:focus {
    color: #000;
}

/* ===== CONTENIDO ===== */
.search-content {
    background: transparent;
}

/* ===== GRID DE PRODUCTOS ===== */
.products-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
}

.product-card {
    background: #fff;
    border-radius: 4px;
    overflow: hidden;
    transition: box-shadow 0.2s;
    cursor: pointer;
}

.product-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.product-image-link {
    display: block;
    text-decoration: none;
}

.product-image {
    position: relative;
    width: 100%;
    padding-top: 75%;
    background: #f7f7f7;
    overflow: hidden;
}

.product-image img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.product-info {
    padding: 10px;
}

.product-title {
    font-size: 0.95rem;
    font-weight: 600;
    margin-top: 0 !important;
    margin-bottom: 2px !important;
    line-height: 1.3;
    height: 1.3em !important;
    overflow: hidden;
    padding-bottom: 0;
}

.product-title a {
    color: #000;
    text-decoration: none;
    display: block !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    word-break: normal !important;
    line-height: 1.3;
}

.product-title a:hover {
    color: #ff6b1a;
}

@media (min-width: 768px) {
    .product-title {
        font-size: 1rem;
    }
}

.product-price {
    font-size: 1.15rem;
    font-weight: 700;
    color: #ff6b1a;
    margin-bottom: 0.3rem;
}

@media (min-width: 768px) {
    .product-price {
        font-size: 1.2rem;
    }
}

.product-location {
    display: flex;
    align-items: center;
    font-size: 0.85rem;
    color: #000;
    font-weight: 400;
    margin-bottom: 0.3rem;
}

.product-location i {
    color: #666;
    font-size: 0.9rem;
    width: 16px;
    text-align: left;
    margin-right: 6px;
    flex-shrink: 0;
}

/* META - ESTANDARIZADA CON ANCHO FIJO */
.product-meta {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin-bottom: 0.25rem;
    font-size: 0.85rem;
    color: #000;
    font-weight: 400;
}

.product-date,
.product-views {
    display: flex;
    align-items: center;
}

.product-date i,
.product-views i {
    font-size: 0.9rem;
    color: #666;
    width: 16px;
    text-align: left;
    margin-right: 6px;
    flex-shrink: 0;
}

/* VENDEDOR - ESTANDARIZADO CON ANCHO FIJO */
.product-seller {
    display: flex;
    align-items: center;
    margin-bottom: 0;
    font-size: 0.85rem;
    color: #000;
    font-weight: 400;
}

.product-seller .seller-name {
    display: inline-block;
}

.product-seller i {
    font-size: 0.9rem;
    color: #666;
    width: 16px;
    text-align: left;
    margin-right: 6px;
    flex-shrink: 0;
}

/* BADGE DESTACADO */
.product-badge {
    position: absolute;
    top: 8px;
    left: 8px;
    background: #ff6b1a;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    z-index: 2;
}

/* Sin resultados */
.no-results {
    background: #fff;
    padding: 60px 24px;
    text-align: center;
    border-radius: 4px;
}

.no-results h3 {
    font-size: 20px;
    color: #001a34;
    margin: 16px 0 8px;
}

.no-results .text-muted {
    color: #9ca8b5;
    font-size: 14px;
}

.suggestions {
    background: #f7f7f7;
    padding: 20px;
    border-radius: 4px;
    margin: 24px auto;
    max-width: 400px;
    text-align: left;
}

.suggestions h5 {
    font-size: 15px;
    font-weight: 500;
    color: #001a34;
    margin-bottom: 12px;
}

.suggestions ul li {
    font-size: 14px;
    color: #9ca8b5;
    margin-bottom: 8px;
}

/* ===== RESPONSIVE ===== */

/* Desktop grande */
@media (min-width: 1441px) {
    .search-container {
        max-width: 1440px;
    }
}

/* Laptop */
@media (max-width: 1280px) {
    .products-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

/* Tablet */
@media (max-width: 1024px) {
    .search-title {
        font-size: 18px;
    }

    .search-filters-bar {
        padding: 12px 16px;
    }

    .search-select {
        min-width: 120px;
    }
}

/* Tablet pequeño */
@media (max-width: 768px) {
    .search-container {
        padding: 12px;
        background: #fff;
    }

    .search-header {
        padding: 10px 16px;
        margin-bottom: 12px;
    }

    .search-title {
        font-size: 16px;
    }

    .unified-search-wrapper {
        padding: 16px;
    }

    .unified-search-bar {
        padding: 10px 16px;
        flex-wrap: wrap;
        gap: 12px;
    }

    .search-input-wrapper {
        min-width: 100%;
        order: 1;
    }

    .filter-divider {
        display: none;
    }

    .filter-select {
        min-width: calc(50% - 6px);
        order: 2;
        padding: 10px 28px 10px 12px;
        font-size: 13px;
    }

    .premium-filters {
        padding: 16px;
        margin-bottom: 16px;
    }

    .filter-row {
        flex-direction: column;
        gap: 10px;
    }

    .filter-item {
        width: 100%;
        min-width: 100%;
    }

    .price-item {
        width: 100%;
        min-width: 100%;
    }

    .price-inputs {
        padding: 6px 12px;
    }

    .filter-btn-clear {
        position: absolute;
        top: 16px;
        right: 16px;
    }

    .products-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
}

/* Móvil */
@media (max-width: 480px) {
    .search-container {
        padding: 8px;
    }

    .search-header {
        padding: 8px 12px;
    }

    .search-title {
        font-size: 14px;
    }

    .unified-search-wrapper {
        padding: 12px;
    }

    .unified-search-bar {
        padding: 8px 12px;
        gap: 10px;
    }

    .search-input-wrapper {
        min-width: 100%;
    }

    .filter-select {
        min-width: 100%;
        width: 100%;
        padding: 10px 28px 10px 12px;
        font-size: 13px;
    }

    .search-filters-bar {
        padding: 8px;
        gap: 8px;
    }

    .search-filter-price {
        width: 100%;
    }

    .search-input {
        width: 60px;
    }

    .products-grid {
        grid-template-columns: 1fr;
    }

    .product-title {
        font-size: 14px;
    }
}

/* ===== ESTADO VACÍO ===== */
.empty-state {
    padding: 80px 20px;
    text-align: center;
}

.empty-state-content {
    max-width: 400px;
    margin: 0 auto;
}

.empty-state-icon {
    width: 80px;
    height: 80px;
    color: #d1d5db;
    margin: 0 auto 24px;
    display: block;
}

.empty-state-title {
    font-size: 20px;
    font-weight: 500;
    color: #374151;
    margin: 0 0 12px 0;
}

.empty-state-text {
    font-size: 15px;
    color: #6b7280;
    margin: 0;
    line-height: 1.5;
}
</style>

<script>
// Barra Unificada - Auto-submit instantáneo
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('unifiedSearchForm');
    const searchInput = document.querySelector('.search-input-unified');
    const categoriaSelect = document.getElementById('categoriaSelect');
    const departamentoSelect = document.getElementById('departamentoSelect');
    const ordenSelect = document.getElementById('ordenSelect');

    // Auto-submit cuando cambia categoría - redirigir a category.php si se selecciona una
    if (categoriaSelect) {
        categoriaSelect.addEventListener('change', function() {
            const categoriaId = this.value;
            if (categoriaId) {
                // Redirigir a category.php con la categoría seleccionada
                window.location.href = 'category.php?id=' + categoriaId;
            } else {
                // Si se selecciona "Todas las categorías", hacer submit normal
                form.submit();
            }
        });
    }

    if (departamentoSelect) {
        departamentoSelect.addEventListener('change', function() {
            form.submit();
        });
    }

    if (ordenSelect) {
        ordenSelect.addEventListener('change', function() {
            form.submit();
        });
    }

    // Submit al presionar Enter en búsqueda
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                form.submit();
            }
        });
    }

    // Smooth scroll para paginación
    const paginationLinks = document.querySelectorAll('.pagination a');
    paginationLinks.forEach(link => {
        link.addEventListener('click', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });
});
</script>

 

<?php require_once '../includes/footer.php'; ?>
