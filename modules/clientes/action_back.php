<?php
/**
 * Procesamiento de acciones para el módulo de clientes
 * Maneja: CRUD, verificación QSQ, documentos
 */

session_start();
require_once '../../includes/config.php';
require_once '../../includes/Database.php';
require_once '../../includes/cliente.manager.php';
require_once 'permissions.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'No autorizado']));
}

// Instancias
$clientesManager = new ClientesManager();
$clientePermissions = new ClientePermissions($_SESSION['user_id']);

// Obtener acción
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Procesar según la acción
switch ($action) {
    
    // ==================== GUARDAR (Crear/Actualizar) ====================
    case 'save':
        // Verificar permisos
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        if ($id > 0) {
            if (!$clientePermissions->canEdit()) {
                $_SESSION['error'] = 'No tienes permiso para editar clientes';
                header('Location: ../../catalogos.php?mod=clientes&action=list');
                exit;
            }
        } else {
            if (!$clientePermissions->canCreate()) {
                $_SESSION['error'] = 'No tienes permiso para crear clientes';
                header('Location: ../../catalogos.php?mod=clientes&action=list');
                exit;
            }
            
            // Verificar QSQ para nuevos clientes
            if (!isset($_POST['qsq_verified']) || $_POST['qsq_verified'] !== 'true') {
                $_SESSION['error'] = 'Debe verificar el cliente con QSQ antes de guardarlo';
                header('Location: ../../catalogos.php?mod=clientes&action=create');
                exit;
            }
        }
        
        // Preparar datos
        $data = [
            'nombres' => $_POST['nombres'] ?? '',
            'paterno' => $_POST['paterno'] ?? '',
            'materno' => $_POST['materno'] ?? '',
            'rfc' => strtoupper($_POST['rfc'] ?? ''),
            'curp' => strtoupper($_POST['curp'] ?? ''),
            'calle' => $_POST['calle'] ?? '',
            'nroint' => $_POST['nroint'] ?? '',
            'nroext' => $_POST['nroext'] ?? '',
            'cp' => $_POST['cp'] ?? '',
            'colonia' => $_POST['colonia'] ?? '',
            'delegacion' => $_POST['delegacion'] ?? '',
            'edo' => $_POST['edo'] ?? null,
            'emal' => $_POST['emal'] ?? '',
            'tel' => $_POST['tel'] ?? '',
            'tel2' => $_POST['tel2'] ?? '',
            'ext' => $_POST['ext'] ?? '',
            'tipo_persona' => $_POST['tipo_persona'] ?? 'FISICA',
            'regimen_fiscal' => $_POST['regimen_fiscal'] ?? null,
            'coment' => $_POST['coment'] ?? '',
            'pais' => $_POST['pais'] ?? 1,
            'altoriesg' => isset($_POST['altoriesg']),
            'fideicomitente' => isset($_POST['fideicomitente']),
            'fideicomisario' => isset($_POST['fideicomisario']),
            'activo' => $_POST['activo'] ?? '1'
        ];
        
        // Guardar
        if ($id > 0) {
            $result = $clientesManager->updateCliente($id, $data);
        } else {
            $result = $clientesManager->createCliente($data);
            if ($result['success']) {
                $id = $result['cliente_id'];
            }
        }
        
        // Manejar documentos si se subieron
        if ($result['success'] && !empty($_FILES['documentos'])) {
            handleDocumentUpload($id, $_FILES['documentos']);
        }
        
        // Redirigir con mensaje
        if ($result['success']) {
            $_SESSION['success'] = $result['message'];
            header('Location: ../../catalogos.php?mod=clientes&action=edit&id=' . $id);
        } else {
            $_SESSION['error'] = $result['message'];
            $returnAction = $id > 0 ? 'edit&id=' . $id : 'create';
            header('Location: ../../catalogos.php?mod=clientes&action=' . $returnAction);
        }
        break;
    
    // ==================== ELIMINAR ====================
    case 'delete':
        if (!$clientePermissions->canDelete()) {
            die(json_encode(['success' => false, 'message' => 'No tienes permiso para eliminar clientes']));
        }
        
        $id = (int)($_POST['id'] ?? 0);
        $result = $clientesManager->deleteCliente($id);
        
        header('Content-Type: application/json');
        echo json_encode($result);
        break;
    
    // ==================== CAMBIAR ESTADO ====================
    case 'toggle-status':
        if (!$clientePermissions->canEdit()) {
            die(json_encode(['success' => false, 'message' => 'No tienes permiso para modificar clientes']));
        }
        
        $id = (int)($_POST['id'] ?? 0);
        $result = $clientesManager->toggleStatus($id);
        
        header('Content-Type: application/json');
        echo json_encode($result);
        break;
    
    // ==================== VERIFICAR QSQ ====================
    case 'verify_qsq':
        if (!$clientePermissions->canVerifyQSQ()) {
            die(json_encode(['success' => false, 'message' => 'No tienes permiso para verificar QSQ']));
        }
        
        $data = [
            'nombres' => $_POST['nombres'] ?? '',
            'paterno' => $_POST['paterno'] ?? '',
            'materno' => $_POST['materno'] ?? '',
            'rfc' => strtoupper($_POST['rfc'] ?? ''),
            'curp' => strtoupper($_POST['curp'] ?? ''),
            'tipo_persona' => $_POST['tipo_persona'] ?? 'FISICA'
        ];
        
        $result = verificarQSQApi($data);
        
        header('Content-Type: application/json');
        echo json_encode($result);
        break;
    
    // ==================== VERIFICAR QSQ INDIVIDUAL ====================
    case 'verify_qsq_single':
        if (!$clientePermissions->canVerifyQSQ()) {
            die(json_encode(['success' => false, 'message' => 'No tienes permiso para verificar QSQ']));
        }
        
        $id = (int)($_POST['id'] ?? 0);
        $clienteData = $clientesManager->getCliente($id);
        
        if (!$clienteData['success']) {
            die(json_encode(['success' => false, 'message' => 'Cliente no encontrado']));
        }
        
        $cliente = $clienteData['data'];
        $data = [
            'nombres' => $cliente['nombres'],
            'paterno' => $cliente['paterno'],
            'materno' => $cliente['materno'],
            'rfc' => $cliente['rfc'],
            'curp' => $cliente['curp'],
            'tipo_persona' => $cliente['tipo_persona']
        ];
        
        $result = verificarQSQApi($data);
        
        // Si hay alertas, actualizar el campo altoriesg
        if ($result['success'] && !$result['data']['valid']) {
            $updateData = ['altoriesg' => true];
            $clientesManager->updateCliente($id, $updateData);
        }
        
        header('Content-Type: application/json');
        echo json_encode($result);
        break;
    
    // ==================== ELIMINAR DOCUMENTO ====================
    case 'delete_document':
        if (!$clientePermissions->canManageDocuments()) {
            die(json_encode(['success' => false, 'message' => 'No tienes permiso para gestionar documentos']));
        }
        
        $docId = (int)($_POST['doc_id'] ?? 0);
        $result = $clientesManager->deleteDocumento($docId);
        
        header('Content-Type: application/json');
        echo json_encode($result);
        break;
    
    default:
        die(json_encode(['success' => false, 'message' => 'Acción no válida']));
}

