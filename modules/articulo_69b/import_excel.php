<?php
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Limpiar cualquier output previo
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Función para enviar respuesta JSON limpia
function sendJsonResponse($data) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Función para manejar errores fatales
function handleFatalError() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        sendJsonResponse([
            'success' => false,
            'message' => 'Error fatal del servidor: ' . $error['message']
        ]);
    }
}
register_shutdown_function('handleFatalError');

try {
    require_once '../../config.php';
    require_once '../../includes/Database.php';
    require_once '../../includes/Session.php';
    require_once '../../includes/Permissions.php';
    
    $session = new Session();
    $permissions = new Permissions();
    $userId = $session->getUserId();
    $isAdmin = $session->isAdmin();
    
    $canCreate = $permissions->hasPermission($userId, 'articulo_69b', 'creer') || $isAdmin;
    
    if (!$canCreate) {
        sendJsonResponse([
            'success' => false,
            'message' => 'Sin permisos para importar'
        ]);
    }
    
    // Debug: Log para verificar qué está llegando
    error_log("POST recibido: " . print_r($_POST, true));
    error_log("FILES recibido: " . print_r($_FILES, true));
    error_log("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'no definido'));
    
    // Verificar que se haya subido un archivo (puede venir como 'excel_file' o 'csv_file')
    $fileKey = null;
    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
        $fileKey = 'excel_file';
    } elseif (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $fileKey = 'csv_file';
    }
    
    if (!$fileKey) {
        $errorMsg = 'No se recibió ningún archivo';
        
        if (isset($_FILES['excel_file']['error'])) {
            switch ($_FILES['excel_file']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errorMsg = 'El archivo es demasiado grande';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errorMsg = 'El archivo se subió parcialmente';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $errorMsg = 'No se seleccionó ningún archivo';
                    break;
                default:
                    $errorMsg = 'Error al subir el archivo (código: ' . $_FILES['excel_file']['error'] . ')';
            }
        } elseif (isset($_FILES['csv_file']['error'])) {
            switch ($_FILES['csv_file']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errorMsg = 'El archivo es demasiado grande';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errorMsg = 'El archivo se subió parcialmente';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $errorMsg = 'No se seleccionó ningún archivo';
                    break;
                default:
                    $errorMsg = 'Error al subir el archivo (código: ' . $_FILES['csv_file']['error'] . ')';
            }
        }
        
        sendJsonResponse([
            'success' => false,
            'message' => $errorMsg
        ]);
    }
    
    // Validar extensión del archivo
    $fileName = $_FILES[$fileKey]['name'];
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    if (!in_array($fileExt, ['csv', 'txt', 'xlsx', 'xls'])) {
        sendJsonResponse([
            'success' => false,
            'message' => 'Solo se permiten archivos CSV, TXT o Excel (XLSX, XLS)'
        ]);
    }
    
    $db = Database::getInstance()->getConnection();

    // Aumentar límites de tiempo y memoria
    set_time_limit(300); // 5 minutos
    ini_set('memory_limit', '512M');

    // 🔥 OBTENER CONTEO ANTERIOR
    $stmtCount = $db->prepare("SELECT COUNT(*) as total FROM t_cat_articulo_69b");
    $stmtCount->execute();
    $conteoAnterior = (int)$stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

    // Leer el contenido del archivo subido
    $csvContent = null;
    
    // Si es Excel, convertir a CSV (requiere una librería, por ahora solo aceptamos CSV/TXT)
    if (in_array($fileExt, ['xlsx', 'xls'])) {
        sendJsonResponse([
            'success' => false,
            'message' => 'Por favor exporte el Excel a formato CSV (.csv) antes de importar'
        ]);
    }
    
    $csvContent = file_get_contents($_FILES[$fileKey]['tmp_name']);
    
    if ($csvContent === false || empty($csvContent)) {
        throw new Exception('No se pudo leer el archivo subido o está vacío');
    }
    
    if (strlen($csvContent) < 100) {
        throw new Exception('El archivo es muy pequeño o no contiene datos válidos');
    }
    
    // Convertir a UTF-8
    $encoding = mb_detect_encoding($csvContent, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII'], true);
    if ($encoding && $encoding !== 'UTF-8') {
        $csvContent = mb_convert_encoding($csvContent, 'UTF-8', $encoding);
    }
    
    // Dividir en líneas
    $lines = preg_split('/\r\n|\r|\n/', $csvContent);
    
    if (count($lines) < 3) {
        throw new Exception('El archivo CSV no tiene suficientes líneas (encontradas: ' . count($lines) . ')');
    }
    
    // Eliminar encabezados (primeras 3 líneas del formato SAT)
    array_shift($lines);
    array_shift($lines);
    array_shift($lines);
    
    $insertados = 0;
    $errores = 0;
    $omitidos = 0;
    $errorDetails = [];
    
    $db->beginTransaction();
    $db->exec("TRUNCATE TABLE t_cat_articulo_69b RESTART IDENTITY;");
    
    $stmtInsert = $db->prepare("INSERT INTO t_cat_articulo_69b (
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
    
    foreach ($lines as $lineNumber => $line) {
        $line = trim($line);
        
        if (empty($line)) {
            $omitidos++;
            continue;
        }
        
        try {
            $data = str_getcsv($line, ',', '"');
            
            if (count($data) < 4) {
                $omitidos++;
                continue;
            }
            
            $rfc = mb_strtoupper(trim($data[1] ?? ''), 'UTF-8');
            $nombre = mb_strtoupper(trim($data[2] ?? ''), 'UTF-8');
            $situacion = trim($data[3] ?? '');
            
            $params = [
                $rfc,
                $nombre,
                $situacion,
                trim($data[4] ?? ''),
                trim($data[5] ?? ''),
                trim($data[6] ?? ''),
                trim($data[7] ?? ''),
                trim($data[8] ?? ''),
                trim($data[9] ?? ''),
                trim($data[10] ?? ''),
                trim($data[11] ?? ''),
                trim($data[12] ?? ''),
                trim($data[13] ?? ''),
                trim($data[14] ?? ''),
                trim($data[15] ?? ''),
                trim($data[16] ?? ''),
                trim($data[17] ?? ''),
                '',
                ''
            ];
            
            $stmtInsert->execute($params);
            $insertados++;
            
        } catch (PDOException $e) {
            $errores++;
            $errorDetails[] = [
                'linea' => $lineNumber + 4, // +4 porque quitamos 3 líneas de encabezado
                'rfc' => $rfc ?? 'N/A',
                'error' => substr($e->getMessage(), 0, 100)
            ];
            
            error_log("Error importación línea " . ($lineNumber + 4) . ": " . $e->getMessage());
            
            if ($errores > 50) {
                throw new Exception("Demasiados errores (>50). Proceso abortado.");
            }
        }
    }
    
    $db->commit();
    
    // 🔥 CALCULAR COMPARATIVO
    $conteoActual = $insertados;
    $diferencia = $conteoActual - $conteoAnterior;
    $fechaActualizacion = date('Y-m-d H:i:s');
    
    // 🔥 GUARDAR COMPARATIVO EN LA BASE DE DATOS
    $comparativoData = [
        'anterior' => $conteoAnterior,
        'actual' => $conteoActual,
        'diferencia' => $diferencia,
        'fecha' => $fechaActualizacion
    ];
    
    $comparativoJson = json_encode($comparativoData, JSON_UNESCAPED_UNICODE);
    
    try {
        // Verificar si ya existe el registro
        $stmtCheck = $db->prepare("SELECT COUNT(*) as existe FROM t_const WHERE nom = 'Art_69B_Comparativo'");
        $stmtCheck->execute();
        $existe = $stmtCheck->fetch(PDO::FETCH_ASSOC)['existe'];
        
        if ($existe > 0) {
            // Actualizar
            $stmtUpdate = $db->prepare("UPDATE t_const SET val = ? WHERE nom = 'Art_69B_Comparativo'");
            $stmtUpdate->execute([$comparativoJson]);
        } else {
            // Insertar
            $stmtInsertComp = $db->prepare("INSERT INTO t_const (nom, val) VALUES ('Art_69B_Comparativo', ?)");
            $stmtInsertComp->execute([$comparativoJson]);
        }
        
        error_log("Comparativo guardado en BD: " . $comparativoJson);
    } catch (PDOException $e) {
        error_log("Error al guardar comparativo en BD: " . $e->getMessage());
        // No bloqueamos la respuesta si falla esto
    }
    
    $message = 'Importación completada exitosamente';
    if ($errores > 0) {
        $message .= " con $errores error(es)";
    }
    
    // 🔥 DEVOLVER COMPARATIVO EN LA RESPUESTA
    sendJsonResponse([
        'success' => true,
        'message' => $message,
        'stats' => [
            'insertados' => $insertados,
            'errores' => $errores,
            'omitidos' => $omitidos,
            'total_procesados' => $insertados,
            'total_lineas' => count($lines),
            'conteo_anterior' => $conteoAnterior,
            'conteo_actual' => $conteoActual
        ],
        'comparativo' => $comparativoData,
        'error_details' => array_slice($errorDetails, 0, 5)
    ]);
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    error_log("Error crítico importación SAT: " . $e->getMessage());
    
    sendJsonResponse([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'stats' => [
            'insertados' => $insertados ?? 0,
            'actualizados' => 0,
            'errores' => $errores ?? 0,
            'omitidos' => $omitidos ?? 0,
            'total_procesados' => ($insertados ?? 0)
        ]
    ]);
} catch (Throwable $e) {
    error_log("Error inesperado importación SAT: " . $e->getMessage());
    
    sendJsonResponse([
        'success' => false,
        'message' => 'Error inesperado del servidor'
    ]);
}