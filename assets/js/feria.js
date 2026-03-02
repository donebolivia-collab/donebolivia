/**
 * FERIA VIRTUAL - LOGIC (DRY & PERFORMANCE)
 * Manejo de Modales, Búsqueda por Voz y UI
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // 1. BÚSQUEDA POR VOZ (Web Speech API)
    const searchInput = document.getElementById('searchInputFeria');
    const voiceBtn = document.getElementById('voiceSearchBtnFeria');
    
    if (searchInput && voiceBtn && ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window)) {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        const recognition = new SpeechRecognition();
        
        recognition.continuous = false;
        recognition.interimResults = false;
        recognition.lang = 'es-BO';

        voiceBtn.style.display = 'flex';

        voiceBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (voiceBtn.classList.contains('listening')) {
                recognition.stop();
            } else {
                recognition.start();
            }
        });

        recognition.onstart = function() {
            voiceBtn.classList.add('listening');
            searchInput.placeholder = "Escuchando...";
        };

        recognition.onend = function() {
            voiceBtn.classList.remove('listening');
            if (searchInput.value === '') {
                searchInput.placeholder = "Buscar tienda...";
            }
        };

        recognition.onresult = function(event) {
            const transcript = event.results[0][0].transcript;
            searchInput.value = transcript;
            setTimeout(() => { searchInput.form.submit(); }, 500);
        };

        recognition.onerror = function(event) {
            console.error('Error de voz:', event.error);
            voiceBtn.classList.remove('listening');
            searchInput.placeholder = "Error. Intenta escribir.";
        };
    }

    // 2. SELECTOR DE DEPARTAMENTOS
    const trigger = document.getElementById('deptTrigger');
    const menu = document.getElementById('deptMenu');
    
    if (trigger && menu) {
        trigger.onclick = function(e) {
            e.stopPropagation();
            e.preventDefault();
            menu.classList.toggle('show-menu');
        };

        document.body.onclick = function(e) {
            if (!menu.contains(e.target) && !trigger.contains(e.target)) {
                menu.classList.remove('show-menu');
            }
        };
    }

    // 3. MANEJO DE MODALES
    window.closeFeriaModal = function() {
        const modal = document.getElementById('feriaModal');
        if(modal) modal.classList.remove('active');
    };

    window.openFeriaModal = function(sectorCode, sectorName, posIndex) {
        const modal = document.getElementById('feriaModal');
        const states = document.querySelectorAll('.modal-state');
        
        if(!modal) return;

        // Ocultar todos los estados
        states.forEach(el => el.style.display = 'none');
        
        // Variables globales inyectadas desde PHP en window.feriaConfig
        const { isLoggedIn, hasStore, storeName, currentCity } = window.feriaConfig;

        if (!isLoggedIn) {
            document.getElementById('modal-guest').style.display = 'block';
        } else if (!hasStore) {
            const btnCreate = document.getElementById('btn-create-store');
            btnCreate.href = `/mi/crear_tienda.php?feria_sector=${sectorCode}&feria_city=${currentCity}&feria_pos=${posIndex}`;
            document.getElementById('modal-user').style.display = 'block';
        } else {
            document.getElementById('owner-store-name').textContent = storeName;
            document.getElementById('target-sector-name').textContent = sectorName;
            
            document.getElementById('input-sector').value = sectorCode;
            document.getElementById('input-city').value = currentCity;
            document.getElementById('input-pos').value = posIndex;
            
            document.getElementById('modal-owner').style.display = 'block';
        }

        modal.classList.add('active');
    };

    // Cerrar modal al clickear afuera
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('feria-modal-overlay')) {
            closeFeriaModal();
        }
    });
});
