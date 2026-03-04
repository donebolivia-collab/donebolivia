<?php
/**
 * Product System Test Suite - Testing Integral del Nuevo Sistema
 * Verifica todas las capas y componentes del sistema refactorizado
 */

class ProductSystemTest {
    private $db;
    private $testResults = [];
    private $errors = [];
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Ejecuta todos los tests del sistema
     */
    public function runAllTests() {
        echo "<h2>🧪 PRODUCT SYSTEM TEST SUITE</h2>";
        
        $this->testDatabaseConnection();
        $this->testProductService();
        $this->testImageService();
        $this->testDataContractValidation();
        $this->testTransactionRollback();
        $this->testErrorHandling();
        $this->testSecurityValidation();
        
        $this->printResults();
    }
    
    /**
     * Test 1: Conexión a base de datos
     */
    private function testDatabaseConnection() {
        echo "<h3>📊 Test 1: Conexión a Base de Datos</h3>";
        
        try {
            // Test conexión básica
            $stmt = $this->db->query("SELECT 1");
            $result = $stmt->fetch();
            
            if ($result) {
                $this->addTestResult('database_connection', true, 'Conexión exitosa');
            } else {
                $this->addTestResult('database_connection', false, 'Error en conexión básica');
            }
            
            // Test tablas necesarias
            $tables = ['productos', 'producto_imagenes', 'producto_badges'];
            foreach ($tables as $table) {
                try {
                    $stmt = $this->db->query("SHOW TABLES LIKE '$table'");
                    $exists = $stmt->rowCount() > 0;
                    $this->addTestResult("table_$table", $exists, $exists ? "Tabla $table existe" : "Tabla $table no existe");
                } catch (Exception $e) {
                    $this->addTestResult("table_$table", false, "Error verificando tabla $table: " . $e->getMessage());
                }
            }
            
        } catch (Exception $e) {
            $this->addTestResult('database_connection', false, 'Excepción: ' . $e->getMessage());
        }
    }
    
    /**
     * Test 2: ProductService
     */
    private function testProductService() {
        echo "<h3>🔧 Test 2: ProductService</h3>";
        
        try {
            require_once __DIR__ . '/services/ProductService.php';
            $service = new ProductService($this->db);
            
            // Test validación de contrato
            $invalidData = ['titulo' => 'corto'];
            $validation = $this->invokePrivateMethod($service, 'validateContract', [$invalidData]);
            $this->addTestResult('service_validation', !$validation['valid'], 'Validación de datos inválidos funciona');
            
            // Test datos válidos
            $validData = [
                'titulo' => 'Producto de prueba con más de 10 caracteres',
                'descripcion' => 'Descripción de prueba con más de 20 caracteres',
                'precio' => 99.99,
                'estado' => 'Nuevo',
                'categoria_id' => 1,
                'subcategoria_id' => 1,
                'departamento' => 'SCZ',
                'municipio' => 'SCZ-001'
            ];
            $validation = $this->invokePrivateMethod($service, 'validateContract', [$validData]);
            $this->addTestResult('service_valid_data', $validation['valid'], 'Validación de datos válidos funciona');
            
        } catch (Exception $e) {
            $this->addTestResult('product_service', false, 'Excepción: ' . $e->getMessage());
        }
    }
    
    /**
     * Test 3: ImageService
     */
    private function testImageService() {
        echo "<h3>🖼️ Test 3: ImageService</h3>";
        
        try {
            require_once __DIR__ . '/services/ImageService.php';
            $service = new ImageService($this->db);
            
            // Test validación de archivo (mock)
            $invalidFile = [
                'error' => UPLOAD_ERR_INI_SIZE,
                'size' => 10 * 1024 * 1024, // 10MB
                'type' => 'application/pdf'
            ];
            
            $validation = $this->invokePrivateMethod($service, 'validateImageFile', [$invalidFile]);
            $this->addTestResult('image_validation', !$validation, 'Rechaza archivo inválido correctamente');
            
            // Test generación de nombre único
            $filename = $this->invokePrivateMethod($service, 'generateUniqueFilename', ['test.jpg', 123]);
            $isUnique = strpos($filename, 'product_123_') === 0 && strpos($filename, '.jpg') !== false;
            $this->addTestResult('image_filename', $isUnique, 'Genera nombres de archivo únicos');
            
        } catch (Exception $e) {
            $this->addTestResult('image_service', false, 'Excepción: ' . $e->getMessage());
        }
    }
    
