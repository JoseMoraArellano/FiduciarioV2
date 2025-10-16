<?php
require_once '../config.php';

/**
 * Clase para gestionar la obtención y actualización de tokens
 */
class TokenManager {
    
    /**
     * Obtiene un nuevo token desde la API
     */
    public static function obtenerNuevoToken() {
        try {
            // Obtener valores de configuración desde t_const
            $clientId = getConstant('client_id');
            $type = getConstant('type');
            $urlEndpoint = getConstant('url_endpointqeq');
            $authorization = getConstant('Autorization');
            
            // Validar que existan todos los valores
            if (!$clientId || !$type || !$urlEndpoint || !$authorization) {
                return [
                    'success' => false,
                    'error' => 'Faltan configuraciones en t_const. Verifica: client_id, type, url_endpointqeq, Autorization'
                ];
            }
            
            // Construir URL con parámetros
            $url = $urlEndpoint . '?' . http_build_query([
                'client_id' => $clientId,
                'type' => $type
            ]);
            
            // Configurar cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $authorization
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Solo para desarrollo
            
            // Ejecutar petición
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            // Verificar errores de cURL
            if ($error) {
                return [
                    'success' => false,
                    'error' => 'Error en la petición: ' . $error
                ];
            }
            
            // Verificar código HTTP
            if ($httpCode !== 200) {
                return [
                    'success' => false,
                    'error' => 'Error HTTP ' . $httpCode . ': ' . $response
                ];
            }
            
// Decodificar respuesta JSON (si aplica)
$data = json_decode($response, true);

// Si es un array JSON, validar el campo 'success'
if (is_array($data)) {
    // Si la API retorna success = false, es un error
    if (isset($data['success']) && $data['success'] === false) {
        return [
            'success' => false,
            'error' => $data['status'] ?? 'Error desconocido de la API'
        ];
    }
    
    // Si hay token, es exitoso
    $token = $data['token'] ?? $response;
} else {
    // Si no es JSON, usar como texto plano
    $token = $response;
}

return [
    'success' => true,
    'token' => $token,
    'raw_response' => $response
];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Excepción: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Actualiza el token en la base de datos
     */
    public static function actualizarTokenEnDB($token, $userId = 1) {
        try {
            $resultado = updateConstant('bearer token', 5, $token, $userId);
            
            if ($resultado) {
                return [
                    'success' => true,
                    'mensaje' => 'Token actualizado correctamente en la base de datos'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'No se pudo actualizar el token en la base de datos'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al actualizar: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Proceso completo: obtener y actualizar token
     */
    public static function renovarToken($userId = 1) {
        // Obtener nuevo token
        $resultado = self::obtenerNuevoToken();
        
        if (!$resultado['success']) {
            return $resultado;
        }
        
        // Actualizar en base de datos
        $actualizacion = self::actualizarTokenEnDB($resultado['token'], $userId);
        
        if (!$actualizacion['success']) {
            return $actualizacion;
        }
        
        return [
            'success' => true,
            'token' => $resultado['token'],
            'mensaje' => 'Token renovado y actualizado correctamente'
        ];
    }
}
?>