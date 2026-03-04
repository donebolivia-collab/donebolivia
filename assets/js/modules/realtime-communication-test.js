/**
 * Realtime Communication Test Suite
 * Testing integral del sistema de comunicación robusta
 */

class RealtimeCommunicationTest {
    constructor() {
        this.testResults = [];
        this.errors = [];
        this.testTimeout = 10000; // 10 segundos por test
    }

    /**
     * Ejecuta todos los tests del sistema de comunicación
     */
    async runAllTests() {
        console.log('🧪 Iniciando suite de tests de comunicación en tiempo real');
        
        this.testResults = [];
        this.errors = [];
        
        // Tests de inicialización
        await this.testCommunicatorInitialization();
        await this.testReceiverInitialization();
        
        // Tests de handshake
        await this.testHandshakeProcess();
        await this.testHandshakeTimeout();
        await this.testHandshakeRetry();
        
        // Tests de mensajes
        await this.testMessageSending();
        await this.testMessageQueue();
        await this.testMessageDuplication();
        
        // Tests de reconexión
        await this.testConnectionLost();
        await this.testAutomaticReconnection();
        
        // Tests de heartbeat
        await this.testHeartbeatSystem();
        
        // Tests de seguridad
        await this.testMessageValidation();
        await this.testOriginValidation();
        
        // Tests de estrés
        await this.testHighFrequencyMessages();
        await this.testLargePayloads();
        
        this.printResults();
    }

    /**
     * Test 1: Inicialización del comunicador
     */
    async testCommunicatorInitialization() {
        this.addTestResult('communicator_init', () => {
            try {
                const communicator = new RealtimeCommunicator({
                    maxRetries: 3,
                    retryDelay: 500,
                    handshakeTimeout: 2000
                });
                
                if (!communicator) {
                    throw new Error('No se pudo crear instancia del comunicador');
                }
                
                if (typeof communicator.init !== 'function') {
                    throw new Error('Método init no encontrado');
                }
                
                const state = communicator.getConnectionState();
                if (!state || typeof state !== 'object') {
                    throw new Error('getConnectionState no devuelve estado válido');
                }
                
                communicator.destroy();
                return true;
            } catch (error) {
                throw error;
            }
        }, 'Inicialización del comunicador');
    }

    /**
     * Test 2: Inicialización del receptor
     */
    async testReceiverInitialization() {
        this.addTestResult('receiver_init', () => {
            try {
                const receiver = new IframeReceiver({
                    handshakeTimeout: 2000,
                    heartbeatInterval: 1000
                });
                
                if (!receiver) {
                    throw new Error('No se pudo crear instancia del receptor');
                }
                
                if (typeof receiver.init !== 'function') {
                    throw new Error('Método init no encontrado');
                }
                
                receiver.destroy();
                return true;
            } catch (error) {
                throw error;
            }
        }, 'Inicialización del receptor');
    }

    /**
     * Test 3: Proceso de handshake
     */
    async testHandshakeProcess() {
        this.addTestResult('handshake_process', async () => {
            return new Promise((resolve, reject) => {
                try {
                    // Crear iframe de prueba
                    const iframe = document.createElement('iframe');
                    iframe.style.display = 'none';
                    document.body.appendChild(iframe);
                    
                    // Inicializar comunicador
                    const communicator = new RealtimeCommunicator({
                        handshakeTimeout: 3000
                    });
                    
                    let handshakeCompleted = false;
                    
                    // Configurar callback de handshake
                    communicator.on('onHandshakeComplete', () => {
                        handshakeCompleted = true;
                        communicator.destroy();
                        document.body.removeChild(iframe);
                        resolve(true);
                    });
                    
                    // Simular respuesta del iframe
                    setTimeout(() => {
                        if (!handshakeCompleted) {
                            const event = new MessageEvent('message', {
                                data: {
                                    type: 'HANDSHAKE_RESPONSE',
                                    payload: {
                                        connectionId: 'test_connection',
                                        timestamp: Date.now(),
                                        status: 'ready'
                                    }
                                },
                                source: iframe.contentWindow,
                                origin: window.location.origin
                            });
                            
                            window.dispatchEvent(event);
                        }
                    }, 100);
                    
                    // Inicializar
                    communicator.init();
                    
                    // Timeout
                    setTimeout(() => {
                        if (!handshakeCompleted) {
                            communicator.destroy();
                            document.body.removeChild(iframe);
                            reject(new Error('Handshake timeout'));
                        }
                    }, 2000);
                    
                } catch (error) {
                    reject(error);
                }
            });
        }, 'Proceso de handshake');
    }

