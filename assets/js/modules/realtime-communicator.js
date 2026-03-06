/**
 * Realtime Communication System - Comunicación Robusta Editor-Visor
 * Solución definitiva al problema del "Gato de Schrödinger"
 */

class RealtimeCommunicator {
  constructor(options = {}) {
    // Configuración
    this.config = {
      maxRetries: options.maxRetries || 5,
      retryDelay: options.retryDelay || 1000,
      handshakeTimeout: options.handshakeTimeout || 5000,
      heartbeatInterval: options.heartbeatInterval || 30000,
      ...options
    };

    // Estado de comunicación
    this.state = {
      isConnected: false,
      isHandshakeComplete: false,
      retryCount: 0,
      lastHeartbeat: null,
      connectionId: this.generateConnectionId()
    };

    // Cola de mensajes pendientes
    this.messageQueue = [];
    this.processedMessages = new Set();

    // Callbacks
    this.callbacks = {
      onConnect: [],
      onDisconnect: [],
      onMessage: [],
      onError: [],
      onHandshakeComplete: []
    };

    // Referencias a elementos
    this.iframe = null;
    this.targetWindow = null;

    // Timers
    this.timers = {
      handshake: null,
      heartbeat: null,
      retry: null
    };

    this.init();
  }

  /**
     * Inicializa el sistema de comunicación
     */
  init() {
    // Solo log en modo desarrollo
    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
      console.log(`[RealtimeCommunicator] Inicializando conexión ${this.state.connectionId}`);
    }