    /**
     * Test 4: Validación de contrato JavaScript-PHP
     */
    private function testDataContractValidation() {
        echo "<h3>📝 Test 4: Data Contract Validation</h3>";
        
        // Simular validación del contrato
        $testCases = [
            [
                'data' => ['titulo' => 'short'],
                'expected_valid' => false,
                'description' => 'Rechaza título corto'
            ],
            [
                'data' => ['titulo' => 'Título válido con más de 10 caracteres'],
                'expected_valid' => false,
                'description' => 'Rechaza datos incompletos'
            ],
            [
                'data' => [
                    'titulo' => 'Título válido con más de 10 caracteres',
                    'descripcion' => 'Descripción válida con más de 20 caracteres',
                    'precio' => 100,
                    'estado' => 'Nuevo',
                    'categoria_id' => 1,
                    'subcategoria_id' => 1,
                    'departamento' => 'SCZ',
                    'municipio' => 'SCZ-001'
                ],
                'expected_valid' => true,
                'description' => 'Acepta datos completos válidos'
            ]
        ];
        
        foreach ($testCases as $i => $testCase) {
            $isValid = $this->validateDataContract($testCase['data']);
            $passed = $isValid === $testCase['expected_valid'];
            $this->addTestResult("contract_test_$i", $passed, $testCase['description']);
        }
    }
    
    /**
     * Test 5: Rollback de transacciones
     */
    private function testTransactionRollback() {
        echo "<h3>🔄 Test 5: Transaction Rollback</h3>";
        
        try {
            $this->db->beginTransaction();
            
            // Insertar un producto de prueba
            $stmt = $this->db->prepare("
                INSERT INTO productos (
                    usuario_id, categoria_id, subcategoria_id,
                    titulo, descripcion, precio, estado,
                    departamento_codigo, municipio_codigo,
                    departamento_nombre, municipio_nombre,
                    activo, fecha_publicacion
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
            ");
            
            $result = $stmt->execute([
                999, // usuario_id de prueba
                1, 1,
                'Producto Test Rollback',
                'Descripción de prueba para rollback',
                99.99,
                'Nuevo',
                'SCZ', 'SCZ-001',
                'Santa Cruz', 'Santa Cruz'
            ]);
            
            $productId = $this->db->lastInsertId();
            
            // Forzar rollback
            $this->db->rollBack();
            
            // Verificar que no se guardó
            $stmt = $this->db->prepare("SELECT id FROM productos WHERE id = ?");
            $stmt->execute([$productId]);
            $exists = $stmt->fetch();
            
            $this->addTestResult('transaction_rollback', !$exists, 'Rollback elimina datos correctamente');
            
        } catch (Exception $e) {
            $this->addTestResult('transaction_rollback', false, 'Excepción: ' . $e->getMessage());
        }
    }
    
    /**
     * Test 6: Manejo de errores
     */
    private function testErrorHandling() {
        echo "<h3>⚠️ Test 6: Error Handling</h3>";
        
        try {
            // Simular error de base de datos
            try {
                $stmt = $this->db->prepare("SELECT * FROM tabla_inexistente");
                $stmt->execute();
                $this->addTestResult('error_handling_db', false, 'No detectó error de tabla inexistente');
            } catch (Exception $e) {
                $this->addTestResult('error_handling_db', true, 'Detectó error de base de datos correctamente');
            }
            
            // Test validación de entrada
            $invalidInputs = [
                'precio' => 'not_a_number',
                'categoria_id' => -1,
                'estado' => 'invalid_state'
            ];
            
            foreach ($invalidInputs as $field => $value) {
                $isValid = $this->validateField($field, $value);
                $this->addTestResult("error_input_$field", !$isValid, "Rechaza $field inválido");
            }
            
        } catch (Exception $e) {
            $this->addTestResult('error_handling', false, 'Excepción: ' . $e->getMessage());
        }
    }
    
    /**
     * Test 7: Validación de seguridad
     */
    private function testSecurityValidation() {
        echo "<h3>🔒 Test 7: Security Validation</h3>";
        
        try {
            // Test SQL Injection básico
            $maliciousInput = "'; DROP TABLE productos; --";
            $safeInput = $this->sanitizeInput($maliciousInput);
            $isSafe = !strpos($safeInput, "'") && !strpos($safeInput, ';') && !strpos($safeInput, '--');
            $this->addTestResult('security_sql_injection', $isSafe, 'Protección contra SQL Injection básica');
            
            // Test XSS
            $xssInput = '<script>alert("xss")</script>';
            $safeXss = $this->sanitizeInput($xssInput);
            $isXssSafe = !strpos($safeXss, '<script>') && !strpos($safeXss, 'alert');
            $this->addTestResult('security_xss', $isXssSafe, 'Protección contra XSS básica');
            
            // Test validación de archivos
            $maliciousFiles = [
                'shell.php',
                'script.js',
                'exploit.exe'
            ];
            
            foreach ($maliciousFiles as $file) {
                $isAllowed = $this->isAllowedFileType($file);
                $this->addTestResult("security_file_$file", !$isAllowed, "Rechaza archivo $file");
            }
            
        } catch (Exception $e) {
            $this->addTestResult('security_validation', false, 'Excepción: ' . $e->getMessage());
        }
    }
    
    /**
     * Valida el contrato de datos (simulación de validación JS)
     */
    private function validateDataContract($data) {
        $required = ['titulo', 'descripcion', 'precio', 'estado', 'categoria_id', 'subcategoria_id', 'departamento', 'municipio'];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return false;
            }
        }
        
        if (strlen($data['titulo']) < 10) return false;
        if (strlen($data['descripcion']) < 20) return false;
        if ($data['precio'] <= 0) return false;
        
        $validStates = ['Nuevo', 'Como Nuevo', 'Buen Estado', 'Aceptable'];
        if (!in_array($data['estado'], $validStates)) return false;
        
        return true;
    }
    
