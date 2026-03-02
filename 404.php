<?php
$titulo = "Página No Encontrada";
require_once 'includes/header.php';
?>

<div class="container">
    <div class="error-page">
        <div class="row justify-content-center">
            <div class="col-lg-6 text-center">
                <div class="error-content">
                    <div class="error-number">404</div>
                    <h1 class="error-title">¡Oops! Página no encontrada</h1>
                    <p class="error-description">
                        La página que estás buscando no existe o ha sido movida. 
                        Puede que el enlace esté roto o que hayas escrito mal la dirección.
                    </p>
                    
                    <div class="error-actions">
                        <a href="index.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-home"></i>
                            Ir al Inicio
                        </a>
                        <a href="products/search.php" class="btn btn-outline-primary btn-lg">
                            <i class="fas fa-search"></i>
                            Buscar Productos
                        </a>
                    </div>
                    
                    <div class="error-suggestions">
                        <h5>¿Qué puedes hacer?</h5>
                        <ul class="suggestions-list">
                            <li>Verifica que la URL esté escrita correctamente</li>
                            <li>Usa el buscador para encontrar lo que necesitas</li>
                            <li>Explora nuestras categorías principales</li>
                            <li>Contacta con nosotros si el problema persiste</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Categorías populares -->
        <div class="popular-categories">
            <h3>Categorías Populares</h3>
            <div class="row">
                <?php
                $categorias = obtenerCategorias();
                foreach ($categorias as $categoria):
                ?>
                <div class="col-lg-3 col-md-6 mb-3">
                    <a href="products/category.php?id=<?php echo $categoria['id']; ?>" class="category-link">
                        <div class="category-card-small">
                            <i class="<?php echo $categoria['icono']; ?>"></i>
                            <span><?php echo $categoria['nombre']; ?></span>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<style>
.error-page {
    padding: 4rem 0;
    min-height: 60vh;
}

.error-content {
    padding: 2rem;
}

.error-number {
    font-size: 8rem;
    font-weight: 900;
    color: var(--primary-color);
    line-height: 1;
    margin-bottom: 1rem;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
}

.error-title {
    font-size: 2.5rem;
    color: var(--secondary-color);
    margin-bottom: 1.5rem;
}

.error-description {
    font-size: 1.1rem;
    color: var(--gray);
    margin-bottom: 2rem;
    line-height: 1.6;
}

.error-actions {
    margin-bottom: 3rem;
}

.error-actions .btn {
    margin: 0.5rem;
}

.error-suggestions {
    background: var(--light-gray);
    padding: 2rem;
    border-radius: var(--border-radius);
    text-align: left;
    display: inline-block;
    margin-bottom: 3rem;
}

.error-suggestions h5 {
    color: var(--secondary-color);
    margin-bottom: 1rem;
}

.suggestions-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.suggestions-list li {
    padding: 0.5rem 0;
    color: var(--gray);
    position: relative;
    padding-left: 1.5rem;
}

.suggestions-list li:before {
    content: '💡';
    position: absolute;
    left: 0;
}

.popular-categories {
    border-top: 1px solid var(--border-color);
    padding-top: 3rem;
    text-align: center;
}

.popular-categories h3 {
    color: var(--secondary-color);
    margin-bottom: 2rem;
}

.category-link {
    text-decoration: none;
    color: inherit;
}

.category-card-small {
    background: var(--white);
    padding: 1.5rem;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
    transition: var(--transition);
    text-align: center;
    border: 2px solid transparent;
}

.category-card-small:hover {
    transform: translateY(-3px);
    border-color: var(--primary-color);
    box-shadow: var(--shadow-hover);
}

.category-card-small i {
    font-size: 2rem;
    color: var(--primary-color);
    margin-bottom: 0.5rem;
    display: block;
}

.category-card-small span {
    font-weight: 600;
    color: var(--secondary-color);
}

@media (max-width: 768px) {
    .error-number {
        font-size: 6rem;
    }
    
    .error-title {
        font-size: 2rem;
    }
    
    .error-actions .btn {
        display: block;
        width: 100%;
        margin: 0.5rem 0;
    }
    
    .error-suggestions {
        width: 100%;
        text-align: center;
    }
}

@media (max-width: 576px) {
    .error-page {
        padding: 2rem 0;
    }
    
    .error-number {
        font-size: 4rem;
    }
    
    .error-title {
        font-size: 1.5rem;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>
