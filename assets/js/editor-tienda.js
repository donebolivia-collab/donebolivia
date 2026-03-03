/**
 * Editor Tienda Split JS
 * Logic for Sidebar, Accordions, and Live Preview Communication
 */

const $ = (selector, parent = document) => TiendaGuard.safeQuery(selector, parent);
const safeGetById = (id) => TiendaGuard.safeQuery(`#${id}`);

document.addEventListener('DOMContentLoaded', () => {
    initAccordions();
    initColorPicker();
    initSidebarProducts();
    initStoreSync(); // Start listening for changes
    updateSaveIndicator(true); // Init state (Mostrar Verde al inicio)
    initRefactoredEventListeners(); // <--- NUEVO
    // attachLogoHandlers(); // ELIMINADO: Legacy
    initBrandIdentityControls(); // <--- [NUEVO] IDENTIDAD DE MARCA

    // Initial Load
    renderSidebarProducts();
    updateBannerSlotsUI(); // <--- AÑADIDO PARA ESTADO INICIAL CORRECTO



    // Check URL for success params
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('status') === 'success') {
        showNotif('Guardado correctamente');
    }
});

// --- NUEVO: MANEJADOR DE EVENTOS DELEGADO PARA REFACTORIZACIÓN ---
function initRefactoredEventListeners() {
    document.body.addEventListener('click', function(e) {
        const target = e.target.closest('[data-action]');
        if (!target) return;

        const action = target.dataset.action;

        switch (action) {
            // Legacy logo handlers removed.

            case 'select-ui-option':
                {
                    const parentId = target.dataset.parent;
                    const value = target.dataset.value;
                    const label = target.dataset.label;
                    const callbackName = target.dataset.callback;

                    // Reconstruimos la función de callback desde su nombre
                    const callback = window[callbackName];

                    if (typeof callback === 'function') {
                        // Llamamos a la versión original de selectUIOption pero con los parámetros recuperados
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

                    postToFrame('updateTheme', {
                        color: color,
                        fondo: window.tiendaState.estiloFondo
                    });
                    markUnsaved();
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
    
    // Forzar redibujado de iframe si es necesario
    setTimeout(() => {
        // window.setCanvasSize('desktop'); // Opcional: resetear tamaño
    }, 300);
};

// --- ACCORDION SYSTEM ---
window.initAccordions = function() {
    // Ensure at least one is open if none are
    // MODIFICADO: No forzar apertura automática para respetar estado contraído inicial
    
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
        syncAllSettings();
        setTimeout(syncAllSettings, 150);
    } else {
        item.classList.remove('open');
    }
};

// --- LIVE PREVIEW SYNC ---
const storeFrame = safeGetById('storeFrame');
let isFrameReady = false; // Cambiamos el nombre de la variable para mayor claridad

// Ya no necesitamos el isFrameLoaded en onload, lo eliminamos.

// Escuchamos el "apretón de manos" desde el iframe.
window.addEventListener('message', (event) => {
    // Verificación de seguridad básica
    if (event.source !== storeFrame.contentWindow) {
        return;
    }

    const data = event.data;

    if (data.type === 'iframeReady') {
        console.log('Editor: Iframe está listo. Sincronizando...');
        isFrameReady = true;
        syncAllSettings(); // ¡Ahora es seguro sincronizar!
    }
    
    if (data.type === 'syncFromFrame') {
        const { field, value } = data;
        const elementIdMap = {
            'descripcion': 'storeDescription',
            'nombre': 'storeName'
        };
        const el = safeGetById(elementIdMap[field]);
        if (el && el.value !== value) {
            el.value = value;
            markUnsaved();
        }
    }
});

function postToFrame(type, payload) {
    if (!isFrameReady || !storeFrame?.contentWindow) return;
    storeFrame.contentWindow.postMessage({ type, payload }, window.location.origin);
}

function syncAllSettings() {
    if (!isFrameReady) return;

    postToFrame('updateTheme', {
        color: window.tiendaState.color,
        fondo: window.tiendaState.estiloFondo,
        bordes: window.tiendaState.estiloBordes,
        fuente: window.tiendaState.tipografia,
        tamano: window.tiendaState.tamanoTexto,
        tarjetas: window.tiendaState.estiloTarjetas,
        grid: window.tiendaState.gridDensity,
        banner: window.tiendaState.banner,
        seccionesDestacadas: window.tiendaState.seccionesDestacadas
    });

    const nameInput = safeGetById('storeName');
    if (nameInput) {
        postToFrame('updateText', {
            selector: '.store-name',
            text: nameInput.value,
            visible: window.tiendaState.mostrar_nombre
        });
    }

    postToFrame('updateLogoState', {
        visible: !!window.tiendaState.mostrar_logo,
        url: window.tiendaState.logo_principal ? `/uploads/logos/${window.tiendaState.logo_principal}` : null
    });

    syncContact();
}

// --- STATE MANAGEMENT & AUTO-SAVE MOCK ---
let saveTimeout;
let hasUnsavedChanges = false;

function markUnsaved() {
    hasUnsavedChanges = true;
    const indicator = safeGetById('autoSaveStatus');
    const bar = safeGetById('statusBar');

    if (indicator) {
        indicator.innerHTML = '<i class="fas fa-sync fa-spin"></i> Guardando...';
        indicator.classList.add('visible');
    }

    if (bar) {
        bar.style.backgroundColor = '#ff9800';
        bar.style.color = '#ffffff';
    }
    
    clearTimeout(saveTimeout);
    saveTimeout = setTimeout(saveChanges, 1500); // Auto-save after 1.5s
}

function updateSaveIndicator(saved) {
    const indicator = safeGetById('autoSaveStatus');
    const bar = safeGetById('statusBar');
    if (!indicator) return;

    if (saved) {
        indicator.innerHTML = '<i class="fas fa-check"></i> Guardado';
        indicator.style.color = '#ffffff';
        if (bar) {
            bar.style.backgroundColor = '#00a650';
            bar.style.color = '#ffffff';
        }
        indicator.classList.add('visible');
        setTimeout(() => indicator.classList.remove('visible'), 2000);
    } else {
        indicator.classList.remove('visible');
    }
}

function syncContact() {
    const data = {
        whatsapp: safeGetById('inputWhatsapp')?.value || '',
        email: safeGetById('inputEmail')?.value || '',
        direccion: safeGetById('inputDireccion')?.value || '',
        maps: safeGetById('inputMaps')?.value || '',
        facebook: safeGetById('inputFacebook')?.value || '',
        instagram: safeGetById('inputInstagram')?.value || '',
        tiktok: safeGetById('inputTiktok')?.value || '',
        telegram: safeGetById('inputTelegram')?.value || '',
        youtube: safeGetById('inputYoutube')?.value || ''
    };
    postToFrame('updateContact', data);
}

async function saveChanges() {
    if (!hasUnsavedChanges) return;

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
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        const resData = await res.json();
        if (resData.success) {
            hasUnsavedChanges = false;
            updateSaveIndicator(true);
        } else {
            console.error('Save failed', resData);
            updateSaveIndicator(false);
            showNotif('Error al guardar', 'error');
        }
    } catch (e) {
        console.error('Save error', e);
        updateSaveIndicator(false);
    }
}

function initStoreSync() {
    const inputs = ['storeName', 'storeDescription', 'inputWhatsapp', 'inputEmail', 'inputDireccion'];
    inputs.forEach(id => {
        const el = safeGetById(id);
        if (el) {
            el.addEventListener('input', (e) => {
                markUnsaved();
                if (id === 'storeName') postToFrame('updateText', { selector: '.store-name', text: e.target.value });
                if (id === 'storeDescription') postToFrame('updateText', { selector: '.about-description-text', text: e.target.value });
                if (['inputWhatsapp', 'inputEmail', 'inputDireccion'].includes(id)) {
                    syncContact();
                }
            });
        }
    });
}

// --- SYNC HELPERS ---
window.updateMenuInFrame = function() {
    // Send current menu structure to iframe for real-time update
    if (window.menuItems) {
        postToFrame('updateMenu', { items: window.menuItems });
    }
};

// --- COLOR PICKER ---

// Nueva paleta de 36 colores, organizada y con nombres para tooltips.
const colors = [
    // Rojos y Rosados
    { color: '#FF0000', name: 'Rojo' },
    { color: '#E1306C', name: 'Rosa Instagram' },
    { color: '#FF017B', name: 'Fucsia' },
    { color: '#BA2C5D', name: 'Rosa Oscuro' },

    // Naranjas y Marrones
    { color: '#ff6a00', name: 'Naranja Done' },
    { color: '#F16253', name: 'Naranja Salmón' },
    { color: '#D85427', name: 'Naranja Ladrillo' },
    { color: '#E07A5F', name: 'Terracota' },
    { color: '#C19A6B', name: 'Camel' },
    { color: '#794C1E', name: 'Marrón Café' },

    // Amarillos y Dorados
    { color: '#FFBF00', name: 'Mostaza' },
    { color: '#D4AF37', name: 'Dorado' },
    { color: '#E1E66B', name: 'Lima Limón' },

    // Verdes
    { color: '#25D366', name: 'Verde WhatsApp' },
    { color: '#00A86B', name: 'Verde Jade' },
    { color: '#20c997', name: 'Menta' },
    { color: '#008080', name: 'Teal' },
    { color: '#8A9A5B', name: 'Verde Salvia' },
    { color: '#3A5829', name: 'Verde Oscuro' },
    { color: '#8C8733', name: 'Verde Oliva' },
    { color: '#666229', name: 'Verde Musgo' },

    // Azules y Morados
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

    // Grises, Negro y Blanco
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
            postToFrame('updateTheme', {
                color: value,
                fondo: window.tiendaState.estiloFondo
            });
            markUnsaved();
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



// --- [NUEVO] REFACTORIZACIÓN DE IDENTIDAD VISUAL ---
function updateVisualOption(key, value) {
    window.tiendaState[key] = value;

    const themeKeyMap = {
        estiloFondo: 'fondo',
        estiloBordes: 'bordes',
        tipografia: 'fuente',
        tamanoTexto: 'tamano',
        estiloTarjetas: 'tarjetas',
        estiloFotos: 'fotos',
        gridDensity: 'grid'
    };
    const themeKey = themeKeyMap[key];

    if (key === 'navbarStyle') {
        postToFrame('setNavbarStyle', { style: value });
    } else if (themeKey) {
        postToFrame('updateTheme', { [themeKey]: value });
    } else {
        console.warn(`Visual option key "${key}" has no theme mapping.`);
    }
    
    markUnsaved();
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

// --- BANNER LOGIC (SLIDER V2) ---
window.updateBannerState = function() {
    const bannerActive = safeGetById('bannerActive');
    if (!bannerActive) return;
    const isActive = bannerActive.checked;
    window.tiendaState.banner.activo = isActive ? 1 : 0;
    
    const bannerContent = safeGetById('bannerBlockContent');
    if (bannerContent) {
        bannerContent.style.display = isActive ? 'block' : 'none';
    }
    
    if(isActive) {
        updateBannerSlotsUI();
    }

    postToFrame('updateTheme', { 
        banner: { 
            activo: isActive 
        } 
    });
    markUnsaved();
};

window.updateBannerSlotsUI = function() {
    if (!window.tiendaState?.banner) return;

    const imagenes = window.tiendaState.banner.imagenes || [];
    
    [1, 2, 3].forEach((i) => {
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


// --- FUNCIONES VIEJAS ELIMINADAS ---

window.toggleFeaturedSections = function(isActive) {
    const content = safeGetById('featuredSectionsContent');
    if (!content) return;

    window.tiendaState.seccionesDestacadas.activo = isActive ? 1 : 0;
    content.style.display = isActive ? 'block' : 'none';

    postToFrame('updateTheme', { 
        seccionesDestacadas: { 
            activo: window.tiendaState.seccionesDestacadas.activo
        } 
    });
    markUnsaved();
};

window.setFeaturedSectionsStyle = function(style) {
    window.tiendaState.seccionesDestacadas.estilo = style;
    postToFrame('updateTheme', { 
        seccionesDestacadas: { 
            estilo: style
        } 
    });
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

// --- PRODUCTS ---
function initSidebarProducts() {
    // Using window.allProducts from PHP
}



// --- PRODUCT DRAWER & LOGIC ---
function resetProductDrawer() {
    // Resetear formulario y estado interno
    const form = $('#formProducto');
    if (form) form.reset();

    $('#prodId').value = '';
    $('#drawerTitle').textContent = 'Nuevo Producto';

    // Resetear estado de las imágenes
    selectedProductImages = [];
    existingProductImages = [];
    imagesToDelete = [];
    currentCategoryTienda = '';

    // Limpiar previsualización de imágenes
    renderProductImagePreview();

    // Resetear y habilitar dropdown de categoría de tienda
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

    // Limpiar selector de insignias (badges)
    if (window.badgesMultiSelect) {
        window.badgesMultiSelect.setValues([]);
    }
}

async function populateDrawerForEdit(p) {
    $('#drawerTitle').textContent = 'Editar Producto';
    $('#prodId').value = p.id;
    $('#prodTitulo').value = p.titulo;
    $('#prodPrecio').value = p.precio;
    $('#prodDescripcion').value = p.descripcion;
    $('#prodCondicion').value = p.estado || 'Nuevo';

    // Cargar y seleccionar categoría y subcategoría
    const catSelect = $('#prodCategoriaId');
    const catTrigger = $('#prodCatIdDropdown .ui-trigger');
    const catLabel = $('#prodCatIdLabel');
    
    if (catSelect) {
        catSelect.value = p.categoria_id;
        
        // Establecer nombre de categoría
        let catName = 'Categoría'; // Fallback
        const menuOption = $(`#prodCatIdDropdown .ui-option[onclick*="'${p.categoria_id}'"]`);
        if (menuOption) {
            catName = menuOption.innerText.trim();
        }
        catLabel.innerText = catName;
        
        // BLOQUEAR CATEGORÍA en modo edición para evitar cambios
        if (catTrigger) {
            catTrigger.classList.add('disabled');
            catTrigger.onclick = null;
            catTrigger.style.pointerEvents = 'none';
        }
        
        await cargarSubcategorias(p.categoria_id, p.subcategoria_id);
    }

    // Cargar insignias existentes
    if (window.badgesMultiSelect && p.badges) {
        window.badgesMultiSelect.setValues(p.badges);
    }
    
    // Cargar sección de tienda
    currentCategoryTienda = p.categoria_tienda || '';

    // Cargar imágenes existentes
    if (p.imagen_principal) {
        existingProductImages = [p.imagen_principal];
        renderProductImagePreview();
    } else if (p.imagenes) {
        existingProductImages = p.imagenes;
        renderProductImagePreview();
    }
}

async function setupDrawerForNew(sectionContext = null) {
    // 1. Verificar si hay una Categoría por Defecto del Sector (se mantiene)
    if (window.tiendaState && window.tiendaState.categoriaDefaultId && window.tiendaState.categoriaDefaultId !== 'null') {
        const catId = window.tiendaState.categoriaDefaultId;
        const catInput = $('#prodCategoriaId');
        const catTrigger = $('#prodCatIdDropdown .ui-trigger');
        const catLabel = $('#prodCatIdLabel');

        if (catInput && catTrigger && catLabel) {
            catInput.value = catId;
            
            let catName = 'Categoría'; // Fallback
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
        // Asegurarse de que el dropdown de categoría no esté bloqueado
        const catTrigger = $('#prodCatIdDropdown .ui-trigger');
        if (catTrigger) {
            catTrigger.classList.remove('disabled');
            catTrigger.style.pointerEvents = 'auto';
            catTrigger.onclick = () => toggleUI('prodCatIdDropdown');
        }
    }

    // 2. NUEVO: Aplicar el contexto de la sección si existe
    if (sectionContext) {
        currentCategoryTienda = sectionContext; // Guardar para el envío del formulario

        // Actualizar UI del dropdown de sección
        const hiddenInput = $('#prodCategoriaTienda');
        if (hiddenInput) hiddenInput.value = sectionContext;

        const label = $('#prodCatTiendaLabel');
        const item = window.menuItems.find(i => i.label === sectionContext);
        if (label && item) {
            label.innerText = toTitleCase(item.label);
        }

        // Deshabilitar el dropdown para forzar el contexto
        const trigger = $('#prodCatTiendaDropdown .ui-trigger');
        if (trigger) {
            trigger.classList.add('disabled');
            trigger.style.pointerEvents = 'none';
            trigger.style.opacity = '0.7';
            trigger.onclick = null;
        }
    }

    // 3. Previsualizar "tarjeta fantasma" en el iframe (se mantiene)
    updateGhostCard();
    setTimeout(() => {
        postToFrame('scrollTo', { selector: '#ghostCard' });
    }, 100);
}

let selectedProductImages = [];
let existingProductImages = [];
let imagesToDelete = [];
let currentCategoryTienda = '';

window.openProductDrawer = async (id = null) => {
    // 1. Abrir el panel y resetear estado base
    resetProductDrawer();
    
    const drawer = $('#productDrawer');
    if (drawer) {
        drawer.classList.add('show');
    } else {
        console.error('PANEL LATERAL NO ENCONTRADO: No se pudo encontrar el elemento con ID #productDrawer');
        return; // Detener ejecución si el panel no existe
    }

    // 2. Determinar modo (Crear o Editar)
    if (id) {
        // MODO EDICIÓN
        const productData = window.allProducts.find(item => item.id == id);
        if (productData) {
            await populateDrawerForEdit(productData);
        } else {
            console.error("Producto no encontrado para editar:", id);
            showToast("Error: No se encontró el producto.", 'error');
            closeProductDrawer();
            return;
        }
    } else {
        // MODO CREACIÓN (MODIFICADO)
        // Ahora pasamos el contexto del filtro de sección activo
        await setupDrawerForNew(currentSectionFilter);
    }
};

// --- TEXT AI GENERATOR (Puter.js Integration) ---
window.generarDescripcionIA = async function() {
    const btn = document.querySelector('.btn-text-ai');
    const descField = document.getElementById('prodDescripcion');

    // 1. Recopilar todos los datos del formulario
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

    // 2. Construir un Prompt Enriquecido
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

        // 3. Manejo de Respuesta Robusto
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
        updateGhostCard();
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
    // document.getElementById('productDrawerOverlay').classList.remove('show');
    postToFrame('previewProduct', { active: false }); // ESTO apaga la ghost card
};

function initGhostCardListeners() {
    const inputs = ['prodTitulo', 'prodPrecio'];
    inputs.forEach(id => {
        const el = document.getElementById(id);
        if(el) {
            el.oninput = updateGhostCard;
            el.onchange = updateGhostCard;
        }
    });
    // No longer needed, as we're reading from UIMultiSelect
    // document.querySelectorAll('.badges-container input[name="badges[]"]').forEach(el => {
    //     el.onchange = updateGhostCard;
    // });
}

function updateGhostCard() {
    const title = document.getElementById('prodTitulo')?.value || '';
    const price = document.getElementById('prodPrecio')?.value || '0.00';
    
    let selectedBadgeIds = [];
    const badgesInput = document.getElementById('badgesInput');
    if (badgesInput && badgesInput.value) {
        selectedBadgeIds = badgesInput.value.split(',').filter(b => b.trim());
    }

    // Mapear IDs a rutas de imagen completas
    const badgeImageUrls = selectedBadgeIds.map(id => {
        const badge = window.availableBadges.find(b => b.id == id);
        // Devolver la ruta con una barra inicial para que sea absoluta
        return badge ? '/' + badge.svg_path : null;
    }).filter(path => path !== null);

    let imgUrl = '';
    const preview = document.getElementById('prodImgPreview');
    if (preview) {
        const firstImg = preview.querySelector('img');
        if (firstImg) {
            // Data URLs funcionan en iframes, blob URLs no
            if (firstImg.src.startsWith('data:')) {
                // Data URL generado por FileReader - funciona perfectamente en iframe
                imgUrl = firstImg.src;
            } else if (firstImg.src.startsWith('blob:')) {
                // Si por alguna razón es blob URL, no funcionará en iframe
                console.warn('Blob URL detectada - no funcionará en iframe');
                imgUrl = firstImg.src;
            } else {
                // Para imágenes existentes, usar URL normal con cache busting y corregir orientación
                const baseUrl = firstImg.src.split('?')[0];
                imgUrl = baseUrl + '?v=' + new Date().getTime() + '&orient=correct';
            }
        }
    }
    
    postToFrame('previewProduct', {
        active: true,
        titulo: title,
        precio: price,
        badges: badgeImageUrls, // Enviar las rutas de las imágenes
        imagen: imgUrl
    });
}

window.cargarSubcategorias = async function(catId, selectedId = null) {
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
         return;
    }

    try {
        const cleanCatId = parseInt(catId);
        if (isNaN(cleanCatId)) {
            if(subLabel) subLabel.innerText = 'Categoría inválida';
            return;
        }

        const res = await fetch(`/api/subcategorias.php?categoria_id=${cleanCatId}`);
        const data = await res.json();

        // Verificación de la respuesta de la API
        if (!data.success) {
            console.error('Error de API al cargar subcategorías:', data.error);
            if(subLabel) subLabel.innerText = 'Sin subcategorías';
            if(subTrigger) subTrigger.classList.remove('disabled'); // Habilitar para que el usuario pueda reintentar
            return;
        }

        const subs = data.data || []; // Asegurarse de que subs sea siempre un array
        
        let html = '';
        // Opción por defecto
        html += `<div class="ui-option" onclick="selectUIOption('prodSubcatIdDropdown', '', '-- Seleccionar --', (val)=>{ document.getElementById('prodSubcategoriaId').value=val; })">-- Seleccionar --</div>`;
        
        let selectedName = '-- Seleccionar --';

        if (Array.isArray(subs)) {
            subs.forEach(s => {
                const isSelected = (selectedId && s.id == selectedId);
                if (isSelected) selectedName = s.nombre;
                
                html += `<div class="ui-option ${isSelected ? 'selected' : ''}" onclick="selectUIOption('prodSubcatIdDropdown', '${s.id}', '${s.nombre}', (val)=>{ document.getElementById('prodSubcategoriaId').value=val; })">
                    ${s.nombre}
                </div>`;
            });
        }
        
        if(subMenu) subMenu.innerHTML = html;
        if(subLabel) subLabel.innerText = selectedName;
        
        // Si tenemos un ID seleccionado, actualizar el input oculto
        if (selectedId && subInput) subInput.value = selectedId;
        
        // Habilitar trigger
        if(subTrigger) subTrigger.classList.remove('disabled');
        
    } catch(e) {
        console.error(e);
        if(subLabel) subLabel.innerText = 'Sin subcategorías';
    }
};

window.renderProductImagePreview = function() {
    const zone = document.getElementById('prodImgPreview');
    zone.innerHTML = '';
    
    // Existing
    existingProductImages.forEach((img, idx) => {
        const div = document.createElement('div');
        div.style.cssText = "position:relative; aspect-ratio:1/1; border-radius:8px; overflow:hidden;";
        div.innerHTML = `<img src="/uploads/${img.nombre_archivo}" style="width:100%; height:100%; object-fit:cover; transform: scale(1);">
            <button type="button" onclick="deleteExistingImage(${idx})" style="position:absolute; top:2px; right:2px; background:red; color:white; border:none; border-radius:50%; width:20px; height:20px; font-size:12px;">&times;</button>`;
        zone.appendChild(div);
    });

    // New
    selectedProductImages.forEach((file, idx) => {
        const reader = new FileReader();
        reader.onload = (e) => {
            const div = document.createElement('div');
            div.style.cssText = "position:relative; aspect-ratio:1/1; border-radius:8px; overflow:hidden; border:1px solid blue;";
            // Usar el data URL del FileReader, no blob URL
            div.innerHTML = `<img src="${e.target.result}" style="width:100%; height:100%; object-fit:cover; transform: scale(1);">
                <button type="button" onclick="deleteNewImage(${idx})" style="position:absolute; top:2px; right:2px; background:black; color:white; border:none; border-radius:50%; width:20px; height:20px; font-size:12px;">&times;</button>`;
            zone.appendChild(div);
            
            // Trigger ghost update after last image loads
            if (idx === selectedProductImages.length - 1) updateGhostCard();
        };
        reader.readAsDataURL(file);  // Esto genera data URL, no blob URL
    });
    
    // Trigger update immediately for existing images case
    updateGhostCard();
};

window.deleteExistingImage = (idx) => {
    if(confirm('¿Borrar?')) {
        imagesToDelete.push(existingProductImages[idx].id);
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
// Initialize immediately
initProductImageUploader();

// Initialize UIMultiSelect for badges
window.initBadgesMultiSelect = function() {
    if (typeof UIMultiSelect !== 'undefined' && window.availableBadges) {
        // Transformar los badges disponibles al formato que requiere el componente
        const badgeOptions = window.availableBadges.map(badge => {
            return { 
                value: String(badge.id), // Forzar el valor a ser un string
                label: badge.nombre
            };
        });

        window.badgesMultiSelect = new UIMultiSelect({
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
                updateGhostCard();
            }
        });
    }
};

// Initialize badges multiselect
initBadgesMultiSelect();


window.guardarProducto = async function() {
    const formData = new FormData();
    const id = document.getElementById('prodId').value;
    if(id) formData.append('id', id);

    const titulo = document.getElementById('prodTitulo').value.trim();
    const descripcion = document.getElementById('prodDescripcion').value.trim();
    const precio = document.getElementById('prodPrecio').value;

    if(titulo.length < 10) return showNotif('El título debe tener al menos 10 caracteres', 'error');
    if(!precio || parseFloat(precio) <= 0) return showNotif('Precio inválido', 'error');

    formData.append('titulo', titulo);
    formData.append('precio', precio);
    formData.append('descripcion', descripcion);
    formData.append('estado', document.getElementById('prodCondicion').value);
    
    // Leer badges del UIMultiSelect en lugar de checkboxes
    const badges = [];
    const badgesInput = document.getElementById('badgesInput');
    if (badgesInput && badgesInput.value) {
        badges.push(...badgesInput.value.split(',').filter(b => b.trim()));
    }
    formData.append('badges', JSON.stringify(badges));
    formData.append('categoria_tienda', currentCategoryTienda);
    formData.append('categoria_id', document.getElementById('prodCategoriaId').value);
    formData.append('subcategoria_id', document.getElementById('prodSubcategoriaId').value);
    
    // Default location from store state
    formData.append('departamento', window.tiendaState.deptCode || 'SCZ');
    formData.append('municipio', window.tiendaState.munCode || 'SCZ-001');

    selectedProductImages.forEach(file => formData.append(id ? 'imagenes_nuevas[]' : 'imagenes[]', file));
    if(id && imagesToDelete.length > 0) formData.append('imagenes_eliminar', JSON.stringify(imagesToDelete));

    const btn = document.getElementById('btnGuardarProducto');
    btn.textContent = 'Guardando...';
    btn.disabled = true;

    try {
        const endpoint = id ? '/api/editar_producto_completo.php' : '/api/crear_producto_completo.php';
        const res = await fetch(endpoint, { method: 'POST', body: formData });
        const result = await res.json();
        
        if(result.success) {
            showNotif('Producto guardado');
            closeProductDrawer();
            
            // Reload iframe to show changes
            storeFrame.src = storeFrame.src;
            
            // Refresh local list (would require re-fetch, but for now just reload page or fetch api)
            // For simplicity, we reload the whole page or fetch updated list logic if we had an API for it.
            // Since we are in "Editor Mode", user expects to see it in list.
            location.reload(); 
        } else {
            showNotif(result.message || 'Error al guardar', 'error');
        }
    } catch(e) { 
        showNotif('Error de conexión', 'error');
        console.error(e);
    } finally {
        btn.textContent = 'Guardar';
        btn.disabled = false;
    }
};



// --- NEW LOGO UPLOAD/DELETE LOGIC ---
// [REMOVED] Legacy handlers replaced by ImageUploader class.


// --- [NUEVO] IDENTIDAD DE MARCA (LOGO PRINCIPAL) ---

function initBrandIdentityControls() {
    const uploader = document.getElementById('brand-logo-uploader');
    const fileInput = document.getElementById('logoPrincipalInput');
    const deleteBtn = document.getElementById('deletePrincipalLogoBtn');
    const mostrarLogoToggle = document.getElementById('mostrar_logo_principal');
    const mostrarNombreToggle = document.getElementById('mostrar_nombre_tienda');

    // Simplified check, we don't need the content sections anymore for toggling
    if (!uploader || !fileInput || !deleteBtn || !mostrarLogoToggle || !mostrarNombreToggle) {
        console.warn('Faltan elementos de UI de Identidad de Marca.');
        return;
    }

    // [MODIFICADO] La lógica de subida y borrado se maneja ahora exclusivamente en ImageUploader.js
    // Se han eliminado los listeners duplicados que causaban conflictos.

    // 5. Toggles de visibilidad (LOGIC CORRECTED)
    mostrarLogoToggle.addEventListener('change', () => {
        const isVisible = mostrarLogoToggle.checked;
        window.tiendaState.mostrar_logo = isVisible;

        // The line that hides the control has been removed.

        postToFrame('updateLogoState', { 
            visible: isVisible,
            url: window.tiendaState.logo_principal ? `/uploads/logos/${window.tiendaState.logo_principal}` : null
        });
        markUnsaved();
    });

    mostrarNombreToggle.addEventListener('change', () => {
        const isVisible = mostrarNombreToggle.checked;
        window.tiendaState.mostrar_nombre = isVisible;

        // The line that hides the control has been removed.

        postToFrame('updateText', {
            selector: '.store-name',
            text: document.getElementById('storeName').value,
            visible: isVisible
        });
        markUnsaved();
    });
    
    // 6. Sincronización inicial de los toggles
    mostrarLogoToggle.checked = !!window.tiendaState.mostrar_logo;
    mostrarNombreToggle.checked = !!window.tiendaState.mostrar_nombre;
    updateLogoToggleState(); // This disables the toggle if no logo exists.
}

function updateLogoToggleState() {
    const mostrarLogoToggle = document.getElementById('mostrar_logo_principal');
    if (!mostrarLogoToggle) return;

    // 1. FIX: El toggle nunca se deshabilita. El usuario siempre debe poder interactuar con él.
    mostrarLogoToggle.disabled = false; 

    // 2. El estado visual del toggle (`checked`) DEBE reflejar el estado guardado (`mostrar_logo`).
    // Esto corrige el bug donde se forzaba a 'off' si no había un archivo de logo.
    mostrarLogoToggle.checked = !!window.tiendaState.mostrar_logo;

    // 3. La visibilidad de la sección de subida de logo ya no se gestiona aquí.

    // 4. Sincronizar con el iframe para mostrar el logo, el placeholder o nada.
    const hasLogoFile = !!window.tiendaState.logo_principal;
    postToFrame('updateLogoState', { 
        visible: mostrarLogoToggle.checked,
        url: hasLogoFile ? `/uploads/logos/${window.tiendaState.logo_principal}` : null
    });
}



// --- [NUEVO] IDENTIDAD DE MARCA ---





// --- UTILS ---
window.showNotif = function(msg, type='success') {
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
};

// --- LOGO DELETION & UPLOAD ---
// [REMOVED] Legacy functions removeLogo and setupUploader.
// Replaced by ImageUploader class.


// --- SOCIALS UPDATES ---
function updateSocialHidden(network, value) {
    const input = document.getElementById('input' + network.charAt(0).toUpperCase() + network.slice(1));
    if(input) input.value = value;
    markUnsaved();
    syncContact();
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
    syncContact();
};

window.handleLocation = function() {
    const input = document.getElementById('inputMaps');
    const btn = document.querySelector('.btn-social-icon.maps');
    if (!input || !btn) return;

    if (btn.classList.contains('active')) {
        // Location exists, ask to delete
        if (confirm('¿Deseas eliminar la ubicación guardada?')) {
            input.value = '';
            btn.classList.remove('active');
            markUnsaved();
            syncContact();
        }
    } else {
        // Location does not exist, detect it
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
            syncContact();
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
// --- INVENTORY DRAWER MANAGER ---
window.openFeriaDrawer = function() {
    // Cerrar otros
    if(typeof closeSectionsDrawer === 'function') closeSectionsDrawer();
    if(typeof closeProductDrawer === 'function') closeProductDrawer();
    if(typeof closeHomeDrawer === 'function') closeHomeDrawer();
    if(typeof closeInventoryDrawer === 'function') closeInventoryDrawer();

    const drawer = document.getElementById('feriaDrawer');
    if(drawer) {
        drawer.classList.add('show');
        if (window.updateBannerSlotsUI) {
            updateBannerSlotsUI();
        }
    }
};

window.closeFeriaDrawer = function() {
    const drawer = document.getElementById('feriaDrawer');
    if(drawer) drawer.classList.remove('show');
};

window.openInventoryDrawer = function() {
    // Cerrar otros
    if(typeof closeSectionsDrawer === 'function') closeSectionsDrawer();
    if(typeof closeProductDrawer === 'function') closeProductDrawer();
    if(typeof closeHomeDrawer === 'function') closeHomeDrawer();
    
    const drawer = document.getElementById('inventoryDrawer');
    if(drawer) {
        drawer.classList.add('show');
        renderInventoryList();
        updateInventoryFilters();
    }
};

window.closeInventoryDrawer = function() {
    const drawer = document.getElementById('inventoryDrawer');
    if(drawer) drawer.classList.remove('show');
};

// --- HOME DRAWER MANAGER ---
window.openHomeDrawer = function() {
    // Cerrar otros
    if(typeof closeSectionsDrawer === 'function') closeSectionsDrawer();
    if(typeof closeProductDrawer === 'function') closeProductDrawer();
    if(typeof closeInventoryDrawer === 'function') closeInventoryDrawer();
    
    const drawer = document.getElementById('homeDrawer');
    if(drawer) {
        drawer.classList.add('show');
    }
};

window.closeHomeDrawer = function() {
    const drawer = document.getElementById('homeDrawer');
    if(drawer) drawer.classList.remove('show');
};

// Renderizado y Lógica


window.toggleProductoActivo = async function(id, estadoActual) {
    const nuevoEstado = parseInt(estadoActual) === 1 ? 0 : 1;
    const btn = event.currentTarget; 
    
    const icon = btn.querySelector('i');
    icon.className = 'fas fa-spinner fa-spin';
    
    try {
        const res = await fetch('/api/toggle_producto_activo.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id: id, activo: nuevoEstado })
        });
        
        const data = await res.json();
        
        if (data.success) {
            // Actualizar dato local
            const prod = window.allProducts.find(p => p.id == id);
            if (prod) prod.activo = nuevoEstado;
            
            renderInventoryList();
            
            // Feedback
            const msg = nuevoEstado === 0 ? 'Marcado Sin Stock (Oculto)' : 'Marcado En Stock (Visible)';
            showNotif(msg);
            
            // --- ACTUALIZACIÓN VISUAL REAL (Recarga) ---
            const frame = document.getElementById('storeFrame');
            if(frame) frame.src = frame.src; // Parpadeo necesario para garantizar consistencia
            
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
    
    // --- FILTRADO ---
    // 1. Texto
    const searchText = document.getElementById('invSearch').value.toLowerCase();
    if (searchText) {
        products = products.filter(p => p.titulo.toLowerCase().includes(searchText));
    }
    
    // 2. Sección
    const sectionVal = document.getElementById('invSectionVal').value;
    if (sectionVal) {
        products = products.filter(p => p.categoria_tienda === sectionVal);
    }

    // 3. Estado Stock (UNIFICADO EN SORT)
    const sortVal = document.getElementById('invSortVal').value;
    
    // Filtrar primero por estado si corresponde
    if (sortVal === 'in_stock') {
        products = products.filter(p => parseInt(p.activo) === 1);
    } else if (sortVal === 'out_stock') {
        products = products.filter(p => parseInt(p.activo) === 0);
    }
    
    // --- ORDENAMIENTO ---
    products.sort((a, b) => {
        if (sortVal === 'recent' || sortVal === 'in_stock' || sortVal === 'out_stock') return b.id - a.id; // Default orden para filtros de estado
        if (sortVal === 'oldest') return a.id - b.id;
        if (sortVal === 'price_asc') return parseFloat(a.precio) - parseFloat(b.precio);
        if (sortVal === 'price_desc') return parseFloat(b.precio) - parseFloat(a.precio);
        return 0;
    });
    
    // --- RENDER ---
    document.getElementById('invCount').innerText = `${products.length} productos`;
    
    if (products.length === 0) {
        list.innerHTML = `
            <div style="text-align:center; padding:40px 20px; color:#94a3b8;">
                <i class="fas fa-box-open" style="font-size:32px; margin-bottom:10px; opacity:0.5;"></i>
                <div style="font-size:13px;">No se encontraron productos</div>
            </div>
        `;
        return;
    }
    
    products.forEach(p => {
        const imgUrl = p.imagen_principal ? `/uploads/${p.imagen_principal}` : '/assets/img/default-store.png';
        const section = p.categoria_tienda || 'Inicio';
        
        const card = document.createElement('div');
        card.className = 'inv-product-card';
        // Estado visual según activo
        if (parseInt(p.activo) === 0) card.classList.add('hidden-product'); 

        // Icono de STOCK (Caja)
        const isHidden = parseInt(p.activo) === 0;
        const stockIcon = isHidden ? 'fa-box-open' : 'fa-box';
        const stockTitle = isHidden ? 'Sin Stock (Oculto)' : 'En Stock (Visible)';
        // Clases específicas para color
        const stockClass = isHidden ? 'stock-out' : 'stock-in';

        card.innerHTML = `
            <div class="inv-img-box">
                <img src="${imgUrl}" class="inv-img">
            </div>
            <div class="inv-info">
                <div class="inv-title" title="${p.titulo}">${p.titulo}</div>
                <div class="inv-meta">
                    <span class="inv-price">Bs ${p.precio}</span>
                </div>
            </div>
            <div class="inv-actions">
                <button class="btn-inv-action ${stockClass}" title="${stockTitle}" onclick="toggleProductoActivo(${p.id}, ${p.activo})">
                    <i class="fas ${stockIcon}"></i>
                </button>
                <button class="btn-inv-action edit" title="Editar" onclick="openProductDrawer(${p.id})">
                    <i class="fas fa-pencil-alt"></i>
                </button>
                <button class="btn-inv-action delete" title="Eliminar" onclick="eliminarProducto(${p.id})">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>
        `;
        list.appendChild(card);
    });
};

window.filterInventory = function() {
    renderInventoryList();
};

// --- HELPER: Title Case (Capitalización Real) ---


window.updateInventoryFilters = function() {
    // Actualizar Dropdown de Secciones en Inventario con las actuales
    const menu = document.getElementById('invSectionMenu');
    const label = document.getElementById('invSectionLabel');
    if (!menu) return;
    
    let html = `<div class="ui-option selected" onclick="selectUIOption('invSectionFilter', '', 'Todas las secciones', (v)=>{document.getElementById('invSectionVal').value=v; filterInventory();})">Todas Las Secciones</div>`;
    
    if (window.menuItems && Array.isArray(window.menuItems)) {
        window.menuItems.forEach(item => {
            if (item.label.toLowerCase() === 'inicio') return;
            // Aplicar Title Case aquí al generar el HTML
            const prettyLabel = toTitleCase(item.label);
            html += `<div class="ui-option" onclick="selectUIOption('invSectionFilter', '${item.label}', '${prettyLabel}', (v)=>{document.getElementById('invSectionVal').value=v; filterInventory();})">${prettyLabel}</div>`;
        });
    }
    menu.innerHTML = html;
    
    // Resetear visualmente si estaba filtrado
    const currentVal = document.getElementById('invSectionVal').value;
    if(!currentVal && label) label.innerText = 'Todas Las Secciones';
};

window.verProductoEnTienda = function(slug, id) {
    const url = `/tienda/${slug}/producto/${id}`;
    window.open(url, '_blank');
};

window.eliminarProducto = async function(id) {
    if(!confirm('¿Eliminar producto?')) return;
    
    try {
        const res = await fetch('/api/eliminar_producto.php', { method: 'POST', body: JSON.stringify({id: id}) });
        if(res.ok) {
            showNotif('Producto eliminado');
            // Actualizar datos globales
            window.allProducts = window.allProducts.filter(p => p.id != id);
            // Actualizar ambas vistas
            renderSidebarProducts(); // Lista simple
            renderInventoryList(); // Inventario Pro
            
            // Recargar iframe
            const frame = document.getElementById('storeFrame');
            if(frame) frame.src = frame.src;
        }
    } catch(e) { showNotif('Error', 'error'); }
};

// Override guardarProducto success callback part
// Esto es complejo porque guardarProducto es grande. 
// Mejor: Al terminar guardarProducto (que recarga página), el inventario se cerrará.
// Si queremos mantenerlo abierto, necesitaríamos lógica de estado persistente o SPA total.
// Por ahora, recargar la página está bien, el usuario volverá a abrir inventario si quiere.

// --- END INVENTORY MANAGER ---
window.initSectionsManager = function() {
    console.log('Iniciando Gestor de Secciones...');
    // Inicializar filtro maestro al cargar
    try { renderMasterFilter(); } catch(e) { console.error('Error renderMasterFilter', e); }
    // Renderizar lista en drawer
    try { renderSectionsList(); } catch(e) { console.error('Error renderSectionsList', e); }
    
    // Bind click event explícitamente por seguridad
    const btn = document.getElementById('btnOpenSections');
    if(btn) {
        btn.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            openSectionsDrawer();
        };
    }
};

// DRAWER CONTROLS
window.openSectionsDrawer = function() {
    console.log('Abriendo drawer secciones...');
    // Cerrar otros drawers para evitar solapamiento
    if(typeof closeProductDrawer === 'function') closeProductDrawer();
    if(typeof closeInventoryDrawer === 'function') closeInventoryDrawer();
    if(typeof closeHomeDrawer === 'function') closeHomeDrawer();
    
    const drawer = document.getElementById('sectionsDrawer');
    if(drawer) {
        drawer.classList.add('show');
        renderSectionsList(); 
    } else {
        console.error('No se encontró el drawer #sectionsDrawer');
    }
};

window.closeSectionsDrawer = function() {
    const drawer = document.getElementById('sectionsDrawer');
    if(drawer) {
        drawer.classList.remove('show');
    }
};

// Helper to Capitalize
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
    
    // 1. Fixed "Inicio" - Ahora estilizado como las demás (Sólido)
    const homeCount = (window.allProducts && Array.isArray(window.allProducts)) ? window.allProducts.length : 0; 
    const homeDiv = document.createElement('div');
    homeDiv.className = 'section-sortable-item fixed-item'; // Clase unificada
    
    homeDiv.innerHTML = `
        <div class="drag-handle disabled" title="Fijo">
            <i class="fas fa-lock" style="font-size:12px; opacity:0.5;"></i>
        </div>
        <div style="flex: 1; display: flex; flex-direction: column; min-width: 0;">
            <div style="font-weight: 600; color: #334155; font-size: 14px;">Inicio</div>
            <span style="font-size: 11px; color: #64748b; margin-top:2px;">
                ${homeCount} productos (Total)
            </span>
        </div>
        <div style="display: flex; gap: 6px;">
            <span style="background: #e2e8f0; color: #64748b; font-size: 10px; font-weight: 600; padding: 4px 8px; border-radius: 20px;">Fijo</span>
        </div>
    `;
    container.appendChild(homeDiv);
    
    // 2. Custom Sections
    if (window.menuItems && Array.isArray(window.menuItems)) {
        window.menuItems.forEach((item, index) => {
            if (item.label.toLowerCase() === 'inicio' || item.label.toLowerCase() === 'todos') return;

            const div = document.createElement('div');
            // Estilos base para el item draggeable
            div.className = 'section-sortable-item';
            if (item.hidden) div.classList.add('hidden-section'); // Opacidad si está oculto
            
            div.draggable = true;
            div.dataset.index = index;
            
            // Eventos Drag & Drop
            div.addEventListener('dragstart', handleDragStart);
            div.addEventListener('dragover', handleDragOver);
            div.addEventListener('drop', handleDrop);
            div.addEventListener('dragenter', handleDragEnter);
            div.addEventListener('dragleave', handleDragLeave);
            div.addEventListener('dragend', handleDragEnd);

            // Count products
            const count = (window.allProducts && Array.isArray(window.allProducts)) 
                ? window.allProducts.filter(p => p.categoria_tienda === item.label).length 
                : 0;
            const displayName = toTitleCase(item.label);
            
            // HTML Estructura (Sólido + Ojo + Drag Handle Azul)
            div.innerHTML = `
                <div class="drag-handle" title="Arrastrar para ordenar">
                    <i class="fas fa-grip-vertical"></i>
                </div>
                <div style="flex: 1; display: flex; flex-direction: column; min-width: 0;">
                    <div class="section-name-display" id="displayName-${index}" style="font-weight: 600; color: #334155; font-size: 14px; cursor:pointer;" onclick="toggleInlineEdit(${index})">${displayName}</div>
                    <input type="text" class="section-name-edit" id="editInput-${index}" value="${item.label}" style="display:none; width:100%; padding:4px; font-size:14px; border:1px solid #22226B; border-radius:4px;" onblur="saveInlineEdit(${index})" onkeydown="handleEditKey(event, ${index})">
                    
                    <span style="font-size: 11px; color: #64748b; margin-top:2px;">
                        ${count} productos
                    </span>
                </div>
                <div style="display: flex; gap: 6px;">
                    <button onclick="toggleVisibility(${index})" title="${item.hidden ? 'Mostrar' : 'Ocultar'}" class="btn-icon-mini-action eye ${item.hidden ? 'off' : ''}">
                        <i class="fas ${item.hidden ? 'fa-eye-slash' : 'fa-eye'}"></i>
                    </button>
                    <button onclick="toggleInlineEdit(${index})" title="Renombrar" class="btn-icon-mini-action edit"><i class="fas fa-pencil-alt"></i></button>
                    <button onclick="deleteSection(${index})" title="Eliminar" class="btn-icon-mini-action delete"><i class="fas fa-trash-alt"></i></button>
                </div>
            `;
            container.appendChild(div);
        });
    }
};

// --- VISIBILITY TOGGLE ---
window.toggleVisibility = function(index) {
    if (!window.menuItems[index]) return;
    
    // Toggle state
    window.menuItems[index].hidden = !window.menuItems[index].hidden;
    
    renderSectionsList();
    renderMasterFilter(); // Filter should probably hide/show these too?
    markUnsaved();
    updateMenuInFrame();
};

// --- DRAG & DROP LOGIC ---
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
    
    // Quitar indicador de todos
    document.querySelectorAll('.section-sortable-item').forEach(i => i.classList.remove('drag-over-top', 'drag-over-bottom'));

    if (afterElement == null) {
        // Al final de la lista
        container.appendChild(dragSrcEl);
    } else {
        // Antes de un elemento específico
        container.insertBefore(dragSrcEl, afterElement);
    }
    
    return false;
}

// Helper para detectar posición exacta del mouse
function getDragAfterElement(container, y) {
    const draggableElements = [...container.querySelectorAll('.section-sortable-item:not(.dragging):not(.fixed-item)')];

    return draggableElements.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;
        
        if (offset < 0 && offset > closest.offset) {
            return { offset: offset, element: child };
        } else {
            return closest;
        }
    }, { offset: Number.NEGATIVE_INFINITY }).element;
}


function handleDragEnter(e) {
    // Ya no es necesario marcar estilos aquí, lo maneja dragOver con inserción real
}

function handleDragLeave(e) {
    // Limpieza manejada por dragOver
}

function handleDragEnd(e) {
    this.classList.remove('dragging');
    document.querySelectorAll('.section-sortable-item').forEach(item => {
        item.classList.remove('drag-over-top', 'drag-over-bottom');
    });

    // RE-CALCULAR ÍNDICES Y GUARDAR ORDEN REAL
    updateMenuItemsOrder();
}

function handleDrop(e) {
    if (e.stopPropagation) e.stopPropagation();
    return false;
}

function updateMenuItemsOrder() {
    const container = document.getElementById('drawerSectionsList');
    const newOrderLabels = [];
    
    // Leer el DOM para ver el nuevo orden visual
    container.querySelectorAll('.section-sortable-item').forEach(div => {
        const nameDisplay = div.querySelector('.section-name-display');
        if (nameDisplay) {
            newOrderLabels.push(nameDisplay.textContent.trim()); // Usamos el texto visible
        } else {
             // Es el input edit?
             const input = div.querySelector('.section-name-edit');
             if(input) newOrderLabels.push(input.value.trim());
        }
    });

    // Reconstruir menuItems basado en etiquetas (labels)
    // Nota: Esto asume que las etiquetas son únicas. Si no, usar data-index original es más seguro.
    // Mejor enfoque: Usar data-index original para buscar el objeto.
    
    const newMenuItems = [];
    
    // Preservar Inicio/Todos primero
    const fixedItems = window.menuItems.filter(i => i.label.toLowerCase() === 'inicio' || i.label.toLowerCase() === 'todos');
    newMenuItems.push(...fixedItems);

    // Mapear el resto según el DOM
    container.querySelectorAll('.section-sortable-item:not(.fixed-item)').forEach(div => {
         const originalIndex = div.dataset.index; // Indice original en window.menuItems
         if(originalIndex !== undefined && window.menuItems[originalIndex]) {
             newMenuItems.push(window.menuItems[originalIndex]);
         }
    });

    // Actualizar estado global
    window.menuItems = newMenuItems;
    
    // Guardar y Refrescar
    renderMasterFilter(); // Actualiza dropdown principal
    markUnsaved();
    updateMenuInFrame();
    
    // Re-renderizar lista para actualizar data-indexes
    renderSectionsList(); 
}

// --- INLINE EDIT LOGIC ---
window.toggleInlineEdit = function(index) {
    const display = document.getElementById(`displayName-${index}`);
    const input = document.getElementById(`editInput-${index}`);
    
    if (display.style.display === 'none') {
        // Cancelar (volver a mostrar texto)
        display.style.display = 'block';
        input.style.display = 'none';
    } else {
        // Editar (mostrar input)
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
        
        // Actualizar productos asociados
        window.allProducts.forEach(p => {
            if(p.categoria_tienda === oldVal) p.categoria_tienda = newVal;
        });
        
        // Actualizar UI completa
        renderSectionsList();
        renderMasterFilter();
        markUnsaved();
        updateMenuInFrame();
        
        // Feedback visual
        showNotif('Sección renombrada');
    } else {
        // Restaurar si no hubo cambios
        renderSectionsList();
    }
};

window.handleEditKey = function(e, index) {
    if (e.key === 'Enter') {
        saveInlineEdit(index);
    } else if (e.key === 'Escape') {
        renderSectionsList(); // Reset
    }
};

window.quickAddProduct = function(sectionName) {
    closeSectionsDrawer();
    applySectionFilter(sectionName);
    // Esperar un poco para que la transición cierre
    setTimeout(() => {
        openProductDrawer(null); // Abre drawer de producto
        // Pre-seleccionar la sección
        const select = document.getElementById('prodCategoriaTienda');
        if(select) select.value = sectionName;
    }, 300);
};

window.addNewSectionFromDrawer = function() {
    const input = document.getElementById('drawerNewSectionInput');
    if (!input) return;
    
    const name = input.value.trim();
    if (!name) return showNotif('Escribe un nombre', 'error');
    
    // Check duplicates
    const exists = window.menuItems.some(i => i.label.toLowerCase() === name.toLowerCase());
    if (exists) return showNotif('Esa sección ya existe', 'error');
    
    window.menuItems.push({ label: name, url: '#' });
    input.value = '';
    
    renderSectionsList(); 
    renderMasterFilter(); 
    markUnsaved(); 
    updateMenuInFrame();
    
    showNotif('Sección creada');
};

// Aliases para compatibilidad
window.addNewSection = window.addNewSectionFromDrawer;

window.deleteSection = function(index) {
    if(!confirm('¿Eliminar esta sección? Los productos volverán a Inicio.')) return;
    
    // Check if current filter is this section, if so, reset to All
    const sectionName = window.menuItems[index].label;
    if (currentSectionFilter === sectionName) {
        applySectionFilter('');
        document.getElementById('masterSectionFilter').value = '';
    }

    window.menuItems.splice(index, 1);
    
    renderSectionsList();
    renderMasterFilter();
    markUnsaved();
    updateMenuInFrame();
};

window.editSection = function(index) {
    const current = window.menuItems[index].label;
    const newName = prompt('Nuevo nombre:', current);
    
    if (newName && newName.trim() !== '' && newName !== current) {
        const oldName = current;
        const finalName = newName.trim();
        
        window.menuItems[index].label = finalName;
        
        // Update Filter if active
        if (currentSectionFilter === oldName) {
            currentSectionFilter = finalName;
            window.allProducts.forEach(p => {
                if(p.categoria_tienda === oldName) p.categoria_tienda = finalName;
            });
            renderSidebarProducts();
        }
        
        renderSectionsList();
        renderMasterFilter();
        
        // Restore selection in dropdown
        const masterFilter = document.getElementById('masterSectionFilter');
        if(masterFilter.value === oldName) masterFilter.value = finalName;
        
        markUnsaved();
        updateMenuInFrame();
    }
};

// --- FILTER & CONTEXT LOGIC ---
let currentSectionFilter = ''; // '' means All/Inicio

window.renderMasterFilter = function() {
    const select = document.getElementById('masterSectionFilter');
    const filterMenu = document.getElementById('sectionFilterMenu'); // UI Dropdown
    const filterLabel = document.getElementById('sectionFilterLabel'); // UI Label
    
    // El select del formulario drawer (para crear producto)
    const prodSelect = document.getElementById('prodCategoriaTienda'); 
    const formMenu = document.getElementById('prodCatTiendaMenu'); // UI Dropdown Form
    
    // if (!select) return; // Puede que select hidden no exista si no cargó el HTML
    
    const currentVal = select ? select.value : '';
    
    // Calcular conteo total (Protegido contra null/undefined)
    const totalCount = (window.allProducts && Array.isArray(window.allProducts)) ? window.allProducts.length : 0;
    
    // --- CONSTRUIR HTML PARA UI DROPDOWNS ---
    let filterHtml = `<div class="ui-option selected" onclick="selectUIOption('sectionFilterDropdown', '', 'Inicio', (val)=>{ document.getElementById('masterSectionFilter').value=val; applySectionFilter(val); })">Inicio</div>`;
    let formHtml = `<div class="ui-option selected" onclick="selectUIOption('prodCatTiendaDropdown', '', 'Inicio (General)', (val)=>{ document.getElementById('prodCategoriaTienda').value=val; })">Inicio (General)</div>`;
    
    if (window.menuItems && Array.isArray(window.menuItems)) {
        window.menuItems.forEach(item => {
            if (item.label.toLowerCase() === 'inicio') return;
            
            const labelCap = toTitleCase(item.label);
            const count = (window.allProducts && Array.isArray(window.allProducts)) 
                ? window.allProducts.filter(p => p.categoria_tienda === item.label).length 
                : 0;
            
            // Filter Dropdown Item
            filterHtml += `<div class="ui-option" onclick="selectUIOption('sectionFilterDropdown', '${item.label}', '${labelCap}', (val)=>{ document.getElementById('masterSectionFilter').value=val; applySectionFilter(val); })">
                ${labelCap}
            </div>`;
            
            // Form Dropdown Item (Sin conteo)
            formHtml += `<div class="ui-option" onclick="selectUIOption('prodCatTiendaDropdown', '${item.label}', '${labelCap}', (val)=>{ document.getElementById('prodCategoriaTienda').value=val; })">
                ${labelCap}
            </div>`;
        });
    }
    
    // Inyectar HTML
    if(filterMenu) filterMenu.innerHTML = filterHtml;
    if(formMenu) formMenu.innerHTML = formHtml;
    
    // Restaurar label visual si hay valor seleccionado
    if(currentVal && filterLabel) {
        // Buscar el label correcto
        const item = window.menuItems.find(i => i.label === currentVal);
        if(item) filterLabel.innerText = toTitleCase(item.label);
    }
};

window.applySectionFilter = function(sectionName) {
    currentSectionFilter = sectionName;
    renderSidebarProducts(); // Re-render list with filter
    
    // Update "New Product" button text context
    const btn = document.getElementById('btnNewProductContext');
    if(btn) {
        if(sectionName) {
            btn.title = `Nuevo en ${toTitleCase(sectionName)}`;
            btn.classList.add('primary'); // Make it pop, just visually
        } else {
            btn.title = 'Nuevo Producto';
            btn.classList.remove('primary');
        }
    }

    // --- NUEVO: Sincronizar con el Iframe ---
    postToFrame('filterProducts', { section: sectionName || 'todos' });
};





window.renderSidebarProducts = function() {
    const list = document.getElementById('sidebarProductList');
    if (!list) return;
    list.innerHTML = '';
    
    let productsToShow = window.allProducts || [];
    
    // Apply Filter
    if (currentSectionFilter) {
        productsToShow = productsToShow.filter(p => p.categoria_tienda === currentSectionFilter);
    }
    
    if (productsToShow.length === 0) {
        const msg = currentSectionFilter 
            ? `No hay productos en "${currentSectionFilter}"`
            : 'No hay productos';
        list.innerHTML = `<div style="padding:20px; text-align:center; color:#94a3b8; font-size:13px;">${msg}</div>`;
        return;
    }

    productsToShow.forEach(p => {
        const item = document.createElement('div');
        item.className = 'sidebar-product-item';
        
        const imgUrl = p.imagen_principal ? `/uploads/${p.imagen_principal}` : '/assets/img/default-store.png';
        
        // Show section badge if viewing ALL
        let badge = '';
        if (!currentSectionFilter && p.categoria_tienda) {
            badge = `<span style="font-size:10px; background:#e2e8f0; color:#475569; padding:2px 4px; border-radius:4px; margin-left:auto;">${p.categoria_tienda}</span>`;
        }
        
        item.innerHTML = `
            <img src="${imgUrl}" class="sidebar-prod-img">
            <div class="sidebar-prod-info">
                <div class="sidebar-prod-title" title="${p.titulo}">${p.titulo}</div>
                <div class="sidebar-prod-price">Bs ${p.precio}</div>
            </div>
            ${badge}
            <div class="sidebar-prod-actions">
                <button class="btn-icon-mini" onclick="openProductDrawer(${p.id})"><i class="fas fa-pencil-alt"></i></button>
                <button class="btn-icon-mini delete" onclick="eliminarProducto(${p.id})"><i class="fas fa-trash-alt"></i></button>
            </div>
        `;
        list.appendChild(item);
    });
};

// Hook initialization
const originalInit = window.initSidebarProducts;
window.initSidebarProducts = function() {
    originalInit(); // call empty placeholder
    initSectionsManager(); // Init our new manager
};

// --- LÓGICA DE UBICACIÓN FERIA VIRTUAL (V2: Grid Visual) ---

document.addEventListener('DOMContentLoaded', function() {
    const ciudadSelect = document.getElementById('feriaCiudad');
    // Intentar cargar sectores si ya hay ciudad
    if (ciudadSelect && ciudadSelect.value) {
        cargarSectores(true);
    }
});

// 1. Cargar Sectores
window.cargarSectores = function(precargar = false) {
    const ciudad = document.getElementById('feriaCiudad').value;
    
    // UI DROPDOWN REFS
    const sectorInput = document.getElementById('feriaSector');
    const sectorTrigger = document.getElementById('sectorTrigger');
    const sectorLabel = document.getElementById('sectorLabel');
    const sectorMenu = document.getElementById('sectorMenu');

    const bloqueInput = document.getElementById('feriaBloque');
    const bloqueTrigger = document.getElementById('bloqueTrigger');
    const bloqueLabel = document.getElementById('bloqueLabel');
    const bloqueMenu = document.getElementById('bloqueMenu');

    const gridContainer = document.getElementById('feriaGridContainer');
    
    // Reset UI
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
                
                // Si estamos precargando, intentar buscar ubicación actual del usuario
                if (precargar) {
                    fetch('/api/feria_editor.php?action=get_my_location')
                        .then(r => r.json())
                        .then(locData => {
                            if (locData.success && locData.data) {
                                // Seleccionar valores automáticamente
                                if (locData.data.ciudad === ciudad) {
                                    // Set value and trigger load manually
                                    sectorInput.value = locData.data.sector_id;
                                    // Update Label Visual
                                    const selectedOption = data.data.find(s => s.id == locData.data.sector_id);
                                    if(selectedOption) sectorLabel.innerText = selectedOption.titulo;
                                    
                                    cargarBloques(true, locData.data.bloque_id);
                                }
                            }
                        });
                }
            }
        })
        .catch(e => console.error(e));
};

// 2. Cargar Bloques
window.cargarBloques = function(precargar = false, bloqueIdTarget = null) {
    const sectorId = document.getElementById('feriaSector').value;
    
    // UI DROPDOWN REFS
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
                     // Update Label Visual
                    const selectedOption = data.data.find(b => b.id == bloqueIdTarget);
                    if(selectedOption) bloqueLabel.innerText = selectedOption.nombre;

                    cargarGridPuestos();
                }
            }
        })
        .catch(e => console.error(e));
};

// 3. Cargar Grid de Puestos (Visual)
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
                gridSlots.innerHTML = ''; // Limpiar loader
                
                data.data.forEach(slot => {
                    const div = document.createElement('div');
                    div.className = 'feria-slot';
                    div.dataset.slotNumber = slot.numero; // Data attribute para drop
                    
                    // Icono número
                    const numSpan = document.createElement('span');
                    numSpan.className = 'slot-number';
                    numSpan.innerText = slot.numero;
                    div.appendChild(numSpan);

                    if (slot.estado === 'propio') {
                        div.classList.add('my-store');
                        div.title = 'Tu ubicación actual - Arrástrame para mover';
                        div.draggable = true; // Habilitar arrastre
                        
                        // Eventos Drag Source
                        div.addEventListener('dragstart', (e) => {
                            e.dataTransfer.setData('text/plain', slot.numero);
                            e.dataTransfer.effectAllowed = 'move';
                            div.style.opacity = '0.5';
                        });
                        div.addEventListener('dragend', () => {
                            div.style.opacity = '1';
                            document.querySelectorAll('.feria-slot').forEach(el => el.classList.remove('drag-over'));
                        });
                        
                        // Mostrar mi logo
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
                        // LIBRE
                        div.title = `Puesto ${slot.numero} - Click o suelta aquí`;
                        div.onclick = () => ocuparPuesto(slot.numero);
                        
                        // Eventos Drop Target
                        div.addEventListener('dragover', (e) => {
                            e.preventDefault(); // Necesario para permitir drop
                            e.dataTransfer.dropEffect = 'move';
                            div.classList.add('drag-over');
                        });
                        div.addEventListener('dragleave', () => {
                            div.classList.remove('drag-over');
                        });
                        div.addEventListener('drop', (e) => {
                            e.preventDefault();
                            div.classList.remove('drag-over');
                            const origenSlot = e.dataTransfer.getData('text/plain');
                            if (origenSlot) {
                                ocuparPuesto(slot.numero); // Reutilizamos la misma lógica
                            }
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

// 4. Acción: Ocupar Puesto
window.ocuparPuesto = function(slotNumero) {
    const ciudad = document.getElementById('feriaCiudad').value;
    const sectorId = document.getElementById('feriaSector').value;
    const bloqueId = document.getElementById('feriaBloque').value;
    const status = document.getElementById('feriaStatus');

    // Buscar nombre del bloque para confirmación (Limpiando cualquier texto extra)
    // Con UI Dropdown, el nombre está en el Label
    const bloqueLabel = document.getElementById('bloqueLabel').innerText;
    let bloqueNombre = bloqueLabel;
    
    // Limpieza de seguridad por si acaso quedara algo como (Cap: 12)
    bloqueNombre = bloqueNombre.replace(/\(Cap: \d+\)/i, '').trim();

    if (!confirm(`¿Mover tu tienda al Puesto #${slotNumero} del ${bloqueNombre}?`)) return;

    status.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando nueva ubicación...';
    
    fetch('/api/feria_editor.php?action=assign_puesto', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            ciudad: ciudad,
            sector_id: sectorId,
            bloque_id: bloqueId,
            slot_numero: slotNumero
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            status.innerHTML = '<span style="color:green; font-weight:bold;">¡Ubicación actualizada!</span>';
            // Recargar grid para ver cambios visualmente
            cargarGridPuestos();
            
            // Opcional: Recargar iframe si afecta visualización
            // document.getElementById('storeFrame').contentWindow.location.reload();
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
