<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

session_start();

// Ahora permite email o telefono según el canal activo
$email = $_POST['email'] ?? '';
$telefono = $_POST['telefono'] ?? '';
$otp_ingresado = $_POST['otp'] ?? '';

// Validar datos
if ((empty($email) && empty($telefono)) || empty($otp_ingresado)) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

// Verificar que existe OTP en sesión
if (!isset($_SESSION['otp_temp'])) {
    echo json_encode(['success' => false, 'message' => 'No hay código pendiente de verificación']);
    exit;
}

$otp_data = $_SESSION['otp_temp'];

// Verificar que el identificador (email o telefono) coincide
if (!empty($email)) {
    if (($otp_data['email'] ?? null) !== $email) {
        echo json_encode(['success' => false, 'message' => 'Email no coincide']);
        exit;
    }
}

if (!empty($telefono)) {
    if (($otp_data['telefono'] ?? null) !== $telefono) {
        echo json_encode(['success' => false, 'message' => 'Teléfono no coincide']);
        exit;
    }
}

// Verificar intentos
if ($otp_data['intentos'] >= 5) {
    unset($_SESSION['otp_temp']);
    echo json_encode(['success' => false, 'message' => 'Máximo de intentos alcanzado. Solicita un nuevo código.']);
    exit;
}

// Verificar expiración
$ahora = new DateTime();
$expira = new DateTime($otp_data['expira']);

if ($ahora > $expira) {
    unset($_SESSION['otp_temp']);
    echo json_encode(['success' => false, 'message' => 'El código ha expirado. Solicita uno nuevo.']);
    exit;
}

// Verificar OTP
if (password_verify($otp_ingresado, $otp_data['otp_hash'])) {
    // OTP correcto
    $_SESSION['otp_temp']['verificado'] = true;
    if (!empty($email)) {
        $_SESSION['email_verificado'] = $email;
        $msg = 'Email verificado correctamente';
    } else {
        $_SESSION['telefono_verificado'] = $telefono;
        $msg = 'Teléfono verificado correctamente';
    }
    
    echo json_encode([
        'success' => true, 
        'message' => $msg
    ]);
} else {
    // OTP incorrecto - incrementar intentos
    $_SESSION['otp_temp']['intentos']++;
    $intentos_restantes = 5 - $_SESSION['otp_temp']['intentos'];
    
    echo json_encode([
        'success' => false, 
        'message' => "Código incorrecto. Te quedan {$intentos_restantes} intentos.",
        'intentos_restantes' => $intentos_restantes
    ]);
}