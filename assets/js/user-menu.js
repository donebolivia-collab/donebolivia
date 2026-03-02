// Menú desplegable de usuario (extraído de header.php)
document.addEventListener('DOMContentLoaded', function() {
  const menuBtn = document.getElementById('userMenuBtn');
  const dropdownMenu = document.getElementById('userDropdownMenu');

  if (menuBtn && dropdownMenu) {
    // Toggle menú al hacer click
    menuBtn.addEventListener('click', function(e) {
      e.stopPropagation();
      const isExpanded = menuBtn.getAttribute('aria-expanded') === 'true';
      if (isExpanded) closeMenu(); else openMenu();
    });

    // Cerrar al hacer click fuera
    document.addEventListener('click', function(e) {
      if (!menuBtn.contains(e.target) && !dropdownMenu.contains(e.target)) {
        closeMenu();
      }
    });

    // Cerrar con tecla Escape
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') closeMenu();
    });

    function openMenu() {
      menuBtn.setAttribute('aria-expanded', 'true');
      dropdownMenu.classList.add('show');
    }

    function closeMenu() {
      menuBtn.setAttribute('aria-expanded', 'false');
      dropdownMenu.classList.remove('show');
    }
  }
});
