// FIX para botones de FAVORITOS y COMPARTIR
// Este archivo corrige los problemas de los botones

document.addEventListener('DOMContentLoaded', function() {
    console.log('Inicializando fix de favoritos y compartir...');
    
    // FIX 1: Botones de FAVORITOS
    initFavoritesFixed();
    
    // FIX 2: Función de COMPARTIR
    window.shareProduct = shareProductFixed;
    
    console.log('Fix aplicado correctamente');
});

// SOLUCIÓN 1: Favoritos corregidos
function initFavoritesFixed() {
    const favoriteButtons = document.querySelectorAll('.btn-favorite');
    
    console.log(`Encontrados ${favoriteButtons.length} botones de favoritos`);
    
    favoriteButtons.forEach(button => {
        // Remover listeners anteriores
        button.replaceWith(button.cloneNode(true));
    });
    
    // Agregar nuevos listeners
    document.querySelectorAll('.btn-favorite').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log('Click en botón de favorito');
            toggleFavoriteFixed(this);
        });
    });
}

// Toggle favorito CORREGIDO
function toggleFavoriteFixed(button) {
    const productId = button.dataset.productId;
    const isActive = button.classList.contains('active');
    
    console.log(`Toggle favorito - Producto ID: ${productId}, Activo: ${isActive}`);
    
    if (!productId) {
        console.error('No se encontró product ID');
        showNotificationFixed('Error: ID de producto no encontrado', 'error');
        return;
    }
    
    // Deshabilitar botón temporalmente
    button.disabled = true;
    const originalHTML = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
    
    // Enviar al servidor con FormData (formato correcto)
    const formData = new FormData();
    formData.append('product_id', productId);
    
    fetch('/api/toggle_favorite.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Respuesta recibida:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Datos recibidos:', data);
        
        if (data.success) {
            // Cambiar estado visual
            button.classList.toggle('active');
            
            // Actualizar icono y texto
            if (data.action === 'added') {
                button.innerHTML = '<i class="fas fa-heart"></i> Favorito';
                button.classList.add('active');
                showNotificationFixed('✓ Agregado a favoritos', 'success');
            } else {
                button.innerHTML = '<i class="far fa-heart"></i> Favorito';
                button.classList.remove('active');
                showNotificationFixed('✓ Eliminado de favoritos', 'success');
            }
        } else {
            console.error('Error del servidor:', data.message);
            button.innerHTML = originalHTML;
            showNotificationFixed('Error: ' + (data.message || 'No se pudo procesar'), 'error');
        }
    })
    .catch(error => {
        console.error('Error en la petición:', error);
        button.innerHTML = originalHTML;
        showNotificationFixed('Error de conexión. Intenta de nuevo.', 'error');
    })
    .finally(() => {
        button.disabled = false;
    });
}

// SOLUCIÓN 2: Compartir corregido
function shareProductFixed(productId, title) {
    console.log(`Compartir producto - ID: ${productId}, Título: ${title}`);
    
    const url = window.location.href;
    const text = `${title} - CAMBALACHE`;
    
    // Opción 1: Web Share API (móviles modernos)
    if (navigator.share) {
        navigator.share({
            title: text,
            text: `Mira este producto en CAMBALACHE: ${title}`,
            url: url
        })
        .then(() => {
            console.log('Compartido exitosamente');
            showNotificationFixed('✓ Compartido exitosamente', 'success');
        })
        .catch((error) => {
            console.log('Error al compartir o cancelado:', error);
            // Si cancela, no mostrar error
            if (error.name !== 'AbortError') {
                fallbackShare(url, title);
            }
        });
    } else {
        // Opción 2: Fallback para desktop
        fallbackShare(url, title);
    }
}

// Fallback para compartir (copiar al portapapeles)
function fallbackShare(url, title) {
    // Intentar copiar al portapapeles
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url)
            .then(() => {
                showNotificationFixed('✓ Enlace copiado al portapapeles', 'success');
            })
            .catch(() => {
                // Si falla, mostrar modal con opciones
                showShareModal(url, title);
            });
    } else {
        // Si no hay clipboard API, mostrar modal
        showShareModal(url, title);
    }
}

