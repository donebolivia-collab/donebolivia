/**
 * Editor Tienda Split JS - Modelo A (Recarga del Previsualizador)
 * Logic for Sidebar, Accordions, and Preview Reloading.
 * Real-time sync via postMessage has been completely removed.
 */

const $ = (selector, parent = document) => TiendaGuard.safeQuery(selector, parent);
const safeGetById = (id) => TiendaGuard.safeQuery(`#${id}`);

document.addEventListener('DOMContentLoaded', () => {
  initAccordions();
  initColorPicker();
  initSidebarProducts();
  initStoreSync(); // Attaches input listeners
  updateSaveIndicator(true); // Init state
  initRefactoredEventListeners();
  initBrandIdentityControls();

  // Initial Load
  renderSidebarProducts();
  updateBannerSlotsUI();

  // Check URL for success params
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('status') === 'success') {
    showNotif('Guardado correctamente');
  }
});

// --- NUEVO: FUNCIÓN CENTRAL DE RECARGA DEL PREVISUALIZADOR ---
function reloadPreviewFrame() {
  const storeFrame = safeGetById('storeFrame');
  const loader = document.getElementById('previewLoader'); 

  if (!storeFrame) return;

  console.log('Reloading preview frame with cache-busting...');

  // The loader is now shown by saveChanges(), but we ensure it's visible here too.
  if (loader) {
    loader.style.display = 'flex';
  }

  // The 'load' event will hide the loader.
  const onFrameLoad = () => {
    if (loader) {
      loader.style.display = 'none';
    }
    storeFrame.removeEventListener('load', onFrameLoad);
    console.log('Preview frame reloaded successfully.');
  };

  storeFrame.addEventListener('load', onFrameLoad);

  // Add a cache-busting parameter to the URL to force a full reload.
  try {
    const currentSrc = storeFrame.src;
    const newSrc = new URL(currentSrc, window.location.origin);
    newSrc.searchParams.set('v', Date.now());
    storeFrame.src = newSrc.toString();
  } catch (e) {
    console.error("Failed to create URL for reloading, falling back to simple reload.", e);
    storeFrame.src = storeFrame.src; // Fallback
  }
}

// --- MANEJADOR DE EVENTOS DELEGADO ---
function initRefactoredEventListeners() {
  document.body.addEventListener('click', function(e) {
    const target = e.target.closest('[data-action]');
    if (!target) return;

    const action = target.dataset.action;

    switch (action) {
      case 'select-ui-option':
        {
          const parentId = target.dataset.parent;
          const value = target.dataset.value;
          const label = target.dataset.label;
          const callbackName = target.dataset.callback;
          const callback = window[callbackName];

          if (typeof callback === 'function') {
            selectUIOption(parentId, value, label, callback);
          } else {
            console.error(`Callback function "${callbackName}" not found.`);
          }
          break;
        }
      case 'edit-social':
        {
          const socialNetwork = target.dataset.social;
          if (socialNetwork) {
            editSocial(socialNetwork);
          }
          break;
        }
      case 'handle-location':
        {
          handleLocation();
          break;
        }
      case 'update-theme':
        {
          const color = target.dataset.color;
          if (!color) break;

          document.querySelectorAll('.color-swatch-mini').forEach(s => s.classList.remove('active'));
          target.classList.add('active');
          window.tiendaState.color = color;

          const currentColorPreview = safeGetById('currentColorPreview');
          if (currentColorPreview) {
            currentColorPreview.style.backgroundColor = color;
          }

          const colorDropdown = safeGetById('colorDropdown');
          if (colorDropdown) {
            const menu = colorDropdown.querySelector('.ui-menu');
            if (menu) menu.classList.remove('show');
          }

          markUnsaved(); // DISPARAMOS GUARDADO
          break;
        }
    }
  });
}

// --- SIDEBAR TOGGLE ---
window.toggleSidebar = function() {
  const sidebar = safeGetById('editorSidebar');
  const container = $('.editor-canvas-container');
  const expandBtn = safeGetById('expandBtn');

  sidebar.classList.toggle('collapsed');

  if (sidebar.classList.contains('collapsed')) {
    container.classList.add('expanded');
    expandBtn.style.display = 'block';
  } else {
    container.classList.remove('expanded');
    expandBtn.style.display = 'none';
  }
};

// --- ACCORDION SYSTEM ---
window.initAccordions = function() {
  // No action needed on init
};

window.toggleAccordion = function(header) {
  if (!header) return;
  const item = header.parentElement;
  if (!item) return;
  const isActive = item.classList.contains('open');

  document.querySelectorAll('.accordion-item').forEach(acc => {
    if (acc !== item) {
      acc.classList.remove('open');
    }
  });

  if (!isActive) {
    item.classList.add('open');
  } else {
    item.classList.remove('open');
  }
};

// --- LIVE PREVIEW SYNC (ELIMINADO) ---
// La comunicación en tiempo real (postMessage) ha sido eliminada.
// La actualización ahora ocurre solo después de guardar los cambios, recargando el iframe.

// --- STATE MANAGEMENT & AUTO-SAVE ---
let saveTimeout;
let hasUnsavedChanges = false;

let isSaving = false; // Flag to prevent concurrent saves

function markUnsaved() {
  // Instead of waiting, we call save immediately.
  // The isSaving flag prevents multiple calls if the user clicks very quickly.
  if (isSaving) {
    console.log("Save already in progress. Skipping.");
    return;
  }
  saveChanges();
}

function updateSaveIndicator(saved) {
  const indicator = safeGetById('autoSaveStatus');
  const bar = safeGetById('statusBar');
  if (!indicator) return;

  // We only use the status bar for errors now. Success is handled by the loader.
  if (saved) {
    // The successful "Guardado" is no longer displayed in the bar.
    // The disappearance of the loader is the confirmation.
    if (bar) {
      bar.style.backgroundColor = '#00a650'; // Momentary green if desired
    }
    // Quickly hide any previous state
    setTimeout(() => {
        indicator.classList.remove('visible');
    }, 500);
  } else {
    indicator.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Error al guardar';
    if (bar) {
      bar.style.backgroundColor = '#d9534f'; // Red
      bar.style.color = '#ffffff';
    }
    indicator.classList.add('visible');
  }
}

