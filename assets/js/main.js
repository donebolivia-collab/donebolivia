// CAMBALACHE - JavaScript Principal
// Funcionalidades interactivas y responsive

document.addEventListener('DOMContentLoaded', function() {
    
    // Inicializar funcionalidades
    initSearchFunctionality();
    initProductCards();
    initForms();
    initImageUpload();
    initFilters();
    // initFavorites(); // Desactivado: manejado por favorites-share-fix.js
    initLazyLoading();
});

// Funcionalidad de búsqueda
function initSearchFunctionality() {
    const searchForms = document.querySelectorAll('.search-form');
    const searchInputs = document.querySelectorAll('input[name="q"]');
    
    // Autocompletado y sugerencias
    searchInputs.forEach(input => {
        let timeout;
        
        input.addEventListener('input', function() {
            clearTimeout(timeout);
            const query = this.value.trim();
            
            if (query.length >= 2) {
                timeout = setTimeout(() => {
                    showSearchSuggestions(query, this);
                }, 300);
            } else {
                hideSearchSuggestions();
            }
        });
        
        // Ocultar sugerencias al hacer clic fuera
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.search-suggestions')) {
                hideSearchSuggestions();
            }
        });
    });
    
    // Búsqueda por voz (si está disponible)
    if ('/* speech removed */' in window) {
        
    }
}

// Mostrar sugerencias de búsqueda
function showSearchSuggestions(query, input) {
    // Crear contenedor de sugerencias si no existe
    let suggestionsContainer = input.parentNode.querySelector('.search-suggestions');
    if (!suggestionsContainer) {
        suggestionsContainer = document.createElement('div');
        suggestionsContainer.className = 'search-suggestions';
        input.parentNode.appendChild(suggestionsContainer);
    }
    
    // Simular sugerencias (en producción, esto vendría del servidor)
    const suggestions = [
        'iPhone 14',
        'Samsung Galaxy',
        'Toyota Corolla',
        'Honda Civic',
        'Refrigeradora LG',
        'Laptop HP',
        'PlayStation 5'
    ].filter(item => item.toLowerCase().includes(query.toLowerCase()));
    
    if (suggestions.length > 0) {
        suggestionsContainer.innerHTML = suggestions
            .slice(0, 5)
            .map(suggestion => `
                <div class="suggestion-item" onclick="selectSuggestion('${suggestion}', this)">
                    <i class="fas fa-search"></i>
                    ${suggestion}
                </div>
            `).join('');
        
        suggestionsContainer.style.display = 'block';
    } else {
        hideSearchSuggestions();
    }
}

// Ocultar sugerencias
function hideSearchSuggestions() {
    const suggestions = document.querySelectorAll('.search-suggestions');
    suggestions.forEach(container => {
        container.style.display = 'none';
    });
}

// Seleccionar sugerencia
function selectSuggestion(suggestion, element) {
    const input = element.closest('.search-form').querySelector('input[name="q"]');
    input.value = suggestion;
    hideSearchSuggestions();
    element.closest('.search-form').submit();
}

// Funcionalidad de tarjetas de productos
function initProductCards() {
    const productCards = document.querySelectorAll('.product-card');
    
    productCards.forEach(card => {
        // Efecto hover mejorado
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
        
        // Click en la tarjeta para ver detalles
        card.addEventListener('click', function(e) {
            if (!e.target.closest('.product-actions')) {
                const productId = this.dataset.productId;
                if (productId) {
                    window.location.href = `/products/view_product.php?id=${productId}`;
                }
            }
        });
    });
}

// Funcionalidad de formularios
function initForms() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        // Ignorar validación global para el formulario de registro (tiene su propia lógica)
        if (form.id === 'registerForm') return;

        // Validación en tiempo real
        const inputs = form.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateField(this);
            });
            
            input.addEventListener('input', function() {
                clearFieldError(this);
            });
        });
        
        // Validación al enviar
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
            }
        });
    });
}

