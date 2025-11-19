#!/usr/bin/php
<?php
/**
 * Script de sincronización automática para cron job
 * Ejecuta la sincronización diaria de todos los indicadores
 * 
 * Uso: php /ruta/completa/includes/banxico/cron_sincronizar.php
 * Cron ejemplo: 0 6 * * * php /var/www/html/includes/banxico/cron_sincronizar.php
 * Salida redirigida a un archivo de log:
* 0 6 * * * /usr/bin/php /var/www/html/includes/banxico/cron_sincronizar.php >> /var/www/html/banxico_cron_output.log 2>&1
* Cron ejemplo con restricción de días (lunes a viernes):
* 0 7 * * 1-5 /usr/bin/php /ruta/cron_sincronizar.php
* Corecto: 0 7 * * 1-5 /usr/bin/php /var/www/html/includes/banxico/cron_sincronizar.php >> /var/www/html/banxico_cron_output.log 2>&1
* /var/www/html/includes/banxico/cron_sincronizar.php
 */

// Prevenir ejecución desde navegador
if (php_sapi_name() !== 'cli') {
    die("Este script solo puede ejecutarse desde línea de comandos\n");
}

// Configuración
// require_once __DIR__ .  . '/config.php';
require_once '../config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/sincronizar_diario.php';

// Configurar zona horaria
date_default_timezone_set('America/Mexico_City');

// Crear archivo de log específico para cron
$log_cron = dirname(dirname(__DIR__)) . '/banxico_cron.log';

function escribirLogCron($mensaje) {
    global $log_cron;
    $fecha = date('Y-m-d H:i:s');
    $linea = "[" . $fecha . "] " . $mensaje . PHP_EOL;
    file_put_contents($log_cron, $linea, FILE_APPEND | LOCK_EX);
}

// Función principal
function ejecutarSincronizacion() {
    escribirLogCron("========================================");
    escribirLogCron("Iniciando sincronización automática");
    escribirLogCron("========================================");
    
    try {
        // Usuario para registros (cron job)
        $usuario = 'CRON';
        
        // Crear instancia del sincronizador
        $sincronizador = new SincronizadorDiario($usuario);
        
        // Ejecutar sincronización de todos los indicadores
        $resultados = $sincronizador->sincronizarTodo();
        
        // Registrar resultados
        $exitosos = 0;
        $fallidos = 0;
        
        foreach ($resultados as $indicador => $exito) {
            if ($exito) {
                $exitosos++;
                escribirLogCron("✓ " . strtoupper($indicador) . " sincronizado correctamente");
            } else {
                $fallidos++;
                escribirLogCron("✗ " . strtoupper($indicador) . " falló en sincronización");
            }
        }
        
        // Obtener estadísticas actuales
        escribirLogCron("----------------------------------------");
        escribirLogCron("Estadísticas después de sincronización:");
        
        $tablas = [
            'tiie' => ['tabla' => 't_tiie', 'campo' => 'dato'],
            'tdc' => ['tabla' => 't_tdc', 'campo' => 'tasa'],
            'inpc' => ['tabla' => 't_inpc', 'campo' => 'indice'],
            'cpp' => ['tabla' => 't_cpp', 'campo' => 'tasa'],
            'udis' => ['tabla' => 't_udis', 'campo' => 'valor']
        ];
        
        foreach ($tablas as $key => $config) {
            $stats = $sincronizador->obtenerEstadisticas(
                $config['tabla'], 
                $config['campo']
            );
            
            if ($stats) {
                escribirLogCron(
                    strtoupper($key) . ": " . 
                    "Total registros: " . $stats['total_registros'] . 
                    ", Último valor: " . $stats['ultimo_dato'] . 
                    ", Fecha: " . $stats['fecha_ultimo']
                );
            }
        }
        
        escribirLogCron("----------------------------------------");
        escribirLogCron("Resumen: $exitosos exitosos, $fallidos fallidos");
        
        // Enviar notificación por email si hay fallos (opcional)
        if ($fallidos > 0) {
            enviarNotificacionError($fallidos, $resultados);
        }
        
        escribirLogCron("Sincronización automática completada");
        escribirLogCron("========================================\n");
        
        // Retornar código de salida
        return $fallidos === 0 ? 0 : 1;
        
    } catch (Exception $e) {
        escribirLogCron("ERROR CRÍTICO: " . $e->getMessage());
        escribirLogCron("Trace: " . $e->getTraceAsString());
        escribirLogCron("========================================\n");
        return 2;
    }
}

// Función opcional para enviar notificaciones por email
function enviarNotificacionError($fallidos, $resultados) {
    // Configurar aquí si deseas notificaciones por email
    $para = 'tu-email@ejemplo.com'; // Cambiar por tu email
    $asunto = 'Error en sincronización Banxico - ' . date('Y-m-d');
    
    $mensaje = "Hubo $fallidos errores en la sincronización automática:\n\n";
    
    foreach ($resultados as $indicador => $exito) {
        if (!$exito) {
            $mensaje .= "- " . strtoupper($indicador) . " falló\n";
        }
    }
    
    $mensaje .= "\nRevisar log en: " . dirname(dirname(__DIR__)) . "/banxico.log\n";
    
    // Descomentar si quieres activar emails
    // mail($para, $asunto, $mensaje);
    
    escribirLogCron("Notificación de error preparada (email desactivado)");
}

// Ejecutar sincronización
$codigo_salida = ejecutarSincronizacion();

// Salir con código apropiado
exit($codigo_salida);
?>