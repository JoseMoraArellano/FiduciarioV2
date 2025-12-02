<?php
require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/Session.php';
require_once '../../includes/Permissions.php';


$db = Database::getInstance()->getConnection();
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$userId = $_SESSION['user_id'] ?? 0;

$permissions = new Permissions();
$canView = $permissions->hasPermission($userId, 'banxico', 'lire');
$canCreate = $permissions->hasPermission($userId, 'banxico', 'creer');
$canEdit = $permissions->hasPermission($userId, 'banxico', 'modifier');
$canDelete = $permissions->hasPermission($userId, 'banxico', 'supprimer');

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
        $codigo = $_POST['codigo'] ?? '';
        $activo = filter_var($_POST['activo'] ?? false, FILTER_VALIDATE_BOOLEAN);

        try {
            $stmtCheck = $db->prepare("SELECT COUNT(*) as total FROM t_cat_banxico WHERE clave = ?");
            $stmtCheck->execute([$clave]);
            $existe = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($existe['total'] > 0) {
                $response['success'] = false;
                $response['message'] = 'Ya existe un registro con esta clave';
                break;
            }
            $stmt = $db->prepare("INSERT INTO t_cat_banxico (banco, clave, codigo, activo) 
                                  VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $banco,
                $clave,
                $codigo,
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
        $banco = $_POST['banco'] ?? '';
        $clave = $_POST['clave'] ?? '';
        $codigo = $_POST['codigo'] ?? '';
        // Conversión más robusta del campo activo
        $activo = ($_POST['activo'] ?? '0') == '1' || $_POST['activo'] === 'true' || $_POST['activo'] === true;

        try {
            $stmtCheck = $db->prepare("SELECT COUNT(*) as total 
                               FROM t_cat_banxico 
                               WHERE clave = ? 
                               AND id != ?");
            $stmtCheck->execute([$clave, $id]);
            $existe = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($existe['total'] > 0) {
                $response['success'] = false;
                $response['message'] = 'Ya existe la clave en otro registro';
                break;
            }

            $stmt = $db->prepare("UPDATE t_cat_banxico SET banco = ?, clave = ?, codigo = ?, activo = ? WHERE id = ?");
            $stmt->execute([
                $banco,
                $clave,
                $codigo,
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
            $stmt = $db->prepare("UPDATE t_cat_banxico SET activo = false WHERE id = ?");
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
            $stmt = $db->prepare("SELECT * FROM t_cat_banxico WHERE id = ?");
            $stmt->execute([$id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($data) {
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
