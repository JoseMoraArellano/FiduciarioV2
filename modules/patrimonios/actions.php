<?php
require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/Session.php';
require_once '../../includes/Permissions.php';


$db = Database::getInstance()->getConnection();
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$userId = $_SESSION['user_id'] ?? 0;

$permissions = new Permissions();
$canView = $permissions->hasPermission($userId, 'patrimonios', 'lire');
$canCreate = $permissions->hasPermission($userId, 'patrimonios', 'creer');
$canEdit = $permissions->hasPermission($userId, 'patrimonios', 'modifier');
$canDelete = $permissions->hasPermission($userId, 'patrimonios', 'supprimer');

$session = new Session();
$isAdmin = $session->isAdmin();

$response = ['success' => false, 'message' => ''];

switch ($action) {
    case 'create':
    
        if (!$canCreate && !$isAdmin) {            
            $response['message'] = 'Sin permisos para crear';
            break;
        }
        
        $nombre = $_POST['nombre'] ?? '';
        $activo = ($_POST['activo'] ?? 0) == 1 ? 1 : 0;
        
        try {
            $stmtCheck = $db->prepare("SELECT COUNT(*) as total FROM t_cat_patrimonios WHERE nombre = ?");
            $stmtCheck->execute([$nombre]);
            $existe = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            if ($existe['total'] > 0) {
                $response['success'] = false;
                $response['message'] = 'Ya existe un registro con este nombre';
                break;
            }
            
            $stmt = $db->prepare("INSERT INTO t_cat_patrimonios (nombre, activo) 
                                  VALUES (?, ?)");
            $stmt->execute([
                $nombre,
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
        $nombre = $_POST['nombre'] ?? '';
        $activo = ($_POST['activo'] ?? 0) == 1 ? 1 : 0;
        
        try {
            $stmtCheck = $db->prepare("SELECT COUNT(*) as total 
                                   FROM t_cat_patrimonios 
                                   WHERE nombre = ? 
                                   AND id != ?");
            $stmtCheck->execute([$nombre, $id]);
            $existe = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            
            if ($existe['total'] > 0) {
                $response['success'] = false;
                $response['message'] = 'Ya existe otro registro con este nombre';
                break;
            }
            
            $stmt = $db->prepare("UPDATE t_cat_patrimonios SET nombre = ?, activo = ? WHERE id = ?");
            $stmt->execute([$nombre, $activo, $id]);
            
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
            $stmt = $db->prepare("UPDATE t_cat_patrimonios SET activo = false WHERE id = ?");
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
            $stmt = $db->prepare("SELECT * FROM t_cat_patrimonios WHERE id = ?");
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