async function saveChanges() {
  isSaving = true;
  hasUnsavedChanges = true; // Mark as having changes to save

  const loader = document.getElementById('previewLoader');
  if (loader) {
    loader.style.display = 'flex'; // 1. Show loader IMMEDIATELY
  }

  const data = {
    nombre: safeGetById('storeName')?.value || '',
    descripcion: safeGetById('storeDescription')?.value || '',
    color_primario: window.tiendaState.color,
    gridDensity: window.tiendaState.gridDensity,
    estilo_fondo: window.tiendaState.estiloFondo,
    estilo_bordes: window.tiendaState.estiloBordes,
    estilo_fotos: window.tiendaState.estiloFotos,
    tipografia: window.tiendaState.tipografia,
    tamano_texto: window.tiendaState.tamanoTexto,
    estilo_tarjetas: window.tiendaState.estiloTarjetas,
    secciones_destacadas_activo: window.tiendaState.seccionesDestacadas.activo,
    secciones_destacadas_estilo: window.tiendaState.seccionesDestacadas.estilo,
    mostrar_banner: window.tiendaState.banner.activo,
    banner_titulo: window.tiendaState.banner.titulo,
    banner_subtitulo: window.tiendaState.banner.subtitulo,
    banner_texto_boton: window.tiendaState.banner.boton,
    whatsapp: safeGetById('inputWhatsapp')?.value || '',
    email_contacto: safeGetById('inputEmail')?.value || '',
    direccion: safeGetById('inputDireccion')?.value || '',
    google_maps_url: safeGetById('inputMaps')?.value || '',
    facebook_url: safeGetById('inputFacebook')?.value || '',
    instagram_url: safeGetById('inputInstagram')?.value || '',
    tiktok_url: safeGetById('inputTiktok')?.value || '',
    telegram_user: safeGetById('inputTelegram')?.value || '',
    youtube_url: safeGetById('inputYoutube')?.value || '',
    menu_items: JSON.stringify(window.menuItems || []),
    logo_principal: window.tiendaState.logo_principal,
    mostrar_logo: window.tiendaState.mostrar_logo,
    mostrar_nombre: window.tiendaState.mostrar_nombre,
    navbar_style: window.tiendaState.navbarStyle || 'blanco'
  };

  try {
    const res = await fetch('/api/guardar_tienda.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    const resData = await res.json();
    if (resData.success) {
      console.log('Save successful, reloading preview.');
      hasUnsavedChanges = false;
      // updateSaveIndicator(true); // No longer needed, loader is the feedback
      reloadPreviewFrame(); // 2. If save is successful, reload the frame.
                             // The iframe's 'load' event will hide the loader.
    } else {
      console.error('Save failed', resData);
      updateSaveIndicator(false);
      if (loader) {
        loader.style.display = 'none'; // Hide loader on failure
      }
      showNotif('Error al guardar', 'error');
    }
  } catch (e) {
    console.error('Save error', e);
    updateSaveIndicator(false);
    if (loader) {
        loader.style.display = 'none'; // Hide loader on failure
    }
  } finally {
    isSaving = false; // Allow the next save
  }
}

// Attach listeners to main text inputs
function initStoreSync() {
  const inputs = ['storeName', 'storeDescription', 'inputWhatsapp', 'inputEmail', 'inputDireccion'];
  inputs.forEach(id => {
    const el = safeGetById(id);
    if (el) {
      el.addEventListener('input', () => {
        markUnsaved(); // Solo marcamos para guardar, no más postMessage
      });
    }
  });
}

// SYNC HELPERS (OBSOLETOS)
window.updateMenuInFrame = function() {
    markUnsaved();
};

// --- COLOR PICKER ---
const colors = [
  { color: '#FF0000', name: 'Rojo' },
  { color: '#E1306C', name: 'Rosa Instagram' },
  { color: '#FF017B', name: 'Fucsia' },
  { color: '#BA2C5D', name: 'Rosa Oscuro' },
  { color: '#ff6a00', name: 'Naranja Done' },
  { color: '#F16253', name: 'Naranja Salmón' },
  { color: '#D85427', name: 'Naranja Ladrillo' },
  { color: '#E07A5F', name: 'Terracota' },
  { color: '#C19A6B', name: 'Camel' },
  { color: '#794C1E', name: 'Marrón Café' },
  { color: '#FFBF00', name: 'Mostaza' },
  { color: '#D4AF37', name: 'Dorado' },
  { color: '#E1E66B', name: 'Lima Limón' },
  { color: '#25D366', name: 'Verde WhatsApp' },
  { color: '#00A86B', name: 'Verde Jade' },
  { color: '#20c997', name: 'Menta' },
  { color: '#008080', name: 'Teal' },
  { color: '#8A9A5B', name: 'Verde Salvia' },
  { color: '#3A5829', name: 'Verde Oscuro' },
  { color: '#8C8733', name: 'Verde Oliva' },
  { color: '#666229', name: 'Verde Musgo' },
  { color: '#1a73e8', name: 'Azul Facebook' },
  { color: '#038CB4', name: 'Azul Cielo' },
  { color: '#026E8F', name: 'Azul Marino' },
  { color: '#00374A', name: 'Azul Petróleo' },
  { color: '#8C92AC', name: 'Azul Acero' },
  { color: '#22226B', name: 'Azul Corporativo' },
  { color: '#6C4DF2', name: 'Púrpura' },
  { color: '#6A0DAD', name: 'Morado Real' },
  { color: '#5A54A4', name: 'Lavanda' },
  { color: '#BDB0D0', name: 'Lila' },
  { color: '#602A7B', name: 'Morado' },
  { color: '#34495E', name: 'Gris Pizarra' },
  { color: '#999999', name: 'Gris' },
  { color: '#000000', name: 'Negro' },
  { color: '#FFFFFF', name: 'Blanco' }
];

function initColorPicker() {
  const container = safeGetById('colorSwatches');
  const hexInput = safeGetById('hexColorInput');
  const hexAddon = safeGetById('hexColorAddon');

  if (!container || !hexInput || !hexAddon) return;

  container.innerHTML = '';
  colors.forEach(colorObj => {
    const el = document.createElement('div');
    el.className = 'color-swatch-mini';
    el.style.backgroundColor = colorObj.color;
    el.title = colorObj.name;
    if (colorObj.color.toLowerCase() === window.tiendaState.color.toLowerCase()) {
      el.classList.add('active');
    }
    el.dataset.action = 'update-theme';
    el.dataset.color = colorObj.color;
    container.appendChild(el);
  });

  const initialColor = window.tiendaState.color;
  hexInput.value = initialColor.toUpperCase();
  hexAddon.style.backgroundColor = initialColor;

  document.body.addEventListener('click', e => {
    const target = e.target.closest('[data-action="update-theme"]');
    if (!target) return;
    const newColor = target.dataset.color;
    if (newColor) {
      hexInput.value = newColor.toUpperCase();
      hexAddon.style.backgroundColor = newColor;
    }
  });

  hexInput.addEventListener('input', () => {
    let value = hexInput.value.trim();
    if (!value.startsWith('#')) {
      value = '#' + value;
    }
    if (/^#[0-9A-F]{6}$/i.test(value)) {
      hexAddon.style.backgroundColor = value;
      window.tiendaState.color = value;
      markUnsaved(); // DISPARAMOS GUARDADO
      document.querySelectorAll('.color-swatch-mini').forEach(s => s.classList.remove('active'));
      const matchingSwatch = container.querySelector(`[data-color="${value.toLowerCase()}"]`);
      if (matchingSwatch) {
        matchingSwatch.classList.add('active');
      }
    }
  });
}

// --- CUSTOM DROPDOWNS (UI STYLE) ---
window.toggleUI = function(id) {
  const dropdown = safeGetById(id);
  if (!dropdown) return;
  const menu = dropdown.querySelector('.ui-menu');
  const trigger = dropdown.querySelector('.ui-trigger') || dropdown.querySelector('.ui-button');
  if (!menu || !trigger) return;

  const isActive = menu.classList.contains('show');

  document.querySelectorAll('.ui-dropdown').forEach(otherDropdown => {
    if (otherDropdown.id !== id) {
      otherDropdown.querySelector('.ui-menu')?.classList.remove('show');
      (otherDropdown.querySelector('.ui-trigger') || otherDropdown.querySelector('.ui-button'))?.classList.remove('active');
    }
  });

  menu.classList.toggle('show', !isActive);
  trigger.classList.toggle('active', !isActive);
};

window.selectUIOption = function(parentId, value, labelHtml, callback) {
  const dropdown = safeGetById(parentId);
  if (!dropdown) return;

  const labelSpan = dropdown.querySelector('.trigger-label');
  if (labelSpan) {
    labelSpan.innerHTML = labelHtml;
  }

  dropdown.querySelectorAll('.ui-option').forEach(opt => opt.classList.remove('selected'));
  const selectedOption = Array.from(dropdown.querySelectorAll('.ui-option')).find(opt =>
    opt.innerText.trim() === labelHtml.replace(/<[^>]*>/g, '').trim()
  );
  if (selectedOption) {
    selectedOption.classList.add('selected');
  }

  dropdown.querySelector('.ui-menu')?.classList.remove('show');
  (dropdown.querySelector('.ui-trigger') || dropdown.querySelector('.ui-button'))?.classList.remove('active');

  if (typeof callback === 'function') {
    callback(value);
  }
};

document.addEventListener('click', function(e) {
  if (!e.target.closest('.ui-dropdown')) {
    document.querySelectorAll('.ui-menu').forEach(m => m.classList.remove('show'));
    document.querySelectorAll('.ui-trigger, .ui-button').forEach(t => t.classList.remove('active'));
  }
});


// --- REFACTORIZACIÓN DE IDENTIDAD VISUAL ---
function updateVisualOption(key, value) {
  window.tiendaState[key] = value;
  markUnsaved(); // Solo guardamos, la recarga hará el resto.
}
window.updateVisualOption = updateVisualOption;

window.setBackground = (bg) => updateVisualOption('estiloFondo', bg);
window.setBorder = (style) => updateVisualOption('estiloBordes', style);
window.setFont = (font) => updateVisualOption('tipografia', font);
window.setTextSize = (size) => updateVisualOption('tamanoTexto', size);
window.setCardStyle = (style) => updateVisualOption('estiloTarjetas', style);
window.setNavbarStyle = (style) => updateVisualOption('navbarStyle', style);
window.setPhotoStyle = (style) => updateVisualOption('estiloFotos', style);
window.setGridDensity = (density) => updateVisualOption('gridDensity', density);

// --- BANNER LOGIC ---
window.updateBannerState = function() {
  const bannerActive = safeGetById('bannerActive');
  if (!bannerActive) return;
  const isActive = bannerActive.checked;
  window.tiendaState.banner.activo = isActive ? 1 : 0;

  if (isActive) {
    updateBannerSlotsUI();
  }
  markUnsaved(); // Solo guardamos
};

window.updateBannerSlotsUI = function() {
  if (!window.tiendaState?.banner) return;
  const imagenes = window.tiendaState.banner.imagenes || [];
  [1, 2, 3, 4].forEach((i) => {
    const slotContainer = safeGetById(`bannerSlotContainer${i}`);
    if (!slotContainer) return;
    const preview = safeGetById(`bannerPreviewImg${i}`);
    const placeholder = safeGetById(`bannerPlaceholder${i}`);
    const deleteBtn = slotContainer.querySelector('.btn-delete-banner');
    const img = imagenes[i - 1];
    if (preview && placeholder) {
      if (img) {
        preview.src = img;
        preview.style.display = 'block';
        placeholder.style.display = 'none';
        if (deleteBtn) deleteBtn.style.display = 'flex';
      } else {
        preview.src = '';
        preview.style.display = 'none';
        placeholder.style.display = 'flex';
        if (deleteBtn) deleteBtn.style.display = 'none';
      }
    }
  });
  const addBtn = safeGetById('addBannerSlotBtn');
  if (addBtn) {
    addBtn.style.display = 'none';
  }
};

// --- SECCIONES DESTACADAS ---
window.toggleFeaturedSections = function(isActive) {
  const content = safeGetById('featuredSectionsContent');
  if (!content) return;
  window.tiendaState.seccionesDestacadas.activo = isActive ? 1 : 0;
  markUnsaved();
};

window.setFeaturedSectionsStyle = function(style) {
  window.tiendaState.seccionesDestacadas.estilo = style;
  markUnsaved();
};

// --- CANVAS CONTROL ---
window.setCanvasSize = function(size) {
  const frame = safeGetById('storeFrame');
  const btns = document.querySelectorAll('.device-btn');
  if (frame) {
    frame.className = `store-iframe ${size}`;
  }
  btns.forEach((b, index) => {
    const shouldBeActive = (size === 'mobile' && index === 1) || (size !== 'mobile' && index === 0);
    b.classList.toggle('active', shouldBeActive);
  });
};

// --- PRODUCTOS ---
function initSidebarProducts() {
  // Using window.allProducts from PHP
}

// --- PRODUCT DRAWER & LOGIC ---
function resetProductDrawer() {
  const form = $('#formProducto');
  if (form) form.reset();
  $('#prodId').value = '';
  $('#drawerTitle').textContent = 'Nuevo Producto';
  selectedProductImages = [];
  existingProductImages = [];
  imagesToDelete = [];
  currentCategoryTienda = '';
  renderProductImagePreview();
  const sectionTrigger = $('#prodCatTiendaDropdown .ui-trigger');
  const sectionLabel = $('#prodCatTiendaLabel');
  if (sectionTrigger) {
    sectionTrigger.classList.remove('disabled');
    sectionTrigger.style.pointerEvents = 'auto';
    sectionTrigger.style.opacity = '1';
    sectionTrigger.onclick = () => toggleUI('prodCatTiendaDropdown');
  }
  if (sectionLabel) {
    sectionLabel.innerText = 'Inicio (General)';
  }
  if (window.badgesMultiSelect && typeof window.badgesMultiSelect.clear === 'function') {
    window.badgesMultiSelect.clear();
  }
}

async function populateDrawerForEdit(p) {
  $('#drawerTitle').textContent = 'Editar Producto';
  $('#prodId').value = p.id;
  $('#prodTitulo').value = p.titulo;
  $('#prodPrecio').value = p.precio;
  $('#prodDescripcion').value = p.descripcion;
  $('#prodCondicion').value = p.estado || 'Nuevo';

  const catSelect = $('#prodCategoriaId');
  const catTrigger = $('#prodCatIdDropdown .ui-trigger');
  const catLabel = $('#prodCatIdLabel');

  if (catSelect) {
    catSelect.value = p.categoria_id;
    let catName = 'Categoría';
    const menuOption = $(`#prodCatIdDropdown .ui-option[onclick*="'${p.categoria_id}'"]`);
    if (menuOption) {
      catName = menuOption.innerText.trim();
    }
    catLabel.innerText = catName;
    if (catTrigger) {
      catTrigger.classList.add('disabled');
      catTrigger.onclick = null;
      catTrigger.style.pointerEvents = 'none';
    }
    await cargarSubcategorias(p.categoria_id, p.subcategoria_id);
  }

  if (window.badgesModule && p.badges) {
    window.badgesModule.setValues(p.badges);
  }
  currentCategoryTienda = p.categoria_tienda || '';
  if (p.imagen_principal) {
    existingProductImages = [{ id: p.imagen_principal, nombre_archivo: p.imagen_principal }];
    renderProductImagePreview();
  } else if (p.imagenes && Array.isArray(p.imagenes)) {
    existingProductImages = p.imagenes.map(img =>
      typeof img === 'string' ? { nombre_archivo: img } : img
    );
    renderProductImagePreview();
  }
}

async function setupDrawerForNew(sectionContext = null) {
  if (window.tiendaState && window.tiendaState.categoriaDefaultId && window.tiendaState.categoriaDefaultId !== 'null') {
    const catId = window.tiendaState.categoriaDefaultId;
    const catInput = $('#prodCategoriaId');
    const catTrigger = $('#prodCatIdDropdown .ui-trigger');
    const catLabel = $('#prodCatIdLabel');
    if (catInput && catTrigger && catLabel) {
      catInput.value = catId;
      let catName = 'Categoría';
      const menuOption = $(`#prodCatIdDropdown .ui-option[onclick*="'${catId}'"]`);
      if (menuOption) {
        catName = menuOption.innerText.trim();
      }
      catLabel.innerText = catName;
      catTrigger.classList.add('disabled');
      catTrigger.onclick = null;
      catTrigger.style.pointerEvents = 'none';
      await cargarSubcategorias(catId);
    }
  } else {
    const catTrigger = $('#prodCatIdDropdown .ui-trigger');
    if (catTrigger) {
      catTrigger.classList.remove('disabled');
      catTrigger.style.pointerEvents = 'auto';
      catTrigger.onclick = () => toggleUI('prodCatIdDropdown');
    }
  }

  if (sectionContext) {
    currentCategoryTienda = sectionContext;
    const hiddenInput = $('#prodCategoriaTienda');
    if (hiddenInput) hiddenInput.value = sectionContext;
    const label = $('#prodCatTiendaLabel');
    const item = window.menuItems.find(i => i.label === sectionContext);
    if (label && item) {
      label.innerText = toTitleCase(item.label);
    }
    const trigger = $('#prodCatTiendaDropdown .ui-trigger');
    if (trigger) {
      trigger.classList.add('disabled');
      trigger.style.pointerEvents = 'none';
      trigger.style.opacity = '0.7';
      trigger.onclick = null;
    }
  }
}

let selectedProductImages = [];
let existingProductImages = [];
let imagesToDelete = [];
let currentCategoryTienda = '';

window.openProductDrawer = async function(id = null) {
  resetProductDrawer();
  const drawer = $('#productDrawer');
  if (drawer) {
    drawer.classList.add('show');
    setTimeout(() => {
      if (typeof window.initBadgesEditor === 'function') {
        window.initBadgesEditor();
      }
    }, 100);
  } else {
    console.error('PANEL LATERAL NO ENCONTRADO: No se pudo encontrar el elemento con ID #productDrawer');
    return;
  }
  if (id) {
    const productData = window.allProducts.find(item => item.id == id);
    if (productData) {
      await populateDrawerForEdit(productData);
    } else {
      console.error('Producto no encontrado para editar:', id);
      showNotif('Error: No se encontró el producto.', 'error');
      closeProductDrawer();
    }
  } else {
    await setupDrawerForNew(currentSectionFilter);
  }
};

window.generarDescripcionIA = async function() {
  const btn = document.querySelector('.btn-text-ai');
  const descField = document.getElementById('prodDescripcion');
  const titulo = document.getElementById('prodTitulo').value.trim();
  const precio = document.getElementById('prodPrecio').value.trim();
  const condicion = document.getElementById('prodCondicionLabel').innerText.trim();
  const categoria = document.getElementById('prodCatIdLabel').innerText.trim().replace('-- Seleccionar --', '');
  const subcategoria = document.getElementById('prodSubcatLabel').innerText.trim().replace('-- Primero selecciona categoría --', '').replace('-- Seleccionar --', '');

  if (!titulo || titulo.length < 3) {
    showNotif('Escribe primero el nombre del producto', 'error');
    return;
  }

  const originalText = btn.innerHTML;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generando...';
  btn.classList.add('loading');
  btn.disabled = true;

  const prompt = `Actúa como un copywriter experto en e-commerce para el mercado de Bolivia.
Escribe una descripción de venta atractiva y persuasiva para el siguiente producto, usando los datos proporcionados.

Datos del Producto:
- Título: ${titulo}
- Precio: ${precio ? `Bs. ${precio}` : 'No especificado'}
- Categoría: ${categoria || 'No especificada'}
- Subcategoría: ${subcategoria || 'No especificada'}
- Estado: ${condicion || 'No especificado'}

Reglas:
1. Tono profesional pero cercano y convincente.
2. Incluye 2-3 emojis relevantes que encajen con el producto.
3. Estructura en párrafos cortos y fáciles de leer.
4. Si el precio es bajo, resáltalo como una oportunidad. Si es alto, justifica el valor.
5. NO incluyas saludos, despedidas o frases como "Aquí está la descripción". Solo el texto de venta puro.
6. Finaliza con un llamado a la acción sutil.`;

  try {
    if (typeof puter === 'undefined' || typeof puter.ai === 'undefined' || typeof puter.ai.chat === 'undefined') {
      throw new Error('Servicio de IA (Puter.js) no está cargado o no es válido.');
    }
    const response = await puter.ai.chat(prompt);
    let content = '';
    if (response && response.message && typeof response.message.content === 'string') {
      content = response.message.content;
    } else if (response && typeof response.text === 'string') {
      content = response.text;
    } else if (typeof response === 'string') {
      content = response;
    } else {
      console.error('Respuesta inesperada de la API de Puter:', response);
      throw new Error('La estructura de la respuesta de la IA no es la esperada.');
    }
    descField.value = content.trim();
    showNotif('¡Descripción generada con IA!');
  } catch (err) {
    console.error('Error detallado de TextAI:', err);
    const errorMessage = err.message || 'Error al generar texto. Revisa la consola.';
    showNotif(errorMessage, 'error');
  } finally {
    btn.innerHTML = originalText;
    btn.classList.remove('loading');
    btn.disabled = false;
  }
};

window.closeProductDrawer = function() {
  document.getElementById('productDrawer').classList.remove('show');
};

window.cargarSubcategorias = async function(catId, selectedId = null) {
  const subInput = document.getElementById('prodSubcategoriaId');
  const subTrigger = document.getElementById('prodSubcatTrigger');
  const subLabel = document.getElementById('prodSubcatLabel');
  const subMenu = document.getElementById('prodSubcatMenu');

  if (subInput) subInput.value = '';
  if (subLabel) subLabel.innerText = 'Cargando...';
  if (subTrigger) subTrigger.classList.add('disabled');
  if (subMenu) subMenu.innerHTML = '';

  if (!catId) {
    if (subLabel) subLabel.innerText = '-- Primero selecciona categoría --';
    return;
  }

  try {
    const cleanCatId = parseInt(catId);
    if (isNaN(cleanCatId)) {
      if (subLabel) subLabel.innerText = 'Categoría inválida';
      return;
    }
    const res = await fetch(`/api/subcategorias.php?categoria_id=${cleanCatId}`);
    const data = await res.json();

    if (!data.success) {
      console.error('Error de API al cargar subcategorías:', data.error);
      if (subLabel) subLabel.innerText = 'Sin subcategorías';
      if (subTrigger) subTrigger.classList.remove('disabled');
      return;
    }

    const subs = data.data || [];
    let html = '<div class="ui-option" onclick="selectUIOption(\'prodSubcatIdDropdown\', \'\', \'-- Seleccionar --\', (val)=>{ document.getElementById(\'prodSubcategoriaId\').value=val; })">-- Seleccionar --</div>';
    let selectedName = '-- Seleccionar --';

    if (Array.isArray(subs)) {
      subs.forEach(s => {
        const isSelected = (selectedId && s.id == selectedId);
        if (isSelected) selectedName = s.nombre;
        html += `<div class="ui-option ${isSelected ? 'selected' : ''}" onclick="selectUIOption('prodSubcatIdDropdown', '${s.id}', '${s.nombre}', (val)=>{ document.getElementById('prodSubcategoriaId').value=val; })">${s.nombre}</div>`;
      });
    }

    if (subMenu) subMenu.innerHTML = html;
    if (subLabel) subLabel.innerText = selectedName;
    if (selectedId && subInput) subInput.value = selectedId;
    if (subTrigger) subTrigger.classList.remove('disabled');
  } catch (e) {
    console.error(e);
    if (subLabel) subLabel.innerText = 'Sin subcategorías';
  }
};

window.renderProductImagePreview = function() {
  const zone = document.getElementById('prodImgPreview');
  zone.innerHTML = '';

  existingProductImages.forEach((img, idx) => {
    const div = document.createElement('div');
    div.style.cssText = 'position:relative; aspect-ratio:1/1; border-radius:8px; overflow:hidden;';
    div.innerHTML = `<img src="/uploads/${img.nombre_archivo}" style="width:100%; height:100%; object-fit:cover; transform: scale(1);"><button type="button" onclick="deleteExistingImage(${idx})" style="position:absolute; top:2px; right:2px; background:red; color:white; border:none; border-radius:50%; width:20px; height:20px; font-size:12px;">&times;</button>`;
    zone.appendChild(div);
  });

  selectedProductImages.forEach((file, idx) => {
    const reader = new FileReader();
    reader.onload = (e) => {
      const div = document.createElement('div');
      div.style.cssText = 'position:relative; aspect-ratio:1/1; border-radius:8px; overflow:hidden; border:1px solid blue;';
      div.innerHTML = `<img src="${e.target.result}" style="width:100%; height:100%; object-fit:cover; transform: scale(1);"><button type="button" onclick="deleteNewImage(${idx})" style="position:absolute; top:2px; right:2px; background:black; color:white; border:none; border-radius:50%; width:20px; height:20px; font-size:12px;">&times;</button>`;
      zone.appendChild(div);
    };
    reader.readAsDataURL(file);
  });
};

window.deleteExistingImage = (idx) => {
  if (confirm('¿Borrar?')) {
    existingProductImages.splice(idx, 1);
    renderProductImagePreview();
  }
};

window.deleteNewImage = (idx) => {
  selectedProductImages.splice(idx, 1);
  renderProductImagePreview();
};

window.initProductImageUploader = function() {
  const zone = document.getElementById('prodImgZone');
  const input = document.getElementById('prodImagenes');
  if (!zone || !input) return;
  zone.onclick = () => input.click();
  input.onchange = (e) => {
    if (e.target.files && e.target.files.length > 0) {
      Array.from(e.target.files).forEach(file => {
        if (selectedProductImages.length < 5) selectedProductImages.push(file);
      });
      renderProductImagePreview();
      input.value = '';
    }
  };
};
initProductImageUploader();

window.guardarProducto = async function() {
  const formData = new FormData();
  const id = document.getElementById('prodId').value;

  if (id) formData.append('id', id);

  const titulo = document.getElementById('prodTitulo').value.trim();
  const descripcion = document.getElementById('prodDescripcion').value.trim();
  const precio = document.getElementById('prodPrecio').value;

  if (!titulo) {
    showNotif('El título es requerido', 'error');
    return;
  }
  if (titulo.length < 10) {
    showNotif('El título debe tener al menos 10 caracteres', 'error');
    return;
  }
  if (!precio || parseFloat(precio) <= 0) {
    showNotif('Precio inválido', 'error');
    return;
  }

  formData.append('titulo', titulo);
  formData.append('descripcion', descripcion);
  formData.append('precio', precio);
  formData.append('estado', document.getElementById('prodCondicion').value);

  const badges = [];
  const badgesInput = document.getElementById('badgesInput');
  if (badgesInput && badgesInput.value) {
    badges.push(...badgesInput.value.split(',').filter(b => b.trim()));
  }
  formData.append('badges', JSON.stringify(badges));
  formData.append('categoria_tienda', currentCategoryTienda);
  formData.append('categoria_id', document.getElementById('prodCategoriaId').value);
  formData.append('subcategoria_id', document.getElementById('prodSubcategoriaId').value);
  formData.append('departamento', window.tiendaState.deptCode || 'SCZ');
  formData.append('municipio', window.tiendaState.munCode || 'SCZ-001');

  selectedProductImages.forEach(file => formData.append(id ? 'imagenes_nuevas[]' : 'imagenes[]', file));
  if (id && imagesToDelete.length > 0) formData.append('imagenes_eliminar', JSON.stringify(imagesToDelete));

  const btn = document.getElementById('btnGuardarProducto');
  if (btn) {
    btn.textContent = 'Guardando...';
    btn.disabled = true;
  }

  try {
    const endpoint = id ? '/api/editar_producto_completo.php' : '/api/crear_producto_completo.php';
    const res = await fetch(endpoint, { method: 'POST', body: formData });
    const result = await res.json();

    if (result.success) {
      showNotif('Producto guardado');
      closeProductDrawer();
      location.reload(); // Recargar la página es la forma más simple de ver el nuevo producto
    } else {
      showNotif(result.message || 'Error al guardar', 'error');
    }
  } catch (e) {
    showNotif('Error de conexión', 'error');
    console.error(e);
  } finally {
    if (btn) {
      btn.textContent = 'Guardar';
      btn.disabled = false;
    }
  }
};

// --- IDENTIDAD DE MARCA (LOGO) ---
function initBrandIdentityControls() {
  const mostrarLogoToggle = document.getElementById('mostrar_logo_principal');
  const mostrarNombreToggle = document.getElementById('mostrar_nombre_tienda');

  if (!mostrarLogoToggle || !mostrarNombreToggle) {
    console.warn('Faltan elementos de UI de Identidad de Marca.');
    return;
  }

  mostrarLogoToggle.addEventListener('change', () => {
    window.tiendaState.mostrar_logo = mostrarLogoToggle.checked;
    markUnsaved();
  });

  mostrarNombreToggle.addEventListener('change', () => {
    window.tiendaState.mostrar_nombre = mostrarNombreToggle.checked;
    markUnsaved();
  });

  mostrarLogoToggle.checked = !!window.tiendaState.mostrar_logo;
  mostrarNombreToggle.checked = !!window.tiendaState.mostrar_nombre;
  updateLogoToggleState();
}

function updateLogoToggleState() {
  const mostrarLogoToggle = document.getElementById('mostrar_logo_principal');
  if (!mostrarLogoToggle) return;
  mostrarLogoToggle.disabled = false;
  mostrarLogoToggle.checked = !!window.tiendaState.mostrar_logo;
}

// --- UTILS ---
window.showNotif = function(msg, type = 'success') {
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
  if (type === 'success') icon.classList.add('fa-check-circle');
  else if (type === 'error') icon.classList.add('fa-exclamation-circle');
  document.getElementById('notifText').textContent = msg;
  el.className = `notification show ${type}`;
  setTimeout(() => el.classList.remove('show'), 3000);
};

// --- SOCIALS UPDATES ---
function updateSocialHidden(network, value) {
  const input = document.getElementById('input' + network.charAt(0).toUpperCase() + network.slice(1));
  if (input) input.value = value;
  markUnsaved();
}
window.updateSocialHidden = updateSocialHidden;

window.editSocial = function(network) {
  const inputId = 'input' + network.charAt(0).toUpperCase() + network.slice(1);
  const input = document.getElementById(inputId);
  const btn = document.querySelector('.btn-social-icon.' + network);
  if (!input) return;
  const current = input.value;
  const val = prompt('Ingresa tu usuario/link de ' + network + ':', current);
  if (val === null) return;
  input.value = val.trim();
  if (val.trim()) {
    if (btn) btn.classList.add('active');
  } else {
    if (btn) btn.classList.remove('active');
  }
  markUnsaved();
};

window.handleLocation = function() {
  const input = document.getElementById('inputMaps');
  const btn = document.querySelector('.btn-social-icon.maps');
  if (!input || !btn) return;
  if (btn.classList.contains('active')) {
    if (confirm('¿Deseas eliminar la ubicación guardada?')) {
      input.value = '';
      btn.classList.remove('active');
      markUnsaved();
    }
  } else {
    detectLocationGPS();
  }
};

window.detectLocationGPS = function() {
  const status = document.getElementById('locationStatus');
  const input = document.getElementById('inputMaps');
  const btn = document.querySelector('.btn-social-icon.maps');
  if (!navigator.geolocation) {
    alert('Geolocalización no soportada');
    return;
  }
  if (status) {
    status.style.display = 'block';
    status.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Detectando ubicación...';
    status.style.color = '#64748b';
  }
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      const lat = pos.coords.latitude;
      const lng = pos.coords.longitude;
      const url = 'https://www.google.com/maps?q=' + lat + ',' + lng;
      if (input) input.value = url;
      if (btn) btn.classList.add('active');
      if (status) {
        status.style.color = '#10b981';
        status.innerHTML = '<i class="fas fa-check"></i> Ubicación actualizada';
        setTimeout(() => { status.style.display = 'none'; }, 2500);
      }
      markUnsaved();
    },
    (err) => {
      let msg = 'Error al obtener ubicación';
      if (err.code === 1) msg = 'Permiso denegado';
      if (err.code === 2) msg = 'Ubicación no disponible';
      if (err.code === 3) msg = 'Tiempo agotado';
      if (status) {
        status.style.color = '#ef4444';
        status.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + msg;
      } else {
        alert(msg);
      }
    },
    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
  );
};

