<?php
require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/Session.php';
require_once '../../includes/Permissions.php';

$db = Database::getInstance()->getConnection();
$action = $_POST['action'] ?? '';


$permissions = new Permissions();
$canView = $permissions->hasPermission('tiie', 'lire');
$canCreate = $permissions->hasPermission('tiie', 'creer');
$canEdit = $permissions->hasPermission('tiie', 'modifier');
$canDelete = $permissions->hasPermission('tiie', 'supprimer');
$isAdmin = $permissions->isAdmin();
$response = ['success' => false, 'message' => ''];

switch ($action) {
    case 'create':
        
        if (!$canCreate && !$isAdmin) {            
            $response['message'] = 'Sin permisos para crear';
            break;
        }
        
        $fecha = $_POST['fecha'] ?? '';
        $dato = $_POST['dato'] ?? 0;
        $activo = isset($_POST['activo']) ? 1 : 0;
        $usuario = $_SESSION['username'] ?? 'sistema';
                
        
        try {
            $stmt = $db->prepare("INSERT INTO t_tiie (fecha, dato, activo, fecha_insercion, hora_insercion, usuausuario) 
                                  VALUES (?, ?, ?, CURRENT_DATE, CURRENT_TIME, ?)");
            
            file_put_contents($debugFile, "Ejecutando INSERT...\n", FILE_APPEND);
            
            $stmt->execute([
                $fecha, 
                $dato, 
                $activo,
                $usuario
            ]);                        
            
            $response['success'] = true;
            $response['message'] = 'Registro creado exitosamente';
        } catch (PDOException $e) {            
            $response['message'] = 'Error al crear: ' . $e->getMessage();
        }
        break;
        
    case 'update':
        if (!$canEdit && !$isAdmin) {
            $response['message'] = 'Sin permisos para editar';
            break;
        }
        
        $id = $_POST['id'] ?? 0;
        $fecha = $_POST['fecha'] ?? '';
        $dato = $_POST['dato'] ?? 0;
        $activo = isset($_POST['activo']) ? 1 : 0;
        
        try {
            $stmt = $db->prepare("UPDATE t_tiie SET fecha = ?, dato = ?, activo = ? WHERE id = ?");
            $stmt->execute([$fecha, $dato, $activo, $id]);
            
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
            $stmt = $db->prepare("UPDATE t_tiie SET activo = false WHERE id = ?");
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
            $stmt = $db->prepare("SELECT * FROM t_tiie WHERE id = ?");
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
        }
        
        echo json_encode($response);
        exit;
        break;
}
header('Content-Type: application/json');
echo json_encode($response);
?>