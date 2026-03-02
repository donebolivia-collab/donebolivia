/**
 * CAMBALACHE - Lazy Loading Optimizado
 * Mejora la carga de imágenes para mejor performance
 */

(function() {
    'use strict';
    
    // Verificar si el navegador soporta Intersection Observer
    if ('IntersectionObserver' in window) {
        // Configuración del observer
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    
                    // Cargar imagen
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                    }
                    
                    // Cargar srcset si existe
                    if (img.dataset.srcset) {
                        img.srcset = img.dataset.srcset;
                        img.removeAttribute('data-srcset');
                    }
                    
                    // Agregar clase loaded
                    img.classList.add('lazy-loaded');
                    
                    // Dejar de observar esta imagen
                    observer.unobserve(img);
                }
            });
        }, {
            root: null,
            rootMargin: '50px', // Comenzar a cargar 50px antes de entrar al viewport
            threshold: 0.01
        });
        
        // Observar todas las imágenes con data-src
        const lazyImages = document.querySelectorAll('img[data-src], img[loading="lazy"]');
        lazyImages.forEach(img => imageObserver.observe(img));
        
    } else {
        // Fallback para navegadores antiguos
        const lazyImages = document.querySelectorAll('img[data-src]');
        lazyImages.forEach(img => {
            if (img.dataset.src) {
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
            }
            if (img.dataset.srcset) {
                img.srcset = img.dataset.srcset;
                img.removeAttribute('data-srcset');
            }
        });
    }
    
    // Placeholder mientras carga
    const style = document.createElement('style');
    style.textContent = `
        img[loading="lazy"]:not(.lazy-loaded) {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s ease-in-out infinite;
        }
        
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        
        img.lazy-loaded {
            animation: fadeIn 0.3s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    `;
    document.head.appendChild(style);
})();
