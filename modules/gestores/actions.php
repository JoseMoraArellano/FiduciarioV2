<?php
require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/Session.php';
require_once '../../includes/Permissions.php';


$db = Database::getInstance()->getConnection();
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$userId = $_SESSION['user_id'] ?? 0;

$permissions = new Permissions();
$canView = $permissions->hasPermission($userId, 'gestores', 'lire');
$canCreate = $permissions->hasPermission($userId, 'gestores', 'creer');
$canEdit = $permissions->hasPermission($userId, 'gestores', 'modifier');
$canDelete = $permissions->hasPermission($userId, 'gestores', 'supprimer');

$session = new Session();
$isAdmin = $session->isAdmin();

$response = ['success' => false, 'message' => ''];

switch ($action) {
    case 'create':

        if (!$canCreate && !$isAdmin) {
            $response['message'] = 'Sin permisos para crear';
            break;
        }

        $nombres = mb_strtoupper(trim($_POST['nombres'] ?? ''), 'UTF-8');
        $paterno = mb_strtoupper(trim($_POST['paterno'] ?? ''), 'UTF-8');
        $materno = mb_strtoupper(trim($_POST['materno'] ?? ''), 'UTF-8');
        $correo = trim($_POST['correo'] ?? '');
        $ext = trim($_POST['ext'] ?? '');
        $firmante = filter_var($_POST['firmante'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $adminfide = filter_var($_POST['adminfide'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $contacto = filter_var($_POST['contacto'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $promotor = filter_var($_POST['promotor'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $notario = filter_var($_POST['notario'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $activo = filter_var($_POST['activo'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $nota = trim($_POST['nota'] ?? '');
        $notapublic = trim($_POST['notapublic'] ?? '');

        try {
            // Validar que el correo no exista
            $stmtCheck = $db->prepare("SELECT COUNT(*) as total FROM t_gestores WHERE correo = ?");
            $stmtCheck->execute([$correo]);
            $existe = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($existe['total'] > 0) {
                $response['success'] = false;
                $response['message'] = 'Ya existe un registro con este correo';
                break;
            }

            // Insertar el registro
            $stmt = $db->prepare("INSERT INTO t_gestores (nombres, paterno, materno, correo, ext, firmante, adminfide, contacto, promotor, notario, activo, nota, notapublic) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $nombres,
                $paterno,
                $materno,
                $correo,
                $ext,
                $firmante ? 'true' : 'false',
                $adminfide ? 'true' : 'false',
                $contacto ? 'true' : 'false',
                $promotor ? 'true' : 'false',
                $notario ? 'true' : 'false',
                $activo ? 'true' : 'false',
                $nota,
                $notapublic
            ]);

            // Obtener el ID insertado y generar url_gestor
            $newId = $db->lastInsertId();
            $urlGestor = 'uploads/gestores/' . $newId;
            
            $stmtUrl = $db->prepare("UPDATE t_gestores SET url_gestor = ? WHERE id = ?");
            $stmtUrl->execute([$urlGestor, $newId]);

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
        $nombres = trim($_POST['nombres'] ?? '');
        $paterno = trim($_POST['paterno'] ?? '');
        $materno = trim($_POST['materno'] ?? '');        
        $correo = trim($_POST['correo'] ?? '');
        $ext = trim($_POST['ext'] ?? '');
        $firmante = filter_var($_POST['firmante'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $adminfide = filter_var($_POST['adminfide'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $contacto = filter_var($_POST['contacto'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $promotor = filter_var($_POST['promotor'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $notario = filter_var($_POST['notario'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $activo = filter_var($_POST['activo'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $nota = trim($_POST['nota'] ?? '');
        $notapublic = trim($_POST['notapublic'] ?? '');

        try {
            // Validar que el correo no exista en otro registro
            $stmtCheck = $db->prepare("SELECT COUNT(*) as total FROM t_gestores WHERE correo = ? AND id != ?");
            $stmtCheck->execute([$correo, $id]);
            $existe = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($existe['total'] > 0) {
                $response['success'] = false;
                $response['message'] = 'Ya existe el correo en otro registro';
                break;
            }

            $stmt = $db->prepare("UPDATE t_gestores SET nombres = ?, paterno = ?, materno = ?, correo = ?, ext = ?, firmante = ?, adminfide = ?, contacto = ?, promotor = ?, notario = ?, activo = ?, nota = ?, notapublic = ? WHERE id = ?");
            $stmt->execute([
                $nombres,
                $paterno,
                $materno,
                $correo,
                $ext,
                $firmante ? 'true' : 'false',
                $adminfide ? 'true' : 'false',
                $contacto ? 'true' : 'false',
                $promotor ? 'true' : 'false',
                $notario ? 'true' : 'false',
                $activo ? 'true' : 'false',
                $nota,
                $notapublic,
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
            $stmt = $db->prepare("UPDATE t_gestores SET activo = false WHERE id = ?");
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
            $stmt = $db->prepare("SELECT * FROM t_gestores WHERE id = ?");
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