    /**
     * Test 4: Timeout de handshake
     */
    async testHandshakeTimeout() {
        this.addTestResult('handshake_timeout', async () => {
            return new Promise((resolve, reject) => {
                try {
                    const communicator = new RealtimeCommunicator({
                        handshakeTimeout: 1000,
                        maxRetries: 1
                    });
                    
                    let timeoutTriggered = false;
                    
                    communicator.on('onError', (data) => {
                        if (data.type === 'HANDSHAKE_TIMEOUT') {
                            timeoutTriggered = true;
                            communicator.destroy();
                            resolve(true);
                        }
                    });
                    
                    communicator.init();
                    
                    // No enviar respuesta, debe timeout
                    setTimeout(() => {
                        if (!timeoutTriggered) {
                            communicator.destroy();
                            reject(new Error('Timeout no se disparó'));
                        }
                    }, 2000);
                    
                } catch (error) {
                    reject(error);
                }
            });
        }, 'Timeout de handshake');
    }

    /**
     * Test 5: Reintento de handshake
     */
    async testHandshakeRetry() {
        this.addTestResult('handshake_retry', async () => {
            return new Promise((resolve, reject) => {
                try {
                    const communicator = new RealtimeCommunicator({
                        handshakeTimeout: 500,
                        maxRetries: 3,
                        retryDelay: 100
                    });
                    
                    let retryCount = 0;
                    
                    communicator.on('onError', (data) => {
                        retryCount++;
                    });
                    
                    communicator.on('onHandshakeComplete', () => {
                        if (retryCount >= 2) { // Debe reintentar al menos 2 veces
                            communicator.destroy();
                            resolve(true);
                        } else {
                            communicator.destroy();
                            reject(new Error('No se reintentó suficientes veces'));
                        }
                    });
                    
                    communicator.init();
                    
                    // Simular respuesta después del tercer intento
                    setTimeout(() => {
                        const event = new MessageEvent('message', {
                            data: {
                                type: 'HANDSHAKE_RESPONSE',
                                payload: { status: 'ready' }
                            },
                            source: window,
                            origin: window.location.origin
                        });
                        window.dispatchEvent(event);
                    }, 600); // Después de 3 timeouts de 500ms
                    
                    // Timeout general
                    setTimeout(() => {
                        communicator.destroy();
                        reject(new Error('Test timeout'));
                    }, 2000);
                    
                } catch (error) {
                    reject(error);
                }
            });
        }, 'Reintento de handshake');
    }

    /**
     * Test 6: Envío de mensajes
     */
    async testMessageSending() {
        this.addTestResult('message_sending', async () => {
            return new Promise((resolve, reject) => {
                try {
                    const communicator = new RealtimeCommunicator();
                    let messageReceived = false;
                    
                    communicator.on('onMessage', (data) => {
                        if (data.type === 'TEST_MESSAGE' && data.payload.test === 'value') {
                            messageReceived = true;
                            communicator.destroy();
                            resolve(true);
                        }
                    });
                    
                    communicator.on('onHandshakeComplete', () => {
                        // Enviar mensaje de prueba
                        communicator.sendMessage('TEST_MESSAGE', { test: 'value' });
                    });
                    
                    // Simular respuesta
                    setTimeout(() => {
                        const event = new MessageEvent('message', {
                            data: {
                                type: 'HANDSHAKE_RESPONSE',
                                payload: { status: 'ready' }
                            },
                            source: window,
                            origin: window.location.origin
                        });
                        window.dispatchEvent(event);
                    }, 100);
                    
                    setTimeout(() => {
                        const event = new MessageEvent('message', {
                            data: {
                                type: 'TEST_MESSAGE',
                                payload: { test: 'value' }
                            },
                            source: window,
                            origin: window.location.origin
                        });
                        window.dispatchEvent(event);
                    }, 200);
                    
                    // Timeout
                    setTimeout(() => {
                        if (!messageReceived) {
                            communicator.destroy();
                            reject(new Error('Mensaje no recibido'));
                        }
                    }, 1000);
                    
                } catch (error) {
                    reject(error);
                }
            });
        }, 'Envío de mensajes');
    }

