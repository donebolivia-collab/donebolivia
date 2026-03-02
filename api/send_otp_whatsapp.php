<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$telefono = $_POST['telefono'] ?? '';

// Validar teléfono
if (empty($telefono)) {
    echo json_encode(['success' => false, 'message' => 'Teléfono es requerido']);
    exit;
}

if (!validarTelefono($telefono)) {
    echo json_encode(['success' => false, 'message' => 'Teléfono no válido']);
    exit;
}

try {
    $db = getDB();
    
    // Verificar si el teléfono ya está registrado y verificado
    $stmt = $db->prepare("SELECT id, telefono_verificado FROM usuarios WHERE telefono = ?");
    $stmt->execute([$telefono]);
    $usuario = $stmt->fetch();
    
    if ($usuario && $usuario['telefono_verificado'] == 1) {
        echo json_encode(['success' => false, 'message' => 'Este teléfono ya está verificado']);
        exit;
    }
    
    // Generar OTP (fijo para entorno de pruebas) o a partir de constante FIXED_OTP
    $otp_fijo = defined('FIXED_OTP') ? FIXED_OTP : '123456';
    $otp = sprintf("%06d", (int)$otp_fijo);
    
    // Hash del OTP para almacenamiento seguro
    $otp_hash = password_hash($otp, PASSWORD_DEFAULT);
    
    // Tiempo de expiración: 5 minutos
    $expira = date('Y-m-d H:i:s', strtotime('+5 minutes'));
    
    // Guardar OTP en sesión temporal (para registro nuevo)
    session_start();
    $_SESSION['otp_temp'] = [
        'telefono' => $telefono,
        'otp_hash' => $otp_hash,
        'expira' => $expira,
        'intentos' => 0
    ];
    
    if ($http_code == 200) {
        // ÉXITO REAL
        echo json_encode([
            'success' => true, 
            'message' => 'Código enviado correctamente.'
        ]);
    } else {
        // FALLO REAL (Logueamos el error pero avisamos al frontend)
        error_log("Meta API Error: " . $response);
        
        echo json_encode([
            'success' => false, 
            'message' => 'No se pudo enviar el mensaje. Inténtalo más tarde.',
            'error_details' => $result
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error en send_otp_whatsapp: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error del sistema']);
}

