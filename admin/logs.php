<?php
$titulo = "Logs de Actividad";
require_once 'header.php';

// Obtener conexión a BD
$db = getDB();

// Filtros
$filtro_accion = $_GET['accion'] ?? '';
$filtro_tabla = $_GET['tabla'] ?? '';
$filtro_fecha = $_GET['fecha'] ?? '';

// Paginación
$por_pagina = 50;
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina - 1) * $por_pagina;

// Construir query
$where = [];
$params = [];

if ($filtro_accion) {
    $where[] = "accion = ?";
    $params[] = $filtro_accion;
}

if ($filtro_tabla) {
    $where[] = "tabla_afectada = ?";
    $params[] = $filtro_tabla;
}

if ($filtro_fecha) {
    $where[] = "DATE(fecha) = ?";
    $params[] = $filtro_fecha;
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Obtener total
$stmt = $db->prepare("SELECT COUNT(*) as total FROM log_acciones $where_sql");
$stmt->execute($params);
$total = $stmt->fetch()['total'];
$total_paginas = ceil($total / $por_pagina);

// Obtener logs
$stmt = $db->prepare("
    SELECT l.*,
           a.nombre as admin_nombre
    FROM log_acciones l
    LEFT JOIN administradores a ON a.id = l.usuario_id
    $where_sql
    ORDER BY l.fecha DESC
    LIMIT $por_pagina OFFSET $offset
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Obtener tipos de acciones únicas
$acciones = $db->query("SELECT DISTINCT accion FROM log_acciones ORDER BY accion")->fetchAll(PDO::FETCH_COLUMN);

// Obtener tablas afectadas únicas
$tablas = $db->query("SELECT DISTINCT tabla_afectada FROM log_acciones WHERE tabla_afectada IS NOT NULL ORDER BY tabla_afectada")->fetchAll(PDO::FETCH_COLUMN);
?>

<!-- Filtros -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="stat-card">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Acción</label>
                    <select name="accion" class="form-select">
                        <option value="">Todas las acciones</option>
                        <?php foreach ($acciones as $accion): ?>
                            <option value="<?php echo htmlspecialchars($accion); ?>"
                                    <?php echo $filtro_accion === $accion ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $accion))); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Tabla Afectada</label>
                    <select name="tabla" class="form-select">
                        <option value="">Todas las tablas</option>
                        <?php foreach ($tablas as $tabla): ?>
                            <option value="<?php echo htmlspecialchars($tabla); ?>"
                                    <?php echo $filtro_tabla === $tabla ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($tabla); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Fecha</label>
                    <input type="date" name="fecha" class="form-control"
                           value="<?php echo htmlspecialchars($filtro_fecha); ?>"
                           max="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="fas fa-filter me-2"></i>Filtrar
                        </button>
                        <a href="/admin/logs.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Estadísticas -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-card-icon" style="background: rgba(255,107,26,0.1); color: var(--primary);">
                <i class="fas fa-history"></i>
            </div>
            <div class="stat-card-title">Total Eventos</div>
            <div class="stat-card-value"><?php echo formatearNumero($total); ?></div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-card-icon" style="background: rgba(23,162,184,0.1); color: #17a2b8;">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-card-title">Hoy</div>
            <div class="stat-card-value">
                <?php
                $stmt = $db->query("SELECT COUNT(*) as total FROM log_acciones WHERE DATE(fecha) = CURDATE()");
                echo formatearNumero($stmt->fetch()['total']);
                ?>
            </div>
        </div>
    </div>
</div>

<!-- Timeline de logs -->
<div class="stat-card">
    <h5 class="mb-4"><i class="fas fa-list me-2 text-primary"></i>Historial de Actividades</h5>

    <?php if (empty($logs)): ?>
        <div class="text-center py-5">
            <i class="fas fa-inbox" style="font-size: 64px; color: #dee2e6;"></i>
            <p class="text-muted mt-3">No hay registros con estos filtros</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th style="width: 60px;">ID</th>
                        <th style="width: 160px;">Fecha/Hora</th>
                        <th style="width: 150px;">Administrador</th>
                        <th style="width: 180px;">Acción</th>
                        <th style="width: 120px;">Tabla</th>
                        <th style="width: 80px;">Registro</th>
                        <th>Detalles</th>
                        <th style="width: 120px;">IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><small class="text-muted">#<?php echo $log['id']; ?></small></td>
                            <td>
                                <small>
                                    <?php echo date('d/m/Y', strtotime($log['fecha'])); ?><br>
                                    <strong><?php echo date('H:i:s', strtotime($log['fecha'])); ?></strong>
                                </small>
                            </td>
                            <td>
                                <small>
                                    <?php echo htmlspecialchars($log['admin_nombre'] ?? 'Sistema'); ?>
                                </small>
                            </td>
                            <td>
                                <?php
                                $badge_color = match(true) {
                                    str_contains($log['accion'], 'eliminar') => 'danger',
                                    str_contains($log['accion'], 'crear') || str_contains($log['accion'], 'activar') => 'success',
                                    str_contains($log['accion'], 'actualizar') || str_contains($log['accion'], 'editar') => 'warning',
                                    str_contains($log['accion'], 'desactivar') => 'secondary',
                                    default => 'info'
                                };
                                ?>
                                <span class="badge bg-<?php echo $badge_color; ?>">
                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $log['accion']))); ?>
                                </span>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <?php echo $log['tabla_afectada'] ? htmlspecialchars($log['tabla_afectada']) : '-'; ?>
                                </small>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <?php echo $log['registro_id'] ? '#' . $log['registro_id'] : '-'; ?>
                                </small>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <?php echo $log['detalles'] ? htmlspecialchars($log['detalles']) : '-'; ?>
                                </small>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <code><?php echo htmlspecialchars($log['ip'] ?? 'N/A'); ?></code>
                                </small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Paginación -->
<?php if ($total_paginas > 1): ?>
<nav class="mt-4">
    <ul class="pagination justify-content-center">
        <?php if ($pagina > 1): ?>
            <li class="page-item">
                <a class="page-link" href="?pagina=<?php echo $pagina - 1; ?>&accion=<?php echo urlencode($filtro_accion); ?>&tabla=<?php echo urlencode($filtro_tabla); ?>&fecha=<?php echo $filtro_fecha; ?>">
                    Anterior
                </a>
            </li>
        <?php endif; ?>

        <?php for ($i = max(1, $pagina - 3); $i <= min($total_paginas, $pagina + 3); $i++): ?>
            <li class="page-item <?php echo $i === $pagina ? 'active' : ''; ?>">
                <a class="page-link" href="?pagina=<?php echo $i; ?>&accion=<?php echo urlencode($filtro_accion); ?>&tabla=<?php echo urlencode($filtro_tabla); ?>&fecha=<?php echo $filtro_fecha; ?>">
                    <?php echo $i; ?>
                </a>
            </li>
        <?php endfor; ?>

        <?php if ($pagina < $total_paginas): ?>
            <li class="page-item">
                <a class="page-link" href="?pagina=<?php echo $pagina + 1; ?>&accion=<?php echo urlencode($filtro_accion); ?>&tabla=<?php echo urlencode($filtro_tabla); ?>&fecha=<?php echo $filtro_fecha; ?>">
                    Siguiente
                </a>
            </li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<?php require_once 'footer.php'; ?>
