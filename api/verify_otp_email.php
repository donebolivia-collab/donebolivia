<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? ($_POST['email'] ?? '');
$codigo = $input['codigo'] ?? ($_POST['codigo'] ?? '');

session_start();

if (empty($email) || empty($codigo)) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

if (!isset($_SESSION['otp_email'])) {
    echo json_encode(['success' => false, 'message' => 'El código ha expirado o no existe']);
    exit;
}

$otp_data = $_SESSION['otp_email'];

if ($otp_data['email'] !== $email) {
    echo json_encode(['success' => false, 'message' => 'El correo no coincide']);
    exit;
}

if (time() > $otp_data['expira']) {
    unset($_SESSION['otp_email']);
    echo json_encode(['success' => false, 'message' => 'Código expirado']);
    exit;
}

if ($otp_data['otp'] == $codigo) {
    unset($_SESSION['otp_email']);
    $_SESSION['email_verificado'] = true;
    echo json_encode(['success' => true, 'message' => 'Correo verificado']);
} else {
    echo json_encode(['success' => false, 'message' => 'Código incorrecto']);
}
