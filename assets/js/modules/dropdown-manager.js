/**
 * Dropdown Manager - Módulo reutilizable para menús de 3 puntos
 * Implementa patrón DRY para todos los dropdowns del sistema
 */

class DropdownManager {
    constructor() {
        this.instances = new Map();
        this.isInitialized = false;
    }

    /**
     * Inicializa el gestor de dropdowns
     */
    init() {
        if (this.isInitialized) return;
        
        setTimeout(() => {
            this.setupTippyDelegate();
            this.isInitialized = true;
            console.log('Dropdown Manager initialized');
        }, 100);
    }

    /**
     * Configura la delegación de eventos de Tippy
     */
    setupTippyDelegate() {
        if (typeof tippy === 'undefined') {
            console.error('Tippy.js no está disponible');
            return;
        }

        tippy.delegate('body', {
            target: '.dropdown-trigger',
            content: (reference) => this.getDropdownContent(reference),
            allowHTML: true,
            interactive: true,
            trigger: 'click',
            placement: 'left',
            arrow: true,
            animation: 'scale',
            theme: 'light',
            hideOnClick: true,
            appendTo: document.body,
            offset: [0, 8],
            popperOptions: {
                fallbackPlacements: []
            },
            onShow: (instance) => this.handleShow(instance),
            onHide: (instance) => this.handleHide(instance)
        });
    }

    /**
     * Obtiene el contenido del dropdown basado en el tipo
     */
    getDropdownContent(reference) {
        const dropdownType = reference.getAttribute('data-dropdown-type');
        const dropdownId = reference.getAttribute('data-dropdown-id');
        
        if (!dropdownType || !dropdownId) {
            console.warn('Faltan atributos data-dropdown-type o data-dropdown-id');
            return '<div>Error: Configuración incompleta</div>';
        }

        const template = document.getElementById(`${dropdownType}-dropdown-${dropdownId}`);
        return template ? template.innerHTML : this.getDefaultContent(dropdownType);
    }

    /**
     * Contenido por defecto según el tipo de dropdown
     */
    getDefaultContent(type) {
        const contents = {
            'inventory': `
                <div class="custom-dropdown">
                    <div class="dropdown-item" onclick="dropdownManager.handleAction('edit', this)">
                        <span>Editar</span>
                    </div>
                    <div class="dropdown-item" onclick="dropdownManager.handleAction('delete', this)">
                        <span>Eliminar</span>
                    </div>
                    <div class="dropdown-item" onclick="dropdownManager.handleAction('toggle', this)">
                        <span>Ocultar</span>
                    </div>
                </div>
            `,
            'product': `
                <div class="custom-dropdown">
                    <div class="dropdown-item" onclick="dropdownManager.handleAction('edit', this)">
                        <span>Editar</span>
                    </div>
                    <div class="dropdown-item" onclick="dropdownManager.handleAction('duplicate', this)">
                        <span>Duplicar</span>
                    </div>
                    <div class="dropdown-item" onclick="dropdownManager.handleAction('delete', this)">
                        <span>Eliminar</span>
                    </div>
                </div>
            `
        };

        return contents[type] || contents['inventory'];
    }

    /**
     * Maneja el evento show del dropdown
     */
    handleShow(instance) {
        // Ocultar otros tippys
        document.querySelectorAll('[data-tippy-root]').forEach(popper => {
            const tippyInstance = popper._tippy;
            if (tippyInstance && tippyInstance !== instance) {
                tippyInstance.hide();
            }
        });

        // Configurar cierre al scroll con animación suave
        const scrollHandler = () => {
            if (instance.state.isShown) {
                // Aplicar efecto profesional con fadeOut
                instance.popper.style.transition = 'opacity 0.2s ease-out, transform 0.2s ease-out';
                instance.popper.style.opacity = '0';
                instance.popper.style.transform = 'scale(0.95) translateX(10px)';
                
                // Ocultar después de la animación
                setTimeout(() => {
                    instance.hide();
                    // Resetear estilos para próxima apertura
                    instance.popper.style.transition = '';
                    instance.popper.style.opacity = '';
                    instance.popper.style.transform = '';
                }, 200);
            }
        };
        
        // Guardar referencia para poder limpiar después
        instance._scrollHandler = scrollHandler;
        
        // Añadir listeners con capture para mejor detección
        document.addEventListener('scroll', scrollHandler, { capture: true, passive: true });
        window.addEventListener('scroll', scrollHandler, { capture: true, passive: true });
    }

    /**
     * Maneja el evento hide del dropdown
     */
    handleHide(instance) {
        // Limpiar listeners de scroll cuando se cierra
        if (instance._scrollHandler) {
            document.removeEventListener('scroll', instance._scrollHandler, { capture: true });
            window.removeEventListener('scroll', instance._scrollHandler, { capture: true });
            delete instance._scrollHandler;
        }
    }

    /**
     * Maneja las acciones del dropdown
     */
    handleAction(action, element) {
        const dropdownId = element.closest('[data-tippy-root]')._tippy.reference.getAttribute('data-dropdown-id');
        const dropdownType = element.closest('[data-tippy-root]')._tippy.reference.getAttribute('data-dropdown-type');
        
        console.log(`Action: ${action}, Type: ${dropdownType}, ID: ${dropdownId}`);
        
        // Ejecutar acción según el tipo
        switch (dropdownType) {
            case 'inventory':
                this.handleInventoryAction(action, dropdownId);
                break;
            case 'product':
                this.handleProductAction(action, dropdownId);
                break;
            default:
                console.warn(`Tipo de dropdown no soportado: ${dropdownType}`);
        }
    }

    /**
     * Maneja acciones específicas del inventario
     */
    handleInventoryAction(action, productId) {
        switch (action) {
            case 'edit':
                if (typeof openProductDrawer === 'function') {
                    openProductDrawer(productId);
                }
                break;
            case 'delete':
                if (typeof eliminarProducto === 'function') {
                    eliminarProducto(productId);
                }
                break;
            case 'toggle':
                if (typeof toggleProductoActivo === 'function') {
                    const product = window.allProducts?.find(p => p.id == productId);
                    if (product) {
                        toggleProductoActivo(productId, product.activo);
                    }
                }
                break;
        }
    }

    /**
     * Maneja acciones específicas de productos
     */
    handleProductAction(action, productId) {
        switch (action) {
            case 'edit':
                if (typeof openProductDrawer === 'function') {
                    openProductDrawer(productId);
                }
                break;
            case 'duplicate':
                console.log('Duplicar producto:', productId);
                // Implementar lógica de duplicación
                break;
            case 'delete':
                if (typeof eliminarProducto === 'function') {
                    eliminarProducto(productId);
                }
                break;
        }
    }

    /**
     * Registra un nuevo tipo de dropdown
     */
    registerDropdownType(type, content) {
        // Permite extender con nuevos tipos de dropdown
        console.log(`Registrado nuevo tipo de dropdown: ${type}`);
    }

    /**
     * Destruye todas las instancias
     */
    destroy() {
        this.instances.clear();
        this.isInitialized = false;
    }
}

// Crear instancia global
window.dropdownManager = new DropdownManager();

// Auto-inicialización cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    window.dropdownManager.init();
});
