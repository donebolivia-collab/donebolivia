<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../admin_functions.php';

// Verificar sesión de admin
if (session_status() === PHP_SESSION_NONE) session_start();
if (!esAdmin()) {
    echo "Acceso denegado";
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    echo "ID inválido";
    exit;
}

$db = getDB();
$stmt = $db->prepare("
    SELECT d.*, 
           u.nombre as reportante_nombre, 
           u.email as reportante_email,
           t.nombre as tienda_nombre
    FROM denuncias_tiendas d
    LEFT JOIN usuarios u ON d.usuario_reporta_id = u.id
    JOIN tiendas t ON d.tienda_id = t.id
    WHERE d.id = ?
");
$stmt->execute([$id]);
$reporte = $stmt->fetch();

if (!$reporte) {
    echo "Reporte no encontrado";
    exit;
}

// Motivos legibles
$motivos = [
    'fraude_estafa' => 'Posible fraude o estafa',
    'productos_prohibidos' => 'Venta de productos prohibidos',
    'suplantacion' => 'Suplantación de identidad',
    'contenido_inapropiado' => 'Contenido inapropiado',
    'spam' => 'Spam o información falsa',
    'otro' => 'Otro motivo'
];
?>

<div class="list-group list-group-flush">
    <div class="list-group-item px-0">
        <small class="text-muted d-block">ID Reporte</small>
        <strong>#<?php echo $reporte['id']; ?></strong>
    </div>
    
    <div class="list-group-item px-0">
        <small class="text-muted d-block">Tienda Reportada</small>
        <strong><?php echo htmlspecialchars($reporte['tienda_nombre']); ?></strong>
    </div>

    <div class="list-group-item px-0">
        <small class="text-muted d-block">Motivo</small>
        <span class="badge bg-warning text-dark" style="font-size: 0.9em;">
            <?php echo $motivos[$reporte['motivo']] ?? $reporte['motivo']; ?>
        </span>
    </div>

    <div class="list-group-item px-0">
        <small class="text-muted d-block">Fecha del Reporte</small>
        <?php echo date('d/m/Y H:i', strtotime($reporte['fecha_denuncia'])); ?>
    </div>

    <div class="list-group-item px-0">
        <small class="text-muted d-block">Reportante</small>
        <?php if ($reporte['usuario_reporta_id']): ?>
            <div class="d-flex align-items-center mt-1">
                <div class="bg-light rounded-circle p-2 me-2">
                    <i class="fas fa-user text-secondary"></i>
                </div>
                <div>
                    <div><?php echo htmlspecialchars($reporte['reportante_nombre']); ?></div>
                    <small class="text-muted"><?php echo htmlspecialchars($reporte['reportante_email']); ?></small>
                </div>
            </div>
        <?php else: ?>
            <span class="text-muted fst-italic"><i class="fas fa-user-secret"></i> Anónimo</span>
        <?php endif; ?>
    </div>

    <div class="list-group-item px-0">
        <small class="text-muted d-block">Estado Actual</small>
        <?php
        $estado_class = match($reporte['estado']) {
            'pendiente' => 'bg-warning',
            'revisado' => 'bg-info',
            'sancionado' => 'bg-danger',
            'descartado' => 'bg-secondary',
            default => 'bg-secondary'
        };
        ?>
        <span class="badge <?php echo $estado_class; ?> mt-1">
            <?php echo ucfirst($reporte['estado']); ?>
        </span>
    </div>
</div>