// --- INVENTORY & OTHER DRAWERS ---
// La lógica de abrir y cerrar drawers se mantiene intacta
window.openFeriaDrawer = function() {
  if (typeof closeSectionsDrawer === 'function') closeSectionsDrawer();
  if (typeof closeProductDrawer === 'function') closeProductDrawer();
  if (typeof closeHomeDrawer === 'function') closeHomeDrawer();
  if (typeof closeInventoryDrawer === 'function') closeInventoryDrawer();
  const drawer = document.getElementById('feriaDrawer');
  if (drawer) {
    drawer.classList.add('show');
    if (window.updateBannerSlotsUI) {
      updateBannerSlotsUI();
    }
  }
};
window.closeFeriaDrawer = function() {
  const drawer = document.getElementById('feriaDrawer');
  if (drawer) drawer.classList.remove('show');
};
window.openInventoryDrawer = function() {
  if (typeof closeSectionsDrawer === 'function') closeSectionsDrawer();
  if (typeof closeProductDrawer === 'function') closeProductDrawer();
  if (typeof closeHomeDrawer === 'function') closeHomeDrawer();
  const drawer = document.getElementById('inventoryDrawer');
  if (drawer) {
    drawer.classList.add('show');
    renderInventoryList();
    updateInventoryFilters();
  }
};
window.closeInventoryDrawer = function() {
  const drawer = document.getElementById('inventoryDrawer');
  if (drawer) drawer.classList.remove('show');
};
window.openHomeDrawer = function() {
  if (typeof closeSectionsDrawer === 'function') closeSectionsDrawer();
  if (typeof closeProductDrawer === 'function') closeProductDrawer();
  if (typeof closeInventoryDrawer === 'function') closeInventoryDrawer();
  const drawer = document.getElementById('homeDrawer');
  if (drawer) {
    drawer.classList.add('show');
  }
};
window.closeHomeDrawer = function() {
  const drawer = document.getElementById('homeDrawer');
  if (drawer) drawer.classList.remove('show');
};
window.toggleProductoActivo = async function(id, estadoActual) {
  const nuevoEstado = parseInt(estadoActual) === 1 ? 0 : 1;
  try {
    const res = await fetch('/api/toggle_producto_activo.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id, activo: nuevoEstado })
    });
    const data = await res.json();
    if (data.success) {
      const prod = window.allProducts.find(p => p.id == id);
      if (prod) prod.activo = nuevoEstado;
      renderInventoryList();
      const msg = nuevoEstado === 0 ? 'Marcado Sin Stock (Oculto)' : 'Marcado En Stock (Visible)';
      showNotif(msg);
      reloadPreviewFrame(); // Recargar para ver el cambio
    } else {
      showNotif('Error al cambiar estado', 'error');
      renderInventoryList();
    }
  } catch (e) {
    console.error(e);
    showNotif('Error de conexión', 'error');
    renderInventoryList();
  }
};

