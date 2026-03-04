/**
 * Iframe Receiver - Sistema de comunicación del lado del iframe
 * Responde al comunicador del editor con handshake robusto
 */

class IframeReceiver {
    constructor(options = {}) {
        this.config = {
            handshakeTimeout: options.handshakeTimeout || 5000,
            heartbeatInterval: options.heartbeatInterval || 30000,
            ...options
        };

        this.state = {
            isConnected: false,
            connectionId: null,
            lastHeartbeat: null
        };

        this.parentWindow = null;
        this.timers = {
            heartbeat: null
        };

        this.callbacks = {
            onConnect: [],
            onDisconnect: [],
            onMessage: [],
            onError: []
        };

        this.init();
    }

    /**
     * Inicializa el receptor del iframe
     */
    init() {
        console.log('[IframeReceiver] Inicializando receptor');
        
        // Verificar si estamos en un iframe
        if (window.self === window.top) {
            console.warn('[IframeReceiver] No estamos en un iframe');
            return;
        }

        this.parentWindow = window.parent;
        this.setupMessageListener();
        this.sendReady();
    }

    /**
     * Configura el listener de mensajes
     */
    setupMessageListener() {
        window.addEventListener('message', this.handleMessage.bind(this));
    }

    /**
     * Envía señal de listo al padre
     */
    sendReady() {
        console.log('[IframeReceiver] Enviando señal de listo');
        
        this.sendMessage('READY_SIGNAL', {
            timestamp: Date.now(),
            url: window.location.href,
            userAgent: navigator.userAgent
        });
    }

    /**
     * Maneja los mensajes entrantes
     */
    handleMessage(event) {
        // Validación de seguridad
        if (!this.validateMessage(event)) {
            return;
        }

        const { type, payload, messageId, connectionId } = event.data;

        console.log(`[IframeReceiver] Mensaje recibido: ${type}`, payload);

        switch (type) {
            case 'HANDSHAKE_REQUEST':
                this.onHandshakeRequest(payload, messageId, connectionId);
                break;
                
            case 'HEARTBEAT_REQUEST':
                this.onHeartbeatRequest(payload, messageId);
                break;
                
            case 'READY_SIGNAL':
                // No debería recibir esto, pero lo manejamos por si acaso
                break;
                
            default:
                this.handleCustomMessage(type, payload, messageId);
                break;
        }
    }

    /**
     * Valida la seguridad del mensaje
     */
    validateMessage(event) {
        // Verificar que venga del padre
        if (event.source !== this.parentWindow) {
            console.warn('[IframeReceiver] Mensaje no viene del padre');
            return false;
        }

        // Verificar origen
        if (event.origin !== window.location.origin && !this.isSameOrigin(event)) {
            console.warn('[IframeReceiver] Origen no permitido:', event.origin);
            return false;
        }

        // Verificar estructura
        if (!event.data || typeof event.data !== 'object') {
            console.warn('[IframeReceiver] Estructura de mensaje inválida');
            return false;
        }

        return true;
    }

    /**
     * Verifica si es el mismo origen (considerando subdominios)
     */
    isSameOrigin(event) {
        try {
            const parentOrigin = new URL(event.origin);
            const currentOrigin = new URL(window.location.origin);
            
            return parentOrigin.hostname === currentOrigin.hostname;
        } catch (error) {
            return false;
        }
    }

    /**
     * Maneja la solicitud de handshake
     */
    onHandshakeRequest(payload, messageId, connectionId) {
        console.log('[IframeReceiver] Handshake request recibido');
        
        this.state.connectionId = connectionId;
        this.state.isConnected = true;

        // Responder al handshake
        this.sendMessage('HANDSHAKE_RESPONSE', {
            connectionId: connectionId,
            timestamp: Date.now(),
            status: 'ready',
            url: window.location.href
        }, messageId);

        // Iniciar heartbeat
        this.startHeartbeat();

        // Notificar conexión
        this.triggerCallbacks('onConnect', { payload, connectionId });
    }

    /**
     * Maneja la solicitud de heartbeat
     */
    onHeartbeatRequest(payload, messageId) {
        this.state.lastHeartbeat = Date.now();
        
        this.sendMessage('HEARTBEAT_RESPONSE', {
            timestamp: this.state.lastHeartbeat,
            status: 'alive'
        }, messageId);
    }

