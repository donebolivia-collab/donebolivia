<?php
/**
 * Configuración de Base de Datos - Híbrida (Local + Producción)
 * Diseñada para soportar subidas completas sin romper el sitio.
 */

// Detectar si estamos en el servidor (donebolivia.com) o en local
$es_produccion = (strpos($_SERVER['HTTP_HOST'], 'donebolivia.com') !== false);

if ($es_produccion) {
    // ==========================================
    //   ENTORNO DE PRODUCCIÓN (SERVIDO REAL)
    // ==========================================
    
    // Configuración desde InfinityFree
     define('DB_HOST', 'sql209.infinityfree.com'); 
     define('DB_NAME', 'if0_39881623_cambalache'); // Nombre corregido según tu panel
     define('DB_USER', 'if0_39881623');
     define('DB_PASS', 'Yhefri123');
    
    // Configuración del Sitio
    define('SITE_URL', 'https://donebolivia.com');
    define('SITE_NAME', 'Done!');
    define('UPLOAD_PATH', __DIR__ . '/../uploads/');
    define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

    // WhatsApp y Correos
    define('WHATSAPP_MESSAGE', 'Hola! Me interesa tu producto: ');
    define('SMTP_FROM_EMAIL', 'no-reply@donebolivia.com');
    define('SMTP_FROM_NAME', 'Done!');
    define('APP_VERSION', '4.0.2');
    
    // Seguridad: Ocultar errores técnicos a los visitantes
    ini_set('display_errors', 0);
    error_reporting(0);

} else {
    // ==========================================
    //   ENTORNO LOCAL (TU COMPUTADORA)
    // ==========================================
    
    // Cargar sistema de variables de entorno local (.env)
    require_once __DIR__ . '/env.php';

    // Si env() falla, usa estos valores por defecto para XAMPP/Localhost
    define('DB_HOST', env('DB_HOST', 'localhost'));
    define('DB_NAME', env('DB_NAME', 'cambalache_db'));
    define('DB_USER', env('DB_USER', 'root'));
    define('DB_PASS', env('DB_PASS', ''));
    
    define('SITE_URL', env('SITE_URL', 'http://localhost:8000'));
    define('SITE_NAME', env('SITE_NAME', 'Done! (Local)'));
    define('UPLOAD_PATH', __DIR__ . '/../uploads/');
    define('MAX_FILE_SIZE', env('MAX_FILE_SIZE', 5 * 1024 * 1024));
    
    define('WHATSAPP_MESSAGE', env('WHATSAPP_MESSAGE', 'Hola! Me interesa tu producto: '));
    define('SMTP_FROM_EMAIL', env('SMTP_FROM_EMAIL', 'no-reply@donebolivia.com'));
    define('SMTP_FROM_NAME', env('SMTP_FROM_NAME', 'Done!'));
    define('APP_VERSION', '4.0.1-dev');

    // Debug: Mostrar todos los errores en local
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

// ==========================================
//   CLASE DE CONEXIÓN (NO TOCAR)
// ==========================================

class Database {
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    private $conn = null;

    public function getConnection() {
        if ($this->conn === null) {
            try {
                $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";
                $this->conn = new PDO($dsn, $this->username, $this->password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]);
            } catch(PDOException $e) {
                // En producción, mostrar mensaje amigable
                if (strpos($_SERVER['HTTP_HOST'], 'donebolivia.com') !== false) {
                    error_log("DB Error: " . $e->getMessage()); // Guardar en log del servidor
                    // MODO DEBUG TEMPORAL: Mostrar el error real en pantalla
                    die("<div style='background:#ff6b35; color:white; padding:20px; text-align:center; font-family:sans-serif;'>
                            <h2>Error de Conexión</h2>
                            <p>El servidor dice: <strong>" . $e->getMessage() . "</strong></p>
                            <p>Verifica que el nombre de la base de datos en el archivo config/database.php sea idéntico al de tu panel.</p>
                         </div>");
                } else {
                    // En local, mostrar el error real
                    die("<h3>Error de Conexión Local:</h3> " . $e->getMessage());
                }
            }
        }
        return $this->conn;
    }

    public function closeConnection() {
        $this->conn = null;
    }
}

// Función global
function getDB() {
    static $database = null;
    if ($database === null) {
        $database = new Database();
    }
    return $database->getConnection();
}
?>
