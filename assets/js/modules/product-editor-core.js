/**
 * Product Editor Core Module
 * Corazón del editor de productos
 * Extraído de editor-tienda.js para mejor mantenibilidad
 */

// Namespace seguro para el módulo
const ProductEditorCore = (function() {
    'use strict';
    
    // Variables privadas del módulo
    let currentProductId = null;
    let isEditMode = false;
    
    // Configuración
    const MIN_TITLE_LENGTH = 10;
    
    /**
     * Inicializa el editor de productos
     */
    function init() {
        console.log('ProductEditorCore: Inicializando editor de productos...');
        
        // Inicializar estado
        currentProductId = null;
        isEditMode = false;
        
        console.log('ProductEditorCore: Editor inicializado correctamente');
        return true;
    }
    
    /**
     * Abre el drawer del producto
     * @param {number|null} id - ID del producto (null para nuevo)
     */
    async function openProductDrawer(id = null) {
        // 1. Abrir el panel y resetear estado base
        resetProductDrawer();
        
        const drawer = document.getElementById('productDrawer');
        if (!drawer) {
            console.error('ProductEditorCore: PANEL LATERAL NO ENCONTRADO');
            return false;
        }
        
        drawer.classList.add('show');
        
        // 2. Determinar modo (Crear o Editar)
        if (id) {
            // MODO EDICIÓN
            isEditMode = true;
            currentProductId = id;
            
            const productData = window.allProducts.find(item => item.id == id);
            if (productData) {
                await populateDrawerForEdit(productData);
            } else {
                console.error("Producto no encontrado para editar:", id);
                if (typeof showNotif === 'function') {
                    showNotif("Error: No se encontró el producto.", 'error');
                }
                closeProductDrawer();
                return false;
            }
        } else {
            // MODO CREACIÓN
            isEditMode = false;
            currentProductId = null;
            
            // Pasar el contexto del filtro de sección activo
            if (typeof currentSectionFilter !== 'undefined') {
                await setupDrawerForNew(currentSectionFilter);
            }
        }
        
        return true;
    }
    
    /**
     * Cierra el drawer del producto
     */
    function closeProductDrawer() {
        const drawer = document.getElementById('productDrawer');
        if (drawer) {
            drawer.classList.remove('show');
        }
        
        // Apagar ghost card
        if (typeof postToFrame === 'function') {
            postToFrame('previewProduct', { active: false });
        }
        
        // Resetear estado
        currentProductId = null;
        isEditMode = false;
        
        return true;
    }
    
    /**
     * Resetea el estado del drawer
     */
    function resetProductDrawer() {
        const title = document.getElementById('drawerTitle');
        const id = document.getElementById('prodId');
        
        if (title) title.textContent = 'Nuevo Producto';
        if (id) id.value = '';
        
        // Resetear estado de las imágenes
        if (typeof ImageManager !== 'undefined' && ImageManager.reset) {
            ImageManager.reset();
        }
        
        // Resetear sección de tienda
        if (typeof currentCategoryTienda !== 'undefined') {
            currentCategoryTienda = '';
        }
        
        // Resetear y habilitar dropdown de categoría de tienda
        const sectionTrigger = document.querySelector('#prodCatTiendaDropdown .ui-trigger');
        if (sectionTrigger) {
            sectionTrigger.style.pointerEvents = 'auto';
        }
        
        return true;
    }
    
    /**
     * Puebla el drawer con datos de producto existente
     * @param {Object} p - Datos del producto
     */
    async function populateDrawerForEdit(p) {
        const title = document.getElementById('drawerTitle');
        const id = document.getElementById('prodId');
        const titulo = document.getElementById('prodTitulo');
        const precio = document.getElementById('prodPrecio');
        const descripcion = document.getElementById('prodDescripcion');
        const condicion = document.getElementById('prodCondicion');
        
        // Datos básicos
        if (title) title.textContent = 'Editar Producto';
        if (id) id.value = p.id;
        if (titulo) titulo.value = p.titulo;
        if (precio) precio.value = p.precio;
        if (descripcion) descripcion.value = p.descripcion;
        if (condicion) condicion.value = p.estado || 'Nuevo';
        
        // Cargar y seleccionar categoría y subcategoría
        const catSelect = document.getElementById('prodCategoriaId');
        const catTrigger = document.querySelector('#prodCatIdDropdown .ui-trigger');
        const catLabel = document.getElementById('prodCatIdLabel');
        
        if (catSelect) {
            catSelect.value = p.categoria_id;
            
            // Establecer nombre de categoría
            let catName = 'Categoría'; // Fallback
            const menuOption = document.querySelector(`#prodCatIdDropdown .ui-option[onclick*="${p.categoria_id}"]`);
            if (menuOption) {
                catName = menuOption.innerText.trim();
            }
            if (catLabel) catLabel.innerText = catName;
            
            // BLOQUEAR CATEGORÍA en modo edición para evitar cambios
            if (catTrigger) {
                catTrigger.classList.add('disabled');
                catTrigger.onclick = null;
                catTrigger.style.pointerEvents = 'none';
            }
        }
        
        // Cargar sección de tienda
        if (typeof currentCategoryTienda !== 'undefined') {
            currentCategoryTienda = p.categoria_tienda || '';
        }
        
        // Cargar insignias existentes
        if (typeof BadgeSystem !== 'undefined' && BadgeSystem.setSelected) {
            if (p.badges) {
                BadgeSystem.setSelected(p.badges);
            }
        }
        
        // Cargar imágenes existentes
        if (typeof ImageManager !== 'undefined' && ImageManager.loadExistingImages) {
            ImageManager.loadExistingImages(p);
        }
        
        // Cargar subcategorías
        if (p.categoria_id) {
            await cargarSubcategorias(p.categoria_id, p.subcategoria_id);
        }
        
        return true;
    }
    
    /**
     * Configura el drawer para nuevo producto
     * @param {string} sectionFilter - Filtro de sección
     */
    async function setupDrawerForNew(sectionFilter) {
        // Pre-seleccionar la sección
        const select = document.getElementById('prodCategoriaTienda');
        if (select) {
            select.value = sectionFilter;
        }
        
        return true;
    }
    
    /**
     * Guarda el producto (crea o edita)
     */
    async function guardarProducto() {
        const formData = new FormData();
        const id = document.getElementById('prodId').value;
        
        if(id) formData.append('id', id);
        
        // Obtener datos del formulario
        const titulo = document.getElementById('prodTitulo').value.trim();
        const descripcion = document.getElementById('prodDescripcion').value.trim();
        const precio = document.getElementById('prodPrecio').value;
        
        // Validaciones mejoradas
        if(!validateProductData(titulo, precio)) {
            return false;
        }
        
        // Agregar datos básicos
        formData.append('titulo', titulo);
        formData.append('descripcion', descripcion);
        formData.append('precio', precio);
        formData.append('estado', document.getElementById('prodCondicion').value);
        
        // Leer badges del UIMultiSelect
        if (typeof BadgeSystem !== 'undefined' && BadgeSystem.getSelected) {
            const badges = BadgeSystem.getSelected();
            formData.append('badges', JSON.stringify(badges));
        }
        
        // Agregar categorías
        formData.append('categoria_tienda', typeof currentCategoryTienda !== 'undefined' ? currentCategoryTienda : '');
        formData.append('categoria_id', document.getElementById('prodCategoriaId').value);
        formData.append('subcategoria_id', document.getElementById('prodSubcategoriaId').value);
        
        // Default location from store state
        if (typeof window.tiendaState !== 'undefined') {
            formData.append('departamento', window.tiendaState.deptCode || 'SCZ');
            formData.append('municipio', window.tiendaState.munCode || 'SCZ-001');
        }
        
        // Preparar imágenes
        if (typeof ImageManager !== 'undefined' && ImageManager.prepareForSave) {
            ImageManager.prepareForSave(id, formData);
        }
        
        // Mostrar estado de carga
        const btn = document.getElementById('btnGuardarProducto');
        if(btn) {
            btn.textContent = 'Guardando...';
            btn.disabled = true;
        }
        
        try {
            const endpoint = id ? '/api/editar_producto_completo.php' : '/api/crear_producto_completo.php';
            
            const res = await fetch(endpoint, { method: 'POST', body: formData });
            const result = await res.json();
            
            if(result.success) {
                if (typeof showNotif === 'function') {
                    showNotif('Producto guardado');
                }
                closeProductDrawer();
                
                // 🚀 Progressive Sync Pattern - Sin parpadeo
                if (result.product) {
                    // Usar el nuevo sistema de sincronización
                    await window.ProductSyncManager.sync('add', result.product);
                } else {
                    // Fallback: Actualización local si no hay datos de producto
                    console.warn('[ProductEditorCore] No se recibieron datos del producto, usando fallback');
                    if (typeof renderInventoryList === 'function') {
                        renderInventoryList();
                    }
                } 
            } else {
                if (typeof showNotif === 'function') {
                    showNotif(result.message || 'Error al guardar', 'error');
                }
            }
        } catch(e) { 
            if (typeof showNotif === 'function') {
                showNotif('Error de conexión', 'error');
            }
            console.error(e);
        } finally {
            if(btn) {
                btn.textContent = 'Guardar';
                btn.disabled = false;
            }
        }
        
        return true;
    }
    
    /**
     * Valida los datos del producto
     * @param {string} titulo - Título del producto
     * @param {string} precio - Precio del producto
     * @returns {boolean}
     */
    function validateProductData(titulo, precio) {
        if(!titulo) {
            if (typeof showNotif === 'function') {
                showNotif('El título es requerido', 'error');
            }
            return false;
        }
        
        if(titulo.length < MIN_TITLE_LENGTH) {
            if (typeof showNotif === 'function') {
                showNotif(`El título debe tener al menos ${MIN_TITLE_LENGTH} caracteres`, 'error');
            }
            return false;
        }
        
        if(!precio || parseFloat(precio) <= 0) {
            if (typeof showNotif === 'function') {
                showNotif('Precio inválido', 'error');
            }
            return false;
        }
        
        return true;
    }
    
    /**
     * Carga subcategorías desde la API
     * @param {string} catId - ID de categoría
     * @param {string|null} selectedId - ID seleccionado
     */
    async function cargarSubcategorias(catId, selectedId = null) {
        // UI Refs
        const subInput = document.getElementById('prodSubcategoriaId');
        const subTrigger = document.getElementById('prodSubcatTrigger');
        const subLabel = document.getElementById('prodSubcatLabel');
        const subMenu = document.getElementById('prodSubcatMenu');
        
        // Reset UI
        if(subInput) subInput.value = '';
        if(subLabel) subLabel.innerText = 'Cargando...';
        if(subTrigger) subTrigger.classList.add('disabled');
        if(subMenu) subMenu.innerHTML = '';
        
        if(!catId) {
            if(subLabel) subLabel.innerText = '-- Primero selecciona categoría --';
            return false;
        }
        
        try {
            const cleanCatId = parseInt(catId);
            if (isNaN(cleanCatId)) {
                if(subLabel) subLabel.innerText = 'Categoría inválida';
                return false;
            }
            
            const res = await fetch(`/api/subcategorias.php?categoria_id=${cleanCatId}`);
            const data = await res.json();
            
            if (!data.success) {
                if(subLabel) subLabel.innerText = 'Error al cargar subcategorías';
                return false;
            }
            
            // Construir opciones
            let optionsHTML = '<div class="ui-option" onclick="selectUIOption(\'prodSubcatIdDropdown\', \'\', \'-- Seleccionar --\', (val)=>{ document.getElementById(\'prodSubcategoriaId\').value=val; })">-- Seleccionar --</div>';
            
            if (data.subcategorias && Array.isArray(data.subcategorias)) {
                data.subcategorias.forEach(sub => {
                    const selected = sub.id == selectedId ? 'selected' : '';
                    optionsHTML += `<div class="ui-option ${selected}" onclick="selectUIOption('prodSubcatIdDropdown', '${sub.id}', '${sub.nombre}', (val)=>{ document.getElementById('prodSubcategoriaId').value=val; })">${sub.nombre}</div>`;
                });
            }
            
            if(subMenu) subMenu.innerHTML = optionsHTML;
            if(subLabel) subLabel.innerText = data.subcategorias?.length > 0 ? '-- Seleccionar --' : 'Sin subcategorías';
            if(subTrigger) subTrigger.classList.remove('disabled');
            
            return true;
        } catch(error) {
            console.error('Error cargando subcategorías:', error);
            if(subLabel) subLabel.innerText = 'Error de conexión';
            return false;
        }
    }
    
    /**
     * Obtiene el estado actual del editor
     * @returns {Object}
     */
    function getEditorState() {
        return {
            isEditMode: isEditMode,
            currentProductId: currentProductId,
            hasUnsavedChanges: false // TODO: Implementar tracking de cambios
        };
    }
    
    /**
     * Verifica si el módulo está inicializado
     * @returns {boolean}
     */
    function isInitialized() {
        return true; // Siempre inicializado después de init()
    }
    
    /**
     * Destruye el editor de productos
     */
    function destroy() {
        currentProductId = null;
        isEditMode = false;
        console.log('ProductEditorCore: Destruido correctamente');
    }
    
    // API pública del módulo
    return {
        init: init,
        openDrawer: openProductDrawer,
        closeDrawer: closeProductDrawer,
        reset: resetProductDrawer,
        populateForEdit: populateDrawerForEdit,
        setupForNew: setupDrawerForNew,
        save: guardarProducto,
        loadSubcategories: cargarSubcategorias,
        getState: getEditorState,
        isInitialized: isInitialized,
        destroy: destroy
    };
})();

// Exponer globalmente para compatibilidad con código existente
window.ProductEditorCore = ProductEditorCore;
window.openProductDrawer = ProductEditorCore.openDrawer;
window.closeProductDrawer = ProductEditorCore.closeDrawer;
window.guardarProducto = ProductEditorCore.save;
window.cargarSubcategorias = ProductEditorCore.loadSubcategories;
