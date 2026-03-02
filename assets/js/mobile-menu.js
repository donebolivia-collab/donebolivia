/**
 * MENÚ HAMBURGUESA MÓVIL - CAMBALACHE
 * Manejo del menú lateral para dispositivos móviles
 */

(function() {
    'use strict';
    
    // Elementos
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const closeMenuBtn = document.getElementById('closeMenuBtn');
    const mobileMenu = document.getElementById('mobileSideMenu');
    const menuOverlay = document.getElementById('mobileMenuOverlay');
    
    // Verificar que los elementos existen
    if (!hamburgerBtn || !closeMenuBtn || !mobileMenu || !menuOverlay) {
        return;
    }
    
    /**
     * Abrir menú
     */
    function openMenu() {
        mobileMenu.classList.add('active');
        menuOverlay.classList.add('active');
        document.body.style.overflow = 'hidden'; // Prevenir scroll del body
    }
    
    /**
     * Cerrar menú
     */
    function closeMenu() {
        mobileMenu.classList.remove('active');
        menuOverlay.classList.remove('active');
        document.body.style.overflow = ''; // Restaurar scroll
    }
    
    // Event listeners
    hamburgerBtn.addEventListener('click', openMenu);
    closeMenuBtn.addEventListener('click', closeMenu);
    menuOverlay.addEventListener('click', closeMenu);
    
    // Cerrar menú al hacer clic en un link (excepto el CTA)
    const menuItems = mobileMenu.querySelectorAll('.mobile-menu-item:not(.cta-item)');
    menuItems.forEach(item => {
        item.addEventListener('click', () => {
            // Pequeño delay para que se vea la animación antes de navegar
            setTimeout(closeMenu, 200);
        });
    });
    
    // Cerrar menú con tecla ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && mobileMenu.classList.contains('active')) {
            closeMenu();
        }
    });
    
    // Prevenir scroll en el body cuando el menú está abierto
    mobileMenu.addEventListener('touchmove', (e) => {
        if (mobileMenu.classList.contains('active')) {
            e.stopPropagation();
        }
    }, { passive: false });
    
})();
