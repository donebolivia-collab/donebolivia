/**
 * ProductSyncManager - Orquestador Central de Sincronización
 * Implementa Progressive Sync Pattern respetando DRY y principios SOLID
 */

class ProductSyncManager {
    constructor() {
        this.operationHandlers = new Map();
        this.setupDefaultHandlers();
    }

    /**
     * Configura los handlers por defecto
     */
    setupDefaultHandlers() {
        this.operationHandlers.set('add', this.handleAddProduct.bind(this));
        this.operationHandlers.set('update', this.handleUpdateProduct.bind(this));
        this.operationHandlers.set('delete', this.handleDeleteProduct.bind(this));
    }

    /**
     * Método principal de sincronización - Respeta DRY
     * @param {string} operation - Tipo de operación (add/update/delete)
     * @param {Object} data - Datos del producto
     * @returns {Promise<boolean>} - Resultado de la operación
     */
    async sync(operation, data) {
        try {
            console.log(`[ProductSyncManager] Iniciando sincronización: ${operation}`);
            
            // Validar operación
            if (!this.operationHandlers.has(operation)) {
                throw new Error(`Operación no soportada: ${operation}`);
            }

            // Validar datos
            if (!this.validateProductData(data)) {
                throw new Error('Datos de producto inválidos');
            }

            // Ejecutar handler específico
            const handler = this.operationHandlers.get(operation);
            await handler(data);

            console.log(`[ProductSyncManager] Sincronización completada: ${operation}`);
            return true;

        } catch (error) {
            console.error(`[ProductSyncManager] Error en sincronización:`, error);
            
            // Notificar error
            if (typeof showNotif === 'function') {
                showNotif(`Error de sincronización: ${error.message}`, 'error');
            }
            
            return false;
        }
    }

    /**
     * Handler para agregar producto
     * @param {Object} productData - Datos del nuevo producto
     */
    async handleAddProduct(productData) {
        // 1. Actualizar iframe (comunicación en tiempo real)
        await this.updateIframe('addProduct', productData);

        // 2. Actualizar estado local (sin recarga)
        this.updateLocalState('add', productData);

        // 3. Notificar éxito
        if (typeof showNotif === 'function') {
            showNotif('Producto agregado correctamente', 'success');
        }
    }

    /**
     * Handler para actualizar producto
     * @param {Object} productData - Datos actualizados del producto
     */
    async handleUpdateProduct(productData) {
        // 1. Actualizar iframe
        await this.updateIframe('updateProduct', productData);

        // 2. Actualizar estado local
        this.updateLocalState('update', productData);

        // 3. Notificar éxito
        if (typeof showNotif === 'function') {
            showNotif('Producto actualizado correctamente', 'success');
        }
    }

    /**
     * Handler para eliminar producto
     * @param {Object} productData - Datos del producto a eliminar
     */
    async handleDeleteProduct(productData) {
        // 1. Actualizar iframe
        await this.updateIframe('deleteProduct', productData);

        // 2. Actualizar estado local
        this.updateLocalState('delete', productData);

        // 3. Notificar éxito
        if (typeof showNotif === 'function') {
            showNotif('Producto eliminado correctamente', 'success');
        }
    }

    /**
     * Actualiza el iframe vía comunicación en tiempo real
     * @param {string} operation - Tipo de operación
     * @param {Object} data - Datos a enviar
     */
    async updateIframe(operation, data) {
        return new Promise((resolve, reject) => {
            try {
                // Usar el sistema de comunicación existente
                if (typeof postToFrame === 'function') {
                    const success = postToFrame(operation, data);
                    
                    if (success) {
                        console.log(`[ProductSyncManager] Mensaje enviado a iframe: ${operation}`);
                        resolve();
                    } else {
                        console.warn(`[ProductSyncManager] No se pudo enviar mensaje a iframe: ${operation}`);
                        // No rechazamos, continuamos con actualización local
                        resolve();
                    }
                } else {
                    console.warn('[ProductSyncManager] postToFrame no disponible');
                    resolve();
                }
            } catch (error) {
                console.error('[ProductSyncManager] Error actualizando iframe:', error);
                reject(error);
            }
        });
    }

    /**
     * Actualiza el estado local sin recargar página
     * @param {string} operation - Tipo de operación
     * @param {Object} data - Datos del producto
     */
    updateLocalState(operation, data) {
        try {
            // Asegurar que window.allProducts existe
            if (!window.allProducts || !Array.isArray(window.allProducts)) {
                console.warn('[ProductSyncManager] window.allProducts no disponible');
                return;
            }

            switch (operation) {
                case 'add':
                    this.addProductToLocalState(data);
                    break;
                case 'update':
                    this.updateProductInLocalState(data);
                    break;
                case 'delete':
                    this.deleteProductFromLocalState(data);
                    break;
            }

            // Actualizar UI local
            this.updateLocalUI();

        } catch (error) {
            console.error('[ProductSyncManager] Error actualizando estado local:', error);
        }
    }

