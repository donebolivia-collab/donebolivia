/**
 * ImageUploader - Clase universal para manejo de imágenes
 * Solución DRY para todos los campos de subida
 * MODO SEGURO: No reemplaza, solo añade funcionalidad
 */
class ImageUploader {
  constructor(config) {
    this.config = {
      container: null,
      inputId: null,
      input: null,
      previewId: null,
      placeholderId: null,
      deleteBtnId: null,
      deleteBtn: null,
      type: null, // banner, logo, producto
      apiEndpoint: '/api/subir_imagen_tienda.php',
      deleteEndpoint: '/api/eliminar_imagen_tienda.php',
      maxSize: 5 * 1024 * 1024, // 5MB
      onSuccess: null,
      onError: null,
      onDelete: null,
      safeMode: true, // MODO SEGURO: No elimina código existente
      ...config
    };

    this.originalHandlers = {}; // Backup de handlers originales
    this.init();
  }

  init() {
    this.bindEvents();
    this.updateUI();
  }

  bindEvents() {
    // MODO SEGURO: Buscar por múltiples métodos
    let input = document.getElementById(this.config.inputId);
    const container = this.config.container ? document.getElementById(this.config.container) : null;

    // Si no hay inputId, buscar dentro del container
    if (!input && container) {
      input = container.querySelector('input[type="file"]');
    }

    // Si pasan el input directamente
    if (this.config.input) {
      input = this.config.input;
    }

    let deleteBtn = document.getElementById(this.config.deleteBtnId);

    // Si no hay deleteBtnId, buscar dentro del container
    if (!deleteBtn && container) {
      // BUSCAR CUALQUIER TIPO DE BOTÓN DE BORRADO (Legacy y Nuevo)
      deleteBtn = container.querySelector('.btn-delete-image, .btn-delete-banner, .remove-logo-btn');
    }

    // Si pasan el botón directamente
    if (this.config.deleteBtn) {
      deleteBtn = this.config.deleteBtn;
    }

    // Si input sigue siendo null, intentar buscarlo por el ID del container + "Input" (convención común)
    if (!input && this.config.container) {
      // Caso específico para Banners
      if (this.config.container.includes('banner')) {
        const index = this.config.container.replace(/\D/g, '');
        input = document.getElementById(`bannerInput${index}`);
      }
    }

    // BACKUP de handlers originales (MODO SEGURO)
    if (input && input.onchange) {
      this.originalHandlers.onchange = input.onchange;
      input.onchange = null; // Desvincular handler inline para evitar doble disparo
    }
    if (deleteBtn && deleteBtn.onclick) {
      this.originalHandlers.onclick = deleteBtn.onclick;
      deleteBtn.onclick = null; // Desvincular handler inline
    }

    if (input) {
      input.addEventListener('change', (e) => this.handleUpload(e));
    } else {
      console.warn(`ImageUploader: No se encontró input para ${this.config.type}`);
    }

    if (deleteBtn) {
      deleteBtn.addEventListener('click', (e) => this.handleDelete(e));
    }

    // Guardar referencias
    this.input = input;
    this.deleteBtn = deleteBtn;
    this.container = container;

    // [NUEVO] Listener en el contenedor para abrir el selector de archivos
    // Esto reemplaza el onclick inline del HTML y previene conflictos con el botón eliminar
    if (this.container && this.input) {
      this.container.addEventListener('click', (e) => {
        // Si el click fue en el botón de eliminar (o sus hijos), NO abrir selector
        if (this.deleteBtn && (e.target === this.deleteBtn || this.deleteBtn.contains(e.target))) {
          return;
        }
        // Si el click fue en el input mismo, no hacer nada (ya se maneja nativo)
        if (e.target === this.input) return;

        this.input.click();
      });

      // Soporte básico para Drag & Drop visual
      this.container.addEventListener('dragover', (e) => {
        e.preventDefault();
        this.container.classList.add('drag-over');
      });
      this.container.addEventListener('dragleave', () => this.container.classList.remove('drag-over'));
      this.container.addEventListener('drop', (e) => {
        e.preventDefault();
        this.container.classList.remove('drag-over');
        if (e.dataTransfer.files.length > 0 && this.input) {
          // Asignar archivos al input y disparar evento change manualmente
          this.input.files = e.dataTransfer.files;
          this.input.dispatchEvent(new Event('change'));
        }
      });
    }
  }