// ==================== FUNCIONES AUXILIARES ====================

/**
 * Manejar carga de documentos
 */
function handleDocumentUpload($clienteId, $files) {
    global $clientesManager;
    
    $uploadDir = '../../uploads/clientes/' . $clienteId . '/';
    
    // Crear directorio si no existe
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 
                     'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    
    $maxSize = 10 * 1024 * 1024; // 10MB
    
    // Procesar cada archivo
    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }
        
        // Validar tipo de archivo
        if (!in_array($files['type'][$i], $allowedTypes)) {
            $_SESSION['warning'] = 'Algunos archivos no fueron cargados por tipo inválido';
            continue;
        }
        
        // Validar tamaño
        if ($files['size'][$i] > $maxSize) {
            $_SESSION['warning'] = 'Algunos archivos exceden el límite de 10MB';
            continue;
        }
        
        // Generar nombre único
        $extension = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
        $filename = uniqid('doc_') . '.' . $extension;
        $filepath = $uploadDir . $filename;
        
        // Mover archivo
        if (move_uploaded_file($files['tmp_name'][$i], $filepath)) {
            // Registrar en base de datos
            $fileData = [
                'filename' => $files['name'][$i],
                'filepath' => $filepath,
                'tipo' => 'general',
                'size' => $files['size'][$i],
                'mime_type' => $files['type'][$i]
            ];
            
            $clientesManager->addDocumento($clienteId, $fileData);
        }
    }
}

