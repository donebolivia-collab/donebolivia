<?php
// Configuración y funciones
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/SimpleMail.php'; // IMPORTANTE: Clase de correo probada
iniciarSesion();

// Si ya está logueado, al index
if (estaLogueado()) {
    header("Location: ../index.php");
    exit;
}

$titulo = "Recuperar Contraseña";
$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = limpiarEntrada($_POST['email'] ?? '');

    if (empty($email)) {
        $error = 'Por favor ingresa tu correo electrónico';
    } elseif (!validarEmail($email)) {
        $error = 'El formato del correo no es válido';
    } else {
        try {
            $db = getDB();
            // Verificar si el usuario existe
            $stmt = $db->prepare("SELECT id, nombre, email FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();

            if ($usuario) {
                // Generar token único y seguro
                $token = bin2hex(random_bytes(32));
                $expiracion = date('Y-m-d H:i:s', strtotime('+1 hour')); // Validez de 1 hora

                // Guardar token en la base de datos
                $stmt = $db->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                $stmt->execute([$email, $token, $expiracion]);

                // Configuración de Correo (Mismas credenciales que send_otp_email.php)
                $smtp_user = 'donebolivia@gmail.com';
                $smtp_pass = 'tqsv gwjp terw puaz';
                
                $subject = "Recuperar Contraseña - Done!";
                $resetLink = SITE_URL . "/auth/reset_password.php?token=" . $token;
                
                $message = "
                <div style='font-family: Arial, sans-serif; padding: 20px; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #eee; border-radius: 8px;'>
                    <div style='text-align: center; margin-bottom: 20px;'>
                        <h2 style='color: #ff6b1a; margin: 0;'>Done!</h2>
                        <p style='margin: 5px 0 0 0; color: #666;'>Recuperación de Cuenta</p>
                    </div>
                    
                    <p>Hola <strong>" . htmlspecialchars($usuario['nombre']) . "</strong>,</p>
                    <p>Hemos recibido una solicitud para restablecer tu contraseña. Si fuiste tú, haz clic en el botón de abajo:</p>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='" . $resetLink . "' style='background: #ff6b1a; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px; display: inline-block;'>Restablecer Contraseña</a>
                    </div>
                    
                    <p style='font-size: 14px;'>O copia y pega este enlace en tu navegador:</p>
                    <p style='background: #f8f9fa; padding: 10px; border-radius: 4px; font-size: 12px; word-break: break-all; color: #666;'>" . $resetLink . "</p>
                    
                    <p style='color: #666; font-size: 13px;'>Este enlace expirará en 1 hora.</p>
                    <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                    <p style='font-size: 12px; color: #999; text-align: center;'>Si no solicitaste esto, puedes ignorar este correo de forma segura.</p>
                </div>
                ";

                // Enviar usando SimpleMail (Clase probada)
                $mail = new SimpleMail($smtp_user, $smtp_pass);

                if($mail->send($email, $subject, $message, 'Done Bolivia')) {
                    $mensaje = 'Si el correo existe en nuestro sistema, recibirás un enlace para restablecer tu contraseña.';
                } else {
                     error_log("Error enviando correo SMTP a $email");
                     $error = 'Hubo un problema enviando el correo. Inténtalo más tarde.'; // Aquí sí mostramos error técnico
                }
            } else {
                // Mensaje genérico por seguridad (User Enumeration)
                $mensaje = 'Si el correo existe en nuestro sistema, recibirás un enlace para restablecer tu contraseña.';
            }
        } catch (Exception $e) {
            $error = 'Ocurrió un error. Inténtalo más tarde.';
            error_log("Error en recuperación: " . $e->getMessage());
        }
    }
}

require_once '../includes/header.php';
?>

<style>
/* Reutilizamos estilos de login/register para consistencia */
.register-wrap {
    max-width: 500px;
    margin: 40px auto;
    padding: 30px;
    background: #f5f5f5;
    border-radius: 8px;
}
.register-title { 
    text-align: center; 
    font-size: 28px; 
    font-weight: 700; 
    margin-bottom: 10px; 
    color: #2c3e50; 
}
.register-subtitle {
    text-align: center;
    color: #666;
    margin-bottom: 30px;
    font-size: 15px;
}
.register-card {
    background: white;
    border-radius: 12px;
    padding: 32px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    border: 1px solid #e5e7eb;
}
.input-text {
    width: 100%;
    padding: 14px 16px;
    border: 1.5px solid #d1d5db;
    border-radius: 8px;
    font-size: 15px;
    margin-bottom: 20px;
    box-sizing: border-box;
}
.input-text:focus {
    outline: none;
    border-color: #ff6b1a;
    box-shadow: 0 0 0 3px rgba(255, 107, 26, 0.1);
}
.btn-submit {
    width: 100%;
    padding: 14px;
    background: #ff6b1a;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-submit:hover {
    background: #e85e00;
}
.back-link {
    display: block;
    text-align: center;
    margin-top: 20px;
    color: #666;
    text-decoration: none;
    font-size: 14px;
}
.back-link:hover {
    color: #ff6b1a;
    text-decoration: underline;
}
</style>

<div class="register-wrap">
    <h1 class="register-title">¿Olvidaste tu contraseña?</h1>

    <?php if ($error): ?>
        <div class="alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <?php if ($mensaje): ?>
        <div class="alert-error" style="background: #f0fdf4; color: #166534; border-color: #bbf7d0;">
            <i class="fas fa-check-circle"></i> <?php echo $mensaje; ?>
        </div>
    <?php else: ?>
        <div class="register-card">
            <form method="POST" action="">
                <label for="email" class="field-label" style="display:block; margin-bottom:8px; font-weight:600; color:#334155;">Correo Electrónico</label>
                <input type="email" 
                       id="email" 
                       name="email" 
                       class="input-text" 
                       placeholder="ejemplo@correo.com"
                       required
                       autofocus>

                <button type="submit" class="btn-submit">
                    Enviar enlace de recuperación
                </button>
            </form>
        </div>
    <?php endif; ?>

    <a href="login.php" class="back-link">
        <i class="fas fa-arrow-left"></i> Volver al inicio de sesión
    </a>
</div>

<?php require_once '../includes/footer.php'; ?>