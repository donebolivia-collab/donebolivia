<?php
$titulo = "Reportes de Tiendas";
require_once 'header.php';

// Filtros
$buscar = $_GET['q'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';
$filtro_motivo = $_GET['motivo'] ?? '';
$orden = $_GET['orden'] ?? 'recientes';

// Paginación
$por_pagina = 20;
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina - 1) * $por_pagina;

// Construir query
$where = [];
$params = [];

if ($buscar) {
    // Buscar por nombre de tienda, slug o ID de reporte
    $where[] = "(t.nombre LIKE ? OR t.slug LIKE ? OR d.id = ?)";
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
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM denuncias_tiendas d 
        JOIN tiendas t ON d.tienda_id = t.id
        $where_sql
    ");
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
} catch (Exception $e) {
    // Si la tabla no existe, manejar gracefully
    $total = 0;
    $error_tabla = true;
}

$total_paginas = ceil($total / $por_pagina);

// Orden
$order_sql = match($orden) {
    'antiguos' => 'd.fecha_denuncia ASC',
    default => 'd.fecha_denuncia DESC'
};

$reportes = [];
if (!isset($error_tabla) && $total > 0) {
    // Obtener reportes
    $stmt = $db->prepare("
        SELECT d.*,
               t.nombre as tienda_nombre,
               t.slug as tienda_slug,
               t.usuario_id as tienda_owner_id,
               u.nombre as reportante_nombre,
               u.email as reportante_email,
               v.nombre as vendedor_nombre,
               v.email as vendedor_email
        FROM denuncias_tiendas d
        JOIN tiendas t ON d.tienda_id = t.id
        LEFT JOIN usuarios u ON d.usuario_reporta_id = u.id
        LEFT JOIN usuarios v ON t.usuario_id = v.id
        $where_sql
        ORDER BY $order_sql
        LIMIT $por_pagina OFFSET $offset
    ");
    $stmt->execute($params);
    $reportes = $stmt->fetchAll();
}

// Estadísticas rápidas
$stats = ['total' => 0, 'pendientes' => 0, 'revisados' => 0, 'resueltos' => 0];
if (!isset($error_tabla)) {
    $stmt = $db->query("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
            SUM(CASE WHEN estado = 'revisado' THEN 1 ELSE 0 END) as revisados,
            SUM(CASE WHEN estado = 'sancionado' THEN 1 ELSE 0 END) + SUM(CASE WHEN estado = 'descartado' THEN 1 ELSE 0 END) as resueltos
        FROM denuncias_tiendas
    ");
    $stats = $stmt->fetch();
}

// Motivos disponibles
$motivos = [
    'fraude_estafa' => 'Posible fraude o estafa',
    'productos_prohibidos' => 'Venta de productos prohibidos',
    'suplantacion' => 'Suplantación de identidad',
    'contenido_inapropiado' => 'Contenido inapropiado',
    'spam' => 'Spam o información falsa',
    'otro' => 'Otro motivo'
];
?>

<!-- Estadísticas rápidas -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-card-icon" style="background: rgba(255,107,26,0.1); color: var(--primary);">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="stat-card-title">Total Reportes</div>
            <div class="stat-card-value"><?php echo formatearNumero($stats['total'] ?? 0); ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-card-icon" style="background: rgba(255,193,7,0.1); color: #ffc107;">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-card-title">Pendientes</div>
            <div class="stat-card-value"><?php echo formatearNumero($stats['pendientes'] ?? 0); ?></div>
        </div>
    </div>
    <!-- Agrega más stats si quieres -->
</div>

<!-- Filtros y búsqueda -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="stat-card">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="q" class="form-control"
                           placeholder="Buscar por tienda o ID..."
                           value="<?php echo htmlspecialchars($buscar); ?>">
                </div>
                <div class="col-md-2">
                    <select name="estado" class="form-select">
                        <option value="">Todos los estados</option>
                        <option value="pendiente" <?php echo $filtro_estado === 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                        <option value="revisado" <?php echo $filtro_estado === 'revisado' ? 'selected' : ''; ?>>Revisado</option>
                        <option value="sancionado" <?php echo $filtro_estado === 'sancionado' ? 'selected' : ''; ?>>Sancionado</option>
                        <option value="descartado" <?php echo $filtro_estado === 'descartado' ? 'selected' : ''; ?>>Descartado</option>
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
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-2"></i>Buscar
                    </button>
                </div>
                <div class="col-md-1">
                    <a href="/admin/store_reports.php" class="btn btn-secondary w-100">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if (isset($error_tabla)): ?>
    <div class="alert alert-info">
        No hay reportes de tiendas aún (la tabla no ha sido creada).
    </div>
<?php else: ?>

<!-- Tabla de reportes -->
<div class="admin-table">
    <table class="table table-hover">
        <thead>
            <tr>
                <th>ID</th>
                <th>Tienda Reportada</th>
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
                            <a href="/tienda/<?php echo htmlspecialchars($reporte['tienda_slug']); ?>"
                               target="_blank"
                               class="text-decoration-none">
                                <strong><?php echo htmlspecialchars($reporte['tienda_nombre']); ?></strong>
                            </a>
                            <br>
                            <small class="text-muted">
                                Propietario: <?php echo htmlspecialchars($reporte['vendedor_nombre']); ?>
                            </small>
                        </div>
                    </td>
                    <td>
                        <span class="badge bg-warning text-dark">
                            <?php echo $motivos[$reporte['motivo']] ?? $reporte['motivo']; ?>
                        </span>
                    </td>
                    <td>
                        <div>
                            <?php if ($reporte['reportante_nombre']): ?>
                                <?php echo htmlspecialchars($reporte['reportante_nombre']); ?>
                                <br>
                                <small class="text-muted"><?php echo htmlspecialchars($reporte['reportante_email']); ?></small>
                            <?php else: ?>
                                <span class="text-muted fst-italic">Anónimo</span>
                            <?php endif; ?>
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
                            'sancionado' => 'bg-danger',
                            'descartado' => 'bg-secondary',
                            default => 'bg-secondary'
                        };
                        ?>
                        <span class="badge <?php echo $badge_class; ?>">
                            <?php echo ucfirst($reporte['estado']); ?>
                        </span>
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <a href="/admin/store_manage.php?id=<?php echo $reporte['tienda_id']; ?>" class="btn btn-dark" target="_blank" title="Gestión Avanzada (Cockpit)">
                                <i class="fas fa-cogs"></i>
                            </a>
                            
                            <?php if ($reporte['estado'] === 'pendiente'): ?>
                                <button class="btn btn-primary" onclick="cambiarEstadoReporte(<?php echo $reporte['id']; ?>, 'revisado')" title="Marcar Revisado">
                                    <i class="fas fa-glasses"></i>
                                </button>
                            <?php endif; ?>

                            <?php if ($reporte['estado'] !== 'sancionado' && $reporte['estado'] !== 'descartado'): ?>
                                <button class="btn btn-danger" onclick="sancionarTienda(<?php echo $reporte['id']; ?>, '<?php echo htmlspecialchars(addslashes($reporte['tienda_nombre'])); ?>')" title="Sancionar / Suspender">
                                    <i class="fas fa-gavel"></i>
                                </button>
                                <button class="btn btn-secondary" onclick="cambiarEstadoReporte(<?php echo $reporte['id']; ?>, 'descartado')" title="Descartar Reporte">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            <?php endif; ?>
                            <!-- Nuevo: Ver productos para cirugía específica -->
                            <a href="/admin/products.php?q=<?php echo urlencode($reporte['tienda_nombre']); ?>" class="btn btn-warning text-dark" target="_blank" title="Revisar Productos">
                                <i class="fas fa-box-open"></i>
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
        <!-- (Simplificado, igual que en otros archivos) -->
        <?php for ($i = max(1, $pagina - 3); $i <= min($total_paginas, $pagina + 3); $i++): ?>
            <li class="page-item <?php echo $i === $pagina ? 'active' : ''; ?>">
                <a class="page-link" href="?pagina=<?php echo $i; ?>&q=<?php echo urlencode($buscar); ?>&estado=<?php echo $filtro_estado; ?>">
                    <?php echo $i; ?>
                </a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?php endif; // End if !error_tabla ?>

<script>
function cambiarEstadoReporte(id, nuevoEstado, accionAdicional = null) {
    // Si no es sanción, usar confirmación simple
    if (nuevoEstado !== 'sancionado' && !confirm('¿Cambiar el estado de este reporte a ' + nuevoEstado.toUpperCase() + '?')) return;

    const data = {
        id: id,
        estado: nuevoEstado,
        csrf_token: CSRF_TOKEN
    };

    if (accionAdicional) {
        data.accion_adicional = accionAdicional;
    }

    fetch('/admin/ajax/cambiar_estado_reporte_tienda.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (data.message) alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function sancionarTienda(id, nombreTienda) {
    // Menú de decisión estilo "Mano Dura"
    const opcion = prompt(
        `SANCIONAR TIENDA: "${nombreTienda}"\n\n` +
        `Escribe el NÚMERO de la acción a tomar:\n` +
        `1. Solo marcar reporte como sancionado (Advertencia)\n` +
        `2. SUSPENDER TIENDA COMPLETAMENTE (La tienda dejará de ser visible)\n\n` +
        `Escribe 1 o 2:`
    );

    if (opcion === '1') {
        cambiarEstadoReporte(id, 'sancionado');
    } else if (opcion === '2') {
        if (confirm('¿ESTÁS SEGURO? Esta acción bloqueará el acceso público a la tienda inmediatamente.')) {
            cambiarEstadoReporte(id, 'sancionado', 'suspender_tienda');
        }
    }
}
</script>

<?php require_once 'footer.php'; ?>