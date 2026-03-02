<?php
// Evitar caché del navegador para desarrollo
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$titulo = "Registrarse";
require_once '../includes/header.php';
require_once '../config/ubicaciones_bolivia.php'; // Cargar ubicaciones

$departments = obtenerDepartamentosBolivia(); // Obtener departamentos para el select

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombres = limpiarEntrada($_POST['nombres']);
    $apellidos = limpiarEntrada($_POST['apellidos']);
    // Fecha de nacimiento por defecto (Usuario declara ser mayor de 18)
    $fecha_nacimiento = date('Y-m-d', strtotime('-18 years'));
    $email = limpiarEntrada($_POST['email']);
    $telefono = limpiarEntrada($_POST['telefono']);
    $password = $_POST['password'];
    
    // Ubicación
    $departamento_codigo = limpiarEntrada($_POST['departamento'] ?? '');
    $municipio_codigo = limpiarEntrada($_POST['municipio'] ?? '');

    // Combinar nombres y apellidos para el campo nombre
    $nombre = trim($nombres . ' ' . $apellidos);

    // Obtener nombres de ubicación
    $departamento_nombre = '';
    $municipio_nombre = '';
    
    if ($departamento_codigo && isset($departments[$departamento_codigo])) {
        $departamento_nombre = $departments[$departamento_codigo];
        // Validar municipio
        $municipios = obtenerMunicipiosDeDepartamento($departamento_codigo);
        foreach ($municipios as $mun) {
            if ($mun['codigo'] === $municipio_codigo) {
                $municipio_nombre = $mun['nombre'];
                break;
            }
        }
    }

    // Validaciones
    if (empty($nombres) || empty($apellidos) || empty($email) || empty($telefono) || empty($password)) {
        $error = 'Todos los campos son obligatorios';
    } elseif (empty($departamento_codigo) || empty($municipio_codigo)) {
        $error = 'Debes seleccionar tu ubicación (Departamento y Municipio)';
    } elseif (!validarEmail($email)) {
        $error = 'El email no es válido';
    } elseif (!validarTelefono($telefono)) {
        $error = 'El teléfono debe ser un número boliviano válido (ej: 70123456)';
    } elseif (strlen($password) < 4) {
        $error = 'La contraseña debe tener al menos 4 caracteres';
    } else {
        try {
            $db = getDB();
            // Normalizar teléfono a solo números (sin prefijo +591)
            $telefono_norm = preg_replace('/[^0-9]/', '', $telefono);

            $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);

            if ($stmt->fetch()) {
                $error = 'Este email ya está registrado';
            } else {
                $stmtTel = $db->prepare("SELECT id FROM usuarios WHERE telefono = ?");
                $stmtTel->execute([$telefono_norm]);
                if ($stmtTel->fetch()) {
                    $error = 'Este número ya está registrado';
                } else {
                    // === INSERT CRÍTICO CORREGIDO (ciudad_id = 0, telefono_verificado = 1) ===
                    $stmt = $db->prepare("
                        INSERT INTO usuarios (nombre, email, telefono, password, fecha_nacimiento, ciudad_id, telefono_verificado, departamento_codigo, municipio_codigo, departamento_nombre, municipio_nombre)
                        VALUES (?, ?, ?, ?, ?, 0, 1, ?, ?, ?, ?)
                    ");

                    $hashedPassword = hashPassword($password);

                    if ($stmt->execute([$nombre, $email, $telefono_norm, $hashedPassword, $fecha_nacimiento, $departamento_codigo, $municipio_codigo, $departamento_nombre, $municipio_nombre])) {
                        $usuario_id = $db->lastInsertId();

                        $_SESSION['usuario_id'] = $usuario_id;
                        $_SESSION['usuario_nombre'] = $nombre;

                        // Notificación a Telegram
                        require_once '../config/telegram_config.php';
                        $mensaje = "🚀 <b>Nuevo Usuario Registrado</b>\n\n" .
                                   "👤 <b>Nombre:</b> " . $nombre . "\n" .
                                   "📧 <b>Email:</b> " . $email . "\n" .
                                   "📱 <b>Tel:</b> " . $telefono_norm . "\n" .
                                   "🎂 <b>Edad:</b> Mayor de 18 (Declarada)\n" .
                                   "📍 <b>Ubicación:</b> " . $municipio_nombre . ", " . $departamento_nombre . "\n" .
                                   "📅 <b>Fecha:</b> " . date('d/m/Y H:i');
                        enviarNotificacionTelegram($mensaje);

                        redireccionar('../index.php?mensaje=registro_exitoso');
                    } else {
                        $error = 'Error al crear la cuenta. Inténtalo de nuevo.';
                    }
                }
            }
        } catch (Exception $e) {
            $error = 'Error del sistema. Inténtalo más tarde.';
            error_log("Error en registro: " . $e->getMessage());
        }
    }
}
?>

<style>
/* Contenedor con fondo gris igual a add_product.php */
.register-wrap {
    max-width: 750px;
    margin: 20px auto;
    padding: 20px;
    background: #f5f5f5;
    border-radius: 8px;
}
.register-title { text-align: center; font-size: 28px; font-weight: 700; margin-bottom: 30px; color: #2c3e50; }

/* Tarjetas blancas flotantes idénticas a add_product.php */
.register-card {
    background: white;
    border-radius: 12px;
    padding: 32px;
    margin-bottom: 24px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    border: 1px solid #e5e7eb;
    transition: all 0.3s ease;
}
.register-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
}

/* Títulos con estilo profesional idéntico a add_product.php */
.register-card h2 {
    font-size: 18px;
    font-weight: 700;
    margin: 0 0 20px 0;
    color: #2c3e50;
    border-bottom: 2px solid #f3f4f6;
    padding-bottom: 12px;
}

