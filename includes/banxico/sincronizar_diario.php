<?php
/**
 * Sincronización diaria de indicadores financieros
 * Obtiene datos oportunos (más recientes) de Banxico
 */

// require_once 'BanxicoService.php';
//require_once __DIR__ . '/includes/banxico/BanxicoService.php';
// require_once __DIR__ . '/include/banxico/BanxicoService.php';
require_once __DIR__ . '/BanxicoService.php';




class SincronizadorDiario {
    
    private $db;
    private $banxico;
    private $usuario;
    
    public function __construct($usuario = 'Admin') {
        $this->db = Database::getInstance()->getConnection();
        $this->banxico = new BanxicoService();
        $this->usuario = $usuario;
    }
    
    /**
     * Convierte fecha de formato DD/MM/YYYY a YYYY-MM-DD
     */
    private function convertirFecha($fecha_str) {
        $partes = explode('/', $fecha_str);
        return $partes[2] . '-' . $partes[1] . '-' . $partes[0];
    }
    
    /**
     * Sincroniza TIIE
     */
    public function sincronizarTiie() {
        try {
            $this->banxico->escribirLog("INFO", "Iniciando sincronización de TIIE");
            
            $datos = $this->banxico->obtenerTiieOportuno();
            
            if (!$datos) {
                $this->banxico->escribirLog("ERROR", "No se obtuvieron datos de TIIE");
                return false;
            }
            
            $fecha = $this->convertirFecha($datos['fecha']);
            
            // Verificar si ya existe
            $stmt = $this->db->prepare("SELECT id FROM t_tiie WHERE fecha = :fecha");
            $stmt->execute(['fecha' => $fecha]);
            
            if ($stmt->fetch()) {
                $this->banxico->escribirLog("INFO", "TIIE para fecha $fecha ya existe");
                return true;
            }
            
            // Insertar nuevo registro
            $stmt = $this->db->prepare("
                INSERT INTO t_tiie (fecha, dato, activo, fecha_insercion, hora_insercion, usuausuario)
                VALUES (:fecha, :dato, :activo, :fecha_insercion, :hora_insercion, :usuario)
            ");
            
            $ahora = new DateTime();
            
            $resultado = $stmt->execute([
                'fecha' => $fecha,
                'dato' => $datos['dato'],
                'activo' => true,
                'fecha_insercion' => $ahora->format('Y-m-d'),
                'hora_insercion' => $ahora->format('H:i:s'),
                'usuario' => $this->usuario
            ]);
            
            if ($resultado) {
                $this->banxico->escribirLog("INFO", "✅ TIIE insertado: Fecha=" . $datos['fecha'] . ", Dato=" . $datos['dato']);
            }
            
            return $resultado;
            
        } catch (Exception $e) {
            $this->banxico->escribirLog("ERROR", "Error en sincronizarTiie: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Sincroniza Tipo de Cambio
     */
    public function sincronizarTipoCambio() {
        try {
            $this->banxico->escribirLog("INFO", "Iniciando sincronización de Tipo de Cambio");
            
            $datos = $this->banxico->obtenerTipoCambioOportuno();
            
            if (!$datos) {
                $this->banxico->escribirLog("ERROR", "No se obtuvieron datos de TDC");
                return false;
            }
            
            $fecha = $this->convertirFecha($datos['fecha']);
            
            // Verificar si ya existe
            $stmt = $this->db->prepare("SELECT id FROM t_tdc WHERE fecha = :fecha");
            $stmt->execute(['fecha' => $fecha]);
            
            if ($stmt->fetch()) {
                $this->banxico->escribirLog("INFO", "TDC para fecha $fecha ya existe");
                return true;
            }
            
            // Insertar nuevo registro
            $stmt = $this->db->prepare("
                INSERT INTO t_tdc (fecha, tasa, fecha_captura, hora_captura, usuario)
                VALUES (:fecha, :tasa, :fecha_captura, :hora_captura, :usuario)
            ");
            
            $ahora = new DateTime();
            
            $resultado = $stmt->execute([
                'fecha' => $fecha,
                'tasa' => $datos['dato'],
                'fecha_captura' => $ahora->format('Y-m-d'),
                'hora_captura' => $ahora->format('H:i:s'),
                'usuario' => $this->usuario
            ]);
            
            if ($resultado) {
                $this->banxico->escribirLog("INFO", "✅ TDC insertado: Fecha=" . $datos['fecha'] . ", Tasa=" . $datos['dato']);
            }
            
            return $resultado;
            
        } catch (Exception $e) {
            $this->banxico->escribirLog("ERROR", "Error en sincronizarTipoCambio: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Sincroniza INPC
     */
    public function sincronizarInpc() {
        try {
            $this->banxico->escribirLog("INFO", "Iniciando sincronización de INPC");
            
            $datos = $this->banxico->obtenerInpcOportuno();
            
            if (!$datos) {
                $this->banxico->escribirLog("ERROR", "No se obtuvieron datos de INPC");
                return false;
            }
            
            $fecha = $this->convertirFecha($datos['fecha']);
            
            // Verificar si ya existe
            $stmt = $this->db->prepare("SELECT id FROM t_inpc WHERE fecha = :fecha");
            $stmt->execute(['fecha' => $fecha]);
            
            if ($stmt->fetch()) {
                $this->banxico->escribirLog("INFO", "INPC para fecha $fecha ya existe");
                return true;
            }
            
            // Insertar nuevo registro
            $stmt = $this->db->prepare("
                INSERT INTO t_inpc (fecha, indice, fecha_captura, hora_captura, usuario)
                VALUES (:fecha, :indice, :fecha_captura, :hora_captura, :usuario)
            ");
            
            $ahora = new DateTime();
            
            $resultado = $stmt->execute([
                'fecha' => $fecha,
                'indice' => $datos['dato'],
                'fecha_captura' => $ahora->format('Y-m-d'),
                'hora_captura' => $ahora->format('H:i:s'),
                'usuario' => $this->usuario
            ]);
            
            if ($resultado) {
                $this->banxico->escribirLog("INFO", "✅ INPC insertado: Fecha=" . $datos['fecha'] . ", Índice=" . $datos['dato']);
            }
            
            return $resultado;
            
        } catch (Exception $e) {
            $this->banxico->escribirLog("ERROR", "Error en sincronizarInpc: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Sincroniza CPP
     */
    public function sincronizarCpp() {
        try {
            $this->banxico->escribirLog("INFO", "Iniciando sincronización de CPP");
            
            $datos = $this->banxico->obtenerCppOportuno();
            
            if (!$datos) {
                $this->banxico->escribirLog("ERROR", "No se obtuvieron datos de CPP");
                return false;
            }
            
            $fecha = $this->convertirFecha($datos['fecha']);
            
            // Verificar si ya existe
            $stmt = $this->db->prepare("SELECT id FROM t_cpp WHERE fecha = :fecha");
            $stmt->execute(['fecha' => $fecha]);
            
            if ($stmt->fetch()) {
                $this->banxico->escribirLog("INFO", "CPP para fecha $fecha ya existe");
                return true;
            }
            
            // Insertar nuevo registro
            $stmt = $this->db->prepare("
                INSERT INTO t_cpp (fecha, tasa, fecha_captura, hora_captura, usuario)
                VALUES (:fecha, :tasa, :fecha_captura, :hora_captura, :usuario)
            ");
            
            $ahora = new DateTime();
            
            $resultado = $stmt->execute([
                'fecha' => $fecha,
                'tasa' => $datos['dato'],
                'fecha_captura' => $ahora->format('Y-m-d'),
                'hora_captura' => $ahora->format('H:i:s'),
                'usuario' => $this->usuario
            ]);
            
            if ($resultado) {
                $this->banxico->escribirLog("INFO", "✅ CPP insertado: Fecha=" . $datos['fecha'] . ", Tasa=" . $datos['dato']);
            }
            
            return $resultado;
            
        } catch (Exception $e) {
            $this->banxico->escribirLog("ERROR", "Error en sincronizarCpp: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Sincroniza UDIS
     */
    public function sincronizarUdis() {
        try {
            $this->banxico->escribirLog("INFO", "Iniciando sincronización de UDIS");
            
            $datos = $this->banxico->obtenerUdisOportuno();
            
            if (!$datos) {
                $this->banxico->escribirLog("ERROR", "No se obtuvieron datos de UDIS");
                return false;
            }
            
            $fecha = $this->convertirFecha($datos['fecha']);
            $fecha_hoy = date('Y-m-d');
            
            // Validar que no sea fecha futura
            if ($fecha > $fecha_hoy) {
                $this->banxico->escribirLog("INFO", "UDIS fecha $fecha es futura, se omite");
                return true;
            }
            
            // Verificar si ya existe
            $stmt = $this->db->prepare("SELECT id FROM t_udis WHERE fecha = :fecha");
            $stmt->execute(['fecha' => $fecha]);
            
            if ($stmt->fetch()) {
                $this->banxico->escribirLog("INFO", "UDIS para fecha $fecha ya existe");
                return true;
            }
            
            // Insertar nuevo registro
            $stmt = $this->db->prepare("
                INSERT INTO t_udis (fecha, valor, fecha_captura, hora_captura, usuario)
                VALUES (:fecha, :valor, :fecha_captura, :hora_captura, :usuario)
            ");
            
            $ahora = new DateTime();
            
            $resultado = $stmt->execute([
                'fecha' => $fecha,
                'valor' => $datos['dato'],
                'fecha_captura' => $ahora->format('Y-m-d'),
                'hora_captura' => $ahora->format('H:i:s'),
                'usuario' => $this->usuario
            ]);
            
            if ($resultado) {
                $this->banxico->escribirLog("INFO", "✅ UDIS insertado: Fecha=" . $datos['fecha'] . ", Valor=" . $datos['dato']);
            }
            
            return $resultado;
            
        } catch (Exception $e) {
            $this->banxico->escribirLog("ERROR", "Error en sincronizarUdis: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Ejecuta todas las sincronizaciones
     */
    public function sincronizarTodo() {
        $this->banxico->escribirLog("INFO", "=== Iniciando sincronización completa ===");
        
        $resultados = [
            'tiie' => $this->sincronizarTiie(),
            'tdc' => $this->sincronizarTipoCambio(),
            'inpc' => $this->sincronizarInpc(),
            'cpp' => $this->sincronizarCpp(),
            'udis' => $this->sincronizarUdis()
        ];
        
        $todos_exitosos = !in_array(false, $resultados, true);
        
        if ($todos_exitosos) {
            $this->banxico->escribirLog("INFO", "=== ✅ Sincronización completa exitosa ===");
        } else {
            $this->banxico->escribirLog("WARNING", "=== ⚠️ Algunas sincronizaciones fallaron ===");
        }
        
        return $resultados;
    }
    
    /**
     * Obtiene estadísticas de un indicador
     */
    public function obtenerEstadisticas($tabla, $campo_valor, $campo_fecha_captura = 'fecha_captura') {
        try {
            // Total de registros
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM $tabla");
            $stmt->execute();
            $total = $stmt->fetch()['total'];
            
            if ($total == 0) {
                return [
                    'total_registros' => 0,
                    'ultimo_dato' => null,
                    'fecha_ultimo' => null,
                    'promedio_mes' => null
                ];
            }
            
            // Último registro
            $stmt = $this->db->prepare("
                SELECT fecha, $campo_valor as valor 
                FROM $tabla 
                ORDER BY fecha DESC 
                LIMIT 1
            ");
            $stmt->execute();
            $ultimo = $stmt->fetch();
            
            // Promedio último mes
            $fecha_mes_atras = date('Y-m-d', strtotime('-30 days'));
            $stmt = $this->db->prepare("
                SELECT AVG($campo_valor) as promedio 
                FROM $tabla 
                WHERE fecha >= :fecha
            ");
            $stmt->execute(['fecha' => $fecha_mes_atras]);
            $promedio = $stmt->fetch()['promedio'];
            
            return [
                'total_registros' => $total,
                'ultimo_dato' => $ultimo ? floatval($ultimo['valor']) : null,
                'fecha_ultimo' => $ultimo ? $ultimo['fecha'] : null,
                'promedio_mes' => $promedio ? round(floatval($promedio), 4) : null
            ];
            
        } catch (Exception $e) {
            $this->banxico->escribirLog("ERROR", "Error obteniendo estadísticas: " . $e->getMessage());
            return null;
        }
    }
}
?>