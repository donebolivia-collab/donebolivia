<?php
// CRÍTICO: Iniciar sesión para acceder a $_SESSION
session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';
header('Content-Type: application/json');

// NOTA: YA NO verificamos obligatoriamente la sesión.
// Permitimos reportes anónimos para mejorar la seguridad de la comunidad.

// Obtener datos del POST
$data = json_decode(file_get_contents('php://input'), true);
$tienda_slug = $data['tienda_slug'] ?? '';
$motivo = $data['motivo'] ?? '';

// Validar datos
if (!$tienda_slug || !$motivo) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

// Motivos válidos para reporte de tienda
$motivos_validos = [
    'fraude_estafa',
    'productos_prohibidos',
    'suplantacion',
    'contenido_inapropiado',
    'spam',
    'otro'
];

if (!in_array($motivo, $motivos_validos)) {
    echo json_encode(['success' => false, 'message' => 'Motivo de reporte inválido']);
    exit;
}

$db = getDB();
// Si hay sesión, usamos el ID, si no, es NULL (Anónimo)
$usuario_id = isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : null;

// Verificar que la tienda existe
$stmt = $db->prepare("SELECT id, nombre, usuario_id FROM tiendas WHERE slug = ?");
$stmt->execute([$tienda_slug]);
$tienda = $stmt->fetch();

if (!$tienda) {
    echo json_encode(['success' => false, 'message' => 'La tienda no existe']);
    exit;
}

// Si está logueado, verificar que no se reporte a sí mismo
if ($usuario_id && $tienda['usuario_id'] == $usuario_id) {
    echo json_encode(['success' => false, 'message' => 'No puedes reportar tu propia tienda']);
    exit;
}

// Verificar duplicados recientes (Flood Control)
// Si es anónimo, verificamos por IP para evitar spam masivo
if ($usuario_id) {
    $stmt = $db->prepare("SELECT id FROM denuncias_tiendas WHERE tienda_id = ? AND usuario_reporta_id = ? AND DATE(fecha_denuncia) = CURDATE()");
    $stmt->execute([$tienda['id'], $usuario_id]);
} else {
    // Para anónimos, podríamos verificar IP, pero por simplicidad y privacidad ahora solo permitimos.
    // Opcional: Implementar control de IP si hay mucho spam.
    $stmt = false; 
}

if ($stmt && $stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Ya has enviado un reporte sobre esta tienda hoy.']);
    exit;
}