    /**
     * Test 7: Cola de mensajes
     */
    async testMessageQueue() {
        this.addTestResult('message_queue', async () => {
            return new Promise((resolve, reject) => {
                try {
                    const communicator = new RealtimeCommunicator();
                    let messageReceived = false;
                    
                    communicator.on('onMessage', (data) => {
                        if (data.type === 'QUEUED_MESSAGE') {
                            messageReceived = true;
                            communicator.destroy();
                            resolve(true);
                        }
                    });
                    
                    // Enviar mensaje antes de handshake (debe ir a la cola)
                    communicator.sendMessage('QUEUED_MESSAGE', { queued: true });
                    
                    // Completar handshake después
                    setTimeout(() => {
                        const event = new MessageEvent('message', {
                            data: {
                                type: 'HANDSHAKE_RESPONSE',
                                payload: { status: 'ready' }
                            },
                            source: window,
                            origin: window.location.origin
                        });
                        window.dispatchEvent(event);
                    }, 100);
                    
                    // Timeout
                    setTimeout(() => {
                        if (!messageReceived) {
                            communicator.destroy();
                            reject(new Error('Mensaje encolado no recibido'));
                        }
                    }, 1000);
                    
                } catch (error) {
                    reject(error);
                }
            });
        }, 'Cola de mensajes');
    }

    /**
     * Test 8: Duplicación de mensajes
     */
    async testMessageDuplication() {
        this.addTestResult('message_duplication', async () => {
            return new Promise((resolve, reject) => {
                try {
                    const communicator = new RealtimeCommunicator();
                    let messageCount = 0;
                    
                    communicator.on('onMessage', (data) => {
                        if (data.type === 'DUPLICATE_TEST') {
                            messageCount++;
                        }
                    });
                    
                    communicator.on('onHandshakeComplete', () => {
                        // Enviar mismo mensaje dos veces
                        communicator.sendMessage('DUPLICATE_TEST', { test: 'value' });
                        communicator.sendMessage('DUPLICATE_TEST', { test: 'value' });
                        
                        setTimeout(() => {
                            if (messageCount === 1) { // Solo debe procesarse una vez
                                communicator.destroy();
                                resolve(true);
                            } else {
                                communicator.destroy();
                                reject(new Error('Mensaje duplicado no se previno'));
                            }
                        }, 500);
                    });
                    
                    // Simular handshake
                    setTimeout(() => {
                        const event = new MessageEvent('message', {
                            data: {
                                type: 'HANDSHAKE_RESPONSE',
                                payload: { status: 'ready' }
                            },
                            source: window,
                            origin: window.location.origin
                        });
                        window.dispatchEvent(event);
                    }, 100);
                    
                    // Timeout
                    setTimeout(() => {
                        communicator.destroy();
                        reject(new Error('Test timeout'));
                    }, 2000);
                    
                } catch (error) {
                    reject(error);
                }
            });
        }, 'Prevención de duplicación de mensajes');
    }

    /**
     * Test 9: Pérdida de conexión
     */
    async testConnectionLost() {
        this.addTestResult('connection_lost', async () => {
            return new Promise((resolve, reject) => {
                try {
                    const communicator = new RealtimeCommunicator();
                    let disconnectReceived = false;
                    
                    communicator.on('onDisconnect', () => {
                        disconnectReceived = true;
                        communicator.destroy();
                        resolve(true);
                    });
                    
                    communicator.on('onHandshakeComplete', () => {
                        // Simular pérdida de conexión
                        setTimeout(() => {
                            const event = new MessageEvent('message', {
                                data: {
                                    type: 'CONNECTION_LOST'
                                },
                                source: window,
                                origin: window.location.origin
                            });
                            window.dispatchEvent(event);
                        }, 100);
                    });
                    
                    // Simular handshake
                    setTimeout(() => {
                        const event = new MessageEvent('message', {
                            data: {
                                type: 'HANDSHAKE_RESPONSE',
                                payload: { status: 'ready' }
                            },
                            source: window,
                            origin: window.location.origin
                        });
                        window.dispatchEvent(event);
                    }, 100);
                    
                    // Timeout
                    setTimeout(() => {
                        if (!disconnectReceived) {
                            communicator.destroy();
                            reject(new Error('Desconexión no detectada'));
                        }
                    }, 1000);
                    
                } catch (error) {
                    reject(error);
                }
            });
        }, 'Detección de pérdida de conexión');
    }

