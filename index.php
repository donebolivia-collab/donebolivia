<?php
$titulo = "Inicio";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/components.php';
?>

<div class="text-center my-3">
  <img src="/assets/img/done.png" alt="Done!" class="yx-hero-logo">
</div>

<form action="/products/search.php" method="GET" class="yx-search">
  <div style="position: relative; width: 100%;">
    <input
      id="searchInput"
      type="text"
      name="q"
      class="pill"
      placeholder="Buscar en Done!"
      value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>"
      style="padding-right: 50px;">
    
    <button type="button" id="voiceSearchBtn" class="voice-search-btn" style="display: none;" title="Buscar por voz">
      <i class="fas fa-microphone"></i>
      <span class="voice-ripple"></span>
    </button>
  </div>
</form>

<style>
/* Estilos Micrófono Search */
.voice-search-btn {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #999;
    font-size: 1.2rem;
    cursor: pointer;
    transition: all 0.3s ease;
    z-index: 10;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}

.voice-search-btn:hover {
    color: #ff6b1a; /* var(--primary) */
    background-color: rgba(255, 107, 26, 0.1);
}

.voice-search-btn.listening {
    color: #ff6b1a;
    background-color: rgba(255, 107, 26, 0.1);
}

.voice-ripple {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 100%;
    height: 100%;
    border-radius: 50%;
    border: 2px solid #ff6b1a;
    opacity: 0;
    pointer-events: none;
}

.voice-search-btn.listening .voice-ripple {
    animation: ripple-effect 1.5s infinite;
}

@keyframes ripple-effect {
    0% {
        width: 100%;
        height: 100%;
        opacity: 0.8;
    }
    100% {
        width: 180%;
        height: 180%;
        opacity: 0;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const voiceBtn = document.getElementById('voiceSearchBtn');
    
    // Verificar soporte de Web Speech API
    if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        const recognition = new SpeechRecognition();
        
        recognition.continuous = false;
        recognition.interimResults = false;
        recognition.lang = 'es-BO'; // Español Bolivia (o es-ES)

        // Mostrar el botón solo si hay soporte
        voiceBtn.style.display = 'flex';

        voiceBtn.addEventListener('click', function() {
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
                searchInput.placeholder = "Buscar en Done!";
            }
        };

        recognition.onresult = function(event) {
            const transcript = event.results[0][0].transcript;
            searchInput.value = transcript;
            // Opcional: Auto-submit
            setTimeout(() => {
                searchInput.form.submit();
            }, 500);
        };

        recognition.onerror = function(event) {
            console.error('Error de reconocimiento de voz:', event.error);
            voiceBtn.classList.remove('listening');
            searchInput.placeholder = "Error. Intenta escribir.";
        };
    }
});
</script>

<section class="yx-cats yx-cats--compact">
  <?php
    $cats = obtenerCategorias();
    foreach ($cats as $cat):
      $name = $cat['nombre'];
      $ico = getCategoryIcon($name);
      $href = '/products/category.php?id=' . urlencode($cat['id']);
      $slug = getCategorySlug($name);
      
      echo renderCategoryCard($name, $ico, $href, $slug);
    endforeach;
  ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
