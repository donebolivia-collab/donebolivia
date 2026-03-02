<?php
$titulo = "Usuarios";
require_once 'header.php';

// Filtros
$buscar = $_GET['q'] ?? '';
$filtro_activo = $_GET['activo'] ?? '';
$orden = $_GET['orden'] ?? 'recientes';

// Paginación
$por_pagina = 20;
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina - 1) * $por_pagina;

// Construir query
$where = [];
$params = [];

if ($buscar) {
    $where[] = "(nombre LIKE ? OR email LIKE ? OR telefono LIKE ?)";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}

if ($filtro_activo !== '') {
    $where[] = "activo = ?";
    $params[] = $filtro_activo;
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Obtener total
$db = getDB();
$stmt = $db->prepare("SELECT COUNT(*) as total FROM usuarios $where_sql");
$stmt->execute($params);
$total = $stmt->fetch()['total'];
$total_paginas = ceil($total / $por_pagina);

// Orden
$order_sql = match($orden) {
    'antiguos' => 'fecha_registro ASC',
    'nombre' => 'nombre ASC',
    'productos' => 'productos_count DESC',
    default => 'fecha_registro DESC'
};

// Obtener usuarios
$stmt = $db->prepare("
    SELECT u.*,
           COUNT(DISTINCT p.id) as productos_count,
           MAX(p.fecha_publicacion) as ultima_publicacion,
           CASE
               WHEN u.fecha_nacimiento IS NOT NULL
               THEN TIMESTAMPDIFF(YEAR, u.fecha_nacimiento, CURDATE())
               ELSE NULL
           END as edad
    FROM usuarios u
    LEFT JOIN productos p ON p.usuario_id = u.id
    $where_sql
    GROUP BY u.id
    ORDER BY $order_sql
    LIMIT $por_pagina OFFSET $offset
");
$stmt->execute($params);
$usuarios = $stmt->fetchAll();
?>

<!-- Filtros y búsqueda -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="stat-card">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="q" class="form-control"
                           placeholder="Buscar por nombre, email o teléfono..."
                           value="<?php echo htmlspecialchars($buscar); ?>">
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
                        <option value="nombre" <?php echo $orden === 'nombre' ? 'selected' : ''; ?>>Nombre A-Z</option>
                        <option value="productos" <?php echo $orden === 'productos' ? 'selected' : ''; ?>>Más productos</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-2"></i>Buscar
                    </button>
                </div>
                <div class="col-md-2">
                    <a href="/admin/users.php" class="btn btn-secondary w-100">
                        <i class="fas fa-times me-2"></i>Limpiar
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Estadísticas rápidas -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-card-icon" style="background: rgba(255,107,26,0.1); color: var(--primary);">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-card-title">Total Usuarios</div>
            <div class="stat-card-value"><?php echo formatearNumero($total); ?></div>
        </div>
    </div>
</div>

<!-- Tabla de usuarios -->
<div class="admin-table">
    <table class="table table-hover">
        <thead>
            <tr>
                <th>ID</th>
                <th>Usuario</th>
                <th>Email</th>
                <th>Teléfono</th>
                <th>Edad</th>
                <th>Productos</th>
                <th>Registro</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($usuarios as $usuario): ?>
                <tr>
                    <td><strong>#<?php echo $usuario['id']; ?></strong></td>
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="admin-user-avatar me-2" style="width: 32px; height: 32px; font-size: 14px;">
                                <?php echo strtoupper(substr($usuario['nombre'], 0, 1)); ?>
                            </div>
                            <div>
                                <strong><?php echo htmlspecialchars($usuario['nombre']); ?></strong>
                            </div>
                        </div>
                    </td>
                    <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                    <td><?php echo htmlspecialchars($usuario['telefono'] ?? '-'); ?></td>
                    <td>
                        <?php if ($usuario['edad']): ?>
                            <span class="badge bg-info"><?php echo $usuario['edad']; ?> años</span>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge bg-primary"><?php echo $usuario['productos_count']; ?></span>
                    </td>
                    <td>
                        <small class="text-muted">
                            <?php echo date('d/m/Y', strtotime($usuario['fecha_registro'])); ?>
                        </small>
                    </td>
                    <td>
                        <?php if ($usuario['activo']): ?>
                            <span class="badge bg-success">Activo</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <?php if ($usuario['activo']): ?>
                                <button class="btn btn-warning" onclick="cambiarEstado(<?php echo $usuario['id']; ?>, 0)"
                                        title="Desactivar">
                                    <i class="fas fa-ban"></i>
                                </button>
                            <?php else: ?>
                                <button class="btn btn-success" onclick="cambiarEstado(<?php echo $usuario['id']; ?>, 1)"
                                        title="Activar">
                                    <i class="fas fa-check"></i>
                                </button>
                            <?php endif; ?>
                            <button class="btn btn-danger" onclick="eliminar(<?php echo $usuario['id']; ?>, '<?php echo htmlspecialchars($usuario['nombre']); ?>')"
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
                <a class="page-link" href="?pagina=<?php echo $pagina - 1; ?>&q=<?php echo urlencode($buscar); ?>&activo=<?php echo $filtro_activo; ?>&orden=<?php echo $orden; ?>">
                    Anterior
                </a>
            </li>
        <?php endif; ?>

        <?php for ($i = max(1, $pagina - 3); $i <= min($total_paginas, $pagina + 3); $i++): ?>
            <li class="page-item <?php echo $i === $pagina ? 'active' : ''; ?>">
                <a class="page-link" href="?pagina=<?php echo $i; ?>&q=<?php echo urlencode($buscar); ?>&activo=<?php echo $filtro_activo; ?>&orden=<?php echo $orden; ?>">
                    <?php echo $i; ?>
                </a>
            </li>
        <?php endfor; ?>

        <?php if ($pagina < $total_paginas): ?>
            <li class="page-item">
                <a class="page-link" href="?pagina=<?php echo $pagina + 1; ?>&q=<?php echo urlencode($buscar); ?>&activo=<?php echo $filtro_activo; ?>&orden=<?php echo $orden; ?>">
                    Siguiente
                </a>
            </li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<script>
function cambiarEstado(id, nuevoEstado) {
    if (!confirm('¿Estás seguro de cambiar el estado de este usuario?')) return;

    fetch('/admin/ajax/cambiar_estado_usuario.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id: id,
            activo: nuevoEstado,
            csrf_token: CSRF_TOKEN
        })
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

function eliminar(id, nombre) {
    if (!confirm(`¿ELIMINAR al usuario "${nombre}"?\n\nEsta acción eliminará también todos sus productos y NO se puede deshacer.`)) return;

    fetch('/admin/ajax/eliminar_usuario.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id: id,
            csrf_token: CSRF_TOKEN
        })
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
