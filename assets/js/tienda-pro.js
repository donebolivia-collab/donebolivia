// TIENDA PRO JS - Extracted from tienda_pro.php

// Toggle Sections (SPA Feel)
function showSection(sectionId, menuElement) {
  // Esta función ahora SOLO maneja mostrar las secciones principales de la página.
  // La lógica de filtrado está completamente delegada a filterProducts.

  const productsSection = document.getElementById('productos');
  const aboutSection = document.getElementById('about-section');
  const contactSection = document.getElementById('contact-section');

  // Ocultar todas las secciones principales primero
  if (productsSection) productsSection.style.display = 'none';
  if (aboutSection) aboutSection.style.display = 'none';
  if (contactSection) contactSection.style.display = 'none';

  if (sectionId === 'about' || sectionId === 'contact') {
    // Para las páginas estáticas, manejar el menú y la visibilidad aquí
    document.querySelectorAll('.menu-item').forEach(item => {
      item.classList.remove('active');
    });
    if (menuElement) {
      menuElement.classList.add('active');
    }

    if (sectionId === 'about' && aboutSection) {
      aboutSection.style.display = 'block';
    } else if (sectionId === 'contact' && contactSection) {
      contactSection.style.display = 'block';
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
  } else { // 'productos' o cualquier categoría
    // Para las secciones de productos, solo asegurarse de que el contenedor principal esté visible.
    // filterProducts se encargará del menú y de qué productos mostrar/ocultar.
    if (productsSection) productsSection.style.display = 'block';
  }
}

// Filtrar productos por categoría
function filterProducts(filterText, menuElement) {
  const isEditor = window.self !== window.top; // Condición de seguridad

  document.querySelectorAll('.menu-item').forEach(item => {
    item.classList.remove('active');
  });
  if (menuElement) {
    menuElement.classList.add('active');
  }

  const products = document.querySelectorAll('.product-card');
  let visibleCount = 0;

  const normalize = (text) => {
    if (!text) return '';
    return text.toLowerCase()
      .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .trim();
  };

  const cleanFilter = normalize(filterText);

  products.forEach(product => {
    // *** FIX 1: IGNORAR LA TARJETA FANTASMA ***
    if (product.id === 'ghostCard') return;

    const estado = normalize(product.dataset.estado);
    const categoriaTienda = normalize(product.dataset.categoriaTienda);
    let shouldShow = false;

    if (cleanFilter === 'inicio' || cleanFilter === 'todos') {
      shouldShow = true;
    } else if (cleanFilter === 'nuevos' || cleanFilter === 'nuevo') {
      shouldShow = estado === 'nuevo';
    } else if (cleanFilter === 'usados' || cleanFilter === 'usado') {
      shouldShow = estado === 'usado';
    } else {
      shouldShow = categoriaTienda === cleanFilter;
    }

    if (shouldShow) {
      product.style.display = 'block';
      visibleCount++;
    } else {
      product.style.display = 'none';
    }
  });

  // *** FIX 2: AISLAR LÓGICA DEL BANNER ***
  const slider = document.getElementById('heroSliderContainer');
  if (slider) { // FIX: Eliminado el guardia !isEditor que rompía la lógica en el editor
    if (cleanFilter === 'inicio' || cleanFilter === 'todos') {
      slider.classList.remove('hidden-by-filter');
      if (slider.dataset.userEnabled === 'true') {
        slider.style.display = 'block';
      }
    } else {
      slider.classList.add('hidden-by-filter');
      slider.style.display = 'none';
    }
  }

  // *** FIX 3: LÓGICA PARA SECCIONES DESTACADAS ***
  const featuredSections = document.getElementById('secciones-destacadas');
  if (featuredSections) {
    if (cleanFilter === 'inicio' || cleanFilter === 'todos') {
      featuredSections.style.display = 'block';
    } else {
      featuredSections.style.display = 'none';
    }
  }

  if (!isEditor) {
    document.getElementById('productos').scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  if (cleanFilter !== 'inicio' && cleanFilter !== 'todos') {
    const hash = filterText.toLowerCase().replace(/\s+/g, '-');
    history.replaceState(null, null, '#' + hash);
  } else {
    history.replaceState(null, null, ' ');
  }
}

// ===== LÓGICA DEL MODAL DE PRODUCTO =====
const modal = document.getElementById('productModal');
const modalLoading = document.getElementById('modalLoading');
const modalContent = document.getElementById('modalContent');
let currentProductUrl = '';
let isModalHistoryState = false;
let currentProductId = 0;

let currentImages = [];
let currentImageIndex = 0;

function navigateGallery(direction) {
  if (currentImages.length <= 1) return;
  currentImageIndex += direction;
  if (currentImageIndex < 0) currentImageIndex = currentImages.length - 1;
  else if (currentImageIndex >= currentImages.length) currentImageIndex = 0;
  updateGalleryUI();
}

function updateGalleryUI() {
  const mainImg = document.getElementById('modalMainImage');
  const imgData = currentImages[currentImageIndex];

  mainImg.style.opacity = 0;
  setTimeout(() => {
    mainImg.src = '/uploads/' + imgData.nombre_archivo;
    mainImg.style.opacity = 1;
  }, 150);

  const counter = document.getElementById('galleryCounter');
  if (counter) counter.textContent = `${currentImageIndex + 1} / ${currentImages.length}`;

  document.querySelectorAll('.modal-thumb').forEach((t, i) => {
    if (i === currentImageIndex) {
      t.classList.add('active');
      t.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
    } else {
      t.classList.remove('active');
    }
  });
}

function openProductModal(tiendaSlug, productId) {
  const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
  if (scrollbarWidth > 0) document.body.style.paddingRight = `${scrollbarWidth}px`;

  modal.style.display = 'flex';
  setTimeout(() => modal.classList.add('active'), 10);

  modalLoading.style.display = 'flex';
  modalContent.style.display = 'none';
  document.body.style.overflow = 'hidden';
  document.body.classList.add('modal-open');

  currentProductUrl = window.location.origin + '/tienda/' + tiendaSlug + '?producto=' + productId;

  if (!isModalHistoryState) {
    window.history.pushState({ modalOpen: true }, '', currentProductUrl);
    isModalHistoryState = true;
  }

  currentProductId = productId;

  fetch('/api/obtener_producto_publico.php?id=' + productId)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        populateModal(data.producto, data.imagenes, tiendaSlug, data.user_has_liked);
        if (data.producto.visitas) {
          const viewPill = document.getElementById('grid-views-' + productId);
          if (viewPill) viewPill.innerHTML = '<i class="fas fa-eye"></i> ' + data.producto.visitas;
        }
        modalLoading.style.display = 'none';
        modalContent.style.display = 'grid';
      } else {
        alert('Error al cargar el producto');
        closeModal();
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('Error de conexión');
      closeModal();
    });
}

