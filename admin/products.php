<?php
$titulo = "Productos";
require_once 'header.php';

// Obtener conexión a BD
$db = getDB();

// Filtros
$buscar = $_GET['q'] ?? '';
$filtro_activo = $_GET['activo'] ?? '';
$filtro_categoria = $_GET['categoria'] ?? '';
$orden = $_GET['orden'] ?? 'recientes';

// Paginación
$por_pagina = 20;
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina - 1) * $por_pagina;

// Obtener categorías para filtro
$categorias = $db->query("SELECT * FROM categorias ORDER BY nombre")->fetchAll();

// Construir query
$where = [];
$params = [];

if ($buscar) {
    // Corrección: Búsqueda usando el JOIN con la tabla tiendas (alias t)
    $where[] = "(
        p.titulo LIKE ? OR 
        p.descripcion LIKE ? OR 
        u.nombre LIKE ? OR 
        u.email LIKE ? OR 
        t.nombre LIKE ?
    )";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}

if ($filtro_activo !== '') {
    $where[] = "p.activo = ?";
    $params[] = $filtro_activo;
}

if ($filtro_categoria) {
    $where[] = "p.categoria_id = ?";
    $params[] = $filtro_categoria;
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Obtener total
try {
    // IMPORTANTE: Debemos incluir los JOINS también en el conteo porque el WHERE puede filtrar por tablas unidas
    $sql_count = "
        SELECT COUNT(*) as total 
        FROM productos p 
        LEFT JOIN usuarios u ON u.id = p.usuario_id
        LEFT JOIN tiendas t ON t.usuario_id = p.usuario_id
        $where_sql
    ";
    $stmt = $db->prepare($sql_count);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
} catch (Exception $e) {
    error_log("Error al contar productos: " . $e->getMessage());
    $total = 0;
}
$total_paginas = ($total > 0) ? ceil($total / $por_pagina) : 1;

// Orden
$order_sql = match($orden) {
    'antiguos' => 'p.fecha_publicacion ASC',
    'precio_asc' => 'p.precio ASC',
    'precio_desc' => 'p.precio DESC',
    'titulo' => 'p.titulo ASC',
    default => 'p.fecha_publicacion DESC'
};

// Obtener productos
try {
    // Obtener productos con JOIN para eliminar problema N+1
    $stmt = $db->prepare("
        SELECT p.*,
               u.nombre as usuario_nombre,
               u.email as usuario_email,
               c.nombre as categoria_nombre,
               (
                   SELECT nombre_archivo 
                   FROM producto_imagenes pi 
                   WHERE pi.producto_id = p.id 
                   ORDER BY pi.es_principal DESC, pi.orden ASC 
                   LIMIT 1
               ) as imagen
        FROM productos p
        LEFT JOIN usuarios u ON u.id = p.usuario_id
        LEFT JOIN categorias c ON c.id = p.categoria_id
        LEFT JOIN tiendas t ON t.usuario_id = p.usuario_id
        $where_sql
        ORDER BY $order_sql
        LIMIT $por_pagina OFFSET $offset
    ");
    $stmt->execute($params);
    $productos = $stmt->fetchAll();

    // El bucle foreach ya no necesita hacer consultas
} catch (Exception $e) {
    // Error en producción (loguear y mostrar vacío)
    error_log("Error en products.php: " . $e->getMessage());
    $productos = [];
    
    // DEBUG: Descomentar para ver el error en pantalla si es necesario
    // echo "<div class='alert alert-danger'>Error SQL: " . $e->getMessage() . "</div>";
}
?>

<!-- Filtros -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="stat-card">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="q" class="form-control"
                           placeholder="Buscar productos..."
                           value="<?php echo htmlspecialchars($buscar); ?>">
                </div>
                <div class="col-md-2">
                    <select name="categoria" class="form-select">
                        <option value="">Todas las categorías</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"
                                    <?php echo $filtro_categoria == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="activo" class="form-select">
                        <option value="">Todos los estados</option>
                        <option value="1" <?php echo $filtro_activo === '1' ? 'selected' : ''; ?>>Activos</option>
                        <option value="0" <?php echo $filtro_activo === '0' ? 'selected' : ''; ?>>Inactivos</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="orden" class="form-select">
                        <option value="recientes" <?php echo $orden === 'recientes' ? 'selected' : ''; ?>>Más recientes</option>
                        <option value="antiguos" <?php echo $orden === 'antiguos' ? 'selected' : ''; ?>>Más antiguos</option>
                        <option value="precio_asc" <?php echo $orden === 'precio_asc' ? 'selected' : ''; ?>>Menor precio</option>
                        <option value="precio_desc" <?php echo $orden === 'precio_desc' ? 'selected' : ''; ?>>Mayor precio</option>
                        <option value="titulo" <?php echo $orden === 'titulo' ? 'selected' : ''; ?>>Título A-Z</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                <div class="col-md-2">
                    <a href="/admin/products.php" class="btn btn-secondary w-100">
                        <i class="fas fa-times me-2"></i>Limpiar
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
                <i class="fas fa-box"></i>
            </div>
            <div class="stat-card-title">Total Productos</div>
            <div class="stat-card-value"><?php echo formatearNumero($total); ?></div>
        </div>
    </div>
</div>

<!-- Tabla de productos -->
<div class="admin-table">
    <table class="table table-hover">
        <thead>
            <tr>
                <th style="width: 60px;">Imagen</th>
                <th>Producto</th>
                <th>Usuario</th>
                <th>Categoría</th>
                <th>Precio</th>
                <th>Publicación</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($productos as $prod): ?>
                <tr>
                    <td>
                        <?php if ($prod['imagen']): ?>
                            <img src="/uploads/<?php echo htmlspecialchars($prod['imagen']); ?>"
                                 alt="<?php echo htmlspecialchars(substr($prod['titulo'], 0, 30)); ?>"
                                 style="width: 50px; height: 50px; object-fit: cover; border-radius: 6px;"
                                 onerror="this.onerror=null; this.parentElement.innerHTML='<div style=\'width: 50px; height: 50px; background: #e9ecef; border-radius: 6px; display: flex; align-items: center; justify-content: center;\'><i class=\'fas fa-image text-muted\'></i></div>';">
                        <?php else: ?>
                            <div style="width: 50px; height: 50px; background: #e9ecef; border-radius: 6px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-image text-muted"></i>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars(substr($prod['titulo'], 0, 50)); ?></strong>
                        <?php if (strlen($prod['titulo']) > 50) echo '...'; ?>
                        <br>
                        <small class="text-muted">ID: #<?php echo $prod['id']; ?></small>
                    </td>
                    <td>
                        <small>
                            <?php echo htmlspecialchars($prod['usuario_nombre']); ?>
                            <br>
                            <span class="text-muted"><?php echo htmlspecialchars($prod['usuario_email']); ?></span>
                        </small>
                    </td>
                    <td>
                        <span class="badge bg-info">
                            <?php echo htmlspecialchars($prod['categoria_nombre']); ?>
                        </span>
                    </td>
                    <td>
                        <strong>Bs. <?php echo number_format($prod['precio'], 2); ?></strong>
                    </td>
                    <td>
                        <small class="text-muted">
                            <?php echo date('d/m/Y', strtotime($prod['fecha_publicacion'])); ?>
                        </small>
                    </td>
                    <td>
                        <?php if ($prod['activo']): ?>
                            <span class="badge bg-success">Activo</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <a href="/products/view_product.php?id=<?php echo $prod['id']; ?>"
                               class="btn btn-info" target="_blank" title="Ver">
                                <i class="fas fa-eye"></i>
                            </a>
                            <?php if ($prod['activo']): ?>
                                <button class="btn btn-warning" onclick="cambiarEstado(<?php echo $prod['id']; ?>, 0)"
                                        title="Desactivar">
                                    <i class="fas fa-ban"></i>
                                </button>
                            <?php else: ?>
                                <button class="btn btn-success" onclick="cambiarEstado(<?php echo $prod['id']; ?>, 1)"
                                        title="Activar">
                                    <i class="fas fa-check"></i>
                                </button>
                            <?php endif; ?>
                            <button class="btn btn-danger" onclick="eliminar(<?php echo $prod['id']; ?>, '<?php echo htmlspecialchars(addslashes($prod['titulo'])); ?>')"
                                    title="Eliminar">
                                <i class="fas fa-trash"></i>
                            </button>
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
                <a class="page-link" href="?pagina=<?php echo $pagina - 1; ?>&q=<?php echo urlencode($buscar); ?>&activo=<?php echo $filtro_activo; ?>&categoria=<?php echo $filtro_categoria; ?>&orden=<?php echo $orden; ?>">
                    Anterior
                </a>
            </li>
        <?php endif; ?>

        <?php for ($i = max(1, $pagina - 3); $i <= min($total_paginas, $pagina + 3); $i++): ?>
            <li class="page-item <?php echo $i === $pagina ? 'active' : ''; ?>">
                <a class="page-link" href="?pagina=<?php echo $i; ?>&q=<?php echo urlencode($buscar); ?>&activo=<?php echo $filtro_activo; ?>&categoria=<?php echo $filtro_categoria; ?>&orden=<?php echo $orden; ?>">
                    <?php echo $i; ?>
                </a>
            </li>
        <?php endfor; ?>

        <?php if ($pagina < $total_paginas): ?>
            <li class="page-item">
                <a class="page-link" href="?pagina=<?php echo $pagina + 1; ?>&q=<?php echo urlencode($buscar); ?>&activo=<?php echo $filtro_activo; ?>&categoria=<?php echo $filtro_categoria; ?>&orden=<?php echo $orden; ?>">
                    Siguiente
                </a>
            </li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<script>
function cambiarEstado(id, nuevoEstado) {
    if (!confirm('¿Estás seguro de cambiar el estado de este producto?')) return;

    fetch('/admin/ajax/cambiar_estado_producto.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, activo: nuevoEstado, csrf_token: CSRF_TOKEN }) // CSRF Agregado
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function eliminar(id, titulo) {
    if (!confirm(`¿ELIMINAR el producto "${titulo}"?\n\nEsta acción NO se puede deshacer.`)) return;

    fetch('/admin/ajax/eliminar_producto.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, csrf_token: CSRF_TOKEN }) // CSRF Agregado
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}
</script>

<?php require_once 'footer.php'; ?>