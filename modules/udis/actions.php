<?php
require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/Session.php';
require_once '../../includes/Permissions.php';

$db = Database::getInstance()->getConnection();
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$userId = $_SESSION['user_id'] ?? 0;

$permissions = new Permissions();
$canView = $permissions->hasPermission($userId,'udis', 'lire');
$canCreate = $permissions->hasPermission($userId,'udis', 'creer');
$canEdit = $permissions->hasPermission($userId,'udis', 'modifier');
$canDelete = $permissions->hasPermission($userId,'udis', 'supprimer');

$session = new Session();
$isAdmin = $session->isAdmin();

$response = ['success' => false, 'message' => ''];

switch ($action) {
    case 'create':
    
    if (!$canCreate && !$isAdmin) {            
        $response['message'] = 'Sin permisos para crear';
        break;
    }
    
    $fecha = $_POST['fecha'] ?? '';
    $valor = $_POST['valor'] ?? 0;
    $usuario = $_SESSION['username'] ?? 'sistema';
    
    // Hacer explícitas fecha y hora de captura
    $fecha_captura = date('Y-m-d H:i:s'); // Para timestamp
    $hora_captura = date('H:i:s'); // Para varchar formato hora
    
    try {
                    $stmtCheck = $db->prepare("SELECT COUNT(*) as total FROM t_udis WHERE DATE(fecha) = DATE(?)");
            $stmtCheck->execute([$fecha]);
            $existe = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            
            if ($existe['total'] > 0) {
                $response['success'] = false;
                $response['message'] = 'Ya existe un registro con esta fecha';
                break;
            }
        $stmt = $db->prepare("INSERT INTO t_udis (fecha, valor, fecha_captura, hora_captura, usuario) 
                      VALUES (?, ?, NOW(), ?, ?)");              
        $stmt->execute([
            $fecha,
            $valor,
            $hora_captura,
            $usuario
        ]);
        
        $response['success'] = true;
        $response['message'] = 'Registro creado exitosamente';
    } catch (PDOException $e) {
        $response['message'] = 'Error al crear: '. $e->getMessage();
    }
    break;
        
     case 'update':
        if (!$canEdit && !$isAdmin) {
            $response['message'] = 'Sin permisos para editar';
            break;
        }
        
        $id = $_POST['id'] ?? 0;
        $fecha = $_POST['fecha'] ?? '';
        $valor = $_POST['valor'] ?? 0;
        
        try {        
        $stmtCheck = $db->prepare("SELECT COUNT(*) as total 
                                   FROM t_udis 
                                   WHERE DATE(fecha) = DATE(?) 
                                   AND id != ?");
        $stmtCheck->execute([$fecha, $id]);
        $existe = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        
        if ($existe['total'] > 0) {
            $response['success'] = false;
            $response['message'] = 'Ya existe otro registro con esta fecha';
            break;
        }
            $stmt = $db->prepare("UPDATE t_udis SET fecha = ?, valor = ? WHERE id = ?");
            $stmt->execute([$fecha, $valor,  $id]);
            
            $response['success'] = true;
            $response['message'] = 'Registro actualizado';
        } catch (PDOException $e) {
            $response['message'] = 'Error al actualizar: ' . $e->getMessage();
        }
        break;
        
    case 'delete':
        if (!$canDelete && !$isAdmin) {
            $response['message'] = 'Sin permisos para eliminar';
            echo json_encode($response);
            exit;
        }
        
        $id = $_POST['id'] ?? 0;
        
        try {
            $stmt = $db->prepare("DELETE FROM t_udis WHERE id = ?");
            $stmt->execute([$id]);
            
            $response['success'] = true;
            $response['message'] = 'Registro eliminado';
        } catch (PDOException $e) {
            $response['message'] = 'Error al eliminar: ' . $e->getMessage();
        }
        
        echo json_encode($response);
        exit;
        break;
        
    case 'get':
        $id = $_GET['id'] ?? 0;
        
        try {
            $stmt = $db->prepare("SELECT * FROM t_udis WHERE id = ?");
            $stmt->execute([$id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (isset($data['fecha'])) {            
                $data['fecha'] = date('Y-m-d', strtotime($data['fecha']));
            }
            
            if ($data) {
                $response['success'] = true;
                $response['data'] = $data;
            } else {
                $response['message'] = 'Registro no encontrado';
            }
        } catch (PDOException $e) {
            $response['message'] = 'Error: ' . $e->getMessage();
        }
        
        echo json_encode($response);
        exit;
        break;
}
header('Content-Type: application/json');
echo json_encode($response);