// Función interna para intentar insertar
function intentarInsertarReporte($db, $tienda_id, $usuario_id, $motivo) {
    $stmt = $db->prepare("
        INSERT INTO denuncias_tiendas (tienda_id, usuario_reporta_id, motivo, fecha_denuncia)
        VALUES (?, ?, ?, NOW())
    ");
    return $stmt->execute([$tienda_id, $usuario_id, $motivo]);
}

// Insertar la denuncia
try {
    intentarInsertarReporte($db, $tienda['id'], $usuario_id, $motivo);
    enviarCorreoAdmin($tienda, $motivo, $usuario_id, $db, $tienda_slug);

    echo json_encode([
        'success' => true,
        'message' => 'Reporte enviado correctamente.'
    ]);

} catch (Exception $e) {
    $mensajeError = $e->getMessage();
    
    // CASO 1: La tabla no existe
    if (strpos($mensajeError, "doesn't exist") !== false) {
        crearTablaDenuncias($db);
        // Reintentar
        try {
            intentarInsertarReporte($db, $tienda['id'], $usuario_id, $motivo);
            enviarCorreoAdmin($tienda, $motivo, $usuario_id, $db, $tienda_slug);
            echo json_encode(['success' => true, 'message' => 'Reporte enviado correctamente.']);
        } catch (Exception $e2) {
            error_log("Error fatal reporte: " . $e2->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error al guardar el reporte.']);
        }
    } 
    // CASO 2: La columna usuario_reporta_id no acepta NULL (Configuración antigua)
    // Error típico: "Column 'usuario_reporta_id' cannot be null"
    elseif (strpos($mensajeError, "cannot be null") !== false) {
        try {
            // AUTO-CORRECCIÓN: Modificar la tabla para permitir NULLs
            $db->exec("ALTER TABLE denuncias_tiendas MODIFY usuario_reporta_id INT NULL");
            
            // Reintentar inserción
            intentarInsertarReporte($db, $tienda['id'], $usuario_id, $motivo);
            enviarCorreoAdmin($tienda, $motivo, $usuario_id, $db, $tienda_slug);
            
            echo json_encode(['success' => true, 'message' => 'Reporte enviado correctamente.']);
        } catch (Exception $e3) {
            error_log("Error al modificar tabla denuncias: " . $e3->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error de base de datos. Por favor contacta soporte.']);
        }
    }
    else {
        error_log("Error desconocido al reportar: " . $mensajeError);
        echo json_encode(['success' => false, 'message' => 'Error interno al procesar el reporte']);
    }
}

function crearTablaDenuncias($db) {
    // Creamos la tabla permitiendo NULL en usuario_reporta_id desde el principio
    $db->exec("CREATE TABLE IF NOT EXISTS denuncias_tiendas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tienda_id INT NOT NULL,
        usuario_reporta_id INT NULL, -- Permitimos NULL para anónimos
        motivo VARCHAR(50) NOT NULL,
        fecha_denuncia DATETIME NOT NULL,
        estado ENUM('pendiente', 'revisado', 'sancionado', 'descartado') DEFAULT 'pendiente',
        FOREIGN KEY (tienda_id) REFERENCES tiendas(id) ON DELETE CASCADE
        -- No agregamos FK estricta a usuarios para simplificar anónimos o eliminados
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
}

function enviarCorreoAdmin($tienda, $motivo, $usuario_id, $db, $tienda_slug) {
    // Obtener información del usuario que reporta (si existe)
    $usuario_reporta = "Anónimo (Invitado)";
    $email_reporta = "No disponible";
    
    if ($usuario_id) {
        $stmt = $db->prepare("SELECT nombre, email FROM usuarios WHERE id = ?");
        $stmt->execute([$usuario_id]);
        $u = $stmt->fetch();
        if ($u) {
            $usuario_reporta = $u['nombre'];
            $email_reporta = $u['email'];
        }
    }

    // Convertir motivo a texto legible
    $motivos_texto = [
        'fraude_estafa' => 'Posible fraude o estafa',
        'productos_prohibidos' => 'Venta de productos prohibidos',
        'suplantacion' => 'Suplantación de identidad',
        'contenido_inapropiado' => 'Contenido inapropiado u ofensivo',
        'spam' => 'Spam o información falsa',
        'otro' => 'Otro motivo'
    ];

    // Enviar correo al administrador
    $admin_email = 'yhefric@gmail.com'; 
    
    $asunto = "ALERTA: Reporte de Tienda - " . $tienda['nombre'];
    $mensaje = "Se ha reportado una tienda en donebolivia.com\n\n";
    $mensaje .= "Tienda: " . $tienda['nombre'] . " (ID: " . $tienda['id'] . ")\n";
    $mensaje .= "URL: " . SITE_URL . "/tienda/" . $tienda_slug . "\n";
    $mensaje .= "Motivo: " . ($motivos_texto[$motivo] ?? $motivo) . "\n";
    $mensaje .= "Reportado por: " . $usuario_reporta . "\n";
    $mensaje .= "Email del reportante: " . $email_reporta . "\n";
    $mensaje .= "Fecha: " . date('d/m/Y H:i:s') . "\n\n";
    $mensaje .= "Panel de administración: " . SITE_URL . "/admin/stores.php\n";

    $headers = "From: noreply@donebolivia.com\r\n";
    $headers .= "Reply-To: noreply@donebolivia.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    @mail($admin_email, $asunto, $mensaje, $headers);
}
?>