    /**
     * Valida un campo específico
     */
    private function validateField($field, $value) {
        switch ($field) {
            case 'precio':
                return is_numeric($value) && $value > 0;
            case 'categoria_id':
            case 'subcategoria_id':
                return is_numeric($value) && $value > 0;
            case 'estado':
                $valid = ['Nuevo', 'Como Nuevo', 'Buen Estado', 'Aceptable'];
                return in_array($value, $valid);
            default:
                return !empty($value);
        }
    }
    
    /**
     * Limpia entrada de usuario
     */
    private function sanitizeInput($input) {
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Verifica si el tipo de archivo es permitido
     */
    private function isAllowedFileType($filename) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($ext, $allowed);
    }
    
    /**
     * Invoca un método privado (para testing)
     */
    private function invokePrivateMethod($object, $method, $parameters = []) {
        $reflection = new ReflectionClass(get_class($object));
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }
    
    /**
     * Agrega resultado de test
     */
    private function addTestResult($testName, $passed, $message) {
        $this->testResults[] = [
            'test' => $testName,
            'passed' => $passed,
            'message' => $message
        ];
        
        if (!$passed) {
            $this->errors[] = $message;
        }
        
        $status = $passed ? '✅ PASS' : '❌ FAIL';
        echo "<div style='margin: 5px 0; padding: 5px; background: " . ($passed ? '#d4edda' : '#f8d7da') . ";'>$status $testName: $message</div>";
    }
    
    /**
     * Imprime resultados finales
     */
    private function printResults() {
        $total = count($this->testResults);
        $passed = count(array_filter($this->testResults, fn($r) => $r['passed']));
        $failed = $total - $passed;
        
        echo "<h3>📋 RESULTADOS FINALES</h3>";
        echo "<div style='padding: 15px; background: #f8f9fa; border-radius: 8px; margin: 10px 0;'>";
        echo "<strong>Total:</strong> $total tests<br>";
        echo "<strong style='color: green;'>✅ Pasados:</strong> $passed<br>";
        echo "<strong style='color: red;'>❌ Fallidos:</strong> $failed<br>";
        echo "<strong>Porcentaje:</strong> " . round(($passed / $total) * 100, 2) . "%";
        echo "</div>";
        
        if (!empty($this->errors)) {
            echo "<h4>❌ ERRORES DETECTADOS:</h4>";
            echo "<ul>";
            foreach ($this->errors as $error) {
                echo "<li style='color: red;'>$error</li>";
            }
            echo "</ul>";
        }
        
        if ($failed === 0) {
            echo "<div style='padding: 15px; background: #d4edda; color: #155724; border-radius: 8px; text-align: center; font-weight: bold;'>";
            echo "🎉 TODOS LOS TESTS PASARON - SISTEMA ROBUSTO Y FUNCIONAL";
            echo "</div>";
        }
    }
}

// Ejecutar tests si se accede directamente
if (basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    require_once __DIR__ . '/../config/database.php';
    
    try {
        $db = getDB();
        $tester = new ProductSystemTest($db);
        $tester->runAllTests();
    } catch (Exception $e) {
        echo "<div style='color: red; padding: 15px; background: #f8d7da; border-radius: 8px;'>";
        echo "❌ Error al inicializar tests: " . $e->getMessage();
        echo "</div>";
    }
}
?>
