<?php
require_once '../includes/header.php';

// Obtener ID del producto
$producto_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$producto_id) {
    redireccionar('../index.php');
}

try {
    $db = getDB();
    
    // Obtener información del producto
    $stmt = $db->prepare("
        SELECT p.*, u.nombre as vendedor_nombre, u.telefono, u.email as vendedor_email,
               u.fecha_registro as vendedor_desde, u.calificacion_promedio, u.total_ventas,
               cat.nombre as categoria_nombre, cat.icono as categoria_icono,
               sub.nombre as subcategoria_nombre
        FROM productos p
        JOIN usuarios u ON p.usuario_id = u.id
        JOIN categorias cat ON p.categoria_id = cat.id
        JOIN subcategorias sub ON p.subcategoria_id = sub.id
        WHERE p.id = ? AND p.activo = 1
    ");
    $stmt->execute([$producto_id]);
    $producto = $stmt->fetch();
    
    if (!$producto) {
        redireccionar('../index.php?error=producto_no_encontrado');
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
    
    $titulo = $producto['titulo'];
    
} catch (Exception $e) {
    error_log("Error al obtener producto: " . $e->getMessage());
    redireccionar('../index.php?error=error_sistema');
}
?>

<!-- Modal Fullscreen estilo Supercell -->
<div class="product-hero-modal">
    <div class="hero-container">
        
        <!-- Header Hero -->
        <div class="hero-header">
            <button class="btn-close-hero" onclick="window.history.back()">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Título ENORME centrado -->
        <div class="hero-title-section">
            <h1 class="hero-title"><?php echo strtoupper(htmlspecialchars($producto['titulo'])); ?></h1>
            <div class="hero-subtitle">
                <i class="fas fa-map-marker-alt"></i>
                <?php echo htmlspecialchars($producto['municipio_nombre']) . ', ' . htmlspecialchars($producto['departamento_nombre']); ?>
                <span class="hero-time">• <?php echo tiempoTranscurrido($producto['fecha_publicacion']); ?></span>
            </div>
        </div>

        <!-- IMAGEN HERO ENORME (60% pantalla) -->
        <div class="hero-image-container">
            <?php if (!empty($imagenes)): ?>
                <div class="hero-main-image">
                    <img src="../uploads/<?php echo $imagenes[0]['nombre_archivo']; ?>" 
                         alt="<?php echo htmlspecialchars($producto['titulo']); ?>"
                         id="heroMainImage"
                         class="hero-img">
                    
                    <!-- Navegación de imágenes -->
                    <?php if (count($imagenes) > 1): ?>
                    <button class="hero-nav-btn hero-prev" onclick="heroPreviousImage()">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="hero-nav-btn hero-next" onclick="heroNextImage()">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                    
                    <!-- Contador -->
                    <div class="hero-image-counter">
                        <span id="heroImageCounter">1</span> / <?php echo count($imagenes); ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Badge de condición -->
                    <?php if ($producto['estado']): ?>
                    <div class="hero-condition-badge">
                        <span class="condition-tag <?php echo $producto['estado']; ?>">
                            <?php echo ucfirst($producto['estado']); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Thumbnails horizontales -->
                <?php if (count($imagenes) > 1): ?>
                <div class="hero-thumbnails">
                    <?php foreach ($imagenes as $index => $imagen): ?>
                    <div class="hero-thumb <?php echo $index === 0 ? 'active' : ''; ?>"
                         onclick="heroChangeImage('<?php echo $imagen['nombre_archivo']; ?>', <?php echo $index; ?>)">
                        <img src="../uploads/<?php echo $imagen['nombre_archivo']; ?>" 
                             alt="Miniatura <?php echo $index + 1; ?>">
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="hero-no-image">
                    <i class="fas fa-image"></i>
                    <p>Sin imagen disponible</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- CTA HERO con Precio integrado -->
        <div class="hero-cta-section">
            <div class="hero-price-cta">
                <div class="hero-price-info">
                    <span class="hero-price-label">Precio</span>
                    <span class="hero-price-value"><?php echo formatearPrecio($producto['precio']); ?></span>
                </div>
                <a href="<?php echo generarEnlaceWhatsApp($producto['telefono'], WHATSAPP_MESSAGE . $producto['titulo'] . ' - ' . SITE_URL . '/products/view_product.php?id=' . $producto_id); ?>" 
                   target="_blank" 
                   class="btn-hero-whatsapp">
                    <i class="fab fa-whatsapp"></i>
                    Contactar por WhatsApp
                </a>
            </div>
            
            <!-- Botones secundarios (share/fav) -->
            <div class="hero-secondary-actions">
                <button class="btn-hero-action" onclick="compartirProducto()" title="Compartir">
                    <i class="fas fa-share-alt"></i>
                </button>
                
                <?php if (estaLogueado()): ?>
                <button class="btn-hero-action btn-favorite" 
                        data-product-id="<?php echo $producto_id; ?>"
                        title="Guardar en favoritos">
                    <i class="far fa-heart"></i>
                </button>
                <?php else: ?>
                <button class="btn-hero-action" 
                        onclick="alert('Inicia sesión para guardar en favoritos')"
                        title="Guardar en favoritos">
                    <i class="far fa-heart"></i>
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Información Colapsable -->
        <div class="hero-collapsible-section">
            
            <!-- Descripción -->
            <details class="hero-details" open>
                <summary class="hero-summary">
                    <i class="fas fa-align-left"></i>
                    <span>Descripción del producto</span>
                    <i class="fas fa-chevron-down arrow-icon"></i>
                </summary>
                <div class="hero-details-content">
                    <?php 
                    $descripcion = htmlspecialchars($producto['descripcion']);
                    $descripcion = nl2br($descripcion);
                    $descripcion = preg_replace('/^[•\-]\s*(.+)$/m', '<li>$1</li>', $descripcion);
                    if (strpos($descripcion, '<li>') !== false) {
                        $descripcion = '<ul class="hero-description-list">' . $descripcion . '</ul>';
                    }
                    echo $descripcion;
                    ?>
                </div>
            </details>
            
            <!-- Vendedor -->
            <details class="hero-details">
                <summary class="hero-summary">
                    <i class="fas fa-user"></i>
                    <span>Información del vendedor</span>
                    <i class="fas fa-chevron-down arrow-icon"></i>
                </summary>
                <div class="hero-details-content">
                    <div class="hero-seller-card">
                        <div class="hero-seller-header">
                            <div class="hero-seller-avatar">
                                <i class="fas fa-user-circle"></i>
                            </div>
                            <div class="hero-seller-info">
                                <h4><?php echo htmlspecialchars($producto['vendedor_nombre']); ?></h4>
                                <div class="hero-seller-rating">
                                    <?php 
                                    $rating = $producto['calificacion_promedio'];
                                    for ($i = 1; $i <= 5; $i++): 
                                        if ($i <= $rating): ?>
                                            <i class="fas fa-star"></i>
                                        <?php else: ?>
                                            <i class="far fa-star"></i>
                                        <?php endif;
                                    endfor; 
                                    ?>
                                    <span>(<?php echo number_format($rating, 1); ?>)</span>
                                </div>
                            </div>
                        </div>
                        <div class="hero-seller-stats">
                            <div class="hero-stat">
                                <span class="stat-value"><?php echo $producto['total_ventas']; ?></span>
                                <span class="stat-label">ventas</span>
                            </div>
                            <div class="hero-stat">
                                <span class="stat-value"><?php echo date('Y', strtotime($producto['vendedor_desde'])); ?></span>
                                <span class="stat-label">miembro desde</span>
                            </div>
                        </div>
                    </div>
                </div>
            </details>
            
            <!-- Consejos de Seguridad -->
            <details class="hero-details">
                <summary class="hero-summary">
                    <i class="fas fa-shield-alt"></i>
                    <span>Consejos de seguridad</span>
                    <i class="fas fa-chevron-down arrow-icon"></i>
                </summary>
                <div class="hero-details-content">
                    <ul class="hero-safety-list">
                        <li><i class="fas fa-check-circle"></i> Reúnete en lugares públicos y seguros</li>
                        <li><i class="fas fa-check-circle"></i> Inspecciona el producto antes de pagar</li>
                        <li><i class="fas fa-check-circle"></i> No hagas pagos por adelantado</li>
                        <li><i class="fas fa-check-circle"></i> Confía en tu instinto</li>
                    </ul>
                </div>
            </details>
            
        </div>

    </div>
</div>

<style>
/* ========================================
   DISEÑO HERO ESTILO SUPERCELL STORE
   ======================================== */

:root {
    --hero-bg-start: #1a1a2e;
    --hero-bg-mid: #16213e;
    --hero-bg-end: #0f3460;
    --hero-accent: #FF6B35;
    --hero-accent-light: #FF8555;
    --hero-text: #ffffff;
    --hero-text-dim: #b0b0b0;
    --hero-card-bg: rgba(255, 255, 255, 0.05);
    --hero-card-border: rgba(255, 255, 255, 0.1);
}

/* Reset del main wrap */
.yx-wrap {
    padding: 0 !important;
    margin: 0 !important;
}

/* Modal Fullscreen */
.product-hero-modal {
    min-height: 100vh;
    background: linear-gradient(135deg, var(--hero-bg-start) 0%, var(--hero-bg-mid) 50%, var(--hero-bg-end) 100%);
    padding: 2rem 1rem;
    position: relative;
}

.hero-container {
    max-width: 900px;
    margin: 0 auto;
    animation: heroFadeIn 0.6s ease-out;
}

@keyframes heroFadeIn {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Header Hero */
.hero-header {
    text-align: right;
    margin-bottom: 1rem;
}

.btn-close-hero {
    background: var(--hero-card-bg);
    border: 2px solid var(--hero-card-border);
    color: var(--hero-text);
    width: 44px;
    height: 44px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    cursor: pointer;
    transition: all 0.3s;
    backdrop-filter: blur(10px);
}

.btn-close-hero:hover {
    background: rgba(255, 255, 255, 0.15);
    transform: rotate(90deg);
}

/* Título HERO */
.hero-title-section {
    text-align: center;
    margin-bottom: 2rem;
}

.hero-title {
    font-size: 2.5rem;
    font-weight: 900;
    color: var(--hero-text);
    margin: 0 0 0.5rem 0;
    text-shadow: 0 2px 12px rgba(0, 0, 0, 0.5);
    letter-spacing: 1px;
}

.hero-subtitle {
    color: var(--hero-text-dim);
    font-size: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.hero-subtitle i {
    color: var(--hero-accent);
}

.hero-time {
    opacity: 0.7;
}

/* IMAGEN HERO ENORME */
.hero-image-container {
    margin-bottom: 2rem;
}

.hero-main-image {
    position: relative;
    border-radius: 24px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
    background: var(--hero-card-bg);
    backdrop-filter: blur(20px);
}

.hero-img {
    width: 100%;
    height: 600px;
    object-fit: cover;
    display: block;
}

/* Navegación de imágenes */
.hero-nav-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(10px);
    border: 2px solid rgba(255, 255, 255, 0.2);
    color: white;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s;
    font-size: 1.2rem;
}

.hero-nav-btn:hover {
    background: var(--hero-accent);
    border-color: var(--hero-accent);
    transform: translateY(-50%) scale(1.1);
}

.hero-prev {
    left: 1rem;
}

.hero-next {
    right: 1rem;
}

/* Contador de imágenes */
.hero-image-counter {
    position: absolute;
    bottom: 1rem;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(10px);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
}

/* Badge de condición */
.hero-condition-badge {
    position: absolute;
    top: 1rem;
    left: 1rem;
}

.condition-tag {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 700;
    font-size: 0.85rem;
    text-transform: uppercase;
    backdrop-filter: blur(10px);
}

.condition-tag.nuevo {
    background: rgba(46, 125, 50, 0.9);
    color: white;
}

.condition-tag.usado {
    background: rgba(230, 81, 0, 0.9);
    color: white;
}

/* Thumbnails horizontales */
.hero-thumbnails {
    display: flex;
    gap: 0.75rem;
    margin-top: 1rem;
    overflow-x: auto;
    padding: 0.5rem 0;
    scrollbar-width: thin;
    scrollbar-color: var(--hero-accent) transparent;
}

.hero-thumbnails::-webkit-scrollbar {
    height: 6px;
}

.hero-thumbnails::-webkit-scrollbar-track {
    background: var(--hero-card-bg);
    border-radius: 10px;
}

.hero-thumbnails::-webkit-scrollbar-thumb {
    background: var(--hero-accent);
    border-radius: 10px;
}

.hero-thumb {
    flex-shrink: 0;
    width: 80px;
    height: 80px;
    border-radius: 12px;
    overflow: hidden;
    border: 3px solid transparent;
    cursor: pointer;
    transition: all 0.3s;
    opacity: 0.6;
}

.hero-thumb:hover {
    opacity: 1;
    transform: scale(1.05);
}

.hero-thumb.active {
    border-color: var(--hero-accent);
    opacity: 1;
}

.hero-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* CTA HERO con Precio */
.hero-cta-section {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
    align-items: stretch;
}

.hero-price-cta {
    flex: 1;
    background: linear-gradient(135deg, var(--hero-accent) 0%, var(--hero-accent-light) 100%);
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 12px 30px rgba(255, 107, 53, 0.4);
    transition: all 0.3s;
}

.hero-price-cta:hover {
    transform: translateY(-4px);
    box-shadow: 0 16px 40px rgba(255, 107, 53, 0.5);
}

.hero-price-info {
    margin-bottom: 1rem;
}

.hero-price-label {
    display: block;
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 0.25rem;
}

.hero-price-value {
    display: block;
    color: white;
    font-size: 2.5rem;
    font-weight: 900;
    line-height: 1;
}

.btn-hero-whatsapp {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    width: 100%;
    background: rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(10px);
    color: white;
    padding: 1rem;
    border: none;
    border-radius: 12px;
    font-size: 1.1rem;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.3s;
}

.btn-hero-whatsapp:hover {
    background: rgba(0, 0, 0, 0.5);
    color: white;
    transform: scale(1.02);
}

.btn-hero-whatsapp i {
    font-size: 1.5rem;
}

/* Botones secundarios */
.hero-secondary-actions {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.btn-hero-action {
    width: 56px;
    height: 56px;
    background: var(--hero-card-bg);
    backdrop-filter: blur(10px);
    border: 2px solid var(--hero-card-border);
    color: var(--hero-text);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-hero-action:hover {
    background: var(--hero-accent);
    border-color: var(--hero-accent);
    transform: scale(1.1);
}

/* Sección Colapsable */
.hero-collapsible-section {
    background: var(--hero-card-bg);
    backdrop-filter: blur(20px);
    border: 1px solid var(--hero-card-border);
    border-radius: 20px;
    overflow: hidden;
}

.hero-details {
    border-bottom: 1px solid var(--hero-card-border);
}

.hero-details:last-child {
    border-bottom: none;
}

.hero-summary {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem;
    cursor: pointer;
    user-select: none;
    color: var(--hero-text);
    font-weight: 600;
    font-size: 1.1rem;
    transition: all 0.3s;
    list-style: none;
}

.hero-summary::-webkit-details-marker {
    display: none;
}

.hero-summary:hover {
    background: rgba(255, 255, 255, 0.03);
}

.hero-summary i:first-child {
    color: var(--hero-accent);
    font-size: 1.3rem;
}

.hero-summary span {
    flex: 1;
}

.arrow-icon {
    transition: transform 0.3s;
}

.hero-details[open] .arrow-icon {
    transform: rotate(180deg);
}

.hero-details-content {
    padding: 0 1.5rem 1.5rem 1.5rem;
    color: var(--hero-text-dim);
    line-height: 1.6;
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Lista de descripción */
.hero-description-list {
    list-style: none;
    padding: 0;
}

.hero-description-list li {
    padding-left: 1.5rem;
    position: relative;
    margin-bottom: 0.5rem;
}

.hero-description-list li::before {
    content: "•";
    position: absolute;
    left: 0;
    color: var(--hero-accent);
    font-weight: 700;
    font-size: 1.2rem;
}

/* Card del vendedor */
.hero-seller-card {
    background: rgba(255, 255, 255, 0.03);
    border-radius: 12px;
    padding: 1rem;
}

.hero-seller-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.hero-seller-avatar i {
    font-size: 3rem;
    color: var(--hero-accent);
}

.hero-seller-info h4 {
    color: var(--hero-text);
    margin: 0 0 0.25rem 0;
    font-size: 1.1rem;
}

.hero-seller-rating {
    color: #FFB800;
}

.hero-seller-rating span {
    color: var(--hero-text-dim);
    margin-left: 0.5rem;
}

.hero-seller-stats {
    display: flex;
    gap: 2rem;
    justify-content: center;
    padding-top: 1rem;
    border-top: 1px solid var(--hero-card-border);
}

.hero-stat {
    text-align: center;
}

.hero-stat .stat-value {
    display: block;
    color: var(--hero-accent);
    font-size: 1.5rem;
    font-weight: 700;
}

.hero-stat .stat-label {
    display: block;
    color: var(--hero-text-dim);
    font-size: 0.85rem;
}

/* Lista de seguridad */
.hero-safety-list {
    list-style: none;
    padding: 0;
}

.hero-safety-list li {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}

.hero-safety-list i {
    color: #4caf50;
    font-size: 1.1rem;
}

/* Sin imagen */
.hero-no-image {
    height: 600px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: var(--hero-card-bg);
    border-radius: 24px;
}

.hero-no-image i {
    font-size: 4rem;
    color: var(--hero-text-dim);
    margin-bottom: 1rem;
}

.hero-no-image p {
    color: var(--hero-text-dim);
}

/* Responsive */
@media (max-width: 768px) {
    .hero-title {
        font-size: 1.8rem;
    }
    
    .hero-img {
        height: 400px;
    }
    
    .hero-price-value {
        font-size: 2rem;
    }
    
    .hero-cta-section {
        flex-direction: column;
    }
    
    .hero-secondary-actions {
        flex-direction: row;
        justify-content: center;
    }
    
    .hero-thumbnails {
        justify-content: center;
    }
}
</style>

<script>
// Galería Hero
let heroCurrentIndex = 0;
const heroImages = <?php echo json_encode(array_column($imagenes, 'nombre_archivo')); ?>;

function heroChangeImage(imageName, index) {
    document.getElementById('heroMainImage').src = '../uploads/' + imageName;
    document.getElementById('heroImageCounter').textContent = index + 1;
    heroCurrentIndex = index;
    
    // Actualizar thumbnails activos
    document.querySelectorAll('.hero-thumb').forEach((thumb, i) => {
        thumb.classList.toggle('active', i === index);
    });
}

function heroNextImage() {
    heroCurrentIndex = (heroCurrentIndex + 1) % heroImages.length;
    heroChangeImage(heroImages[heroCurrentIndex], heroCurrentIndex);
}

function heroPreviousImage() {
    heroCurrentIndex = (heroCurrentIndex - 1 + heroImages.length) % heroImages.length;
    heroChangeImage(heroImages[heroCurrentIndex], heroCurrentIndex);
}

// Compartir producto
function compartirProducto() {
    if (navigator.share) {
        navigator.share({
            title: '<?php echo addslashes($producto["titulo"]); ?>',
            text: 'Mira este producto en CAMBALACHE',
            url: window.location.href
        });
    } else {
        alert('Función de compartir no disponible');
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
