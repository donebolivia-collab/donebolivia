/**
 * Debugging Script - Para diagnosticar problemas de comunicación
 * Ejecutar en consola del navegador
 */

function debugCommunicationSystem() {
    console.log('🔍 INICIANDO DIAGNÓSTICO DEL SISTEMA DE COMUNICACIÓN');
    console.log('=====================================');
    
    // 1. Verificar si los scripts cargaron
    console.log('📦 Scripts cargados:');
    console.log('  RealtimeCommunicator:', typeof window.RealtimeCommunicator);
    console.log('  IframeReceiver:', typeof window.IframeReceiver);
    console.log('  EditorIntegration:', typeof window.EditorIntegration);
    console.log('  IframeIntegration:', typeof window.IframeIntegration);
    
    // 2. Verificar si estamos en modo editor
    const isEditorMode = window.location.search.includes('editor_mode=1');
    console.log('📝 Modo editor:', isEditorMode);
    
    // 3. Verificar elementos del DOM
    console.log('🏗️ Elementos del DOM:');
    console.log('  storeFrame:', !!document.getElementById('storeFrame'));
    console.log('  connectionIndicator:', !!document.getElementById('connectionIndicator'));
    
    // 4. Verificar estado del comunicador
    if (window.getConnectionState) {
        const state = window.getConnectionState();
        console.log('📊 Estado del comunicador:', state);
    }
    
    // 5. Verificar si el iframe está accesible
    const iframe = document.getElementById('storeFrame');
    if (iframe) {
        console.log('🖼️ Estado del iframe:');
        console.log('  src:', iframe.src);
        console.log('  contentWindow:', !!iframe.contentWindow);
        console.log('  readyState:', iframe.readyState);
        console.log('  loaded:', iframe.complete);
    }
    
    // 6. Intentar comunicación manual
    console.log('🔄 Probando comunicación manual...');
    
    if (iframe && iframe.contentWindow) {
        try {
            iframe.contentWindow.postMessage({
                type: 'DEBUG_TEST',
                timestamp: Date.now()
            }, window.location.origin);
            console.log('✅ Mensaje de prueba enviado');
        } catch (error) {
            console.error('❌ Error enviando mensaje:', error);
        }
    } else {
        console.error('❌ No se puede acceder al contentWindow del iframe');
    }
    
    // 7. Verificar listeners de mensajes
    console.log('👂 Listeners activos:');
    console.log('  message listeners:', window.addEventListener.toString().includes('message'));
    
    // 8. Recomendaciones
    console.log('💡 RECOMENDACIONES:');
    if (typeof window.RealtimeCommunicator === 'undefined') {
        console.log('  ❌ RealtimeCommunicator no cargó - recarga la página');
    }
    if (typeof window.IframeReceiver === 'undefined' && isEditorMode) {
        console.log('  ❌ IframeReceiver no cargó - el iframe no tiene el nuevo sistema');
    }
    if (!iframe) {
        console.log('  ❌ Iframe no encontrado - problema en el HTML');
    }
    if (iframe && !iframe.contentWindow) {
        console.log('  ❌ contentWindow no accesible -可能是跨域问题');
    }
    
    console.log('=====================================');
    console.log('🏁 DIAGNÓSTICO COMPLETADO');
}

// Función para limpiar y reiniciar el sistema
function resetCommunicationSystem() {
    console.log('🔄 REINICIANDO SISTEMA DE COMUNICACIÓN');
    
    // Destruir comunicador existente
    if (window.realtimeCommunicator) {
        window.realtimeCommunicator.destroy();
    }
    
    // Limpiar indicadores
    const indicator = document.getElementById('connectionIndicator');
    if (indicator) {
        indicator.remove();
    }
    
    // Recrear indicador
    setTimeout(() => {
        if (window.initCommunicationSystem) {
            window.initCommunicationSystem();
        }
    }, 1000);
}

// Función para probar handshake manualmente
function testManualHandshake() {
    console.log('🤝 PROBANDO HANDSHAKE MANUAL');
    
    const iframe = document.getElementById('storeFrame');
    if (!iframe || !iframe.contentWindow) {
        console.error('❌ Iframe no disponible');
        return;
    }
    
    // Enviar mensaje de handshake
    iframe.contentWindow.postMessage({
        type: 'HANDSHAKE_REQUEST',
        connectionId: 'manual_test_' + Date.now(),
        timestamp: Date.now(),
        origin: window.location.origin
    }, window.location.origin);
    
    console.log('✅ Handshake manual enviado');
}

// Función para monitorear mensajes
function startMessageMonitoring() {
    console.log('👂 INICIANDO MONITOREO DE MENSAJES');
    
    // Agregar listener para ver todos los mensajes
    window.addEventListener('message', function(event) {
        console.log('📨 Mensaje recibido:', {
            type: event.data?.type,
            origin: event.origin,
            source: event.source === document.getElementById('storeFrame')?.contentWindow ? 'iframe' : 'other',
            timestamp: new Date().toISOString()
        });
    });
}

// Ejecutar diagnóstico automáticamente
debugCommunicationSystem();

// Exponer funciones globalmente
window.debugCommunicationSystem = debugCommunicationSystem;
window.resetCommunicationSystem = resetCommunicationSystem;
window.testManualHandshake = testManualHandshake;
window.startMessageMonitoring = startMessageMonitoring;

console.log('🔧 Herramientas de debugging cargadas. Usa:');
console.log('  debugCommunicationSystem() - Diagnóstico completo');
console.log('  resetCommunicationSystem() - Reiniciar sistema');
console.log('  testManualHandshake() - Probar handshake manual');
console.log('  startMessageMonitoring() - Monitorear mensajes');
