<?php
// Configuración y funciones
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
iniciarSesion();

// Si ya está logueado, al index
if (estaLogueado()) {
    header("Location: ../index.php");
    exit;
}

$titulo = "Restablecer Contraseña";
$mensaje = '';
$error = '';
$token = $_GET['token'] ?? '';

if (empty($token)) {
    header("Location: login.php");
    exit;
}

// Validar token en base de datos
try {
    $db = getDB();
    // Buscar token válido y no expirado
    $stmt = $db->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    $resetRequest = $stmt->fetch();

    if (!$resetRequest) {
        $error = 'El enlace de recuperación es inválido o ha expirado. Solicita uno nuevo.';
    }
} catch (Exception $e) {
    $error = 'Error verificando el enlace. Inténtalo más tarde.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($password) || strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres';
    } elseif ($password !== $confirm_password) {
        $error = 'Las contraseñas no coinciden';
    } else {
        try {
            // Actualizar contraseña en base de datos
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $email = $resetRequest['email'];

            // Iniciar transacción para atomicidad
            $db->beginTransaction();

            // 1. Actualizar usuario
            $stmt = $db->prepare("UPDATE usuarios SET password = ? WHERE email = ?");
            $stmt->execute([$hash, $email]);

            // 2. Eliminar el token usado (y otros tokens viejos de ese email por limpieza)
            $stmt = $db->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmt->execute([$email]);

            $db->commit();
            
            // Redirigir al login con éxito
            header("Location: login.php?mensaje=password_reset_success");
            exit;

        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Error al actualizar la contraseña. Inténtalo de nuevo.';
            error_log("Error reset password: " . $e->getMessage());
        }
    }
}

require_once '../includes/header.php';
?>

<style>
/* Reutilizamos estilos de login/register */
.register-wrap { max-width: 500px; margin: 40px auto; padding: 30px; background: #f5f5f5; border-radius: 8px; }
.register-title { text-align: center; font-size: 28px; font-weight: 700; margin-bottom: 30px; color: #2c3e50; }
.register-card { background: white; border-radius: 12px; padding: 32px; border: 1px solid #e5e7eb; }
.input-text { width: 100%; padding: 14px 16px; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 15px; margin-bottom: 20px; }
.btn-submit { width: 100%; padding: 14px; background: #ff6b1a; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }
</style>

<div class="register-wrap">
    <h1 class="register-title">Nueva Contraseña</h1>

    <?php if ($error): ?>
        <div class="alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <div class="register-card">
        <form method="POST" action="">
            <label class="field-label">Nueva Contraseña</label>
            <input type="password" name="password" class="input-text" required placeholder="Mínimo 6 caracteres">

            <label class="field-label">Confirmar Contraseña</label>
            <input type="password" name="confirm_password" class="input-text" required placeholder="Repite la contraseña">

            <button type="submit" class="btn-submit">
                Guardar Contraseña
            </button>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>