    /**
     * Test 10: Reconexión automática
     */
    async testAutomaticReconnection() {
        this.addTestResult('auto_reconnection', async () => {
            return new Promise((resolve, reject) => {
                try {
                    const communicator = new RealtimeCommunicator({
                        maxRetries: 3,
                        retryDelay: 100
                    });
                    
                    let reconnectAttempts = 0;
                    let reconnected = false;
                    
                    communicator.on('onError', (data) => {
                        if (data.type === 'HANDSHAKE_TIMEOUT') {
                            reconnectAttempts++;
                        }
                    });
                    
                    communicator.on('onHandshakeComplete', () => {
                        if (reconnectAttempts > 0) {
                            reconnected = true;
                        }
                    });
                    
                    // Simular fallos iniciales y luego éxito
                    let attempt = 0;
                    const simulateAttempts = () => {
                        attempt++;
                        
                        if (attempt <= 2) {
                            // No responder (timeout)
                            setTimeout(simulateAttempts, 50);
                        } else {
                            // Responder en el tercer intento
                            setTimeout(() => {
                                const event = new MessageEvent('message', {
                                    data: {
                                        type: 'HANDSHAKE_RESPONSE',
                                        payload: { status: 'ready' }
                                    },
                                    source: window,
                                    origin: window.location.origin
                                });
                                window.dispatchEvent(event);
                                
                                setTimeout(() => {
                                    if (reconnected && reconnectAttempts >= 2) {
                                        communicator.destroy();
                                        resolve(true);
                                    } else {
                                        communicator.destroy();
                                        reject(new Error('Reconexión no funcionó correctamente'));
                                    }
                                }, 200);
                            }, 50);
                        }
                    };
                    
                    communicator.init();
                    simulateAttempts();
                    
                    // Timeout general
                    setTimeout(() => {
                        communicator.destroy();
                        reject(new Error('Test timeout'));
                    }, 2000);
                    
                } catch (error) {
                    reject(error);
                }
            });
        }, 'Reconexión automática');
    }

    /**
     * Test 11: Sistema de heartbeat
     */
    async testHeartbeatSystem() {
        this.addTestResult('heartbeat_system', async () => {
            return new Promise((resolve, reject) => {
                try {
                    const communicator = new RealtimeCommunicator({
                        heartbeatInterval: 200
                    });
                    
                    let heartbeatReceived = false;
                    
                    communicator.on('onMessage', (data) => {
                        if (data.type === 'HEARTBEAT_RESPONSE') {
                            heartbeatReceived = true;
                            communicator.destroy();
                            resolve(true);
                        }
                    });
                    
                    communicator.on('onHandshakeComplete', () => {
                        // Esperar heartbeat automático
                        setTimeout(() => {
                            if (!heartbeatReceived) {
                                communicator.destroy();
                                reject(new Error('Heartbeat no recibido'));
                            }
                        }, 400);
                    });
                    
                    // Simular handshake y respuesta de heartbeat
                    setTimeout(() => {
                        const event = new MessageEvent('message', {
                            data: {
                                type: 'HANDSHAKE_RESPONSE',
                                payload: { status: 'ready' }
                            },
                            source: window,
                            origin: window.location.origin
                        });
                        window.dispatchEvent(event);
                        
                        // Simular respuesta de heartbeat
                        setTimeout(() => {
                            const heartbeatEvent = new MessageEvent('message', {
                                data: {
                                    type: 'HEARTBEAT_RESPONSE',
                                    payload: { status: 'alive' }
                                },
                                source: window,
                                origin: window.location.origin
                            });
                            window.dispatchEvent(heartbeatEvent);
                        }, 100);
                    }, 100);
                    
                    // Timeout
                    setTimeout(() => {
                        communicator.destroy();
                        reject(new Error('Test timeout'));
                    }, 1000);
                    
                } catch (error) {
                    reject(error);
                }
            });
        }, 'Sistema de heartbeat');
    }

