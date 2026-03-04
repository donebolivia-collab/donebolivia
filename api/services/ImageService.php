<?php
/**
 * Image Service - Manejo centralizado de imágenes de productos
 */

class ImageService {
    private $db;
    private $maxFileSize = 5 * 1024 * 1024; // 5MB
    private $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Sube una imagen de producto
     * @param array $file - Datos del archivo $_FILES
     * @param int $productId - ID del producto
     * @param int $order - Orden de la imagen
     * @return array ['success' => bool, 'filename' => string, 'message' => string]
     */
    public function uploadProductImage($file, $productId, $order = 0) {
        try {
            // Validaciones básicas
            if (!$this->validateImageFile($file)) {
                throw new Exception('Archivo de imagen inválido');
            }
            
            // Generar nombre único
            $filename = $this->generateUniqueFilename($file['name'], $productId);
            
            // Subir archivo
            $uploadResult = $this->uploadFile($file, $filename, $productId);
            if (!$uploadResult['success']) {
                throw new Exception($uploadResult['message']);
            }
            
            // Guardar en base de datos
            $this->saveImageRecord($productId, $filename, $order);
            
            return [
                'success' => true,
                'filename' => $filename,
                'message' => 'Imagen subida correctamente'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'filename' => null,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Valida un archivo de imagen
     */
    private function validateImageFile($file) {
        // Verificar que no haya errores
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }
        
        // Verificar tamaño
        if ($file['size'] > $this->maxFileSize) {
            return false;
        }
        
        // Verificar tipo MIME
        if (!in_array($file['type'], $this->allowedTypes)) {
            return false;
        }
        
        // Verificar que sea realmente una imagen
        if (!getimagesize($file['tmp_name'])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Genera un nombre de archivo único
     */
    private function generateUniqueFilename($originalName, $productId) {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $timestamp = time();
        $random = mt_rand(1000, 9999);
        
        return "product_{$productId}_{$timestamp}_{$random}.{$extension}";
    }
    
    /**
     * Sube el archivo físico
     */
    private function uploadFile($file, $filename, $productId) {
        // Crear directorio si no existe
        $uploadDir = UPLOAD_PATH . 'productos/' . $productId . '/';
        
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                return ['success' => false, 'message' => 'No se pudo crear el directorio'];
            }
        }
        
        $destination = $uploadDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            return ['success' => true];
        } else {
            return ['success' => false, 'message' => 'Error al mover el archivo'];
        }
    }
    
    /**
     * Guarda el registro de la imagen en la base de datos
     */
    private function saveImageRecord($productId, $filename, $order) {
        $isMain = ($order === 0) ? 1 : 0;
        
        $stmt = $this->db->prepare("
            INSERT INTO producto_imagenes (producto_id, nombre_archivo, es_principal, orden)
            VALUES (?, ?, ?, ?)
        ");
        
        return $stmt->execute([$productId, $filename, $isMain, $order]);
    }
    
    /**
     * Elimina una imagen físicamente y de la base de datos
     */
    public function deleteImage($imageId) {
        try {
            // Obtener información de la imagen
            $stmt = $this->db->prepare("SELECT producto_id, nombre_archivo FROM producto_imagenes WHERE id = ?");
            $stmt->execute([$imageId]);
            $image = $stmt->fetch();
            
            if (!$image) {
                throw new Exception('Imagen no encontrada');
            }
            
            // Eliminar archivo físico
            $filePath = UPLOAD_PATH . 'productos/' . $image['producto_id'] . '/' . $image['nombre_archivo'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            // Eliminar registro de la base de datos
            $stmt = $this->db->prepare("DELETE FROM producto_imagenes WHERE id = ?");
            $stmt->execute([$imageId]);
            
            return ['success' => true, 'message' => 'Imagen eliminada'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Reordena las imágenes de un producto
     */
    public function reorderImages($productId, $imageOrder) {
        try {
            foreach ($imageOrder as $order => $imageId) {
                $stmt = $this->db->prepare("
                    UPDATE producto_imagenes 
                    SET orden = ? 
                    WHERE id = ? AND producto_id = ?
                ");
                $stmt->execute([$order, $imageId, $productId]);
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Establece una imagen como principal
     */
    public function setMainImage($imageId, $productId) {
        try {
            // Quitar todas como principales
            $stmt = $this->db->prepare("
                UPDATE producto_imagenes 
                SET es_principal = 0 
                WHERE producto_id = ?
            ");
            $stmt->execute([$productId]);
            
            // Establecer la nueva como principal
            $stmt = $this->db->prepare("
                UPDATE producto_imagenes 
                SET es_principal = 1 
                WHERE id = ? AND producto_id = ?
            ");
            $stmt->execute([$imageId, $productId]);
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
