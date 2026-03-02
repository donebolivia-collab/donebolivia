<?php
require_once __DIR__ . '/../includes/SimpleMail.php';

header('Content-Type: application/json; charset=utf-8');

// --- CREDENCIALES GMAIL ---
$smtp_user = 'donebolivia@gmail.com';
$smtp_pass = 'tqsv gwjp terw puaz'; // App Password
// --------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
// Soporte para JSON o FormData
$email = $input['email'] ?? ($_POST['email'] ?? '');
$nombre = $input['nombre'] ?? ($_POST['nombre'] ?? 'Usuario');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Email inválido']);
    exit;
}

try {
    // Generar OTP
    $otp = sprintf("%06d", mt_rand(100000, 999999));
    
    // Guardar en sesión
    session_start();
    $_SESSION['otp_email'] = [
        'email' => $email,
        'otp' => $otp,
        'expira' => time() + 600 // 10 min
    ];

    // Contenido del correo
    $subject = 'Tu código de verificación - Done Bolivia';
    $body = "
        <div style='font-family: Arial, sans-serif; padding: 20px; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #eee; border-radius: 8px;'>
            <div style='text-align: center; margin-bottom: 20px;'>
                <h2 style='color: #ff6b1a; margin: 0;'>Done!</h2>
                <p style='margin: 5px 0 0 0; color: #666;'>Bolivia</p>
            </div>
            
            <p>Hola <strong>{$nombre}</strong>,</p>
            <p>Estás a un paso de completar tu registro. Usa el siguiente código para verificar tu cuenta:</p>
            
            <div style='background: #f8f9fa; padding: 15px; font-size: 32px; font-weight: bold; letter-spacing: 8px; text-align: center; border-radius: 8px; margin: 30px 0; color: #2c3e50; border: 2px dashed #ff6b1a;'>
                {$otp}
            </div>
            
            <p style='text-align: center; color: #666;'>Este código expira en 10 minutos.</p>
            <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
            <p style='font-size: 12px; color: #999; text-align: center;'>Si no solicitaste este código, puedes ignorar este correo.</p>
        </div>
    ";

    // Enviar usando SimpleMail
    $mail = new SimpleMail($smtp_user, $smtp_pass);
    
    if ($mail->send($email, $subject, $body, 'Done Bolivia')) {
        echo json_encode(['success' => true, 'message' => 'Código enviado a tu correo']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se pudo enviar el correo. Verifica tu conexión o intenta más tarde.']);
    }

} catch (Exception $e) {
    error_log("Error Mail: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error del sistema.']);
}
