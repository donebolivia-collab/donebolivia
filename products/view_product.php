<?php
/**
 * DONE! - Vista de Producto
 * Diseño de una columna, responsive, orden fijo en todos los dispositivos
 */

$titulo = "Producto";
require_once '../config/database.php';
require_once '../includes/functions.php';

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
               u.foto_perfil as vendedor_foto,
               cat.nombre as categoria_nombre, cat.icono as categoria_icono,
               sub.nombre as subcategoria_nombre
        FROM productos p
        JOIN usuarios u ON p.usuario_id = u.id
        JOIN categorias cat ON p.categoria_id = cat.id
        LEFT JOIN subcategorias sub ON p.subcategoria_id = sub.id
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
    
    // Verificar si está en favoritos
    $esFavorito = false;
    if (estaLogueado()) {
        $stmtFav = $db->prepare("SELECT 1 FROM favoritos WHERE usuario_id = ? AND producto_id = ?");
        $stmtFav->execute([$_SESSION['usuario_id'], $producto_id]);
        $esFavorito = $stmtFav->fetch() ? true : false;
    }
    
    $titulo = $producto['titulo'];
    
} catch (Exception $e) {
    error_log("Error al obtener producto: " . $e->getMessage());
    redireccionar('../index.php?error=error_sistema');
}

// Incluir header del sitio
require_once '../includes/header.php';
?>

<div class="pv-container">
    
    <!-- 1. TÍTULO + UBICACIÓN (PRIMERO) -->
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
                <!-- Imagen principal -->
                <img src="/uploads/<?php echo $imagenes[0]['nombre_archivo']; ?>" 
                     alt="<?php echo htmlspecialchars($producto['titulo']); ?>"
                     id="pvMainImage"
                     class="pv-main-img">
                
                <!-- Navegación -->
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
                
                <!-- Botones flotantes: Compartir y Favoritos -->
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
            
            <!-- Miniaturas -->
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
            <?php if (!empty($producto['envio_gratis'])): ?>
                <span class="badge-delivery-green" style="font-size: 13px; padding: 4px 8px;">
                    <img src="/assets/img/delivery.png" class="truck-icon" alt=""> Envío Gratis
                </span>
            <?php endif; ?>
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
        <a href="<?php echo generarEnlaceWhatsApp($producto['telefono'], WHATSAPP_MESSAGE . $producto['titulo'] . ' - ' . SITE_URL . '/products/view_product.php?id=' . $producto_id); ?>"
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
        <button class="pv-report-btn" data-bs-toggle="modal" data-bs-target="#reportModal">
            <i class="fas fa-flag"></i>
            Reportar este anuncio
        </button>
    </div>
    <?php endif; ?>

</div>

<!-- Modal de Reporte -->
<div class="modal fade" id="reportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reportar anuncio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">Selecciona el motivo del reporte:</p>
                <form id="reportForm">
                    <div class="report-options">
                        <label class="report-option">
                            <input type="radio" name="motivo" value="producto_vendido" required>
                            <span class="report-option-text">
                                <i class="fas fa-check-circle"></i>
                                El producto ya fue vendido
                            </span>
                        </label>
                        <label class="report-option">
                            <input type="radio" name="motivo" value="precio_sospechoso" required>
                            <span class="report-option-text">
                                <i class="fas fa-exclamation-triangle"></i>
                                Precio incorrecto o sospechoso
                            </span>
                        </label>
                        <label class="report-option">
                            <input type="radio" name="motivo" value="descripcion_enganosa" required>
                            <span class="report-option-text">
                                <i class="fas fa-images"></i>
                                Descripción o fotos engañosas
                            </span>
                        </label>
                        <label class="report-option">
                            <input type="radio" name="motivo" value="ubicacion_incorrecta" required>
                            <span class="report-option-text">
                                <i class="fas fa-map-marker-alt"></i>
                                Ubicación incorrecta
                            </span>
                        </label>
                        <label class="report-option">
                            <input type="radio" name="motivo" value="publicacion_duplicada" required>
                            <span class="report-option-text">
                                <i class="fas fa-copy"></i>
                                Publicación duplicada
                            </span>
                        </label>
                        <label class="report-option">
                            <input type="radio" name="motivo" value="vendedor_sospechoso" required>
                            <span class="report-option-text">
                                <i class="fas fa-user-times"></i>
                                Vendedor sospechoso o estafador
                            </span>
                        </label>
                        <label class="report-option">
                            <input type="radio" name="motivo" value="contenido_inapropiado" required>
                            <span class="report-option-text">
                                <i class="fas fa-ban"></i>
                                Contenido inapropiado
                            </span>
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" onclick="submitReport()">Enviar reporte</button>
            </div>
        </div>
    </div>
</div>

<!-- Estilos específicos de la vista de producto -->
<link href="/assets/css/product-view.css?v=1.0" rel="stylesheet">

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

// ===== REPORTAR PRODUCTO =====
function submitReport() {
    const form = document.getElementById('reportForm');
    const motivo = form.querySelector('input[name="motivo"]:checked');

    if (!motivo) {
        alert('Por favor selecciona un motivo de reporte');
        return;
    }

    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';

    fetch('submit_report.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            producto_id: <?php echo $producto_id; ?>,
            motivo: motivo.value
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            const modal = bootstrap.Modal.getInstance(document.getElementById('reportModal'));
            modal.hide();
            form.reset();
        } else {
            alert(data.message || 'Error al enviar el reporte');
        }
    })
    .catch(() => {
        alert('Error de conexión. Intenta nuevamente.');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
