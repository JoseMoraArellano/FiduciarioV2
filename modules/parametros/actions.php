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
$currentUserId = $session->getUserId();

$response = ['success' => false, 'message' => ''];

// Campos permitidos para actualizar
$camposPermitidos = ['iva', 'ufactura', 'fechahh', 'direcbanam', 'fechabanco', 'fechabbv', 'direcbbv', 'fechascot', 'fechahsbc', 'direchsbc', 'direcpdf'];

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
        $stmtCheck = $db->prepare("SELECT COUNT(*) as total FROM t_parametros WHERE plazo = ?");
        $stmtCheck->execute([$plazo]);
        $existe = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        
        if ($existe['total'] > 0) {
            $response['success'] = false;
            $response['message'] = 'Ya existe un registro con esta fecha';
            break;
        }
            $stmt = $db->prepare("INSERT INTO t_parametros (plazo, nombre, activo) 
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
        
        $campo = $_POST['campo'] ?? '';
        $valor = $_POST['valor'] ?? '';
        
        // Validar que el campo esté permitido
        if (!in_array($campo, $camposPermitidos)) {
            $response['message'] = 'Campo no válido';
            break;
        }
        
        try {
            // Obtener el registro actual (solo hay uno)
            $stmtGet = $db->prepare("SELECT * FROM t_parametros LIMIT 1");
            $stmtGet->execute();
            $parametroActual = $stmtGet->fetch(PDO::FETCH_ASSOC);
            
            if (!$parametroActual) {
                $response['message'] = 'No se encontró el registro de parámetros';
                break;
            }
            
            $idParametro = $parametroActual['id'];
            $valorAnterior = $parametroActual[$campo];
            
            // Obtener historial actual
            $historicoActual = [];
            if (!empty($parametroActual['historico'])) {
                $historicoActual = json_decode($parametroActual['historico'], true);
                if (!is_array($historicoActual)) {
                    $historicoActual = ['cambios' => []];
                }
            } else {
                $historicoActual = ['cambios' => []];
            }
            
            // Agregar nuevo cambio al historial
            $nuevoCambio = [
                'fecha' => date('Y-m-d H:i:s'),
                'usuario_id' => $currentUserId,
                'campo' => $campo,
                'valor_anterior' => $valorAnterior,
                'valor_nuevo' => $valor
            ];
            $historicoActual['cambios'][] = $nuevoCambio;
            
            // Actualizar el campo específico, useract e historico
            $sql = "UPDATE t_parametros SET {$campo} = ?, useract = ?, historico = ?::json WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $valor,
                $currentUserId,
                json_encode($historicoActual, JSON_UNESCAPED_UNICODE),
                $idParametro
            ]);
            
            $response['success'] = true;
            $response['message'] = 'Parámetro actualizado correctamente';
            
        } catch (PDOException $e) {
            $response['message'] = 'Error al actualizar: ' . $e->getMessage();
            error_log("Error PDO en update parametros: " . $e->getMessage());
        }
        break;
        
        
case 'get':
    if (!$canView && !$isAdmin) {
        $response['message'] = 'Sin permisos para ver';
        break;
    }
    
    try {
        $stmt = $db->prepare("SELECT * FROM t_parametros LIMIT 1");
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($data) {
            $response['success'] = true;
            $response['data'] = $data;
        } else {
            $response['message'] = 'No hay parámetros configurados';
        }
    } catch (PDOException $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
    break;
}

header('Content-Type: application/json');
echo json_encode($response);