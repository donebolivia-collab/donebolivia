<?php
/**
 * Sistema de carga de variables de entorno desde .env
 * CAMBALACHE
 */

function loadEnv($path = null) {
    if ($path === null) {
        $path = __DIR__ . '/../.env';
    }
    
    if (!file_exists($path)) {
        // Si no existe .env, usar valores por defecto o lanzar error
        return false;
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // Ignorar comentarios
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parsear línea KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remover comillas si existen
            $value = trim($value, '"\'');
            
            // Setear variable de entorno
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
    
    return true;
}

/**
 * Obtener variable de entorno
 */
function env($key, $default = null) {
    $value = getenv($key);
    
    if ($value === false) {
        $value = $_ENV[$key] ?? $default;
    }
    
    // Convertir strings booleanos
    if (is_string($value)) {
        $lower = strtolower($value);
        if ($lower === 'true') return true;
        if ($lower === 'false') return false;
        if ($lower === 'null') return null;
    }
    
    return $value;
}

// Cargar .env automáticamente
loadEnv();
?>
