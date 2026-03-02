<?php
$titulo = "Gestión de Tiendas";
require_once 'header.php';

// Obtener conexión a BD
$db = getDB();

// Filtros
$buscar = $_GET['q'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';
$orden = $_GET['orden'] ?? 'recientes';

// Paginación
$por_pagina = 20;
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina - 1) * $por_pagina;

// Construir query
$where = [];
$params = [];

if ($buscar) {
    $where[] = "(t.nombre LIKE ? OR t.slug LIKE ?)";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}

if ($filtro_estado !== '') {
    $where[] = "t.estado = ?";
    $params[] = $filtro_estado;
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Obtener total
$stmt = $db->prepare("SELECT COUNT(*) as total FROM tiendas t $where_sql");
$stmt->execute($params);
$total = $stmt->fetch()['total'];
$total_paginas = ceil($total / $por_pagina);

// Orden
$order_sql = match($orden) {
    'antiguos' => 't.created_at ASC',
    'visitas_desc' => 't.visitas DESC',
    'nombre' => 't.nombre ASC',
    default => 't.created_at DESC' // Si no existe created_at, usaremos id DESC
};

// Verificar si existe la columna created_at, si no, usar id
// Para simplificar, asumiremos ID por ahora si no estamos seguros, pero intentaremos created_at.
// Mejor usar ID DESC por defecto que es seguro.
if ($orden === 'recientes') $order_sql = 't.id DESC';
if ($orden === 'antiguos') $order_sql = 't.id ASC';


// Obtener tiendas
try {
    $stmt = $db->prepare("
        SELECT t.*,
               u.nombre as usuario_nombre,
               u.email as usuario_email,
               (SELECT COUNT(*) FROM productos p WHERE p.usuario_id = t.usuario_id AND p.activo = 1) as total_productos
        FROM tiendas t
        LEFT JOIN usuarios u ON u.id = t.usuario_id
        $where_sql
        ORDER BY $order_sql
        LIMIT $por_pagina OFFSET $offset
    ");
    $stmt->execute($params);
    $tiendas = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("Error en stores.php: " . $e->getMessage());
    $tiendas = [];
}
?>

<!-- Filtros -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="stat-card">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="q" class="form-control"
                           placeholder="Buscar por nombre o slug..."
                           value="<?php echo htmlspecialchars($buscar); ?>">
                </div>
                <div class="col-md-3">
                    <select name="estado" class="form-select">
                        <option value="">Todos los estados</option>
                        <option value="activo" <?php echo $filtro_estado === 'activo' ? 'selected' : ''; ?>>Activas</option>
                        <option value="inactivo" <?php echo $filtro_estado === 'inactivo' ? 'selected' : ''; ?>>Inactivas</option>
                        <option value="suspendido" <?php echo $filtro_estado === 'suspendido' ? 'selected' : ''; ?>>Suspendidas</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="orden" class="form-select">
                        <option value="recientes" <?php echo $orden === 'recientes' ? 'selected' : ''; ?>>Más recientes</option>
                        <option value="antiguos" <?php echo $orden === 'antiguos' ? 'selected' : ''; ?>>Más antiguas</option>
                        <option value="visitas_desc" <?php echo $orden === 'visitas_desc' ? 'selected' : ''; ?>>Más visitas</option>
                        <option value="nombre" <?php echo $orden === 'nombre' ? 'selected' : ''; ?>>Nombre A-Z</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                <div class="col-md-1">
                    <a href="/admin/stores.php" class="btn btn-secondary w-100">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Estadísticas -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-card-icon" style="background: rgba(40,167,69,0.1); color: #28a745;">
                <i class="fas fa-store"></i>
            </div>
            <div class="stat-card-title">Total Tiendas</div>
            <div class="stat-card-value"><?php echo formatearNumero($total); ?></div>
        </div>
    </div>
</div>

<!-- Tabla de tiendas -->
<div class="admin-table">
    <table class="table table-hover">
        <thead>
            <tr>
                <th style="width: 60px;">Logo</th>
                <th>Tienda</th>
                <th>Propietario</th>
                <th>Productos</th>
                <th>Visitas</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tiendas as $tienda): ?>
                <tr>
                    <td>
                        <?php if ($tienda['logo']): ?>
                            <img src="/uploads/<?php echo htmlspecialchars($tienda['logo']); ?>"
                                 alt="Logo"
                                 style="width: 50px; height: 50px; object-fit: cover; border-radius: 50%; border: 1px solid #ddd;"
                                 onerror="this.onerror=null; this.parentElement.innerHTML='<div style=\'width: 50px; height: 50px; background: #e9ecef; border-radius: 50%; display: flex; align-items: center; justify-content: center;\'><i class=\'fas fa-store text-muted\'></i></div>';">
                        <?php else: ?>
                            <div style="width: 50px; height: 50px; background: <?php echo htmlspecialchars($tienda['color_primario'] ?? '#e9ecef'); ?>; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;">
                                <i class="fas fa-store"></i>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars($tienda['nombre']); ?></strong>
                        <br>
                        <small class="text-muted">/<?php echo htmlspecialchars($tienda['slug']); ?></small>
                    </td>
                    <td>
                        <small>
                            <?php echo htmlspecialchars($tienda['usuario_nombre']); ?>
                            <br>
                            <span class="text-muted"><?php echo htmlspecialchars($tienda['usuario_email']); ?></span>
                        </small>
                    </td>
                    <td>
                        <span class="badge bg-secondary">
                            <?php echo $tienda['total_productos']; ?> prods
                        </span>
                    </td>
                    <td>
                        <i class="fas fa-eye text-muted me-1"></i>
                        <?php echo number_format($tienda['visitas']); ?>
                    </td>
                    <td>
                        <?php
                        $estado_class = match($tienda['estado']) {
                            'activo' => 'bg-success',
                            'inactivo' => 'bg-secondary',
                            'suspendido' => 'bg-danger',
                            default => 'bg-warning'
                        };
                        ?>
                        <span class="badge <?php echo $estado_class; ?>">
                            <?php echo ucfirst($tienda['estado']); ?>
                        </span>
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <a href="/admin/store_manage.php?id=<?php echo $tienda['id']; ?>" 
                               class="btn btn-primary" title="Gestionar Tienda">
                                <i class="fas fa-cogs"></i> Gestionar
                            </a>
                            <a href="/tienda/<?php echo $tienda['slug']; ?>"
                               class="btn btn-outline-secondary" target="_blank" title="Ver Frontend">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Paginación -->
<?php if ($total_paginas > 1): ?>
<nav class="mt-4">
    <ul class="pagination justify-content-center">
        <?php if ($pagina > 1): ?>
            <li class="page-item">
                <a class="page-link" href="?pagina=<?php echo $pagina - 1; ?>&q=<?php echo urlencode($buscar); ?>&estado=<?php echo $filtro_estado; ?>&orden=<?php echo $orden; ?>">
                    Anterior
                </a>
            </li>
        <?php endif; ?>

        <?php for ($i = max(1, $pagina - 3); $i <= min($total_paginas, $pagina + 3); $i++): ?>
            <li class="page-item <?php echo $i === $pagina ? 'active' : ''; ?>">
                <a class="page-link" href="?pagina=<?php echo $i; ?>&q=<?php echo urlencode($buscar); ?>&estado=<?php echo $filtro_estado; ?>&orden=<?php echo $orden; ?>">
                    <?php echo $i; ?>
                </a>
            </li>
        <?php endfor; ?>

        <?php if ($pagina < $total_paginas): ?>
            <li class="page-item">
                <a class="page-link" href="?pagina=<?php echo $pagina + 1; ?>&q=<?php echo urlencode($buscar); ?>&estado=<?php echo $filtro_estado; ?>&orden=<?php echo $orden; ?>">
                    Siguiente
                </a>
            </li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<script>
function cambiarEstadoTienda(id, nuevoEstado) {
    const accion = nuevoEstado === 'activo' ? 'ACTIVAR' : 'SUSPENDER';
    if (!confirm(`¿Estás seguro de ${accion} esta tienda?`)) return;

    fetch('/admin/ajax/cambiar_estado_tienda.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, estado: nuevoEstado, csrf_token: CSRF_TOKEN })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => {
        alert('Error de conexión');
        console.error(err);
    });
}
</script>

<?php require_once 'footer.php'; ?>