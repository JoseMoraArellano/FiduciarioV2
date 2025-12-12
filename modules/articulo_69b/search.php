<?php
// ==================== modules/articulo_69b/search.php ====================

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

try {
    // PRIMERO: Cargar el config.php que tiene las constantes DB_HOST, DB_USER, etc.
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
    
    // SEGUNDO: Cargar las clases de includes
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

    $canView = $isAdmin
        || $permissions->hasPermission($userId, 'articulo_69b', 'lire')
        || $session->hasPermission('catalogos', 'lire', 'articulo_69b');

    if (!$canView) {
        echo json_encode([
            'success' => false,
            'message' => 'No tienes permisos para ver este módulo'
        ]);
        exit;
    }

    // Obtener parámetros de búsqueda
    $rfc = isset($_GET['rfc']) ? trim($_GET['rfc']) : '';
    $nombre = isset($_GET['nombre']) ? trim($_GET['nombre']) : '';
    $situacion = isset($_GET['situacion']) ? trim($_GET['situacion']) : '';
    
    // Validar que haya al menos un criterio de búsqueda válido
    $hasValidSearch = (strlen($rfc) >= 3) || (strlen($nombre) >= 3) || !empty($situacion);
    
    if (!$hasValidSearch) {
        echo json_encode([
            'success' => true,
            'data' => [],
            'message' => 'Ingrese al menos 3 caracteres para buscar'
        ]);
        exit;
    }

    // Construir query dinámicamente
    $query = "SELECT 
        id, rfc, nombre_contribuyente, situacion_contribuyente,
        numero_fecha_oficio_presuncion_sat, publicacion_sat_presuntos,
        numero_fecha_oficio_presuncion_dof, publicacion_dof_presuntos,
        numero_fecha_oficio_desvirtuar_sat, publicacion_sat_desvirtuados,
        numero_fecha_oficio_desvirtuar_dof, publicacion_dof_desvirtuados,
        numero_fecha_oficio_definitivos_sat, publicacion_sat_definitivos,
        numero_fecha_oficio_definitivos_dof, publicacion_dof_definitivos,
        numero_fecha_oficio_sentencia_sat, publicacion_sat_sentencia_favorable,
        numero_fecha_oficio_sentencia_dof, publicacion_dof_sentencia_favorable
        FROM t_cat_articulo_69b WHERE 1=1";
    
    $params = [];

    if (!empty($rfc)) {
        $query .= " AND rfc LIKE :rfc";
        $params[':rfc'] = '%' . $rfc . '%';
    }

    if (!empty($nombre)) {
        $query .= " AND nombre_contribuyente LIKE :nombre";
        $params[':nombre'] = '%' . $nombre . '%';
    }

    if (!empty($situacion)) {
        $query .= " AND situacion_contribuyente = :situacion";
        $params[':situacion'] = $situacion;
    }

    $query .= " ORDER BY rfc ASC LIMIT 500";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $registros,
        'count' => count($registros)
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log("Error PDO en búsqueda: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error en la base de datos'
    ]);
} catch (Exception $e) {
    error_log("Error en búsqueda: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor'
    ]);
}