    /**
     * Maneja mensajes personalizados
     */
    handleCustomMessage(type, payload, messageId) {
        console.log(`[IframeReceiver] Procesando mensaje personalizado: ${type}`);
        
        // Aquí se manejan todos los mensajes del editor
        switch (type) {
            case 'updateTheme':
                this.updateTheme(payload);
                break;
                
            case 'updateText':
                this.updateText(payload);
                break;
                
            case 'updateLogoState':
                this.updateLogoState(payload);
                break;
                
            case 'updateContact':
                this.updateContact(payload);
                break;
                
            case 'updateMenu':
                this.updateMenu(payload);
                break;
                
            case 'previewProduct':
                this.previewProduct(payload);
                break;
                
            case 'scrollTo':
                this.scrollToElement(payload);
                break;
                
            case 'filterProducts':
                this.filterProducts(payload);
                break;
                
            case 'setNavbarStyle':
                this.setNavbarStyle(payload);
                break;
                
            default:
                console.warn(`[IframeReceiver] Tipo de mensaje no manejado: ${type}`);
                break;
        }

        // Notificar a callbacks personalizados
        this.triggerCallbacks('onMessage', { type, payload, messageId });
    }

    /**
     * Actualiza el tema de la tienda
     */
    updateTheme(payload) {
        console.log('[IframeReceiver] Actualizando tema', payload);
        
        const root = document.documentElement.style;

        // Color
        if (payload.color) {
            root.setProperty('--color-widget', payload.color);
            
            // Calcular RGB para opacidad
            const hex = payload.color.replace('#', '');
            const r = parseInt(hex.substring(0,2), 16);
            const g = parseInt(hex.substring(2,4), 16);
            const b = parseInt(hex.substring(4,6), 16);
            const rgb = `${r}, ${g}, ${b}`;
            
            root.setProperty('--color-widget-rgb', rgb);
            
            // Actualizar colores derivados
            const opacityElement = document.querySelector('[data-default-opacity]');
            const defaultOpacity = opacityElement ? parseFloat(opacityElement.dataset.defaultOpacity) : 0.12;
            
            root.setProperty('--btn-bg-hover', `rgba(${rgb}, ${defaultOpacity})`);
            root.setProperty('--btn-bg-active', `rgba(${rgb}, ${defaultOpacity})`);
        }

        // Fondo
        if (payload.fondo) {
            this.updateBackground(payload.fondo);
        }

        // Bordes
        if (payload.bordes) {
            this.updateBorders(payload.bordes);
        }

        // Fuente
        if (payload.fuente) {
            this.updateFont(payload.fuente);
        }

        // Tamaño de texto
        if (payload.tamano) {
            this.updateTextSize(payload.tamano);
        }

        // Tarjetas
        if (payload.tarjetas) {
            this.updateCardStyle(payload.tarjetas);
        }

        // Grid
        if (payload.grid) {
            this.updateGridDensity(payload.grid);
        }

        // Banner
        if (payload.banner) {
            this.updateBanner(payload.banner);
        }

        // Secciones destacadas
        if (payload.seccionesDestacadas) {
            this.updateFeaturedSections(payload.seccionesDestacadas);
        }

        // Fotos
        if (payload.fotos) {
            this.updatePhotoStyle(payload.fotos);
        }
    }

    /**
     * Actualiza el fondo
     */
    updateBackground(fondo) {
        const body = document.body;
        
        // Limpiar clases de fondo existentes
        body.classList.remove('bg-tintado', 'bg-gris');
        
        switch (fondo) {
            case 'tintado':
                body.classList.add('bg-tintado');
                break;
            case 'gris':
                body.classList.add('bg-gris');
                break;
            default:
                // Blanco por defecto
                break;
        }
    }

    /**
     * Actualiza los bordes
     */
    updateBorders(bordes) {
        const root = document.documentElement.style;
        
        let rBtn = '8px', rCard = '12px';
        
        switch (bordes) {
            case 'recto':
                rBtn = '0px';
                rCard = '0px';
                break;
            case 'pill':
                rBtn = '50px';
                rCard = '24px';
                break;
            default:
                // Suave/redondeado por defecto
                break;
        }
        
        root.setProperty('--border-radius-btn', rBtn);
        root.setProperty('--border-radius-card', rCard);
    }

