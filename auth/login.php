<?php
// Mover la lógica de sesión y procesamiento al inicio ANTES de cualquier HTML
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
iniciarSesion();

$titulo = "Iniciar Sesión";
$mensaje = '';
$error = '';

// Verificar si ya está logueado
if (estaLogueado()) {
    header("Location: ../index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $telefono_local = preg_replace('/[^0-9]/', '', $_POST['telefono'] ?? '');
    $password = $_POST['password'] ?? '';
    $recordar = isset($_POST['recordar']);

    if (empty($telefono_local) || empty($password)) {
        $error = 'Teléfono y contraseña son obligatorios';
    } elseif (!preg_match('/^[67][0-9]{7}$/', $telefono_local)) {
        $error = 'El teléfono debe ser boliviano y tener 8 dígitos (empieza con 6 o 7)';
    } else {
        try {
            $db = getDB();
            // Teléfono sin prefijo +591 (solo números bolivianos)
            $telefono_norm = $telefono_local;
            $stmt = $db->prepare("
                   SELECT u.*, 
                          COALESCE(c.nombre, u.municipio_nombre) as ciudad_nombre, 
                          COALESCE(c.departamento, u.departamento_nombre) as departamento 
                   FROM usuarios u 
                   LEFT JOIN ciudades c ON u.ciudad_id = c.id 
                   WHERE u.telefono = ?
               ");
            $stmt->execute([$telefono_norm]);
            $usuario = $stmt->fetch();

            if ($usuario && isset($usuario['password']) && verificarPassword($password, $usuario['password'])) {
                if (!empty($usuario['telefono_verificado']) && (int)$usuario['telefono_verificado'] === 1) {
                    // Regenerar ID de sesión para prevenir Session Fixation
                    session_regenerate_id(true);

                    // Login exitoso
                    $_SESSION['usuario_id'] = $usuario['id'];
                    $_SESSION['usuario_nombre'] = $usuario['nombre'];
                    $_SESSION['usuario_telefono'] = $usuario['telefono'];

                    if ($recordar) {
                        $token = bin2hex(random_bytes(32));
                        setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/');
                        // TODO: persistir token en DB si se implementa remember me real
                    }

                    // Validar parámetro redirect para prevenir Open Redirect
                    $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : '../index.php';
                    // Solo permitir redirecciones internas
                    if (!str_starts_with($redirect, '/') && !str_starts_with($redirect, '../')) {
                        $redirect = '../index.php';
                    }
                    // Prevenir redirecciones a dominios externos
                    $parsed = parse_url($redirect);
                    if (isset($parsed['host']) || isset($parsed['scheme'])) {
                        $redirect = '../index.php';
                    }
                    
                    // REDIRECCIÓN LIMPIA DEL SERVIDOR (Sin parpadeo blanco)
                    header("Location: " . $redirect);
                    exit;
                    
                } else {
                    $error = 'Tu número no está verificado. Verifícalo para iniciar sesión.';
                }
            } else {
                $error = 'Teléfono o contraseña incorrectos';
            }
        } catch (Exception $e) {
            $error = 'Error del sistema. Inténtalo más tarde.';
            error_log("Error en login: " . $e->getMessage());
        }
    }
}

// Mensajes de la URL
if (isset($_GET['mensaje'])) {
    switch ($_GET['mensaje']) {
        case 'registro_exitoso':
            $mensaje = '¡Cuenta creada exitosamente! Ahora puedes iniciar sesión.';
            break;
        case 'logout':
            $mensaje = 'Has cerrado sesión correctamente.';
            break;
        case 'sesion_expirada':
            $error = 'Tu sesión ha expirado. Por favor inicia sesión nuevamente.';
            break;
        case 'password_reset_success':
            $mensaje = '¡Contraseña restablecida correctamente! Inicia sesión con tu nueva contraseña.';
            break;
    }
}

// Solo cargar la vista (Header y HTML) si no hubo redirección
require_once '../includes/header.php';
?>

<style>
/* Estilos unificados con Register.php */
.register-wrap {
    max-width: 500px; /* Más angosto para login */
    margin: 40px auto;
    padding: 30px;
    background: #f5f5f5;
    border-radius: 8px;
}
.register-title { 
    text-align: center; 
    font-size: 28px; 
    font-weight: 700; 
    margin-bottom: 30px; 
    color: #2c3e50; 
}

.register-card {
    background: white;
    border-radius: 12px;
    padding: 32px;
    margin-bottom: 24px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    border: 1px solid #e5e7eb;
}

.input-text {
    width: 100%;
    padding: 14px 16px;
    border: 1.5px solid #d1d5db;
    border-radius: 8px;
    font-size: 15px;
    font-family: inherit;
    margin-bottom: 0;
    height: 48px;
    box-sizing: border-box;
    background: white;
    transition: all 0.3s ease;
}
.input-text:focus {
    outline: none;
    border-color: #ff6b1a;
    box-shadow: 0 0 0 3px rgba(255, 107, 26, 0.1);
    background: #fffbf8;
}

.field-group {
    margin-bottom: 20px;
}
.field-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #334155;
    font-size: 14px;
}

.btn-submit {
    width: 100%;
    padding: 0;
    background: #ff6b1a;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.btn-submit:hover {
    background: #e85e00;
}

.login-options {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 16px;
    font-size: 14px;
}
.login-options a, .login-options label {
    color: #000; /* Negro solicitado */
    text-decoration: none;
    cursor: pointer;
}
.login-options a:hover {
    text-decoration: underline;
}

.create-account-section {
    text-align: center;
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid #e5e7eb;
}
.create-account-text {
    color: #000; /* Negro solicitado */
    font-size: 15px;
    margin-bottom: 16px;
}
.btn-create {
    display: block;
    width: auto;
    margin: 0 40px;
    padding: 12px 24px;
    background-color: #ffffff; /* Blanco limpio */
    border: 2px solid #ff6b1a; /* Borde Naranja Marca */
    border-radius: 6px;
    color: #ff6b1a; /* Texto Naranja */
    font-weight: 700;
    font-size: 15px;
    text-decoration: none;
    text-align: center;
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); /* Transición suave Pro */
    box-sizing: border-box;
    box-shadow: 0 4px 6px rgba(255, 107, 26, 0.1); /* Sombra sutil naranja */
}