window.renderInventoryList = function() {
  const list = document.getElementById('inventoryList');
  if (!list) return;
  list.innerHTML = '';
  let products = window.allProducts || [];
  const searchText = document.getElementById('invSearch').value.toLowerCase();
  if (searchText) {
    products = products.filter(p => p.titulo.toLowerCase().includes(searchText));
  }
  const sectionVal = document.getElementById('invSectionVal').value;
  if (sectionVal) {
    products = products.filter(p => p.categoria_tienda === sectionVal);
  }
  const sortVal = document.getElementById('invSortVal').value;
  if (sortVal === 'in_stock') {
    products = products.filter(p => parseInt(p.activo) === 1);
  } else if (sortVal === 'out_stock') {
    products = products.filter(p => parseInt(p.activo) === 0);
  }
  products.sort((a, b) => {
    if (sortVal === 'recent' || sortVal === 'in_stock' || sortVal === 'out_stock') return b.id - a.id;
    if (sortVal === 'oldest') return a.id - b.id;
    if (sortVal === 'price_asc') return parseFloat(a.precio) - parseFloat(b.precio);
    if (sortVal === 'price_desc') return parseFloat(b.precio) - parseFloat(a.precio);
    return 0;
  });
  document.getElementById('invCount').innerText = `${products.length} productos`;
  if (products.length === 0) {
    list.innerHTML = `<div style="text-align:center; padding:40px 20px; color:#94a3b8;"><i class="fas fa-box-open" style="font-size:32px; margin-bottom:10px; opacity:0.5;"></i><div style="font-size:13px;">No se encontraron productos</div></div>`;
    return;
  }
  const tableContainer = document.createElement('div');
  tableContainer.className = 'inventory-container';
  const table = document.createElement('table');
  table.className = 'inventory-table';
  const tbody = document.createElement('tbody');
  products.forEach((p, index) => {
    const imgUrl = p.imagen_principal ? `/uploads/${p.imagen_principal}` : '/assets/img/default-store.png';
    const row = document.createElement('tr');
    row.className = 'inventory-row';
    row.style.background = (index % 2 === 0) ? 'white' : '#f8fafc';
    row.innerHTML = `
      <td style="padding: 12px 4px 12px 0; vertical-align: middle; width: 45px;">
          <div style="display: flex; align-items: center; gap: 4px; width: 100%; margin-left: 0;">
              <input type="checkbox" id="inventory_${p.id}" style="width: 16px; height: 16px; margin: 0; padding: 0; position: relative; left: 0; transform: translateX(0);">
              <img src="${imgUrl}" alt="${p.titulo}" style="width: 24px; height: 24px; border-radius: 4px; object-fit: cover;">
          </div>
      </td>
      <td style="padding: 12px 4px; vertical-align: middle; width: auto; overflow: hidden;">
          <span style="font-weight: 500; color: #1f293b; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 13px;" title="${p.titulo}">${p.titulo}</span>
      </td>
      <td style="padding: 12px 4px 12px 4px; vertical-align: middle; width: 50px; text-align: right; padding-right: 4px;">
          <div class="dropdown-inventory" style="position: relative; display: inline-block; width: 100%; text-align: right;">
              <button id="ellipsis-${p.id}" class="dropdown-trigger" data-dropdown-type="inventory" data-dropdown-id="${p.id}" style="background: none; border: none; padding: 8px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; border-radius: 4px; transition: background 0.2s; margin-left: auto;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='none'">
                  <i class="fas fa-ellipsis-v" style="color: #64748b; font-size: 14px;"></i>
              </button>
          </div>
      </td>`;
    tbody.appendChild(row);
  });
  table.appendChild(tbody);
  tableContainer.appendChild(table);
  list.appendChild(tableContainer);
};

