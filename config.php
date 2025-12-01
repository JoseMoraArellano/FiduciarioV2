<?php
/**
 * Configuración de conexión a PostgreSQL
 */
define('APP_NAME', 'Fiduciario');
define('ROOT_PATH', __DIR__);
define('DB_HOST', '10.1.1.152');
define('DB_PORT', '5432');
define('DB_NAME', 'tes_db');
define('DB_USER', 'postgres');
define('DB_PASS', 'O27j19e07xz');

/**
 * Obtener conexión PDO a PostgreSQL
 */
function getConnection() {
    try {
        $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch(PDOException $e) {
        die("Error de conexión: " . $e->getMessage());
    }
}

/**
 * Obtener valor de constante desde t_const
 */
function getConstant($nom) {
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT val FROM t_const WHERE nom = :nom AND activo = true LIMIT 1");
    $stmt->execute(['nom' => $nom]);
    $result = $stmt->fetch();
    return $result ? $result['val'] : null;
}

/**
 * Actualizar valor de constante en t_const
 */
function updateConstant($nom, $id, $newValue, $userId = 1) {
    $pdo = getConnection();
    $stmt = $pdo->prepare("
        UPDATE t_const 
        SET val = :val, 
            fechaedit = CURRENT_TIMESTAMP, 
            useredit = :useredit 
        WHERE nom = :nom AND id = :id
    ");
    return $stmt->execute([
        'val' => $newValue,
        'useredit' => $userId,
        'nom' => $nom,
        'id' => $id
    ]);
}
?>