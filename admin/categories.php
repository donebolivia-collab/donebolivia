<?php
$titulo = "Categorías";
require_once 'header.php';

// Obtener conexión a BD
$db = getDB();

// Obtener categorías
$categorias = $db->query("
    SELECT c.*,
           COUNT(DISTINCT p.id) as productos_count
    FROM categorias c
    LEFT JOIN productos p ON p.categoria_id = c.id
    GROUP BY c.id
    ORDER BY c.nombre ASC
")->fetchAll();

// Obtener subcategorías agrupadas por categoría
$subcategorias_query = $db->query("
    SELECT s.*,
           COUNT(DISTINCT p.id) as productos_count
    FROM subcategorias s
    LEFT JOIN productos p ON p.subcategoria_id = s.id
    GROUP BY s.id
    ORDER BY s.categoria_id, s.nombre ASC
")->fetchAll();

$subcategorias_por_categoria = [];
foreach ($subcategorias_query as $sub) {
    $subcategorias_por_categoria[$sub['categoria_id']][] = $sub;
}
?>

<style>
.categoria-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border: 1px solid #e9ecef;
}

.categoria-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 15px;
    border-bottom: 2px solid #f8f9fa;
    margin-bottom: 15px;
}

.categoria-title {
    display: flex;
    align-items: center;
    gap: 12px;
}

.categoria-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--primary) 0%, #e55d0f 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.subcategorias-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 10px;
    margin-top: 15px;
}

.subcategoria-item {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.3s;
}

.subcategoria-item:hover {
    background: #e9ecef;
    transform: translateX(5px);
}
</style>

<!-- Estadísticas -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-card-icon" style="background: rgba(255,107,26,0.1); color: var(--primary);">
                <i class="fas fa-tags"></i>
            </div>
            <div class="stat-card-title">Total Categorías</div>
            <div class="stat-card-value"><?php echo count($categorias); ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-card-icon" style="background: rgba(40,167,69,0.1); color: #28a745;">
                <i class="fas fa-tag"></i>
            </div>
            <div class="stat-card-title">Total Subcategorías</div>
            <div class="stat-card-value"><?php echo count($subcategorias_query); ?></div>
        </div>
    </div>
</div>

<!-- Listado de categorías -->
<?php foreach ($categorias as $cat): ?>
    <div class="categoria-card">
        <div class="categoria-header">
            <div class="categoria-title">
                <div class="categoria-icon">
                    <i class="<?php echo getCategoryIcon($cat['nombre']); ?>"></i>
                </div>
                <div>
                    <h4 class="mb-1"><?php echo htmlspecialchars($cat['nombre']); ?></h4>
                    <small class="text-muted">
                        <i class="fas fa-box me-1"></i>
                        <?php echo $cat['productos_count']; ?> productos
                    </small>
                </div>
            </div>
            <div>
                <span class="badge bg-primary" style="font-size: 14px;">
                    ID: <?php echo $cat['id']; ?>
                </span>
            </div>
        </div>

        <?php if (isset($subcategorias_por_categoria[$cat['id']])): ?>
            <div>
                <strong class="text-muted" style="font-size: 13px;">
                    <i class="fas fa-list me-1"></i>
                    Subcategorías (<?php echo count($subcategorias_por_categoria[$cat['id']]); ?>):
                </strong>
                <div class="subcategorias-grid">
                    <?php foreach ($subcategorias_por_categoria[$cat['id']] as $sub): ?>
                        <div class="subcategoria-item">
                            <span><?php echo htmlspecialchars($sub['nombre']); ?></span>
                            <span class="badge bg-secondary"><?php echo $sub['productos_count']; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="text-muted">
                <i class="fas fa-info-circle me-1"></i>
                Sin subcategorías
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<!-- Información -->
<div class="alert alert-info mt-4">
    <i class="fas fa-info-circle me-2"></i>
    <strong>Nota:</strong> La gestión de categorías y subcategorías se realiza directamente desde la base de datos.
    Esta vista es de solo lectura para verificar la estructura de categorización del sitio.
</div>

<?php require_once 'footer.php'; ?>
