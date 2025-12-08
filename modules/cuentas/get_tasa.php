<?php
// Incluir archivos necesarios en el orden correcto
$baseDir = dirname(dirname(__DIR__));

// Primero cargar el archivo de configuración
if (file_exists($baseDir . '/includes/config.php')) {
    require_once $baseDir . '/includes/config.php';
} elseif (file_exists($baseDir . '/config.php')) {
    require_once $baseDir . '/config.php';
}

// Luego cargar Database
require_once $baseDir . '/includes/Database.php';

// Headers
header('Content-Type: application/json');

try {
    // Validar parámetros
    if (!isset($_GET['fecha']) || !isset($_GET['tipo'])) {
        throw new Exception('Parámetros faltantes');
    }

    $fecha = $_GET['fecha'];
    $tipoMoneda = $_GET['tipo'];

    // Obtener conexión
    $db = Database::getInstance()->getConnection();
    
    if (!$db) {
        throw new Exception('No se pudo obtener conexión a la base de datos');
    }

    // Consulta - asegurarse de comparar solo la fecha sin hora
    $query = "SELECT tasa FROM t_tdc WHERE DATE(fecha) = DATE(:fecha) LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute(['fecha' => $fecha]);
    
    $tasa = $stmt->fetchColumn();

    if ($tasa !== false && $tasa !== null) {
        echo json_encode([
            'success' => true,
            'tasa' => number_format((float)$tasa, 4, '.', '')
        ]);
    } else {
        // Si no encuentra, intentar buscar registros cercanos para debug
        $queryDebug = "SELECT DATE(fecha) as fecha, tasa FROM t_tdc ORDER BY ABS(EXTRACT(EPOCH FROM (fecha - :fecha::timestamp))) LIMIT 3";
        $stmtDebug = $db->prepare($queryDebug);
        $stmtDebug->execute(['fecha' => $fecha]);
        $cercanos = $stmtDebug->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
    'success' => false,
    'message' => 'No se encontró tasa para la fecha: ' . $fecha . "\n" .  "\n" . 'Favor de actualizar la API Banxico',
    'fechas_cercanas' => $cercanos
]);
    }

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}