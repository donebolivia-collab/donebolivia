/**
 * Badges Module - Sistema modular de gestión de insignias
 * Reemplaza la lógica de badges en editor-tienda.js
 * Usa Tom Select para una mejor experiencia de usuario
 */

// Alias para Tom Select
const tom = window.TomSelect;

class BadgesModule {
  constructor() {
    this.badgesMultiSelect = null;
    this.availableBadges = window.availableBadges || [];
    this.initialized = false;
  }

  async init() {
    if (this.initialized) return;

    console.log('Initializing Badges Module...');

    // Esperar a que Tom Select esté cargado
    if (typeof tom === 'undefined') {
      console.error('Tom Select no está cargado');
      return;
    }

    // Esperar a que los datos de badges estén disponibles
    await this.waitForBadgesData();

    this.initializeBadgesMultiSelect();
    this.initialized = true;

    console.log('Badges Module initialized successfully');
  }

  async waitForBadgesData() {
    let attempts = 0;
    const maxAttempts = 50;

    while ((!window.availableBadges || window.availableBadges.length === 0) && attempts < maxAttempts) {
      await new Promise(resolve => setTimeout(resolve, 100));
      attempts++;
    }

    if (window.availableBadges && window.availableBadges.length > 0) {
      this.availableBadges = window.availableBadges;
      console.log(`Loaded ${this.availableBadges.length} badges`);
    } else {
      console.warn('No badges data available after waiting');
    }
  }

  initializeBadgesMultiSelect() {
    const container = document.getElementById('badgesMultiSelect');
    if (!container) {
      console.warn('Badges container not found');
      return;
    }

    // Destruir instancia previa si existe
    if (this.badgesMultiSelect && typeof this.badgesMultiSelect.destroy === 'function') {
      this.badgesMultiSelect.destroy();
      this.badgesMultiSelect = null;
    }

    // Transformar badges al formato que requiere Tom Select
    const options = this.availableBadges.map(badge => ({
      value: String(badge.id),
      text: badge.nombre,
      image: badge.svg_path ? `/${badge.svg_path}` : null
    }));

    // Inicializar Tom Select
    this.badgesMultiSelect = new tom('#badgesSelect', {
      options: options,
      items: [],
      plugins: ['checkbox_options', 'remove_button'],
      maxItems: null,
      create: false,
      render: {
        option: (data, escape) => {
          // Versión limpia solo con texto y color de marca
          return `<div class="badge-option">
                    <span class="badge-option-text">${escape(data.text)}</span>
                  </div>`;

          /* Versión con imágenes (comentada si no se ve limpio)
          if (data.image) {
            return `<div class="badge-option">
                      <img src="${data.image}" alt="${escape(data.text)}" class="badge-option-image" style="width: 20px; height: 20px; object-fit: contain;">
                      <span class="badge-option-text">${escape(data.text)}</span>
                    </div>`;
          }
          return `<div class="badge-option">
                    <span class="badge-option-text">${escape(data.text)}</span>
                  </div>`;
          */
        },
        item: (data, escape) => {
          // Versión limpia solo con texto
          return `<div class="badge-item">
                    <span class="badge-item-text">${escape(data.text)}</span>
                  </div>`;

          /* Versión con imágenes (comentada si no se ve limpio)
          if (data.image) {
            return `<div class="badge-item">
                      <img src="${data.image}" alt="${escape(data.text)}" class="badge-item-image" style="width: 20px; height: 20px; object-fit: contain;">
                      <span class="badge-item-text">${escape(data.text)}</span>
                    </div>`;
          }
          return `<div class="badge-item">
                    <span class="badge-item-text">${escape(data.text)}</span>
                  </div>`;
          */
        }
      },
      onItemAdd: (value, item) => {
        this.updateBadgesInput();
        this.updateGhostCard();
      },
      onItemRemove: (value) => {
        this.updateBadgesInput();
        this.updateGhostCard();
      }
    });

    // Exponer instancia global para compatibilidad
    window.badgesMultiSelect = this.badgesMultiSelect;
  }

  updateBadgesInput() {
    const badgesInput = document.getElementById('badgesInput');
    if (badgesInput && this.badgesMultiSelect) {
      const values = this.badgesMultiSelect.getValue();
      badgesInput.value = values.join(',');
    }
  }

  updateGhostCard() {
    // Llamar a la función global de ghost card si existe
    if (typeof window.updateGhostCard === 'function') {
      window.updateGhostCard();
    }
  }

  // Método para establecer valores (usado al editar productos)
  setValues(badgeIds) {
    if (!this.badgesMultiSelect) return;

    const values = Array.isArray(badgeIds) ? badgeIds : (badgeIds ? badgeIds.split(',') : []);
    this.badgesMultiSelect.setValue(values);
  }

  // Método para obtener valores
  getValues() {
    if (!this.badgesMultiSelect) return [];
    return this.badgesMultiSelect.getValue();
  }

  // Método para destruir la instancia
  destroy() {
    if (this.badgesMultiSelect) {
      this.badgesMultiSelect.destroy();
      this.badgesMultiSelect = null;
    }
    this.initialized = false;
  }
}

// Crear instancia global
window.badgesModule = new BadgesModule();

// Inicialización automática cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
  // Solo inicializar si estamos en el editor de tienda
  if (window.location.pathname.includes('/mi/tienda_editor.php')) {
    window.badgesModule.init();
  }
});

// Exponer para inicialización manual
window.initBadgesModule = () => {
  window.badgesModule.init();
};

// Función específica para inicializar en el editor
window.initBadgesEditor = () => {
  console.log('Forzando inicialización de Badges Editor...');

  // Verificar que Tom Select esté disponible
  if (typeof window.TomSelect === 'undefined') {
    console.error('Tom Select no está cargado');
    return;
  }

  // Esperar a que los datos estén disponibles
  if (!window.availableBadges || window.availableBadges.length === 0) {
    console.warn('Badges no disponibles aún, esperando...');
    setTimeout(() => window.initBadgesEditor(), 100);
    return;
  }

  // Forzar inicialización
  if (window.badgesModule) {
    window.badgesModule.initializeBadgesMultiSelect();
    console.log('Badges Editor inicializado correctamente');
  } else {
    console.error('Badges Module no encontrado');
  }
};

// DEBUG VISUAL - DIAGNÓSTICO TÉCNICO
setTimeout(() => {
  console.log('--- DEBUG BADGES ---');
  const el = document.querySelector('.ts-control');
  if (el) {
    const style = window.getComputedStyle(el);
    console.log('Altura real:', style.height);
    console.log('Borde real:', style.border);
    console.log('Background real:', style.background);
    console.log('Display real:', style.display);
    console.log('Padding real:', style.padding);
    console.log('Archivo CSS cargado:', document.querySelector('link[href*="badges-multiselect"]')?.href);
    console.log('Elemento encontrado:', el);
    console.log('Clases del elemento:', el.className);
    console.log('Padre del elemento:', el.parentElement);
    console.log('Estilos computados completos:', style);
  } else {
    console.error('ERROR: No se encuentra el elemento .ts-control en el DOM');
    console.log('Buscando alternativas...');
    console.log('ts-wrapper:', document.querySelector('.ts-wrapper'));
    console.log('badges-multiselect:', document.querySelector('.badges-multiselect'));
    console.log('badgesSelect:', document.querySelector('#badgesSelect'));
    console.log('Todos los selects:', document.querySelectorAll('select'));
  }
}, 2000); // Esperar 2 segundos para que todo cargue