function closeModalNow() {
  modal.classList.remove('active');
  setTimeout(() => {
    modal.style.display = 'none';
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';
    document.body.classList.remove('modal-open');
  }, 300);
  isModalHistoryState = false;
}

function closeModal() {
  if (isModalHistoryState) {
    window.history.back(); // This will trigger popstate
  } else {
    // Fallback for modals not in history
    closeModalNow();
  }
}

modal.addEventListener('click', (e) => {
  if (e.target === modal) closeModal();
});

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && modal.classList.contains('active')) closeModal();
});

function populateModal(p, images, tiendaSlug, userHasLiked) {
  const toSentenceCase = (str) => {
    if (!str) return '';
    const lower = str.toLowerCase();
    return lower.charAt(0).toUpperCase() + lower.slice(1);
  };

  document.getElementById('modalTitle').textContent = toSentenceCase(p.titulo);
  document.getElementById('modalPrice').textContent = p.precio_formateado;
  document.getElementById('modalPriceLiteral').textContent = p.precio_literal || '';
  document.getElementById('modalDescription').innerHTML = p.descripcion.replace(/\n/g, '<br>');

  // RENDERIZAR BADGES DINÁMICOS EN EL MODAL
  const modalBadgesContainer = document.getElementById('modalBadges');
  if (modalBadgesContainer) {
    modalBadgesContainer.innerHTML = ''; // Limpiar badges anteriores

    const badgeTemplates = {
      envio_gratis: '<img src="/assets/img/badges/envio_gratis.svg" class="badge-completo" alt="Envío gratis">',
      oferta: '<img src="/assets/img/badges/oferta.svg" class="badge-completo" alt="Oferta">',
      nuevo: '<img src="/assets/img/badges/nuevo.svg" class="badge-completo" alt="Novedad">',
      novedad: '<img src="/assets/img/badges/nuevo.svg" class="badge-completo" alt="Novedad">',
      recomendado: '<img src="/assets/img/badges/recomendado.svg" class="badge-completo" alt="Recomendado">'
    };

    if (p.badges && Array.isArray(p.badges)) {
      p.badges.forEach(badgeKey => {
        if (badgeTemplates[badgeKey]) {
          modalBadgesContainer.innerHTML += badgeTemplates[badgeKey];
        }
      });
    }
  }

  const mainImg = document.getElementById('modalMainImage');
  const thumbsContainer = document.getElementById('modalThumbs');
  const prevBtn = document.getElementById('galleryPrevBtn');
  const nextBtn = document.getElementById('galleryNextBtn');

  thumbsContainer.innerHTML = '';
  currentImages = images || [];
  currentImageIndex = 0;

  if (currentImages.length > 0) {
    mainImg.src = '/uploads/' + currentImages[0].nombre_archivo;
    mainImg.style.opacity = 1;

    if (currentImages.length > 1) {
      prevBtn.style.display = 'flex';
      nextBtn.style.display = 'flex';
    } else {
      prevBtn.style.display = 'none';
      nextBtn.style.display = 'none';
    }

    currentImages.forEach((img, index) => {
      const thumb = document.createElement('img');
      thumb.src = '/uploads/' + img.nombre_archivo;
      thumb.className = `modal-thumb ${index === 0 ? 'active' : ''}`;
      thumb.onclick = () => {
        currentImageIndex = index;
        updateGalleryUI();
      };
      thumbsContainer.appendChild(thumb);
    });
  } else {
    mainImg.src = '/assets/img/no-image.png';
    prevBtn.style.display = 'none';
    nextBtn.style.display = 'none';
  }

  const whatsappBtn = document.getElementById('modalWhatsappBtn');
  // Mensaje optimizado para cierre de venta
  const message = `Hola, estoy interesado en comprar *${p.titulo}*. ¿Sigue disponible? ${currentProductUrl}`;
  const text = encodeURIComponent(message);

  if (p.telefono_vendedor) {
    whatsappBtn.href = `https://wa.me/591${p.telefono_vendedor}?text=${text}`;
    whatsappBtn.style.display = 'flex';
  } else {
    whatsappBtn.style.display = 'none';
  }

  const likeBtn = document.getElementById('modalLikeBtn');
  const likeIcon = likeBtn.querySelector('i');

  if (userHasLiked) {
    likeBtn.classList.add('liked');
    likeIcon.classList.remove('far');
    likeIcon.classList.add('fas');
  } else {
    likeBtn.classList.remove('liked');
    likeIcon.classList.add('far');
    likeIcon.classList.remove('fas');
  }
}

