/**
 * Iframe Communicator Module
 * Gestión completa de comunicación entre editor y iframe de preview
 * Extraído de editor-tienda.js para mejor mantenibilidad
 */

// Namespace seguro para el módulo
const IframeCommunicator = (function() {
  'use strict';

  // Variables privadas del módulo
  let storeFrame = null;
  let isFrameReady = false;

  /**
     * Inicializa el sistema de comunicación con iframe
     */
  function init() {
    // Obtener referencia al iframe
    storeFrame = document.getElementById('storeFrame');

    if (!storeFrame) {
      console.warn('IframeCommunicator: storeFrame no encontrado');
      return false;
    }

    // Escuchar mensajes del iframe
    window.addEventListener('message', handleMessage);

    console.log('IframeCommunicator: Inicializado correctamente');
    return true;
  }

  /**
     * Maneja los mensajes recibidos del iframe
     * @param {MessageEvent} event - Evento del mensaje
     */
  function handleMessage(event) {
    // Verificación de seguridad básica
    if (event.source !== storeFrame.contentWindow) {
      return;
    }

    const data = event.data;

    // Manejar diferentes tipos de mensajes
    switch (data.type) {
    case 'iframeReady':
      console.log('Editor: Iframe está listo. Sincronizando...');
      isFrameReady = true;
      syncAllSettings();
      break;

    case 'syncFromFrame':
      handleSyncFromFrame(data);
      break;

    default:
      console.log('IframeCommunicator: Tipo de mensaje no manejado:', data.type);
    }
  }

  /**
     * Maneja la sincronización desde el iframe
     * @param {Object} data - Datos de sincronización
     */
  function handleSyncFromFrame(data) {
    const { field, value } = data;
    const elementIdMap = {
      descripcion: 'storeDescription',
      nombre: 'storeName'
    };

    const el = document.getElementById(elementIdMap[field]);
    if (el && el.value !== value) {
      el.value = value;

      // Marcar como no guardado si existe la función
      if (typeof markUnsaved === 'function') {
        markUnsaved();
      }
    }
  }

  /**
     * Envía un mensaje al iframe
     * @param {string} type - Tipo de mensaje
     * @param {Object} payload - Datos a enviar
     */
  function postToFrame(type, payload) {
    if (!isFrameReady || !storeFrame?.contentWindow) {
      console.warn('IframeCommunicator: Iframe no listo para comunicación');
      return false;
    }

    storeFrame.contentWindow.postMessage({ type, payload }, window.location.origin);
    return true;
  }

  /**
     * Sincroniza todas las configuraciones con el iframe
     */
  function syncAllSettings() {
    if (!isFrameReady) {
      console.warn('IframeCommunicator: Iframe no listo para sincronizar');
      return false;
    }

    // Verificar que window.tiendaState exista
    if (!window.tiendaState) {
      console.warn('IframeCommunicator: tiendaState no disponible');
      return false;
    }

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

    // Sincronizar nombre de tienda
    const nameInput = document.getElementById('storeName');
    if (nameInput) {
      postToFrame('updateText', {
        selector: '.store-name',
        text: nameInput.value,
        visible: window.tiendaState.mostrar_nombre
      });
    }

    // Sincronizar logo
    postToFrame('updateLogoState', {
      visible: !!window.tiendaState.mostrar_logo,
      url: window.tiendaState.logo_principal ? `/uploads/logos/${window.tiendaState.logo_principal}` : null
    });

    console.log('IframeCommunicator: Configuraciones sincronizadas');
    return true;
  }

  /**
     * Actualiza textos en el iframe
     * @param {string} selector - Selector CSS
     * @param {string} text - Texto a actualizar
     * @param {boolean} visible - Visibilidad del texto
     */
  function updateText(selector, text, visible = true) {
    return postToFrame('updateText', { selector, text, visible });
  }

  /**
     * Actualiza el tema del iframe
     * @param {Object} themeData - Datos del tema
     */
  function updateTheme(themeData) {
    return postToFrame('updateTheme', themeData);
  }

  /**
     * Actualiza el estado del logo
     * @param {boolean} visible - Visibilidad del logo
     * @param {string} url - URL del logo
     */
  function updateLogoState(visible, url) {
    return postToFrame('updateLogoState', { visible, url });
  }

  /**
     * Actualiza el contacto en el iframe
     * @param {Object} contactData - Datos de contacto
     */
  function updateContact(contactData) {
    return postToFrame('updateContact', contactData);
  }

  /**
     * Verifica si el sistema está inicializado
     * @returns {boolean}
     */
  function isInitialized() {
    return storeFrame !== null;
  }

  /**
     * Verifica si el iframe está listo
     * @returns {boolean}
     */
  function checkFrameReady() {
    return isFrameReady;
  }

  /**
     * Fuerza el estado del iframe a listo
     * @param {boolean} ready - Estado de listo
     */
  function setFrameReady(ready = true) {
    isFrameReady = ready;
    console.log(`IframeCommunicator: Frame ${ready ? 'listo' : 'no listo'}`);
  }

  /**
     * Destruye el sistema de comunicación
     */
  function destroy() {
    window.removeEventListener('message', handleMessage);
    storeFrame = null;
    isFrameReady = false;
    console.log('IframeCommunicator: Destruido correctamente');
  }

  // API pública del módulo
  return {
    init,
    postToFrame,
    syncAllSettings,
    updateText,
    updateTheme,
    updateLogoState,
    updateContact,
    isInitialized,
    checkFrameReady,
    setFrameReady,
    destroy
  };
})();

// Exponer globalmente para compatibilidad con código existente
window.IframeCommunicator = IframeCommunicator;
window.postToFrame = IframeCommunicator.postToFrame;
window.syncAllSettings = IframeCommunicator.syncAllSettings;
