<?php
/**
 * AUDITORÍA EXPERTA DE ALMACENAMIENTO
 * 
 * Sistema completo para monitorear y auditar el uso de almacenamiento
 * Genera reportes detallados y alertas de crecimiento
 */

require_once __DIR__ . '/../config/database.php';

class StorageAuditor {
    private $db;
    private $uploadPath;
    private $report;
    
    public function __construct() {
        $this->db = getDB();
        $this->uploadPath = UPLOAD_PATH;
        $this->report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'storage' => [],
            'database' => [],
            'growth' => [],
            'recommendations' => []
        ];
    }
    
    public function executeAudit() {
        echo "📊 INICIANDO AUDITORÍA DE ALMACENAMIENTO\n";
        echo "⏰ " . date('Y-m-d H:i:s') . "\n";
        echo str_repeat("=", 60) . "\n\n";
        
        $this->analyzeStorageUsage();
        $this->analyzeDatabaseGrowth();
        $this->generateRecommendations();
        $this->generateReport();
        
        return $this->report;
    }
    
    private function analyzeStorageUsage() {
        echo "💾 ANALIZANDO USO DE ALMACENAMIENTO...\n";
        
        $directories = [
            'productos' => 'Imágenes de productos',
            'perfiles' => 'Fotos de perfil',
            'portadas' => 'Fondos de perfil',
            'logos' => 'Logos de tiendas'
        ];
        
        $totalSize = 0;
        $totalFiles = 0;
        
        foreach ($directories as $dir => $description) {
            $path = $this->uploadPath . $dir;
            $stats = $this->getDirectoryStats($path);
            
            $this->report['storage'][$dir] = [
                'description' => $description,
                'path' => $path,
                'files' => $stats['files'],
                'size_bytes' => $stats['size'],
                'size_formatted' => $this->formatBytes($stats['size']),
                'avg_file_size' => $stats['files'] > 0 ? round($stats['size'] / $stats['files']) : 0
            ];
            
            $totalSize += $stats['size'];
            $totalFiles += $stats['files'];
            
            echo "  📁 $dir: {$stats['files']} archivos, " . $this->formatBytes($stats['size']) . "\n";
        }
        
        $this->report['storage']['total'] = [
            'files' => $totalFiles,
            'size_bytes' => $totalSize,
            'size_formatted' => $this->formatBytes($totalSize)
        ];
        
        echo "\n📈 TOTAL: $totalFiles archivos, " . $this->formatBytes($totalSize) . "\n\n";
    }
    
    private function getDirectoryStats($path) {
        if (!is_dir($path)) {
            return ['files' => 0, 'size' => 0];
        }
        
        $files = glob($path . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE);
        $totalSize = 0;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $totalSize += filesize($file);
            }
        }
        
        return ['files' => count($files), 'size' => $totalSize];
    }
    
    private function analyzeDatabaseGrowth() {
        echo "🗃️ ANALIZANDO CRECIMIENTO DE BASE DE DATOS...\n";
        
        // Productos por mes
        $stmt = $this->db->query("
            SELECT DATE_FORMAT(fecha_publicacion, '%Y-%m') as mes, COUNT(*) as total
            FROM productos 
            WHERE fecha_publicacion >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(fecha_publicacion, '%Y-%m')
            ORDER BY mes DESC
        ");
        
        $this->report['growth']['products_monthly'] = $stmt->fetchAll();
        
        // Usuarios por mes
        $stmt = $this->db->query("
            SELECT DATE_FORMAT(fecha_registro, '%Y-%m') as mes, COUNT(*) as total
            FROM usuarios 
            WHERE fecha_registro >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(fecha_registro, '%Y-%m')
            ORDER BY mes DESC
        ");
        
        $this->report['growth']['users_monthly'] = $stmt->fetchAll();
        
        echo "  📊 Productos últimos 12 meses: " . count($this->report['growth']['products_monthly']) . " meses\n";
        echo "  👥 Usuarios últimos 12 meses: " . count($this->report['growth']['users_monthly']) . " meses\n\n";
    }
    
    private function generateRecommendations() {
        echo "💡 GENERANDO RECOMENDACIONES...\n";
        
        $recommendations = [];
        
        // Analizar tamaño total
        $totalSize = $this->report['storage']['total']['size_bytes'];
        
        if ($totalSize > 5 * 1024 * 1024 * 1024) { // > 5GB
            $recommendations[] = [
                'priority' => 'HIGH',
                'message' => 'Almacenamiento elevado (>5GB). Considera implementar CDN',
                'action' => 'Implementar CDN externo para imágenes'
            ];
        }
        
        // Analizar archivos por directorio
        foreach ($this->report['storage'] as $dir => $stats) {
            if ($dir === 'total') continue;
            
            if ($stats['files'] > 10000) {
                $recommendations[] = [
                    'priority' => 'MEDIUM',
                    'message' => "Directorio $dir tiene {$stats['files']} archivos",
                    'action' => 'Ejecutar limpieza de imágenes huérfanas'
                ];
            }
            
            if ($stats['avg_file_size'] > 2 * 1024 * 1024) { // > 2MB promedio
                $recommendations[] = [
                    'priority' => 'LOW',
                    'message' => "Archivos grandes en $dir (promedio: " . $this->formatBytes($stats['avg_file_size']) . ")",
                    'action' => 'Optimizar compresión de imágenes'
                ];
            }
        }
        
        $this->report['recommendations'] = $recommendations;
        
        foreach ($recommendations as $rec) {
            $priority = $rec['priority'] === 'HIGH' ? '🔴' : ($rec['priority'] === 'MEDIUM' ? '🟡' : '🟢');
            echo "  $priority {$rec['message']}\n";
            echo "     💡 Acción: {$rec['action']}\n";
        }
        
        echo "\n";
    }
    
    private function generateReport() {
        $reportFile = __DIR__ . '/../storage_audit_' . date('Y-m-d_H-i-s') . '.json';
        file_put_contents($reportFile, json_encode($this->report, JSON_PRETTY_PRINT));
        
        echo "📄 REPORTE GENERADO: $reportFile\n";
        echo str_repeat("=", 60) . "\n";
        echo "✅ AUDITORÍA COMPLETADA\n\n";
        
        echo "📋 RESUMEN EJECUTIVO:\n";
        echo "• Total archivos: {$this->report['storage']['total']['files']}\n";
        echo "• Espacio usado: {$this->report['storage']['total']['size_formatted']}\n";
        echo "• Recomendaciones: " . count($this->report['recommendations']) . "\n";
        echo "• Ver reporte completo: $reportFile\n";
    }
    
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

// Ejecución
try {
    $auditor = new StorageAuditor();
    $auditor->executeAudit();
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