// Validar campo individual
function validateField(field) {
    const value = field.value.trim();
    const type = field.type;
    const required = field.hasAttribute('required');
    
    clearFieldError(field);
    
    if (required && !value) {
        showFieldError(field, 'Este campo es obligatorio');
        return false;
    }
    
    if (value) {
        switch (type) {
            case 'email':
                if (!isValidEmail(value)) {
                    showFieldError(field, 'Ingresa un email válido');
                    return false;
                }
                break;
            case 'tel':
                if (!isValidPhone(value)) {
                    showFieldError(field, 'Ingresa un teléfono válido');
                    return false;
                }
                break;
            case 'number':
                if (isNaN(value) || value < 0) {
                    showFieldError(field, 'Ingresa un número válido');
                    return false;
                }
                break;
        }
    }
    
    return true;
}

// Validar formulario completo
function validateForm(form) {
    const fields = form.querySelectorAll('input[required], textarea[required], select[required]');
    let isValid = true;
    
    fields.forEach(field => {
        if (!validateField(field)) {
            isValid = false;
        }
    });
    
    return isValid;
}

// Mostrar error en campo
function showFieldError(field, message) {
    field.classList.add('is-invalid');
    
    let errorDiv = field.parentNode.querySelector('.field-error');
    if (!errorDiv) {
        errorDiv = document.createElement('div');
        errorDiv.className = 'field-error text-danger small mt-1';
        field.parentNode.appendChild(errorDiv);
    }
    
    errorDiv.textContent = message;
}

// Limpiar error de campo
function clearFieldError(field) {
    field.classList.remove('is-invalid');
    const errorDiv = field.parentNode.querySelector('.field-error');
    if (errorDiv) {
        errorDiv.remove();
    }
}

// Validaciones específicas
function isValidEmail(email) {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(email);
}

function isValidPhone(phone) {
    const regex = /^(\+?591)?[67]\d{7}$/;
    return regex.test(phone.replace(/\s/g, ''));
}

// Funcionalidad de subida de imágenes
function initImageUpload() {
    const imageInputs = document.querySelectorAll('input[type="file"][accept*="image"]');
    
    imageInputs.forEach(input => {
        input.addEventListener('change', function() {
            handleImageUpload(this);
        });
        
        // Drag and drop
        const container = input.closest('.image-upload-container');
        if (container) {
            container.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('drag-over');
            });
            
            container.addEventListener('dragleave', function() {
                this.classList.remove('drag-over');
            });
            
            container.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('drag-over');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    input.files = files;
                    handleImageUpload(input);
                }
            });
        }
    });
}

