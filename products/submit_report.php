<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
header('Content-Type: application/json');

// Verificar que el usuario esté logueado
if (!estaLogueado()) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión para reportar productos']);
    exit;
}

// Obtener datos del POST
$data = json_decode(file_get_contents('php://input'), true);
$producto_id = $data['producto_id'] ?? 0;
$motivo = $data['motivo'] ?? '';

// Validar datos
if (!$producto_id || !$motivo) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

// Motivos válidos
$motivos_validos = [
    'producto_vendido',
    'precio_sospechoso',
    'descripcion_enganosa',
    'ubicacion_incorrecta',
    'publicacion_duplicada',
    'vendedor_sospechoso',
    'contenido_inapropiado'
];

if (!in_array($motivo, $motivos_validos)) {
    echo json_encode(['success' => false, 'message' => 'Motivo de reporte inválido']);
    exit;
}

$db = getDB();
$usuario_id = $_SESSION['usuario_id'];

// Verificar que el producto existe
$stmt = $db->prepare("SELECT id, titulo, usuario_id FROM productos WHERE id = ?");
$stmt->execute([$producto_id]);
$producto = $stmt->fetch();

if (!$producto) {
    echo json_encode(['success' => false, 'message' => 'El producto no existe']);
    exit;
}

// Verificar que el usuario no esté reportando su propio producto
if ($producto['usuario_id'] == $usuario_id) {
    echo json_encode(['success' => false, 'message' => 'No puedes reportar tu propio producto']);
    exit;
}

// Verificar que el usuario no haya reportado ya este producto
$stmt = $db->prepare("SELECT id FROM denuncias WHERE producto_id = ? AND usuario_reporta_id = ?");
$stmt->execute([$producto_id, $usuario_id]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Ya reportaste este producto anteriormente']);
    exit;
}

// Insertar la denuncia
try {
    $stmt = $db->prepare("
        INSERT INTO denuncias (producto_id, usuario_reporta_id, motivo, fecha_denuncia)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$producto_id, $usuario_id, $motivo]);

    // Obtener información del usuario que reporta
    $stmt = $db->prepare("SELECT nombre, email FROM usuarios WHERE id = ?");
    $stmt->execute([$usuario_id]);
    $usuario = $stmt->fetch();

    // Convertir motivo a texto legible
    $motivos_texto = [
        'producto_vendido' => 'El producto ya fue vendido',
        'precio_sospechoso' => 'Precio incorrecto o sospechoso',
        'descripcion_enganosa' => 'Descripción o fotos engañosas',
        'ubicacion_incorrecta' => 'Ubicación incorrecta',
        'publicacion_duplicada' => 'Publicación duplicada',
        'vendedor_sospechoso' => 'Vendedor sospechoso o estafador',
        'contenido_inapropiado' => 'Contenido inapropiado'
    ];

    // Enviar correo al administrador
    $asunto = "Nuevo reporte de producto - donebolivia.com";
    $mensaje = "Se ha reportado un producto en donebolivia.com\n\n";
    $mensaje .= "Producto: " . $producto['titulo'] . " (ID: " . $producto_id . ")\n";
    $mensaje .= "Motivo: " . $motivos_texto[$motivo] . "\n";
    $mensaje .= "Reportado por: " . $usuario['nombre'] . " (" . $usuario['email'] . ")\n";
    $mensaje .= "Fecha: " . date('d/m/Y H:i:s') . "\n\n";
    $mensaje .= "Ver producto: " . SITE_URL . "/products/view_product.php?id=" . $producto_id . "\n";
    $mensaje .= "Panel de reportes: " . SITE_URL . "/admin/reports.php\n";

    $headers = "From: noreply@donebolivia.com\r\n";
    $headers .= "Reply-To: noreply@donebolivia.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    mail('yhefric@gmail.com', $asunto, $mensaje, $headers);

    echo json_encode([
        'success' => true,
        'message' => 'Reporte enviado correctamente. Gracias por ayudarnos a mantener la calidad de los anuncios.'
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error al procesar el reporte']);
}