function toggleLikeCurrent() {
  if (!currentProductId) return;

  const likeBtn = document.getElementById('modalLikeBtn');
  const wasLiked = likeBtn.classList.contains('liked');
  const likeIcon = likeBtn.querySelector('i');

  if (wasLiked) {
    likeBtn.classList.remove('liked');
    likeIcon.classList.add('far');
    likeIcon.classList.remove('fas');
  } else {
    likeBtn.classList.add('liked');
    likeIcon.classList.remove('far');
    likeIcon.classList.add('fas');
  }

  fetch('/api/toggle_like.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id: currentProductId })
  })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        const likePill = document.getElementById('grid-likes-' + currentProductId);
        if (likePill) likePill.innerHTML = '<i class="fas fa-heart"></i> ' + data.likes;

        if (data.liked) {
          likeBtn.classList.add('liked');
          likeIcon.classList.remove('far');
          likeIcon.classList.add('fas');
        } else {
          likeBtn.classList.remove('liked');
          likeIcon.classList.add('far');
          likeIcon.classList.remove('fas');
        }
      } else {
        alert('Error al dar like: ' + data.message);
        if (wasLiked) {
          likeBtn.classList.add('liked');
          likeIcon.classList.remove('far');
          likeIcon.classList.add('fas');
        } else {
          likeBtn.classList.remove('liked');
          likeIcon.classList.add('far');
          likeIcon.classList.remove('fas');
        }
      }
    })
    .catch(err => {
      console.error(err);
    });
}

