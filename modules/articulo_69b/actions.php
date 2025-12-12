<?php
// ==================== modules/articulo_69b/actions.php ====================

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

try {
    // PRIMERO: Cargar el config.php
    $possibleConfigPaths = [
        '../../config.php',
        '../config.php',
        dirname(dirname(__DIR__)) . '/config.php',
        __DIR__ . '/../../config.php'
    ];
    
    $configLoaded = false;
    foreach ($possibleConfigPaths as $configPath) {
        if (file_exists($configPath)) {
            require_once $configPath;
            $configLoaded = true;
            break;
        }
    }
    
    if (!$configLoaded) {
        echo json_encode([
            'success' => false,
            'message' => 'No se encontró el archivo de configuración'
        ]);
        exit;
    }
    
    // SEGUNDO: Cargar las clases
    $possiblePaths = [
        '../../includes/',
        dirname(dirname(__DIR__)) . '/includes/',
        '../includes/',
        __DIR__ . '/../../includes/'
    ];
    
    $includesPath = null;
    foreach ($possiblePaths as $path) {
        if (file_exists($path . 'Database.php')) {
            $includesPath = $path;
            break;
        }
    }
    
    if (!$includesPath) {
        echo json_encode([
            'success' => false,
            'message' => 'No se encontraron los archivos includes'
        ]);
        exit;
    }
    
    require_once $includesPath . 'Database.php';
    require_once $includesPath . 'Session.php';
    require_once $includesPath . 'Permissions.php';
    
    $db = Database::getInstance()->getConnection();
    $session = new Session();
    $permissions = new Permissions();
    
    $userId = $session->getUserId();
    $isAdmin = $session->isAdmin();
    
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    $canView = $isAdmin || $permissions->hasPermission($userId, 'articulo_69b', 'lire');
    $canCreate = $isAdmin || $permissions->hasPermission($userId, 'articulo_69b', 'creer');
    $canEdit = $isAdmin || $permissions->hasPermission($userId, 'articulo_69b', 'modifier');
    $canDelete = $isAdmin || $permissions->hasPermission($userId, 'articulo_69b', 'supprimer');
    
    $response = ['success' => false, 'message' => ''];
    
    switch ($action) {
        case 'create':
            if (!$canCreate) {
                $response['message'] = 'Sin permisos para crear';
                break;
            }
    
            $rfc = mb_strtoupper(trim($_POST['rfc'] ?? ''), 'UTF-8');
            $nombre_contribuyente = mb_strtoupper(trim($_POST['nombre_contribuyente'] ?? ''), 'UTF-8');
            $situacion_contribuyente = trim($_POST['situacion_contribuyente'] ?? '');
            
            if (empty($rfc) || empty($nombre_contribuyente)) {
                $response['message'] = 'RFC y Nombre son requeridos';
                break;
            }
            
            $stmtCheck = $db->prepare("SELECT COUNT(*) as total FROM t_cat_articulo_69b WHERE rfc = ?");
            $stmtCheck->execute([$rfc]);
            $existe = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    
            if ($existe['total'] > 0) {
                $response['message'] = 'Ya existe un registro con este RFC';
                break;
            }
    
            $stmt = $db->prepare("INSERT INTO t_cat_articulo_69b (
                rfc, nombre_contribuyente, situacion_contribuyente,
                numero_fecha_oficio_presuncion_sat, publicacion_sat_presuntos,
                numero_fecha_oficio_presuncion_dof, publicacion_dof_presuntos,
                numero_fecha_oficio_desvirtuar_sat, publicacion_sat_desvirtuados,
                numero_fecha_oficio_desvirtuar_dof, publicacion_dof_desvirtuados,
                numero_fecha_oficio_definitivos_sat, publicacion_sat_definitivos,
                numero_fecha_oficio_definitivos_dof, publicacion_dof_definitivos,
                numero_fecha_oficio_sentencia_sat, publicacion_sat_sentencia_favorable,
                numero_fecha_oficio_sentencia_dof, publicacion_dof_sentencia_favorable
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $rfc, $nombre_contribuyente, $situacion_contribuyente,
                trim($_POST['numero_fecha_oficio_presuncion_sat'] ?? ''),
                trim($_POST['publicacion_sat_presuntos'] ?? ''),
                trim($_POST['numero_fecha_oficio_presuncion_dof'] ?? ''),
                trim($_POST['publicacion_dof_presuntos'] ?? ''),
                trim($_POST['numero_fecha_oficio_desvirtuar_sat'] ?? ''),
                trim($_POST['publicacion_sat_desvirtuados'] ?? ''),
                trim($_POST['numero_fecha_oficio_desvirtuar_dof'] ?? ''),
                trim($_POST['publicacion_dof_desvirtuados'] ?? ''),
                trim($_POST['numero_fecha_oficio_definitivos_sat'] ?? ''),
                trim($_POST['publicacion_sat_definitivos'] ?? ''),
                trim($_POST['numero_fecha_oficio_definitivos_dof'] ?? ''),
                trim($_POST['publicacion_dof_definitivos'] ?? ''),
                trim($_POST['numero_fecha_oficio_sentencia_sat'] ?? ''),
                trim($_POST['publicacion_sat_sentencia_favorable'] ?? ''),
                trim($_POST['numero_fecha_oficio_sentencia_dof'] ?? ''),
                trim($_POST['publicacion_dof_sentencia_favorable'] ?? '')
            ]);
    
            $response['success'] = true;
            $response['message'] = 'Registro creado exitosamente';
            $response['id'] = $db->lastInsertId();
            break;
    
        case 'update':
            if (!$canEdit) {
                $response['message'] = 'Sin permisos para editar';
                break;
            }
    
            $id = $_POST['id'] ?? 0;
            $rfc = mb_strtoupper(trim($_POST['rfc'] ?? ''), 'UTF-8');
            $nombre_contribuyente = mb_strtoupper(trim($_POST['nombre_contribuyente'] ?? ''), 'UTF-8');
            $situacion_contribuyente = trim($_POST['situacion_contribuyente'] ?? '');
            
            if (empty($id) || empty($rfc) || empty($nombre_contribuyente)) {
                $response['message'] = 'ID, RFC y Nombre son requeridos';
                break;
            }
            
            $stmtCheck = $db->prepare("SELECT COUNT(*) as total FROM t_cat_articulo_69b WHERE rfc = ? AND id != ?");
            $stmtCheck->execute([$rfc, $id]);
            $existe = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    
            if ($existe['total'] > 0) {
                $response['message'] = 'Ya existe el RFC en otro registro';
                break;
            }
    
            $stmt = $db->prepare("UPDATE t_cat_articulo_69b SET 
                rfc = ?, nombre_contribuyente = ?, situacion_contribuyente = ?,
                numero_fecha_oficio_presuncion_sat = ?, publicacion_sat_presuntos = ?,
                numero_fecha_oficio_presuncion_dof = ?, publicacion_dof_presuntos = ?,
                numero_fecha_oficio_desvirtuar_sat = ?, publicacion_sat_desvirtuados = ?,
                numero_fecha_oficio_desvirtuar_dof = ?, publicacion_dof_desvirtuados = ?,
                numero_fecha_oficio_definitivos_sat = ?, publicacion_sat_definitivos = ?,
                numero_fecha_oficio_definitivos_dof = ?, publicacion_dof_definitivos = ?,
                numero_fecha_oficio_sentencia_sat = ?, publicacion_sat_sentencia_favorable = ?,
                numero_fecha_oficio_sentencia_dof = ?, publicacion_dof_sentencia_favorable = ?,
                fecha_actualizacion = CURRENT_TIMESTAMP
                WHERE id = ?");
            
            $stmt->execute([
                $rfc, $nombre_contribuyente, $situacion_contribuyente,
                trim($_POST['numero_fecha_oficio_presuncion_sat'] ?? ''),
                trim($_POST['publicacion_sat_presuntos'] ?? ''),
                trim($_POST['numero_fecha_oficio_presuncion_dof'] ?? ''),
                trim($_POST['publicacion_dof_presuntos'] ?? ''),
                trim($_POST['numero_fecha_oficio_desvirtuar_sat'] ?? ''),
                trim($_POST['publicacion_sat_desvirtuados'] ?? ''),
                trim($_POST['numero_fecha_oficio_desvirtuar_dof'] ?? ''),
                trim($_POST['publicacion_dof_desvirtuados'] ?? ''),
                trim($_POST['numero_fecha_oficio_definitivos_sat'] ?? ''),
                trim($_POST['publicacion_sat_definitivos'] ?? ''),
                trim($_POST['numero_fecha_oficio_definitivos_dof'] ?? ''),
                trim($_POST['publicacion_dof_definitivos'] ?? ''),
                trim($_POST['numero_fecha_oficio_sentencia_sat'] ?? ''),
                trim($_POST['publicacion_sat_sentencia_favorable'] ?? ''),
                trim($_POST['numero_fecha_oficio_sentencia_dof'] ?? ''),
                trim($_POST['publicacion_dof_sentencia_favorable'] ?? ''),
                $id
            ]);
    
            $response['success'] = true;
            $response['message'] = 'Registro actualizado correctamente';
            break;
    
        case 'delete':
            if (!$canDelete) {
                $response['message'] = 'Sin permisos para eliminar';
                break;
            }
    
            $id = $_POST['id'] ?? 0;
            
            if (empty($id)) {
                $response['message'] = 'ID requerido';
                break;
            }
            
            $stmt = $db->prepare("DELETE FROM t_cat_articulo_69b WHERE id = ?");
            $stmt->execute([$id]);
    
            $response['success'] = true;
            $response['message'] = 'Registro eliminado correctamente';
            break;
    
        case 'get':
            if (!$canView) {
                $response['message'] = 'Sin permisos para ver';
                break;
            }
    
            $id = $_GET['id'] ?? '';
            
            if (empty($id)) {
                $response['message'] = 'ID requerido';
                break;
            }
            
            $stmt = $db->prepare("SELECT * FROM t_cat_articulo_69b WHERE id = ?");
            $stmt->execute([$id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
            if ($data) {
                $response['success'] = true;
                $response['data'] = $data;
            } else {
                $response['message'] = 'Registro no encontrado';
            }
            break;
            
        default:
            $response['message'] = 'Acción no válida';
            break;
    }

} catch (PDOException $e) {
    error_log("Error PDO: " . $e->getMessage());
    $response = [
        'success' => false,
        'message' => 'Error en la base de datos'
    ];
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    $response = [
        'success' => false,
        'message' => 'Error del servidor'
    ];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);