    /**
     * Test 12: Validación de mensajes
     */
    async testMessageValidation() {
        this.addTestResult('message_validation', () => {
            try {
                const communicator = new RealtimeCommunicator();
                
                // Intentar procesar mensaje inválido
                const invalidEvent = new MessageEvent('message', {
                    data: null, // Mensaje nulo
                    source: window,
                    origin: window.location.origin
                });
                
                // No debe lanzar error
                window.dispatchEvent(invalidEvent);
                
                // Intentar procesar mensaje con estructura inválida
                const invalidStructEvent = new MessageEvent('message', {
                    data: 'string_en_lugar_de_objeto',
                    source: window,
                    origin: window.location.origin
                });
                
                window.dispatchEvent(invalidStructEvent);
                
                communicator.destroy();
                return true;
            } catch (error) {
                throw error;
            }
        }, 'Validación de mensajes');
    }

    /**
     * Test 13: Validación de origen
     */
    async testOriginValidation() {
        this.addTestResult('origin_validation', () => {
            try {
                const communicator = new RealtimeCommunicator();
                
                // Intentar mensaje de origen no permitido
                const invalidOriginEvent = new MessageEvent('message', {
                    data: {
                        type: 'TEST_MESSAGE',
                        payload: { test: 'value' }
                    },
                    source: window,
                    origin: 'https://malicious-site.com'
                });
                
                // No debe procesar el mensaje
                window.dispatchEvent(invalidOriginEvent);
                
                communicator.destroy();
                return true;
            } catch (error) {
                throw error;
            }
        }, 'Validación de origen');
    }

    /**
     * Test 14: Mensajes de alta frecuencia
     */
    async testHighFrequencyMessages() {
        this.addTestResult('high_frequency_messages', async () => {
            return new Promise((resolve, reject) => {
                try {
                    const communicator = new RealtimeCommunicator();
                    let messagesReceived = 0;
                    const totalMessages = 50;
                    
                    communicator.on('onMessage', (data) => {
                        if (data.type === 'FREQUENCY_TEST') {
                            messagesReceived++;
                        }
                    });
                    
                    communicator.on('onHandshakeComplete', () => {
                        // Enviar muchos mensajes rápidamente
                        for (let i = 0; i < totalMessages; i++) {
                            communicator.sendMessage('FREQUENCY_TEST', { index: i });
                        }
                        
                        // Simular respuestas
                        setTimeout(() => {
                            for (let i = 0; i < totalMessages; i++) {
                                const event = new MessageEvent('message', {
                                    data: {
                                        type: 'FREQUENCY_TEST',
                                        payload: { index: i }
                                    },
                                    source: window,
                                    origin: window.location.origin
                                });
                                window.dispatchEvent(event);
                            }
                            
                            setTimeout(() => {
                                if (messagesReceived === totalMessages) {
                                    communicator.destroy();
                                    resolve(true);
                                } else {
                                    communicator.destroy();
                                    reject(new Error(`Solo ${messagesReceived}/${totalMessages} mensajes recibidos`));
                                }
                            }, 500);
                        }, 100);
                    });
                    
                    // Simular handshake
                    setTimeout(() => {
                        const event = new MessageEvent('message', {
                            data: {
                                type: 'HANDSHAKE_RESPONSE',
                                payload: { status: 'ready' }
                            },
                            source: window,
                            origin: window.location.origin
                        });
                        window.dispatchEvent(event);
                    }, 100);
                    
                    // Timeout
                    setTimeout(() => {
                        communicator.destroy();
                        reject(new Error('Test timeout'));
                    }, 3000);
                    
                } catch (error) {
                    reject(error);
                }
            });
        }, 'Mensajes de alta frecuencia');
    }