const reportModal = document.getElementById('reportModal');

function closeReportModal() {
  reportModal.classList.remove('active');
  setTimeout(() => reportModal.style.display = 'none', 300);
  document.querySelectorAll('input[name="report_reason"]').forEach(input => input.checked = false);
}

function submitReport() {
  const selected = document.querySelector('input[name="report_reason"]:checked');
  if (!selected) {
    alert('Por favor selecciona un motivo para el reporte.');
    return;
  }

  const motivo = selected.value;

  // Usar variable global tiendaSlug
  const slug = window.tiendaSlug || '';

  fetch('/api/reportar_tienda.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      tienda_slug: slug,
      motivo
    })
  })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        alert('Gracias. Tu reporte ha sido enviado y será revisado por el equipo de Done! Bolivia.');
        closeReportModal();
      } else {
        alert('Error: ' + (data.message || 'No se pudo enviar el reporte'));
      }
    })
    .catch(err => {
      console.error(err);
      alert('Error al enviar el reporte. Por favor, inténtalo de nuevo.');
      closeReportModal();
    });
}

reportModal.addEventListener('click', (e) => {
  if (e.target === reportModal) closeReportModal();
});

/* --- GENERIC SLIDER LOGIC --- */
function initSlider(containerSelector) {
  const container = document.querySelector(containerSelector);
  if (!container) return;

  const track = container.querySelector('.slider-wrapper');
  const slides = container.querySelectorAll('.slide');
  const dotsContainer = container.querySelector('.slider-dots');
  const prevButton = container.querySelector('.slider-arrow.left');
  const nextButton = container.querySelector('.slider-arrow.right');

  if (!track || slides.length === 0) return;

  // Si es un carrusel de scroll, la lógica es diferente y más simple
  if (container.classList.contains('secciones-slider')) {
    let autoScrollInterval;

    const updateArrows = () => {
      const hasOverflow = track.scrollWidth > track.clientWidth;
      if (prevButton && nextButton) {
        prevButton.style.display = hasOverflow ? 'flex' : 'none';
        nextButton.style.display = hasOverflow ? 'flex' : 'none';
      }
    };

    const startAutoScroll = () => {
      stopAutoScroll();
      if (track.scrollWidth <= track.clientWidth) return; // No hacer nada si no hay overflow
      autoScrollInterval = setInterval(() => {
        // Si está cerca del final, vuelve al principio
        if (track.scrollLeft + track.clientWidth >= track.scrollWidth - 1) {
          track.scrollTo({ left: 0, behavior: 'smooth' });
        } else {
          track.scrollBy({ left: 200, behavior: 'smooth' });
        }
      }, 5000);
    };

    const stopAutoScroll = () => {
      clearInterval(autoScrollInterval);
    };

    if (prevButton && nextButton) {
      prevButton.addEventListener('click', () => {
        track.scrollBy({ left: -250, behavior: 'smooth' });
        startAutoScroll(); // Reinicia el timer para que no salte justo después
      });
      nextButton.addEventListener('click', () => {
        track.scrollBy({ left: 250, behavior: 'smooth' });
        startAutoScroll(); // Reinicia el timer
      });
    }

    container.addEventListener('mouseenter', stopAutoScroll);
    container.addEventListener('mouseleave', startAutoScroll);

    // Asegurarnos de que el cálculo se hace cuando todo ha cargado
    window.addEventListener('load', () => {
      updateArrows();
      startAutoScroll();
    });
    // También al inicio por si acaso
    updateArrows();
    startAutoScroll();

    if (dotsContainer) dotsContainer.style.display = 'none';
    return;
  }

  // Lógica para el banner principal (transform-based)
  let currentSlide = 0;
  let sliderInterval;

  function updateSliderUI() {
    track.style.transform = `translateX(-${currentSlide * 100}%)`;
    if (dotsContainer) {
      const dots = dotsContainer.querySelectorAll('.slider-dot');
      dots.forEach((dot, idx) => {
        dot.classList.toggle('active', idx === currentSlide);
      });
    }
  }

  function moveSlide(direction) {
    currentSlide = (currentSlide + direction + slides.length) % slides.length;
    updateSliderUI();
  }

  function goToSlide(index) {
    currentSlide = index;
    updateSliderUI();
    startSlideInterval(); // Reinicia el timer al navegar manualmente
  }

  function startSlideInterval() {
    stopSlideInterval();
    if (slides.length > 1) {
      sliderInterval = setInterval(() => moveSlide(1), 5000);
    }
  }

  function stopSlideInterval() {
    if (sliderInterval) clearInterval(sliderInterval);
  }

  // --- Init ---
  if (dotsContainer && slides.length > 1) {
    dotsContainer.innerHTML = '';
    for (let i = 0; i < slides.length; i++) {
      const dot = document.createElement('span');
      dot.className = 'slider-dot';
      dot.addEventListener('click', () => goToSlide(i));
      dotsContainer.appendChild(dot);
    }
  }

  if (prevButton && nextButton) {
    if (slides.length > 1) {
      prevButton.style.display = 'flex';
      nextButton.style.display = 'flex';
      prevButton.onclick = () => { moveSlide(-1); startSlideInterval(); };
      nextButton.onclick = () => { moveSlide(1); startSlideInterval(); };
    } else {
      prevButton.style.display = 'none';
      nextButton.style.display = 'none';
    }
  }

  container.addEventListener('mouseenter', stopSlideInterval);
  container.addEventListener('mouseleave', startSlideInterval);

  updateSliderUI();
  startSlideInterval();
}

