/**
 * Image Manager Module
 * Gestión completa de imágenes de productos
 * Extraído de editor-tienda.js para mejor mantenibilidad
 */

// Namespace seguro para el módulo
const ImageManager = (function() {
  'use strict';

  // Variables privadas del módulo
  let selectedProductImages = [];
  let existingProductImages = [];
  let imagesToDelete = [];
  let currentCategoryTienda = '';

  // Configuración
  const MAX_IMAGES = 5;

  /**
     * Inicializa el gestor de imágenes
     */
  function init() {
    console.log('ImageManager: Inicializando gestor de imágenes...');

    // Inicializar el uploader
    initProductImageUploader();

    // Resetear estado inicial
    resetImageState();

    console.log('ImageManager: Gestor inicializado correctamente');
    return true;
  }

  /**
     * Resetea el estado de las imágenes
     */
  function resetImageState() {
    selectedProductImages = [];
    existingProductImages = [];
    imagesToDelete = [];
    currentCategoryTienda = '';

    // Limpiar previsualización
    renderProductImagePreview();
  }

  /**
     * Renderiza el preview de todas las imágenes
     */
  function renderProductImagePreview() {
    const zone = document.getElementById('prodImgPreview');
    if (!zone) {
      console.warn('ImageManager: prodImgPreview no encontrado');
      return false;
    }

    zone.innerHTML = '';

    // Renderizar imágenes existentes
    existingProductImages.forEach((img, idx) => {
      const div = createExistingImageElement(img, idx);
      zone.appendChild(div);
    });

    // Renderizar imágenes nuevas
    selectedProductImages.forEach((file, idx) => {
      renderNewImagePreview(file, idx);
    });

    // Actualizar ghost card
    if (typeof updateGhostCard === 'function') {
      updateGhostCard();
    }

    return true;
  }

  /**
     * Crea elemento para imagen existente
     * @param {Object} img - Datos de la imagen
     * @param {number} idx - Índice
     * @returns {HTMLElement}
     */
  function createExistingImageElement(img, idx) {
    const div = document.createElement('div');
    div.style.cssText = 'position:relative; aspect-ratio:1/1; border-radius:8px; overflow:hidden;';
    div.innerHTML = `
            <img src="/uploads/${img.nombre_archivo}" style="width:100%; height:100%; object-fit:cover; transform: scale(1);">
            <button type="button" onclick="ImageManager.deleteExistingImage(${idx})" style="position:absolute; top:2px; right:2px; background:red; color:white; border:none; border-radius:50%; width:20px; height:20px; font-size:12px;">&times;</button>
        `;
    return div;
  }

  /**
     * Renderiza preview de imagen nueva
     * @param {File} file - Archivo de imagen
     * @param {number} idx - Índice
     */
  function renderNewImagePreview(file, idx) {
    const reader = new FileReader();
    reader.onload = (e) => {
      const div = document.createElement('div');
      div.style.cssText = 'position:relative; aspect-ratio:1/1; border-radius:8px; overflow:hidden; border:1px solid blue;';
      div.innerHTML = `
                <img src="${e.target.result}" style="width:100%; height:100%; object-fit:cover; transform: scale(1);">
                <button type="button" onclick="ImageManager.deleteNewImage(${idx})" style="position:absolute; top:2px; right:2px; background:black; color:white; border:none; border-radius:50%; width:20px; height:20px; font-size:12px;">&times;</button>
            `;

      const zone = document.getElementById('prodImgPreview');
      if (zone) {
        zone.appendChild(div);
      }

      // Trigger ghost update después de la última imagen
      if (idx === selectedProductImages.length - 1) {
        if (typeof updateGhostCard === 'function') {
          updateGhostCard();
        }
      }
    };
    reader.readAsDataURL(file);
  }

  /**
     * Elimina imagen existente
     * @param {number} idx - Índice de la imagen
     */
  function deleteExistingImage(idx) {
    if (confirm('¿Borrar?')) {
      const deletedImage = existingProductImages[idx];
      existingProductImages.splice(idx, 1);

      // Agregar a lista de eliminación si tiene ID
      if (deletedImage.id) {
        imagesToDelete.push(deletedImage.id);
      }

      renderProductImagePreview();
    }
  }

  /**
     * Elimina imagen nueva
     * @param {number} idx - Índice de la imagen
     */
  function deleteNewImage(idx) {
    selectedProductImages.splice(idx, 1);
    renderProductImagePreview();
  }

  /**
     * Inicializa el uploader de imágenes
     */
  function initProductImageUploader() {
    const zone = document.getElementById('prodImgZone');
    const input = document.getElementById('prodImagenes');

    if (!zone || !input) {
      console.warn('ImageManager: Elementos de uploader no encontrados');
      return false;
    }

    // Click en zona para abrir selector
    zone.onclick = () => input.click();

    // Manejo de selección de archivos
    input.onchange = (e) => {
      if (e.target.files && e.target.files.length > 0) {
        Array.from(e.target.files).forEach(file => {
          if (selectedProductImages.length < MAX_IMAGES) {
            selectedProductImages.push(file);
          } else {
            console.warn(`ImageManager: Límite de ${MAX_IMAGES} imágenes alcanzado`);
          }
        });
        renderProductImagePreview();
        input.value = '';
      }
    };

    return true;
  }

  /**
     * Carga imágenes existentes de un producto
     * @param {Object} product - Datos del producto
     */
  function loadExistingImages(product) {
    existingProductImages = [];
    selectedProductImages = [];
    imagesToDelete = [];

    if (product.imagen_principal) {
      existingProductImages = [{
        id: product.imagen_principal,
        nombre_archivo: product.imagen_principal
      }];
    } else if (product.imagenes && Array.isArray(product.imagenes)) {
      existingProductImages = product.imagenes.map(img =>
        typeof img === 'string' ? { nombre_archivo: img } : img
      );
    }

    renderProductImagePreview();
  }

  /**
     * Prepara imágenes para guardar
     * @param {string} productId - ID del producto
     * @param {FormData} formData - FormData donde agregar las imágenes
     */
  function prepareImagesForSave(productId, formData) {
    // Agregar imágenes nuevas
    selectedProductImages.forEach(file => {
      const fieldName = productId ? 'imagenes_nuevas[]' : 'imagenes[]';
      formData.append(fieldName, file);
    });

    // Agregar imágenes a eliminar
    if (productId && imagesToDelete.length > 0) {
      formData.append('imagenes_eliminar', JSON.stringify(imagesToDelete));
    }

    return true;
  }

  /**
     * Obtiene imágenes seleccionadas
     * @returns {Array}
     */
  function getSelectedImages() {
    return [...selectedProductImages];
  }

  /**
     * Obtiene imágenes existentes
     * @returns {Array}
     */
  function getExistingImages() {
    return [...existingProductImages];
  }

  /**
     * Obtiene imágenes a eliminar
     * @returns {Array}
     */
  function getImagesToDelete() {
    return [...imagesToDelete];
  }

  /**
     * Verifica si el módulo está inicializado
     * @returns {boolean}
     */
  function isInitialized() {
    return true; // Siempre inicializado después de init()
  }

  /**
     * Obtiene estadísticas de imágenes
     * @returns {Object}
     */
  function getImageStats() {
    return {
      selected: selectedProductImages.length,
      existing: existingProductImages.length,
      toDelete: imagesToDelete.length,
      maxAllowed: MAX_IMAGES,
      canAddMore: selectedProductImages.length < MAX_IMAGES
    };
  }

  /**
     * Destruye el gestor de imágenes
     */
  function destroy() {
    selectedProductImages = [];
    existingProductImages = [];
    imagesToDelete = [];
    currentCategoryTienda = '';

    console.log('ImageManager: Destruido correctamente');
  }

  // API pública del módulo
  return {
    init,
    reset: resetImageState,
    render: renderProductImagePreview,
    deleteExistingImage,
    deleteNewImage,
    loadExistingImages,
    prepareForSave: prepareImagesForSave,
    getSelected: getSelectedImages,
    getExisting: getExistingImages,
    getToDelete: getImagesToDelete,
    getStats: getImageStats,
    isInitialized,
    destroy
  };
})();

// Exponer globalmente para compatibilidad con código existente
window.ImageManager = ImageManager;
window.renderProductImagePreview = ImageManager.render;
window.deleteExistingImage = ImageManager.deleteExistingImage;
window.deleteNewImage = ImageManager.deleteNewImage;
window.initProductImageUploader = ImageManager.init;
