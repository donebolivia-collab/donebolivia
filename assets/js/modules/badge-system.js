/**
 * Badge System Module
 * Gestión completa del sistema de badges para productos
 * Modulo extraído de editor-tienda.js para mejor mantenibilidad
 */

// Namespace seguro para el módulo
const BadgeSystem = (function() {
    'use strict';
    
    // Variables privadas del módulo
    let multiSelectInstance = null;
    
    /**
     * Inicializa el sistema de badges
     * Depende de: window.availableBadges, UIMultiSelect
     */
    function initBadgesMultiSelect() {
        // Verificar dependencias
        if (typeof UIMultiSelect === 'undefined') {
            console.warn('BadgeSystem: UIMultiSelect no disponible');
            return false;
        }
        
        if (!window.availableBadges) {
            console.warn('BadgeSystem: window.availableBadges no disponible');
            return false;
        }
        
        // Transformar los badges disponibles al formato que requiere el componente
        const badgeOptions = window.availableBadges.map(badge => {
            return { 
                value: String(badge.id), // Forzar el valor a ser un string
                label: badge.nombre
            };
        });

        // Crear instancia del multiselect
        multiSelectInstance = new UIMultiSelect({
            container: 'badgesMultiSelect',
            id: 'badgesMultiSelectComponent',
            placeholder: 'Seleccionar insignias...',
            options: badgeOptions, // Usar las opciones dinámicas
            maxVisible: 3,
            onChange: function(values, selectedOptions) {
                // Actualizar hidden input con los IDs seleccionados
                const badgesInput = document.getElementById('badgesInput');
                if (badgesInput) {
                    badgesInput.value = values.join(',');
                }
                // Actualizar ghost card (previsualización en vivo)
                if (typeof updateGhostCard === 'function') {
                    updateGhostCard();
                }
            }
        });
        
        // Almacenar instancia global para compatibilidad
        window.badgesMultiSelect = multiSelectInstance;
        
        console.log('BadgeSystem: Inicializado correctamente');
        return true;
    }
    
    /**
     * Obtiene los badges seleccionados actualmente
     * @returns {Array} Array de IDs de badges seleccionados
     */
    function getSelectedBadges() {
        const badgesInput = document.getElementById('badgesInput');
        if (!badgesInput || !badgesInput.value) {
            return [];
        }
        
        return badgesInput.value.split(',').filter(b => b.trim());
    }
    
    /**
     * Establece los badges seleccionados
     * @param {Array} badgeIds - Array de IDs de badges a seleccionar
     */
    function setSelectedBadges(badgeIds) {
        if (!multiSelectInstance) {
            console.warn('BadgeSystem: MultiSelect no inicializado');
            return false;
        }
        
        if (!Array.isArray(badgeIds)) {
            console.warn('BadgeSystem: badgeIds debe ser un array');
            return false;
        }
        
        multiSelectInstance.setValues(badgeIds);
        return true;
    }
    
    /**
     * Limpia la selección de badges
     */
    function clearBadges() {
        if (!multiSelectInstance) {
            console.warn('BadgeSystem: MultiSelect no inicializado');
            return false;
        }
        
        multiSelectInstance.setValues([]);
        return true;
    }
    
    /**
     * Verifica si el sistema está inicializado
     * @returns {boolean}
     */
    function isInitialized() {
        return multiSelectInstance !== null;
    }
    
    /**
     * Destruye la instancia del multiselect
     */
    function destroy() {
        if (multiSelectInstance && typeof multiSelectInstance.destroy === 'function') {
            multiSelectInstance.destroy();
            multiSelectInstance = null;
            window.badgesMultiSelect = null;
            console.log('BadgeSystem: Destruido correctamente');
        }
    }
    
    // API pública del módulo
    return {
        init: initBadgesMultiSelect,
        getSelected: getSelectedBadges,
        setSelected: setSelectedBadges,
        clear: clearBadges,
        isInitialized: isInitialized,
        destroy: destroy
    };
})();

// Exponer globalmente para compatibilidad con código existente
window.BadgeSystem = BadgeSystem;
window.initBadgesMultiSelect = BadgeSystem.init;
