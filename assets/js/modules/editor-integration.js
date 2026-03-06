/**
 * Editor Integration - Integración del nuevo sistema de comunicación
 * Reemplaza el sistema frágil actual por el robusto
 */

// Inicializar el nuevo sistema de comunicación
let realtimeCommunicator = null;
let isCommunicatorReady = false;

/**
 * Inicializa el sistema de comunicación robusto
 */
function initRealtimeCommunication() {
  console.log('[EditorIntegration] Inicializando comunicación robusta');

  // SIEMPRE inicializar, sin verificar editor_mode
  // El sistema debe funcionar siempre que esté en un iframe

  // Verificar si estamos en un iframe
  if (window.self === window.top) {
    console.log('[EditorIntegration] No estamos en un iframe, omitiendo comunicación');
    return;
  }

  // Crear instancia del comunicador
  realtimeCommunicator = new RealtimeCommunicator({
    maxRetries: 5,
    retryDelay: 1000,
    handshakeTimeout: 5000,
    heartbeatInterval: 30000
  });

  // Configurar callbacks
  setupCommunicatorCallbacks();

  // Marcar como listo
  isCommunicatorReady = true;

  console.log('[EditorIntegration] Comunicación robusta inicializada');
}

/**
 * Configura los callbacks del comunicador
 */
function setupCommunicatorCallbacks() {
  // Callback de conexión exitosa
  realtimeCommunicator.on('onConnect', (data) => {
    console.log('[EditorIntegration] Conexión establecida', data);

    // Sincronizar estado inicial
    syncAllSettings();

    // Actualizar indicador de estado
    updateConnectionIndicator(true);
  });

  // Callback de desconexión
  realtimeCommunicator.on('onDisconnect', () => {
    console.warn('[EditorIntegration] Conexión perdida');

    // Actualizar indicador de estado
    updateConnectionIndicator(false);

    // Mostrar notificación al usuario
    showNotif('Conexión con vista previa perdida. Reintentando...', 'warning');
  });

  // Callback de mensajes
  realtimeCommunicator.on('onMessage', (data) => {
    console.log('[EditorIntegration] Mensaje recibido', data);

    // Manejar mensajes específicos del iframe
    handleIframeMessage(data);
  });

  // Callback de errores
  realtimeCommunicator.on('onError', (data) => {
    console.error('[EditorIntegration] Error de comunicación', data);

    // Mostrar notificación al usuario
    showNotif('Error en la comunicación con la vista previa', 'error');

    // Actualizar indicador
    updateConnectionIndicator(false);
  });

  // Callback de handshake completado
  realtimeCommunicator.on('onHandshakeComplete', (data) => {
    console.log('[EditorIntegration] Handshake completado', data);

    // Sincronizar estado completo
    syncCompleteState();
  });
}

/**
 * Maneja mensajes provenientes del iframe
 */
function handleIframeMessage(data) {
  switch (data.type) {
  case 'syncFromFrame':
    handleSyncFromFrame(data.payload);
    break;

  case 'iframeReady':
    // Este mensaje ahora es manejado por el comunicador
    console.log('[EditorIntegration] iframeReady recibido (legacy)');
    break;

  default:
    console.log('[EditorIntegration] Mensaje no manejado:', data.type);
    break;
  }
}

/**
 * Maneja la sincronización desde el iframe
 */
function handleSyncFromFrame(payload) {
  const { field, value } = payload;

  // Actualizar el campo correspondiente en el editor
  const elementMap = {
    descripcion: 'storeDescription',
    nombre: 'storeName'
  };

  const elementId = elementMap[field];
  if (elementId) {
    const element = document.getElementById(elementId);
    if (element && element.value !== value) {
      element.value = value;
      markUnsaved();
    }
  }
}

/**
 * Envía un mensaje al iframe usando el nuevo sistema
 */
function postToFrameRobust(type, payload) {
  if (!isCommunicatorReady || !realtimeCommunicator) {
    console.warn('[EditorIntegration] Comunicador no listo, encolando mensaje');

    // Encolar mensaje para cuando esté listo
    setTimeout(() => {
      postToFrameRobust(type, payload);
    }, 100);

    return false;
  }

  return realtimeCommunicator.sendMessage(type, payload);
}

/**
 * Sincroniza toda la configuración inicial
 */
