<?php
/**
 * Sincronización de datos históricos de Banxico
 * Carga masiva de indicadores financieros
 */

// require_once 'BanxicoService.php';
//require_once __DIR__ . '/includes/banxico/BanxicoService.php';
//require_once __DIR__ . '/include/banxico/BanxicoService.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/BanxicoService.php';



class SincronizadorHistorico {
    
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
     * Valida si un valor es válido (no es N/E, null, etc)
     */
    private function esValorValido($valor) {
        return !in_array($valor, ["N/E", "", null, "null"], true);
    }
    
    /**
     * Carga histórico de TIIE
     */
    public function cargarTiieHistorico($fecha_inicio = "1993-01-21", $fecha_fin = null) {
        $this->banxico->escribirLog("INFO", "Iniciando carga histórica de TIIE desde $fecha_inicio");
        
        try {
            $this->db->beginTransaction();
            
            $datos = $this->banxico->obtenerDatosHistoricos('tiie', $fecha_inicio, $fecha_fin, 120);
            
            if (empty($datos)) {
                $this->banxico->escribirLog("WARNING", "No se obtuvieron datos históricos de TIIE");
                return false;
            }
            
            $this->banxico->escribirLog("INFO", "Obtenidos " . count($datos) . " registros de TIIE");
            
            $registros_insertados = 0;
            $registros_omitidos = 0;
            
            $stmt = $this->db->prepare("
                INSERT INTO t_tiie (fecha, dato, activo, fecha_insercion, hora_insercion, usuausuario)
                VALUES (:fecha, :dato, :activo, :fecha_insercion, :hora_insercion, :usuario)
                ON CONFLICT (fecha) DO NOTHING
            ");
            
            foreach ($datos as $dato_info) {
                // Validar valor
                if (!$this->esValorValido($dato_info['dato'])) {
                    $registros_omitidos++;
                    continue;
                }
                
                try {
                    $valor_numerico = floatval($dato_info['dato']);
                } catch (Exception $e) {
                    $this->banxico->escribirLog("WARNING", "TIIE - Valor inválido: " . $dato_info['dato']);
                    $registros_omitidos++;
                    continue;
                }
                
                $fecha = $this->convertirFecha($dato_info['fecha']);
                $ahora = new DateTime();
                
                $stmt->execute([
                    'fecha' => $fecha,
                    'dato' => $valor_numerico,
                    'activo' => true,
                    'fecha_insercion' => $ahora->format('Y-m-d'),
                    'hora_insercion' => $ahora->format('H:i:s'),
                    'usuario' => $this->usuario
                ]);
                
                if ($stmt->rowCount() > 0) {
                    $registros_insertados++;
                }
                
                // Commit cada 100 registros
                if ($registros_insertados % 100 == 0) {
                    $this->db->commit();
                    $this->db->beginTransaction();
                    $this->banxico->escribirLog("INFO", "Insertados $registros_insertados registros de TIIE");
                }
            }
            
            $this->db->commit();
            
            $this->banxico->escribirLog("INFO", "✅ Carga de TIIE completada: $registros_insertados insertados, $registros_omitidos omitidos");
            
            return [
                'insertados' => $registros_insertados,
                'omitidos' => $registros_omitidos
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->banxico->escribirLog("ERROR", "Error en carga histórica de TIIE: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Carga histórico de Tipo de Cambio
     */
    public function cargarTdcHistorico($fecha_inicio = "2021-11-01", $fecha_fin = null) {
        $this->banxico->escribirLog("INFO", "Iniciando carga histórica de TDC desde $fecha_inicio");
        
        try {
            $this->db->beginTransaction();
            
            $datos = $this->banxico->obtenerDatosHistoricos('tdc', $fecha_inicio, $fecha_fin, 120);
            
            if (empty($datos)) {
                $this->banxico->escribirLog("WARNING", "No se obtuvieron datos históricos de TDC");
                return false;
            }
            
            $this->banxico->escribirLog("INFO", "Obtenidos " . count($datos) . " registros de TDC");
            
            $registros_insertados = 0;
            $registros_omitidos = 0;
            
            $stmt = $this->db->prepare("
                INSERT INTO t_tdc (fecha, tasa, fecha_captura, hora_captura, usuario)
                VALUES (:fecha, :tasa, :fecha_captura, :hora_captura, :usuario)
                ON CONFLICT (fecha) DO NOTHING
            ");
            
            foreach ($datos as $dato_info) {
                if (!$this->esValorValido($dato_info['dato'])) {
                    $registros_omitidos++;
                    continue;
                }
                
                try {
                    $valor_numerico = floatval($dato_info['dato']);
                } catch (Exception $e) {
                    $this->banxico->escribirLog("WARNING", "TDC - Valor inválido: " . $dato_info['dato']);
                    $registros_omitidos++;
                    continue;
                }
                
                $fecha = $this->convertirFecha($dato_info['fecha']);
                $ahora = new DateTime();
                
                $stmt->execute([
                    'fecha' => $fecha,
                    'tasa' => $valor_numerico,
                    'fecha_captura' => $ahora->format('Y-m-d'),
                    'hora_captura' => $ahora->format('H:i:s'),
                    'usuario' => $this->usuario
                ]);
                
                if ($stmt->rowCount() > 0) {
                    $registros_insertados++;
                }
                
                if ($registros_insertados % 100 == 0) {
                    $this->db->commit();
                    $this->db->beginTransaction();
                    $this->banxico->escribirLog("INFO", "Insertados $registros_insertados registros de TDC");
                }
            }
            
            $this->db->commit();
            
            $this->banxico->escribirLog("INFO", "✅ Carga de TDC completada: $registros_insertados insertados, $registros_omitidos omitidos");
            
            return [
                'insertados' => $registros_insertados,
                'omitidos' => $registros_omitidos
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->banxico->escribirLog("ERROR", "Error en carga histórica de TDC: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Carga histórico de INPC
     */
    public function cargarInpcHistorico($fecha_inicio = "1969-01-01", $fecha_fin = null) {
        $this->banxico->escribirLog("INFO", "Iniciando carga histórica de INPC desde $fecha_inicio");
        
        try {
            $this->db->beginTransaction();
            
            $datos = $this->banxico->obtenerDatosHistoricos('inpc', $fecha_inicio, $fecha_fin, 120);
            
            if (empty($datos)) {
                $this->banxico->escribirLog("WARNING", "No se obtuvieron datos históricos de INPC");
                return false;
            }
            
            $this->banxico->escribirLog("INFO", "Obtenidos " . count($datos) . " registros de INPC");
            
            $registros_insertados = 0;
            $registros_omitidos = 0;
            
            $stmt = $this->db->prepare("
                INSERT INTO t_inpc (fecha, indice, fecha_captura, hora_captura, usuario)
                VALUES (:fecha, :indice, :fecha_captura, :hora_captura, :usuario)
                ON CONFLICT (fecha) DO NOTHING
            ");
            
            foreach ($datos as $dato_info) {
                if (!$this->esValorValido($dato_info['dato'])) {
                    $registros_omitidos++;
                    continue;
                }
                
                try {
                    $valor_numerico = floatval($dato_info['dato']);
                } catch (Exception $e) {
                    $this->banxico->escribirLog("WARNING", "INPC - Valor inválido: " . $dato_info['dato']);
                    $registros_omitidos++;
                    continue;
                }
                
                $fecha = $this->convertirFecha($dato_info['fecha']);
                $ahora = new DateTime();
                
                $stmt->execute([
                    'fecha' => $fecha,
                    'indice' => $valor_numerico,
                    'fecha_captura' => $ahora->format('Y-m-d'),
                    'hora_captura' => $ahora->format('H:i:s'),
                    'usuario' => $this->usuario
                ]);
                
                if ($stmt->rowCount() > 0) {
                    $registros_insertados++;
                }
                
                if ($registros_insertados % 100 == 0) {
                    $this->db->commit();
                    $this->db->beginTransaction();
                    $this->banxico->escribirLog("INFO", "Insertados $registros_insertados registros de INPC");
                }
            }
            
            $this->db->commit();
            
            $this->banxico->escribirLog("INFO", "✅ Carga de INPC completada: $registros_insertados insertados, $registros_omitidos omitidos");
            
            return [
                'insertados' => $registros_insertados,
                'omitidos' => $registros_omitidos
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->banxico->escribirLog("ERROR", "Error en carga histórica de INPC: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Carga histórico de CPP
     */
    public function cargarCppHistorico($fecha_inicio = "1975-08-01", $fecha_fin = null) {
        $this->banxico->escribirLog("INFO", "Iniciando carga histórica de CPP desde $fecha_inicio");
        
        try {
            $this->db->beginTransaction();
            
            $datos = $this->banxico->obtenerDatosHistoricos('cpp', $fecha_inicio, $fecha_fin, 120);
            
            if (empty($datos)) {
                $this->banxico->escribirLog("WARNING", "No se obtuvieron datos históricos de CPP");
                return false;
            }
            
            $this->banxico->escribirLog("INFO", "Obtenidos " . count($datos) . " registros de CPP");
            
            $registros_insertados = 0;
            $registros_omitidos = 0;
            
            $stmt = $this->db->prepare("
                INSERT INTO t_cpp (fecha, tasa, fecha_captura, hora_captura, usuario)
                VALUES (:fecha, :tasa, :fecha_captura, :hora_captura, :usuario)
                ON CONFLICT (fecha) DO NOTHING
            ");
            
            foreach ($datos as $dato_info) {
                if (!$this->esValorValido($dato_info['dato'])) {
                    $registros_omitidos++;
                    continue;
                }
                
                try {
                    $valor_numerico = floatval($dato_info['dato']);
                } catch (Exception $e) {
                    $this->banxico->escribirLog("WARNING", "CPP - Valor inválido: " . $dato_info['dato']);
                    $registros_omitidos++;
                    continue;
                }
                
                $fecha = $this->convertirFecha($dato_info['fecha']);
                $ahora = new DateTime();
                
                $stmt->execute([
                    'fecha' => $fecha,
                    'tasa' => $valor_numerico,
                    'fecha_captura' => $ahora->format('Y-m-d'),
                    'hora_captura' => $ahora->format('H:i:s'),
                    'usuario' => $this->usuario
                ]);
                
                if ($stmt->rowCount() > 0) {
                    $registros_insertados++;
                }
                
                if ($registros_insertados % 100 == 0) {
                    $this->db->commit();
                    $this->db->beginTransaction();
                    $this->banxico->escribirLog("INFO", "Insertados $registros_insertados registros de CPP");
                }
            }
            
            $this->db->commit();
            
            $this->banxico->escribirLog("INFO", "✅ Carga de CPP completada: $registros_insertados insertados, $registros_omitidos omitidos");
            
            return [
                'insertados' => $registros_insertados,
                'omitidos' => $registros_omitidos
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->banxico->escribirLog("ERROR", "Error en carga histórica de CPP: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Carga histórico de UDIS
     */
    public function cargarUdisHistorico($fecha_inicio = "1995-04-04", $fecha_fin = null) {
        $this->banxico->escribirLog("INFO", "Iniciando carga histórica de UDIS desde $fecha_inicio");
        
        try {
            $this->db->beginTransaction();
            
            $datos = $this->banxico->obtenerDatosHistoricos('udis', $fecha_inicio, $fecha_fin, 120);
            
            if (empty($datos)) {
                $this->banxico->escribirLog("WARNING", "No se obtuvieron datos históricos de UDIS");
                return false;
            }
            
            $this->banxico->escribirLog("INFO", "Obtenidos " . count($datos) . " registros de UDIS");
            
            $registros_insertados = 0;
            $registros_omitidos = 0;
            
            $stmt = $this->db->prepare("
                INSERT INTO t_udis (fecha, valor, fecha_captura, hora_captura, usuario)
                VALUES (:fecha, :valor, :fecha_captura, :hora_captura, :usuario)
                ON CONFLICT (fecha) DO NOTHING
            ");
            
            foreach ($datos as $dato_info) {
                if (!$this->esValorValido($dato_info['dato'])) {
                    $registros_omitidos++;
                    continue;
                }
                
                try {
                    $valor_numerico = floatval($dato_info['dato']);
                } catch (Exception $e) {
                    $this->banxico->escribirLog("WARNING", "UDIS - Valor inválido: " . $dato_info['dato']);
                    $registros_omitidos++;
                    continue;
                }
                
                $fecha = $this->convertirFecha($dato_info['fecha']);
                $ahora = new DateTime();
                
                $stmt->execute([
                    'fecha' => $fecha,
                    'valor' => $valor_numerico,
                    'fecha_captura' => $ahora->format('Y-m-d'),
                    'hora_captura' => $ahora->format('H:i:s'),
                    'usuario' => $this->usuario
                ]);
                
                if ($stmt->rowCount() > 0) {
                    $registros_insertados++;
                }
                
                if ($registros_insertados % 100 == 0) {
                    $this->db->commit();
                    $this->db->beginTransaction();
                    $this->banxico->escribirLog("INFO", "Insertados $registros_insertados registros de UDIS");
                }
            }
            
            $this->db->commit();
            
            $this->banxico->escribirLog("INFO", "✅ Carga de UDIS completada: $registros_insertados insertados, $registros_omitidos omitidos");
            
            return [
                'insertados' => $registros_insertados,
                'omitidos' => $registros_omitidos
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->banxico->escribirLog("ERROR", "Error en carga histórica de UDIS: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Carga todos los históricos
     */
    public function cargarTodosLosHistoricos() {
        $this->banxico->escribirLog("INFO", "=== INICIANDO CARGA HISTÓRICA COMPLETA ===");
        
        $resultados = [
            'inpc' => $this->cargarInpcHistorico(),
            'tiie' => $this->cargarTiieHistorico(),
            'tdc' => $this->cargarTdcHistorico(),
            'cpp' => $this->cargarCppHistorico(),
            'udis' => $this->cargarUdisHistorico()
        ];
        
        $this->banxico->escribirLog("INFO", "=== CARGA HISTÓRICA COMPLETA FINALIZADA ===");
        
        return $resultados;
    }
}
?>