<?php
/**
 * Sistema de Caché Simple para CAMBALACHE
 * Almacena resultados de consultas frecuentes en archivos
 * Mejora significativamente la velocidad de respuesta
 */

class SimpleCache {
    private $cacheDir;
    private $defaultTTL = 3600; // 1 hora por defecto
    
    public function __construct($cacheDir = null) {
        $this->cacheDir = $cacheDir ?? __DIR__ . '/../cache/';
        
        // Crear directorio de caché si no existe
        if (!file_exists($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    /**
     * Obtener valor del caché
     * @param string $key Clave del caché
     * @return mixed|null Retorna el valor o null si no existe o expiró
     */
    public function get($key) {
        $filename = $this->getCacheFilename($key);
        
        if (!file_exists($filename)) {
            return null;
        }
        
        $data = @unserialize(file_get_contents($filename));
        
        if ($data === false) {
            return null;
        }
        
        // Verificar si expiró
        if (time() > $data['expires']) {
            @unlink($filename);
            return null;
        }
        
        return $data['value'];
    }
    
    /**
     * Guardar valor en caché
     * @param string $key Clave del caché
     * @param mixed $value Valor a almacenar
     * @param int $ttl Tiempo de vida en segundos
     * @return bool
     */
    public function set($key, $value, $ttl = null) {
        $ttl = $ttl ?? $this->defaultTTL;
        $filename = $this->getCacheFilename($key);
        
        $data = [
            'value' => $value,
            'expires' => time() + $ttl
        ];
        
        return file_put_contents($filename, serialize($data)) !== false;
    }
    
    /**
     * Eliminar valor del caché
     * @param string $key Clave del caché
     * @return bool
     */
    public function delete($key) {
        $filename = $this->getCacheFilename($key);
        
        if (file_exists($filename)) {
            return @unlink($filename);
        }
        
        return false;
    }
    
    /**
     * Limpiar todo el caché
     * @return bool
     */
    public function clear() {
        $files = glob($this->cacheDir . '*.cache');
        
        foreach ($files as $file) {
            @unlink($file);
        }
        
        return true;
    }
    
    /**
     * Limpiar caché expirado
     * @return int Cantidad de archivos eliminados
     */
    public function clearExpired() {
        $files = glob($this->cacheDir . '*.cache');
        $deleted = 0;
        
        foreach ($files as $file) {
            $data = @unserialize(file_get_contents($file));
            
            if ($data !== false && time() > $data['expires']) {
                @unlink($file);
                $deleted++;
            }
        }
        
        return $deleted;
    }
    
    /**
     * Obtener o crear valor en caché
     * @param string $key Clave del caché
     * @param callable $callback Función que genera el valor si no existe
     * @param int $ttl Tiempo de vida en segundos
     * @return mixed
     */
    public function remember($key, $callback, $ttl = null) {
        $value = $this->get($key);
        
        if ($value !== null) {
            return $value;
        }
        
        $value = $callback();
        $this->set($key, $value, $ttl);
        
        return $value;
    }
    
    /**
     * Generar nombre de archivo para la clave
     * @param string $key
     * @return string
     */
    private function getCacheFilename($key) {
        return $this->cacheDir . md5($key) . '.cache';
    }
}

// Instancia global de caché
function getCache() {
    static $cache = null;
    
    if ($cache === null) {
        $cache = new SimpleCache();
    }
    
    return $cache;
}
?>
