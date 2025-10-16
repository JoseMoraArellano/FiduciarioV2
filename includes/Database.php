<?php
/**
 * Clase Database - Manejo de conexión PDO para PostgreSQL
 * Patrón Singleton para una única instancia de conexión
 */
class Database {
    
    private static $instance = null;
    private $connection;
    
    /**
     * Constructor privado para Singleton
     */
    private function __construct() {
        try {
            $dsn = sprintf(
                "pgsql:host=%s;port=%s;dbname=%s",
                DB_HOST,
                DB_PORT,
                DB_NAME
            );
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false
            ];
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
            
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            die("Error de conexión a la base de datos. Contacte al administrador.");
        }
    }
    
    /**
     * Obtiene la instancia única de la conexión
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Obtiene la conexión PDO
     */
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * Previene la clonación del objeto
     */
    private function __clone() {}
    
    /**
     * Previene la deserialización del objeto
     */
    public function __wakeup() {
        throw new Exception("No se puede deserializar un singleton.");
    }
}
?>