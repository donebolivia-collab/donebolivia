/**
 * UI Components Module
 * Componentes reutilizables de interfaz de usuario
 * Extraído de editor-tienda.js para mejor mantenibilidad
 */

// Namespace seguro para el módulo
const UIComponents = (function() {
    'use strict';
    
    /**
     * Sistema de notificaciones
     * Crea y muestra notificaciones al usuario
     */
    function showNotif(msg, type = 'success') {
        // Create element if not exists
        let el = document.getElementById('notif');
        if (!el) {
            el = document.createElement('div');
            el.id = 'notif';
            el.className = 'notification';
            el.innerHTML = '<i class="fas"></i> <span id="notifText"></span>';
            document.body.appendChild(el);
        }
        
        const icon = el.querySelector('i');
        icon.className = 'fas'; 
        if(type === 'success') icon.classList.add('fa-check-circle');
        else if(type === 'error') icon.classList.add('fa-exclamation-circle');
        
        document.getElementById('notifText').textContent = msg;
        el.className = `notification show ${type}`;
        setTimeout(() => el.classList.remove('show'), 3000);
    }
    
    /**
     * Inicializa el sistema de accordions
     * Configura el comportamiento de los accordions
     */
    function initAccordions() {
        // Ensure at least one is open if none are
        // MODIFICADO: No forzar apertura automática para respetar estado contraído inicial
        console.log('UIComponents: Accordions inicializados');
    }
    
    /**
     * Toggle individual de accordion
     * @param {HTMLElement} header - Header del accordion a toggle
     */
    function toggleAccordion(header) {
        if (!header) return;
        const item = header.parentElement;
        if (!item) return;
        const isActive = item.classList.contains('open');

        // Cerrar todos los demás
        document.querySelectorAll('.accordion-item').forEach(acc => {
            if (acc !== item) { 
                acc.classList.remove('open');
            }
        });

        // Toggle del actual
        if (!isActive) {
            item.classList.add('open');
        } else {
            item.classList.remove('open');
        }
    }
    
    /**
     * Toggle de UI dropdowns genérico
     * @param {string} id - ID del dropdown a toggle
     */
    function toggleUI(id) {
        const dropdown = document.getElementById(id);
        if (!dropdown) return;
        const menu = dropdown.querySelector('.ui-menu');
        const trigger = dropdown.querySelector('.ui-trigger');
        
        if (!menu || !trigger) return;
        
        // Cerrar otros dropdowns
        document.querySelectorAll('.ui-dropdown').forEach(dd => {
            if (dd !== dropdown) {
                dd.classList.remove('show');
            }
        });
        
        // Toggle del actual
        dropdown.classList.toggle('show');
    }
    
    /**
     * Toggle de sidebar principal
     */
    function toggleSidebar() {
        const sidebar = document.getElementById('editorSidebar');
        const container = document.querySelector('.editor-canvas-container');
        const expandBtn = document.getElementById('expandBtn');
        
        if (!sidebar || !container || !expandBtn) return;
        
        const isHidden = sidebar.classList.contains('hidden');
        
        if (isHidden) {
            sidebar.classList.remove('hidden');
            container.classList.remove('expanded');
            expandBtn.style.display = 'none';
        } else {
            sidebar.classList.add('hidden');
            container.classList.add('expanded');
            expandBtn.style.display = 'block';
        }
    }
    
    /**
     * Toggle de visibilidad de elementos
     * @param {number} index - Índice del elemento
     */
    function toggleVisibility(index) {
        if (!window.menuItems[index]) return;
        
        // Toggle state
        window.menuItems[index].active = !window.menuItems[index].active;
        
        // Update UI
        updateMenuInFrame();
        renderSectionsList();
    }
    
    /**
     * Verifica si el módulo está inicializado
     * @returns {boolean}
     */
    function isInitialized() {
        return true; // Siempre inicializado, no requiere setup
    }
    
    /**
     * Inicializa todos los componentes UI
     */
    function init() {
        console.log('UIComponents: Inicializando componentes...');
        initAccordions();
        console.log('UIComponents: Componentes inicializados');
    }
    
    // API pública del módulo
    return {
        showNotif: showNotif,
        initAccordions: initAccordions,
        toggleAccordion: toggleAccordion,
        toggleUI: toggleUI,
        toggleSidebar: toggleSidebar,
        toggleVisibility: toggleVisibility,
        init: init,
        isInitialized: isInitialized
    };
})();

// Exponer globalmente para compatibilidad con código existente
window.UIComponents = UIComponents;
window.showNotif = UIComponents.showNotif;
window.initAccordions = UIComponents.initAccordions;
window.toggleAccordion = UIComponents.toggleAccordion;
window.toggleUI = UIComponents.toggleUI;
window.toggleSidebar = UIComponents.toggleSidebar;
window.toggleVisibility = UIComponents.toggleVisibility;