    this.setupMessageListener();
    this.setupIframeDetection();
    this.startHandshake();
  }

  /**
     * Configura el listener de mensajes con seguridad mejorada
     */
  setupMessageListener() {
    window.addEventListener('message', this.handleMessage.bind(this));
  }

  /**
     * Detecta cuando el iframe está disponible
     */
  setupIframeDetection() {
    // MutationObserver para detectar cuando el iframe se agrega al DOM
    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
          if (node.nodeName === 'IFRAME' && node.id === 'storeFrame') {
            this.onIframeDetected(node);
          }
        });
      });
    });

    observer.observe(document.body, {
      childList: true,
      subtree: true
    });

    // Verificar si el iframe ya existe
    const existingIframe = document.getElementById('storeFrame');
    if (existingIframe) {
      this.onIframeDetected(existingIframe);
    }
  }

  /**
     * Se ejecuta cuando se detecta el iframe
     */
  onIframeDetected(iframe) {
    console.log('[RealtimeCommunicator] Iframe detectado');
    this.iframe = iframe;

    // Esperar a que el iframe esté completamente cargado
    if (iframe.contentWindow) {
      this.targetWindow = iframe.contentWindow;
      this.startHandshake();
    } else {
      iframe.addEventListener('load', () => {
        this.targetWindow = iframe.contentWindow;
        this.startHandshake();
      });
    }
  }

  /**
     * Inicia el proceso de handshake robusto
     */
  startHandshake() {
    if (!this.targetWindow) {
      console.warn('[RealtimeCommunicator] Target window no disponible');
      return;
    }

    console.log('[RealtimeCommunicator] Iniciando handshake');
    this.resetHandshake();

    // Enviar mensaje de handshake
    this.sendMessage('HANDSHAKE_REQUEST', {
      connectionId: this.state.connectionId,
      timestamp: Date.now(),
      origin: window.location.origin
    });

    // Configurar timeout para el handshake
    this.timers.handshake = setTimeout(() => {
      if (!this.state.isHandshakeComplete) {
        this.onHandshakeTimeout();
      }
    }, this.config.handshakeTimeout);
  }

  /**
     * Maneja los mensajes entrantes
     */
  handleMessage(event) {
    // Validación de seguridad mejorada
    if (!this.validateMessage(event)) {
      return;
    }

    const { type, payload, messageId } = event.data;

    // Evitar procesar mensajes duplicados
    if (messageId && this.processedMessages.has(messageId)) {
      return;
    }

    if (messageId) {
      this.processedMessages.add(messageId);
    }

    console.log(`[RealtimeCommunicator] Mensaje recibido: ${type}`, payload);

    switch (type) {
    case 'HANDSHAKE_RESPONSE':
      this.onHandshakeResponse(payload);
      break;

    case 'HEARTBEAT_RESPONSE':
      this.onHeartbeatResponse(payload);
      break;

    case 'CONNECTION_LOST':
      this.onConnectionLost();
      break;

    default:
      this.triggerCallbacks('onMessage', { type, payload, event });
      break;
    }
  }

  /**
     * Valida la seguridad del mensaje
     */
  validateMessage(event) {
    // Verificar origen
    if (event.origin !== window.location.origin && !this.isAllowedOrigin(event.origin)) {
      console.warn('[RealtimeCommunicator] Origen no permitido:', event.origin);
      return false;
    }

    // Verificar source
    if (this.targetWindow && event.source !== this.targetWindow) {
      console.warn('[RealtimeCommunicator] Source no coincide con target window');
      return false;
    }

    // Verificar estructura
    if (!event.data || typeof event.data !== 'object') {
      console.warn('[RealtimeCommunicator] Estructura de mensaje inválida');
      return false;
    }

    return true;
  }

  /**
     * Maneja la respuesta del handshake
     */
  onHandshakeResponse(payload) {
    if (this.state.isHandshakeComplete) {
      return; // Ya completado
    }

    console.log('[RealtimeCommunicator] Handshake completado');

    this.clearTimer('handshake');
    this.state.isHandshakeComplete = true;
    this.state.isConnected = true;
    this.state.retryCount = 0;

    // Iniciar heartbeat
    this.startHeartbeat();

    // Procesar cola de mensajes pendientes
    this.processMessageQueue();

    // Notificar conexión exitosa
    this.triggerCallbacks('onConnect', { payload });
    this.triggerCallbacks('onHandshakeComplete', { payload });
  }

  /**
     * Maneja el timeout del handshake
     */
  onHandshakeTimeout() {
    console.warn('[RealtimeCommunicator] Handshake timeout');

    if (this.state.retryCount < this.config.maxRetries) {
      this.state.retryCount++;
      console.log(`[RealtimeCommunicator] Reintentando handshake (${this.state.retryCount}/${this.config.maxRetries})`);

      this.timers.retry = setTimeout(() => {
        this.startHandshake();
      }, this.config.retryDelay * this.state.retryCount);
    } else {
      this.triggerCallbacks('onError', {
        type: 'HANDSHAKE_TIMEOUT',
        message: 'No se pudo establecer conexión después de múltiples intentos'
      });
    }
  }

  /**
     * Inicia el sistema de heartbeat
     */
  startHeartbeat() {
    this.clearTimer('heartbeat');

    this.timers.heartbeat = setInterval(() => {
      this.sendMessage('HEARTBEAT_REQUEST', {
        timestamp: Date.now()
      });
    }, this.config.heartbeatInterval);
  }

  /**
     * Maneja la respuesta del heartbeat
     */
  onHeartbeatResponse(payload) {
    this.state.lastHeartbeat = Date.now();
    console.log('[RealtimeCommunicator] Heartbeat recibido');
  }

  /**
     * Envía un mensaje con sistema de cola y reintento
     */
  sendMessage(type, payload, options = {}) {
    const message = {
      type,
      payload,
      messageId: this.generateMessageId(),
      timestamp: Date.now(),
      connectionId: this.state.connectionId,
      ...options
    };

    // Si no hay conexión, agregar a la cola
    if (!this.state.isConnected || !this.targetWindow) {
      if (options.queue !== false) {
        this.messageQueue.push(message);
        console.log(`[RealtimeCommunicator] Mensaje encolado: ${type}`);
      }
      return false;
    }

    try {
      this.targetWindow.postMessage(message, window.location.origin);
      console.log(`[RealtimeCommunicator] Mensaje enviado: ${type}`);
      return true;
    } catch (error) {
      console.error('[RealtimeCommunicator] Error enviando mensaje:', error);

      if (options.queue !== false) {
        this.messageQueue.push(message);
      }

      this.triggerCallbacks('onError', {
        type: 'SEND_ERROR',
        message: error.message,
        originalMessage: message
      });

      return false;
    }
  }

  /**
     * Procesa la cola de mensajes pendientes
     */
  processMessageQueue() {
    if (this.messageQueue.length === 0) {
      return;
    }

    console.log(`[RealtimeCommunicator] Procesando ${this.messageQueue.length} mensajes pendientes`);

    const queue = [...this.messageQueue];
    this.messageQueue = [];

    queue.forEach(message => {
      this.sendMessage(message.type, message.payload, { queue: false });
    });
  }

  /**
     * Maneja la pérdida de conexión
     */
  onConnectionLost() {
    console.warn('[RealtimeCommunicator] Conexión perdida');

    this.state.isConnected = false;
    this.state.isHandshakeComplete = false;

    this.clearTimer('heartbeat');

    this.triggerCallbacks('onDisconnect');

    // Intentar reconexión automática
    this.attemptReconnection();
  }

  /**
     * Intenta reconexión automática
     */
  attemptReconnection() {
    if (this.state.retryCount >= this.config.maxRetries) {
      console.error('[RealtimeCommunicator] Máximo número de reintentos alcanzado');
      return;
    }

    this.state.retryCount++;
    console.log(`[RealtimeCommunicator] Intentando reconexión (${this.state.retryCount}/${this.config.maxRetries})`);

    this.timers.retry = setTimeout(() => {
      this.startHandshake();
    }, this.config.retryDelay * this.state.retryCount);
  }

  /**
     * Registra callbacks para eventos
     */
  on(event, callback) {
    if (this.callbacks[event]) {
      this.callbacks[event].push(callback);
    }
  }

  /**
     * Elimina callbacks
     */
  off(event, callback) {
    if (this.callbacks[event]) {
      const index = this.callbacks[event].indexOf(callback);
      if (index > -1) {
        this.callbacks[event].splice(index, 1);
      }
    }
  }

  /**
     * Dispara los callbacks de un evento
     */
  triggerCallbacks(event, data) {
    if (this.callbacks[event]) {
      this.callbacks[event].forEach(callback => {
        try {
          callback(data);
        } catch (error) {
          console.error(`[RealtimeCommunicator] Error en callback ${event}:`, error);
        }
      });
    }
  }

  /**
     * Limpia un timer específico
     */
  clearTimer(name) {
    if (this.timers[name]) {
      clearTimeout(this.timers[name]);
      clearInterval(this.timers[name]);
      this.timers[name] = null;
    }
  }

  /**
     * Resetea el estado del handshake
     */
  resetHandshake() {
    this.clearTimer('handshake');
    this.state.isHandshakeComplete = false;
  }

  /**
     * Genera un ID único de conexión
     */
  generateConnectionId() {
    return 'conn_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
  }

  /**
     * Genera un ID único de mensaje
     */
  generateMessageId() {
    return 'msg_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
  }

  /**
     * Verifica si un origen está permitido
     */
  isAllowedOrigin(origin) {
    // En desarrollo, permitir oríinos locales
    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
      return origin.startsWith('http://localhost') || origin.startsWith('http://127.0.0.1');
    }

    // En producción, verificar contra el dominio actual
    return origin === window.location.origin;
  }

  /**
     * Obtiene el estado actual de la conexión
     */
  getConnectionState() {
    return {
      ...this.state,
      queueLength: this.messageQueue.length,
      config: this.config
    };
  }

  /**
     * Destruye el comunicador
     */
  destroy() {
    console.log('[RealtimeCommunicator] Destruyendo comunicador');

    // Limpiar timers
    Object.keys(this.timers).forEach(timer => this.clearTimer(timer));

    // Limpiar listener
    window.removeEventListener('message', this.handleMessage);

    // Limpiar estado
    this.state.isConnected = false;
    this.state.isHandshakeComplete = false;
    this.messageQueue = [];
    this.processedMessages.clear();

    // Limpiar callbacks
    Object.keys(this.callbacks).forEach(event => {
      this.callbacks[event] = [];
    });
  }
}

// Exponer globalmente
window.RealtimeCommunicator = RealtimeCommunicator;
