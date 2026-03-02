<?php
$titulo = "Gestión Avanzada de Tienda";
require_once 'header.php';

// Validar ID
$tienda_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$tienda_id) {
    echo "<div class='alert alert-danger'>ID de tienda no válido.</div>";
    require_once 'footer.php';
    exit;
}

$db = getDB();

// 1. Obtener Datos Completos de la Tienda
$stmt = $db->prepare("
    SELECT t.*, 
           u.nombre as owner_nombre, 
           u.email as owner_email, 
           u.telefono as owner_telefono,
           u.fecha_registro as owner_since,
           (SELECT COUNT(*) FROM productos p WHERE p.usuario_id = t.usuario_id) as total_products,
           (SELECT COUNT(*) FROM productos p WHERE p.usuario_id = t.usuario_id AND p.activo = 1) as active_products,
           (SELECT COUNT(*) FROM denuncias_tiendas d WHERE d.tienda_id = t.id) as total_reports
    FROM tiendas t
    LEFT JOIN usuarios u ON t.usuario_id = u.id
    WHERE t.id = ?
");
$stmt->execute([$tienda_id]);
$tienda = $stmt->fetch();

if (!$tienda) {
    echo "<div class='alert alert-danger'>Tienda no encontrada.</div>";
    require_once 'footer.php';
    exit;
}

// 2. Obtener Historial de Reportes
$stmt_rep = $db->prepare("
    SELECT d.*, u.nombre as reportante
    FROM denuncias_tiendas d
    LEFT JOIN usuarios u ON d.usuario_reporta_id = u.id
    WHERE d.tienda_id = ?
    ORDER BY d.fecha_denuncia DESC
");
$stmt_rep->execute([$tienda_id]);
$reportes = $stmt_rep->fetchAll();

// 3. Determinar Salud de la Tienda (Lógica simple por ahora)
$salud_score = 100 - ($tienda['total_reports'] * 10);
if ($salud_score < 0) $salud_score = 0;

$salud_color = 'success';
if ($salud_score < 80) $salud_color = 'warning';
if ($salud_score < 50) $salud_color = 'danger';

?>

<style>
    .store-header-card {
        background: white;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        margin-bottom: 30px;
        border-left: 5px solid var(--primary);
    }
    
    .status-switch {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
        border: 1px solid #e9ecef;
    }

    .health-indicator {
        width: 100%;
        height: 8px;
        background: #e9ecef;
        border-radius: 4px;
        overflow: hidden;
        margin-top: 5px;
    }
    
    .health-bar {
        height: 100%;
        transition: width 0.5s ease;
    }

    .action-panel {
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }

    .panel-header {
        padding: 15px 20px;
        background: #f8f9fa;
        border-bottom: 1px solid #eee;
        font-weight: 600;
        color: #555;
    }
    
    .info-row {
        display: flex;
        justify-content: space-between;
        padding: 12px 0;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .info-label { color: #888; font-size: 0.9em; }
    .info-value { font-weight: 500; color: #333; }

    /* CORRECCIÓN VISUAL PESTAÑAS */
    .nav-tabs .nav-link {
        color: #495057 !important; /* Texto gris oscuro siempre */
        font-weight: 500;
        background-color: #f8f9fa; /* Fondo gris muy suave */
        margin-right: 2px;
        border: 1px solid #dee2e6;
    }
    
    .nav-tabs .nav-link:hover {
        background-color: #e9ecef;
        color: #000 !important;
    }

    .nav-tabs .nav-link.active {
        color: #000 !important;
        background-color: #fff !important;
        border-bottom-color: transparent;
        border-top: 3px solid var(--primary); /* Toque de color activo */
    }
</style>

<div class="row">
    <div class="col-md-8">
        <!-- HEADER PRINCIPAL DE LA TIENDA -->
        <div class="store-header-card">
            <div class="d-flex justify-content-between align-items-start">
                <div class="d-flex gap-4">
                    <div style="position: relative;">
                        <?php if ($tienda['logo']): ?>
                            <img src="/uploads/<?php echo htmlspecialchars($tienda['logo']); ?>" 
                                 style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid #fff; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                        <?php else: ?>
                            <div style="width: 100px; height: 100px; border-radius: 50%; background: #eee; display: flex; align-items: center; justify-content: center; font-size: 40px; color: #ccc;">
                                <i class="fas fa-store"></i>
                            </div>
                        <?php endif; ?>
                        <div style="position: absolute; bottom: 0; right: 0; background: <?php echo $tienda['estado'] == 'activo' ? '#28a745' : '#dc3545'; ?>; color: white; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid white;">
                            <i class="fas <?php echo $tienda['estado'] == 'activo' ? 'fa-check' : 'fa-ban'; ?>"></i>
                        </div>
                    </div>
                    <div>
                        <h2 class="mb-1"><?php echo htmlspecialchars($tienda['nombre']); ?></h2>
                        <p class="text-muted mb-2">/tienda/<?php echo htmlspecialchars($tienda['slug']); ?> <a href="/tienda/<?php echo htmlspecialchars($tienda['slug']); ?>" target="_blank"><i class="fas fa-external-link-alt small"></i></a></p>
                        
                        <div class="d-flex gap-2">
                            <span class="badge bg-secondary"><i class="fas fa-box"></i> <?php echo $tienda['total_products']; ?> productos</span>
                            <span class="badge bg-secondary"><i class="fas fa-eye"></i> <?php echo number_format($tienda['visitas']); ?> visitas</span>
                        </div>
                    </div>
                </div>

                <!-- CONTROL MAESTRO DE ESTADO (EL INTERRUPTOR) -->
                <div class="status-switch">
                    <div>
                        <label class="small text-muted d-block mb-1">ESTADO ACTUAL</label>
                        <strong class="<?php echo $tienda['estado'] == 'activo' ? 'text-success' : 'text-danger'; ?> text-uppercase" id="estadoLabel">
                            <?php echo $tienda['estado']; ?>
                        </strong>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="mainSwitch" style="width: 3.5em; height: 1.75em;"
                               <?php echo $tienda['estado'] == 'activo' ? 'checked' : ''; ?>
                               onchange="toggleTiendaStatus(<?php echo $tienda_id; ?>, this.checked)">
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <label class="small text-muted">SALUD DE LA TIENDA (Risk Score)</label>
                <div class="d-flex justify-content-between align-items-end mb-1">
                    <h4 class="m-0 text-<?php echo $salud_color; ?>"><?php echo $salud_score; ?>/100</h4>
                    <span class="small text-muted"><?php echo $tienda['total_reports']; ?> reportes históricos</span>
                </div>
                <div class="health-indicator">
                    <div class="health-bar bg-<?php echo $salud_color; ?>" style="width: <?php echo $salud_score; ?>%"></div>
                </div>
            </div>
        </div>

        <!-- PESTAÑAS DE GESTIÓN -->
        <ul class="nav nav-tabs mb-3" id="storeTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="reports-tab" data-bs-toggle="tab" href="#reports" role="tab">
                    <i class="fas fa-exclamation-triangle text-warning"></i> Reportes
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="products-tab" data-bs-toggle="tab" href="#products" role="tab">
                    <i class="fas fa-box"></i> Productos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="actions-tab" data-bs-toggle="tab" href="#actions" role="tab">
                    <i class="fas fa-shield-alt"></i> Gestión de Cumplimiento
                </a>
            </li>
        </ul>

        <div class="tab-content">
            <!-- TAB REPORTES -->
            <div class="tab-pane fade show active" id="reports" role="tabpanel">
                <div class="action-panel">
                    <div class="panel-header">Historial de Denuncias</div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Motivo</th>
                                    <th>Estado</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($reportes)): ?>
                                    <tr><td colspan="4" class="text-center py-4 text-muted">Sin reportes registrados. Una tienda ejemplar.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($reportes as $rep): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($rep['fecha_denuncia'])); ?></td>
                                        <td><?php echo $rep['motivo']; ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $rep['estado'] == 'pendiente' ? 'warning' : ($rep['estado'] == 'sancionado' ? 'danger' : 'secondary'); ?>">
                                                <?php echo $rep['estado']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="verDetalleReporte(<?php echo $rep['id']; ?>)">Ver</button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB PRODUCTOS (PREVIEW) -->
            <div class="tab-pane fade" id="products" role="tabpanel">
                <div class="alert alert-info d-flex align-items-center">
                    <i class="fas fa-info-circle fa-2x me-3"></i>
                    <div>
                        Esta tienda tiene <strong><?php echo $tienda['total_products']; ?></strong> productos.
                        <br>
                        <a href="/admin/products.php?q=<?php echo urlencode($tienda['nombre']); ?>" class="btn btn-sm btn-primary mt-2" target="_blank">
                            Ir al Gestor de Productos <i class="fas fa-external-link-alt"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- TAB GESTIÓN DE CUMPLIMIENTO (Antes Acciones Avanzadas) -->
            <div class="tab-pane fade" id="actions" role="tabpanel">
                
                <!-- SECCIÓN 1: CICLO DE VIDA (Eliminación de Tienda) -->
                <div class="card mb-4 border-light shadow-sm">
                    <div class="card-header bg-white font-weight-bold">
                        <i class="fas fa-recycle text-secondary"></i> Ciclo de Vida de la Tienda
                    </div>
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h6 class="text-dark">Eliminación Definitiva de la Tienda</h6>
                                <p class="small text-muted mb-0">
                                    Esta acción eliminará la tienda, sus productos, imágenes y reportes asociados. 
                                    <strong>El usuario propietario NO será eliminado ni bloqueado.</strong> 
                                    Utilice esto para solicitudes de cierre o depuración.
                                </p>
                            </div>
                            <div class="col-md-4 text-end">
                                <button class="btn btn-outline-danger" onclick="eliminarTiendaPermanente(<?php echo $tienda_id; ?>)">
                                    <i class="fas fa-trash-alt"></i> Eliminar Tienda
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN 2: SANCIONES Y RESTRICCIONES (Escalonado) -->
                <div class="card border-light shadow-sm">
                    <div class="card-header bg-white font-weight-bold">
                        <i class="fas fa-gavel text-warning"></i> Sanciones y Restricciones
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <!-- Nivel 1 -->
                            <div class="col-md-4">
                                <div class="p-3 border rounded bg-light h-100">
                                    <h6 class="text-warning"><i class="fas fa-exclamation-circle"></i> Nivel 1: Advertencia</h6>
                                    <p class="small text-muted">Envía una notificación formal al propietario sobre incumplimiento de políticas.</p>
                                    <button class="btn btn-sm btn-warning w-100" onclick="enviarAdvertencia(<?php echo $tienda_id; ?>)">Enviar Advertencia</button>
                                </div>
                            </div>
                            
                            <!-- Nivel 2 (Flexible) -->
                            <div class="col-md-4">
                                <div class="p-3 border rounded bg-light h-100">
                                    <h6 class="text-orange" style="color: #fd7e14;"><i class="fas fa-clock"></i> Nivel 2: Suspensión Temporal</h6>
                                    <p class="small text-muted">Suspende la tienda por un tiempo definido. Tú eliges la duración.</p>
                                    <button class="btn btn-sm btn-outline-warning w-100" onclick="suspenderTemporalmente(<?php echo $tienda_id; ?>)">Definir Suspensión...</button>
                                </div>
                            </div>

                            <!-- Nivel 3 -->
                            <div class="col-md-4">
                                <div class="p-3 border rounded bg-light h-100">
                                    <h6 class="text-danger"><i class="fas fa-ban"></i> Nivel 3: Suspensión Indefinida</h6>
                                    <p class="small text-muted">Desactiva la tienda hasta nuevo aviso. Igual que el interruptor superior.</p>
                                    <button class="btn btn-sm btn-outline-danger w-100" onclick="forzarSuspension(<?php echo $tienda_id; ?>)">Suspender Ahora</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <div class="col-md-4">
        <!-- INFO PROPIETARIO -->
        <div class="action-panel mb-4">
            <div class="panel-header">Propietario</div>
            <div class="p-3">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-light rounded-circle p-3 me-3">
                        <i class="fas fa-user fa-lg text-secondary"></i>
                    </div>
                    <div>
                        <h6 class="m-0"><?php echo htmlspecialchars($tienda['owner_nombre']); ?></h6>
                        <small class="text-muted">ID: <?php echo $tienda['usuario_id']; ?></small>
                    </div>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Email</span>
                    <span class="info-value small"><?php echo htmlspecialchars($tienda['owner_email']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Teléfono</span>
                    <span class="info-value"><?php echo htmlspecialchars($tienda['owner_telefono'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Registro</span>
                    <span class="info-value"><?php echo date('d/m/Y', strtotime($tienda['owner_since'])); ?></span>
                </div>

                <div class="mt-3">
                    <a href="/admin/users.php?q=<?php echo urlencode($tienda['owner_email']); ?>" class="btn btn-outline-secondary btn-sm w-100">Ver Perfil Completo</a>
                </div>
            </div>
        </div>
        
        <!-- NOTAS INTERNAS (TODO: Implementar backend) -->
        <div class="action-panel">
            <div class="panel-header">Notas del Staff</div>
            <div class="p-3">
                <textarea class="form-control mb-2" rows="3" placeholder="Escribe notas internas sobre esta tienda..."></textarea>
                <button class="btn btn-sm btn-primary float-end">Guardar Nota</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DETALLE REPORTE -->
<div class="modal fade" id="detalleReporteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle de la Denuncia</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="detalleReporteContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
// Función faltante implementada
function verDetalleReporte(id) {
    const modalEl = document.getElementById('detalleReporteModal');
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
    
    // Cargar contenido
    document.getElementById('detalleReporteContent').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
        </div>
    `;

    fetch('/admin/ajax/detalle_reporte_tienda.php?id=' + id)
        .then(r => r.text())
        .then(html => {
            document.getElementById('detalleReporteContent').innerHTML = html;
        })
        .catch(err => {
            document.getElementById('detalleReporteContent').innerHTML = '<div class="alert alert-danger">Error al cargar detalles.</div>';
        });
}

function toggleTiendaStatus(id, isActive) {
    const nuevoEstado = isActive ? 'activo' : 'suspendido';
    const accion = isActive ? 'ACTIVAR (Perdonar)' : 'SUSPENDER (Castigar)';
    
    // Feedback visual inmediato (Optimistic UI)
    const label = document.getElementById('estadoLabel');
    const originalText = label.innerText;
    const originalClass = label.className;
    
    // Confirmación
    if(!confirm(`¿Confirmas ${accion} esta tienda?`)) {
        // Revertir switch si cancela
        document.getElementById('mainSwitch').checked = !isActive;
        return;
    }

    fetch('/admin/ajax/cambiar_estado_tienda.php', {
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
            // Actualizar UI
            label.innerText = nuevoEstado.toUpperCase();
            label.className = isActive ? 'text-success text-uppercase' : 'text-danger text-uppercase';
            // Recargar para actualizar iconos y logs
            setTimeout(() => location.reload(), 500);
        } else {
            alert('Error: ' + data.message);
            // Revertir switch
            document.getElementById('mainSwitch').checked = !isActive;
        }
    });
}

function enviarAdvertencia(id) {
    if(confirm('¿Enviar correo de advertencia formal al propietario?')) {
        // TODO: Conectar con backend real de email
        alert('Simulación: Correo de advertencia enviado a <?php echo $tienda["owner_email"]; ?>');
    }
}

function forzarSuspension(id) {
    if(!confirm('¿CONFIRMAS SUSPENDER ESTA TIENDA INDEFINIDAMENTE?\n\nLa tienda dejará de ser visible inmediatamente.')) return;

    fetch('/admin/ajax/cambiar_estado_tienda.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id: id,
            estado: 'suspendido',
            csrf_token: CSRF_TOKEN
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Tienda suspendida correctamente.');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function suspenderTemporalmente(id) {
    const dias = prompt("¿Por cuántos días deseas suspender esta tienda?\n\nIngresa el número de días (ej: 1, 3, 7, 14):", "7");
    
    if (dias === null) return; // Cancelado
    
    const numDias = parseInt(dias);
    if (isNaN(numDias) || numDias <= 0) {
        alert("Por favor ingresa un número válido de días.");
        return;
    }

    // Calcular fecha de fin para mostrarla en la confirmación
    const fechaFin = new Date();
    fechaFin.setDate(fechaFin.getDate() + numDias);
    const fechaStr = fechaFin.toLocaleDateString();

    if(!confirm(`¿CONFIRMAS SUSPENDER POR ${numDias} DÍAS?\n\nLa tienda quedará inactiva hasta el ${fechaStr}.\n\nEl sistema la reactivará AUTOMÁTICAMENTE en esa fecha.`)) return;
    
    // Ejecutar suspensión temporal
    fetch('/admin/ajax/cambiar_estado_tienda.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id: id,
            estado: 'suspendido',
            suspension_dias: numDias,
            csrf_token: CSRF_TOKEN
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert(`Tienda suspendida por ${numDias} días.`);
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

function eliminarTiendaPermanente(id) {
    const confirmacion = prompt("ATENCIÓN: ESTA ACCIÓN ES DESTRUCTIVA E IRREVERSIBLE.\n\nSe eliminarán:\n- La tienda completa\n- Todos sus productos\n- Todas las imágenes del servidor\n- Todo el historial\n\nPara confirmar, escribe la palabra: BORRAR");
    
    if(confirmacion === "BORRAR") {
        // Ejecutar eliminación real
        fetch('/admin/ajax/eliminar_tienda_permanente.php', {
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
                alert('TIENDA ELIMINADA CORRECTAMENTE.');
                window.location.href = '/admin/stores.php'; // Redirigir al listado
            } else {
                alert('Error crítico: ' + data.message);
            }
        })
        .catch(err => {
            alert('Error de conexión con el servidor.');
            console.error(err);
        });
    } else {
        if (confirmacion !== null) { // Si no canceló el prompt
            alert("Acción cancelada. La palabra de seguridad era incorrecta.");
        }
    }
}
</script>

<?php require_once 'footer.php'; ?>