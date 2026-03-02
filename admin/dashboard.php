<?php
$titulo = "Dashboard";
require_once 'header.php';

$stats = getEstadisticasGenerales();
$grafico_registros = getGraficoRegistros();
$grafico_productos = getGraficoProductos();
$grafico_visitas = getGraficoVisitas();
$busquedas_populares = getBusquedasPopulares(5);
$productos_vistos = getProductosMasVistos(5);
$usuarios_activos = getUsuariosMasActivos(5);
?>

<!-- Tarjetas de estadísticas -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-card-icon" style="background: rgba(255,107,26,0.1); color: var(--primary);">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-card-title">Total Usuarios</div>
            <div class="stat-card-value"><?php echo formatearNumero($stats['total_usuarios']); ?></div>
            <div class="stat-card-change positive">
                <i class="fas fa-arrow-up"></i>
                <?php echo $stats['usuarios_hoy']; ?> nuevos hoy
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-card-icon" style="background: rgba(40,167,69,0.1); color: #28a745;">
                <i class="fas fa-box"></i>
            </div>
            <div class="stat-card-title">Productos Activos</div>
            <div class="stat-card-value"><?php echo formatearNumero($stats['productos_activos']); ?></div>
            <div class="stat-card-change positive">
                <i class="fas fa-arrow-up"></i>
                <?php echo $stats['productos_hoy']; ?> nuevos hoy
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-card-icon" style="background: rgba(23,162,184,0.1); color: #17a2b8;">
                <i class="fas fa-eye"></i>
            </div>
            <div class="stat-card-title">Visitas Hoy</div>
            <div class="stat-card-value"><?php echo formatearNumero($stats['visitas_hoy']); ?></div>
            <div class="stat-card-change">
                <i class="fas fa-chart-line"></i>
                Tráfico del día
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-card-icon" style="background: rgba(220,53,69,0.1); color: #dc3545;">
                <i class="fas fa-flag"></i>
            </div>
            <div class="stat-card-title">Reportes Pendientes</div>
            <div class="stat-card-value"><?php echo formatearNumero($stats['reportes_pendientes']); ?></div>
            <div class="stat-card-change <?php echo $stats['reportes_pendientes'] > 0 ? 'negative' : ''; ?>">
                <i class="fas fa-exclamation-triangle"></i>
                Requieren atención
            </div>
        </div>
    </div>
</div>

<!-- Gráficos -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="stat-card">
            <h5 class="mb-4"><i class="fas fa-chart-line me-2 text-primary"></i>Registros (últimos 30 días)</h5>
            <canvas id="chartRegistros" height="250"></canvas>
        </div>
    </div>

    <div class="col-md-6">
        <div class="stat-card">
            <h5 class="mb-4"><i class="fas fa-chart-bar me-2 text-success"></i>Productos Publicados (últimos 30 días)</h5>
            <canvas id="chartProductos" height="250"></canvas>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-12">
        <div class="stat-card">
            <h5 class="mb-4"><i class="fas fa-chart-area me-2 text-info"></i>Visitas (últimos 7 días)</h5>
            <canvas id="chartVisitas" height="100"></canvas>
        </div>
    </div>
</div>

<!-- Tablas -->
<div class="row g-4">
    <div class="col-md-4">
        <div class="stat-card">
            <h5 class="mb-3"><i class="fas fa-search me-2 text-primary"></i>Búsquedas Populares</h5>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Término</th>
                            <th class="text-end">Veces</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($busquedas_populares as $busqueda): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($busqueda['termino']); ?></td>
                                <td class="text-end">
                                    <span class="badge bg-primary"><?php echo $busqueda['veces']; ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="stat-card">
            <h5 class="mb-3"><i class="fas fa-fire me-2 text-danger"></i>Productos Más Vistos</h5>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th class="text-end">Visitas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productos_vistos as $producto): ?>
                            <tr>
                                <td>
                                    <small><?php echo htmlspecialchars(substr($producto['titulo'], 0, 30)); ?>...</small>
                                </td>
                                <td class="text-end">
                                    <span class="badge bg-danger"><?php echo $producto['visitas']; ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="stat-card">
            <h5 class="mb-3"><i class="fas fa-star me-2 text-warning"></i>Usuarios Más Activos</h5>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th class="text-end">Publicaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios_activos as $usuario): ?>
                            <tr>
                                <td>
                                    <small><?php echo htmlspecialchars($usuario['nombre']); ?></small>
                                </td>
                                <td class="text-end">
                                    <span class="badge bg-warning text-dark"><?php echo $usuario['publicaciones']; ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Configuración de Chart.js
Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
Chart.defaults.color = '#6c757d';

// Gráfico de Registros
const ctxRegistros = document.getElementById('chartRegistros').getContext('2d');
new Chart(ctxRegistros, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($grafico_registros, 'fecha')); ?>,
        datasets: [{
            label: 'Registros',
            data: <?php echo json_encode(array_column($grafico_registros, 'total')); ?>,
            borderColor: '#ff6b1a',
            backgroundColor: 'rgba(255, 107, 26, 0.1)',
            tension: 0.4,
            fill: true
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

// Gráfico de Productos
const ctxProductos = document.getElementById('chartProductos').getContext('2d');
new Chart(ctxProductos, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($grafico_productos, 'fecha')); ?>,
        datasets: [{
            label: 'Productos',
            data: <?php echo json_encode(array_column($grafico_productos, 'total')); ?>,
            backgroundColor: '#28a745',
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

// Gráfico de Visitas
const ctxVisitas = document.getElementById('chartVisitas').getContext('2d');
new Chart(ctxVisitas, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($grafico_visitas, 'fecha')); ?>,
        datasets: [{
            label: 'Visitas',
            data: <?php echo json_encode(array_column($grafico_visitas, 'total')); ?>,
            borderColor: '#17a2b8',
            backgroundColor: 'rgba(23, 162, 184, 0.1)',
            tension: 0.4,
            fill: true
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
