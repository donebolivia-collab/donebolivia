/**
 * Iframe Integration - Integración del receptor en el iframe
 * Inicializa el sistema robusto de comunicación en la vista previa
 */

// Variable global para el receptor
let iframeReceiver = null;
let isReceiverReady = false;

/**
 * Inicializa el sistema de comunicación del iframe
 */
function initIframeCommunication() {
  console.log('[IframeIntegration] Inicializando comunicación del iframe');

  // Verificar si estamos en modo editor
  if (!window.location.search.includes('editor_mode=1')) {
    console.log('[IframeIntegration] No estamos en modo editor, omitiendo comunicación');
    return;
  }

  // Crear instancia del receptor
  iframeReceiver = new IframeReceiver({
    handshakeTimeout: 5000,
    heartbeatInterval: 30000
  });

  // Configurar callbacks
  setupReceiverCallbacks();

  // Marcar como listo
  isReceiverReady = true;

  console.log('[IframeIntegration] Comunicación del iframe inicializada');
}

/**
 * Configura los callbacks del receptor
 */
function setupReceiverCallbacks() {
  // Callback de conexión
  iframeReceiver.on('onConnect', (data) => {
    console.log('[IframeIntegration] Conexión establecida con editor', data);

    // Enviar señal de listo al editor (legacy)
    if (window.parent && window.parent !== window.self) {
      window.parent.postMessage({
        type: 'iframeReady',
        timestamp: Date.now()
      }, window.location.origin);
    }

    // Actualizar estado visual
    updateEditorModeIndicator(true);
  });

  // Callback de desconexión
  iframeReceiver.on('onDisconnect', () => {
    console.warn('[IframeIntegration] Desconectado del editor');
    updateEditorModeIndicator(false);
  });

  // Callback de mensajes personalizados
  iframeReceiver.on('onMessage', (data) => {
    console.log('[IframeIntegration] Mensaje personalizado recibido', data);

    // Aquí se pueden manejar mensajes específicos si es necesario
    handleCustomEditorMessage(data);
  });

  // Callback de errores
  iframeReceiver.on('onError', (data) => {
    console.error('[IframeIntegration] Error en comunicación', data);

    // Mostrar indicador de error
    updateEditorModeIndicator(false, true);
  });
}

/**
 * Maneja mensajes personalizados del editor
 */
function handleCustomEditorMessage(data) {
  switch (data.type) {
  case 'EDITOR_MODE_TOGGLE':
    toggleEditModeIndicator(data.payload.active);
    break;

  case 'HIGHLIGHT_ELEMENT':
    highlightEditableElement(data.payload.selector);
    break;

  case 'CLEAR_HIGHLIGHTS':
    clearEditableHighlights();
    break;

  default:
    // Otros mensajes personalizados
    break;
  }
}

/**
 * Actualiza el indicador de modo editor
 */
function updateEditorModeIndicator(isConnected, hasError = false) {
  let indicator = document.getElementById('editorModeIndicator');

  if (!indicator) {
    indicator = createEditorModeIndicator();
  }

  // Actualizar estado visual
  indicator.className = `editor-mode-indicator ${isConnected ? 'connected' : ''} ${hasError ? 'error' : ''}`;
  indicator.title = isConnected ? 'Modo editor activo' : 'Modo editor desconectado';

  // Actualizar texto
  const statusText = indicator.querySelector('.status-text');
  if (statusText) {
    statusText.textContent = hasError ? 'Error' : (isConnected ? 'Editor' : 'Conectando...');
  }
}

/**
 * Crea el indicador de modo editor
 */
function createEditorModeIndicator() {
  const indicator = document.createElement('div');
  indicator.id = 'editorModeIndicator';
  indicator.className = 'editor-mode-indicator';
  indicator.innerHTML = `
        <i class="fas fa-edit"></i>
        <span class="status-text">Editor</span>
    `;

  // Agregar estilos
  const style = document.createElement('style');
  style.textContent = `
        .editor-mode-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            z-index: 10000;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .editor-mode-indicator.connected {
            background: rgba(40, 167, 69, 0.9);
            border-color: rgba(40, 167, 69, 0.3);
        }
        
        .editor-mode-indicator.error {
            background: rgba(220, 53, 69, 0.9);
            border-color: rgba(220, 53, 69, 0.3);
            animation: shake 0.5s ease-in-out;
        }
        
        .editor-mode-indicator i {
            font-size: 10px;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        /* Ocultar en móviles para no interferir */
        @media (max-width: 768px) {
            .editor-mode-indicator {
                top: 10px;
                right: 10px;
                padding: 6px 10px;
                font-size: 11px;
            }
        }
    `;
  document.head.appendChild(style);

  // Agregar al body
  document.body.appendChild(indicator);

  return indicator;
}

