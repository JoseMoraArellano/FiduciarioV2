<?php
/**
 * Manejador AJAX para las peticiones de sincronización
 * Procesa las solicitudes desde la interfaz web
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
//require_once 'sincronizar_diario.php';
 //require_once 'sincronizar_historicos.php';
require_once __DIR__ . '/sincronizar_diario.php';
require_once __DIR__ . '/sincronizar_historicos.php';

// Configurar tiempo máximo de ejecución para procesos largos
set_time_limit(1800); // 30 minutos

// Verificar que sea una petición AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    http_response_code(403);
    die(json_encode(['error' => 'Acceso no autorizado']));
}

// Obtener usuario de sesión
$usuario = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'Admin';

// Obtener acción solicitada
$accion = isset($_POST['accion']) ? $_POST['accion'] : '';
$indicador = isset($_POST['indicador']) ? $_POST['indicador'] : '';

// Preparar respuesta
$respuesta = [
    'success' => false,
    'mensaje' => '',
    'data' => null,
    'timestamp' => date('Y-m-d H:i:s')
];

try {
    switch($accion) {
        
        case 'sincronizar_diario':
            $sincronizador = new SincronizadorDiario($usuario);
            
            if ($indicador && $indicador !== 'todos') {
                // Sincronizar indicador específico
                $metodo = 'sincronizar' . ucfirst($indicador);
                
                if (method_exists($sincronizador, $metodo)) {
                    $resultado = $sincronizador->$metodo();
                    
                    $respuesta['success'] = $resultado;
                    $respuesta['mensaje'] = $resultado ? 
                        "✅ $indicador sincronizado correctamente" : 
                        "❌ Error al sincronizar $indicador";
                    
                    // Obtener estadísticas
                    $tablas = [
                        'tiie' => ['tabla' => 't_tiie', 'campo' => 'dato'],
                        'tipoCambio' => ['tabla' => 't_tdc', 'campo' => 'tasa'],
                        'inpc' => ['tabla' => 't_inpc', 'campo' => 'indice'],
                        'cpp' => ['tabla' => 't_cpp', 'campo' => 'tasa'],
                        'udis' => ['tabla' => 't_udis', 'campo' => 'valor']
                    ];
                    
                    if (isset($tablas[$indicador])) {
                        $stats = $sincronizador->obtenerEstadisticas(
                            $tablas[$indicador]['tabla'],
                            $tablas[$indicador]['campo']
                        );
                        $respuesta['data'] = ['estadisticas' => $stats];
                    }
                } else {
                    $respuesta['mensaje'] = "Indicador no válido: $indicador";
                }
                
            } else {
                // Sincronizar todos
                $resultados = $sincronizador->sincronizarTodo();
                
                $respuesta['success'] = !in_array(false, $resultados, true);
                $respuesta['data'] = ['resultados' => $resultados];
                
                $exitosos = array_filter($resultados);
                $respuesta['mensaje'] = sprintf(
                    "Sincronización completa: %d de %d indicadores actualizados",
                    count($exitosos),
                    count($resultados)
                );
            }
            break;
            
        case 'sincronizar_historico':
            $sincronizador = new SincronizadorHistorico($usuario);
            
            // Obtener fechas personalizadas si se enviaron
            $fecha_inicio = isset($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : null;
            $fecha_fin = isset($_POST['fecha_fin']) ? $_POST['fecha_fin'] : null;
            
            if ($indicador && $indicador !== 'todos') {
                // Cargar histórico de indicador específico
                $metodos = [
                    'tiie' => 'cargarTiieHistorico',
                    'tdc' => 'cargarTdcHistorico',
                    'inpc' => 'cargarInpcHistorico',
                    'cpp' => 'cargarCppHistorico',
                    'udis' => 'cargarUdisHistorico'
                ];
                
                if (isset($metodos[$indicador])) {
                    $metodo = $metodos[$indicador];
                    
                    // Usar fechas por defecto si no se especificaron
                    if (!$fecha_inicio) {
                        $fechas_defecto = [
                            'tiie' => '1993-01-21',
                            'tdc' => '2021-11-01',
                            'inpc' => '1969-01-01',
                            'cpp' => '1975-08-01',
                            'udis' => '1995-04-04'
                        ];
                        $fecha_inicio = $fechas_defecto[$indicador];
                    }
                    
                    $resultado = $sincronizador->$metodo($fecha_inicio, $fecha_fin);
                    
                    if ($resultado && is_array($resultado)) {
                        $respuesta['success'] = true;
                        $respuesta['data'] = $resultado;
                        $respuesta['mensaje'] = sprintf(
                            "✅ Carga histórica de %s completada: %d insertados, %d omitidos",
                            strtoupper($indicador),
                            $resultado['insertados'],
                            $resultado['omitidos']
                        );
                    } else {
                        $respuesta['mensaje'] = "❌ Error al cargar histórico de $indicador";
                    }
                } else {
                    $respuesta['mensaje'] = "Indicador no válido: $indicador";
                }
                
            } else {
                // Cargar todos los históricos
                $resultados = $sincronizador->cargarTodosLosHistoricos();
                
                $respuesta['success'] = true;
                $respuesta['data'] = $resultados;
                
                $total_insertados = 0;
                $total_omitidos = 0;
                
                foreach ($resultados as $key => $res) {
                    if (is_array($res)) {
                        $total_insertados += $res['insertados'];
                        $total_omitidos += $res['omitidos'];
                    }
                }
                
                $respuesta['mensaje'] = sprintf(
                    "✅ Carga histórica completa: %d registros insertados, %d omitidos",
                    $total_insertados,
                    $total_omitidos
                );
            }
            break;
            
        case 'obtener_estadisticas':
            $sincronizador = new SincronizadorDiario($usuario);
            
            $estadisticas = [];
            
            $indicadores = [
                'tiie' => ['tabla' => 't_tiie', 'campo' => 'dato', 'nombre' => 'TIIE a 28 días'],
                'tdc' => ['tabla' => 't_tdc', 'campo' => 'tasa', 'nombre' => 'Tipo de Cambio'],
                'inpc' => ['tabla' => 't_inpc', 'campo' => 'indice', 'nombre' => 'INPC'],
                'cpp' => ['tabla' => 't_cpp', 'campo' => 'tasa', 'nombre' => 'CPP'],
                'udis' => ['tabla' => 't_udis', 'campo' => 'valor', 'nombre' => 'UDIS']
            ];
            
            foreach ($indicadores as $key => $info) {
                $stats = $sincronizador->obtenerEstadisticas($info['tabla'], $info['campo']);
                if ($stats) {
                    $stats['nombre'] = $info['nombre'];
                    $estadisticas[$key] = $stats;
                }
            }
            
            $respuesta['success'] = true;
            $respuesta['data'] = $estadisticas;
            $respuesta['mensaje'] = 'Estadísticas obtenidas correctamente';
            break;
            
        case 'ver_log':
            $log_file = dirname(dirname(__DIR__)) . '/banxico.log';
            
            if (file_exists($log_file)) {
                // Obtener últimas 100 líneas del log
                $lineas = file($log_file);
                $ultimas = array_slice($lineas, -100);
                
                $respuesta['success'] = true;
                $respuesta['data'] = ['log' => implode('', $ultimas)];
                $respuesta['mensaje'] = 'Log cargado correctamente';
            } else {
                $respuesta['mensaje'] = 'Archivo de log no encontrado';
            }
            break;
            
        case 'limpiar_log':
            $log_file = dirname(dirname(__DIR__)) . '/banxico.log';
            
            if (file_exists($log_file)) {
                file_put_contents($log_file, '');
                $respuesta['success'] = true;
                $respuesta['mensaje'] = 'Log limpiado correctamente';
            } else {
                $respuesta['mensaje'] = 'Archivo de log no encontrado';
            }
            break;
            
        default:
            $respuesta['mensaje'] = 'Acción no válida';
            break;
    }
    
} catch (Exception $e) {
    $respuesta['success'] = false;
    $respuesta['mensaje'] = 'Error: ' . $e->getMessage();
    
    // Registrar error en log
    $banxico = new BanxicoService();
    $banxico->escribirLog("ERROR", "Error en ajax_handler: " . $e->getMessage());
}

// Enviar respuesta JSON
header('Content-Type: application/json');
echo json_encode($respuesta);
?>