window.refreshSlider = function(images) {
  const container = document.getElementById('heroSliderContainer');
  if (!container) return;
  const track = container.querySelector('.slider-wrapper');
  if (!track) return;
  track.innerHTML = '';
  const validImages = images.filter(img => img && img.trim() !== '');
  if (validImages.length === 0) {
    const placeholder = document.createElement('div');
    placeholder.className = 'slide hero-slide-placeholder';
    placeholder.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg><p>El banner principal de tu tienda se mostrará aquí.</p><p style="font-size: 12px; color: #94a3b8;">Sube una imagen desde el editor para activarlo.</p>';
    track.appendChild(placeholder);
  } else {
    validImages.forEach((img) => {
      const div = document.createElement('div');
      div.className = 'slide';
      div.style.backgroundImage = `url('${img}')`;
      track.appendChild(div);
    });
  }
  // Re-inicializar el slider específico
  initSlider('#heroSliderContainer');
};

// =========================================================================
//  EDITOR COMMUNICATION HANDLER
//  Listens for messages from the parent editor window (editor-tienda.js)
// =========================================================================
window.addEventListener('message', (event) => {
  // Basic security: only accept messages from the same origin
  if (event.origin !== window.location.origin) {
    return;
  }

  const { type, payload } = event.data;

  switch (type) {
  case 'updateVisibility':
    if (payload.target === 'logo') {
      const logoContainer = document.querySelector('.store-logo-container');
      if (logoContainer) {
        logoContainer.style.display = payload.visible ? '' : 'none';
      }
    }
    // El caso para 'nombre' ha sido eliminado para centralizar la lógica en 'updateText'
    break;

  case 'updateLogo': // Handles the "Logo Principal" from the branding section
    const logoImg = document.querySelector('.store-logo-container img');
    if (logoImg) {
      logoImg.src = payload.url || '';
    }
    break;

  case 'updateTheme':
    if (payload.color) {
      document.documentElement.style.setProperty('--primary-color', payload.color);
    }
    if (payload.fondo) {
      document.body.dataset.fondo = payload.fondo;
    }
    if (payload.bordes) {
      document.body.dataset.bordes = payload.bordes;
    }
    if (payload.fuente) {
      document.body.dataset.fuente = payload.fuente;
    }
    if (payload.tamano) {
      document.body.dataset.tamano = payload.tamano;
    }
    if (payload.tarjetas) {
      document.body.dataset.tarjetas = payload.tarjetas;
    }
    // Corregido: Comprobar que la propiedad exista, incluso si es 0
    if (payload.grid !== undefined) {
      let gridTemplateValue = '';
      const gridValue = payload.grid;

      // Tratar el 0 de la DB como 'auto'
      if (gridValue === 'auto' || gridValue === 0) {
        gridTemplateValue = 'repeat(auto-fill, minmax(240px, 1fr))';
      } else if (!isNaN(gridValue) && gridValue > 0) { // Es un número válido (2, 3, 4)
        gridTemplateValue = `repeat(${gridValue}, 1fr)`;
      }

      if (gridTemplateValue) {
        document.documentElement.style.setProperty('--product-grid-template', gridTemplateValue);
      }
    }
    if (payload.banner) {
      if (typeof payload.banner.activo !== 'undefined') {
        const bannerContainer = document.getElementById('heroSliderContainer');
        if (bannerContainer) {
          bannerContainer.style.display = payload.banner.activo ? 'block' : 'none';
          bannerContainer.dataset.userEnabled = payload.banner.activo;
        }
      }
      if (payload.banner.imagenes) {
        if (typeof window.refreshSlider === 'function') {
          window.refreshSlider(payload.banner.imagenes);
        }
      }
    }
    break;

  case 'updateText':
    const el = document.querySelector(payload.selector);
    if (el) {
      el.textContent = payload.text;
      // [FIX FINAL] Usar una clase de utilidad para la visibilidad
      if (typeof payload.visible !== 'undefined') {
        if (payload.visible) {
          el.classList.remove('d-none');
        } else {
          el.classList.add('d-none');
        }
      }
    }
    break;

  case 'scrollTo':
    const section = document.querySelector(payload.selector);
    if (section) {
      section.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    break;

  case 'filterProducts':
    const filter = payload.section || 'todos';
    const menuLink = Array.from(document.querySelectorAll('.menu-item')).find(el => el.textContent.trim().toLowerCase() === filter.toLowerCase());
    filterProducts(filter, menuLink || null);
    break;
  }
});

/* ==========================================================================
   Featured Sections Click Handler
   ========================================================================== */
function handleFeaturedSectionClick(sectionLabel) {
  const normalizedClickLabel = sectionLabel.trim().toLowerCase();
  const menuItems = document.querySelectorAll('.store-menu .menu-item');

  for (const item of menuItems) {
    // The text in the menu is Title-Cased, so we need to normalize it too
    const normalizedMenuLabel = item.textContent.trim().toLowerCase();

    if (normalizedMenuLabel === normalizedClickLabel) {
      // Found the matching menu item, simulate a click
      item.click();
      return; // Exit the function once found and clicked
    }
  }

  console.warn(`handleFeaturedSectionClick: Menu item not found for section "${sectionLabel}"`);
}

document.addEventListener('DOMContentLoaded', () => {
  // Deep Linking from Hash
  const hash = window.location.hash.substring(1); // Quitar #
  if (hash) {
    const searchText = hash.replace(/-/g, ' ');
    const menuItems = document.querySelectorAll('.menu-item');
    let found = false;

    menuItems.forEach(item => {
      if (item.textContent.trim().toLowerCase() === searchText) {
        item.click();
        found = true;
      }
    });

    if (!found && searchText) {
      filterProducts(searchText, null);
    }
  }

  // Handshake with editor
  if (window.self !== window.top) {
    window.parent.postMessage({ type: 'iframeReady' }, window.location.origin);
  }

  // Open modal from URL
  const urlParams = new URLSearchParams(window.location.search);
  const productoId = urlParams.get('producto');

  if (productoId && window.tiendaSlug) {
    openProductModal(window.tiendaSlug, productoId);
  }

  // Init sliders
  initSlider('#heroSliderContainer');
  initSlider('.secciones-slider');
});

window.addEventListener('popstate', (event) => {
  if (isModalHistoryState) {
    closeModalNow();
  }
});
