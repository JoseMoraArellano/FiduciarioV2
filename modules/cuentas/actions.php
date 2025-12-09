<?php
require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/Session.php';
require_once '../../includes/Permissions.php';

$db = Database::getInstance()->getConnection();
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$userId = $_SESSION['user_id'] ?? 0;

$permissions = new Permissions();
$canView = $permissions->hasPermission($userId, 'cuentas', 'lire');
$canCreate = $permissions->hasPermission($userId, 'cuentas', 'creer');
$canEdit = $permissions->hasPermission($userId, 'cuentas', 'modifier');
$canDelete = $permissions->hasPermission($userId, 'cuentas', 'supprimer');

$session = new Session();
$isAdmin = $session->isAdmin();

$response = ['success' => false, 'message' => ''];

switch ($action) {
case 'create':
    if (!$canCreate && !$isAdmin) {            
        $response['message'] = 'Sin permisos para crear';
        break;
    }
    
    // Validar campos obligatorios
    $fideicomiso = !empty($_POST['fideicomiso']) ? intval($_POST['fideicomiso']) : null;
    $nombre_de_banco = trim($_POST['nombre_de_banco'] ?? '');
    $cuenta = trim($_POST['cuenta'] ?? '');
    $clabe = trim($_POST['clabe'] ?? '');
    $categoria = !empty($_POST['categoria']) ? $_POST['categoria'] : null; // varchar en la tabla
    $cuenta_contable = !empty($_POST['cuenta_contable']) ? intval($_POST['cuenta_contable']) : null;
    
    // Validación de campos obligatorios
    if (empty($fideicomiso) || empty($nombre_de_banco) || empty($cuenta) || 
        empty($clabe) || empty($categoria) || empty($cuenta_contable)) {
        $response['message'] = 'Los campos Fideicomiso, Nombre del Banco, Número de Cuenta, CLABE, Categoría y Cuenta Contable son obligatorios';
        break;
    }
    
    // Campos opcionales numéricos
    $banxico = !empty($_POST['banxico']) ? intval($_POST['banxico']) : null;
    $saldo_inicial = !empty($_POST['saldo_inicial']) ? floatval($_POST['saldo_inicial']) : 0;
    $saldo_actual = !empty($_POST['saldo_actual']) ? floatval($_POST['saldo_actual']) : 0;
    $formato_cheques = !empty($_POST['formato_cheques']) ? intval($_POST['formato_cheques']) : null;
    $sucursal = !empty($_POST['sucursal']) ? intval($_POST['sucursal']) : null;
    $cuenta_eje = !empty($_POST['cuenta_eje']) ? intval($_POST['cuenta_eje']) : null;
    $cheque_inicial = !empty($_POST['cheque_inicial']) ? intval($_POST['cheque_inicial']) : null;
    $cheque_final = !empty($_POST['cheque_final']) ? intval($_POST['cheque_final']) : null;
    $ultimo_cheque_asignado = !empty($_POST['ultimo_cheque_asignado']) ? intval($_POST['ultimo_cheque_asignado']) : null;
    
    // Campos booleanos - conversión explícita para PostgreSQL
    $banco_doble = (isset($_POST['banco_doble']) && $_POST['banco_doble'] == 1) ? 't' : 'f';
    $cheques_especiales_imp = (isset($_POST['cheques_especiales_imp']) && $_POST['cheques_especiales_imp'] == 1) ? 't' : 'f';
    $cheques_especiales_con_poliza = (isset($_POST['cheques_especiales_con_poliza']) && $_POST['cheques_especiales_con_poliza'] == 1) ? 't' : 'f';
    $chueque_x_hoja = (isset($_POST['chueque_x_hoja']) && $_POST['chueque_x_hoja'] == 1) ? 't' : 'f';
    $tipo_de_cuenta = (isset($_POST['tipo_de_cuenta']) && $_POST['tipo_de_cuenta'] == 1) ? 't' : 'f';
    $activo = (!isset($_POST['activo']) || $_POST['activo'] == 1) ? 't' : 'f'; // Por defecto true
    
    // Campos de tipo de cambio y fecha
    $tipo_moneda = $_POST['tipo_moneda'] ?? 'MN';
    $fecha_de_cambio = !empty($_POST['fecha_de_cambio']) ? $_POST['fecha_de_cambio'] : null;
    $fecha_de_apertura = !empty($_POST['fecha_de_apertura']) ? $_POST['fecha_de_apertura'] : null;
    $tasa_de_cambio = !empty($_POST['tasa_de_cambio']) ? $_POST['tasa_de_cambio'] : null;
    
    // Si tipo_moneda es MN, limpiar campos de tipo de cambio
    if ($tipo_moneda === 'MN') {
        $fecha_de_cambio = null;
        $tasa_de_cambio = null;
    }
    
    try {
        $stmt = $db->prepare("INSERT INTO t_cuentas (
            fideicomiso, nombre_de_banco, cuenta, clabe, categoria, banxico,
            saldo_inicial, saldo_actual, formato_cheques, sucursal, cuenta_contable,
            banco_doble, cuenta_eje, cheque_inicial, cheque_final, ultimo_cheque_asignado,
            cheques_especiales_imp, cheques_especiales_con_poliza, chueque_x_hoja, activo,
            tipo_moneda, fecha_de_cambio, tasa_de_cambio, \"Fecha_de_apertura\", tipo_de_cuenta
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $fideicomiso, $nombre_de_banco, $cuenta, $clabe, $categoria, $banxico,
            $saldo_inicial, $saldo_actual, $formato_cheques, $sucursal, $cuenta_contable,
            $banco_doble, $cuenta_eje, $cheque_inicial, $cheque_final, $ultimo_cheque_asignado,
            $cheques_especiales_imp, $cheques_especiales_con_poliza, $chueque_x_hoja, $activo,
            $tipo_moneda, $fecha_de_cambio, $tasa_de_cambio, $fecha_de_apertura, $tipo_de_cuenta
        ]);                        
        
        $response['success'] = true;
        $response['message'] = 'Cuenta creada exitosamente';
        
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
    
    // Validar campos obligatorios
    $fideicomiso = $_POST['fideicomiso'] ?? null;
    $nombre_de_banco = trim($_POST['nombre_de_banco'] ?? '');
    $cuenta = trim($_POST['cuenta'] ?? '');
    $clabe = trim($_POST['clabe'] ?? '');
    $categoria = $_POST['categoria'] ?? null;
    $cuenta_contable = $_POST['cuenta_contable'] ?? null;
    
    // Validación de campos obligatorios
    if (empty($fideicomiso) || empty($nombre_de_banco) || empty($cuenta) || 
        empty($clabe) || empty($categoria) || empty($cuenta_contable)) {
        $response['message'] = 'Los campos Fideicomiso, Nombre del Banco, Número de Cuenta, CLABE, Categoría y Cuenta Contable son obligatorios';
        break;
    }
    
    // Resto de campos
    $banxico = !empty($_POST['banxico']) ? $_POST['banxico'] : null;
    $saldo_inicial = $_POST['saldo_inicial'] ?? 0;
    $saldo_actual = $_POST['saldo_actual'] ?? 0;
    $formato_cheques = !empty($_POST['formato_cheques']) ? $_POST['formato_cheques'] : null;
    $sucursal = !empty($_POST['sucursal']) ? $_POST['sucursal'] : null;
    $cliente = !empty($_POST['cliente']) ? $_POST['cliente'] : null;
    
    // Campos booleanos - conversión explícita a 't' o 'f' para PostgreSQL
    $banco_doble = (($_POST['banco_doble'] ?? 0) == 1) ? 't' : 'f';
    $cuenta_eje = !empty($_POST['cuenta_eje']) ? $_POST['cuenta_eje'] : null;
    $cheque_inicial = !empty($_POST['cheque_inicial']) ? $_POST['cheque_inicial'] : null;
    $cheque_final = !empty($_POST['cheque_final']) ? $_POST['cheque_final'] : null;
    $ultimo_cheque_asignado = !empty($_POST['ultimo_cheque_asignado']) ? $_POST['ultimo_cheque_asignado'] : null;
    
    // Más campos booleanos
    $cheques_especiales_imp = (($_POST['cheques_especiales_imp'] ?? 0) == 1) ? 't' : 'f';
    $cheques_especiales_con_poliza = (($_POST['cheques_especiales_con_poliza'] ?? 0) == 1) ? 't' : 'f';
    $chueque_x_hoja = (($_POST['chueque_x_hoja'] ?? 0) == 1) ? 't' : 'f';
    $activo = (($_POST['activo'] ?? 1) == 1) ? 't' : 'f';
    $tipo_de_cuenta = (($_POST['tipo_de_cuenta'] ?? 0) == 1) ? 't' : 'f';
    
    // Campos de tipo de cambio y fecha
    $tipo_moneda = $_POST['tipo_moneda'] ?? 'MN';
    $fecha_de_cambio = !empty($_POST['fecha_de_cambio']) ? $_POST['fecha_de_cambio'] : null;
    $fecha_de_apertura = !empty($_POST['fecha_de_apertura']) ? $_POST['fecha_de_apertura'] : null;
    $tasa_de_cambio = !empty($_POST['tasa_de_cambio']) ? $_POST['tasa_de_cambio'] : null;
    $fecha = !empty($_POST['fecha']) ? $_POST['fecha'] : null;
    
    // Si tipo_moneda es MN, limpiar campos de tipo de cambio
    if ($tipo_moneda === 'MN') {
        $fecha_de_cambio = null;
        $tasa_de_cambio = null;
    }
    
    try {
        // UPDATE con todas las columnas
        $stmt = $db->prepare("UPDATE t_cuentas SET 
            fideicomiso = ?, nombre_de_banco = ?, cuenta = ?, clabe = ?, categoria = ?,
            saldo_inicial = ?, saldo_actual = ?, formato_cheques = ?, sucursal = ?, cliente = ?,
            cuenta_contable = ?, cuenta_eje = ?, cheque_inicial = ?, cheque_final = ?, 
            ultimo_cheque_asignado = ?, cheques_especiales_imp = ?, 
            cheques_especiales_con_poliza = ?, chueque_x_hoja = ?, activo = ?,
            fecha = ?, banxico = ?, banco_doble = ?, tipo_moneda = ?, fecha_de_cambio = ?, 
            tasa_de_cambio = ?, tipo_de_cuenta = ?, \"Fecha_de_apertura\" = ?
            WHERE id = ?");
        
        // 28 valores: 27 campos + id al final
        $stmt->execute([
            $fideicomiso, $nombre_de_banco, $cuenta, $clabe, $categoria,
            $saldo_inicial, $saldo_actual, $formato_cheques, $sucursal, $cliente,
            $cuenta_contable, $cuenta_eje, $cheque_inicial, $cheque_final, $ultimo_cheque_asignado,
            $cheques_especiales_imp, $cheques_especiales_con_poliza, $chueque_x_hoja, $activo,
            $fecha, $banxico, $banco_doble, $tipo_moneda, $fecha_de_cambio,
            $tasa_de_cambio, $tipo_de_cuenta, $fecha_de_apertura, $id
        ]);
        
        $response['success'] = true;
        $response['message'] = 'Cuenta actualizada';
    } catch (PDOException $e) {
        $response['message'] = 'Error al actualizar: ' . $e->getMessage();
        error_log("Error PDO UPDATE: " . $e->getMessage());
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
            $stmt = $db->prepare("UPDATE t_cuentas SET activo = false WHERE id = ?");
            $stmt->execute([$id]);
            
            $response['success'] = true;
            $response['message'] = 'Cuenta eliminada';
        } catch (PDOException $e) {
            $response['message'] = 'Error al eliminar: ' . $e->getMessage();
        }
        
        echo json_encode($response);
        exit;
        break;
        
    case 'get':
        $id = $_GET['id'] ?? 0;
                
        try {
            $stmt = $db->prepare("SELECT * FROM t_cuentas WHERE id = ?");
            $stmt->execute([$id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($data) {
                // CORRECCIÓN: Mapear el campo con mayúscula
                if (isset($data['Fecha_de_apertura'])) {
                    $data['fecha_de_apertura'] = $data['Fecha_de_apertura'];
                }
                
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
?>