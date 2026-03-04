<?php
/**
 * Product Service - Capa de Datos Atómica
 * Manejo centralizado y transaccional de productos
 */

class ProductService {
    private $db;
    private $errors = [];
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Guarda un producto de forma atómica (crear o actualizar)
     * @param array $data - Datos del producto
     * @param array $files - Archivos de imágenes (opcional)
     * @param int $userId - ID del usuario actual
     * @return array ['success' => bool, 'data' => mixed, 'message' => string]
     */
    public function saveProduct($data, $files = [], $userId = null) {
        try {
            // Iniciar transacción
            $this->db->beginTransaction();
            
            // Validar contrato de datos
            $validation = $this->validateContract($data);
            if (!$validation['valid']) {
                throw new Exception('Validación fallida: ' . implode(', ', $validation['errors']));
            }
            
            // Determinar si es creación o actualización
            $isEdit = !empty($data['id']) && $data['id'] > 0;
            
            if ($isEdit) {
                $result = $this->updateProduct($data, $files, $userId);
            } else {
                $result = $this->createProduct($data, $files, $userId);
            }
            
            // Commit si todo fue exitoso
            $this->db->commit();
            
            return [
                'success' => true,
                'data' => $result,
                'message' => $isEdit ? 'Producto actualizado correctamente' : 'Producto creado correctamente'
            ];
            
        } catch (Exception $e) {
            // Rollback en caso de error
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            
            error_log("ProductService::saveProduct - Error: " . $e->getMessage());
            
            return [
                'success' => false,
                'data' => null,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Crea un nuevo producto con todas sus relaciones
     */
    private function createProduct($data, $files, $userId) {
        // 1. Insertar producto principal
        $productId = $this->insertProduct($data, $userId);
        
        // 2. Insertar badges si existen
        if (!empty($data['badges']) && is_array($data['badges'])) {
            $this->saveBadges($productId, $data['badges']);
        }
        
        // 3. Procesar imágenes si existen
        if (!empty($files)) {
            $this->processImages($productId, $files, false);
        }
        
        return ['product_id' => $productId];
    }
    
    /**
     * Actualiza un producto existente de forma atómica
     */
    private function updateProduct($data, $files, $userId) {
        $productId = $data['id'];
        
        // 1. Verificar propiedad del producto
        if (!$this->verifyProductOwnership($productId, $userId)) {
            throw new Exception('No tienes permiso para editar este producto');
        }
        
        // 2. Actualizar datos del producto
        $this->updateProductData($productId, $data);
        
        // 3. Actualizar badges (eliminar y volver a insertar)
        $this->updateBadges($productId, $data['badges'] ?? []);
        
        // 4. Procesar imágenes (eliminar antiguas y agregar nuevas)
        if (!empty($files) || !empty($data['imagenes_eliminar'])) {
            $this->processImages($productId, $files, true, $data['imagenes_eliminar'] ?? []);
        }
        
        return ['product_id' => $productId];
    }
    
    /**
     * Inserta el producto principal en la base de datos
     */
    private function insertProduct($data, $userId) {
        // Verificar si existe la columna categoria_tienda
        $hasCategoriaTienda = $this->columnExists('productos', 'categoria_tienda');
        
        $sql = $hasCategoriaTienda 
            ? "INSERT INTO productos (
                    usuario_id, categoria_id, subcategoria_id, categoria_tienda,
                    titulo, descripcion, precio, estado,
                    departamento_codigo, municipio_codigo,
                    departamento_nombre, municipio_nombre,
                    activo, fecha_publicacion
               ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())"
            : "INSERT INTO productos (
                    usuario_id, categoria_id, subcategoria_id,
                    titulo, descripcion, precio, estado,
                    departamento_codigo, municipio_codigo,
                    departamento_nombre, municipio_nombre,
                    activo, fecha_publicacion
               ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())";
        
        $stmt = $this->db->prepare($sql);
        
        $params = $hasCategoriaTienda
            ? [
                $userId,
                $data['categoria_id'],
                $data['subcategoria_id'],
                $data['categoria_tienda'] ?? null,
                $data['titulo'],
                $data['descripcion'],
                $data['precio'],
                $data['estado'],
                $data['departamento'],
                $data['municipio'],
                $this->getDepartamentoNombre($data['departamento']),
                $this->getMunicipioNombre($data['municipio'])
            ]
            : [
                $userId,
                $data['categoria_id'],
                $data['subcategoria_id'],
                $data['titulo'],
                $data['descripcion'],
                $data['precio'],
                $data['estado'],
                $data['departamento'],
                $data['municipio'],
                $this->getDepartamentoNombre($data['departamento']),
                $this->getMunicipioNombre($data['municipio'])
            ];
        
        $stmt->execute($params);
        return $this->db->lastInsertId();
    }
    
    /**
     * Actualiza los datos del producto
     */
    private function updateProductData($productId, $data) {
        $hasCategoriaTienda = $this->columnExists('productos', 'categoria_tienda');
        
        $sql = $hasCategoriaTienda
            ? "UPDATE productos SET
                    categoria_id = ?, subcategoria_id = ?, categoria_tienda = ?,
                    titulo = ?, descripcion = ?, precio = ?, estado = ?,
                    departamento_codigo = ?, municipio_codigo = ?,
                    departamento_nombre = ?, municipio_nombre = ?,
                    fecha_actualizacion = NOW()
               WHERE id = ?"
            : "UPDATE productos SET
                    categoria_id = ?, subcategoria_id = ?,
                    titulo = ?, descripcion = ?, precio = ?, estado = ?,
                    departamento_codigo = ?, municipio_codigo = ?,
                    departamento_nombre = ?, municipio_nombre = ?,
                    fecha_actualizacion = NOW()
               WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        
        $params = $hasCategoriaTienda
            ? [
                $data['categoria_id'],
                $data['subcategoria_id'],
                $data['categoria_tienda'] ?? null,
                $data['titulo'],
                $data['descripcion'],
                $data['precio'],
                $data['estado'],
                $data['departamento'],
                $data['municipio'],
                $this->getDepartamentoNombre($data['departamento']),
                $this->getMunicipioNombre($data['municipio']),
                $productId
            ]
            : [
                $data['categoria_id'],
                $data['subcategoria_id'],
                $data['titulo'],
                $data['descripcion'],
                $data['precio'],
                $data['estado'],
                $data['departamento'],
                $data['municipio'],
                $this->getDepartamentoNombre($data['departamento']),
                $this->getMunicipioNombre($data['municipio']),
                $productId
            ];
        
        return $stmt->execute($params);
    }
    
    /**
     * Guarda los badges de un producto
     */
    private function saveBadges($productId, $badges) {
        if (empty($badges) || !is_array($badges)) {
            return;
        }
        
        $stmt = $this->db->prepare("INSERT INTO producto_badges (producto_id, badge_id) VALUES (?, ?)");
        
        foreach ($badges as $badgeId) {
            $stmt->execute([$productId, $badgeId]);
        }
    }
    
    /**
     * Actualiza los badges de un producto
     */
    private function updateBadges($productId, $badges) {
        // Eliminar badges existentes
        $stmt = $this->db->prepare("DELETE FROM producto_badges WHERE producto_id = ?");
        $stmt->execute([$productId]);
        
        // Insertar nuevos badges
        $this->saveBadges($productId, $badges);
    }
    
    /**
     * Procesa las imágenes del producto
     */
    private function processImages($productId, $files, $isEdit = false, $imagesToDelete = []) {
        // Si es edición, eliminar imágenes marcadas
        if ($isEdit && !empty($imagesToDelete)) {
            $this->deleteImages($productId, $imagesToDelete);
        }
        
        // Si es edición y hay nuevas imágenes, eliminar todas las existentes
        if ($isEdit && !empty($files)) {
            $this->deleteAllImages($productId);
        }
        
        // Procesar nuevas imágenes
        if (!empty($files)) {
            $this->uploadImages($productId, $files);
        }
        
        // Asegurar que haya al menos una imagen principal
        $this->ensureMainImage($productId);
        
        // Verificar que quede al menos una imagen
        $this->validateMinimumImages($productId);
    }
    
    /**
     * Elimina imágenes específicas
     */
    private function deleteImages($productId, $imageIds) {
        foreach ($imageIds as $imageId) {
            // Obtener información de la imagen
            $stmt = $this->db->prepare("SELECT nombre_archivo FROM producto_imagenes WHERE id = ? AND producto_id = ?");
            $stmt->execute([$imageId, $productId]);
            $image = $stmt->fetch();
            
            if ($image) {
                // Eliminar archivo físico
                $filePath = UPLOAD_PATH . $image['nombre_archivo'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                
                // Eliminar registro de BD
                $stmt = $this->db->prepare("DELETE FROM producto_imagenes WHERE id = ? AND producto_id = ?");
                $stmt->execute([$imageId, $productId]);
            }
        }
    }
    
    /**
     * Elimina todas las imágenes de un producto
     */
    private function deleteAllImages($productId) {
        $stmt = $this->db->prepare("SELECT id, nombre_archivo FROM producto_imagenes WHERE producto_id = ?");
        $stmt->execute([$productId]);
        $images = $stmt->fetchAll();
        
        foreach ($images as $image) {
            // Eliminar archivo físico
            $filePath = UPLOAD_PATH . $image['nombre_archivo'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        
        // Eliminar todos los registros
        $stmt = $this->db->prepare("DELETE FROM producto_imagenes WHERE producto_id = ?");
        $stmt->execute([$productId]);
    }
    
    /**
     * Sube nuevas imágenes
     */
    private function uploadImages($productId, $files) {
        $imageManager = new ImageService($this->db);
        
        foreach ($files as $index => $file) {
            $result = $imageManager->uploadProductImage($file, $productId, $index);
            
            if (!$result['success']) {
                throw new Exception("Error al subir imagen {$index}: " . $result['message']);
            }
        }
    }
    
    /**
     * Asegura que haya una imagen principal
     */
    private function ensureMainImage($productId) {
        $stmt = $this->db->prepare("SELECT id FROM producto_imagenes WHERE producto_id = ? AND es_principal = 1");
        $stmt->execute([$productId]);
        
        if (!$stmt->fetch()) {
            // No hay imagen principal, asignar la primera
            $stmt = $this->db->prepare("
                UPDATE producto_imagenes 
                SET es_principal = 1 
                WHERE producto_id = ? 
                ORDER BY id ASC 
                LIMIT 1
            ");
            $stmt->execute([$productId]);
        }
    }
    
    /**
     * Valida que el producto tenga al menos una imagen
     */
    private function validateMinimumImages($productId) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM producto_imagenes WHERE producto_id = ?");
        $stmt->execute([$productId]);
        
        if ($stmt->fetchColumn() == 0) {
            throw new Exception('El producto debe tener al menos una imagen');
        }
    }
    
    /**
     * Verifica que el usuario sea dueño del producto
     */
    private function verifyProductOwnership($productId, $userId) {
        $stmt = $this->db->prepare("SELECT id FROM productos WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$productId, $userId]);
        return (bool)$stmt->fetch();
    }
    
    /**
     * Valida el contrato de datos del producto
     */
    private function validateContract($data) {
        $errors = [];
        
        // Campos requeridos
        $required = ['titulo', 'descripcion', 'precio', 'estado', 'categoria_id', 'subcategoria_id', 'departamento', 'municipio'];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $errors[] = "Campo requerido: {$field}";
            }
        }
        
        // Validaciones específicas
        if (!empty($data['titulo']) && strlen($data['titulo']) < 10) {
            $errors[] = "El título debe tener al menos 10 caracteres";
        }
        
        if (!empty($data['descripcion']) && strlen($data['descripcion']) < 20) {
            $errors[] = "La descripción debe tener al menos 20 caracteres";
        }
        
        if (!empty($data['precio']) && $data['precio'] <= 0) {
            $errors[] = "El precio debe ser mayor a 0";
        }
        
        // Validar estado
        if (!empty($data['estado'])) {
            $validStates = ['Nuevo', 'Como Nuevo', 'Buen Estado', 'Aceptable'];
            if (!in_array($data['estado'], $validStates)) {
                $errors[] = "Estado no válido";
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Verifica si existe una columna en una tabla
     */
    private function columnExists($table, $column) {
        $stmt = $this->db->prepare("SHOW COLUMNS FROM {$table} LIKE ?");
        $stmt->execute([$column]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Obtiene el nombre del departamento
     */
    private function getDepartamentoNombre($codigo) {
        // Implementar según tu sistema de ubicaciones
        $departamentos = obtenerDepartamentosBolivia();
        return $departamentos[$codigo] ?? '';
    }
    
    /**
     * Obtiene el nombre del municipio
     */
    private function getMunicipioNombre($codigo) {
        // Implementar según tu sistema de ubicaciones
        $municipios = obtenerTodosMunicipiosConCodigos();
        $municipio = $municipios[$codigo] ?? null;
        return $municipio ? $municipio['nombre'] : '';
    }
}
