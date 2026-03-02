<?php
$titulo = "Buscar Tiendas - Feria Virtual";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$resultados = [];

if ($query) {
    try {
        $db = getDB();
        // Buscar tiendas por nombre o descripción (si existe)
        // Asumimos campos básicos nombre, slug, logo based on feria.php
        $stmt = $db->prepare("
            SELECT t.*, u.municipio_nombre as ciudad
            FROM tiendas t
            LEFT JOIN usuarios u ON t.usuario_id = u.id
            WHERE t.nombre LIKE ? 
            ORDER BY t.nombre ASC
        ");
        $stmt->execute(['%' . $query . '%']);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Silenciar error
    }
}
?>

<div class="container my-5">
    <div class="text-center mb-5">
        <h1 class="fw-bold">Resultados de búsqueda</h1>
        <p class="text-muted">Buscando tiendas: "<?php echo htmlspecialchars($query); ?>"</p>
        
        <!-- Buscador Secundario -->
        <div class="row justify-content-center mt-4">
            <div class="col-md-6">
                <form action="feria_search.php" method="GET" style="position: relative;">
                    <input type="text" name="q" class="form-control rounded-pill py-3 px-4 shadow-sm" 
                           placeholder="Buscar otra tienda..." 
                           value="<?php echo htmlspecialchars($query); ?>"
                           style="border: 1px solid #e2e8f0;">
                    <button type="submit" class="btn position-absolute top-50 end-0 translate-middle-y me-2 rounded-circle text-white" 
                            style="background: var(--accent-orange); width: 40px; height: 40px;">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <?php if (empty($query)): ?>
        <div class="text-center py-5">
            <i class="fas fa-store fa-4x text-muted mb-3"></i>
            <h3>Ingresa el nombre de una tienda</h3>
        </div>
    <?php elseif (empty($resultados)): ?>
        <div class="text-center py-5">
            <i class="fas fa-search fa-4x text-muted mb-3"></i>
            <h3>No encontramos tiendas con ese nombre</h3>
            <p>Intenta con otra palabra clave o navega por los sectores de la feria.</p>
            <a href="/feria.php" class="btn btn-outline-primary mt-3">Volver a la Feria</a>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($resultados as $tienda): ?>
                <?php 
                    $logo = !empty($tienda['logo']) ? '/uploads/logos/' . $tienda['logo'] : '/assets/img/default-store.png';
                    // Manejo de URL absoluta vs relativa igual que en feria.php
                    if (!empty($tienda['logo']) && strpos($tienda['logo'], 'http') === 0) {
                        $logo = $tienda['logo'];
                    }
                ?>
                <div class="col-md-3 col-6">
                    <a href="/tienda/<?php echo htmlspecialchars($tienda['slug']); ?>" class="text-decoration-none">
                        <div class="card h-100 border-0 shadow-sm hover-shadow transition-all">
                            <div class="card-body text-center p-4">
                                <div class="mb-3 mx-auto position-relative" style="width: 80px; height: 80px;">
                                    <img src="<?php echo htmlspecialchars($logo); ?>" 
                                         alt="<?php echo htmlspecialchars($tienda['nombre']); ?>"
                                         class="rounded-circle img-fluid border bg-white"
                                         style="width: 100%; height: 100%; object-fit: cover;">
                                </div>
                                <h5 class="card-title text-dark fw-bold mb-1"><?php echo htmlspecialchars($tienda['nombre']); ?></h5>
                                <?php if(!empty($tienda['ciudad'])): ?>
                                    <small class="text-muted"><i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($tienda['ciudad']); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.hover-shadow:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
}
.transition-all {
    transition: all 0.3s ease;
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
