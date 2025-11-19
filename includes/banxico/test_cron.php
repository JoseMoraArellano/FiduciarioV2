<?php
/**
 * Prueba manual del cron job
 * Ejecutar: php test_cron.php
 */

echo "Probando configuración...\n";

// Verificar que estamos en CLI
if (php_sapi_name() !== 'cli') {
    die("ERROR: Este script debe ejecutarse desde línea de comandos\n");
}

// Verificar archivos necesarios
$archivos = [
//    'config.php' =>   __DIR__ . '/config.php',
   'config.php' => dirname(dirname(__DIR__)) . '/config.php',
//    'Database.php' => __DIR__ . '/Database.php',
'Database.php' => dirname(dirname(__DIR__)) . '/includes/Database.php',
    'sincronizar_diario.php' => __DIR__ . '/sincronizar_diario.php'
];

foreach ($archivos as $nombre => $ruta) {
    if (file_exists($ruta)) {
        echo "✓ $nombre encontrado\n";
    } else {
        echo "✗ $nombre NO encontrado en: $ruta\n";
        exit(1);
    }
}

// Intentar conexión a BD
echo "\nProbando conexión a base de datos...\n";
//require_once __DIR__ . 'config.php';
require_once dirname(dirname(__DIR__)) . '/config.php';
//require_once __DIR__ . '/Database.php';
require_once dirname(dirname(__DIR__)) . '/includes/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    echo "✓ Conexión exitosa\n";
    
    // Verificar tabla t_const
    $stmt = $db->query("SELECT COUNT(*) as total FROM t_const WHERE nom IN ('tiie','tdc','inpc','cpp','udis')");
    $result = $stmt->fetch();
    echo "✓ Encontrados " . $result['total'] . " endpoints en t_const\n";
    
} catch (Exception $e) {
    echo "✗ Error de conexión: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✓ Todas las pruebas pasaron. El cron debería funcionar correctamente.\n";
echo "Puedes ejecutar: php " . __DIR__ . "/cron_sincronizar.php\n";
?>