window.filterInventory = function() {
  renderInventoryList();
};

window.updateInventoryFilters = function() {
  const menu = document.getElementById('invSectionMenu');
  const label = document.getElementById('invSectionLabel');
  if (!menu) return;
  let html = '<div class="ui-option selected" onclick="selectUIOption(\'invSectionFilter\', \'\', \'Todas las secciones\', (v)=>{document.getElementById(\'invSectionVal\').value=v; filterInventory();})">Todas Las Secciones</div>';
  if (window.menuItems && Array.isArray(window.menuItems)) {
    window.menuItems.forEach(item => {
      if (item.label.toLowerCase() === 'inicio') return;
      const prettyLabel = toTitleCase(item.label);
      html += `<div class="ui-option" onclick="selectUIOption('invSectionFilter', '${item.label}', '${prettyLabel}', (v)=>{document.getElementById('invSectionVal').value=v; filterInventory();})">${prettyLabel}</div>`;
    });
  }
  menu.innerHTML = html;
  const currentVal = document.getElementById('invSectionVal').value;
  if (!currentVal && label) label.innerText = 'Todas Las Secciones';
};

window.verProductoEnTienda = function(slug, id) {
  const url = `/tienda/${slug}/producto/${id}`;
  window.open(url, '_blank');
};

window.eliminarProducto = async function(id) {
  if (!confirm('¿Eliminar producto?')) return;
  try {
    const res = await fetch('/api/eliminar_producto.php', { method: 'POST', body: JSON.stringify({ id }) });
    if (res.ok) {
      showNotif('Producto eliminado');
      window.allProducts = window.allProducts.filter(p => p.id != id);
      renderSidebarProducts();
      renderInventoryList();
      reloadPreviewFrame();
    }
  } catch (e) { showNotif('Error', 'error'); }
};

