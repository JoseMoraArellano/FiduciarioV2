<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Cargar configuración
require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/Session.php';
require_once '../../includes/Permissions.php';
require_once '../../includes/Auth.php';
require_once '../../includes/GruposManager.php';
require_once '../../includes/ClienteManager.php';

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
// obtener datos del cliente
$userId = $session->getUserId();
$isAdmin = $session->isAdmin();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if (!$isAdmin && !$session->hasPermission('catalogos', 'lire', 'clientes')) {
    header('Content-Type: application/json');
    header('HTTP/1.1 403 Forbidden');

    echo json_encode([
        'success' => false,
        'message' => 'No tienes permisos para realizar esta acción'
    ]);
    exit;
}
// crear instancia del manager de clientes
$clientesManager = new ClienteManager();
switch ($action) {
    // ========================================
    // VER DETALLES DEL CLIENTE (Redirección)
    // ========================================
    case 'view':
        // Verificar permiso de ver
        if (!$isAdmin && !$session->hasPermission('catalogos', 'lire', 'clientes')) {
            $_SESSION['error'] = 'No tienes permiso para ver detalles de clientes';
            header('Location: ../../catalogos.php?mod=clientes&action=list');
            exit;
        }

        $id = (int)($_GET['id'] ?? 0);

        if ($id <= 0) {
            $_SESSION['error'] = 'ID de cliente inválido';
            header('Location: ../../catalogos.php?mod=clientes&action=list');
            exit;
        }

        // Redirigir a la página de visualización
        header('Location: ../../catalogos.php?mod=clientes&action=view&id=' . $id);
        exit;
        break;

    // ========================================
    // EDITAR CLIENTE (Redirección)
    // ========================================
    case 'edit':
        // Verificar permiso de editar
        if (!$isAdmin && !$session->hasPermission('catalogos', 'modifier', 'clientes')) {
            $_SESSION['error'] = 'No tienes permiso para editar clientes';
            header('Location: ../../catalogos.php?mod=clientes&action=list');
            exit;
        }

        $id = (int)($_GET['id'] ?? 0);

        if ($id <= 0) {
            $_SESSION['error'] = 'ID de cliente inválido';
            header('Location: ../../catalogos.php?mod=clientes&action=list');
            exit;
        }

        // Redirigir a la página de edición
        header('Location: ../../catalogos.php?mod=clientes&action=edit&id=' . $id);
        exit;
        break;

    // ========================================
    // GUARDAR CLIENTE (CREAR/EDITAR)
    // ========================================
    case 'save':

        $id = (int)($_POST['id'] ?? 0);

        // Verificar permisos
        if ($id > 0) {
            if (!$isAdmin && !$session->hasPermission('catalogos', 'modifier', 'clientes')) {
                $_SESSION['error'] = 'No tienes permiso para editar clientes';
                header('Location: ../../catalogos.php?mod=clientes&action=list');
                exit;
            }
        } else {
            if (!$isAdmin && !$session->hasPermission('catalogos', 'creer', 'clientes')) {
                $_SESSION['error'] = 'No tienes permiso para crear clientes';
                header('Location: ../../catalogos.php?mod=clientes&action=list');
                exit;
            }
        }

        // Recopilar datos del formulario
        $data = [
            'nombres' => trim($_POST['nombres'] ?? ''),
            'paterno' => trim($_POST['paterno'] ?? ''),
            'materno' => trim($_POST['materno'] ?? ''),
            'rfc' => trim($_POST['rfc'] ?? ''),
            'curp' => trim($_POST['curp'] ?? ''),
            'calle' => trim($_POST['calle'] ?? ''),
            'nroint' => trim($_POST['nroint'] ?? ''),
            'nroext' => trim($_POST['nroext'] ?? ''),
            'cp' => !empty($_POST['cp']) ? trim($_POST['cp']) : null,
            'colonia' => trim($_POST['colonia'] ?? ''),
            'delegacion' => trim($_POST['delegacion'] ?? ''),
            'edo' => !empty($_POST['edo']) ? (int)$_POST['edo'] : null,
            'emal' => trim($_POST['emal'] ?? ''),
            'tel' => trim($_POST['tel'] ?? ''),
            'tel2' => trim($_POST['tel2'] ?? ''),
            'ext' => trim($_POST['ext'] ?? ''),
            'tipo_persona' => trim($_POST['tipo_persona'] ?? 'FISICA'),
            'regimen_fiscal' => !empty($_POST['regimen_fiscal']) ? (int)$_POST['regimen_fiscal'] : null,
            'coment' => trim($_POST['coment'] ?? ''),
            'pais' => !empty($_POST['pais']) ? (int)$_POST['pais'] : 1,
            'altoriesg' => isset($_POST['altoriesg']) && $_POST['altoriesg'] == '1',
            'fideicomitente' => isset($_POST['fideicomitente']) && $_POST['fideicomitente'] == '1',
            'fideicomisario' => isset($_POST['fideicomisario']) && $_POST['fideicomisario'] == '1',
            'activo' => isset($_POST['activo']) ? $_POST['activo'] == '1' : true,
        ];

        // Guardar o actualizar cliente
        if ($id > 0) {
            // === EDITAR ===
            error_log("Editando cliente ID: $id");
            $result = $clientesManager->updateCliente($id, $data);
            $clienteId = $id;
        } else {
            // === CREAR ===
            error_log("Creando nuevo cliente");
            $result = $clientesManager->createCliente($data);
            $clienteId = $result['cliente_id'] ?? 0;
        }

        if ($result['success']) {
            error_log("Cliente guardado exitosamente. ID: $clienteId");

            // Procesar archivos usando el Manager
            $mensajesArchivos = [];
            $totalArchivosSubidos = 0;

            // Procesar archivos del tab Documentos
            if (!empty($_FILES['documentos_fiscales']['name'][0])) {
                error_log("Procesando archivos del tab Documentos");
                $resultDocs = $clientesManager->procesarArchivosSubidos(
                    $clienteId,
                    $_FILES['documentos_fiscales'],
                    'general'
                );

                error_log("Resultado documentos generales: " . print_r($resultDocs, true));

                if ($resultDocs['total_subidos'] > 0) {
                    $mensajesArchivos[] = $resultDocs['message'];
                    $totalArchivosSubidos += $resultDocs['total_subidos'];
                }

                if (!empty($resultDocs['errores'])) {
                    error_log("Errores en documentos generales: " . print_r($resultDocs['errores'], true));
                }
            } else {
                error_log("No hay archivos en documentos[]");
            }

            // Preparar mensaje de éxito
            $_SESSION['success'] = $result['message'];
            if ($totalArchivosSubidos > 0) {
                $_SESSION['success'] .= ' (' . $totalArchivosSubidos . ' documento(s) subido(s))';
            }

            if (!empty($mensajesArchivos)) {
                error_log("Mensajes de archivos: " . implode(' | ', $mensajesArchivos));
            }

            error_log("Redirigiendo a edit con ID: $clienteId");
            header('Location: ../../catalogos.php?mod=clientes&action=edit&id=' . $clienteId);
        } else {
            error_log("Error al guardar cliente: " . $result['message']);
            $_SESSION['error'] = $result['message'];
            $_SESSION['form_data'] = $data;

            if ($id > 0) {
                header('Location: ../../catalogos.php?mod=clientes&action=edit&id=' . $id);
            } else {
                header('Location: ../../catalogos.php?mod=clientes&action=create');
            }
        }
        exit;
        break;

    // ========================================
    // VERIFICACIÓN QSQ
    // ========================================
    case 'verify_qsq':
        header('Content-Type: application/json');

        // Verificar permiso de QSQ
        if (!$isAdmin && !$session->hasPermission('catalogos', 'verify_qsq', 'clientes')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para ejecutar verificaciones QSQ'
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

        // Obtener datos del cliente
        $clienteData = $clientesManager->getCliente($id);

        if (!$clienteData['success']) {
            echo json_encode([
                'success' => false,
                'message' => $clienteData['message']
            ]);
            exit;
        }

        // Ejecutar verificación QSQ
        $result = $clientesManager->verificarQSQ($clienteData['data']);

        if ($result['success']) {
            // Registrar en historial
            $clientesManager->addHistorial($id, 'QSQ_VERIFICATION', 'Verificación QSQ ejecutada', $result['data']);

            echo json_encode([
                'success' => true,
                'message' => 'Verificación QSQ completada',
                'data' => $result['data']
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => $result['message']
            ]);
        }
        break;

    // ========================================
    // VERIFICACIÓN QSQ INDIVIDUAL (desde lista)
    // ========================================
    case 'verify_qsq_single':
        header('Content-Type: application/json');

        // Verificar permiso de QSQ
        if (!$isAdmin && !$session->hasPermission('catalogos', 'verify_qsq', 'clientes')) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permiso para ejecutar verificaciones QSQ'
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

        // Obtener datos del cliente
        $clienteData = $clientesManager->getCliente($id);

        if (!$clienteData['success']) {
            echo json_encode([
                'success' => false,
                'message' => $clienteData['message']
            ]);
            exit;
        }

        // Ejecutar verificación QSQ simplificada para lista
        $result = $clientesManager->verificarQSQ($clienteData['data']);

        if ($result['success']) {
            // Registrar en historial
            $clientesManager->addHistorial($id, 'QSQ_VERIFICATION', 'Verificación QSQ ejecutada desde lista', $result['data']);

            echo json_encode([
                'success' => true,
                'message' => 'Verificación QSQ completada',
                'data' => $result['data']
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => $result['message']
            ]);
        }
        break;

    // ========================================
    // DUPLICAR CLIENTE
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
                'message' => 'ID de cliente inválido'
            ]);
            exit;
        }

        $result = $clientesManager->duplicateCliente($id);
        echo json_encode($result);
        break;

    // ========================================
    // CAMBIAR ESTADO (ACTIVAR/DESACTIVAR)
    // ========================================
    case 'toggle-status':
        header('Content-Type: application/json');

        // Verificar permiso
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
                'message' => 'ID de cliente inválido'
            ]);
            exit;
        }

        // Obtener datos del cliente
        $clienteData = $clientesManager->getCliente($id);
        $clienteNombre = $clienteData['nombre_completo'] ?? ' ';

        // Cambiar estado
        $result = $clientesManager->toggleStatus($id);

        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'message' => $result['message'],
                'new_status' => $result['new_status']
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => $result['message']
            ]);
        }
        break;

    // ========================================
    // ELIMINAR CLIENTE
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

        // Obtener datos del cliente para el mensaje
        $clienteData = $clientesManager->getCliente($id);
        $clienteNombre = $clienteData['success'] ? $clienteData['data']['nombre_completo'] : 'Cliente';

        $result = $clientesManager->deleteCliente($id);

        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'message' => "Cliente '{$clienteNombre}' eliminado correctamente"
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => $result['message']
            ]);
        }
        break;

    // ========================================
    // ELIMINAR DOCUMENTO
        case 'delete_document':
        $docId = intval($_POST['document_id'] ?? 0);

        if ($docId <= 0) {
            echo json_encode([
                'success' => false,
                'message' => 'ID de documento inválido'
            ]);
            exit;
        }

        $result = $clientesManager->deleteDocumento($docId);

        if ($result) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No se pudo eliminar el documento'
            ]);
        }
        exit;

       break;
    // ========================================
    // EXPORTAR A CSV
    // ========================================
    case 'export':
        // Verificar permiso de exportar
        if (!$isAdmin && !$session->hasPermission('catalogos', 'export', 'clientes')) {
            header('HTTP/1.0 403 Forbidden');
            die('No tienes permisos para exportar clientes');
        }

        // Redirigir al archivo de exportación específico
        $queryString = http_build_query($_GET);
        header('Location: export.php?' . $queryString);
        exit;
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
        $permissionIds = array_filter($permissionIds, function ($id) {
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
        $groupIds = array_filter($groupIds, function ($id) {
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

        $result = $clientesManager->quickSearch($query, 10);
        echo json_encode($result);
        break;

    // ========================================
    // ACCIÓN NO VÁLIDA
    // ========================================
    default:
        error_log("Acción no válida recibida: " . $action);
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
