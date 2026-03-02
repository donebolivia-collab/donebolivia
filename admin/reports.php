<?php
$titulo = "Reportes de Productos";
require_once 'header.php';

// Filtros
$buscar = $_GET['q'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';
$filtro_motivo = $_GET['motivo'] ?? '';
$orden = $_GET['orden'] ?? 'recientes';

// Paginaci�n
$por_pagina = 20;
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina - 1) * $por_pagina;

// Construir query
$where = [];
$params = [];

if ($buscar) {
    $where[] = "(p.titulo LIKE ? OR u.nombre LIKE ? OR p.id = ?)";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
    $params[] = $buscar;
}

if ($filtro_estado !== '') {
    $where[] = "d.estado = ?";
    $params[] = $filtro_estado;
}

if ($filtro_motivo) {
    $where[] = "d.motivo = ?";
    $params[] = $filtro_motivo;
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Obtener total
$db = getDB();
$stmt = $db->prepare("SELECT COUNT(*) as total FROM denuncias d $where_sql");
$stmt->execute($params);
$total = $stmt->fetch()['total'];
$total_paginas = ceil($total / $por_pagina);

// Orden
$order_sql = match($orden) {
    'antiguos' => 'd.fecha_denuncia ASC',
    default => 'd.fecha_denuncia DESC'
};

// Obtener reportes
$stmt = $db->prepare("
    SELECT d.*,
           p.titulo as producto_titulo,
           p.precio as producto_precio,
           p.activo as producto_activo,
           u.nombre as reportante_nombre,
           u.email as reportante_email,
           v.nombre as vendedor_nombre,
           v.email as vendedor_email,
           a.nombre as admin_nombre
    FROM denuncias d
    JOIN productos p ON d.producto_id = p.id
    JOIN usuarios u ON d.usuario_reporta_id = u.id
    JOIN usuarios v ON p.usuario_id = v.id
    LEFT JOIN usuarios a ON d.admin_id = a.id
    $where_sql
    ORDER BY $order_sql
    LIMIT $por_pagina OFFSET $offset
");
$stmt->execute($params);
$reportes = $stmt->fetchAll();

// Estad�sticas r�pidas
$stmt = $db->query("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN estado = 'revisado' THEN 1 ELSE 0 END) as revisados,
        SUM(CASE WHEN estado = 'resuelto' THEN 1 ELSE 0 END) as resueltos
    FROM denuncias
");
$stats = $stmt->fetch();

// Motivos disponibles
$motivos = [
    'producto_vendido' => 'Producto vendido',
    'precio_sospechoso' => 'Precio sospechoso',
    'descripcion_enganosa' => 'Descripci�n enga�osa',
    'ubicacion_incorrecta' => 'Ubicaci�n incorrecta',
    'publicacion_duplicada' => 'Publicaci�n duplicada',
    'vendedor_sospechoso' => 'Vendedor sospechoso',
    'contenido_inapropiado' => 'Contenido inapropiado'
];
?>

<!-- Estad�sticas r�pidas -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-card-icon" style="background: rgba(255,107,26,0.1); color: var(--primary);">
                <i class="fas fa-flag"></i>
            </div>
            <div class="stat-card-title">Total Reportes</div>
            <div class="stat-card-value"><?php echo formatearNumero($stats['total']); ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-card-icon" style="background: rgba(255,193,7,0.1); color: #ffc107;">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-card-title">Pendientes</div>
            <div class="stat-card-value"><?php echo formatearNumero($stats['pendientes']); ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-card-icon" style="background: rgba(13,110,253,0.1); color: #0d6efd;">
                <i class="fas fa-eye"></i>
            </div>
            <div class="stat-card-title">Revisados</div>
            <div class="stat-card-value"><?php echo formatearNumero($stats['revisados']); ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-card-icon" style="background: rgba(25,135,84,0.1); color: #198754;">
                <i class="fas fa-check"></i>
            </div>
            <div class="stat-card-title">Resueltos</div>
            <div class="stat-card-value"><?php echo formatearNumero($stats['resueltos']); ?></div>
        </div>
    </div>
</div>

<!-- Filtros y b�squeda -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="stat-card">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="q" class="form-control"
                           placeholder="Buscar por producto, usuario o ID..."
                           value="<?php echo htmlspecialchars($buscar); ?>">
                </div>
                <div class="col-md-2">
                    <select name="estado" class="form-select">
                        <option value="">Todos los estados</option>
                        <option value="pendiente" <?php echo $filtro_estado === 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                        <option value="revisado" <?php echo $filtro_estado === 'revisado' ? 'selected' : ''; ?>>Revisado</option>
                        <option value="resuelto" <?php echo $filtro_estado === 'resuelto' ? 'selected' : ''; ?>>Resuelto</option>
                        <option value="rechazado" <?php echo $filtro_estado === 'rechazado' ? 'selected' : ''; ?>>Rechazado</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="motivo" class="form-select">
                        <option value="">Todos los motivos</option>
                        <?php foreach ($motivos as $key => $valor): ?>
                        <option value="<?php echo $key; ?>" <?php echo $filtro_motivo === $key ? 'selected' : ''; ?>>
                            <?php echo $valor; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="orden" class="form-select">
                        <option value="recientes" <?php echo $orden === 'recientes' ? 'selected' : ''; ?>>M�s recientes</option>
                        <option value="antiguos" <?php echo $orden === 'antiguos' ? 'selected' : ''; ?>>M�s antiguos</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-2"></i>Buscar
                    </button>
                </div>
                <div class="col-md-1">
                    <a href="/admin/reports.php" class="btn btn-secondary w-100">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Tabla de reportes -->
<div class="admin-table">
    <table class="table table-hover">
        <thead>
            <tr>
                <th>ID</th>
                <th>Producto</th>
                <th>Motivo</th>
                <th>Reportante</th>
                <th>Fecha</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($reportes as $reporte): ?>
                <tr>
                    <td><strong>#<?php echo $reporte['id']; ?></strong></td>
                    <td>
                        <div>
                            <a href="/products/view_product.php?id=<?php echo $reporte['producto_id']; ?>"
                               target="_blank"
                               class="text-decoration-none">
                                <strong><?php echo htmlspecialchars($reporte['producto_titulo']); ?></strong>
                            </a>
                            <br>
                            <small class="text-muted">
                                ID: <?php echo $reporte['producto_id']; ?> |
                                <?php echo formatearPrecio($reporte['producto_precio']); ?>
                                <?php if (!$reporte['producto_activo']): ?>
                                    <span class="badge bg-secondary ms-1">Inactivo</span>
                                <?php endif; ?>
                            </small>
                        </div>
                    </td>
                    <td>
                        <span class="badge bg-info">
                            <?php echo $motivos[$reporte['motivo']] ?? $reporte['motivo']; ?>
                        </span>
                    </td>
                    <td>
                        <div>
                            <?php echo htmlspecialchars($reporte['reportante_nombre']); ?>
                            <br>
                            <small class="text-muted"><?php echo htmlspecialchars($reporte['reportante_email']); ?></small>
                        </div>
                    </td>
                    <td>
                        <small>
                            <?php echo date('d/m/Y', strtotime($reporte['fecha_denuncia'])); ?>
                            <br>
                            <span class="text-muted"><?php echo date('H:i', strtotime($reporte['fecha_denuncia'])); ?></span>
                        </small>
                    </td>
                    <td>
                        <?php
                        $badge_class = match($reporte['estado']) {
                            'pendiente' => 'bg-warning',
                            'revisado' => 'bg-info',
                            'resuelto' => 'bg-success',
                            'rechazado' => 'bg-secondary',
                            default => 'bg-secondary'
                        };
                        ?>
                        <span class="badge <?php echo $badge_class; ?>">
                            <?php echo ucfirst($reporte['estado']); ?>
                        </span>
                        <?php if ($reporte['admin_nombre']): ?>
                            <br><small class="text-muted">por <?php echo htmlspecialchars($reporte['admin_nombre']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-info"
                                    onclick="verDetalle(<?php echo $reporte['id']; ?>)"
                                    title="Ver detalle">
                                <i class="fas fa-eye"></i>
                            </button>
                            <?php if ($reporte['estado'] !== 'resuelto'): ?>
                            <button class="btn btn-success"
                                    onclick="cambiarEstado(<?php echo $reporte['id']; ?>, 'resuelto')"
                                    title="Marcar como resuelto">
                                <i class="fas fa-check"></i>
                            </button>
                            <?php endif; ?>
                            <?php if ($reporte['producto_activo']): ?>
                            <button class="btn btn-danger"
                                    onclick="desactivarProducto(<?php echo $reporte['producto_id']; ?>, <?php echo $reporte['id']; ?>)"
                                    title="Desactivar producto">
                                <i class="fas fa-ban"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Paginaci�n -->
<?php if ($total_paginas > 1): ?>
<nav class="mt-4">
    <ul class="pagination justify-content-center">
        <?php if ($pagina > 1): ?>
            <li class="page-item">
                <a class="page-link" href="?pagina=<?php echo $pagina - 1; ?>&q=<?php echo urlencode($buscar); ?>&estado=<?php echo $filtro_estado; ?>&motivo=<?php echo $filtro_motivo; ?>&orden=<?php echo $orden; ?>">
                    Anterior
                </a>
            </li>
        <?php endif; ?>

        <?php for ($i = max(1, $pagina - 3); $i <= min($total_paginas, $pagina + 3); $i++): ?>
            <li class="page-item <?php echo $i === $pagina ? 'active' : ''; ?>">
                <a class="page-link" href="?pagina=<?php echo $i; ?>&q=<?php echo urlencode($buscar); ?>&estado=<?php echo $filtro_estado; ?>&motivo=<?php echo $filtro_motivo; ?>&orden=<?php echo $orden; ?>">
                    <?php echo $i; ?>
                </a>
            </li>
        <?php endfor; ?>

        <?php if ($pagina < $total_paginas): ?>
            <li class="page-item">
                <a class="page-link" href="?pagina=<?php echo $pagina + 1; ?>&q=<?php echo urlencode($buscar); ?>&estado=<?php echo $filtro_estado; ?>&motivo=<?php echo $filtro_motivo; ?>&orden=<?php echo $orden; ?>">
                    Siguiente
                </a>
            </li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<!-- Modal de detalle -->
<div class="modal fade" id="detalleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle del Reporte</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalleContent">
                <div class="text-center">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
function verDetalle(id) {
    const modal = new bootstrap.Modal(document.getElementById('detalleModal'));
    modal.show();

    fetch('/admin/ajax/detalle_reporte.php?id=' + id)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('detalleContent').innerHTML = data.html;
            } else {
                document.getElementById('detalleContent').innerHTML = '<p class="text-danger">Error al cargar detalle</p>';
            }
        })
        .catch(() => {
            document.getElementById('detalleContent').innerHTML = '<p class="text-danger">Error de conexi�n</p>';
        });
}

function cambiarEstado(id, nuevoEstado) {
    if (!confirm('¿Cambiar el estado de este reporte?')) return;

    fetch('/admin/ajax/cambiar_estado_reporte.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id: id,
            estado: nuevoEstado,
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

function desactivarProducto(productoId, reporteId) {
    if (!confirm('¿DESACTIVAR este producto? Esta acción lo ocultará del sitio.')) return;

    fetch('/admin/ajax/desactivar_producto_reportado.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            producto_id: productoId,
            reporte_id: reporteId,
            csrf_token: CSRF_TOKEN
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Producto desactivado correctamente');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}
</script>

<?php require_once 'footer.php'; ?>
