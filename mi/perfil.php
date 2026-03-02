<?php
$titulo = "Mi perfil";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

if (!estaLogueado()) { 
    redireccionar('/auth/login.php?redirect=' . urlencode('/mi/perfil.php')); 
}

$usr = obtenerUsuarioActual();
$db = getDB();

// Obtener productos del usuario
$stmt = $db->prepare("
    SELECT p.*, 
      (SELECT nombre_archivo FROM producto_imagenes 
         WHERE producto_id = p.id 
         ORDER BY es_principal DESC, orden ASC LIMIT 1) AS img
    FROM productos p
    WHERE p.usuario_id = ? AND p.activo = 1 AND p.estado != 'eliminado'
    ORDER BY p.id DESC
    LIMIT 200
");
$stmt->execute([$_SESSION['usuario_id']]);
$productos = $stmt->fetchAll();

// Obtener favoritos del usuario
$stmt_fav = $db->prepare("
    SELECT p.*, 
      (SELECT nombre_archivo FROM producto_imagenes 
         WHERE producto_id = p.id 
         ORDER BY es_principal DESC, orden ASC LIMIT 1) AS img
    FROM productos p
    INNER JOIN favoritos f ON f.producto_id = p.id
    WHERE f.usuario_id = ? AND p.activo = 1 AND p.estado != 'eliminado'
    ORDER BY f.id DESC
    LIMIT 200
");
$stmt_fav->execute([$_SESSION['usuario_id']]);
$favoritos = $stmt_fav->fetchAll();

// Contar estadísticas
$count_productos = count($productos);
$count_favoritos = count($favoritos);
?>

<style>
/* Estilos del perfil estilo YouTube */
.profile-youtube-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem 1rem;
}

/* Header del perfil */
.profile-youtube-header {
    background: #fff;
    border-radius: 12px;
    padding: 2rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.profile-youtube-info {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.profile-youtube-photo {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, #ff6a00 0%, #ff8533 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    color: #fff;
    font-size: 32px;
    flex-shrink: 0;
}

.profile-youtube-details h1 {
    font-size: 1.75rem;
    font-weight: 700;
    margin: 0 0 0.5rem 0;
    color: #222;
}

.profile-youtube-meta {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
    color: #666;
    font-size: 0.95rem;
}

.profile-youtube-meta span {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.profile-youtube-actions {
    margin-top: 1rem;
}

.btn-edit-profile {
    background: #f0f0f0;
    border: none;
    color: #333;
    padding: 0.5rem 1.5rem;
    border-radius: 6px;
    font-weight: 600;
    transition: all 0.2s;
}

.btn-edit-profile:hover {
    background: #e0e0e0;
    color: #000;
}

/* Tabs estilo YouTube */
.profile-youtube-tabs {
    display: flex;
    gap: 2rem;
    border-bottom: 1px solid #e0e0e0;
    margin-bottom: 2rem;
    background: #fff;
    padding: 0 1rem;
    border-radius: 12px 12px 0 0;
}

.profile-tab {
    padding: 1rem 0;
    border: none;
    background: none;
    color: #666;
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    position: relative;
    transition: color 0.2s;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.profile-tab:hover {
    color: #ff6a00;
}

.profile-tab.active {
    color: #ff6a00;
}

.profile-tab.active::after {
    content: '';
    position: absolute;
    bottom: -1px;
    left: 0;
    right: 0;
    height: 3px;
    background: #ff6a00;
    border-radius: 3px 3px 0 0;
}

.profile-tab .count {
    background: #f0f0f0;
    padding: 0.15rem 0.5rem;
    border-radius: 12px;
    font-size: 0.85rem;
    color: #666;
}

.profile-tab.active .count {
    background: #ffe7d6;
    color: #ff6a00;
}

/* Contenido de tabs */
.profile-tab-content {
    display: none;
}

.profile-tab-content.active {
    display: block;
}

/* Grid de productos estilo YouTube */
.products-youtube-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 1.5rem;
    padding: 1rem;
}

.product-youtube-card {
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    text-decoration: none;
    color: inherit;
    display: block;
}

.product-youtube-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.12);
}

.product-youtube-image {
    width: 100%;
    height: 200px;
    object-fit: cover;
    background: #f5f5f5;
}

.product-youtube-info {
    padding: 1rem;
}

.product-youtube-title {
    font-weight: 600;
    font-size: 1rem;
    margin: 0 0 0.5rem 0;
    color: #222;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.product-youtube-price {
    font-size: 1.25rem;
    font-weight: 700;
    color: #ff6a00;
    margin: 0;
}

.product-youtube-meta {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-top: 0.5rem;
    font-size: 0.85rem;
    color: #666;
}

/* Estado vacío */
.empty-state-youtube {
    text-align: center;
    padding: 4rem 2rem;
    background: #fff;
    border-radius: 12px;
}

.empty-state-youtube-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state-youtube h3 {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: #222;
}

.empty-state-youtube p {
    color: #666;
    margin-bottom: 1.5rem;
}

.btn-publish-first {
    background: #ff6a00;
    border: none;
    color: #fff;
    padding: 0.75rem 2rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 1rem;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-publish-first:hover {
    background: #e85e00;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(255, 106, 0, 0.3);
    color: #fff;
}

/* Responsive */
@media (max-width: 768px) {
    .profile-youtube-info {
        flex-direction: column;
        text-align: center;
    }
    
    .profile-youtube-meta {
        justify-content: center;
    }
    
    .profile-youtube-tabs {
        gap: 1rem;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .products-youtube-grid {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 1rem;
    }
    
    .product-youtube-image {
        height: 150px;
    }
}

@media (max-width: 576px) {
    .profile-youtube-header {
        padding: 1.5rem;
    }
    
    .profile-youtube-photo {
        width: 64px;
        height: 64px;
        font-size: 24px;
    }
    
    .profile-youtube-details h1 {
        font-size: 1.5rem;
    }
}
</style>

<div class="profile-youtube-container">
    <!-- Header del perfil -->
    <div class="profile-youtube-header">
        <div class="profile-youtube-info">
            <div class="profile-youtube-photo">
                <?php $fotoPerfilPerfil = !empty($usr['foto_perfil']) ? $usr['foto_perfil'] : ($_SESSION['foto_perfil'] ?? null); ?>
                <?php if (!empty($fotoPerfilPerfil)): ?>
                    <img src="/uploads/perfiles/<?php echo htmlspecialchars($fotoPerfilPerfil); ?>" alt="Foto de perfil" style="width:100%;height:100%;border-radius:50%;object-fit:cover;display:block;">
                <?php else: ?>
                    <?php echo htmlspecialchars(mb_substr($usr['nombre'] ?? 'U', 0, 1, 'UTF-8')); ?>
                <?php endif; ?>
            </div>
            <div class="profile-youtube-details">
                <h1><?php echo htmlspecialchars($usr['nombre'] ?? 'Usuario'); ?></h1>
                <div class="profile-youtube-meta">
                    <span>
                        <i class="fas fa-star" style="color: #ffc107;"></i>
                        5.0 (0 calificaciones)
                    </span>
                    <span>
                        <i class="fas fa-map-marker-alt" style="color: #ff6a00;"></i>
                        <?php echo htmlspecialchars(($usr['ciudad_nombre'] ?? 'Bolivia') . ', ' . ($usr['departamento'] ?? 'Bolivia')); ?>
                    </span>
                </div>
                <div class="profile-youtube-actions">
                    <a href="/mi/editar_perfil.php" class="btn-edit-profile">
                        <i class="fas fa-edit"></i> Editar perfil
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs estilo YouTube -->
    <div class="profile-youtube-tabs">
        <button class="profile-tab active" data-tab="publicaciones">
            MIS PUBLICACIONES
        </button>
        <button class="profile-tab" data-tab="favoritos">
            FAVORITOS
        </button>
    </div>

    <!-- Contenido: Mis Publicaciones -->
    <div class="profile-tab-content active" id="tab-publicaciones">
        <?php if (empty($productos)): ?>
            <div class="empty-state-youtube">
                <div class="empty-state-youtube-icon">📦</div>
                <h3>¡Publica tu primer producto!</h3>
                <p>Empieza a vender en Done! y llega a miles de compradores</p>
                <a href="/products/add_product.php" class="btn-publish-first">
                    <i class="fas fa-plus-circle"></i>
                    Publicar ahora
                </a>
            </div>
        <?php else: ?>
            <div class="products-youtube-grid">
                <?php foreach ($productos as $producto): 
                    $id = (int)$producto['id'];
                    $titulo = $producto['titulo'];
                    $precio = $producto['precio'];
                    $img = $producto['img'];
                    $imgUrl = $img ? '/uploads/' . $img : 'https://via.placeholder.com/250x200?text=Sin+imagen';
                    $href = '/products/view_product.php?id=' . $id;
                ?>
                    <a href="<?php echo htmlspecialchars($href); ?>" class="product-youtube-card">
                        <img src="<?php echo htmlspecialchars($imgUrl); ?>" 
                             alt="<?php echo htmlspecialchars($titulo); ?>" 
                             class="product-youtube-image">
                        <div class="product-youtube-info">
                            <h3 class="product-youtube-title"><?php echo htmlspecialchars($titulo); ?></h3>
                            <p class="product-youtube-price">Bs. <?php echo number_format((float)$precio, 2, '.', ','); ?></p>
                            <div class="product-youtube-meta">
                                <span><i class="fas fa-eye"></i> Ver detalles</span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Contenido: Favoritos -->
    <div class="profile-tab-content" id="tab-favoritos">
        <?php if (empty($favoritos)): ?>
            <div class="empty-state-youtube">
                <div class="empty-state-youtube-icon">❤️</div>
                <h3>No tienes favoritos guardados</h3>
                <p>Explora productos y guarda tus favoritos para verlos aquí</p>
                <a href="/products/search.php" class="btn-publish-first">
                    <i class="fas fa-search"></i>
                    Explorar productos
                </a>
            </div>
        <?php else: ?>
            <div class="products-youtube-grid">
                <?php foreach ($favoritos as $producto): 
                    $id = (int)$producto['id'];
                    $titulo = $producto['titulo'];
                    $precio = $producto['precio'];
                    $img = $producto['img'];
                    $imgUrl = $img ? '/uploads/' . $img : 'https://via.placeholder.com/250x200?text=Sin+imagen';
                    $href = '/products/view_product.php?id=' . $id;
                ?>
                    <a href="<?php echo htmlspecialchars($href); ?>" class="product-youtube-card">
                        <img src="<?php echo htmlspecialchars($imgUrl); ?>" 
                             alt="<?php echo htmlspecialchars($titulo); ?>" 
                             class="product-youtube-image">
                        <div class="product-youtube-info">
                            <h3 class="product-youtube-title"><?php echo htmlspecialchars($titulo); ?></h3>
                            <p class="product-youtube-price">Bs. <?php echo number_format((float)$precio, 2, '.', ','); ?></p>
                            <div class="product-youtube-meta">
                                <span><i class="fas fa-heart" style="color: #ff6a00;"></i> Favorito</span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Funcionalidad de tabs
document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.profile-tab');
    const contents = document.querySelectorAll('.profile-tab-content');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const tabName = this.getAttribute('data-tab');
            
            // Remover active de todos los tabs
            tabs.forEach(t => t.classList.remove('active'));
            contents.forEach(c => c.classList.remove('active'));
            
            // Agregar active al tab clickeado
            this.classList.add('active');
            document.getElementById('tab-' + tabName).classList.add('active');
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