    /**
     * Actualiza la fuente
     */
    updateFont(fuente) {
        const body = document.body;
        
        // Limpiar clases de fuente existentes
        body.classList.remove('font-inter', 'font-jakarta', 'font-manrope', 'font-modern', 'font-tech', 'font-minimal', 'font-classic', 'font-bold', 'font-outfit');
        
        const fontMap = {
            'inter': 'font-inter',
            'jakarta': 'font-jakarta',
            'manrope': 'font-manrope',
            'modern': 'font-modern',
            'tech': 'font-tech',
            'minimal': 'font-minimal',
            'classic': 'font-classic',
            'bold': 'font-bold',
            'outfit': 'font-outfit'
        };
        
        if (fontMap[fuente]) {
            body.classList.add(fontMap[fuente]);
        }
    }

    /**
     * Actualiza el tamaño del texto
     */
    updateTextSize(tamano) {
        const body = document.body;
        
        // Limpiar clases de tamaño existentes
        body.classList.remove('size-small', 'size-large');
        
        if (tamano === 'small') {
            body.classList.add('size-small');
        } else if (tamano === 'large') {
            body.classList.add('size-large');
        }
        // 'normal' es el por defecto
    }

    /**
     * Actualiza el estilo de las tarjetas
     */
    updateCardStyle(tarjetas) {
        const root = document.documentElement.style;
        
        let shadow = '0 10px 30px rgba(0,0,0,0.08)';
        let border = '1px solid var(--border-color)';
        
        switch (tarjetas) {
            case 'flat':
                shadow = 'none';
                border = '1px solid var(--border-color)';
                break;
            case 'borde':
                shadow = 'none';
                border = '2px solid var(--border-color)';
                break;
            case 'elevada':
                border = 'none';
                break;
            default:
                break;
        }
        
        root.setProperty('--card-shadow', shadow);
        root.setProperty('--card-border', border);
    }

    /**
     * Actualiza la densidad de la cuadrícula
     */
    updateGridDensity(grid) {
        const root = document.documentElement.style;
        
        if (grid === 'auto') {
            root.setProperty('--product-grid-template', 'repeat(auto-fill, minmax(240px, 1fr))');
        } else {
            root.setProperty('--product-grid-template', `repeat(${grid}, 1fr)`);
        }
    }

    /**
     * Actualiza el banner
     */
    updateBanner(banner) {
        const bannerContainer = document.getElementById('heroSliderContainer');
        
        if (bannerContainer) {
            if (banner.activo) {
                bannerContainer.style.display = 'block';
            } else {
                bannerContainer.style.display = 'none';
            }
        }
    }

    /**
     * Actualiza las secciones destacadas
     */
    updateFeaturedSections(secciones) {
        // Implementar según necesites
        console.log('[IframeReceiver] Actualizando secciones destacadas', secciones);
    }

    /**
     * Actualiza el estilo de las fotos
     */
    updatePhotoStyle(fotos) {
        const root = document.documentElement.style;
        
        let aspect = '1 / 1';
        let fit = 'cover';
        
        switch (fotos) {
            case 'vertical':
                aspect = '3 / 4';
                break;
            case 'horizontal':
                aspect = '4 / 3';
                break;
            case 'natural':
                aspect = '1 / 1';
                fit = 'contain';
                break;
            default:
                // Cuadrado por defecto
                break;
        }
        
        root.setProperty('--img-aspect-ratio', aspect);
        root.setProperty('--img-object-fit', fit);
    }

    /**
     * Actualiza texto específico
     */
    updateText(payload) {
        const element = document.querySelector(payload.selector);
        if (element) {
            if (payload.text !== undefined) {
                element.textContent = payload.text;
            }
            if (payload.visible !== undefined) {
                element.style.display = payload.visible ? 'block' : 'none';
            }
        }
    }

    /**
     * Actualiza el estado del logo
     */
    updateLogoState(payload) {
        const logoContainer = document.getElementById('principalLogoContainer');
        const logoImage = document.getElementById('principalLogoImage');
        const logoPlaceholder = document.getElementById('principalLogoPlaceholder');
        
        if (logoContainer) {
            logoContainer.style.display = payload.visible ? 'flex' : 'none';
        }
        
        if (payload.url && logoImage) {
            logoImage.src = payload.url;
            logoImage.style.display = 'block';
        }
        
        if (logoPlaceholder) {
            logoPlaceholder.style.display = payload.url ? 'none' : 'flex';
        }
    }

    /**
     * Actualiza la información de contacto
     */
    updateContact(payload) {
        // Implementar según necesites
        console.log('[IframeReceiver] Actualizando contacto', payload);
    }

