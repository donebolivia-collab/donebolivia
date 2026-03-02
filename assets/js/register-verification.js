/**
 * Sistema de verificación de teléfono por WhatsApp
 * Para formulario de registro de CAMBALACHE
 */

(function() {
    'use strict';
    
    // Elementos del DOM
    const btnSendOTP = document.getElementById('btnSendOTP');
    const btnVerifyOTP = document.getElementById('btnVerifyOTP');
    const btnResendOTP = document.getElementById('btnResendOTP');
    const btnSubmit = document.getElementById('btnSubmit');
    const emailInput = document.getElementById('email');
    const telefonoInput = document.getElementById('telefono');
    const otpHidden = document.getElementById('otp_code');
    const otpCells = Array.from(document.querySelectorAll('.otp-cell'));
    const step1 = document.getElementById('step1');
    const step2 = document.getElementById('step2');
    const step3 = document.getElementById('step3');
    // Stepper blocks
    const block1 = document.getElementById('step-1');
    const block2 = document.getElementById('step-2');
    const block3 = document.getElementById('step-3');
    const block4 = document.getElementById('step-4');
    const locationWrapper = document.getElementById('location-wrapper');
    const btnStep1Next = document.getElementById('btnStep1Next');
    const btnStep2Next = document.getElementById('btnStep2Next');
    const otpTimer = document.getElementById('otpTimer');
    
    let timerInterval = null;
    let otpVerificado = false;
    const CHANNEL = (window.OTP_CHANNEL || 'email').toLowerCase();
    const termsCheckbox = document.getElementById('terms');
    
    // Helper: verificar si teléfono existe
    async function phoneExiste(localDigits) {
        try {
            const clean = (localDigits || '').replace(/[^0-9]/g, '');
            if (!/^[67][0-9]{7}$/.test(clean)) return false; // no bloquear si es inválido
            const res = await fetch(`/api/check_phone_exists.php?telefono=${encodeURIComponent(clean)}`);
            const data = await res.json();
            if (!data.success) return false;
            return !!data.exists;
        } catch (e) {
            console.error('Error checando teléfono:', e);
            return false;
        }
    }

    // Ajuste de textos según canal
    try {
        const headerTitle = document.querySelector('.verification-header span');
        if (headerTitle) headerTitle.textContent = CHANNEL === 'whatsapp' ? 'Verificación por WhatsApp' : 'Verificación por Email';
        if (btnSendOTP) btnSendOTP.innerHTML = CHANNEL === 'whatsapp'
            ? '<i class="fab fa-whatsapp"></i> Enviar código por WhatsApp'
            : '<i class="fas fa-paper-plane"></i> Enviar código por Email';
        const info = document.querySelector('.verification-info');
        if (info) info.innerHTML = CHANNEL === 'whatsapp'
            ? '<i class="fas fa-shield-alt"></i> Enviaremos un código de 6 dígitos a tu WhatsApp'
            : '<i class="fas fa-shield-alt"></i> Enviaremos un código de 6 dígitos a tu correo';
    } catch (_) {}

    // Sanitizar input de teléfono y validar duplicado en blur (paso 3)
    if (telefonoInput) {
        telefonoInput.addEventListener('input', () => {
            telefonoInput.value = telefonoInput.value.replace(/[^0-9]/g, '').slice(0,8);
        });
        telefonoInput.addEventListener('blur', async () => {
            const tel = (telefonoInput.value || '').trim();
            if (!/^[67][0-9]{7}$/.test(tel)) return;
            const exists = await phoneExiste(tel);
            if (exists) {
                mostrarAlerta('error', 'Este número ya está registrado');
            }
        });
    }

    // Navegación de pasos
    function goToStep(n) {
        if (block1) block1.style.display = (n === 1) ? 'block' : 'none';
        if (block2) block2.style.display = (n === 2) ? 'block' : 'none';
        if (block3) block3.style.display = (n === 3) ? 'block' : 'none';
        if (block4) block4.style.display = (n === 4) ? 'block' : 'none';
        if (locationWrapper) locationWrapper.style.display = (n === 2) ? 'block' : 'none';
        // Scroll a la sección visible
        const target = [block1, block2, block3, block4].find((b, idx) => b && (idx+1) === n);
        if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // Chequeo asíncrono de email duplicado
    async function emailExiste(email) {
        try {
            const res = await fetch(`/api/check_email_exists.php?email=${encodeURIComponent(email)}`);
            const data = await res.json();
            if (!data.success) return false; // en caso de error del endpoint, no bloquear
            return !!data.exists;
        } catch (e) {
            console.error('Error checando email:', e);
            return false; // no bloquear por errores de red
        }
    }

    // Validación de email con debounce (sin bloquear el botón)
    if (emailInput) {
        let emailDebounce;
        async function validarEmailAsync() {
            const email = emailInput.value.trim();
            if (!email || !/^\S+@\S+\.\S+$/.test(email)) {
                emailInput.setCustomValidity('');
                return;
            }
            const exists = await emailExiste(email.toLowerCase());
            if (exists) {
                emailInput.setCustomValidity('Este email ya está registrado');
                emailInput.reportValidity();
            } else {
                emailInput.setCustomValidity('');
            }
            // No bloquear el botón; el click hará la validación final
        }
        emailInput.addEventListener('input', function() {
            if (emailDebounce) clearTimeout(emailDebounce);
            emailDebounce = setTimeout(validarEmailAsync, 400);
        });
        emailInput.addEventListener('blur', validarEmailAsync);
    }

    // Avanzar Paso 1 → Paso 2
    if (btnStep1Next) {
        btnStep1Next.addEventListener('click', async function() {
            const nombre = document.getElementById('nombre');
            // Validar campos básicos mediante HTML5 (solo nombre y email en Paso 1)
            if (!nombre.checkValidity()) { nombre.reportValidity(); return; }
            if (!emailInput.checkValidity()) { emailInput.reportValidity(); return; }
            // Chequear email duplicado antes de continuar
            const email = emailInput.value.trim().toLowerCase();
            if (!/^\S+@\S+\.\S+$/.test(email)) { emailInput.reportValidity(); return; }
            const exists = await emailExiste(email);
            if (exists) {
                emailInput.setCustomValidity('Este email ya está registrado');
                emailInput.reportValidity();
                return;
            } else {
                emailInput.setCustomValidity('');
            }
            goToStep(2);
        });
    }

    // Avanzar Paso 2 → Paso 3 (requiere departamento y municipio)
    if (btnStep2Next) {
        btnStep2Next.addEventListener('click', function() {
            const departamento = document.getElementById('departamento');
            const municipio = document.getElementById('municipio');
            if (!departamento.value) { departamento.reportValidity(); return; }
            if (!municipio.value) { municipio.reportValidity(); return; }
            goToStep(3);
        });
    }
    
    // Enviar código OTP
    async function enviarOTP() {
        // Reset previo: limpiar temporizador y estados para evitar duplicados/locks
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
        otpVerificado = false;
        if (btnVerifyOTP) {
            btnVerifyOTP.disabled = false;
            btnVerifyOTP.innerHTML = '<i class="fas fa-check-circle"></i> Verificar código';
        }
        let endpoint = '/api/send_otp_email.php';
        let body = '';
        if (CHANNEL === 'whatsapp') {
            const tel = (telefonoInput?.value || '').trim();
            if (!tel || !/^[67][0-9]{7}$/.test(tel)) {
                mostrarAlerta('error', 'Por favor ingresa un teléfono boliviano válido (70123456)');
                telefonoInput?.focus();
                return;
            }
            // Chequear duplicado antes de enviar OTP
            const dup = await phoneExiste(tel);
            if (dup) {
                mostrarAlerta('error', 'Este número ya está registrado');
                telefonoInput?.focus();
                return;
            }
            endpoint = '/api/send_otp_whatsapp.php';
            body = 'telefono=' + encodeURIComponent(tel);
        } else {
            const email = emailInput.value.trim();
            if (!email || !/^\S+@\S+\.\S+$/.test(email)) {
                mostrarAlerta('error', 'Por favor ingresa un email válido');
                emailInput.focus();
                return;
            }
            body = 'email=' + encodeURIComponent(email);
        }
        
        // Deshabilitar botón
        btnSendOTP.disabled = true;
        btnSendOTP.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
        
        // Enviar solicitud
        fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Mostrar paso de verificación (paso 3 dentro del bloque de verificación)
                if (step1 && step2) {
                    step1.style.display = 'none';
                    step2.style.display = 'block';
                }
                
                // Iniciar temporizador (único)
                iniciarTemporizador(data.expira_en || 300);
                
                // Limpiar y enfocar primera casilla OTP
                clearOtp();
                focusOtp(0);
                
                // MODO DEMO FORZADO: mostrar mensaje de desarrollo (verde) y autollenar 123456
                setInlineMsg('success', 'Verificación por WhatsApp se encuentra en desarrollo. Por ahora, el código de verificación es 123456.');
                const digits = ('123456').split('');
                otpCells.forEach((c, i) => c.value = digits[i] || '');
                // Habilitar botón para que el usuario confirme manualmente
                if (btnVerifyOTP) btnVerifyOTP.disabled = false;

                // Cooldown de reenvío 30s
                iniciarCooldownReenvio(30);
            } else {
                setInlineMsg('error', data.message || 'No se pudo enviar el código.');
                btnSendOTP.disabled = false;
                btnSendOTP.innerHTML = CHANNEL === 'whatsapp'
                    ? '<i class="fab fa-whatsapp"></i> Enviar código por WhatsApp'
                    : '<i class="fas fa-paper-plane"></i> Enviar código por Email';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            setInlineMsg('error', 'Error al enviar el código. Intenta nuevamente.');
            btnSendOTP.disabled = false;
            btnSendOTP.innerHTML = CHANNEL === 'whatsapp'
                ? '<i class="fab fa-whatsapp"></i> Enviar código por WhatsApp'
                : '<i class="fas fa-paper-plane"></i> Enviar código por Email';
        });
    }

    btnSendOTP.addEventListener('click', enviarOTP);
    
    // Verificar código OTP
    btnVerifyOTP.addEventListener('click', function() {
        const email = emailInput.value.trim();
        const tel = (telefonoInput?.value || '').trim();
        const otp = getOtp();
        
        // Validar OTP
        if (!otp || !/^\d{6}$/.test(otp)) {
            mostrarAlerta('error', 'Por favor ingresa el código de 6 dígitos');
            focusOtp(firstEmptyIndex());
            return;
        }
        
        // Deshabilitar botón
        btnVerifyOTP.disabled = true;
        btnVerifyOTP.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verificando...';
        
        // Enviar solicitud
        fetch('/api/verify_otp.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: (CHANNEL === 'whatsapp'
                ? ('telefono=' + encodeURIComponent(tel) + '&otp=' + encodeURIComponent(otp))
                : ('email=' + encodeURIComponent(email) + '&otp=' + encodeURIComponent(otp)))
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Detener temporizador
                if (timerInterval) {
                    clearInterval(timerInterval);
                }

                // Marcar como verificado
                otpVerificado = true;

                // Avanzar al Paso 4 (contraseña y términos)
                btnSubmit.disabled = false;
                goToStep(4);

                // Bloquear edición pero mantener envío en POST
                if (telefonoInput) telefonoInput.setAttribute('readonly', 'true');
                if (emailInput) emailInput.setAttribute('readonly', 'true');

                mostrarAlerta('success', data.message);
            } else {
                mostrarAlerta('error', data.message);
                btnVerifyOTP.disabled = false;
                btnVerifyOTP.innerHTML = '<i class="fas fa-check-circle"></i> Verificar código';
                clearOtp();
                focusOtp(0);
            }
        })
        .catch(err => {
            console.error('Error verificando OTP:', err);
            setInlineMsg('error', 'No se pudo verificar el código. Intenta nuevamente.');
            btnVerifyOTP.disabled = false;
            btnVerifyOTP.innerHTML = '<i class="fas fa-check-circle"></i> Verificar código';
        });
    });
    
    // Reenviar código OTP (usa el mismo endpoint y aplica cooldown)
    btnResendOTP.addEventListener('click', function() {
        enviarOTP();
        clearOtp();
        focusOtp(0);
    });

    // Lógica OTP de 6 casillas
    function focusOtp(idx) {
        if (otpCells[idx]) otpCells[idx].focus();
    }

    function firstEmptyIndex() {
        for (let i = 0; i < otpCells.length; i++) {
            if (!otpCells[i].value) return i;
        }
        return otpCells.length - 1;
    }

    function getOtp() {
        const code = otpCells.map(c => c.value.replace(/[^\d]/g, '')).join('');
        if (otpHidden) otpHidden.value = code; // compat
        return code;
    }

    function clearOtp() {
        otpCells.forEach(c => c.value = '');
        if (otpHidden) otpHidden.value = '';
    }

    // Eventos por casilla: solo dígitos, autoadvance y backspace
    otpCells.forEach((cell, idx) => {
        cell.addEventListener('input', (e) => {
            cell.value = cell.value.replace(/[^\d]/g, '');
            if (cell.value && idx < otpCells.length - 1) {
                focusOtp(idx + 1);
            }
            const code = getOtp();
            if (code.length === 6) {
                btnVerifyOTP.click();
            }
        });

        cell.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !cell.value && idx > 0) {
                focusOtp(idx - 1);
            }
        });

        // Pegar 6 dígitos en cualquier casilla
        cell.addEventListener('paste', (e) => {
            const paste = (e.clipboardData || window.clipboardData).getData('text');
            if (!paste) return;
            e.preventDefault();
            const digits = paste.replace(/[^\d]/g, '').slice(0, 6).split('');
            otpCells.forEach((c, i) => c.value = digits[i] || '');
            const code = getOtp();
            if (code.length === 6) {
                btnVerifyOTP.click();
            } else {
                focusOtp(firstEmptyIndex());
            }
        });
    });
    
    // Iniciar temporizador de expiración
    function iniciarTemporizador(segundos) {
        let tiempoRestante = segundos;
        // Deshabilitar botón de reenvío durante el ciclo
        btnResendOTP.disabled = true;
        // Asegurar que Verificar esté habilitado mientras no expire
        if (btnVerifyOTP) btnVerifyOTP.disabled = false;

        // Limpiar cualquier temporizador previo
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }

        // Función de render del timer
        const render = () => {
            const minutos = Math.floor(tiempoRestante / 60);
            const segs = tiempoRestante % 60;
            otpTimer.innerHTML = `<i class="fas fa-clock"></i> Código expira en ${minutos}:${segs.toString().padStart(2, '0')}`;
        };
        render();

        timerInterval = setInterval(() => {
            tiempoRestante--;
            if (tiempoRestante <= 0) {
                clearInterval(timerInterval);
                timerInterval = null;
                otpTimer.innerHTML = '<i class="fas fa-exclamation-circle"></i> Código expirado';
                btnResendOTP.disabled = false;
                if (btnVerifyOTP) btnVerifyOTP.disabled = true;
                return;
            }
            render();
        }, 1000);
    }

    // Cooldown para reenvío (evita duplicados)
    function iniciarCooldownReenvio(segundos) {
        let restante = segundos;
        btnResendOTP.disabled = true;
        const original = CHANNEL === 'whatsapp' ? 'Reenviar código por WhatsApp' : 'Reenviar código por Email';
        btnResendOTP.innerHTML = `<i class="fas fa-redo"></i> ${original} (${restante}s)`;
        const timer = setInterval(() => {
            restante--;
            if (restante <= 0) {
                clearInterval(timer);
                btnResendOTP.disabled = false;
                btnResendOTP.innerHTML = CHANNEL === 'whatsapp'
                    ? '<i class="fab fa-whatsapp"></i> Reenviar código por WhatsApp'
                    : '<i class="fas fa-paper-plane"></i> Reenviar código por Email';
            } else {
                btnResendOTP.innerHTML = `<i class=\"fas fa-redo\"></i> ${original} (${restante}s)`;
            }
        }, 1000);
    }

    // Mostrar alertas (redirige al inline)
    function mostrarAlerta(tipo, mensaje) {
        setInlineMsg(tipo === 'success' ? 'success' : 'error', mensaje);
    }

    // Mensaje inline dentro del card de verificación
    function setInlineMsg(kind, text) {
        const box = document.getElementById('otpInlineMsg');
        if (!box) return;
        box.className = 'otp-inline-msg';
        if (kind === 'success') box.classList.add('is-success');
        else if (kind === 'error') box.classList.add('is-error');
        else box.classList.add('is-info');
        box.innerHTML = `<i class="fas ${kind==='error' ? 'fa-exclamation-circle' : (kind==='success' ? 'fa-check-circle' : 'fa-info-circle')}"></i> ${text}`;
    }
    
    // Prevenir envío del formulario si no está verificado
    const form = document.getElementById('registerForm');
    form.addEventListener('submit', function(event) {
        if (!otpVerificado) {
            event.preventDefault();
            mostrarAlerta('error', CHANNEL === 'whatsapp' ? 'Debes verificar tu número de WhatsApp antes de continuar' : 'Debes verificar tu email antes de continuar');
            return false;
        }
    });
    
})();

// Estilos para animación
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    .alert-temp {
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        border-radius: 8px;
    }
    
    .alert-temp .btn-close {
        padding: 0.5rem;
    }
`;
document.head.appendChild(style);