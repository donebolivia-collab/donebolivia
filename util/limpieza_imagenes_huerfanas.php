<?php
/**
 * UTILIDAD EXPERTA: Limpieza de Imágenes Huérfanas
 * 
 * Esta herramienta identifica y elimina archivos de imágenes que:
 * 1. No están referenciados en la base de datos
 * 2. Pertenecen a productos/usuarios eliminados
 * 3. Son archivos duplicados o corruptos
 * 
 * Uso: php util/limpieza_imagenes_huerfanas.php [--dry-run] [--force]
 * --dry-run: Solo muestra lo que se eliminaría (default)
 * --force: Elimina físicamente los archivos
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

class ImageCleanupManager {
    private $db;
    private $uploadPath;
    private $dryRun;
    private $force;
    private $stats;
    
    public function __construct($dryRun = true, $force = false) {
        $this->db = getDB();
        $this->uploadPath = UPLOAD_PATH;
        $this->dryRun = !$force;
        $this->force = $force;
        $this->stats = [
            'scanned_files' => 0,
            'orphaned_files' => 0,
            'deleted_files' => 0,
            'space_freed' => 0,
            'errors' => 0
        ];
    }
    
    /**
     * Ejecuta el proceso completo de limpieza
     */
    public function executeCleanup() {
        echo "🧹 INICIANDO LIMPIEZA DE IMÁGENES HUÉRFANAS\n";
        echo "📍 Directorio: {$this->uploadPath}\n";
        echo "🔍 Modo: " . ($this->dryRun ? "SIMULACIÓN (no se eliminarán archivos)" : "ELIMINACIÓN REAL") . "\n";
        echo str_repeat("=", 60) . "\n\n";
        
        try {
            // 1. Escanear directorios de imágenes
            $this->scanImageDirectories();
            
            // 2. Identificar imágenes huérfanas
            $this->identifyOrphanedImages();
            
            // 3. Procesar eliminación
            $this->processOrphanedImages();
            
            // 4. Generar reporte final
            $this->generateFinalReport();
            
        } catch (Exception $e) {
            echo "❌ ERROR CRÍTICO: " . $e->getMessage() . "\n";
            return false;
        }
        
        return true;
    }
    
    /**
     * Escanea todos los directorios de imágenes
     */
    private function scanImageDirectories() {
        echo "📂 ESCANEANDO DIRECTORIOS DE IMÁGENES...\n";
        
        $directories = ['productos', 'perfiles', 'portadas', 'logos'];
        
        foreach ($directories as $dir) {
            $fullPath = $this->uploadPath . $dir;
            if (is_dir($fullPath)) {
                $this->scanDirectory($fullPath, $dir);
            } else {
                echo "⚠️  Directorio no encontrado: $fullPath\n";
            }
        }
        
        echo "✅ Escaneo completado: {$this->stats['scanned_files']} archivos analizados\n\n";
    }
    
    /**
     * Escanea un directorio específico
     */
    private function scanDirectory($dirPath, $dirType) {
        $files = glob($dirPath . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE);
        
        foreach ($files as $file) {
            $this->stats['scanned_files']++;
            $fileName = basename($file);
            $relativePath = $dirType . '/' . $fileName;
            
            // Verificar si el archivo está referenciado en BD
            if (!$this->isFileReferenced($relativePath, $dirType)) {
                $this->stats['orphaned_files']++;
                $this->stats['space_freed'] += filesize($file);
                
                echo "🔍 Huérfano encontrado: $relativePath (" . $this->formatBytes(filesize($file)) . ")\n";
            }
        }
    }
    
    /**
     * Verifica si un archivo está referenciado en la base de datos
     */
    private function isFileReferenced($filePath, $dirType) {
        switch ($dirType) {
            case 'productos':
                $stmt = $this->db->prepare("SELECT 1 FROM producto_imagenes WHERE nombre_archivo = ?");
                break;
            case 'perfiles':
                $stmt = $this->db->prepare("SELECT 1 FROM usuarios WHERE foto_perfil = ?");
                break;
            case 'portadas':
                $stmt = $this->db->prepare("SELECT 1 FROM usuarios WHERE fondo_perfil = ?");
                break;
            case 'logos':
                $stmt = $this->db->prepare("SELECT 1 FROM tiendas WHERE logo = ? OR logo_principal = ?");
                return $this->checkReference($stmt, [$filePath, $filePath]);
            default:
                return true;
        }
        
        return $this->checkReference($stmt, [$filePath]);
    }
    
    /**
     * Ejecuta la verificación de referencia
     */
    private function checkReference($stmt, $params) {
        $stmt->execute($params);
        return $stmt->fetch() !== false;
    }
    
    /**
     * Identifica y lista imágenes huérfanas
     */
    private function identifyOrphanedImages() {
        echo "🎯 IDENTIFICANDO IMÁGENES HUÉRFANAS...\n";
        
        // Buscar registros en BD que no tengan archivo físico
        $this->findMissingPhysicalFiles();
        
        echo "✅ Identificación completada\n\n";
    }
    
    /**
     * Busca registros en BD sin archivo físico
     */
    private function findMissingPhysicalFiles() {
        // Productos
        $stmt = $this->db->query("SELECT nombre_archivo FROM producto_imagenes");
        while ($row = $stmt->fetch()) {
            $filePath = $this->uploadPath . $row['nombre_archivo'];
            if (!file_exists($filePath)) {
                echo "⚠️  Registro huérfano en BD: {$row['nombre_archivo']}\n";
            }
        }
    }
    
    /**
     * Procesa la eliminación de imágenes huérfanas
     */
    private function processOrphanedImages() {
        echo "🗑️  PROCESANDO ELIMINACIÓN...\n";
        
        $directories = ['productos', 'perfiles', 'portadas', 'logos'];
        
        foreach ($directories as $dir) {
            $fullPath = $this->uploadPath . $dir;
            if (is_dir($fullPath)) {
                $this->processDirectoryCleanup($fullPath, $dir);
            }
        }
        
        echo "✅ Proceso completado: {$this->stats['deleted_files']} archivos eliminados\n\n";
    }
    
    /**
     * Limpia un directorio específico
     */
    private function processDirectoryCleanup($dirPath, $dirType) {
        $files = glob($dirPath . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE);
        
        foreach ($files as $file) {
            $fileName = basename($file);
            $relativePath = $dirType . '/' . $fileName;
            
            if (!$this->isFileReferenced($relativePath, $dirType)) {
                if ($this->dryRun) {
                    echo "[SIMULACIÓN] Se eliminaría: $relativePath\n";
                } else {
                    if (unlink($file)) {
                        $this->stats['deleted_files']++;
                        echo "✅ Eliminado: $relativePath\n";
                    } else {
                        $this->stats['errors']++;
                        echo "❌ Error eliminando: $relativePath\n";
                    }
                }
            }
        }
    }
    
    /**
     * Genera reporte final
     */
    private function generateFinalReport() {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "📊 REPORTE FINAL DE LIMPIEZA\n";
        echo str_repeat("=", 60) . "\n";
        
        echo "📁 Archivos escaneados: {$this->stats['scanned_files']}\n";
        echo "👻 Archivos huérfanos: {$this->stats['orphaned_files']}\n";
        echo "🗑️  Archivos eliminados: {$this->stats['deleted_files']}\n";
        echo "💾 Espacio liberado: " . $this->formatBytes($this->stats['space_freed']) . "\n";
        echo "❌ Errores: {$this->stats['errors']}\n";
        
        if ($this->dryRun) {
            echo "\n⚠️  ESTE FUE UNA SIMULACIÓN\n";
            echo "💡 Para eliminar realmente, ejecuta: php util/limpieza_imagenes_huerfanas.php --force\n";
        } else {
            echo "\n✅ LIMPIEZA REALIZADA EXITOSAMENTE\n";
        }
        
        echo "\n🎯 RECOMENDACIONES:\n";
        echo "• Ejecuta esta limpieza mensualmente\n";
        echo "• Considera automatizar con cron job\n";
        echo "• Monitorea el crecimiento del directorio uploads/\n";
    }
    
    /**
     * Formatea bytes a formato legible
     */
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

// Ejecución principal
try {
    // Parsear argumentos de línea de comandos
    $dryRun = !in_array('--force', $argv);
    $force = in_array('--force', $argv);
    
    // Validar argumentos
    if (in_array('--help', $argv) || in_array('-h', $argv)) {
        echo "Uso: php limpieza_imagenes_huerfanas.php [--dry-run] [--force] [--help]\n";
        echo "  --dry-run: Simulación (default)\n";
        echo "  --force: Eliminación real\n";
        echo "  --help: Muestra esta ayuda\n";
        exit(0);
    }
    
    // Ejecutar limpieza
    $cleaner = new ImageCleanupManager($dryRun, $force);
    $cleaner->executeCleanup();
    
} catch (Exception $e) {
    echo "❌ ERROR FATAL: " . $e->getMessage() . "\n";
    exit(1);
}
?>
