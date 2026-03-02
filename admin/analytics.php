<?php
$titulo = "Analíticas";
require_once 'header.php';

// Obtener conexión a BD
$db = getDB();

// Rango de fechas
$fecha_inicio = $_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'));
$fecha_fin = $_GET['hasta'] ?? date('Y-m-d');

// Métricas generales del período
$stats = [];

// Total visitas en el período
$stmt = $db->prepare("
    SELECT COUNT(*) as total
    FROM visitas
    WHERE DATE(fecha_visita) BETWEEN ? AND ?
");
$stmt->execute([$fecha_inicio, $fecha_fin]);
$stats['visitas_periodo'] = $stmt->fetch()['total'];

// Total búsquedas
$stmt = $db->prepare("
    SELECT COUNT(*) as total
    FROM busquedas
    WHERE DATE(fecha_busqueda) BETWEEN ? AND ?
");
$stmt->execute([$fecha_inicio, $fecha_fin]);
$stats['busquedas_periodo'] = $stmt->fetch()['total'];

// Nuevos registros
$stmt = $db->prepare("
    SELECT COUNT(*) as total
    FROM usuarios
    WHERE DATE(fecha_registro) BETWEEN ? AND ?
");
$stmt->execute([$fecha_inicio, $fecha_fin]);
$stats['registros_periodo'] = $stmt->fetch()['total'];

// Productos publicados
$stmt = $db->prepare("
    SELECT COUNT(*) as total
    FROM productos
    WHERE DATE(fecha_publicacion) BETWEEN ? AND ?
");
$stmt->execute([$fecha_inicio, $fecha_fin]);
$stats['productos_periodo'] = $stmt->fetch()['total'];

// Gráfico de visitas diarias
$stmt = $db->prepare("
    SELECT DATE(fecha_visita) as fecha, COUNT(*) as total
    FROM visitas
    WHERE DATE(fecha_visita) BETWEEN ? AND ?
    GROUP BY DATE(fecha_visita)
    ORDER BY fecha ASC
");
$stmt->execute([$fecha_inicio, $fecha_fin]);
$visitas_diarias = $stmt->fetchAll();

// Top categorías más buscadas
$stmt = $db->prepare("
    SELECT c.nombre, COUNT(*) as busquedas
    FROM busquedas b
    INNER JOIN categorias c ON b.termino LIKE CONCAT('%', c.nombre, '%')
    WHERE DATE(b.fecha_busqueda) BETWEEN ? AND ?
    GROUP BY c.id
    ORDER BY busquedas DESC
    LIMIT 5
");
$stmt->execute([$fecha_inicio, $fecha_fin]);
$categorias_populares = $stmt->fetchAll();

// Conversión: registros que publicaron
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT u.id) as total
    FROM usuarios u
    INNER JOIN productos p ON p.usuario_id = u.id
    WHERE DATE(u.fecha_registro) BETWEEN ? AND ?
    AND DATE(p.fecha_publicacion) <= DATE_ADD(u.fecha_registro, INTERVAL 7 DAY)
");
$stmt->execute([$fecha_inicio, $fecha_fin]);
$conversion_publicacion = $stmt->fetch()['total'];

$tasa_conversion = $stats['registros_periodo'] > 0
    ? round(($conversion_publicacion / $stats['registros_periodo']) * 100, 1)
    : 0;

// Actividad por hora del día
$stmt = $db->prepare("
    SELECT HOUR(fecha_visita) as hora, COUNT(*) as visitas
    FROM visitas
    WHERE DATE(fecha_visita) BETWEEN ? AND ?
    GROUP BY HOUR(fecha_visita)
    ORDER BY hora ASC
");
$stmt->execute([$fecha_inicio, $fecha_fin]);
$actividad_horas = $stmt->fetchAll();

// Crear array de 24 horas (0-23)
$horas_array = array_fill(0, 24, 0);
foreach ($actividad_horas as $act) {
    $horas_array[$act['hora']] = $act['visitas'];
}
?>

<!-- Filtro de fechas -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="stat-card">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label"><i class="fas fa-calendar me-2"></i>Desde</label>
                    <input type="date" name="desde" class="form-control"
                           value="<?php echo $fecha_inicio; ?>" max="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label"><i class="fas fa-calendar me-2"></i>Hasta</label>
                    <input type="date" name="hasta" class="form-control"
                           value="<?php echo $fecha_fin; ?>" max="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-2"></i>Filtrar
                    </button>
                </div>
                <div class="col-md-4">
                    <div class="btn-group w-100">
                        <a href="?desde=<?php echo date('Y-m-d'); ?>&hasta=<?php echo date('Y-m-d'); ?>"
                           class="btn btn-outline-secondary">Hoy</a>
                        <a href="?desde=<?php echo date('Y-m-d', strtotime('-7 days')); ?>&hasta=<?php echo date('Y-m-d'); ?>"
                           class="btn btn-outline-secondary">7 días</a>
                        <a href="?desde=<?php echo date('Y-m-d', strtotime('-30 days')); ?>&hasta=<?php echo date('Y-m-d'); ?>"
                           class="btn btn-outline-secondary">30 días</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Métricas del período -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-card-icon" style="background: rgba(23,162,184,0.1); color: #17a2b8;">
                <i class="fas fa-eye"></i>
            </div>
            <div class="stat-card-title">Visitas</div>
            <div class="stat-card-value"><?php echo formatearNumero($stats['visitas_periodo']); ?></div>
            <div class="stat-card-change">
                <i class="fas fa-calendar"></i>
                En el período
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-card-icon" style="background: rgba(255,193,7,0.1); color: #ffc107;">
                <i class="fas fa-search"></i>
            </div>
            <div class="stat-card-title">Búsquedas</div>
            <div class="stat-card-value"><?php echo formatearNumero($stats['busquedas_periodo']); ?></div>
            <div class="stat-card-change">
                <i class="fas fa-calendar"></i>
                En el período
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-card-icon" style="background: rgba(255,107,26,0.1); color: var(--primary);">
                <i class="fas fa-user-plus"></i>
            </div>
            <div class="stat-card-title">Nuevos Usuarios</div>
            <div class="stat-card-value"><?php echo formatearNumero($stats['registros_periodo']); ?></div>
            <div class="stat-card-change">
                <i class="fas fa-calendar"></i>
                En el período
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-card-icon" style="background: rgba(40,167,69,0.1); color: #28a745;">
                <i class="fas fa-box"></i>
            </div>
            <div class="stat-card-title">Nuevos Productos</div>
            <div class="stat-card-value"><?php echo formatearNumero($stats['productos_periodo']); ?></div>
            <div class="stat-card-change">
                <i class="fas fa-calendar"></i>
                En el período
            </div>
        </div>
    </div>
</div>

<!-- Gráficos -->
<div class="row g-4 mb-4">
    <div class="col-md-12">
        <div class="stat-card">
            <h5 class="mb-4"><i class="fas fa-chart-area me-2 text-primary"></i>Visitas Diarias</h5>
            <canvas id="chartVisitasDiarias" height="80"></canvas>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-12">
        <div class="stat-card">
            <h5 class="mb-4"><i class="fas fa-clock me-2 text-info"></i>Actividad por Hora del Día</h5>
            <canvas id="chartActividadHoras" height="80"></canvas>
        </div>
    </div>
</div>

<!-- Métricas adicionales -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="stat-card">
            <h5 class="mb-3"><i class="fas fa-chart-pie me-2 text-success"></i>Conversión a Publicación</h5>
            <div class="text-center py-4">
                <div style="font-size: 48px; font-weight: 700; color: #28a745;">
                    <?php echo $tasa_conversion; ?>%
                </div>
                <p class="text-muted mb-0">
                    <?php echo $conversion_publicacion; ?> de <?php echo $stats['registros_periodo']; ?> usuarios
                    publicaron en los primeros 7 días
                </p>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="stat-card">
            <h5 class="mb-3"><i class="fas fa-fire me-2 text-danger"></i>Categorías Más Buscadas</h5>
            <div class="table-responsive">
                <table class="table table-sm">
                    <tbody>
                        <?php if (!empty($categorias_populares)): ?>
                            <?php foreach ($categorias_populares as $cat): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($cat['nombre']); ?></td>
                                    <td class="text-end">
                                        <span class="badge bg-danger"><?php echo $cat['busquedas']; ?> búsquedas</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="2" class="text-center text-muted">
                                    <i class="fas fa-info-circle me-2"></i>No hay datos en este período
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Gráfico de visitas diarias
const ctxVisitasDiarias = document.getElementById('chartVisitasDiarias').getContext('2d');
new Chart(ctxVisitasDiarias, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($visitas_diarias, 'fecha')); ?>,
        datasets: [{
            label: 'Visitas',
            data: <?php echo json_encode(array_column($visitas_diarias, 'total')); ?>,
            borderColor: '#17a2b8',
            backgroundColor: 'rgba(23, 162, 184, 0.1)',
            tension: 0.4,
            fill: true,
            pointRadius: 4,
            pointHoverRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: { beginAtZero: true }
        }
    }
});

// Gráfico de actividad por horas
const ctxActividadHoras = document.getElementById('chartActividadHoras').getContext('2d');
new Chart(ctxActividadHoras, {
    type: 'bar',
    data: {
        labels: ['00h', '01h', '02h', '03h', '04h', '05h', '06h', '07h', '08h', '09h', '10h', '11h',
                 '12h', '13h', '14h', '15h', '16h', '17h', '18h', '19h', '20h', '21h', '22h', '23h'],
        datasets: [{
            label: 'Visitas',
            data: <?php echo json_encode(array_values($horas_array)); ?>,
            backgroundColor: '#ffc107',
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: { beginAtZero: true }
        }
    }
});
</script>

<?php require_once 'footer.php'; ?>