/**
 * Alterna el indicador de modo editor
 */
function toggleEditModeIndicator(active) {
  const indicator = document.getElementById('editorModeIndicator');
  if (indicator) {
    indicator.style.display = active ? 'flex' : 'none';
  }
}

/**
 * Resalta un elemento editable
 */
function highlightEditableElement(selector) {
  // Limpiar resaltados anteriores
  clearEditableHighlights();

  const element = document.querySelector(selector);
  if (element) {
    element.classList.add('editor-highlight');

    // Hacer scroll hacia el elemento
    element.scrollIntoView({ behavior: 'smooth', block: 'center' });

    // Remover resaltado después de 3 segundos
    setTimeout(() => {
      element.classList.remove('editor-highlight');
    }, 3000);
  }
}

/**
 * Limpia todos los resaltados editables
 */
function clearEditableHighlights() {
  document.querySelectorAll('.editor-highlight').forEach(element => {
    element.classList.remove('editor-highlight');
  });
}

/**
 * Configura elementos editables en modo editor
 */
function setupEditableElements() {
  // Agregar clases editables a elementos relevantes
  const editableSelectors = [
    '.store-name',
    '.about-description-text'
    // '.store-navbar' - Eliminado para evitar línea segmentada en hover
  ];

  editableSelectors.forEach(selector => {
    const elements = document.querySelectorAll(selector);
    elements.forEach(element => {
      element.classList.add('editor-editable');
    });
  });

  // Agregar estilos para elementos editables
  const style = document.createElement('style');
  style.textContent = `
        .editor-editable {
            position: relative;
            transition: all 0.2s ease;
        }
        
        .editor-editable:hover {
            outline: 2px dashed var(--color-widget, #1a73e8);
            outline-offset: 2px;
            border-radius: 4px;
        }
        
        .editor-highlight {
            background-color: rgba(26, 115, 232, 0.1);
            outline: 2px solid var(--color-widget, #1a73e8);
            outline-offset: 2px;
            border-radius: 4px;
            animation: editorPulse 2s ease-in-out;
        }
        
        @keyframes editorPulse {
            0% { background-color: rgba(26, 115, 232, 0.1); }
            50% { background-color: rgba(26, 115, 232, 0.2); }
            100% { background-color: rgba(26, 115, 232, 0.1); }
        }
    `;
  document.head.appendChild(style);
}

/**
 * Envía mensaje al editor usando el nuevo sistema
 */
function sendMessageToEditor(type, payload) {
  if (!isReceiverReady || !iframeReceiver) {
    console.warn('[IframeIntegration] Receptor no listo');
    return false;
  }

  return iframeReceiver.sendMessage(type, payload);
}

/**
 * Inicializa todo el sistema cuando el DOM esté listo
 */
function initIframeSystem() {
  console.log('[IframeIntegration] Inicializando sistema completo del iframe');

  // SIEMPRE inicializar si estamos en un iframe
  // No depender de editor_mode para que el sistema funcione siempre

  // Verificar si estamos en un iframe
  if (window.self === window.top) {
    console.log('[IframeIntegration] No estamos en un iframe');
    return;
  }

  // Inicializar comunicación
  initIframeCommunication();

  // Configurar elementos editables
  setupEditableElements();

  // Enviar señal de listo inmediatamente (no esperar)
  setTimeout(() => {
    // Señal legacy para compatibilidad
    if (window.parent && window.parent !== window.self) {
      window.parent.postMessage({
        type: 'iframeReady',
        timestamp: Date.now()
      }, window.location.origin);
    }

    // Señal nueva si el receptor está listo
    if (isReceiverReady && iframeReceiver) {
      iframeReceiver.sendMessage('READY_SIGNAL', {
        timestamp: Date.now(),
        url: window.location.href,
        userAgent: navigator.userAgent
      });
    }
  }, 500);

  console.log('[IframeIntegration] Sistema del iframe inicializado completamente');
}

// Inicializar cuando el DOM esté listo
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initIframeSystem);
} else {
  initIframeSystem();
}

// Exponer funciones globalmente
window.sendMessageToEditor = sendMessageToEditor;
window.initIframeSystem = initIframeSystem;
window.isIframeReceiverReady = () => isReceiverReady;
