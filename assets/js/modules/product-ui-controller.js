/**
 * Product UI Controller - Capa de Interfaz Refactorizada
 * Manejo centralizado de la UI del editor de productos
 */

const ProductUIController = (function() {
    'use strict';
    
    // Estado privado del controlador
    let state = {
        isSubmitting: false,
        currentProductId: null,
        formData: {},
        validationErrors: []
    };
    
    // Configuración
    const CONFIG = {
        MAX_IMAGES: 5,
        MAX_FILE_SIZE: 5 * 1024 * 1024, // 5MB
        DEBOUNCE_DELAY: 300
    };
    
    /**
     * Inicializa el controlador
     */
    function init() {
        console.log('ProductUIController: Inicializando...');
        bindEvents();
        resetState();
        console.log('ProductUIController: Inicializado correctamente');
        return true;
    }
    
    /**
     * Vincula eventos del DOM
     */
    function bindEvents() {
        // Evento submit del formulario
        const form = document.getElementById('formProducto');
        if (form) {
            form.addEventListener('submit', handleFormSubmit);
        }
        
        // Eventos de validación en tiempo real
        const inputs = ['prodTitulo', 'prodPrecio', 'prodDescripcion'];
        inputs.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.addEventListener('input', debounce(validateField, CONFIG.DEBOUNCE_DELAY));
                element.addEventListener('blur', validateField);
            }
        });
        
        // Eventos de cambio de categoría
        const categoriaSelect = document.getElementById('prodCategoriaId');
        if (categoriaSelect) {
            categoriaSelect.addEventListener('change', handleCategoriaChange);
        }
    }
    
    /**
     * Maneja el envío del formulario
     */
    async function handleFormSubmit(e) {
        e.preventDefault();
        
        if (state.isSubmitting) {
            console.warn('ProductUIController: Ya se está enviando el formulario');
            return false;
        }
        
        try {
            // 1. Recopilar datos del DOM
            const formData = collectFormData();
            
            // 2. Validar contrato
            const validation = ProductDataContract.validate(formData);
            if (!validation.valid) {
                showValidationErrors(validation.errors);
                return false;
            }
            
            // 3. Normalizar datos
            const normalizedData = ProductDataContract.normalize(formData);
            
            // 4. Enviar al backend
            const result = await sendToBackend(normalizedData);
            
            // 5. Manejar respuesta
            if (result.success) {
                handleSuccess(result);
            } else {
                handleError(result);
            }
            
        } catch (error) {
            console.error('ProductUIController: Error en envío:', error);
            handleNetworkError(error);
        }
    }
    
    /**
     * Recopila datos del DOM de forma estructurada
     */
    function collectFormData() {
        const data = {};
        
        // Datos básicos
        const fields = [
            'id', 'titulo', 'descripcion', 'precio', 'estado',
            'categoria_id', 'subcategoria_id', 'categoria_tienda',
            'departamento', 'municipio'
        ];
        
        fields.forEach(field => {
            const element = document.getElementById(`prod${field.charAt(0).toUpperCase() + field.slice(1)}`);
            if (element) {
                data[field] = element.value.trim();
            }
        });
        
        // Badges
        if (typeof BadgeSystem !== 'undefined' && BadgeSystem.getSelected) {
            data.badges = BadgeSystem.getSelected();
        }
        
        // Imágenes
        if (typeof ImageManager !== 'undefined' && ImageManager.getFiles) {
            data.imagenes_nuevas = ImageManager.getFiles();
        }
        
        if (typeof ImageManager !== 'undefined' && ImageManager.getImagesToDelete) {
            data.imagenes_eliminar = ImageManager.getImagesToDelete();
        }
        
        return data;
    }
    
    /**
     * Valida un campo específico
     */
    function validateField(e) {
        const field = e.target;
        const fieldName = field.id.replace('prod', '').toLowerCase();
        const value = field.value.trim();
        
        // Resetear estado visual
        field.classList.remove('error', 'success');
        
        // Validaciones específicas
        let isValid = true;
        let errorMessage = '';
        
        switch (fieldName) {
            case 'titulo':
                if (value.length < 10) {
                    isValid = false;
                    errorMessage = 'El título debe tener al menos 10 caracteres';
                }
                break;
                
            case 'precio':
                const price = parseFloat(value);
                if (isNaN(price) || price <= 0) {
                    isValid = false;
                    errorMessage = 'El precio debe ser un número mayor a 0';
                }
                break;
                
            case 'descripcion':
                if (value.length < 20) {
                    isValid = false;
                    errorMessage = 'La descripción debe tener al menos 20 caracteres';
                }
                break;
        }
        
        // Actualizar estado visual
        if (value.length > 0) {
            field.classList.add(isValid ? 'success' : 'error');
        }
        
        // Mostrar/ocultar error
        const errorElement = document.getElementById(`${field.id}-error`);
        if (errorElement) {
            errorElement.textContent = errorMessage;
            errorElement.style.display = isValid ? 'none' : 'block';
        }
        
        return isValid;
    }
    
    /**
     * Maneja el cambio de categoría
     */
    async function handleCategoriaChange(e) {
        const categoriaId = e.target.value;
        
        if (!categoriaId) {
            resetSubcategoria();
            return;
        }
        
        // Cargar subcategorías
        if (typeof ProductEditorCore !== 'undefined' && ProductEditorCore.loadSubcategories) {
            await ProductEditorCore.loadSubcategories(categoriaId);
        }
    }
    
    /**
     * Envía datos al backend con manejo de errores robusto
     */
    async function sendToBackend(data) {
        state.isSubmitting = true;
        updateSubmitButton(true);
        
        try {
            const formData = new FormData();
            
            // Agregar todos los campos
            Object.keys(data).forEach(key => {
                if (key === 'imagenes_nuevas') {
                    // Manejar archivos
                    data[key].forEach((file, index) => {
                        formData.append(data.id ? 'imagenes_nuevas[]' : 'imagenes[]', file);
                    });
                } else if (key === 'imagenes_eliminar') {
                    formData.append(key, JSON.stringify(data[key]));
                } else if (key === 'badges') {
                    formData.append(key, JSON.stringify(data[key]));
                } else {
                    formData.append(key, data[key]);
                }
            });
            
            const endpoint = data.id ? '/api/product_save.php' : '/api/product_save.php';
            
            const response = await fetch(endpoint, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const result = await response.json();
            
            return result;
            
        } catch (error) {
            console.error('ProductUIController: Error de red:', error);
            throw error;
        } finally {
            state.isSubmitting = false;
            updateSubmitButton(false);
        }
    }
    
    /**
     * Maneja respuesta exitosa
     */
    function handleSuccess(result) {
        if (typeof showNotif === 'function') {
            showNotif(result.message || 'Producto guardado correctamente', 'success');
        }
        
        // Cerrar drawer
        if (typeof ProductEditorCore !== 'undefined' && ProductEditorCore.closeDrawer) {
            ProductEditorCore.closeDrawer();
        }
        
        // Recargar lista de productos o iframe
        setTimeout(() => {
            if (typeof renderSidebarProducts === 'function') {
                renderSidebarProducts();
            }
            
            // Opcional: recargar iframe para mostrar cambios
            const storeFrame = document.getElementById('storeFrame');
            if (storeFrame) {
                storeFrame.src = storeFrame.src;
            }
        }, 500);
    }
    
    /**
     * Maneja errores del backend
     */
    function handleError(result) {
        const message = result.message || 'Error al guardar el producto';
        
        if (typeof showNotif === 'function') {
            showNotif(message, 'error');
        }
        
        console.error('ProductUIController: Error del backend:', result);
    }
    
    /**
     * Maneja errores de red
     */
    function handleNetworkError(error) {
        if (typeof showNotif === 'function') {
            showNotif('Error de conexión. Verifica tu internet e intenta nuevamente.', 'error');
        }
        
        console.error('ProductUIController: Error de red:', error);
    }
    
    /**
     * Muestra errores de validación
     */
    function showValidationErrors(errors) {
        const firstError = errors[0];
        
        if (typeof showNotif === 'function') {
            showNotif(firstError, 'error');
        }
        
        // Resaltar campos con errores
        errors.forEach(error => {
            const fieldName = error.split(':')[0].replace('Campo requerido: ', '').toLowerCase();
            const element = document.getElementById(`prod${fieldName.charAt(0).toUpperCase() + fieldName.slice(1)}`);
            
            if (element) {
                element.classList.add('error');
                
                // Mostrar mensaje específico del campo
                const errorElement = document.getElementById(`${element.id}-error`);
                if (errorElement) {
                    errorElement.textContent = error.split(':')[1]?.trim() || error;
                    errorElement.style.display = 'block';
                }
            }
        });
    }
    
    /**
     * Actualiza el estado del botón de envío
     */
    function updateSubmitButton(isSubmitting) {
        const button = document.getElementById('btnGuardarProducto');
        
        if (button) {
            if (isSubmitting) {
                button.textContent = 'Guardando...';
                button.disabled = true;
                button.classList.add('loading');
            } else {
                button.textContent = 'Guardar';
                button.disabled = false;
                button.classList.remove('loading');
            }
        }
    }
    
    /**
     * Resetea las subcategorías
     */
    function resetSubcategoria() {
        const subInput = document.getElementById('prodSubcategoriaId');
        const subLabel = document.getElementById('prodSubcatLabel');
        const subTrigger = document.getElementById('prodSubcatTrigger');
        
        if (subInput) subInput.value = '';
        if (subLabel) subLabel.innerText = '-- Primero selecciona categoría --';
        if (subTrigger) subTrigger.classList.add('disabled');
    }
    
    /**
     * Resetea el estado del controlador
     */
    function resetState() {
        state = {
            isSubmitting: false,
            currentProductId: null,
            formData: {},
            validationErrors: []
        };
    }
    
    /**
     * Utilidad: Debounce
     */
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
    
    // API pública
    return {
        init: init,
        collectFormData: collectFormData,
        validateField: validateField,
        resetState: resetState,
        getState: () => state
    };
})();

// Exponer globalmente
window.ProductUIController = ProductUIController;
