<?php
session_start();
require_once '../../config/database.php';
require_once '../admin_functions.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

// Verificar autenticación
if (!esAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$id = intval($_GET['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    $db = getDB();

    // Obtener detalle completo del reporte
    $stmt = $db->prepare("
        SELECT d.*,
               p.titulo as producto_titulo,
               p.precio as producto_precio,
               p.descripcion as producto_descripcion,
               p.activo as producto_activo,
               u.nombre as reportante_nombre,
               u.email as reportante_email,
               u.telefono as reportante_telefono,
               v.nombre as vendedor_nombre,
               v.email as vendedor_email,
               v.telefono as vendedor_telefono,
               a.nombre as admin_nombre
        FROM denuncias d
        JOIN productos p ON d.producto_id = p.id
        JOIN usuarios u ON d.usuario_reporta_id = u.id
        JOIN usuarios v ON p.usuario_id = v.id
        LEFT JOIN usuarios a ON d.admin_id = a.id
        WHERE d.id = ?
    ");
    $stmt->execute([$id]);
    $reporte = $stmt->fetch();

    if (!$reporte) {
        echo json_encode(['success' => false, 'message' => 'Reporte no encontrado']);
        exit;
    }

    // Motivos en español
    $motivos = [
        'producto_vendido' => 'Producto vendido',
        'precio_sospechoso' => 'Precio sospechoso',
        'descripcion_enganosa' => 'Descripción engañosa',
        'ubicacion_incorrecta' => 'Ubicación incorrecta',
        'publicacion_duplicada' => 'Publicación duplicada',
        'vendedor_sospechoso' => 'Vendedor sospechoso',
        'contenido_inapropiado' => 'Contenido inapropiado'
    ];

    // Generar HTML del detalle
    $html = '
    <div class="mb-3">
        <h6 class="text-muted">Producto Reportado</h6>
        <p class="mb-1"><strong>' . htmlspecialchars($reporte['producto_titulo']) . '</strong></p>
        <p class="mb-1">ID: ' . $reporte['producto_id'] . ' | Precio: ' . formatearPrecio($reporte['producto_precio']) . '</p>
        <p class="mb-0">
            <a href="/products/view_product.php?id=' . $reporte['producto_id'] . '" target="_blank" class="btn btn-sm btn-primary">
                <i class="fas fa-external-link-alt"></i> Ver producto
            </a>
        </p>
    </div>

    <div class="mb-3">
        <h6 class="text-muted">Motivo del Reporte</h6>
        <p class="mb-0"><span class="badge bg-info">' . ($motivos[$reporte['motivo']] ?? $reporte['motivo']) . '</span></p>
    </div>

    <div class="mb-3">
        <h6 class="text-muted">Reportado por</h6>
        <p class="mb-1">' . htmlspecialchars($reporte['reportante_nombre']) . '</p>
        <p class="mb-1 small text-muted">Email: ' . htmlspecialchars($reporte['reportante_email']) . '</p>
        <p class="mb-0 small text-muted">Teléfono: ' . htmlspecialchars($reporte['reportante_telefono'] ?? 'No disponible') . '</p>
    </div>

    <div class="mb-3">
        <h6 class="text-muted">Vendedor</h6>
        <p class="mb-1">' . htmlspecialchars($reporte['vendedor_nombre']) . '</p>
        <p class="mb-1 small text-muted">Email: ' . htmlspecialchars($reporte['vendedor_email']) . '</p>
        <p class="mb-0 small text-muted">Teléfono: ' . htmlspecialchars($reporte['vendedor_telefono'] ?? 'No disponible') . '</p>
    </div>

    <div class="mb-3">
        <h6 class="text-muted">Fecha de Reporte</h6>
        <p class="mb-0">' . date('d/m/Y H:i', strtotime($reporte['fecha_denuncia'])) . '</p>
    </div>

    <div class="mb-3">
        <h6 class="text-muted">Estado</h6>
        <p class="mb-0"><span class="badge bg-' . ($reporte['estado'] === 'resuelto' ? 'success' : ($reporte['estado'] === 'pendiente' ? 'warning' : 'info')) . '">' . ucfirst($reporte['estado']) . '</span></p>
        ' . ($reporte['admin_nombre'] ? '<p class="small text-muted mb-0">Revisado por: ' . htmlspecialchars($reporte['admin_nombre']) . '</p>' : '') . '
        ' . ($reporte['fecha_revision'] ? '<p class="small text-muted mb-0">Fecha revisión: ' . date('d/m/Y H:i', strtotime($reporte['fecha_revision'])) . '</p>' : '') . '
    </div>

    ' . ($reporte['admin_notas'] ? '
    <div class="mb-3">
        <h6 class="text-muted">Notas del Administrador</h6>
        <p class="mb-0">' . nl2br(htmlspecialchars($reporte['admin_notas'])) . '</p>
    </div>
    ' : '') . '

    <div class="mb-0">
        <h6 class="text-muted">Estado del Producto</h6>
        <p class="mb-0">
            ' . ($reporte['producto_activo'] ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>') . '
        </p>
    </div>
    ';

    echo json_encode([
        'success' => true,
        'html' => $html
    ]);

} catch (Exception $e) {
    error_log("Error en detalle_reporte: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
?>
