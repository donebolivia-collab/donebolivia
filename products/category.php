<?php
/**
 * DONE! - Vista de Categoría
 */
require_once '../includes/header.php';

// Obtener ID de categoría
$categoria_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$categoria_id) {
    redireccionar('../index.php');
}

try {
    $db = getDB();
    
    // Obtener información de la categoría
    $stmt = $db->prepare("SELECT * FROM categorias WHERE id = ? AND activo = 1");
    $stmt->execute([$categoria_id]);
    $categoria = $stmt->fetch();
    
    if (!$categoria) {
        redireccionar('../index.php?error=categoria_no_encontrada');
    }
    
    // Obtener subcategorías
    $subcategorias = obtenerSubcategorias($categoria_id);
    
    // Parámetros de filtrado
    $termino_busqueda = isset($_GET['q']) ? limpiarEntrada($_GET['q']) : '';
    $departamento = isset($_GET['departamento']) ? limpiarEntrada($_GET['departamento']) : '';
    $subcategoria_id = isset($_GET['subcategoria']) ? (int)$_GET['subcategoria'] : null;
    $ciudad_id = isset($_GET['ciudad']) ? (int)$_GET['ciudad'] : null;
    $precio_min = isset($_GET['precio_min']) ? (float)$_GET['precio_min'] : null;
    $precio_max = isset($_GET['precio_max']) ? (float)$_GET['precio_max'] : null;
    $estado = isset($_GET['estado']) ? limpiarEntrada($_GET['estado']) : '';
    $orden = isset($_GET['orden']) ? limpiarEntrada($_GET['orden']) : 'reciente';
    $pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;

    // Buscar productos en la categoría
    $resultados = buscarProductos($termino_busqueda, $categoria_id, $ciudad_id, $precio_min, $precio_max, $pagina, 12, $subcategoria_id, $departamento, $orden);
    $productos = $resultados['productos'];
    $total_productos = $resultados['total'];
    $total_paginas = $resultados['paginas'];
    $pagina_actual = $resultados['pagina_actual'];

    $ciudades = obtenerCiudades();
    $titulo = $categoria['nombre'];
    
} catch (Exception $e) {
    error_log("Error en categoría: " . $e->getMessage());
    redireccionar('../index.php?error=error_sistema');
}
?>

<div class="category-container">
    <!-- Barra Unificada: Buscador + Filtros -->
    <div class="unified-search-wrapper">
        <form method="GET" action="" id="unifiedCategoryForm" class="unified-search-form">
            <input type="hidden" name="id" value="<?php echo $categoria_id; ?>">
            <div class="unified-search-bar">
                <!-- Buscador -->
                <div class="search-input-wrapper">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text"
                           name="q"
                           class="search-input-unified"
                           placeholder="Buscar en <?php echo htmlspecialchars($categoria['nombre']); ?>"
                           value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>">
                </div>

                <!-- Separador -->
                <div class="filter-divider"></div>

                <!-- Categoría -->
                <select name="categoria_change" class="filter-select" id="categoriaChangeSelect">
                    <option value="">Cambiar categoría</option>
                    <?php
                    $all_categorias = obtenerCategorias();
                    foreach ($all_categorias as $cat):
                    ?>
                    <option value="<?php echo $cat['id']; ?>" <?php echo $categoria_id == $cat['id'] ? 'selected' : ''; ?>>
                        <?php echo $cat['nombre']; ?>
                    </option>
                    <?php endforeach; ?>
                </select>

                <!-- Separador -->
                <div class="filter-divider"></div>

                <!-- Departamento -->
                <select name="departamento" class="filter-select" id="departamentoCategorySelect">
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
                <select name="orden" class="filter-select" id="ordenCategorySelect">
                    <option value="reciente" <?php echo (!$orden || $orden == 'reciente') ? 'selected' : ''; ?>>Más recientes</option>
                    <option value="precio_bajo" <?php echo $orden == 'precio_bajo' ? 'selected' : ''; ?>>Menor a mayor precio</option>
                    <option value="precio_alto" <?php echo $orden == 'precio_alto' ? 'selected' : ''; ?>>Mayor a menor precio</option>
                </select>
            </div>
        </form>
    </div>

    <!-- Grid de productos -->
    <main class="category-content">
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
                    <?php if ($pagina_actual > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $pagina_actual - 1])); ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php
                    $inicio = max(1, $pagina_actual - 2);
                    $fin = min($total_paginas, $pagina_actual + 2);
                    
                    for ($i = $inicio; $i <= $fin; $i++): ?>
                    <li class="page-item <?php echo $i == $pagina_actual ? 'active' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $i])); ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                    
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
                <!-- Sin productos -->
                <div class="empty-state">
                    <div class="empty-state-content">
                        <svg class="empty-state-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                        <h3 class="empty-state-title">Aún no hay productos en esta categoría</h3>
                        <p class="empty-state-text">Intenta buscar en otras categorías o ajusta los filtros</p>
                    </div>
                </div>
            <?php endif; ?>
    </main>
</div>

<style>
/* ================================================
   DISEÑO MODERNO - LIMPIO, MINIMALISTA, PROFESIONAL
   ================================================ */

.category-container {
    max-width: 1232px;
    margin: 0 auto;
    padding: 16px;
    background: transparent;
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
    flex: 2 !important; /* Más ancho que los dropdowns */
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
    flex: 0 0 auto !important; /* No crecer, solo ocupar el espacio necesario */
    flex-shrink: 0;
}

.filter-select:hover {
    color: #1f2937;
}

.filter-select:focus {
    color: #000;
}

/* ===== CONTENIDO ===== */
.avito-content {
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

/* ===== EMPTY STATE ===== */
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

/* ===== RESPONSIVE ===== */

/* Desktop grande */
@media (min-width: 1441px) {
    .category-container {
        max-width: 1440px;
    }
}

/* Laptop */
@media (max-width: 1280px) {
    .products-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

/* Tablet pequeño */
@media (max-width: 768px) {
    .category-container {
        padding: 12px;
        background: #fff;
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

    .products-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
}

/* Móvil */
@media (max-width: 480px) {
    .category-container {
        padding: 8px;
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

    .products-grid {
        grid-template-columns: 1fr;
    }

    .product-title {
        font-size: 14px;
    }
}
</style>

<script>
// Barra Unificada - Auto-submit instantáneo
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('unifiedCategoryForm');
    const searchInput = document.querySelector('.search-input-unified');
    const categoriaChangeSelect = document.getElementById('categoriaChangeSelect');
    const departamentoSelect = document.getElementById('departamentoCategorySelect');
    const ordenSelect = document.getElementById('ordenCategorySelect');

    // Auto-submit cuando cambia categoría - redirigir a category.php con nueva categoría
    if (categoriaChangeSelect) {
        categoriaChangeSelect.addEventListener('change', function() {
            const nuevaCategoriaId = this.value;
            if (nuevaCategoriaId) {
                // Redirigir a category.php con la nueva categoría
                window.location.href = 'category.php?id=' + nuevaCategoriaId;
            }
        });
    }

    // Auto-submit cuando cambia departamento
    if (departamentoSelect) {
        departamentoSelect.addEventListener('change', function() {
            form.submit();
        });
    }

    // Auto-submit cuando cambia orden
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
});
</script>

 

<?php require_once '../includes/footer.php'; ?>