/* Inputs con estilo profesional idéntico a add_product.php */
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
.input-text:hover {
    border-color: #9ca3af;
}

.input-text.error-border {
    border-color: #dc2626;
    background: #fff5f5;
}

/* Ayuda contextual (estilo Discord) */
.input-helper {
    font-size: 13px;
    color: #64748b;
    margin-top: 0; /* Empezamos en 0 para animar el margen */
    max-height: 0; /* Altura inicial 0 */
    opacity: 0;
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); /* Transición súper suave */
    padding-left: 2px;
}

.input-helper.visible {
    margin-top: 4px;
    max-height: 20px; /* Altura suficiente para el texto */
    opacity: 1;
}

/* El mensaje de error también absoluto */
.section-error {
    display: none; /* Oculto por defecto, pero JS puede mostrarlo */
    color: #dc2626;
    font-size: 13px;
    margin-top: 6px;
    margin-bottom: 12px; /* Añadido para separar del siguiente campo */
    font-weight: 500;
}

/* Pseudo-elemento eliminado para evitar doble icono con JS */

/* Grid para nombres y apellidos en la misma fila */
.input-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; align-items: start; }

/* Estilos dinámicos estilo Discord */
.field-group {
    position: relative;
    margin-bottom: 24px; /* Espacio para el error absoluto */
}

/* --- ESTILOS MODERNOS PARA TOGGLE PASSWORD (FIXED) --- */
.password-wrapper {
    position: relative;
    width: 100%;
    display: flex;
    align-items: center;
}

.password-toggle-btn {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    padding: 4px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #9ca3af; /* Gris suave y moderno */
    transition: all 0.2s ease;
    z-index: 10;
    border-radius: 50%;
}

.password-toggle-btn:hover {
    color: #4b5563; /* Gris más oscuro al hover */
    background-color: #f3f4f6; /* Fondo sutil circular */
}

.password-toggle-btn:focus {
    outline: none;
    color: #ff6b1a; /* Naranja corporativo al foco */
}

.password-toggle-btn svg {
    width: 20px;
    height: 20px;
    stroke-width: 1.8px; /* Línea un poco más fina y elegante */
}
/* ---------------------------------------------------- */

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-5px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Estilos para Verificación Inline */
.verification-card {
    transition: all 0.3s ease;
}

.verification-controls {
    display: flex;
    justify-content: center; /* Centrado */
    margin-top: 10px;
}

.btn-send-code {
    background: #ff6b1a; /* Naranja corporativo */
    color: white;        /* Letras blancas */
    border: none;
    padding: 10px 24px;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s;
}

.btn-send-code:hover {
    background: #e85e00; /* Naranja un poco más oscuro al hover */
}

.otp-inline-wrapper {
    display: none; /* Oculto por defecto */
    margin-top: 20px;
    background: #f8fafc;
    padding: 24px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    animation: slideDown 0.4s ease forwards;
    text-align: center; /* Todo centrado por defecto */
}

.otp-inline-container {
    display: flex;
    gap: 8px;
    justify-content: center;
    margin-bottom: 20px;
}

.otp-input-inline {
    width: 42px;
    height: 50px;
    border: 2px solid #cbd5e1;
    border-radius: 8px;
    font-size: 20px;
    font-weight: bold;
    text-align: center;
    color: #334155;
    background: white;
}

.otp-input-inline:focus {
    border-color: #ff6b1a;
    outline: none;
    box-shadow: 0 0 0 3px rgba(255, 107, 26, 0.1);
}

.verification-success {
    display: none;
    align-items: center;
    justify-content: center;
    gap: 10px;
    background: #dcfce7;
    color: #166534;
    padding: 16px;
    border-radius: 8px;
    border: 1px solid #bbf7d0;
    margin-top: 10px;
    font-weight: 600;
    animation: slideDown 0.4s ease forwards;
}

.verification-success i {
    font-size: 20px;
}

/* Tooltip para botón deshabilitado */
.btn-disabled-wrapper {
    position: relative;
    display: inline-block;
    width: 100%;
}

/* SOLO mostrar tooltip si el botón está DESHABILITADO */
.btn-disabled-wrapper:hover .btn-submit:disabled + .tooltip-text {
    visibility: visible;
    opacity: 1;
    transform: translateY(-10px) translateX(-50%);
}

.tooltip-text {
    visibility: hidden;
    width: 220px;
    background-color: #333;
    color: #fff;
    text-align: center;
    border-radius: 6px;
    padding: 8px 0;
    position: absolute;
    z-index: 1;
    bottom: 100%;
    left: 50%;
    transform: translateY(0) translateX(-50%);
    opacity: 0;
    transition: opacity 0.3s, transform 0.3s;
    font-size: 13px;
    pointer-events: none;
}

.tooltip-text::after {
    content: "";
    position: absolute;
    top: 100%;
    left: 50%;
    margin-left: -5px;
    border-width: 5px;
    border-style: solid;
    border-color: #333 transparent transparent transparent;
}

/* Botones finales */
.form-buttons {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-top: 24px;
}

.btn-cancel {
    padding: 0;
    background: white;
    color: #666;
    border: 2px solid #d0d0d0;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    height: 50px;
    box-sizing: border-box;
    cursor: pointer;
}
.btn-cancel:hover {
    border-color: #999;
    color: #333;
}

.btn-submit {
    padding: 0 !important;
    background: #ff6b1a !important;
    color: white !important;
    border: none !important;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
}
.btn-submit:hover {
    background: #e85e00 !important;
}
.btn-submit:active {
    transform: scale(0.98);
}

/* Alert de error global */
.alert-error {
    background: #fef2f2;
    color: #dc2626;
    padding: 14px;
    border-radius: 8px;
    margin-bottom: 20px;
    border: 1px solid #fecaca;
}

