<?php
session_start();
require_once 'includes/header.php';
require_once 'config/database.php'; // Asegúrate de que esta ruta es correcta para tu base de datos

$productos_favoritos = [];
$mensaje = '';

// Verificar si el usuario está logueado
if (!estaLogueado()) {
    redireccionar('auth/login.php?redirect=' . urlencode('favorites.php')); // Redirigir a login si no está logueado
}

$user_id = $_SESSION['usuario_id'];

try {
    $database = new Database();
    $db = $database->getConnection();

    // Consulta para obtener los productos favoritos del usuario actual
    $query = "
        SELECT 
            p.id, p.titulo, p.precio, p.estado, p.fecha_publicacion,
            pi.nombre_archivo as imagen_principal,
            c.nombre as ciudad_nombre
        FROM favoritos f
        JOIN productos p ON f.producto_id = p.id
        LEFT JOIN producto_imagenes pi ON p.id = pi.producto_id AND pi.es_principal = 1
        JOIN ciudades c ON p.ciudad_id = c.id
        WHERE f.usuario_id = :user_id AND p.activo = 1
        ORDER BY f.fecha DESC
    ";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $productos_favoritos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($productos_favoritos)) {
        $mensaje = 'No tienes productos marcados como favoritos aún.';
    }

} catch (PDOException $e) {
    error_log("Error al cargar favoritos: " . $e->getMessage());
    $mensaje = 'Ocurrió un error al cargar tus favoritos. Por favor, inténtalo de nuevo más tarde.';
}

$titulo_pagina = 'Mis Favoritos';
?>

<div class="container my-5">
    <h1 class="mb-4 text-center">Mis Productos Favoritos</h1>

    <?php if ($mensaje): ?>
        <div class="alert alert-info text-center" role="alert">
            <?php echo $mensaje; ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <?php if (!empty($productos_favoritos)): ?>
            <?php foreach ($productos_favoritos as $producto): ?>
                <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                    <div class="product-card" data-product-id="<?php echo $producto['id']; ?>">
                        <div class="product-image">
                            <?php if ($producto['imagen_principal']): ?>
                                <img src="uploads/<?php echo htmlspecialchars($producto['imagen_principal']); ?>" 
                                     alt="<?php echo htmlspecialchars($producto['titulo']); ?>"
                                     loading="lazy">
                            <?php else: ?>
                                <img src="assets/images/no-image.jpg" 
                                     alt="Sin imagen"
                                     loading="lazy">
                            <?php endif; ?>
                            <span class="badge bg-<?php echo $producto['estado'] === 'nuevo' ? 'success' : 'warning'; ?> product-state">
                                <?php echo ucfirst($producto['estado']); ?>
                            </span>
                        </div>
                        <div class="product-info">
                            <h4 class="product-title"><a href="products/view_product.php?id=<?php echo $producto['id']; ?>">
                                <?php echo htmlspecialchars($producto['titulo']); ?>
                            </a></h4>
                            <div class="product-price">Bs. <?php echo number_format($producto['precio'], 2, '.', ','); ?></div>
                            <div class="product-location">
                                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($producto['ciudad_nombre']); ?>
                            </div>
                            <div class="product-date">
                                <i class="fas fa-clock"></i> <?php echo tiempoTranscurrido($producto['fecha_publicacion']); ?>
                            </div>
                            <div class="product-actions d-flex justify-content-between align-items-center mt-2">
                                <a href="products/view_product.php?id=<?php echo $producto['id']; ?>" class="btn btn-primary btn-sm">Ver Detalles</a>
                                <!-- Botón de eliminar de favoritos -->
                                <button class="btn btn-outline-danger btn-sm toggle-favorite-list" 
                                        data-product-id="<?php echo $producto['id']; ?>"
                                        data-is-favorite="true" 
                                        title="Eliminar de favoritos">
                                    <i class="fas fa-heart-broken"></i> <span class="text-label">Eliminar</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
// Lógica para el botón de eliminar de favoritos en la página de favoritos
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.toggle-favorite-list').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.dataset.productId;
            const card = this.closest('.product-card');

            fetch('api/toggle_favorite.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `product_id=${productId}`,
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.action === 'removed') {
                    // Eliminar la tarjeta del producto de la vista
                    if (card) {
                        card.parentNode.removeChild(card);
                    }
                    // Si no quedan favoritos, mostrar un mensaje
                    if (document.querySelectorAll('.product-card').length === 0) {
                        const container = document.querySelector('.container.my-5');
                        if (container) {
                            let noFavoritesMessage = container.querySelector('.alert-info');
                            if (!noFavoritesMessage) {
                                noFavoritesMessage = document.createElement('div');
                                noFavoritesMessage.className = 'alert alert-info text-center';
                                noFavoritesMessage.setAttribute('role', 'alert');
                                container.appendChild(noFavoritesMessage);
                            }
                            noFavoritesMessage.textContent = 'No tienes productos marcados como favoritos aún.';
                        }
                    }
                } else {
                    alert('Error al eliminar de favoritos: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error en la solicitud AJAX:', error);
                alert('Error al procesar la solicitud. Inténtalo de nuevo.');
            });
        });
    });
});
</script>

<style>
/* Puedes añadir estilos adicionales aquí si lo necesitas para las tarjetas de productos en la página de favoritos */
/* Los estilos de .product-card y sus elementos internos probablemente ya los tienes en tu CSS global */
.product-state {
    position: absolute;
    top: 10px;
    left: 10px;
    padding: 0.2em 0.6em;
    border-radius: 0.25rem;
    font-size: 0.75em;
}
.product-actions .toggle-favorite-list .text-label {
    margin-left: 5px; /* Espacio entre el ícono y el texto */
}
</style>