/**
 * Verificar contra API QSQ
 * Esta es una implementación de ejemplo - debes adaptarla a tu API real
 */
function verificarQSQApi($data) {
    try {
        // Preparar datos para el API
        $nombre_completo = trim($data['nombres'] . ' ' . $data['paterno'] . ' ' . $data['materno']);
        
        // Array de respuesta por defecto
        $response = [
            'success' => true,
            'data' => [
                'valid' => true,
                'messages' => [],
                'alerts' => []
            ]
        ];
        
        // ========== AQUÍ DEBES IMPLEMENTAR LA LLAMADA REAL AL API ==========
        
        // Ejemplo de implementación con cURL:
        /*
        $apiUrl = 'https://api-qsq.ejemplo.com/verify';
        $apiKey = 'TU_API_KEY_AQUI';
        
        $postData = [
            'name' => $nombre_completo,
            'rfc' => $data['rfc'],
            'curp' => $data['curp']
        ];
        
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);
        
        $apiResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $apiData = json_decode($apiResponse, true);
            
            // Procesar respuesta del API
            if (isset($apiData['blacklisted']) && $apiData['blacklisted']) {
                $response['data']['valid'] = false;
                $response['data']['messages'][] = 'Cliente encontrado en listas negras';
                $response['data']['alerts'][] = 'BLACKLIST';
            }
            
            if (isset($apiData['pep']) && $apiData['pep']) {
                $response['data']['valid'] = false;
                $response['data']['messages'][] = 'Cliente es Persona Políticamente Expuesta (PEP)';
                $response['data']['alerts'][] = 'PEP';
            }
            
            if (isset($apiData['sanctions']) && $apiData['sanctions']) {
                $response['data']['valid'] = false;
                $response['data']['messages'][] = 'Cliente tiene sanciones internacionales';
                $response['data']['alerts'][] = 'SANCTIONS';
            }
        } else {
            throw new Exception('Error en API: HTTP ' . $httpCode);
        }
        */
        
        // ========== SIMULACIÓN TEMPORAL (ELIMINAR EN PRODUCCIÓN) ==========
        // Simular verificación con datos de prueba
        $testBlacklist = ['TEST123', 'MALO456', 'DANGER789'];
        
        if (!empty($data['rfc']) && in_array(substr($data['rfc'], 0, 7), $testBlacklist)) {
            $response['data']['valid'] = false;
            $response['data']['messages'][] = 'RFC encontrado en lista negra de prueba';
            $response['data']['alerts'][] = 'TEST_BLACKLIST';
        }
        
        if (!empty($data['curp']) && strpos($data['curp'], 'XXX') !== false) {
            $response['data']['valid'] = false;
            $response['data']['messages'][] = 'CURP con patrón sospechoso';
            $response['data']['alerts'][] = 'SUSPICIOUS_CURP';
        }
        
        // Si no hay alertas
        if ($response['data']['valid']) {
            $response['data']['messages'][] = 'Cliente verificado correctamente';
            $response['data']['messages'][] = 'No se encontraron coincidencias en listas negras';
        }
        
        return $response;
        
    } catch (Exception $e) {
        error_log('Error en verificación QSQ: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error al verificar con el servicio QSQ: ' . $e->getMessage()
        ];
    }
}
?>