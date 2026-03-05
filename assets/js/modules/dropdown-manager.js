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
            offset: [0, 8],
            // appendTo por defecto es document.body, que es lo correcto en este caso.

            onShow: (instance) => {
                // Ocultar otros tippys para evitar solapamientos
                document.querySelectorAll('[data-tippy-root]').forEach(popper => {
                    const tippyInstance = popper._tippy;
                    if (tippyInstance && tippyInstance !== instance) {
                        tippyInstance.hide();
                    }
                });

                // 1. Definimos la acción de cierre
                const closeMenu = () => instance.hide();

                // 2. Escuchamos el scroll en la ventana global
                window.addEventListener('scroll', closeMenu, { once: true, capture: true });

                // 3. Escuchamos el scroll específico del panel de inventario (IMPORTANTE)
                const scrollContainer = document.querySelector('.inventory-list-container');
                if (scrollContainer) {
                    scrollContainer.addEventListener('scroll', closeMenu, { once: true });
                    // También escuchamos la rueda del mouse por si el scroll es muy pequeño
                    scrollContainer.addEventListener('wheel', closeMenu, { once: true });
                }
            },
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
        // Ocultar otros tippys para evitar solapamientos
        document.querySelectorAll('[data-tippy-root]').forEach(popper => {
            const tippyInstance = popper._tippy;
            if (tippyInstance && tippyInstance !== instance) {
                tippyInstance.hide();
            }
        });

        const closeOnScroll = () => {
            if (instance.state.isShown) {
                instance.hide();
            }
        };

        // Guardar referencia para poder removerlo después
        instance._scrollHandler = closeOnScroll;

        // Añadir listener al contenedor de scroll más cercano
        const scrollableParent = instance.reference.closest('.drawer-body, .sidebar-content');
        if (scrollableParent) {
            scrollableParent.addEventListener('scroll', closeOnScroll, { passive: true });
            instance._scrollTarget = scrollableParent;
        }

        // Añadir listener global para la rueda del ratón, con retraso para evitar que cierre al abrir
        instance._wheelTimeout = setTimeout(() => {
            document.addEventListener('wheel', closeOnScroll, { passive: true });
            instance._documentWheelListener = closeOnScroll;
        }, 150); // 150ms de gracia
    }

    /**
     * Maneja el evento hide del dropdown
     */
    handleHide(instance) {
        // Limpiar listener de scroll del contenedor
        if (instance._scrollHandler && instance._scrollTarget) {
            instance._scrollTarget.removeEventListener('scroll', instance._scrollHandler);
        }

        // Limpiar el timeout si el menú se cierra antes de que se ejecute
        if (instance._wheelTimeout) {
            clearTimeout(instance._wheelTimeout);
        }

        // Limpiar listener de la rueda del ratón del documento
        if (instance._documentWheelListener) {
            document.removeEventListener('wheel', instance._documentWheelListener);
        }

        // Limpiar todas las referencias guardadas
        delete instance._scrollHandler;
        delete instance._scrollTarget;
        delete instance._wheelTimeout;
        delete instance._documentWheelListener;
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