.btn-create:hover {
    background-color: #ff6b1a; /* Relleno Naranja al pasar mouse */
    border-color: #ff6b1a;
    color: #ffffff; /* Texto Blanco */
    box-shadow: 0 8px 15px rgba(255, 107, 26, 0.3); /* Sombra más fuerte (Glow) */
    transform: translateY(-1px); /* Elevación sutil y controlada */
}

/* Eliminamos los efectos anteriores */
.btn-create::before {
    display: none;
}

/* Password Toggle */
.password-wrapper {
    position: relative;
    width: 100%;
}
.password-toggle-btn {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    color: #9ca3af;
    padding: 4px;
    display: flex;
}
.password-toggle-btn:hover { color: #4b5563; }

/* Alert */
.alert-error {
    background: #fef2f2;
    color: #dc2626;
    padding: 14px;
    border-radius: 8px;
    margin-bottom: 20px;
    border: 1px solid #fecaca;
    font-size: 14px;
}

/* Responsive */
@media (max-width: 576px) {
    .register-wrap { padding: 16px; margin: 20px 10px; }
    .register-card { padding: 20px; }
}
</style>

<div class="register-wrap">
    <h1 class="register-title">Iniciar Sesión</h1>

    <?php if ($error): ?>
        <div class="alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <?php if ($mensaje): ?>
        <div class="alert-error" style="background: #f0fdf4; color: #166534; border-color: #bbf7d0;">
            <i class="fas fa-check-circle"></i> <?php echo $mensaje; ?>
        </div>
    <?php endif; ?>

    <div class="register-card">
        <form method="POST" action="">
            <div class="field-group">
                <label for="telefono" class="field-label">Teléfono</label>
                <input type="tel" 
                       id="telefono" 
                       name="telefono" 
                       class="input-text" 
                       value="<?php echo isset($_POST['telefono']) ? htmlspecialchars(preg_replace('/[^0-9]/','',$_POST['telefono'])) : ''; ?>"
                       placeholder="Ej: 77712345"
                       pattern="^[67][0-9]{7}$"
                       inputmode="numeric"
                       maxlength="8"
                       autocomplete="tel">
            </div>

            <div class="field-group">
                <label for="password" class="field-label">Contraseña</label>
                <div class="password-wrapper">
                    <input type="password" 
                           id="password" 
                           name="password" 
                           class="input-text" 
                           placeholder="Tu contraseña"
                           autocomplete="current-password"
                           style="padding-right: 45px;">
                    <button type="button" class="password-toggle-btn" onclick="togglePassword('password', this)">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="eye-off">
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                            <line x1="1" y1="1" x2="23" y2="23"></line>
                        </svg>
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="eye-open" style="display: none;">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-submit">
                Iniciar Sesión
            </button>

            <div class="login-options">
                <label>
                    <input type="checkbox" name="recordar" id="recordar"> Recordarme
                </label>
                <a href="forgot_password.php">¿Olvidaste tu contraseña?</a>
            </div>
        </form>
    </div>

    <div class="create-account-section">
        <p class="create-account-text">¿Aún no tienes una cuenta?</p>
        <a href="register.php" class="btn-create">
            Crear Cuenta
        </a>
    </div>
</div>

<script>
function togglePassword(inputId, btn) {
    const input = document.getElementById(inputId);
    const eyeOff = btn.querySelector('.eye-off');
    const eyeOpen = btn.querySelector('.eye-open');
    
    if (input.type === 'password') {
        input.type = 'text';
        eyeOff.style.display = 'none';
        eyeOpen.style.display = 'block';
    } else {
        input.type = 'password';
        eyeOff.style.display = 'block';
        eyeOpen.style.display = 'none';
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
