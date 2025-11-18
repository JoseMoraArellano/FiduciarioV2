<?php
/**
 * Servicio para consumir API de Banxico
 * Convierte indicadores financieros de México
 */

require_once dirname(dirname(__DIR__)) . '/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/Database.php';

class BanxicoService {
    
    private $db;
    private $token;
    private $base_url = "https://www.banxico.org.mx/SieAPIRest/service/v1/series";
    private $log_file;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->log_file = dirname(dirname(__DIR__)) . '/banxico.log';
        $this->cargarToken();
    }
    
    /**
     * Carga el token desde la tabla t_const
     */
    private function cargarToken() {
        try {
            $stmt = $this->db->prepare("SELECT val FROM t_const WHERE id = 15");
            $stmt->execute();
            $result = $stmt->fetch();
            
            if ($result) {
                $this->token = $result['val'];
            } else {
                throw new Exception("Token de Banxico no encontrado en t_const");
            }
        } catch (Exception $e) {
            $this->escribirLog("ERROR", "Error cargando token: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Obtiene el endpoint de un indicador desde t_const
     */
    private function obtenerEndpoint($nombre_indicador) {
        try {
            $stmt = $this->db->prepare("SELECT val FROM t_const WHERE nom = :nom");
            $stmt->execute(['nom' => $nombre_indicador]);
            $result = $stmt->fetch();
            
            if ($result) {
                return $result['val'];
            } else {
                throw new Exception("Endpoint no encontrado para: " . $nombre_indicador);
            }
        } catch (Exception $e) {
            $this->escribirLog("ERROR", "Error obteniendo endpoint: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Realiza petición HTTP a la API de Banxico
     */
    private function hacerPeticion($url, $timeout = 30) {
        $ch = curl_init();
        
        $options = [
            CURLOPT_URL => $url . "?token=" . $this->token,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => ['Accept: application/json']
        ];
        
        curl_setopt_array($ch, $options);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            throw new Exception("Error CURL: " . $error);
        }
        
        if ($http_code !== 200) {
            throw new Exception("Error HTTP: " . $http_code);
        }
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Error parsing JSON: " . json_last_error_msg());
        }
        
        return $data;
    }
    
    /**
     * Obtiene datos oportunos (más recientes) de TIIE
     */
    public function obtenerTiieOportuno() {
        try {
            $endpoint = $this->obtenerEndpoint('tiie');
            $url = $this->base_url . "/" . $endpoint . "/datos/oportuno";
            
            $this->escribirLog("INFO", "Consultando TIIE oportuno...");
            
            $data = $this->hacerPeticion($url);
            
            if (isset($data['bmx']['series'][0]['datos'][0])) {
                $dato = $data['bmx']['series'][0]['datos'][0];
                return [
                    'fecha' => $dato['fecha'],
                    'dato' => floatval($dato['dato'])
                ];
            }
            
            return null;
            
        } catch (Exception $e) {
            $this->escribirLog("ERROR", "Error obteniendo TIIE: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Obtiene datos oportunos de Tipo de Cambio
     */
    public function obtenerTipoCambioOportuno() {
        try {
            $endpoint = $this->obtenerEndpoint('tdc');
            $url = $this->base_url . "/" . $endpoint . "/datos/oportuno";
            
            $this->escribirLog("INFO", "Consultando Tipo de Cambio oportuno...");
            
            $data = $this->hacerPeticion($url);
            
            if (isset($data['bmx']['series'][0]['datos'][0])) {
                $dato = $data['bmx']['series'][0]['datos'][0];
                return [
                    'fecha' => $dato['fecha'],
                    'dato' => floatval($dato['dato'])
                ];
            }
            
            return null;
            
        } catch (Exception $e) {
            $this->escribirLog("ERROR", "Error obteniendo TDC: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Obtiene datos oportunos de INPC
     */
    public function obtenerInpcOportuno() {
        try {
            $endpoint = $this->obtenerEndpoint('inpc');
            $url = $this->base_url . "/" . $endpoint . "/datos/oportuno";
            
            $this->escribirLog("INFO", "Consultando INPC oportuno...");
            
            $data = $this->hacerPeticion($url);
            
            if (isset($data['bmx']['series'][0]['datos'][0])) {
                $dato = $data['bmx']['series'][0]['datos'][0];
                return [
                    'fecha' => $dato['fecha'],
                    'dato' => floatval($dato['dato'])
                ];
            }
            
            return null;
            
        } catch (Exception $e) {
            $this->escribirLog("ERROR", "Error obteniendo INPC: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Obtiene datos oportunos de CPP
     */
    public function obtenerCppOportuno() {
        try {
            $endpoint = $this->obtenerEndpoint('cpp');
            $url = $this->base_url . "/" . $endpoint . "/datos/oportuno";
            
            $this->escribirLog("INFO", "Consultando CPP oportuno...");
            
            $data = $this->hacerPeticion($url);
            
            if (isset($data['bmx']['series'][0]['datos'][0])) {
                $dato = $data['bmx']['series'][0]['datos'][0];
                return [
                    'fecha' => $dato['fecha'],
                    'dato' => floatval($dato['dato'])
                ];
            }
            
            return null;
            
        } catch (Exception $e) {
            $this->escribirLog("ERROR", "Error obteniendo CPP: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Obtiene datos oportunos de UDIS (últimos 7 días)
     */
    public function obtenerUdisOportuno() {
        try {
            $endpoint = $this->obtenerEndpoint('udis');
            
            // UDIS requiere rango de fechas
            $fecha_hasta = date('Y-m-d');
            $fecha_desde = date('Y-m-d', strtotime('-7 days'));
            
            $url = $this->base_url . "/" . $endpoint . "/datos/" . $fecha_desde . "/" . $fecha_hasta;
            
            $this->escribirLog("INFO", "Consultando UDIS del " . $fecha_desde . " al " . $fecha_hasta);
            
            $data = $this->hacerPeticion($url);
            
            if (isset($data['bmx']['series'][0]['datos'])) {
                $datos = $data['bmx']['series'][0]['datos'];
                
                if (count($datos) > 0) {
                    // Tomar el último dato disponible
                    $ultimo = end($datos);
                    return [
                        'fecha' => $ultimo['fecha'],
                        'dato' => floatval($ultimo['dato'])
                    ];
                }
            }
            
            return null;
            
        } catch (Exception $e) {
            $this->escribirLog("ERROR", "Error obteniendo UDIS: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Obtiene datos históricos de cualquier indicador
     */
    public function obtenerDatosHistoricos($indicador, $fecha_inicio, $fecha_fin = null, $timeout = 60) {
        try {
            if ($fecha_fin === null) {
                $fecha_fin = date('Y-m-d');
            }
            
            $endpoint = $this->obtenerEndpoint($indicador);
            $url = $this->base_url . "/" . $endpoint . "/datos/" . $fecha_inicio . "/" . $fecha_fin;
            
            $this->escribirLog("INFO", "Consultando históricos de " . $indicador . " desde " . $fecha_inicio . " hasta " . $fecha_fin);
            
            $data = $this->hacerPeticion($url, $timeout);
            
            if (isset($data['bmx']['series'][0]['datos'])) {
                return $data['bmx']['series'][0]['datos'];
            }
            
            return [];
            
        } catch (Exception $e) {
            $this->escribirLog("ERROR", "Error obteniendo históricos de " . $indicador . ": " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Escribe en el archivo de log
     */
    public function escribirLog($nivel, $mensaje) {
        $fecha = date('Y-m-d H:i:s');
        $linea = "[" . $fecha . "] [" . $nivel . "] " . $mensaje . PHP_EOL;
        
        file_put_contents($this->log_file, $linea, FILE_APPEND | LOCK_EX);
    }
}
?>