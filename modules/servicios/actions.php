<?php
require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/Session.php';
require_once '../../includes/Permissions.php';


$db = Database::getInstance()->getConnection();
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$userId = $_SESSION['user_id'] ?? 0;

$permissions = new Permissions();
$canView = $permissions->hasPermission($userId, 'servicios', 'lire');
$canCreate = $permissions->hasPermission($userId, 'servicios', 'creer');
$canEdit = $permissions->hasPermission($userId, 'servicios', 'modifier');
$canDelete = $permissions->hasPermission($userId, 'servicios', 'supprimer');

$session = new Session();
$isAdmin = $session->isAdmin();

$response = ['success' => false, 'message' => ''];

switch ($action) {
    case 'create':
    
        if (!$canCreate && !$isAdmin) {            
            $response['message'] = 'Sin permisos para crear';
            break;
        }
        
        $consep_descr = $_POST['consep_descr'] ?? '';
         $activo = filter_var($_POST['activo'] ?? false, FILTER_VALIDATE_BOOLEAN);
        
        try {
        $stmtCheck = $db->prepare("SELECT COUNT(*) as total FROM t_cat_servicios WHERE consep_descr = ?");
        $stmtCheck->execute([$consep_descr]);
        $existe = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        
        if ($existe['total'] > 0) {
            $response['success'] = false;
            $response['message'] = 'Ya existe un registro con esta fecha';
            break;
        }
            $stmt = $db->prepare("INSERT INTO t_cat_servicios (consep_descr, activo) 
                                  VALUES (?, ?)");
            $stmt->execute([
                $consep_descr,
                 $activo ? 'true' : 'false'
            ]);                        
            
            $response['success'] = true;
            $response['message'] = 'Registro creado exitosamente';
            
        } catch (PDOException $e) {            
            $response['message'] = 'Error al crear: ' . $e->getMessage();
            error_log("Error PDO: " . $e->getMessage());
        }
        break;
        
    case 'update':
        if (!$canEdit && !$isAdmin) {
            $response['message'] = 'Sin permisos para editar';
            break;
        }
        
        $id = $_POST['id'] ?? 0;
        $consep_descr = $_POST['consep_descr'] ?? '';
        $nombre = $_POST['nombre'] ?? '';
        $activo = filter_var($_POST['activo'] ?? false, FILTER_VALIDATE_BOOLEAN);
        
        try {
                $stmtCheck = $db->prepare("SELECT COUNT(*) as total 
                                   FROM t_cat_servicios 
                                   WHERE consep_descr = ? 
                                   AND id != ?");
        $stmtCheck->execute([$consep_descr, $id]);
        $existe = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        
        if ($existe['total'] > 0) {
            $response['success'] = false;
            $response['message'] = 'Ya existe otro registro con este plazo';
            break;  // Salir sin actualizar
        }
        
            
            $stmt = $db->prepare("UPDATE t_cat_servicios SET consep_descr = ?, activo = ? WHERE id = ?");
                    $stmt->execute([
            $consep_descr,
            $activo ? 'true' : 'false', 
            $id
        ]);
            
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
            $stmt = $db->prepare("UPDATE t_cat_servicios SET activo = false WHERE id = ?");
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
    if (!$canView && !$isAdmin) {
        $response['message'] = 'Sin permisos para ver';
        break;
    }
    
    $id = $_GET['id'] ?? '';
    try {
        $stmt = $db->prepare("SELECT * FROM t_cat_servicios WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($data) {
            // ✅ Convierte el booleano de PostgreSQL a entero para JavaScript
            $data['activo'] = ($data['activo'] === 't' || $data['activo'] === true || $data['activo'] === '1') ? 1 : 0;
            
            $response['success'] = true;
            $response['data'] = $data;
        } else {
            $response['message'] = 'Registro no encontrado';
        }
    } catch (PDOException $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
    break;
}

header('Content-Type: application/json');
echo json_encode($response);