<?php
/**
 * modules/usuarios/actions.php
 * Procesamiento de acciones del módulo de usuarios
 * Maneja: duplicar, eliminar, toggle status, export CSV, save
 */

// Cargar configuración y clases
require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/Session.php';
require_once '../../includes/Permissions.php';
require_once '../../includes/Auth.php';
require_once '../../includes/UsuariosManager.php';

// Iniciar sesión
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

// Obtener datos del usuario
$userId = $session->getUserId();
$isAdmin = $session->isAdmin();

// Verificar permiso general del módulo
if (!$isAdmin && !$session->hasPermission('catalogos', 'lire', 'usuarios')) {
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

// Crear instancia del manager
$usuariosManager = new UsuariosManager();

// Obtener acción
$action = $_REQUEST['action'] ?? '';

// ========================================
// PROCESAR ACCIONES
// ========================================

switch ($action) {
    
    // ========================================
    // DUPLICAR USUARIO
    // ========================================
    case 'duplicate':
        header('Content-Type: application/json');
        
        // Verificar permiso de crear
        if (!$isAdmin && !$session->hasPermission('catalogos', 'creer', 'usuarios')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para duplicar usuarios'
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
        if (!$isAdmin && !$session->hasPermission('catalogos', 'modifier', 'usuarios')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para cambiar el estado de usuarios'
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
        if (!$isAdmin && !$session->hasPermission('catalogos', 'supprimer', 'usuarios')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para eliminar usuarios'
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
        
        // No permitir eliminar al usuario actual
        if ($id == $userId) {
            echo json_encode([
                'success' => false,
                'message' => 'No puedes eliminar tu propio usuario'
            ]);
            exit;
        }
        
        $result = $usuariosManager->deleteUsuario($id);
        echo json_encode($result);
        break;
    
    // ========================================
    // EXPORTAR A CSV
    // ========================================
    case 'export-csv':
        // Verificar permiso de exportar
        if (!$isAdmin && !$session->hasPermission('catalogos', 'export', 'usuarios')) {
            die('No tienes permiso para exportar usuarios');
        }
        
        // Obtener filtros actuales
        $filters = [
            'search' => $_GET['search'] ?? '',
            'status' => $_GET['status'] ?? '',
            'role' => $_GET['role'] ?? '',
            'group' => $_GET['group'] ?? ''
        ];
        
        $result = $usuariosManager->exportToCSV($filters);
        
        if ($result['success']) {
            $filename = 'usuarios_' . date('Y-m-d_H-i-s') . '.csv';
            
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
            if (!$isAdmin && !$session->hasPermission('catalogos', 'modifier', 'usuarios')) {
                $_SESSION['error'] = 'No tienes permiso para editar usuarios';
                header('Location: ../../catalogos.php?mod=usuarios&action=list');
                exit;
            }
        } else {
            // Si es creación, verificar permiso de crear
            if (!$isAdmin && !$session->hasPermission('catalogos', 'creer', 'usuarios')) {
                $_SESSION['error'] = 'No tienes permiso para crear usuarios';
                header('Location: ../../catalogos.php?mod=usuarios&action=list');
                exit;
            }
        }
        
        // Recopilar datos del formulario
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'admin' => isset($_POST['admin']),
            'empleado' => isset($_POST['empleado']),
            'statut' => isset($_POST['statut']) ? (int)$_POST['statut'] : 1,
            
            // Datos del perfil
            'civility' => $_POST['civility'] ?? null,
            'firstname' => trim($_POST['firstname'] ?? ''),
            'lastname' => trim($_POST['lastname'] ?? ''),
            'direccion' => trim($_POST['direccion'] ?? ''),
            'zip' => trim($_POST['zip'] ?? ''),
            'ciudad' => trim($_POST['ciudad'] ?? ''),
            'pais' => trim($_POST['pais'] ?? 'México'),
            'edo' => trim($_POST['edo'] ?? ''),
            'birth' => $_POST['birth'] ?? null,
            'puesto' => trim($_POST['puesto'] ?? ''),
            'tel' => trim($_POST['tel'] ?? ''),
            'tel2' => trim($_POST['tel2'] ?? ''),
            'ext' => trim($_POST['ext'] ?? ''),
            'firma' => $_POST['firma'] ?? null,
            'note_public' => $_POST['note_public'] ?? null,
            'note_private' => $_POST['note_private'] ?? null,
            'gender' => $_POST['gender'] ?? null,
            'adminfide' => $_POST['adminfide'] ?? null,
            'supervisor' => $_POST['supervisor'] ?? null,
        ];
        
        // API Key - solo admin puede modificar
        if ($isAdmin && isset($_POST['api_key'])) {
            $data['api_key'] = trim($_POST['api_key']);
        }
        
        // Procesar según sea creación o edición
        if ($id > 0) {
            // EDITAR
            $result = $usuariosManager->updateUsuario($id, $data);
            
            if ($result['success']) {
                $_SESSION['success'] = $result['message'];
                header('Location: ../../catalogos.php?mod=usuarios&action=edit&id=' . $id);
            } else {
                $_SESSION['error'] = $result['message'];
                $_SESSION['form_data'] = $data; // Preservar datos del formulario
                header('Location: ../../catalogos.php?mod=usuarios&action=edit&id=' . $id);
            }
        } else {
            // CREAR
            $result = $usuariosManager->createUsuario($data);
            
            if ($result['success']) {
                $_SESSION['success'] = $result['message'];
                header('Location: ../../catalogos.php?mod=usuarios&action=edit&id=' . $result['user_id']);
            } else {
                $_SESSION['error'] = $result['message'];
                $_SESSION['form_data'] = $data; // Preservar datos del formulario
                header('Location: ../../catalogos.php?mod=usuarios&action=create');
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
            header('Location: ../../catalogos.php?mod=usuarios&action=list');
            exit;
        }
        
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        
        if ($targetUserId <= 0) {
            $_SESSION['error'] = 'ID de usuario inválido';
            header('Location: ../../catalogos.php?mod=usuarios&action=list');
            exit;
        }
        
        // Obtener permisos seleccionados
        $permissionIds = $_POST['permissions'] ?? [];
        
        // Validar que sean números
        $permissionIds = array_filter($permissionIds, function($id) {
            return is_numeric($id) && $id > 0;
        });
        
        $result = $usuariosManager->assignPermissions($targetUserId, $permissionIds);
        
        if ($result['success']) {
            $_SESSION['success'] = $result['message'];
        } else {
            $_SESSION['error'] = $result['message'];
        }
        
        header('Location: ../../catalogos.php?mod=usuarios&action=permissions&id=' . $targetUserId);
        break;
    
    // ========================================
    // ASIGNAR GRUPOS
    // ========================================
    case 'save-groups':
        // Solo admin puede asignar grupos
        if (!$isAdmin) {
            $_SESSION['error'] = 'Solo los administradores pueden asignar grupos';
            header('Location: ../../catalogos.php?mod=usuarios&action=list');
            exit;
        }
        
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        
        if ($targetUserId <= 0) {
            $_SESSION['error'] = 'ID de usuario inválido';
            header('Location: ../../catalogos.php?mod=usuarios&action=list');
            exit;
        }
        
        // Obtener grupos seleccionados
        $groupIds = $_POST['groups'] ?? [];
        
        // Validar que sean números
        $groupIds = array_filter($groupIds, function($id) {
            return is_numeric($id) && $id > 0;
        });
        
        $result = $usuariosManager->assignGroups($targetUserId, $groupIds);
        
        if ($result['success']) {
            $_SESSION['success'] = $result['message'];
        } else {
            $_SESSION['error'] = $result['message'];
        }
        
        header('Location: ../../catalogos.php?mod=usuarios&action=permissions&id=' . $targetUserId . '&tab=groups');
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
        
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        
        if ($targetUserId <= 0) {
            echo json_encode([
                'success' => false,
                'message' => 'ID de usuario inválido'
            ]);
            exit;
        }
        
        $apiKey = $usuariosManager->generateApiKey();
        $result = $usuariosManager->updateApiKey($targetUserId, $apiKey);
        
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
        
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        
        if ($targetUserId <= 0) {
            echo json_encode([
                'success' => false,
                'message' => 'ID de usuario inválido'
            ]);
            exit;
        }
        
        $result = $usuariosManager->resetPassword($targetUserId);
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
            header('Location: ../../catalogos.php?mod=usuarios&action=list');
        }
        break;
}

exit;
?>