  async handleUpload(event) {
    const file = event.target.files[0];
    if (!file) return;

    if (file.size > this.config.maxSize) {
      this.showError('El archivo es demasiado grande (máx 5MB)');
      return;
    }

    this.showLoading();

    try {
      const formData = new FormData();
      formData.append('imagen', file);
      formData.append('tipo', this.config.type);

      const response = await fetch(this.config.apiEndpoint, {
        method: 'POST',
        body: formData
      });

      const data = await response.json();

      if (data.success) {
        this.updatePreview(data.filename);
        this.showSuccess('Imagen subida correctamente');

        if (this.config.onSuccess) {
          this.config.onSuccess(data);
        }
      } else {
        this.showError(data.message || 'Error al subir imagen');
      }
    } catch (error) {
      console.error('Upload error:', error);
      this.showError('Error de conexión');
    } finally {
      this.hideLoading();
      event.target.value = ''; // Permitir subir mismo archivo
    }
  }

  async handleDelete(event) {
    if (event) {
      event.stopPropagation();
      event.preventDefault();
    }

    if (!confirm('¿Estás seguro de eliminar esta imagen?')) return;

    this.showDeleteLoading();

    try {
      const response = await fetch(this.config.deleteEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ tipo: this.config.type })
      });

      const data = await response.json();

      if (data.success) {
        this.clearPreview();
        this.showSuccess('Imagen eliminada');

        if (this.config.onDelete) {
          this.config.onDelete(data);
        }
      } else {
        this.showError(data.message || 'Error al eliminar imagen');
      }
    } catch (error) {
      console.error('Delete error:', error);
      this.showError('Error de conexión');
    } finally {
      this.hideDeleteLoading();
    }
  }

  // Métodos de UI unificados
  showLoading() {
    const placeholder = document.getElementById(this.config.placeholderId);
    if (placeholder) {
      placeholder.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    }
  }

  hideLoading() {
    this.updatePlaceholder();
  }

  showDeleteLoading() {
    const deleteBtn = document.getElementById(this.config.deleteBtnId);
    if (deleteBtn) {
      deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    }
  }

  hideDeleteLoading() {
    const deleteBtn = document.getElementById(this.config.deleteBtnId);
    if (deleteBtn) {
      deleteBtn.innerHTML = '<i class="fas fa-times"></i>';
    }
  }

  updatePreview(filename) {
    const preview = document.getElementById(this.config.previewId);
    const placeholder = document.getElementById(this.config.placeholderId);
    const deleteBtn = document.getElementById(this.config.deleteBtnId);
    const container = document.getElementById(this.config.container);

    if (preview) {
      const url = this.getImageUrl(filename);
      preview.src = url;
      preview.style.display = 'block';
    }

    if (placeholder) {
      placeholder.style.display = 'none';
    }

    if (deleteBtn) {
      deleteBtn.style.display = 'flex';
    }

    if (container) {
      container.classList.add('has-image');
    }
  }

  clearPreview() {
    const preview = document.getElementById(this.config.previewId);
    const placeholder = document.getElementById(this.config.placeholderId);
    const deleteBtn = document.getElementById(this.config.deleteBtnId);
    const container = document.getElementById(this.config.container);

    if (preview) {
      preview.src = '';
      preview.style.display = 'none';
    }

    if (placeholder) {
      placeholder.style.display = 'flex';
      this.updatePlaceholder();
    }

    if (deleteBtn) {
      deleteBtn.style.display = 'none';
    }

    if (container) {
      container.classList.remove('has-image');
    }
  }

  updatePlaceholder() {
    const placeholder = document.getElementById(this.config.placeholderId);
    if (placeholder) {
      placeholder.innerHTML = '<i class="fas fa-cloud-upload-alt"></i>';
    }
  }

  getImageUrl(filename) {
    const isLogo = this.config.type.includes('logo');
    const folder = isLogo ? '/uploads/logos/' : '/uploads/';
    const timestamp = Date.now();
    return `${folder}${filename}?v=${timestamp}`;
  }

  showSuccess(message) {
    if (typeof showNotif === 'function') {
      showNotif(message);
    }
  }

  showError(message) {
    if (typeof showNotif === 'function') {
      showNotif(message, 'error');
    }
  }

  updateUI() {
    // Actualizar estado inicial basado en si hay imagen o no
    const preview = document.getElementById(this.config.previewId);
    if (preview && preview.src && preview.src !== window.location.href) {
      this.updatePreview(preview.src.split('/').pop().split('?')[0]);
    } else {
      this.clearPreview();
    }
  }
}

// Exportar para uso global
window.ImageUploader = ImageUploader;