// Modal de compartir
function showShareModal(url, title) {
    const whatsappUrl = `https://wa.me/?text=${encodeURIComponent(title + ' - ' + url)}`;
    const facebookUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`;
    const twitterUrl = `https://twitter.com/intent/tweet?text=${encodeURIComponent(title)}&url=${encodeURIComponent(url)}`;
    
    const modal = document.createElement('div');
    modal.className = 'share-modal';
    modal.innerHTML = `
        <div class="share-modal-content">
            <div class="share-modal-header">
                <h3>Compartir producto</h3>
                <button class="btn-close-share" onclick="this.closest('.share-modal').remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="share-modal-body">
                <div class="share-options">
                    <a href="${whatsappUrl}" target="_blank" class="share-option whatsapp">
                        <i class="fab fa-whatsapp"></i>
                        <span>WhatsApp</span>
                    </a>
                    <a href="${facebookUrl}" target="_blank" class="share-option facebook">
                        <i class="fab fa-facebook"></i>
                        <span>Facebook</span>
                    </a>
                    <a href="${twitterUrl}" target="_blank" class="share-option twitter">
                        <i class="fab fa-twitter"></i>
                        <span>Twitter</span>
                    </a>
                    <button onclick="copyToClipboardManual('${url}')" class="share-option copy">
                        <i class="fas fa-copy"></i>
                        <span>Copiar enlace</span>
                    </button>
                </div>
                <div class="share-url">
                    <input type="text" value="${url}" readonly onclick="this.select()">
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Cerrar al hacer click fuera
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.remove();
        }
    });
}

// Copiar al portapapeles manualmente
window.copyToClipboardManual = function(text) {
    const input = document.createElement('input');
    input.value = text;
    document.body.appendChild(input);
    input.select();
    
    try {
        document.execCommand('copy');
        showNotificationFixed('✓ Enlace copiado', 'success');
    } catch (err) {
        showNotificationFixed('No se pudo copiar. Selecciona y copia manualmente.', 'error');
    }
    
    document.body.removeChild(input);
};

// Mostrar notificaciones
function showNotificationFixed(message, type = 'info') {
    // Remover notificaciones anteriores
    const oldNotifications = document.querySelectorAll('.notification-fixed');
    oldNotifications.forEach(n => n.remove());
    
    const notification = document.createElement('div');
    notification.className = `notification-fixed notification-${type}`;
    
    const icon = type === 'success' ? 'fa-check-circle' : 
                 type === 'error' ? 'fa-exclamation-circle' : 
                 'fa-info-circle';
    
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas ${icon}"></i>
            <span>${message}</span>
            <button class="btn-close-notification" onclick="this.parentNode.parentNode.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Animar entrada
    setTimeout(() => notification.classList.add('show'), 10);
    
    // Auto-remover después de 5 segundos
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 300);
    }, 5000);
}

// Estilos para notificaciones y modal
const styles = document.createElement('style');
styles.textContent = `
/* Notificaciones */
.notification-fixed {
    position: fixed;
    top: 20px;
    right: 20px;
    background: white;
    padding: 1rem 1.5rem;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 10000;
    transform: translateX(400px);
    transition: transform 0.3s ease;
    max-width: 350px;
}

.notification-fixed.show {
    transform: translateX(0);
}

.notification-fixed.notification-success {
    border-left: 4px solid #28a745;
}

.notification-fixed.notification-error {
    border-left: 4px solid #dc3545;
}

.notification-fixed.notification-info {
    border-left: 4px solid #17a2b8;
}

.notification-content {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.notification-content i:first-child {
    font-size: 1.25rem;
}

.notification-success .notification-content i:first-child {
    color: #28a745;
}

.notification-error .notification-content i:first-child {
    color: #dc3545;
}

.notification-info .notification-content i:first-child {
    color: #17a2b8;
}

.notification-content span {
    flex: 1;
    font-size: 0.95rem;
}

.btn-close-notification {
    background: none;
    border: none;
    color: #6c757d;
    cursor: pointer;
    padding: 0;
    font-size: 1rem;
}

.btn-close-notification:hover {
    color: #000;
}

/* Modal de compartir */
.share-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 1rem;
}

.share-modal-content {
    background: white;
    border-radius: 12px;
    max-width: 500px;
    width: 100%;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
}

.share-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid #dee2e6;
}

.share-modal-header h3 {
    margin: 0;
    font-size: 1.25rem;
}

.btn-close-share {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #6c757d;
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}

.btn-close-share:hover {
    background: #f8f9fa;
    color: #000;
}

.share-modal-body {
    padding: 1.5rem;
}

.share-options {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.share-option {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem;
    border: 2px solid #dee2e6;
    border-radius: 8px;
    text-decoration: none;
    color: #495057;
    transition: all 0.3s;
    cursor: pointer;
    background: white;
}

.share-option:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.share-option i {
    font-size: 2rem;
}

.share-option.whatsapp:hover {
    border-color: #25D366;
    color: #25D366;
}

.share-option.facebook:hover {
    border-color: #1877f2;
    color: #1877f2;
}

.share-option.twitter:hover {
    border-color: #1da1f2;
    color: #1da1f2;
}

.share-option.copy:hover {
    border-color: #ff6b1a;
    color: #ff6b1a;
}

.share-url {
    margin-top: 1rem;
}

.share-url input {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    font-size: 0.9rem;
    background: #f8f9fa;
}

.share-url input:focus {
    outline: none;
    border-color: #ff6b1a;
    background: white;
}

/* Responsive */
@media (max-width: 576px) {
    .notification-fixed {
        right: 10px;
        left: 10px;
        max-width: none;
    }
    
    .share-options {
        grid-template-columns: 1fr;
    }
}

/* Botón favorito activo */
.btn-favorite.active {
    background: #dc3545 !important;
    border-color: #dc3545 !important;
    color: white !important;
}

.btn-favorite.active:hover {
    background: #c82333 !important;
    border-color: #bd2130 !important;
}
`;

document.head.appendChild(styles);

console.log('Fix de favoritos y compartir cargado completamente');
