<?php
// Cargar configuración
require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/Session.php';
require_once '../../includes/GruposManager.php';

$session = new Session();

// Verificar autenticación
if (!$session->isLoggedIn()) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Sesión expirada'
        ]);
    } else {
        header('Location: ../../login.php');
    }
    exit;
}


if (!$isAdmin && !$session->hasPermission('catalogos', 'lire', 'clientes')) {
    header('HTTP/1.1 403 Forbidden');
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'No tienes permisos para realizar esta acción'
        ]);
    } else {
        die('Acceso denegado');
    }
    exit;
}

$userId = $session->getUserId();
$isAdmin = $session->isAdmin();

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    
    // ========================================
    // DUPLICAR USUARIO
    // ========================================
    case 'duplicate':
        header('Content-Type: application/json');
        
        // Verificar permiso de crear
        if (!$isAdmin && !$session->hasPermission('catalogos', 'creer', 'clientes')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para duplicar clientes'
            ]);
            exit;
        }
        
        $id = (int)($_POST['id'] ?? 0);
        
        if ($id <= 0) {
            echo json_encode([
                'success' => false,
                'message' => 'ID de usuario inválido'
            ]);
            exit;
        }
        
        $result = $usuariosManager->duplicateUsuario($id);
        echo json_encode($result);
        break;
    
    // ========================================
    // CAMBIAR ESTADO (ACTIVAR/DESACTIVAR)
    // ========================================
    case 'toggle-status':
        header('Content-Type: application/json');
        
        // Verificar permiso de modificar
        if (!$isAdmin && !$session->hasPermission('catalogos', 'modifier', 'clientes')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para cambiar el estado de clientes'
            ]);
            exit;
        }
        
        $id = (int)($_POST['id'] ?? 0);
        
        if ($id <= 0) {
            echo json_encode([
                'success' => false,
                'message' => 'ID de usuario inválido'
            ]);
            exit;
        }
        
        $result = $usuariosManager->toggleStatus($id);
        echo json_encode($result);
        break;
    
    // ========================================
    // ELIMINAR USUARIO
    // ========================================
    case 'delete':
        header('Content-Type: application/json');
        
        // Verificar permiso de eliminar
        if (!$isAdmin && !$session->hasPermission('catalogos', 'supprimer', 'clientes')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para eliminar clientes'
            ]);
            exit;
        }
        
        $id = (int)($_POST['id'] ?? 0);
        
        if ($id <= 0) {
            echo json_encode([
                'success' => false,
                'message' => 'ID de cliente inválido'
            ]);
            exit;
        }