/* Checkbox términos */
.terms-checkbox {
    margin: 24px 0 0 0;
    padding: 16px;
    background: white;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
}

.terms-checkbox label {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    cursor: pointer;
    font-size: 14px;
    color: #495057;
}

.terms-checkbox input[type="checkbox"] {
    margin-top: 3px;
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.terms-checkbox a {
    color: #ff6b1a;
    text-decoration: none;
    font-weight: 600;
}

.terms-checkbox a:hover {
    text-decoration: underline;
}

/* Link al final */
.text-center { text-align: center; }
.register-login-link {
    margin-top: 20px;
    color: #666;
    font-size: 15px;
}
.register-login-link a {
    color: #ff6b1a;
    font-weight: 600;
    text-decoration: none;
}
.register-login-link a:hover {
    text-decoration: underline;
}

/* Responsive */
@media (max-width: 768px) {
    .register-wrap { padding: 0 16px; }
    .register-card { padding: 24px 20px; }
    .register-title { font-size: 24px; margin-bottom: 24px; }
    .input-row { grid-template-columns: 1fr; }
    .form-buttons { grid-template-columns: 1fr; }
}

/* MODAL VERIFICACIÓN MODERNO */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.75); /* Fondo oscuro elegante */
    backdrop-filter: blur(4px);
    z-index: 10000;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.modal-content {
    background: white;
    width: 90%;
    max-width: 420px;
    padding: 40px 30px;
    border-radius: 16px;
    box-shadow: 0 20px 50px rgba(0,0,0,0.3);
    text-align: center;
    position: relative;
    transform: translateY(20px);
    transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.modal-title {
    font-size: 22px;
    font-weight: 800;
    color: #1a1a1a;
    margin-bottom: 12px;
}

.modal-text {
    font-size: 15px;
    color: #666;
    margin-bottom: 30px;
    line-height: 1.5;
}

.modal-text strong {
    color: #1a1a1a;
    font-weight: 600;
}

/* Inputs de Código (6 dígitos separados) */
.otp-container {
    display: flex;
    gap: 8px;
    justify-content: center;
    margin-bottom: 24px;
}

.otp-input {
    width: 45px;
    height: 55px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 24px;
    font-weight: bold;
    text-align: center;
    color: #333;
    transition: all 0.2s;
    background: #f8f9fa;
}

.otp-input:focus {
    border-color: #ff6b1a;
    background: white;
    outline: none;
    box-shadow: 0 0 0 4px rgba(255, 107, 26, 0.1);
    transform: translateY(-2px);
}

/* Botón del Modal */
.btn-verify {
    width: 100%;
    padding: 16px;
    background: #ff6b1a;
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 4px 15px rgba(255, 107, 26, 0.3);
}

.btn-verify:hover {
    background: #e85e00;
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(255, 107, 26, 0.4);
}

.btn-verify:disabled {
    background: #ccc;
    cursor: not-allowed;
    box-shadow: none;
}

.resend-link {
    margin-top: 20px;
    font-size: 14px;
    color: #666;
}

.resend-btn {
    background: none;
    border: none;
    color: #ff6b1a;
    font-weight: 600;
    cursor: pointer;
    padding: 0;
    text-decoration: none;
}

.resend-btn:hover {
    text-decoration: underline;
}

.resend-btn:disabled {
    color: #999;
    cursor: default;
    text-decoration: none;
}

/* Botón cerrar modal */
.close-modal {
    position: absolute;
    top: 15px;
    right: 20px;
    font-size: 28px;
    color: #ccc;
    cursor: pointer;
    background: none;
    border: none;
    padding: 0;
    transition: color 0.2s;
}

.close-modal:hover {
    color: #333;
}

/* CRITICAL FIXES FOR NATIVE EYE AND SELECTS */
/* Ocultar OJO NATIVO (Edge/IE) */
input::-ms-reveal,
input::-ms-clear {
    display: none !important;
}
input[type="password"]::-ms-reveal,
input[type="password"]::-ms-clear {
    display: none !important;
}

/* Ocultar OJO NATIVO (Chrome/Edge/WebKit) */
input[type="password"]::-webkit-credentials-auto-fill-button {
    visibility: hidden !important;
    display: none !important;
    pointer-events: none !important;
    position: absolute !important;
    right: 0 !important;
}

/* Estilo limpio para inputs de fecha */
input[type=number]::-webkit-inner-spin-button, 
input[type=number]::-webkit-outer-spin-button { 
    -webkit-appearance: none; 
    margin: 0; 
}
input[type=number] {
    -moz-appearance: textfield;
}
</style>

<div class="register-wrap">
    <!-- v2.0 UPDATED -->
    <h1 class="register-title">Crea tu cuenta</h1>

    <?php if ($error): ?>
        <div class="alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST" action="" id="registerForm" novalidate>

        <!-- TARJETA 1: DATOS PERSONALES (Perfil Completo) -->
        <div class="register-card">
            <h2>Datos Personales</h2>
            
            <!-- Nombres y Apellidos -->
            <div class="input-row">
                <div class="field-group">
                    <input type="text" name="nombres" id="nombres" placeholder="Nombres" class="input-text" value="<?php echo isset($_POST['nombres']) ? htmlspecialchars($_POST['nombres']) : ''; ?>" oninput="this.value = this.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '')">
                </div>
                <div class="field-group">
                    <input type="text" name="apellidos" id="apellidos" placeholder="Apellidos" class="input-text" value="<?php echo isset($_POST['apellidos']) ? htmlspecialchars($_POST['apellidos']) : ''; ?>" oninput="this.value = this.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '')">
                </div>
            </div>
            <div class="section-error" id="error-nombres"></div>

            <!-- Celular (Movido aquí) -->
            <div class="field-group">
                <input type="tel" name="telefono" id="telefono" placeholder="Número de celular (ej: 70123456)" pattern="^[67][0-9]{7}$" inputmode="numeric" maxlength="8" class="input-text" value="<?php echo isset($_POST['telefono']) ? htmlspecialchars($_POST['telefono']) : ''; ?>" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                <div class="input-helper">Este será el medio para gestionar tus compras y ventas.</div>
                <div class="section-error" id="error-telefono"></div>
            </div>

            <!-- Ubicación (Movido aquí) -->
            <div class="input-row">
                <div class="field-group">
                    <select name="departamento" id="departamento" class="input-text" style="background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23131313%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right 1rem center; background-size: .65em auto; padding-right: 2.5rem; -webkit-appearance: none; -moz-appearance: none; appearance: none;">
                        <option value="">Selecciona tu departamento</option>
                        <?php foreach ($departments as $codigo => $nombre): ?>
                            <option value="<?php echo $codigo; ?>" <?php echo (isset($_POST['departamento']) && $_POST['departamento'] === $codigo) ? 'selected' : ''; ?>>
                                <?php echo $nombre; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="section-error" id="error-departamento"></div>
                </div>

                <div class="field-group">
                    <select name="municipio" id="municipio" class="input-text" style="background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23131313%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right 1rem center; background-size: .65em auto; padding-right: 2.5rem; -webkit-appearance: none; -moz-appearance: none; appearance: none;" disabled>
                        <option value="">Selecciona primero tu departamento</option>
                    </select>
                    <div class="section-error" id="error-municipio"></div>
                </div>
            </div>
        </div>

        <!-- TARJETA 2: SEGURIDAD (Antes de validar) -->
        <div class="register-card">
            <h2>Seguridad de tu Cuenta</h2>
            <div class="field-group">
                <div class="password-wrapper">
                    <input type="password" name="password" id="password" placeholder="Crea tu contraseña (mínimo 4 caracteres)" class="input-text" style="padding-right: 45px;">
                    <button type="button" class="password-toggle-btn" onclick="togglePassword('password', this)">
                        <!-- Icono Ojo Cerrado (Por defecto) -->
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="eye-off">
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                            <line x1="1" y1="1" x2="23" y2="23"></line>
                        </svg>
                        <!-- Icono Ojo Abierto (Oculto inicialmente) -->
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="eye-open" style="display: none;">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </button>
                </div>
                <div class="input-helper">Usa al menos 4 caracteres. Recomendamos combinar letras, símbolos y números.</div>
            </div>
            <div class="section-error" id="error-password"></div>
        </div>

        <!-- TARJETA 3: VERIFICACIÓN DE CUENTA (Inline) -->
        <div class="register-card verification-card">
            <h2>Verificación de tu Cuenta</h2>
            
            <div class="field-group">
                <input type="email" name="email" id="email" placeholder="Correo electrónico" class="input-text" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" oninput="this.value = this.value.replace(/[^a-zA-Z0-9@._-]/g, '')">
                <div class="section-error" id="error-email"></div>
                
                <!-- Controles Iniciales -->
                <div class="verification-controls" id="emailControls">
                    <button type="button" id="btnSendCode" class="btn-send-code" disabled style="opacity: 0.5; cursor: not-allowed;">
                        Enviar Código de Verificación
                    </button>
                </div>
            </div>

            <!-- Wrapper OTP (Oculto) -->
            <div id="otpWrapper" class="otp-inline-wrapper">
                <p style="font-size: 14px; color: #334155; margin-bottom: 12px; text-align: center;">
                    Ingresa el código enviado a <strong id="displayEmailOtp">...</strong>
                    <button type="button" id="btnEditEmail" style="background:none; border:none; color:#ff6b1a; font-size:12px; text-decoration:underline; cursor:pointer; margin-left:8px;">(Editar)</button>
                </p>
                
                <div class="otp-inline-container">
                    <input type="text" class="otp-input-inline" maxlength="1" pattern="[0-9]" inputmode="numeric">
                    <input type="text" class="otp-input-inline" maxlength="1" pattern="[0-9]" inputmode="numeric">
                    <input type="text" class="otp-input-inline" maxlength="1" pattern="[0-9]" inputmode="numeric">
                    <input type="text" class="otp-input-inline" maxlength="1" pattern="[0-9]" inputmode="numeric">
                    <input type="text" class="otp-input-inline" maxlength="1" pattern="[0-9]" inputmode="numeric">
                    <input type="text" class="otp-input-inline" maxlength="1" pattern="[0-9]" inputmode="numeric">
                </div>
                
                <div id="inlineOtpError" style="color: #dc2626; font-size: 13px; text-align: center; margin-bottom: 10px; display: none;"></div>

                <div style="text-align: center;">
                    <button type="button" id="btnVerifyCode" class="btn-submit" style="height: 40px; font-size: 14px; width: 280px; margin: 0 auto;">
                        Verificar Código
                    </button>
                    <div style="margin-top: 12px;">
                        <button type="button" id="btnResendInline" style="background: none; border: none; color: #64748b; cursor: pointer; font-size: 13px;">Reenviar código</button>
                    </div>
                </div>
            </div>

            <!-- Mensaje de Éxito (Oculto) -->
            <div id="verificationSuccess" class="verification-success">
                <i class="fas fa-check-circle"></i>
                <span>¡Correo verificado correctamente!</span>
            </div>
        </div>

        <!-- TÉRMINOS Y CONDICIONES -->
        <div class="terms-checkbox">
            <label>
                <input type="checkbox" name="terms" id="terms">
                <span>Declaro ser mayor de 18 años y acepto los <a href="../pages/terms.php" target="_blank">Términos de Uso</a> y la <a href="../pages/privacy.php" target="_blank">Política de Privacidad</a></span>
            </label>
        </div>
        <div class="section-error" id="error-terms"></div>

        <!-- BOTONES FINALES -->
        <div class="form-buttons">
            <a href="/auth/login.php" class="btn-cancel">Cancelar</a>
            <div class="btn-disabled-wrapper">
                <button type="submit" class="btn-submit" id="btnSubmit" disabled style="opacity: 0.6; cursor: not-allowed; width: 100%;">Crear Cuenta</button>
                <span class="tooltip-text" id="tooltipSubmit">Completa la verificación del correo primero</span>
            </div>
        </div>

    </form>

    <div class="text-center register-login-link">
        ¿Ya tienes cuenta? <a href="/auth/login.php">Inicia sesión</a>
    </div>
</div>

<script>
// Función para alternar visibilidad de contraseña
function togglePassword(inputId, btn) {
    const input = document.getElementById(inputId);
    const eyeOff = btn.querySelector('.eye-off');
    const eyeOpen = btn.querySelector('.eye-open');
    
    if (input.type === 'password') {
        input.type = 'text';
        eyeOff.style.display = 'none';
        eyeOpen.style.display = 'block';
        btn.classList.add('active');
    } else {
        input.type = 'password';
        eyeOff.style.display = 'block';
        eyeOpen.style.display = 'none';
        btn.classList.remove('active');
    }
}

// Lógica de Verificación Inline (v2.0)
(function() {
    const form = document.getElementById('registerForm');
    const btnSubmit = document.getElementById('btnSubmit');
    
    // Elementos Inline
    const emailInput = document.getElementById('email');
    const btnSendCode = document.getElementById('btnSendCode');
    const emailControls = document.getElementById('emailControls');
    const otpWrapper = document.getElementById('otpWrapper');
    const displayEmailOtp = document.getElementById('displayEmailOtp');
    const inlineOtpError = document.getElementById('inlineOtpError');
    const btnVerifyCode = document.getElementById('btnVerifyCode');
    const btnResendInline = document.getElementById('btnResendInline');
    const verificationSuccess = document.getElementById('verificationSuccess');
    
    // API URL
    const API_URL = '/api'; 
    
    let isVerified = false;
    
    // Estado de validación de duplicados
    const validationState = {
        emailExists: false,
        phoneExists: false
    };

    // --- LÓGICA DE INPUTS OTP (6 DÍGITOS) ---
    const otpInputs = document.querySelectorAll('.otp-input-inline');
    
    otpInputs.forEach((input, index) => {
        // Al escribir
        input.addEventListener('input', (e) => {
            if (e.target.value.length === 1) {
                if (index < otpInputs.length - 1) {
                    otpInputs[index + 1].focus();
                }
            }
        });

        // Al borrar (Backspace)
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !e.target.value) {
                if (index > 0) {
                    otpInputs[index - 1].focus();
                }
            }
        });
        
        // Pegar código completo
        input.addEventListener('paste', (e) => {
            e.preventDefault();
            const text = (e.clipboardData || window.clipboardData).getData('text').replace(/[^0-9]/g, '');
            if(text.length === 6) {
                otpInputs.forEach((inp, i) => inp.value = text[i]);
                otpInputs[5].focus();
            }
        });
    });

    function getOtpCode() {
        let code = '';
        otpInputs.forEach(input => code += input.value);
        return code;
    }

    // --- PASO 1: ENVIAR CÓDIGO ---
    btnSendCode.addEventListener('click', function() {
        const email = emailInput.value.trim();
        const nombre = document.getElementById('nombres').value.trim();
        
        // Validar email básico antes de enviar
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!email || !emailRegex.test(email)) {
            // El botón debería estar deshabilitado, pero por seguridad:
            return;
        }
        
        // UI Loading
        btnSendCode.textContent = 'Enviando...';
        btnSendCode.disabled = true;
        
        const formData = new FormData();
        formData.append('email', email);
        formData.append('nombre', nombre);

        fetch(`${API_URL}/send_otp_email.php`, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Éxito: Mostrar OTP Inline
                document.getElementById('error-email').style.display = 'none';
                
                // Ocultar botón enviar
                emailControls.style.display = 'none';
                
                // Mostrar Wrapper OTP
                otpWrapper.style.display = 'block';
                displayEmailOtp.textContent = email;
                
                // Focus al primer input
                setTimeout(() => otpInputs[0].focus(), 100);
                
                // Iniciar cuenta atrás reenviar
                startCountdown();
            } else {
                mostrarError('error-email', data.message || 'Error al enviar código');
                btnSendCode.textContent = 'Enviar Código de Verificación';
                btnSendCode.disabled = false;
            }
        })
        .catch(err => {
            console.error(err);
            mostrarError('error-email', 'Error de conexión');
            btnSendCode.textContent = 'Enviar Código de Verificación';
            btnSendCode.disabled = false;
        });
    });

    // --- PASO 2: VERIFICAR CÓDIGO ---
    btnVerifyCode.addEventListener('click', function() {
        const codigo = getOtpCode();
        const email = emailInput.value.trim();
        
        if (codigo.length !== 6) {
            inlineOtpError.textContent = 'Ingresa los 6 dígitos';
            inlineOtpError.style.display = 'block';
            return;
        }
        
        btnVerifyCode.textContent = 'Verificando...';
        btnVerifyCode.disabled = true;
        
        const formData = new FormData();
        formData.append('email', email);
        formData.append('codigo', codigo);

        fetch(`${API_URL}/verify_otp_email.php`, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Éxito Total
                isVerified = true;
                
                // Ocultar OTP Wrapper
                otpWrapper.style.display = 'none';
                
                // Mostrar Success
                verificationSuccess.style.display = 'flex';
                
                // Bloquear Email
                emailInput.readOnly = true;
                emailInput.style.background = '#f0fdf4'; // Verde muy claro
                emailInput.style.borderColor = '#bbf7d0';
                
                // Actualizar estado del botón final
                updateSubmitState();
                
            } else {
                inlineOtpError.textContent = 'Código incorrecto o expirado';
                inlineOtpError.style.display = 'block';
                btnVerifyCode.textContent = 'Verificar Código';
                btnVerifyCode.disabled = false;
            }
        })
        .catch(err => {
            console.error(err);
            inlineOtpError.textContent = 'Error al verificar';
            inlineOtpError.style.display = 'block';
            btnVerifyCode.textContent = 'Verificar Código';
            btnVerifyCode.disabled = false;
        });
    });

    // Reenviar Inline
    let countdownInterval;
    function startCountdown() {
        let seconds = 60;
        clearInterval(countdownInterval);
        btnResendInline.disabled = true;
        btnResendInline.style.opacity = '0.5';
        
        countdownInterval = setInterval(() => {
            seconds--;
            btnResendInline.textContent = `Reenviar en ${seconds}s`;
            if (seconds <= 0) {
                clearInterval(countdownInterval);
                btnResendInline.textContent = 'Reenviar código';
                btnResendInline.disabled = false;
                btnResendInline.style.opacity = '1';
            }
        }, 1000);
    }
    
    btnResendInline.addEventListener('click', function() {
        // Simular click en enviar de nuevo (pero sin ocultar UI)
        const email = emailInput.value.trim();
        const nombre = document.getElementById('nombres').value.trim();
        
        btnResendInline.textContent = 'Enviando...';
        
        const formData = new FormData();
        formData.append('email', email);
        formData.append('nombre', nombre);

        fetch(`${API_URL}/send_otp_email.php`, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                startCountdown();
            } else {
                alert('Error al reenviar');
                btnResendInline.textContent = 'Reenviar código';
            }
        });
    });

    // Editar Email (Reset UI)
    const btnEditEmail = document.getElementById('btnEditEmail');
    if(btnEditEmail) {
        btnEditEmail.addEventListener('click', function(e) {
            e.preventDefault();
            // Reset verification
            isVerified = false;
            verificationSuccess.style.display = 'none';
            emailInput.readOnly = false;
            emailInput.style.background = 'white';
            emailInput.style.borderColor = '#d1d5db';
            
            // Ocultar OTP
            otpWrapper.style.display = 'none';
            // Mostrar controles
            emailControls.style.display = 'flex';
            btnSendCode.textContent = 'Enviar Código de Verificación';
            btnSendCode.disabled = false;
            // Enfocar email
            emailInput.focus();
            emailInput.select();
            
            // Actualizar estado botón final
            updateSubmitState();
        });
    }

    // --- LÓGICA DE UBICACIÓN (DEPARTAMENTO Y MUNICIPIO) ---
    const deptSelect = document.getElementById('departamento');
    const munSelect = document.getElementById('municipio');

    if (deptSelect && munSelect) {
        deptSelect.addEventListener('change', function() {
            const deptCode = this.value;
            munSelect.innerHTML = '<option value="">Cargando...</option>';
            munSelect.disabled = true;
            
            if (!deptCode) {
                munSelect.innerHTML = '<option value="">Selecciona primero tu departamento</option>';
                return;
            }
            
            fetch(`../api/get_municipios.php?departamento=${deptCode}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        munSelect.innerHTML = '<option value="">Selecciona un Municipio</option>';
                        data.data.forEach(mun => {
                            const option = document.createElement('option');
                            option.value = mun.codigo;
                            option.textContent = mun.nombre;
                            munSelect.appendChild(option);
                        });
                        munSelect.disabled = false;
                    } else {
                        munSelect.innerHTML = '<option value="">Error al cargar</option>';
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    munSelect.innerHTML = '<option value="">Error de conexión</option>';
                });
        });
        
        deptSelect.addEventListener('change', function() {
            const err = document.getElementById('error-departamento');
            if (err) err.style.display = 'none';
        });
        munSelect.addEventListener('change', function() {
            const err = document.getElementById('error-municipio');
            if (err) err.style.display = 'none';
        });
    }

    // --- VALIDACIÓN AJAX DE DUPLICADOS (TIEMPO REAL) ---
    
    // 1. Teléfono
    const telefonoInput = document.getElementById('telefono');
    if (telefonoInput) {
        telefonoInput.addEventListener('blur', function() {
            const val = this.value.replace(/[^0-9]/g, '');
            const telRegex = /^[67][0-9]{7}$/;
            
            // Validar formato primero
            if (val.length > 0 && !telRegex.test(val)) {
                 mostrarError('error-telefono', 'Ingresa un número de celular válido');
                 telefonoInput.classList.add('error-border');
                 // Bloquear botón
                 validationState.phoneExists = true; // Usamos esto para bloquear submit
                 updateSubmitState();
                 return;
            }

            if (val.length >= 8) { 
                fetch(`${API_URL}/check_phone_exists.php?telefono=${val}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.exists) {
                            mostrarError('error-telefono', 'Este número de celular ya está registrado');
                            telefonoInput.classList.add('error-border');
                            validationState.phoneExists = true;
                        } else {
                            const err = document.getElementById('error-telefono');
                            if (err && err.textContent.includes('registrado')) {
                                err.style.display = 'none';
                                telefonoInput.classList.remove('error-border');
                            }
                            validationState.phoneExists = false;
                        }
                        updateSubmitState(); // Actualizar botón final
                    })
                    .catch(console.error);
            }
        });
        
        // Reset estado al escribir
        telefonoInput.addEventListener('input', function() {
            // Limpiar errores visuales al escribir
            const err = document.getElementById('error-telefono');
            if(err) err.style.display = 'none';
            this.classList.remove('error-border');
            
            if (validationState.phoneExists) {
                 validationState.phoneExists = false;
                 updateSubmitState();
            }
        });
    }

    // 2. Email (Pre-verificación)
    if (emailInput) {
        emailInput.addEventListener('blur', function() {
            const val = this.value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            // Si hay algo escrito pero es inválido, mostramos error
            if (val.length > 0 && !emailRegex.test(val)) {
                mostrarError('error-email', 'Ingresa un correo válido');
                // Asegurar que el botón siga deshabilitado
                btnSendCode.disabled = true;
                btnSendCode.style.opacity = '0.5';
                btnSendCode.style.cursor = 'not-allowed';
                return;
            }

            if (emailRegex.test(val) && !emailInput.readOnly) {
                fetch(`${API_URL}/check_email_exists.php?email=${val}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.exists) {
                            mostrarError('error-email', 'Este correo ya está registrado');
                            emailInput.classList.add('error-border');
                            // Bloquear botón de código
                            btnSendCode.disabled = true;
                            btnSendCode.style.opacity = '0.5';
                            btnSendCode.style.cursor = 'not-allowed';
                            validationState.emailExists = true;
                        } else {
                            const err = document.getElementById('error-email');
                            if (err && err.textContent.includes('registrado')) {
                                err.style.display = 'none';
                                emailInput.classList.remove('error-border');
                            }
                            // Habilitar si no estaba enviando
                            if (btnSendCode.textContent !== 'Enviando...') {
                                btnSendCode.disabled = false;
                                btnSendCode.style.opacity = '1';
                                btnSendCode.style.cursor = 'pointer';
                            }
                            validationState.emailExists = false;
                        }
                        updateSubmitState();
                    })
                    .catch(console.error);
            }
        });
        
        // Reset al escribir
        emailInput.addEventListener('input', function() {
            const val = this.value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            // Lógica de desbloqueo del botón
            if (emailRegex.test(val)) {
                btnSendCode.disabled = false;
                btnSendCode.style.opacity = '1';
                btnSendCode.style.cursor = 'pointer';
            } else {
                btnSendCode.disabled = true;
                btnSendCode.style.opacity = '0.5';
                btnSendCode.style.cursor = 'not-allowed';
            }

            if (validationState.emailExists && !this.readOnly) {
                 validationState.emailExists = false;
                 const err = document.getElementById('error-email');
                 if(err) err.style.display = 'none';
                 this.classList.remove('error-border');
                 // Reevaluar botón tras limpiar error duplicado
                 if(emailRegex.test(val)) {
                    btnSendCode.disabled = false;
                    btnSendCode.style.opacity = '1';
                    btnSendCode.style.cursor = 'pointer';
                 }
                 updateSubmitState();
            } else {
                 // Si no es error de duplicado, también limpiar errores genéricos al escribir
                 const err = document.getElementById('error-email');
                 if(err && !err.textContent.includes('registrado')) {
                     err.style.display = 'none';
                 }
            }
        });
    }

    // --- LÓGICA DE ESTADO DEL BOTÓN Y TOOLTIP DINÁMICO ---
    const tooltipText = document.getElementById('tooltipSubmit');
    const allInputs = form.querySelectorAll('input, select');
    
    function updateSubmitState() {
        // 0. Validar Duplicados (Bloqueo crítico)
        if (validationState.phoneExists) {
            btnSubmit.disabled = true;
            btnSubmit.style.opacity = '0.6';
            btnSubmit.style.cursor = 'not-allowed';
            tooltipText.textContent = 'El número de celular ya está registrado';
            return;
        }

        // 1. Verificar Correo
        if (!isVerified) {
            btnSubmit.disabled = true;
            btnSubmit.style.opacity = '0.6';
            btnSubmit.style.cursor = 'not-allowed';
            tooltipText.textContent = 'Completa la verificación del correo primero';
            return;
        }

        // 2. Verificar Términos
        const termsChecked = document.getElementById('terms').checked;
        if (!termsChecked) {
            btnSubmit.disabled = true;
            btnSubmit.style.opacity = '0.6';
            btnSubmit.style.cursor = 'not-allowed';
            tooltipText.textContent = 'Debes aceptar los términos y condiciones';
            return;
        }

        // 3. Verificar Campos Vacíos (Básico)
        let hasEmptyFields = false;
        const requiredIds = ['nombres', 'apellidos', 'telefono', 'departamento', 'municipio', 'password'];
        
        for (const id of requiredIds) {
            const el = document.getElementById(id);
            if (!el || !el.value.trim()) {
                hasEmptyFields = true;
                break;
            }
        }

        if (hasEmptyFields) {
            btnSubmit.disabled = true;
            btnSubmit.style.opacity = '0.6';
            btnSubmit.style.cursor = 'not-allowed';
            tooltipText.textContent = 'Completa todos los campos obligatorios';
            return;
        }

        // ¡TODO LISTO!
        btnSubmit.disabled = false;
        btnSubmit.style.opacity = '1';
        btnSubmit.style.cursor = 'pointer';
        tooltipText.textContent = ''; // Ocultar tooltip
    }

    // Escuchar cambios en todo el formulario para actualizar estado
    allInputs.forEach(input => {
        input.addEventListener('input', updateSubmitState);
        input.addEventListener('change', updateSubmitState);
    });

    // Validación y Envío Final
    form.addEventListener('submit', function(e) {
        if (!isVerified) {
            e.preventDefault();
            return;
        }
        
        // Validar resto del formulario (Validación profunda)
        if (!validarFormulario()) {
            e.preventDefault();
        }
    });

    function validarFormulario() {
        let isValid = true;
        
        // Limpiar errores previos
        document.querySelectorAll('.section-error').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.input-text').forEach(el => el.classList.remove('error-border'));

        // Obtener valores
        const nombres = document.getElementById('nombres').value.trim();
        const apellidos = document.getElementById('apellidos').value.trim();
        const telefono = document.getElementById('telefono').value.trim();
        const password = document.getElementById('password').value;
        const terms = document.getElementById('terms').checked;
        const departamento = document.getElementById('departamento').value;
        const municipio = document.getElementById('municipio').value;

        // Validar Nombres
        if (!nombres) {
            mostrarError('error-nombres', 'Ingresa tu nombre');
            document.getElementById('nombres').classList.add('error-border');
            isValid = false;
        }
        if (!apellidos) {
            mostrarError('error-apellidos', 'Ingresa tu apellido');
            document.getElementById('apellidos').classList.add('error-border');
            isValid = false;
        }

        // Validar Fecha
        if (!fecha) {
            mostrarError('error-fecha', 'Ingresa tu fecha de nacimiento válida');
            // Marcar los inputs
            document.getElementById('dob_day').classList.add('error-border');
            document.getElementById('dob_month').classList.add('error-border');
            document.getElementById('dob_year').classList.add('error-border');
            isValid = false;
        } else {
             document.getElementById('dob_day').classList.remove('error-border');
             document.getElementById('dob_month').classList.remove('error-border');
             document.getElementById('dob_year').classList.remove('error-border');
        }

        // Validar Teléfono
        const telRegex = /^[67][0-9]{7}$/;
        if (!telefono || !telRegex.test(telefono)) {
            mostrarError('error-telefono', 'Ingresa un número de celular válido');
            document.getElementById('telefono').classList.add('error-border');
            isValid = false;
        }

        // Validar Ubicación
        if (!departamento) {
            mostrarError('error-departamento', 'Selecciona un departamento');
            document.getElementById('departamento').classList.add('error-border');
            isValid = false;
        }
        if (!municipio) {
            mostrarError('error-municipio', 'Selecciona un municipio');
            document.getElementById('municipio').classList.add('error-border');
            isValid = false;
        }

        // Validar Password
        if (password.length < 4) {
            mostrarError('error-password', 'Mínimo 4 caracteres');
            document.getElementById('password').classList.add('error-border');
            isValid = false;
        }

        // Validar Términos
        if (!terms) {
            mostrarError('error-terms', 'Debes aceptar los términos y declarar mayoría de edad');
            isValid = false;
        }

        return isValid;
    }

    function mostrarError(elementId, mensaje) {
        const el = document.getElementById(elementId);
        if (el) {
            // Si es un error de duplicado, edad o correo inválido, O SI ES ERROR DE FORMATO DE TELÉFONO, añadimos estilo caja
            if (mensaje.includes('registrado') || mensaje.includes('18 años') || mensaje === 'Ingresa un correo válido' || mensaje === 'Ingresa un número de celular válido') {
                el.innerHTML = '<i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i> ' + mensaje;
                el.style.background = '#fef2f2';
                el.style.padding = '10px';
                el.style.borderRadius = '6px';
                el.style.border = '1px solid #fee2e2';
                el.style.width = '100%';
                el.style.boxSizing = 'border-box';
                el.style.marginTop = '6px';
                el.style.marginBottom = '12px';
                el.style.display = 'flex';
                el.style.alignItems = 'center';
                el.style.color = '#dc2626';
                el.style.fontSize = '13px';
                el.style.fontWeight = '500';
            } else {
                // Reset estilos para errores normales
                el.textContent = mensaje;
                el.style.background = 'none';
                el.style.padding = '0';
                el.style.border = 'none';
                el.style.marginTop = '6px';
                el.style.marginBottom = '12px';
                el.style.display = 'block';
                el.style.color = '#dc2626';
                el.style.fontSize = '13px';
                el.style.fontWeight = '500';
            }
        }
    }

    // UX Helpers (Mantener lógica de helpers)
    const inputsWithHelpers = document.querySelectorAll('.field-group .input-text');
    inputsWithHelpers.forEach(input => {
        const getHelper = (el) => {
            let helper = el.nextElementSibling;
            while(helper && !helper.classList.contains('input-helper')) {
                helper = helper.nextElementSibling;
            }
            if(!helper) helper = el.closest('.field-group').querySelector('.input-helper');
            return helper;
        };
        
        input.addEventListener('focus', function() {
            const helper = getHelper(this);
            const errorDiv = this.closest('.field-group').querySelector('.section-error');
            const hasError = errorDiv && errorDiv.style.display === 'block';
            if (helper && !hasError) {
                helper.style.display = 'block';
                requestAnimationFrame(() => helper.classList.add('visible'));
            }
        });

        input.addEventListener('blur', function() {
            const helper = getHelper(this);
            if (helper) {
                helper.classList.remove('visible');
                setTimeout(() => {
                    if(!helper.classList.contains('visible')) helper.style.display = 'none';
                }, 300);
            }
        });
        
        input.addEventListener('input', function() {
             const helper = getHelper(this);
             const errorDiv = this.closest('.field-group').querySelector('.section-error');
             if(errorDiv) errorDiv.style.display = 'none';
             if(this.classList.contains('error-border')) this.classList.remove('error-border');
             if (helper) {
                 helper.style.display = 'block';
                 requestAnimationFrame(() => helper.classList.add('visible'));
             }
        });
    });

    // Checkbox términos limpiar error
    document.getElementById('terms').addEventListener('change', function() {
        document.getElementById('error-terms').style.display = 'none';
    });

})();
</script>

<?php require_once '../includes/footer.php'; ?>
