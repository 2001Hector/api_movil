<?php
// db.php - Conexión MySQL para Hostinger
class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        $host = getenv('DB_HOST') ?: 'mysql.hostinger.com';
        $db   = getenv('DB_NAME') ?: 'u680910350_pedidos';
        $user = getenv('DB_USER') ?: 'u680910350_kevinB';
        $pass = getenv('DB_PASS') ?: 'Kevin2025M';
        $port = getenv('DB_PORT') ?: '3306';
        
        $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
        
        try {
            $this->pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            error_log("✅ Conexión MySQL lista");
        } catch (PDOException $e) {
            error_log("❌ Error de conexión: " . $e->getMessage());
            throw $e;
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->pdo;
    }
}
?>