// --- SECTIONS MANAGER ---
window.initSectionsManager = function() {
  try { renderMasterFilter(); } catch (e) { console.error('Error renderMasterFilter', e); }
  try { renderSectionsList(); } catch (e) { console.error('Error renderSectionsList', e); }
  const btn = document.getElementById('btnOpenSections');
  if (btn) {
    btn.onclick = function(e) {
      e.preventDefault();
      e.stopPropagation();
      openSectionsDrawer();
    };
  }
};
window.openSectionsDrawer = function() {
  if (typeof closeProductDrawer === 'function') closeProductDrawer();
  if (typeof closeInventoryDrawer === 'function') closeInventoryDrawer();
  if (typeof closeHomeDrawer === 'function') closeHomeDrawer();
  const drawer = document.getElementById('sectionsDrawer');
  if (drawer) {
    drawer.classList.add('show');
    renderSectionsList();
  }
};
window.closeSectionsDrawer = function() {
  const drawer = document.getElementById('sectionsDrawer');
  if (drawer) drawer.classList.remove('show');
};
function toTitleCase(str) {
  if (!str) return '';
  return str.toLowerCase().split(' ').map(function(word) {
    return (word.charAt(0).toUpperCase() + word.slice(1));
  }).join(' ');
}
window.renderSectionsList = function() {
  const container = document.getElementById('drawerSectionsList');
  if (!container) return;
  container.innerHTML = '';
  const homeCount = (window.allProducts && Array.isArray(window.allProducts)) ? window.allProducts.length : 0;
  const homeDiv = document.createElement('div');
  homeDiv.className = 'section-sortable-item fixed-item';
  homeDiv.innerHTML = `<div class="drag-handle disabled" title="Fijo"><i class="fas fa-lock" style="font-size:12px; opacity:0.5;"></i></div><div style="flex: 1; display: flex; flex-direction: column; min-width: 0;"><div style="font-weight: 600; color: #334155; font-size: 14px;">Inicio</div><span style="font-size: 11px; color: #64748b; margin-top:2px;">${homeCount} productos (Total)</span></div><div style="display: flex; gap: 6px;"><span style="background: #e2e8f0; color: #64748b; font-size: 10px; font-weight: 600; padding: 4px 8px; border-radius: 20px;">Fijo</span></div>`;
  container.appendChild(homeDiv);
  if (window.menuItems && Array.isArray(window.menuItems)) {
    window.menuItems.forEach((item, index) => {
      if (item.label.toLowerCase() === 'inicio' || item.label.toLowerCase() === 'todos') return;
      const div = document.createElement('div');
      div.className = 'section-sortable-item';
      if (item.hidden) div.classList.add('hidden-section');
      div.draggable = true;
      div.dataset.index = index;
      div.addEventListener('dragstart', handleDragStart);
      div.addEventListener('dragover', handleDragOver);
      div.addEventListener('drop', handleDrop);
      div.addEventListener('dragenter', handleDragEnter);
      div.addEventListener('dragleave', handleDragLeave);
      div.addEventListener('dragend', handleDragEnd);
      const count = (window.allProducts && Array.isArray(window.allProducts)) ? window.allProducts.filter(p => p.categoria_tienda === item.label).length : 0;
      const displayName = toTitleCase(item.label);
      div.innerHTML = `<div class="drag-handle" title="Arrastrar para ordenar"><i class="fas fa-grip-vertical"></i></div><div style="flex: 1; display: flex; flex-direction: column; min-width: 0;"><div class="section-name-display" id="displayName-${index}" style="font-weight: 600; color: #334155; font-size: 14px; cursor:pointer;" onclick="toggleInlineEdit(${index})">${displayName}</div><input type="text" class="section-name-edit" id="editInput-${index}" value="${item.label}" style="display:none; width:100%; padding:4px; font-size:14px; border:1px solid #22226B; border-radius:4px;" onblur="saveInlineEdit(${index})" onkeydown="handleEditKey(event, ${index})"><span style="font-size: 11px; color: #64748b; margin-top:2px;">${count} productos</span></div><div style="display: flex; gap: 6px;"><button onclick="toggleVisibility(${index})" title="${item.hidden ? 'Mostrar' : 'Ocultar'}" class="btn-icon-mini-action eye ${item.hidden ? 'off' : ''}"><i class="fas ${item.hidden ? 'fa-eye-slash' : 'fa-eye'}"></i></button><button onclick="toggleInlineEdit(${index})" title="Renombrar" class="btn-icon-mini-action edit"><i class="fas fa-pencil-alt"></i></button><button onclick="deleteSection(${index})" title="Eliminar" class="btn-icon-mini-action delete"><i class="fas fa-trash-alt"></i></button></div>`;
      container.appendChild(div);
    });
  }
};
window.toggleVisibility = function(index) {
  if (!window.menuItems[index]) return;
  window.menuItems[index].hidden = !window.menuItems[index].hidden;
  renderSectionsList();
  renderMasterFilter();
  markUnsaved();
};
let dragSrcEl = null;
function handleDragStart(e) {
  dragSrcEl = this;
  e.dataTransfer.effectAllowed = 'move';
  e.dataTransfer.setData('text/html', this.innerHTML);
  this.classList.add('dragging');
}
function handleDragOver(e) {
  if (e.preventDefault) e.preventDefault();
  e.dataTransfer.dropEffect = 'move';
  const container = document.getElementById('drawerSectionsList');
  const afterElement = getDragAfterElement(container, e.clientY);
  document.querySelectorAll('.section-sortable-item').forEach(i => i.classList.remove('drag-over-top', 'drag-over-bottom'));
  if (afterElement == null) {
    container.appendChild(dragSrcEl);
  } else {
    container.insertBefore(dragSrcEl, afterElement);
  }
  return false;
}
function getDragAfterElement(container, y) {
  const draggableElements = [...container.querySelectorAll('.section-sortable-item:not(.dragging):not(.fixed-item)')];
  return draggableElements.reduce((closest, child) => {
    const box = child.getBoundingClientRect();
    const offset = y - box.top - box.height / 2;
    if (offset < 0 && offset > closest.offset) {
      return { offset, element: child };
    } else {
      return closest;
    }
  }, { offset: Number.NEGATIVE_INFINITY }).element;
}
function handleDragEnter() {}
function handleDragLeave() {}
function handleDragEnd() {
  this.classList.remove('dragging');
  document.querySelectorAll('.section-sortable-item').forEach(item => {
    item.classList.remove('drag-over-top', 'drag-over-bottom');
  });
  updateMenuItemsOrder();
}
function handleDrop(e) {
  if (e.stopPropagation) e.stopPropagation();
  return false;
}
function updateMenuItemsOrder() {
  const container = document.getElementById('drawerSectionsList');
  const newMenuItems = [];
  const fixedItems = window.menuItems.filter(i => i.label.toLowerCase() === 'inicio' || i.label.toLowerCase() === 'todos');
  newMenuItems.push(...fixedItems);
  container.querySelectorAll('.section-sortable-item:not(.fixed-item)').forEach(div => {
    const originalIndex = div.dataset.index;
    if (originalIndex !== undefined && window.menuItems[originalIndex]) {
      newMenuItems.push(window.menuItems[originalIndex]);
    }
  });
  window.menuItems = newMenuItems;
  renderMasterFilter();
  markUnsaved();
  renderSectionsList();
}
window.toggleInlineEdit = function(index) {
  const display = document.getElementById(`displayName-${index}`);
  const input = document.getElementById(`editInput-${index}`);
  if (display.style.display === 'none') {
    display.style.display = 'block';
    input.style.display = 'none';
  } else {
    display.style.display = 'none';
    input.style.display = 'block';
    input.focus();
  }
};
window.saveInlineEdit = function(index) {
  const input = document.getElementById(`editInput-${index}`);
  const newVal = input.value.trim();
  const oldVal = window.menuItems[index].label;
  if (newVal && newVal !== oldVal) {
    window.menuItems[index].label = newVal;
    window.allProducts.forEach(p => {
      if (p.categoria_tienda === oldVal) p.categoria_tienda = newVal;
    });
    renderSectionsList();
    renderMasterFilter();
    markUnsaved();
    showNotif('Sección renombrada');
  } else {
    renderSectionsList();
  }
};
window.handleEditKey = function(e, index) {
  if (e.key === 'Enter') {
    saveInlineEdit(index);
  } else if (e.key === 'Escape') {
    renderSectionsList();
  }
};
window.quickAddProduct = function(sectionName) {
  closeSectionsDrawer();
  applySectionFilter(sectionName);
  setTimeout(() => {
    openProductDrawer(null);
    const select = document.getElementById('prodCategoriaTienda');
    if (select) select.value = sectionName;
  }, 300);
};
window.addNewSectionFromDrawer = function() {
  const input = document.getElementById('drawerNewSectionInput');
  if (!input) return;
  const name = input.value.trim();
  if (!name) return showNotif('Escribe un nombre', 'error');
  const exists = window.menuItems.some(i => i.label.toLowerCase() === name.toLowerCase());
  if (exists) return showNotif('Esa sección ya existe', 'error');
  window.menuItems.push({ label: name, url: '#' });
  input.value = '';
  renderSectionsList();
  renderMasterFilter();
  markUnsaved();
  showNotif('Sección creada');
};
window.addNewSection = window.addNewSectionFromDrawer;
window.deleteSection = function(index) {
  if (!confirm('¿Eliminar esta sección? Los productos volverán a Inicio.')) return;
  const sectionName = window.menuItems[index].label;
  if (currentSectionFilter === sectionName) {
    applySectionFilter('');
    document.getElementById('masterSectionFilter').value = '';
  }
  window.menuItems.splice(index, 1);
  renderSectionsList();
  renderMasterFilter();
  markUnsaved();
};
window.editSection = function(index) {
  const current = window.menuItems[index].label;
  const newName = prompt('Nuevo nombre:', current);
  if (newName && newName.trim() !== '' && newName !== current) {
    const oldName = current;
    const finalName = newName.trim();
    window.menuItems[index].label = finalName;
    if (currentSectionFilter === oldName) {
      currentSectionFilter = finalName;
      window.allProducts.forEach(p => {
        if (p.categoria_tienda === oldName) p.categoria_tienda = finalName;
      });
      renderSidebarProducts();
    }
    renderSectionsList();
    renderMasterFilter();
    const masterFilter = document.getElementById('masterSectionFilter');
    if (masterFilter.value === oldName) masterFilter.value = finalName;
    markUnsaved();
  }
};
// --- FILTER & CONTEXT LOGIC ---
let currentSectionFilter = '';
window.renderMasterFilter = function() {
  const select = document.getElementById('masterSectionFilter');
  const filterMenu = document.getElementById('sectionFilterMenu');
  const filterLabel = document.getElementById('sectionFilterLabel');
  const formMenu = document.getElementById('prodCatTiendaMenu');
  const currentVal = select ? select.value : '';
  let filterHtml = '<div class="ui-option selected" onclick="selectUIOption(\'sectionFilterDropdown\', \'\', \'Inicio\', (val)=>{ document.getElementById(\'masterSectionFilter\').value=val; applySectionFilter(val); })">Inicio</div>';
  let formHtml = '<div class="ui-option selected" onclick="selectUIOption(\'prodCatTiendaDropdown\', \'\', \'Inicio (General)\', (val)=>{ document.getElementById(\'prodCategoriaTienda\').value=val; })">Inicio (General)</div>';
  if (window.menuItems && Array.isArray(window.menuItems)) {
    window.menuItems.forEach(item => {
      if (item.label.toLowerCase() === 'inicio') return;
      const labelCap = toTitleCase(item.label);
      filterHtml += `<div class="ui-option" onclick="selectUIOption('sectionFilterDropdown', '${item.label}', '${labelCap}', (val)=>{ document.getElementById('masterSectionFilter').value=val; applySectionFilter(val); })">${labelCap}</div>`;
      formHtml += `<div class="ui-option" onclick="selectUIOption('prodCatTiendaDropdown', '${item.label}', '${labelCap}', (val)=>{ document.getElementById('prodCategoriaTienda').value=val; })">${labelCap}</div>`;
    });
  }
  if (filterMenu) filterMenu.innerHTML = filterHtml;
  if (formMenu) formMenu.innerHTML = formHtml;
  if (currentVal && filterLabel) {
    const item = window.menuItems.find(i => i.label === currentVal);
    if (item) filterLabel.innerText = toTitleCase(item.label);
  }
};
window.applySectionFilter = function(sectionName) {
  currentSectionFilter = sectionName;
  renderSidebarProducts();
  const btn = document.getElementById('btnNewProductContext');
  if (btn) {
    if (sectionName) {
      btn.title = `Nuevo en ${toTitleCase(sectionName)}`;
      btn.classList.add('primary');
    } else {
      btn.title = 'Nuevo Producto';
      btn.classList.remove('primary');
    }
  }
};
window.renderSidebarProducts = function() {
  const list = document.getElementById('sidebarProductList');
  if (!list) return;
  list.innerHTML = '';
  let productsToShow = window.allProducts || [];
  if (currentSectionFilter) {
    productsToShow = productsToShow.filter(p => p.categoria_tienda === currentSectionFilter);
  }
  if (productsToShow.length === 0) {
    const msg = currentSectionFilter ? `No hay productos en "${currentSectionFilter}"` : 'No hay productos';
    list.innerHTML = `<div style="padding:20px; text-align:center; color:#94a3b8; font-size:13px;">${msg}</div>`;
    return;
  }
  productsToShow.forEach(p => {
    const item = document.createElement('div');
    item.className = 'sidebar-product-item';
    const imgUrl = p.imagen_principal ? `/uploads/${p.imagen_principal}` : '/assets/img/default-store.png';
    let badge = '';
    if (!currentSectionFilter && p.categoria_tienda) {
      badge = `<span style="font-size:10px; background:#e2e8f0; color:#475569; padding:2px 4px; border-radius:4px; margin-left:auto;">${p.categoria_tienda}</span>`;
    }
    item.innerHTML = `<img src="${imgUrl}" class="sidebar-prod-img"><div class="sidebar-prod-info"><div class="sidebar-prod-title" title="${p.titulo}">${p.titulo}</div><div class="sidebar-prod-price">Bs ${p.precio}</div></div>${badge}<div class="sidebar-prod-actions"><button class="btn-icon-mini" onclick="openProductDrawer(${p.id})"><i class="fas fa-pencil-alt"></i></button><button class="btn-icon-mini delete" onclick="eliminarProducto(${p.id})"><i class="fas fa-trash-alt"></i></button></div>`;
    list.appendChild(item);
  });
};
const originalInit = window.initSidebarProducts;
window.initSidebarProducts = function() {
  originalInit();
  initSectionsManager();
};

