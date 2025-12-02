<?php
require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/Session.php';
require_once '../../includes/Permissions.php';


$db = Database::getInstance()->getConnection();
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$userId = $_SESSION['user_id'] ?? 0;

$permissions = new Permissions();
$canView = $permissions->hasPermission($userId, 'bancos', 'lire');
$canCreate = $permissions->hasPermission($userId, 'bancos', 'creer');
$canEdit = $permissions->hasPermission($userId, 'bancos', 'modifier');
$canDelete = $permissions->hasPermission($userId, 'bancos', 'supprimer');

$session = new Session();
$isAdmin = $session->isAdmin();

$response = ['success' => false, 'message' => ''];

switch ($action) {
    case 'create':
    
        if (!$canCreate && !$isAdmin) {            
            $response['message'] = 'Sin permisos para crear';
            break;
        }
        
        $banco = $_POST['banco'] ?? '';
        $clave = $_POST['clave'] ?? '';
        $sucursal = $_POST['sucursal'] ?? 0;
        $activo = ($_POST['activo'] ?? 0) == 1 ? 1 : 0;
        
        try {
            $stmtCheck = $db->prepare("SELECT COUNT(*) as total FROM t_cat_bancos WHERE banco = ?");
            $stmtCheck->execute([$banco]);
            $existe = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            if ($existe['total'] > 0) {
                $response['success'] = false;
                $response['message'] = 'Ya existe un registro con este banco';
                break;
            }
            
            $stmt = $db->prepare("INSERT INTO t_cat_bancos (banco, clave, sucursal, activo) 
                                  VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $banco,
                $clave,
                $sucursal,
                $activo
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
        $banco = $_POST['banco'] ?? '';
        $clave = $_POST['clave'] ?? '';
        $sucursal = $_POST['sucursal'] ?? 0;
        $activo = ($_POST['activo'] ?? 0) == 1 ? 1 : 0;
        
        try {
            $stmtCheck = $db->prepare("SELECT COUNT(*) as total 
                                   FROM t_cat_bancos 
                                   WHERE banco = ? 
                                   AND id != ?");
            $stmtCheck->execute([$banco, $id]);
            $existe = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            
            if ($existe['total'] > 0) {
                $response['success'] = false;
                $response['message'] = 'Ya existe otro registro con este banco';
                break;
            }
            
            $stmt = $db->prepare("UPDATE t_cat_bancos SET banco = ?, clave = ?, sucursal = ?, activo = ? WHERE id = ?");
            $stmt->execute([$banco, $clave, $sucursal, $activo, $id]);
            
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
            $stmt = $db->prepare("UPDATE t_cat_bancos SET activo = false WHERE id = ?");
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
            $stmt = $db->prepare("SELECT * FROM t_cat_bancos WHERE id = ?");
            $stmt->execute([$id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($data) {
                $response['success'] = true;
                $response['data'] = $data;
            } else {
                $response['message'] = 'Registro no encontrado';
            }
        } catch (PDOException $e) {
            $response['message'] = 'Error: ' . $e->getMessage();
            error_log("GET - Error: " . $e->getMessage());
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
        break;
}

header('Content-Type: application/json');
echo json_encode($response);