function syncCompleteState() {
  console.log('[EditorIntegration] Sincronizando estado completo');

  // Sincronizar tema completo
  postToFrameRobust('updateTheme', {
    color: window.tiendaState.color,
    fondo: window.tiendaState.estiloFondo,
    bordes: window.tiendaState.estiloBordes,
    fuente: window.tiendaState.tipografia,
    tamano: window.tiendaState.tamanoTexto,
    tarjetas: window.tiendaState.estiloTarjetas,
    grid: window.tiendaState.gridDensity,
    banner: window.tiendaState.banner,
    seccionesDestacadas: window.tiendaState.seccionesDestacadas,
    fotos: window.tiendaState.estiloFotos
  });

  // Sincronizar texto
  const nameInput = document.getElementById('storeName');
  if (nameInput) {
    postToFrameRobust('updateText', {
      selector: '.store-name',
      text: nameInput.value,
      visible: window.tiendaState.mostrar_nombre
    });
  }

  // Sincronizar logo
  postToFrameRobust('updateLogoState', {
    visible: !!window.tiendaState.mostrar_logo,
    url: window.tiendaState.logo_principal ? `/uploads/logos/${window.tiendaState.logo_principal}` : null
  });

  // Sincronizar contacto
  syncContact();

  // Sincronizar menú
  if (window.menuItems) {
    postToFrameRobust('updateMenu', { items: window.menuItems });
  }
}

/**
 * Actualiza el indicador de conexión (eliminado - sistema silencioso)
 */
function updateConnectionIndicator(isConnected) {
  // Sistema funciona silenciosamente sin indicador visual
  console.log(`[EditorIntegration] Estado de conexión: ${isConnected ? 'Conectado' : 'Desconectado'}`);
}

/**
 * Reemplaza las funciones antiguas por las nuevas
 */
function replaceLegacyFunctions() {
  console.log('[EditorIntegration] Reemplazando funciones legacy');

  // Guardar referencia a la función original
  const originalPostToFrame = window.postToFrame;

  // Reemplazar con la nueva función robusta
  window.postToFrame = function(type, payload) {
    console.log(`[EditorIntegration] postToFrame llamado: ${type}`);

    // Usar el nuevo sistema
    if (isCommunicatorReady) {
      return postToFrameRobust(type, payload);
    } else {
      // Fallback al sistema antiguo si el nuevo no está listo
      console.warn('[EditorIntegration] Usando fallback a sistema antiguo');
      return originalPostToFrame ? originalPostToFrame.call(this, type, payload) : false;
    }
  };

  // Reemplazar syncAllSettings
  const originalSyncAllSettings = window.syncAllSettings;
  window.syncAllSettings = function() {
    if (isCommunicatorReady && realtimeCommunicator) {
      syncCompleteState();
    } else if (originalSyncAllSettings) {
      originalSyncAllSettings.call(this);
    }
  };
}

/**
 * Inicializa todo el sistema de comunicación
 */
function initCommunicationSystem() {
  console.log('[EditorIntegration] Inicializando sistema completo de comunicación');

  // NO agregar indicador de conexión - el sistema funciona silenciosamente

  // Inicializar comunicador robusto
  initRealtimeCommunication();

  // Reemplazar funciones legacy
  replaceLegacyFunctions();

  // Configurar fallback para el sistema antiguo
  setupLegacyFallback();

  console.log('[EditorIntegration] Sistema de comunicación inicializado completamente');
}

/**
 * Configura fallback al sistema antiguo
 */
function setupLegacyFallback() {
  // Si después de 10 segundos el nuevo sistema no está listo, usar el antiguo
  setTimeout(() => {
    if (!isCommunicatorReady) {
      console.warn('[EditorIntegration] Nuevo sistema no listo, usando fallback');

      // Usar sistema antiguo
      const storeFrame = document.getElementById('storeFrame');
      if (storeFrame) {
        storeFrame.onload = function() {
          console.log('[EditorIntegration] Fallback: iframe cargado');
          window.isFrameReady = true;
          syncAllSettings();
        };
      }
    }
  }, 10000);
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
  // Esperar un poco a que el sistema principal se inicialice
  setTimeout(initCommunicationSystem, 500);
});

// Exponer funciones globalmente
window.initCommunicationSystem = initCommunicationSystem;
window.postToFrameRobust = postToFrameRobust;
window.getConnectionState = function() {
  return realtimeCommunicator ? realtimeCommunicator.getConnectionState() : null;
};