// --- LÓGICA DE UBICACIÓN FERIA VIRTUAL (sin cambios) ---
document.addEventListener('DOMContentLoaded', function() {
  const ciudadSelect = document.getElementById('feriaCiudad');
  if (ciudadSelect && ciudadSelect.value) {
    cargarSectores(true);
  }
});
window.cargarSectores = function(precargar = false) {
    const ciudad = document.getElementById('feriaCiudad').value;
    const sectorInput = document.getElementById('feriaSector');
    const sectorTrigger = document.getElementById('sectorTrigger');
    const sectorLabel = document.getElementById('sectorLabel');
    const sectorMenu = document.getElementById('sectorMenu');
    const bloqueInput = document.getElementById('feriaBloque');
    const bloqueTrigger = document.getElementById('bloqueTrigger');
    const bloqueLabel = document.getElementById('bloqueLabel');
    const bloqueMenu = document.getElementById('bloqueMenu');
    const gridContainer = document.getElementById('feriaGridContainer');

    sectorInput.value = '';
    sectorLabel.innerText = 'Cargando...';
    sectorTrigger.classList.add('disabled');
    sectorMenu.innerHTML = '';
    bloqueInput.value = '';
    bloqueLabel.innerText = '-- Primero selecciona sector --';
    bloqueTrigger.classList.add('disabled');
    bloqueMenu.innerHTML = '';
    gridContainer.style.display = 'none';

    if (!ciudad) {
        sectorLabel.innerText = '-- Primero selecciona ciudad --';
        return;
    }

    fetch('/api/feria_editor.php?action=get_sectores')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                let html = '';
                data.data.forEach(s => {
                    html += `<div class="ui-option" onclick="selectUIOption('sectorDropdown', '${s.id}', '${s.titulo}', (val)=>{ document.getElementById('feriaSector').value=val; cargarBloques(); })">${s.titulo}</div>`;
                });
                sectorMenu.innerHTML = html;
                sectorLabel.innerText = '-- Seleccionar Sector --';
                sectorTrigger.classList.remove('disabled');

                if (precargar) {
                    fetch('/api/feria_editor.php?action=get_my_location')
                        .then(r => r.json())
                        .then(locData => {
                            if (locData.success && locData.data && locData.data.ciudad === ciudad) {
                                sectorInput.value = locData.data.sector_id;
                                const selectedOption = data.data.find(s => s.id == locData.data.sector_id);
                                if (selectedOption) sectorLabel.innerText = selectedOption.titulo;
                                cargarBloques(true, locData.data.bloque_id);
                            }
                        });
                }
            }
        })
        .catch(e => console.error(e));
};
window.cargarBloques = function(precargar = false, bloqueIdTarget = null) {
    const sectorId = document.getElementById('feriaSector').value;
    const bloqueInput = document.getElementById('feriaBloque');
    const bloqueTrigger = document.getElementById('bloqueTrigger');
    const bloqueLabel = document.getElementById('bloqueLabel');
    const bloqueMenu = document.getElementById('bloqueMenu');
    const gridContainer = document.getElementById('feriaGridContainer');

    bloqueInput.value = '';
    bloqueLabel.innerText = 'Cargando...';
    bloqueTrigger.classList.add('disabled');
    bloqueMenu.innerHTML = '';
    gridContainer.style.display = 'none';

    if (!sectorId) return;

    fetch(`/api/feria_editor.php?action=get_bloques&sector_id=${sectorId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                let html = '';
                data.data.forEach(b => {
                    html += `<div class="ui-option" onclick="selectUIOption('bloqueDropdown', '${b.id}', '${b.nombre}', (val)=>{ document.getElementById('feriaBloque').value=val; cargarGridPuestos(); })">${b.nombre}</div>`;
                });
                bloqueMenu.innerHTML = html;
                bloqueLabel.innerText = '-- Seleccionar Bloque --';
                bloqueTrigger.classList.remove('disabled');

                if (precargar && bloqueIdTarget) {
                    bloqueInput.value = bloqueIdTarget;
                    const selectedOption = data.data.find(b => b.id == bloqueIdTarget);
                    if (selectedOption) bloqueLabel.innerText = selectedOption.nombre;
                    cargarGridPuestos();
                }
            }
        })
        .catch(e => console.error(e));
};
window.cargarGridPuestos = function() {
    const bloqueId = document.getElementById('feriaBloque').value;
    const ciudad = document.getElementById('feriaCiudad').value;
    const gridContainer = document.getElementById('feriaGridContainer');
    const gridSlots = document.getElementById('feriaSlotsGrid');
    const status = document.getElementById('feriaStatus');

    if (!bloqueId || !ciudad) return;

    gridContainer.style.display = 'block';
    gridSlots.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Cargando mapa...</div>';
    status.innerHTML = '';

    fetch(`/api/feria_editor.php?action=get_puestos&bloque_id=${bloqueId}&ciudad=${ciudad}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                gridSlots.innerHTML = '';
                data.data.forEach(slot => {
                    const div = document.createElement('div');
                    div.className = 'feria-slot';
                    div.dataset.slotNumber = slot.numero;
                    const numSpan = document.createElement('span');
                    numSpan.className = 'slot-number';
                    numSpan.innerText = slot.numero;
                    div.appendChild(numSpan);

                    if (slot.estado === 'propio') {
                        div.classList.add('my-store');
                        div.title = 'Tu ubicación actual - Arrástrame para mover';
                        div.draggable = true;
                        div.addEventListener('dragstart', (e) => {
                            e.dataTransfer.setData('text/plain', slot.numero);
                            e.dataTransfer.effectAllowed = 'move';
                            div.style.opacity = '0.5';
                        });
                        div.addEventListener('dragend', () => {
                            div.style.opacity = '1';
                            document.querySelectorAll('.feria-slot').forEach(el => el.classList.remove('drag-over'));
                        });
                        if (slot.tienda.logo) {
                            const img = document.createElement('img');
                            img.src = slot.tienda.logo;
                            img.className = 'slot-store-img';
                            div.appendChild(img);
                        } else {
                            const icon = document.createElement('i');
                            icon.className = 'fas fa-store';
                            icon.style.cssText = 'font-size: 24px; color: #e5e5e5;';
                            div.appendChild(icon);
                        }
                        const name = document.createElement('div');
                        name.className = 'slot-overlay-name';
                        name.innerText = 'TU TIENDA';
                        div.appendChild(name);
                    } else if (slot.estado === 'ocupado') {
                        div.classList.add('occupied');
                        div.title = `Ocupado por: ${slot.tienda.nombre}`;
                        if (slot.tienda.logo) {
                            const img = document.createElement('img');
                            img.src = slot.tienda.logo;
                            img.className = 'slot-store-img';
                            div.appendChild(img);
                        } else {
                            const icon = document.createElement('i');
                            icon.className = 'fas fa-store';
                            icon.style.cssText = 'font-size: 24px; color: #e5e5e5;';
                            div.appendChild(icon);
                        }
                    } else {
                        div.title = `Puesto ${slot.numero} - Click o suelta aquí`;
                        div.onclick = () => ocuparPuesto(slot.numero);
                        div.addEventListener('dragover', (e) => {
                            e.preventDefault();
                            e.dataTransfer.dropEffect = 'move';
                            div.classList.add('drag-over');
                        });
                        div.addEventListener('dragleave', () => div.classList.remove('drag-over'));
                        div.addEventListener('drop', (e) => {
                            e.preventDefault();
                            div.classList.remove('drag-over');
                            ocuparPuesto(slot.numero);
                        });
                        const icon = document.createElement('i');
                        icon.className = 'fas fa-plus slot-icon-add';
                        div.appendChild(icon);
                    }
                    gridSlots.appendChild(div);
                });
            }
        })
        .catch(e => {
            console.error(e);
            gridSlots.innerHTML = '<div style="color:red; padding:10px;">Error al cargar mapa</div>';
        });
};
window.ocuparPuesto = function(slotNumero) {
    const ciudad = document.getElementById('feriaCiudad').value;
    const sectorId = document.getElementById('feriaSector').value;
    const bloqueId = document.getElementById('feriaBloque').value;
    const status = document.getElementById('feriaStatus');
    const bloqueNombre = document.getElementById('bloqueLabel').innerText.replace(/\(Cap: \d+\)/i, '').trim();

    if (!confirm(`¿Mover tu tienda al Puesto #${slotNumero} del ${bloqueNombre}?`)) return;

    status.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando nueva ubicación...';

    fetch('/api/feria_editor.php?action=assign_puesto', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            ciudad,
            sector_id: sectorId,
            bloque_id: bloqueId,
            slot_numero: slotNumero
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            status.innerHTML = '<span style="color:green; font-weight:bold;">¡Ubicación actualizada!</span>';
            cargarGridPuestos();
        } else {
            status.innerHTML = `<span style="color:red; font-weight:bold;">Error: ${data.message}</span>`;
        }
    })
    .catch(e => {
        console.error(e);
        status.innerHTML = '<span style="color:red;">Error de conexión</span>';
    });
};

window.openProductDrawer = openProductDrawer;