    /**
     * Agrega producto al estado local
     * @param {Object} productData - Datos del nuevo producto
     */
    addProductToLocalState(productData) {
        // Verificar si ya existe
        const existingIndex = window.allProducts.findIndex(p => p.id === productData.id);
        
        if (existingIndex === -1) {
            // Agregar nuevo producto
            window.allProducts.push(productData);
            console.log(`[ProductSyncManager] Producto agregado localmente: ${productData.id}`);
        } else {
            // Actualizar existente
            window.allProducts[existingIndex] = productData;
            console.log(`[ProductSyncManager] Producto actualizado localmente: ${productData.id}`);
        }
    }

    /**
     * Actualiza producto en estado local
     * @param {Object} productData - Datos actualizados
     */
    updateProductInLocalState(productData) {
        const index = window.allProducts.findIndex(p => p.id === productData.id);
        
        if (index !== -1) {
            // Mantener propiedades existentes, actualizar las nuevas
            window.allProducts[index] = { ...window.allProducts[index], ...productData };
            console.log(`[ProductSyncManager] Producto actualizado localmente: ${productData.id}`);
        } else {
            // Si no existe, agregarlo
            this.addProductToLocalState(productData);
        }
    }

    /**
     * Elimina producto del estado local
     * @param {Object} productData - Datos del producto a eliminar
     */
    deleteProductFromLocalState(productData) {
        const index = window.allProducts.findIndex(p => p.id === productData.id);
        
        if (index !== -1) {
            window.allProducts.splice(index, 1);
            console.log(`[ProductSyncManager] Producto eliminado localmente: ${productData.id}`);
        }
    }

    /**
     * Actualiza la UI local sin recargar
     */
    updateLocalUI() {
        try {
            // Actualizar listas de productos si existen las funciones
            if (typeof renderSidebarProducts === 'function') {
                renderSidebarProducts();
            }
            
            if (typeof renderInventoryList === 'function') {
                renderInventoryList();
            }

            // Actualizar contadores de secciones
            if (typeof updateSectionCounts === 'function') {
                updateSectionCounts();
            }

            console.log('[ProductSyncManager] UI local actualizada');

        } catch (error) {
            console.error('[ProductSyncManager] Error actualizando UI local:', error);
        }
    }

    /**
     * Valida los datos del producto
     * @param {Object} data - Datos a validar
     * @returns {boolean} - Si los datos son válidos
     */
    validateProductData(data) {
        if (!data || typeof data !== 'object') {
            return false;
        }

        // Validaciones básicas
        if (!data.id && data.id !== 0) {
            return false;
        }

        if (!data.titulo && !data.title) {
            return false;
        }

        return true;
    }

    /**
     * Agrega un nuevo handler de operación (extensible)
     * @param {string} operation - Nombre de la operación
     * @param {Function} handler - Función handler
     */
    addOperationHandler(operation, handler) {
        this.operationHandlers.set(operation, handler);
        console.log(`[ProductSyncManager] Handler agregado: ${operation}`);
    }

    /**
     * Elimina un handler de operación
     * @param {string} operation - Nombre de la operación
     */
    removeOperationHandler(operation) {
        this.operationHandlers.delete(operation);
        console.log(`[ProductSyncManager] Handler eliminado: ${operation}`);
    }

    /**
     * Obtiene el estado actual del sincronizador
     * @returns {Object} - Estado del sistema
     */
    getState() {
        return {
            availableOperations: Array.from(this.operationHandlers.keys()),
            totalProducts: window.allProducts ? window.allProducts.length : 0,
            lastSyncTime: new Date().toISOString()
        };
    }

    /**
     * Limpia recursos
     */
    destroy() {
        this.operationHandlers.clear();
        console.log('[ProductSyncManager] Recursos liberados');
    }
}

// Instancia global del sincronizador
window.ProductSyncManager = new ProductSyncManager();

// Exponer métodos de conveniencia
window.syncProduct = async (operation, data) => {
    return await window.ProductSyncManager.sync(operation, data);
};

// Métodos específicos para mayor conveniencia
window.addProduct = async (data) => await window.ProductSyncManager.sync('add', data);
window.updateProduct = async (data) => await window.ProductSyncManager.sync('update', data);
window.deleteProduct = async (data) => await window.ProductSyncManager.sync('delete', data);

console.log('[ProductSyncManager] Módulo cargado y listo para usar');