    /**
     * Actualiza el menú
     */
    updateMenu(payload) {
        // Implementar según necesites
        console.log('[IframeReceiver] Actualizando menú', payload);
    }

    /**
     * Previsualiza un producto
     */
    previewProduct(payload) {
        const ghostCard = document.getElementById('ghostCard');
        
        if (ghostCard) {
            if (payload.active) {
                // Mostrar y actualizar datos
                ghostCard.style.display = 'block';
                
                const titleElement = document.getElementById('ghostTitle');
                const priceElement = document.getElementById('ghostPrice');
                const imgElement = document.getElementById('ghostImg');
                
                if (titleElement) titleElement.textContent = payload.titulo || 'Nuevo Producto';
                if (priceElement) priceElement.textContent = payload.precio ? `Bs. ${payload.precio}` : 'Bs. 0.00';
                if (imgElement && payload.imgUrl) imgElement.src = payload.imgUrl;
                
                // Hacer scroll hacia la ghost card
                setTimeout(() => {
                    ghostCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 100);
            } else {
                // Ocultar
                ghostCard.style.display = 'none';
            }
        }
    }

    /**
     * Hace scroll a un elemento
     */
    scrollToElement(payload) {
        const element = document.querySelector(payload.selector);
        if (element) {
            element.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    /**
     * Filtra productos
     */
    filterProducts(payload) {
        // Implementar filtrado de productos
        console.log('[IframeReceiver] Filtrando productos', payload);
    }

    /**
     * Establece el estilo de la navbar
     */
    setNavbarStyle(payload) {
        const navbar = document.querySelector('.store-navbar');
        
        if (navbar) {
            // Limpiar clases existentes
            navbar.classList.remove('navbar-marca', 'navbar-blanco');
            
            if (payload.style === 'marca') {
                navbar.classList.add('navbar-marca');
            } else {
                navbar.classList.add('navbar-blanco');
            }
        }
    }

    /**
     * Inicia el sistema de heartbeat
     */
    startHeartbeat() {
        this.clearTimer('heartbeat');
        
        this.timers.heartbeat = setInterval(() => {
            // El padre envía heartbeat, nosotros solo respondemos
        }, this.config.heartbeatInterval);
    }

    /**
     * Envía un mensaje al padre
     */
    sendMessage(type, payload, replyToMessageId = null) {
        if (!this.parentWindow) {
            console.warn('[IframeReceiver] Parent window no disponible');
            return false;
        }

        const message = {
            type,
            payload,
            messageId: this.generateMessageId(),
            timestamp: Date.now(),
            connectionId: this.state.connectionId
        };

        // Si es respuesta, agregar referencia
        if (replyToMessageId) {
            message.replyTo = replyToMessageId;
        }

        try {
            this.parentWindow.postMessage(message, window.location.origin);
            console.log(`[IframeReceiver] Mensaje enviado: ${type}`);
            return true;
        } catch (error) {
            console.error('[IframeReceiver] Error enviando mensaje:', error);
            return false;
        }
    }

    /**
     * Registra callbacks
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
     * Dispara callbacks
     */
    triggerCallbacks(event, data) {
        if (this.callbacks[event]) {
            this.callbacks[event].forEach(callback => {
                try {
                    callback(data);
                } catch (error) {
                    console.error(`[IframeReceiver] Error en callback ${event}:`, error);
                }
            });
        }
    }

    /**
     * Limpia un timer
     */
    clearTimer(name) {
        if (this.timers[name]) {
            clearTimeout(this.timers[name]);
            clearInterval(this.timers[name]);
            this.timers[name] = null;
        }
    }

    /**
     * Genera ID único de mensaje
     */
    generateMessageId() {
        return 'iframe_msg_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    /**
     * Destruye el receptor
     */
    destroy() {
        console.log('[IframeReceiver] Destruyendo receptor');
        
        // Limpiar timers
        Object.keys(this.timers).forEach(timer => this.clearTimer(timer));
        
        // Limpiar listener
        window.removeEventListener('message', this.handleMessage);
        
        // Limpiar estado
        this.state.isConnected = false;
        
        // Limpiar callbacks
        Object.keys(this.callbacks).forEach(event => {
            this.callbacks[event] = [];
        });
    }
}

// Exponer globalmente
window.IframeReceiver = IframeReceiver;
