<?php
$titulo = "Tráfico en Vivo";
require_once 'header.php';

// Obtener conexión
$db = getDB();

// Filtro de fecha
$fecha_filtro = $_GET['fecha'] ?? date('Y-m-d');

// --- ESTADÍSTICAS DEL DÍA ---
// Total visitas hoy
$stmt = $db->prepare("SELECT COUNT(*) FROM traffic_logs WHERE DATE(timestamp) = ?");
$stmt->execute([$fecha_filtro]);
$visitas_hoy = $stmt->fetchColumn();

// Visitantes únicos hoy
$stmt = $db->prepare("SELECT COUNT(DISTINCT ip_address) FROM traffic_logs WHERE DATE(timestamp) = ?");
$stmt->execute([$fecha_filtro]);
$unicos_hoy = $stmt->fetchColumn();

// Top Páginas hoy
$stmt = $db->prepare("
    SELECT request_uri, COUNT(*) as total 
    FROM traffic_logs 
    WHERE DATE(timestamp) = ? 
    GROUP BY request_uri 
    ORDER BY total DESC 
    LIMIT 5
");
$stmt->execute([$fecha_filtro]);
$top_pages = $stmt->fetchAll();

// Top Dispositivos hoy
$stmt = $db->prepare("
    SELECT device_type, COUNT(*) as total 
    FROM traffic_logs 
    WHERE DATE(timestamp) = ? 
    GROUP BY device_type 
    ORDER BY total DESC
");
$stmt->execute([$fecha_filtro]);
$devices = $stmt->fetchAll();

// Últimas 100 visitas
$stmt = $db->prepare("
    SELECT * FROM traffic_logs 
    ORDER BY timestamp DESC 
    LIMIT 100
");
$stmt->execute();
$ultimas_visitas = $stmt->fetchAll();
?>

<div class="row g-4 mb-4">
    <!-- KPI Cards -->
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-card-icon" style="background: rgba(255,107,26,0.1); color: var(--primary);">
                <i class="fas fa-eye"></i>
            </div>
            <div class="stat-card-title">Vistas de Página (Hoy)</div>
            <div class="stat-card-value"><?php echo number_format($visitas_hoy); ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-card-icon" style="background: rgba(40,167,69,0.1); color: #28a745;">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-card-title">Visitantes Únicos (Hoy)</div>
            <div class="stat-card-value"><?php echo number_format($unicos_hoy); ?></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="stat-card">
            <div class="stat-card-title">Dispositivos (Hoy)</div>
            <div class="d-flex gap-4 align-items-center mt-3">
                <?php foreach($devices as $dev): 
                    $icon = $dev['device_type'] == 'Mobile' ? 'fa-mobile-alt' : 'fa-desktop';
                    $color = $dev['device_type'] == 'Mobile' ? '#17a2b8' : '#6c757d';
                ?>
                <div class="d-flex align-items-center gap-2">
                    <i class="fas <?php echo $icon; ?>" style="font-size: 24px; color: <?php echo $color; ?>"></i>
                    <div>
                        <div style="font-weight: 700; font-size: 18px;"><?php echo number_format($dev['total']); ?></div>
                        <div style="font-size: 12px; color: #999;"><?php echo $dev['device_type']; ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Últimas Visitas -->
    <div class="col-md-8">
        <div class="admin-table">
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="m-0 text-dark"><i class="fas fa-globe me-2 text-primary"></i>Tráfico en Tiempo Real</h5>
                <button class="btn btn-sm btn-outline-primary" onclick="location.reload()">
                    <i class="fas fa-sync-alt"></i> Actualizar
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Hora</th>
                            <th>IP</th>
                            <th>Página</th>
                            <th>Disp.</th>
                            <th>Referer</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($ultimas_visitas as $v): ?>
                        <tr>
                            <td style="white-space: nowrap; font-size: 12px;">
                                <?php echo date('H:i:s', strtotime($v['timestamp'])); ?>
                                <br>
                                <small class="text-muted"><?php echo date('d/m', strtotime($v['timestamp'])); ?></small>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <?php echo htmlspecialchars($v['ip_address']); ?>
                                </span>
                                <!-- Placeholder para bandera/país si se implementa GeoIP -->
                            </td>
                            <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <a href="<?php echo htmlspecialchars($v['request_uri']); ?>" target="_blank" class="text-decoration-none">
                                    <?php echo htmlspecialchars($v['request_uri']); ?>
                                </a>
                            </td>
                            <td>
                                <?php if($v['device_type'] == 'Mobile'): ?>
                                    <i class="fas fa-mobile-alt text-info" title="Móvil"></i>
                                <?php else: ?>
                                    <i class="fas fa-desktop text-secondary" title="Escritorio"></i>
                                <?php endif; ?>
                            </td>
                            <td style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12px;">
                                <?php 
                                    $ref = $v['referer'];
                                    if(empty($ref)) echo '<span class="text-muted">-</span>';
                                    else {
                                        $host = parse_url($ref, PHP_URL_HOST);
                                        echo htmlspecialchars($host ? $host : 'Directo');
                                    }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($ultimas_visitas)): ?>
                        <tr><td colspan="5" class="text-center py-4">No hay visitas registradas aún.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Top Páginas -->
    <div class="col-md-4">
        <div class="admin-table h-100">
            <div class="p-3 border-bottom">
                <h5 class="m-0 text-dark"><i class="fas fa-trophy me-2 text-warning"></i>Páginas Top (Hoy)</h5>
            </div>
            <table class="table mb-0">
                <tbody>
                    <?php foreach($top_pages as $idx => $p): ?>
                    <tr>
                        <td width="30" class="text-center fw-bold text-muted"><?php echo $idx + 1; ?></td>
                        <td style="word-break: break-all;">
                            <a href="<?php echo htmlspecialchars($p['request_uri']); ?>" target="_blank" class="text-dark text-decoration-none">
                                <?php echo htmlspecialchars($p['request_uri']); ?>
                            </a>
                        </td>
                        <td class="text-end fw-bold"><?php echo number_format($p['total']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($top_pages)): ?>
                    <tr><td colspan="3" class="text-center py-4">Sin datos hoy.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>