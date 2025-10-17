<?php
/**
 * modules/grupos/actions.php
 * Procesamiento de acciones CRUD para grupos
 */

// Cargar configuración
require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/Session.php';
require_once '../../includes/GruposManager.php';

// Iniciar sesión y verificar autenticación
$session = new Session();

if (!$session->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

$isAdmin = $session->isAdmin();
$gruposManager = new GruposManager();

// Obtener acción
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Procesar según la acción
switch ($action) {
    
    case 'create':
        // Verificar permisos
        if (!$isAdmin && !$session->hasPermission('catalogos', 'creer', 'grupos')) {
            echo json_encode(['success' => false, 'message' => 'Sin permisos para crear grupos']);
            exit;
        }
        
        // Validar datos
        $data = [
            'nom' => $_POST['nom'] ?? '',
            'note' => $_POST['note'] ?? ''
        ];
        
        $result = $gruposManager->createGrupo($data);
        
        if ($result['success']) {
            // Redirigir al listado o a permisos
            if (isset($_POST['redirect']) && $_POST['redirect'] === 'permissions') {
                header("Location: ../../catalogos.php?mod=grupos&action=permissions&id=" . $result['grupo_id']);
            } else {
                header("Location: ../../catalogos.php?mod=grupos&action=list&message=created");
            }
        } else {
            // Volver al formulario con error
            header("Location: ../../catalogos.php?mod=grupos&action=create&error=" . urlencode($result['message']));
        }
        break;
        
    case 'update':
        // Verificar permisos
        if (!$isAdmin && !$session->hasPermission('catalogos', 'modifier', 'grupos')) {
            echo json_encode(['success' => false, 'message' => 'Sin permisos para editar grupos']);
            exit;
        }
        
        $id = $_POST['id'] ?? 0;
        $data = [
            'nom' => $_POST['nom'] ?? '',
            'note' => $_POST['note'] ?? ''
        ];
        
        $result = $gruposManager->updateGrupo($id, $data);
        
        if ($result['success']) {
            header("Location: ../../catalogos.php?mod=grupos&action=list&message=updated");
        } else {
            header("Location: ../../catalogos.php?mod=grupos&action=edit&id={$id}&error=" . urlencode($result['message']));
        }
        break;
        
    case 'delete':
        // Verificar permisos
        if (!$isAdmin && !$session->hasPermission('catalogos', 'supprimer', 'grupos')) {
            echo json_encode(['success' => false, 'message' => 'Sin permisos para eliminar grupos']);
            exit;
        }
        
        $id = $_POST['id'] ?? $_GET['id'] ?? 0;
        
        $result = $gruposManager->deleteGrupo($id);
        echo json_encode($result);
        break;
        
    case 'duplicate':
        // Verificar permisos
        if (!$isAdmin && !$session->hasPermission('catalogos', 'creer', 'grupos')) {
            echo json_encode(['success' => false, 'message' => 'Sin permisos para duplicar grupos']);
            exit;
        }
        
        $id = $_POST['id'] ?? $_GET['id'] ?? 0;
        
        $result = $gruposManager->duplicateGrupo($id);
        echo json_encode($result);
        break;
        
    case 'assign-permissions':
        // Verificar permisos
        if (!$isAdmin && !$session->hasPermission('catalogos', 'modifier', 'grupos')) {
            echo json_encode(['success' => false, 'message' => 'Sin permisos para modificar permisos']);
            exit;
        }
        
        $grupoId = $_POST['grupo_id'] ?? 0;
        $permissions = $_POST['permissions'] ?? [];
        
        $result = $gruposManager->assignPermissions($grupoId, $permissions);
        
        if ($result['success']) {
            header("Location: ../../catalogos.php?mod=grupos&action=permissions&id={$grupoId}&message=permissions_updated");
        } else {
            header("Location: ../../catalogos.php?mod=grupos&action=permissions&id={$grupoId}&error=" . urlencode($result['message']));
        }
        break;
        
    case 'assign-usuarios':
        // Verificar permisos
        if (!$isAdmin && !$session->hasPermission('catalogos', 'modifier', 'grupos')) {
            echo json_encode(['success' => false, 'message' => 'Sin permisos para modificar usuarios']);
            exit;
        }
        
        $grupoId = $_POST['grupo_id'] ?? 0;
        $usuarios = $_POST['usuarios'] ?? [];
        
        $result = $gruposManager->assignUsuarios($grupoId, $usuarios);
        
        if ($result['success']) {
            header("Location: ../../catalogos.php?mod=grupos&action=usuarios&id={$grupoId}&message=usuarios_updated");
        } else {
            header("Location: ../../catalogos.php?mod=grupos&action=usuarios&id={$grupoId}&error=" . urlencode($result['message']));
        }
        break;
        
    case 'remove-usuario':
        // Verificar permisos
        if (!$isAdmin && !$session->hasPermission('catalogos', 'modifier', 'grupos')) {
            echo json_encode(['success' => false, 'message' => 'Sin permisos para remover usuarios']);
            exit;
        }
        
        $grupoId = $_POST['grupo_id'] ?? 0;
        $userId = $_POST['user_id'] ?? 0;
        
        $result = $gruposManager->removeUsuario($grupoId, $userId);
        echo json_encode($result);
        break;
        
    case 'export-csv':
        // Verificar permisos de lectura
        if (!$isAdmin && !$session->hasPermission('catalogos', 'lire', 'grupos')) {
            die('Sin permisos para exportar');
        }
        
        $filters = [
            'search' => $_GET['search'] ?? ''
        ];
        
        $result = $gruposManager->exportToCSV($filters);
        
        if ($result['success']) {
            // Configurar headers para descarga
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="grupos_' . date('Y-m-d') . '.csv"');
            
            // BOM para UTF-8 en Excel
            echo "\xEF\xBB\xBF";
            echo $result['data'];
        } else {
            echo "Error al exportar: " . $result['message'];
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        break;
}
?>