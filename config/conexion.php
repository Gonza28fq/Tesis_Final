<?php
// =============================================
// config/conexion.php
// Clase de Conexión a Base de Datos
// =============================================

class Database {
    private static $instance = null;
    private $conexion;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $this->conexion = new PDO($dsn, DB_USER, DB_PASS, $options);
            
        } catch (PDOException $e) {
            if (MODO_DEBUG) {
                die("Error de conexión: " . $e->getMessage());
            } else {
                die("Error al conectar con la base de datos. Contacte al administrador.");
            }
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConexion() {
        return $this->conexion;
    }
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            if (MODO_DEBUG) {
                throw new Exception("Error en query: " . $e->getMessage() . "\nSQL: " . $sql);
            } else {
                throw new Exception("Error al ejecutar la consulta.");
            }
        }
    }
    
    public function select($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    public function selectOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    public function insert($sql, $params = []) {
        $this->query($sql, $params);
        return $this->conexion->lastInsertId();
    }
    
    public function update($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    public function delete($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    // Método COUNT que faltaba
    public function count($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch(PDO::FETCH_NUM);
        return $result ? (int)$result[0] : 0;
    }
    
    // Método para verificar si existe un registro
    public function exists($sql, $params = []) {
        return $this->count($sql, $params) > 0;
    }
    
    // Método para obtener el último ID insertado
    public function lastInsertId() {
        return $this->conexion->lastInsertId();
    }
    
    // Transacciones
    public function beginTransaction() {
        return $this->conexion->beginTransaction();
    }
    
    public function commit() {
        return $this->conexion->commit();
    }
    
    public function rollBack() {
        return $this->conexion->rollBack();
    }
    
    // Método para ejecutar múltiples queries (útil para migraciones)
    public function exec($sql) {
        return $this->conexion->exec($sql);
    }
    
    // Método para preparar statements
    public function prepare($sql) {
        return $this->conexion->prepare($sql);
    }
    
    // Evitar clonación
    private function __clone() {}
    
    // Evitar deserialización
    public function __wakeup() {
        throw new Exception("No se puede deserializar la clase Database");
    }
}

// Función helper para obtener la conexión
function getDB() {
    return Database::getInstance();
}
?>