/*        
        // No permitir eliminar al usuario actual
        if ($id == $userId) {
            echo json_encode([
                'success' => false,
                'message' => 'No puedes eliminar tu propio usuario'
            ]);
            exit;
        }
*/        
        $result = $clientesManager->deleteCliente($id);
        echo json_encode($result);
        break;
    
    // ========================================
    // EXPORTAR A CSV
    // ========================================
    case 'export-csv':
        // Verificar permiso de exportar
        if (!$isAdmin && !$session->hasPermission('catalogos', 'export', 'clientes')) {
            die('No tienes permiso para exportar clientes');
        }
        
        // Obtener filtros actuales
        $filters = [
            'search' => $_GET['search'] ?? '',
//            'status' => $_GET['status'] ?? '',
//            'role' => $_GET['role'] ?? '',
//            'group' => $_GET['group'] ?? ''
        ];
        
        $result = $clientesManager->exportToCSV($filters);
        
        if ($result['success']) {
            $filename = 'clientes_' . date('Y-m-d_H-i-s') . '.csv';
            
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            // BOM para Excel
            echo "\xEF\xBB\xBF";
            echo $result['data'];
        } else {
            die('Error al exportar: ' . $result['message']);
        }
        break;
    
    // ========================================
    // GUARDAR USUARIO (CREAR/EDITAR)
    // ========================================
    case 'save':
        $id = (int)($_POST['id'] ?? 0);
        
        // Si es edición, verificar permiso de modificar
        if ($id > 0) {
            if (!$isAdmin && !$session->hasPermission('catalogos', 'modifier', 'clientes')) {
                $_SESSION['error'] = 'No tienes permiso para editar clientes';
                header('Location: ../../catalogos.php?mod=clientes&action=list');
                exit;
            }
        } else {
            // Si es creación, verificar permiso de crear
            if (!$isAdmin && !$session->hasPermission('catalogos', 'creer', 'clientes')) {
                $_SESSION['error'] = 'No tienes permiso para crear clientes';
                header('Location: ../../catalogos.php?mod=clientes&action=list');
                exit;
            }
        }
        
        // Recopilar datos del formulario
        $data = [
            'name' => trim($_POST['nombres'] ?? ''),
            'paterno' => trim($_POST['paterno'] ?? ''),
            'materno' => trim($_POST['materno'] ?? ''),
            'rfc' => trim($_POST['rfc'] ?? ''),
            'curp' => trim($_POST['curp'] ?? ''),
            'calle' => trim($_POST['calle'] ?? ''),
            'nroint' => trim($_POST['nroint'] ?? ''),
            'nroext' => trim($_POST['nroext'] ?? ''),
            'cp' => trim($_POST['cp'] ?? ''),
            'colonia' => trim($_POST['colonia'] ?? ''),
            'delegacion' => trim($_POST['delegacion'] ?? ''),
            'edo' => trim($_POST['edo'] ?? ''),
            'emal' => trim($_POST['emal'] ?? ''),
            'tel' => trim($_POST['tel'] ?? ''),
            'tel2' => trim($_POST['tel2'] ?? ''),
            'ext' => trim($_POST['ext'] ?? ''),
            'tipo_persona' => trim($_POST['tipo_persona'] ?? ''),
            'regimen_fiscal' => trim($_POST['regimen_fiscal'] ?? ''),
            'userc' => trim($_POST['userc'] ?? ''),
            'useredit' => trim($_POST['useredit'] ?? ''),
            'fechac' => trim($_POST['fechac'] ?? ''),
            'fechaedit' => trim($_POST['fechaedit'] ?? ''),
            'coment' => trim($_POST['coment'] ?? ''),
            'Pais' => trim($_POST['Pais'] ?? ''),
            'altoriesg' => trim($_POST['altoriesg'] ?? ''),
            'fideicomitente' => trim($_POST['fideicomitente'] ?? ''),
            'fideicomisario' => trim($_POST['fideicomisario'] ?? ''),            
        ];
        
        
        // Procesar según sea creación o edición
        if ($id > 0) {
            // EDITAR
            $result = $clientesManager->updateCliente($id, $data);
            
            if ($result['success']) {
                $_SESSION['success'] = $result['message'];
                header('Location: ../../catalogos.php?mod=clientes&action=edit&id=' . $id);
            } else {
                $_SESSION['error'] = $result['message'];
                $_SESSION['form_data'] = $data; // Preservar datos del formulario
                header('Location: ../../catalogos.php?mod=clientes&action=edit&id=' . $id);
            }
        } else {
            // CREAR
            $result = $clientesManager->createCliente($data);

            if ($result['success']) {
                $_SESSION['success'] = $result['message'];
                header('Location: ../../catalogos.php?mod=clientes&action=edit&id=' . $result['cliente_id']);
            } else {
                $_SESSION['error'] = $result['message'];
                $_SESSION['form_data'] = $data; // Preservar datos del formulario
                header('Location: ../../catalogos.php?mod=clientes&action=create');
            }
        }
        break;
    
    // ========================================
    // ASIGNAR PERMISOS
    // ========================================
    case 'save-permissions':
        // Solo admin puede asignar permisos
        if (!$isAdmin) {
            $_SESSION['error'] = 'Solo los administradores pueden asignar permisos';
            header('Location: ../../catalogos.php?mod=clientes&action=list');
            exit;
        }

        $targetUserId = (int)($_POST['cliente_id'] ?? 0);

        if ($targetUserId <= 0) {
            $_SESSION['error'] = 'ID de cliente inválido';
            header('Location: ../../catalogos.php?mod=clientes&action=list');
            exit;
        }
        
        // Obtener permisos seleccionados
        $permissionIds = $_POST['permissions'] ?? [];
        
        // Validar que sean números
        $permissionIds = array_filter($permissionIds, function($id) {
            return is_numeric($id) && $id > 0;
        });
        
        $result = $clientesManager->assignPermissions($targetClienteId, $permissionIds);
        
        if ($result['success']) {
            $_SESSION['success'] = $result['message'];
        } else {
            $_SESSION['error'] = $result['message'];
        }
        
        header('Location: ../../catalogos.php?mod=clientes&action=permissions&id=' . $targetClienteId);
        break;
    
    // ========================================
    // ASIGNAR GRUPOS
    // ========================================
    case 'save-client':
        // Solo admin puede asignar grupos
        if (!$isAdmin) {
            $_SESSION['error'] = 'Solo los administradores pueden asignar grupos';
            header('Location: ../../catalogos.php?mod=clientes&action=list');
            exit;
        }
        
        $targetClienteId = (int)($_POST['cliente_id'] ?? 0);

        if ($targetClienteId <= 0) {
            $_SESSION['error'] = 'ID de cliente inválido';
            header('Location: ../../catalogos.php?mod=clientes&action=list');
            exit;
        }
        
        // Obtener grupos seleccionados
        $groupIds = $_POST['cliente'] ?? [];
        
        // Validar que sean números
        $groupIds = array_filter($groupIds, function($id) {
            return is_numeric($id) && $id > 0;
        });
        
        $result = $clientesManager->assignGroups($targetClienteId, $groupIds);

        if ($result['success']) {
            $_SESSION['success'] = $result['message'];
        } else {
            $_SESSION['error'] = $result['message'];
        }

        header('Location: ../../catalogos.php?mod=clientes&action=permissions&id=' . $targetClienteId . '&tab=groups');
        break;
    
    // ========================================
    // GENERAR API KEY
    // ========================================
    case 'generate-api-key':
        header('Content-Type: application/json');
        
        // Solo admin puede generar API keys
        if (!$isAdmin) {
            echo json_encode([
                'success' => false,
                'message' => 'Solo los administradores pueden generar API Keys'
            ]);
            exit;
        }
        
        $targetClienteId = (int)($_POST['cliente_id'] ?? 0);
        
        if ($targetClienteId <= 0) {
            echo json_encode([
                'success' => false,
                'message' => 'ID de cliente inválido'
            ]);
            exit;
        }
        
        $apiKey = $clientesManager->generateApiKey();
        $result = $clientesManager->updateApiKey($targetClienteId, $apiKey);
        
        echo json_encode($result);
        break;
    
    // ========================================
    // RESETEAR CONTRASEÑA
    // ========================================
    case 'reset-password':
        header('Content-Type: application/json');
        
        // Solo admin puede resetear contraseñas
        if (!$isAdmin) {
            echo json_encode([
                'success' => false,
                'message' => 'Solo los administradores pueden resetear contraseñas'
            ]);
            exit;
        }
        
        $targetClienteId = (int)($_POST['cliente_id'] ?? 0);
        
        if ($targetClienteId <= 0) {
            echo json_encode([
                'success' => false,
                'message' => 'ID de cliente inválido'
            ]);
            exit;
        }

        $result = $clientesManager->resetPassword($targetClienteId);
        echo json_encode($result);
        break;
    
    // ========================================
    // BÚSQUEDA RÁPIDA (AJAX)
    // ========================================
    case 'quick-search':
        header('Content-Type: application/json');
        
        $query = trim($_GET['q'] ?? '');
        
        if (strlen($query) < 2) {
            echo json_encode([
                'success' => false,
                'message' => 'Mínimo 2 caracteres'
            ]);
            exit;
        }
        
        $result = $usuariosManager->quickSearch($query, 10);
        echo json_encode($result);
        break;
    
    // ========================================
    // ACCIÓN NO VÁLIDA
    // ========================================
    default:
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Acción no válida'
            ]);
        } else {
            $_SESSION['error'] = 'Acción no válida';
            header('Location: ../../catalogos.php?mod=clientes&action=list');
        }
        break;
}

exit;
?>