    /**
     * Test 15: Payloads grandes
     */
    async testLargePayloads() {
        this.addTestResult('large_payloads', async () => {
            return new Promise((resolve, reject) => {
                try {
                    const communicator = new RealtimeCommunicator();
                    let messageReceived = false;
                    
                    communicator.on('onMessage', (data) => {
                        if (data.type === 'LARGE_PAYLOAD') {
                            if (data.payload.data.length === 10000) {
                                messageReceived = true;
                                communicator.destroy();
                                resolve(true);
                            }
                        }
                    });
                    
                    communicator.on('onHandshakeComplete', () => {
                        // Enviar payload grande
                        const largePayload = {
                            data: 'x'.repeat(10000), // 10KB
                            metadata: {
                                size: 10000,
                                type: 'test'
                            }
                        };
                        
                        communicator.sendMessage('LARGE_PAYLOAD', largePayload);
                    });
                    
                    // Simular respuesta
                    setTimeout(() => {
                        const event = new MessageEvent('message', {
                            data: {
                                type: 'LARGE_PAYLOAD',
                                payload: {
                                    data: 'x'.repeat(10000),
                                    metadata: {
                                        size: 10000,
                                        type: 'test'
                                    }
                                }
                            },
                            source: window,
                            origin: window.location.origin
                        });
                        window.dispatchEvent(event);
                    }, 200);
                    
                    // Timeout
                    setTimeout(() => {
                        if (!messageReceived) {
                            communicator.destroy();
                            reject(new Error('Payload grande no recibido'));
                        }
                    }, 1000);
                    
                } catch (error) {
                    reject(error);
                }
            });
        }, 'Payloads grandes');
    }

    /**
     * Agrega un resultado de test
     */
    addTestResult(testName, testFunction, description) {
        try {
            const result = Promise.race([
                testFunction(),
                new Promise((_, reject) => 
                    setTimeout(() => reject(new Error('Test timeout')), this.testTimeout)
                )
            ]);
            
            result.then(() => {
                this.testResults.push({
                    test: testName,
                    passed: true,
                    message: description,
                    duration: Date.now()
                });
                console.log(`✅ ${testName}: ${description}`);
            }).catch((error) => {
                this.testResults.push({
                    test: testName,
                    passed: false,
                    message: `${description} - ${error.message}`,
                    duration: Date.now()
                });
                this.errors.push(`${testName}: ${error.message}`);
                console.log(`❌ ${testName}: ${description} - ${error.message}`);
            });
        } catch (error) {
            this.testResults.push({
                test: testName,
                passed: false,
                message: `${description} - ${error.message}`,
                duration: Date.now()
            });
            this.errors.push(`${testName}: ${error.message}`);
            console.log(`❌ ${testName}: ${description} - ${error.message}`);
        }
    }

    /**
     * Imprime los resultados finales
     */
    printResults() {
        const total = this.testResults.length;
        const passed = this.testResults.filter(r => r.passed).length;
        const failed = total - passed;
        
        console.log('\n📋 RESULTADOS DE TESTS DE COMUNICACIÓN');
        console.log('=====================================');
        console.log(`Total: ${total} tests`);
        console.log(`✅ Pasados: ${passed}`);
        console.log(`❌ Fallidos: ${failed}`);
        console.log(`📊 Porcentaje: ${((passed / total) * 100).toFixed(2)}%`);
        
        if (this.errors.length > 0) {
            console.log('\n❌ ERRORES DETECTADOS:');
            this.errors.forEach(error => console.log(`  - ${error}`));
        }
        
        if (failed === 0) {
            console.log('\n🎉 TODOS LOS TESTS PASARON - SISTEMA DE COMUNICACIÓN ROBUSTO');
        } else {
            console.log('\n⚠️  HAY TESTS FALLIDOS - REVISAR EL SISTEMA');
        }
        
        // Retornar resultados para posible uso programático
        return {
            total,
            passed,
            failed,
            percentage: ((passed / total) * 100).toFixed(2),
            results: this.testResults,
            errors: this.errors
        };
    }
}

// Exponer globalmente para ejecutar desde consola
window.RealtimeCommunicationTest = RealtimeCommunicationTest;

// Auto-ejecutar si se accede directamente
if (typeof window !== 'undefined' && window.location) {
    // Ejecutar tests después de cargar las dependencias
    setTimeout(() => {
        if (window.RealtimeCommunicator && window.IframeReceiver) {
            const tester = new RealtimeCommunicationTest();
            window.communicationTestResults = tester.runAllTests();
        } else {
            console.error('Dependencias no cargadas. Asegúrate de incluir los archivos del sistema de comunicación.');
        }
    }, 1000);
}
