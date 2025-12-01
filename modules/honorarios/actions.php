<?php
require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/Session.php';
require_once '../../includes/Permissions.php';


$db = Database::getInstance()->getConnection();
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$userId = $_SESSION['user_id'] ?? 0;

$permissions = new Permissions();
$canView = $permissions->hasPermission($userId, 'honorarios', 'lire');
$canCreate = $permissions->hasPermission($userId, 'honorarios', 'creer');
$canEdit = $permissions->hasPermission($userId, 'honorarios', 'modifier');
$canDelete = $permissions->hasPermission($userId, 'honorarios', 'supprimer');

$session = new Session();
$isAdmin = $session->isAdmin();

$response = ['success' => false, 'message' => ''];

switch ($action) {
    case 'create':
    
        if (!$canCreate && !$isAdmin) {            
            $response['message'] = 'Sin permisos para crear';
            break;
        }
        
        $plazo = $_POST['plazo'] ?? '';
        $nombre = $_POST['nombre'] ?? '';
         $activo = filter_var($_POST['activo'] ?? false, FILTER_VALIDATE_BOOLEAN);
        
        try {
        $stmtCheck = $db->prepare("SELECT COUNT(*) as total FROM t_cat_periodos WHERE plazo = ?");
        $stmtCheck->execute([$plazo]);
        $existe = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        
        if ($existe['total'] > 0) {
            $response['success'] = false;
            $response['message'] = 'Ya existe un registro con esta fecha';
            break;
        }
            $stmt = $db->prepare("INSERT INTO t_cat_periodos (plazo, nombre, activo) 
                                  VALUES (?, ?, ?)");
            $stmt->execute([
                $plazo,
                $nombre,
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
        $plazo = $_POST['plazo'] ?? '';
        $nombre = $_POST['nombre'] ?? '';
        $activo = filter_var($_POST['activo'] ?? false, FILTER_VALIDATE_BOOLEAN);
        
        try {
                $stmtCheck = $db->prepare("SELECT COUNT(*) as total 
                                   FROM t_cat_periodos 
                                   WHERE plazo = ? 
                                   AND id != ?");
        $stmtCheck->execute([$plazo, $id]);
        $existe = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        
        if ($existe['total'] > 0) {
            $response['success'] = false;
            $response['message'] = 'Ya existe otro registro con este plazo';
            break;  // Salir sin actualizar
        }
        
            
            $stmt = $db->prepare("UPDATE t_cat_periodos SET plazo = ?, nombre = ?, activo = ? WHERE id = ?");
                    $stmt->execute([
            $plazo,
            $nombre,
            $activo ? 'true' : 'false',  // ✅ PostgreSQL booleano
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
            $stmt = $db->prepare("UPDATE t_cat_periodos SET activo = false WHERE id = ?");
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
        $stmt = $db->prepare("SELECT * FROM t_cat_periodos WHERE id = ?");
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