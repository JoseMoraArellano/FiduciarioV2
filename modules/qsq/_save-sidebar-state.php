<?php
/**
 * API: Guardar estado del sidebar
 * Endpoint AJAX para persistir el estado abierto/cerrado del sidebar
 * Método: POST
 * Parámetros: { "is_open": true/false }
 */

// Headers para API JSON
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido. Use POST.'
    ]);
    exit;
}

// Cargar dependencias
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Session.php';

try {
    // Iniciar sesión y verificar autenticación
    $session = new Session();
    
    if (!$session->isLoggedIn()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'No autenticado.'
        ]);
        exit;
    }
    
    // Obtener user ID
    $userId = $session->getUserId();
    
    // Leer datos del POST
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // Validar datos
    if (!isset($data['is_open'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Parámetro "is_open" requerido.'
        ]);
        exit;
    }
    
    $isOpen = filter_var($data['is_open'], FILTER_VALIDATE_BOOLEAN);
    
    // Guardar en base de datos
    $db = Database::getInstance()->getConnection();
    
    $sql = "
        UPDATE t_user_preferences 
        SET sidebar_open = :is_open,
            updated_at = CURRENT_TIMESTAMP
        WHERE fk_user = :user_id
    ";
    
    $stmt = $db->prepare($sql);
    $success = $stmt->execute([
        'is_open' => $isOpen ? 1 : 0,
        'user_id' => $userId
    ]);
    
    if ($success) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Estado del sidebar guardado.',
            'data' => [
                'sidebar_open' => $isOpen,
                'user_id' => $userId
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error al guardar estado del sidebar.'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Database error in save-sidebar-state: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos.'
    ]);
    
} catch (Exception $e) {
    error_log("Error in save-sidebar-state: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor.'
    ]);
}
?>