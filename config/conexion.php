<?php
/**
 * Archivo de configuración de conexión a la base de datos
 * Sistema de Gestión Comercial
 */

// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'sistema_gestion_comercial');
define('DB_USER', 'root');  // Cambiar según tu configuración
define('DB_PASS', '40828532Gp#.');      // Cambiar según tu configuración
define('DB_CHARSET', 'utf8mb4');

// Configuración de zona horaria
date_default_timezone_set('America/Argentina/Buenos_Aires');

/**
 * Clase de conexión a la base de datos usando PDO
 */
class Database {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            
            $this->conn = new PDO($dsn, DB_USER, DB_PASS, $options);
            
        } catch(PDOException $e) {
            die("Error de conexión: " . $e->getMessage());
        }
    }
    
    /**
     * Obtener instancia única de la conexión (Singleton)
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Obtener la conexión PDO
     */
    public function getConnection() {
        return $this->conn;
    }
    
    /**
     * Prevenir clonación del objeto
     */
    private function __clone() {}
    
    /**
     * Prevenir deserialización del objeto
     */
    public function __wakeup() {
        throw new Exception("No se puede deserializar singleton");
    }
}

/**
 * Función auxiliar para obtener la conexión rápidamente
 */
function getDB() {
    return Database::getInstance()->getConnection();
}

/**
 * Función para sanitizar datos de entrada
 */
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Función para responder con JSON
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Función para validar sesión de usuario
 */
function validarSesion() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['id_vendedor']) || !isset($_SESSION['usuario'])) {
        header('Location: /login.php');
        exit;
    }
}

/**
 * Función para verificar permisos
 */
function tienePermiso($permiso) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // El administrador siempre tiene todos los permisos
    if ($_SESSION['rol'] === 'Administrador') {
        return true;
    }
    
    // Verificar si el usuario tiene el permiso específico
    if (isset($_SESSION['permisos']) && in_array($permiso, $_SESSION['permisos'])) {
        return true;
    }
    
    return false;
}

/**
 * Función para registrar actividad en auditoría
 */
function registrarAuditoria($id_vendedor, $tipo_acceso, $ip = null) {
    try {
        $db = getDB();
        $sql = "INSERT INTO Auditoria_Accesos (id_vendedor, tipo_acceso, ip, user_agent) 
                VALUES (:id_vendedor, :tipo_acceso, :ip, :user_agent)";
        $stmt = $db->prepare($sql);
        
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido';
        $ip = $ip ?? $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
        
        $stmt->execute([
            ':id_vendedor' => $id_vendedor,
            ':tipo_acceso' => $tipo_acceso,
            ':ip' => $ip,
            ':user_agent' => $user_agent
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Error en auditoría: " . $e->getMessage());
        return false;
    }
}

/**
 * Función para formatear moneda
 */
function formatearMoneda($valor) {
    return '$' . number_format($valor, 2, ',', '.');
}

/**
 * Función para formatear fecha
 */
function formatearFecha($fecha) {
    $timestamp = strtotime($fecha);
    return date('d/m/Y H:i', $timestamp);
}
?>