// Manejar subida de imagen
function handleImageUpload(input) {
    const files = input.files;
    const preview = input.parentNode.querySelector('.image-preview');
    
    if (files.length > 0 && preview) {
        preview.innerHTML = '';
        
        Array.from(files).forEach((file, index) => {
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const imageDiv = document.createElement('div');
                    imageDiv.className = 'preview-image';
                    imageDiv.innerHTML = `
                        <img src="${e.target.result}" alt="Preview">
                        <button type="button" class="btn-remove-image" onclick="removeImage(this, ${index})">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                    preview.appendChild(imageDiv);
                };
                reader.readAsDataURL(file);
            }
        });
    }
}

// Remover imagen
function removeImage(button, index) {
    const preview = button.closest('.image-preview');
    const input = preview.parentNode.querySelector('input[type="file"]');
    
    button.closest('.preview-image').remove();
    
    // Actualizar input files (esto es complejo en JavaScript puro)
    // En una implementación real, usarías FormData
}

// Funcionalidad de filtros
function initFilters() {
    const filterForm = document.querySelector('.filters-form');
    if (!filterForm) return;
    
    const filterInputs = filterForm.querySelectorAll('input, select');
    
    filterInputs.forEach(input => {
        input.addEventListener('change', function() {
            // Auto-aplicar filtros después de un breve delay
            setTimeout(() => {
                applyFilters();
            }, 500);
        });
    });
    
    // Limpiar filtros
    const clearButton = document.querySelector('.btn-clear-filters');
    if (clearButton) {
        clearButton.addEventListener('click', function() {
            clearFilters();
        });
    }
}

// Aplicar filtros
function applyFilters() {
    const filterForm = document.querySelector('.filters-form');
    if (!filterForm) return;
    
    const formData = new FormData(filterForm);
    const params = new URLSearchParams(formData);
    
    // Actualizar URL sin recargar la página
    const newUrl = window.location.pathname + '?' + params.toString();
    window.history.pushState({}, '', newUrl);
    
    // Cargar resultados con AJAX
    loadFilteredResults(params);
}

// Cargar resultados filtrados
function loadFilteredResults(params) {
    const resultsContainer = document.querySelector('.products-grid');
    if (!resultsContainer) return;
    
    // Mostrar loading
    resultsContainer.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
    
    // Simular carga (en producción, esto sería una llamada AJAX real)
    setTimeout(() => {
        // Aquí cargarías los resultados reales del servidor
        // Por ahora, recargamos la página
        window.location.reload();
    }, 1000);
}

// Limpiar filtros
function clearFilters() {
    const filterForm = document.querySelector('.filters-form');
    if (!filterForm) return;
    
    filterForm.reset();
    
    // Redirigir sin parámetros
    window.location.href = window.location.pathname;
}

// Funcionalidad de favoritos
function initFavorites() { /* desactivado - usar favorites-share-fix.js */ }

// Toggle favorito
function toggleFavorite(button) { /* desactivado - usar favorites-share-fix.js */ }

// Lazy loading de imágenes
function initLazyLoading() {
    const images = document.querySelectorAll('img[data-src]');
    
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    imageObserver.unobserve(img);
                }
            });
        });
        
        images.forEach(img => imageObserver.observe(img));
    } else {
        // Fallback para navegadores sin soporte
        images.forEach(img => {
            img.src = img.dataset.src;
        });
    }
}

// Búsqueda por voz
function voiceSearch() {}

// Iniciar búsqueda por voz
function startVoiceSearch(){}

// Mostrar notificaciones
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <span>${message}</span>
            <button class="btn-close-notification" onclick="this.parentNode.parentNode.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remover después de 5 segundos
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}

// Formatear números mientras se escriben
function formatNumber(input) {
    let value = input.value.replace(/[^\d]/g, '');
    value = value.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    input.value = value;
}

// Formatear teléfonos
function formatPhone(input) {
    let value = input.value.replace(/[^\d]/g, '');
    if (value.length >= 8) {
        value = value.substring(0, 8);
        value = value.replace(/(\d{4})(\d{4})/, '$1-$2');
    }
    input.value = value;
}

// Compartir producto
function shareProduct(productId, title) {
    if (navigator.share) {
        navigator.share({
            title: title,
            text: 'Mira este producto en CAMBALACHE',
            url: window.location.href
        });
    } else {
        // Fallback: copiar al portapapeles
        navigator.clipboard.writeText(window.location.href).then(() => {
            showNotification('Enlace copiado al portapapeles', 'success');
        });
    }
}

// Reportar producto
function reportProduct(productId) {
    const reason = prompt('¿Por qué quieres reportar este producto?');
    if (reason) {
        fetch('/api/report_product.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                product_id: productId,
                reason: reason
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Reporte enviado correctamente', 'success');
            } else {
                showNotification('Error al enviar el reporte', 'error');
            }
        });
    }
}

// Utilidades
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function throttle(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    }
}

// Detectar dispositivo móvil
function isMobile() {
    return window.innerWidth <= 768;
}

// Smooth scroll para enlaces internos
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});



/* Greeting Fix: añade .btn-greeting-fix al elemento "Hola, ..." dentro de header .topbar */
(function(){
  function applyGreetingFix(){
    var root = document.querySelector('header .topbar');
    if(!root) return;
    var nodes = root.querySelectorAll('a, span, button');
    for(var i=0;i<nodes.length;i++){
      var el = nodes[i];
      var txt = (el.textContent || '').trim().toLowerCase();
      if(txt && txt.indexOf('hola') === 0){
        el.classList.add('btn-greeting-fix');
        break;
      }
    }
  }
  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', applyGreetingFix);
  } else {
    applyGreetingFix();
  }
  setTimeout(applyGreetingFix, 1200);
})();

