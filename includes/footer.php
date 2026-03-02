</main>
<footer class="footer-main" style="display:none"></footer>

<!-- PRO TOAST NOTIFICATION SYSTEM -->
<style>
.pro-toast {
    position: fixed;
    top: -100px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    padding: 12px 28px;
    border-radius: 50px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.08), 0 0 0 1px rgba(0,0,0,0.03);
    display: flex;
    align-items: center;
    gap: 12px;
    z-index: 9999;
    transition: top 0.8s cubic-bezier(0.19, 1, 0.22, 1); /* Apple-style spring */
    font-family: system-ui, -apple-system, sans-serif;
    min-width: 300px;
    justify-content: center;
}

.pro-toast.show {
    top: 32px;
}

.pro-toast-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #10b981; /* Success Green */
    font-size: 20px;
}

.pro-toast-content {
    display: flex;
    flex-direction: column;
}

.pro-toast-title {
    color: #111827;
    font-size: 14px;
    font-weight: 600;
    letter-spacing: -0.01em;
}

.pro-toast-subtitle {
    color: #6b7280;
    font-size: 12px;
    font-weight: 400;
}
</style>

<div id="proToast" class="pro-toast">
    <div class="pro-toast-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="20 6 9 17 4 12"></polyline>
        </svg>
    </div>
    <div class="pro-toast-content">
        <span class="pro-toast-title" id="toastTitle">Notificación</span>
        <span class="pro-toast-subtitle" id="toastMessage" style="display:none"></span>
    </div>
</div>

<script>
// Ejecutar inmediatamente al cargar, sin esperar al DOM completo por si acaso
(function() {
    function checkAndShowToast() {
        const urlParams = new URLSearchParams(window.location.search);
        const mensaje = urlParams.get('mensaje');
        
        if (mensaje === 'registro_exitoso') {
            // Pequeño delay para asegurar que la animación se vea
            setTimeout(() => {
                showToast('¡Cuenta creada correctamente!', 'Bienvenido.');
                // Limpiar URL
                window.history.replaceState({}, document.title, window.location.pathname);
            }, 500);
        }
    }

    // Intentar ejecutar ya
    checkAndShowToast();
    
    // Y también en DOMContentLoaded por seguridad
    document.addEventListener('DOMContentLoaded', checkAndShowToast);
})();

function showToast(title, subtitle = '') {
    const toast = document.getElementById('proToast');
    if(!toast) return; // Seguridad
    
    const titleEl = document.getElementById('toastTitle');
    const msgEl = document.getElementById('toastMessage');
    
    if(titleEl) titleEl.textContent = title;
    
    if (subtitle && msgEl) {
        msgEl.textContent = subtitle;
        msgEl.style.display = 'block';
    } else if(msgEl) {
        msgEl.style.display = 'none';
    }
    
    // Forzar reflow
    void toast.offsetWidth;
    
    // Añadir clase con un pequeño timeout para garantizar transición
    setTimeout(() => {
        toast.classList.add('show');
    }, 10);
    
    // Ocultar después de 4 segundos
    setTimeout(() => {
        toast.classList.remove('show');
    }, 4500);
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
<script src="/assets/js/main.js?v=fix-validation-2" defer></script>
</body>
</html>
