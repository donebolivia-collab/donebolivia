<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Leer JSON raw input
$input = json_decode(file_get_contents('php://input'), true);
$telefono = $input['telefono'] ?? ($_POST['telefono'] ?? '');
$codigo = $input['codigo'] ?? ($_POST['codigo'] ?? '');

// Limpieza básica
$telefono = preg_replace('/[^0-9]/', '', $telefono);

session_start();

// Validar datos
if (empty($telefono) || empty($codigo)) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

// Verificar que existe OTP en sesión
if (!isset($_SESSION['otp_temp'])) {
    echo json_encode(['success' => false, 'message' => 'No hay código pendiente o la sesión ha expirado']);
    exit;
}

$otp_data = $_SESSION['otp_temp'];

// Verificar que el teléfono coincide
if (($otp_data['telefono'] ?? null) !== $telefono) {
    echo json_encode(['success' => false, 'message' => 'El número de teléfono no coincide con el código solicitado']);
    exit;
}

// Verificar expiración
if (time() > $otp_data['expira']) {
    unset($_SESSION['otp_temp']);
    echo json_encode(['success' => false, 'message' => 'El código ha expirado. Solicita uno nuevo.']);
    exit;
}

// Verificar OTP (Comparación directa simple para debug con Meta)
// En producción, si guardaste hash, usa password_verify($codigo, $otp_data['otp'])
if ($otp_data['otp'] == $codigo) {
    // OTP correcto
    unset($_SESSION['otp_temp']);
    $_SESSION['telefono_verificado'] = true;
    
    echo json_encode([
        'success' => true, 
        'message' => 'Teléfono verificado correctamente'
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Código incorrecto.'
    ]);
}
