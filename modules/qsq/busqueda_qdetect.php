<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Permissions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Sidebar.php';


$session = new Session();


if (isset($database)) {
    $db = $database;
} elseif (class_exists('Database')) {
    $db = Database::getInstance()->getConnection();
} else {
    die('No se pudo obtener conexión a la base de datos');
}

if (!$session->isLoggedIn()) {
    header('Location: ../../login.php');
    exit;
}

$userData = $session->getUserData();
$userId = $session->getUserId();
$isAdmin = $session->isAdmin();
$userPermissions = $userData['permissions'] ?? [];

$currentFile = basename(__FILE__);
$sidebar = new Sidebar($userPermissions, $userId, $currentFile, $isAdmin);
$menuStats = $sidebar->getMenuStats();


$canRead = $session->hasPermission('qdetect', 'lire', 'leer') || $isAdmin;
$canCreate = $session->hasPermission('qdetect', 'creer', 'crear') || $isAdmin;

if (!$canRead) {
    header('Location: ../../login.php?error=permission_denied');
    exit();
}

$pageTitle = 'Búsqueda Q-Detect';
$pageDescription = 'Consulta de información en la base de datos Q-Detect';
$pageIcon = 'fa-solid fa-magnifying-glass';

$resultado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buscar'])) {
    // Validar que al menos uno de los campos obligatorios esté lleno
    $rfc = trim($_POST['rfc'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $curp = trim($_POST['curp'] ?? '');

    if (empty($rfc) && empty($name) && empty($curp)) {
        $resultado = [
            'success' => false,
            'error' => 'Debe proporcionar al menos uno de los siguientes campos: RFC, Nombre o CURP'
        ];
    } else {
        // Obtener valores de configuración
        $url = getConstant('url_q-detect_find');
        $clientId = getConstant('client_id');
        $username = getConstant('username');
        $authorization = getConstant('bearer token');

        // Construir parámetros
        $params = [
            'client_id' => $clientId,
            'username' => $username
        ];

        // Agregar campos del formulario solo si tienen valor
        if (!empty($rfc)) $params['rfc'] = $rfc;
        if (!empty($name)) $params['name'] = $name;
        if (!empty($curp)) $params['curp'] = $curp;

        $percent = trim($_POST['percent'] ?? '');
        if ($percent !== '') $params['percent'] = (int)$percent;

        if (isset($_POST['include_history'])) $params['include_history'] = true;
        if (isset($_POST['related'])) $params['related'] = true;

        $type = trim($_POST['type'] ?? '');
        if ($type !== '') $params['type'] = $type;

        if (isset($_POST['companyCoincidence'])) $params['companyCoincidence'] = 1;

        $coincidenceType = trim($_POST['coincidenceType'] ?? '');
        if ($coincidenceType !== '') $params['coincidenceType'] = (int)$coincidenceType;

        $commonAlert = trim($_POST['commonAlert'] ?? '');
        if ($commonAlert !== '') $params['commonAlert'] = (int)$commonAlert;

        if (isset($_POST['omitPunct'])) $params['omitPunct'] = true;

        $category = trim($_POST['category'] ?? '');
        if ($category !== '') $params['category'] = $category;

        $sex = trim($_POST['sex'] ?? '');
        if ($sex !== '') $params['sex'] = $sex;

        $country = trim($_POST['country'] ?? '');
        if ($country !== '') $params['country'] = $country;

        $org = trim($_POST['org'] ?? '');
        if ($org !== '') $params['org'] = $org;

        $alias = trim($_POST['alias'] ?? '');
        if ($alias !== '') $params['alias'] = $alias;

        $passport = trim($_POST['passport'] ?? '');
        if ($passport !== '') $params['passport'] = $passport;

        $residence_id = trim($_POST['residence_id'] ?? '');
        if ($residence_id !== '') $params['residence_id'] = $residence_id;

        $driver_license = trim($_POST['driver_license'] ?? '');
        if ($driver_license !== '') $params['driver_license'] = $driver_license;

        $visa_number = trim($_POST['visa_number'] ?? '');
        if ($visa_number !== '') $params['visa_number'] = $visa_number;

        $id_card = trim($_POST['id_card'] ?? '');
        if ($id_card !== '') $params['id_card'] = $id_card;

        $ss_number = trim($_POST['ss_number'] ?? '');
        if ($ss_number !== '') $params['ss_number'] = $ss_number;

        $nationality = trim($_POST['nationality'] ?? '');
        if ($nationality !== '') $params['nationality'] = $nationality;

        $citizenship = trim($_POST['citizenship'] ?? '');
        if ($citizenship !== '') $params['citizenship'] = $citizenship;

        // Hacer petición
        $urlCompleta = $url . '?' . http_build_query($params);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $urlCompleta);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $authorization
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $resultado = [
                'success' => false,
                'error' => 'Error en la petición: ' . $error
            ];
        } elseif ($httpCode !== 200) {
            $resultado = [
                'success' => false,
                'error' => 'Error HTTP ' . $httpCode . ' - Respuesta: ' . $response
            ];
        } else {
            $data = json_decode($response, true);
            if (is_array($data) && isset($data['success'])) {
                $resultado = $data;
            } else {
                $resultado = [
                    'success' => false,
                    'error' => 'Respuesta inválida de la API. Respuesta recibida: ' . substr($response, 0, 500)
                ];
            }
        }
    }
}

$stmtPaises = $db->query("SELECT nom FROM t_pais ORDER BY nom");
$paises = $stmtPaises->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../../img/afiducialogo.png">
    <title><?php echo $pageTitle; ?></title>


    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
</head>

<body class="bg-gray-100">
    <div class="flex h-screen">
        <?php echo $sidebar->render($userData); ?>
        <div class="flex-1 flex flex-col overflow-hidden">
            <header class="bg-white shadow-sm border-b border-gray-200">
                <div class="flex items-center justify-between px-6 py-4">
                    <div class="flex items-center gap-4">
                        <a href="../../dashboard.php" class="text-gray-600 hover:text-gray-800 transition">
                            <i class="fas fa-arrow-left"></i>
                        </a>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                                <i class="<?php echo $pageIcon; ?> text-blue-600"></i>
                                <?php echo $pageTitle; ?>
                            </h1>
                            <p class="text-sm text-gray-600"><?php echo $pageDescription; ?></p>
                        </div>
                    </div>

                    <nav class="flex items-center text-sm text-gray-500">
                        <a href="../../dashboard.php" class="hover:text-gray-700">Dashboard</a>
                        <span class="mx-2">/</span>
                        <a href="../qsq.php" class="hover:text-gray-700">QSQ</a>
                        <span class="mx-2">/</span>
                        <span class="text-gray-700"><?php echo $pageTitle; ?></span>
                        <span class="mx-2">|</span>
                        <span class="text-gray-700">
                            <i class="fas fa-user mr-1"></i>
                            <?= htmlspecialchars($userData['name'] ?? 'Usuario') ?>
                        </span>
                    </nav>
                </div>
            </header>
            <main class="flex-1 overflow-y-auto p-6">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
                    <div class="flex flex-wrap gap-3">
                        <button type="submit" form="searchForm" name="buscar"
                            class="bg-blue-600 text-white font-medium py-2 px-6 rounded-lg hover:bg-blue-700 transition-colors duration-200">
                            <i class="fas fa-search mr-2"></i>Buscar
                        </button>
                        <button type="button" onclick="limpiarTodo()"
                            class="bg-gray-500 text-white font-medium py-2 px-6 rounded-lg hover:bg-gray-600 transition-colors duration-200">
                            <i class="fas fa-eraser mr-2"></i>Limpiar Todo
                        </button>
                    </div>
                </div>
                <div id="alertasContainer">
                    <?php if ($resultado && !$resultado['success']): ?>
                        <div class="mb-6 bg-white rounded-lg shadow-sm border border-red-200 p-5">
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-red-100 text-red-600">
                                        <i class="fas fa-times"></i>
                                    </span>
                                </div>
                                <div class="ml-4 flex-1">
                                    <h3 class="text-sm font-semibold text-red-900">Error en la operación</h3>
                                    <div class="mt-3 p-3 bg-red-50 rounded border border-red-200">
                                        <div class="flex items-center justify-between mb-2">
                                            <p class="text-xs font-medium text-red-900">Detalle:</p>
                                            <button type="button" id="btnCopiar" onclick="copiarError()"
                                                class="text-xs bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded transition-colors duration-200">
                                                <i class="fas fa-copy mr-1"></i>Copiar
                                            </button>
                                        </div>
                                        <p id="errorText" class="text-xs font-mono text-red-800 break-all">
                                            <?php echo htmlspecialchars($resultado['error'] ?? 'No se han encontrado coincidencias'); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-1">
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                <i class="fas fa-sliders text-blue-600"></i>
                                Parámetros de Búsqueda
                            </h2>

                            <form method="POST" id="searchForm">
                                <div class="mb-4 p-3 bg-blue-50 rounded border border-blue-200">
                                    <p class="text-xs font-medium text-blue-900 mb-3">
                                        <i class="fas fa-asterisk text-red-500 mr-1"></i>
                                        Al menos uno de estos campos es obligatorio:
                                    </p>

                                    <div class="mb-3">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                                        <input type="text" name="name"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                            value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">RFC</label>
                                        <input type="text" name="rfc"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm uppercase"
                                            value="<?php echo htmlspecialchars($_POST['rfc'] ?? ''); ?>">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">CURP</label>
                                        <input type="text" name="curp"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm uppercase"
                                            value="<?php echo htmlspecialchars($_POST['curp'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div x-data="{ open: false }">
                                    <button type="button" @click="open = !open"
                                        class="w-full flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200 hover:bg-gray-100 transition mb-3">
                                        <span class="text-sm font-medium text-gray-700">
                                            <i class="fas fa-cog mr-2"></i>Opciones avanzadas
                                        </span>
                                        <i class="fas fa-chevron-down transition-transform" :class="{ 'rotate-180': open }"></i>
                                    </button>

                                    <div x-show="open" x-collapse class="space-y-3">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Porcentaje</label>
                                            <input type="number" name="percent" min="0" max="100"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                                value="<?php echo htmlspecialchars($_POST['percent'] ?? ''); ?>">
                                        </div>

                                        <div class="flex items-center">
                                            <input type="checkbox" name="include_history" id="include_history"
                                                class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                                <?php echo isset($_POST['include_history']) ? 'checked' : ''; ?>>
                                            <label for="include_history" class="ml-2 text-sm text-gray-700">Incluir historial</label>
                                        </div>

                                        <div class="flex items-center">
                                            <input type="checkbox" name="related" id="related"
                                                class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                                <?php echo isset($_POST['related']) ? 'checked' : ''; ?>>
                                            <label for="related" class="ml-2 text-sm text-gray-700">Familiares relacionados</label>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Físicas / Morales</label>
                                            <select name="type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                                                <option value="">Seleccione...</option>
                                                <option value="fisica" <?php echo ($_POST['type'] ?? '') === 'fisica' ? 'selected' : ''; ?>>Física</option>
                                                <option value="moral" <?php echo ($_POST['type'] ?? '') === 'moral' ? 'selected' : ''; ?>>Moral</option>
                                            </select>
                                        </div>

                                        <div class="flex items-center">
                                            <input type="checkbox" name="companyCoincidence" id="companyCoincidence"
                                                class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                                <?php echo isset($_POST['companyCoincidence']) ? 'checked' : ''; ?>>
                                            <label for="companyCoincidence" class="ml-2 text-sm text-gray-700">Encontrar empresas</label>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Ponderación Global</label>
                                            <input type="number" name="coincidenceType"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                                value="<?php echo htmlspecialchars($_POST['coincidenceType'] ?? ''); ?>">
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Comparten apellidos</label>
                                            <input type="number" name="commonAlert"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                                value="<?php echo htmlspecialchars($_POST['commonAlert'] ?? ''); ?>">
                                        </div>

                                        <div class="flex items-center">
                                            <input type="checkbox" name="omitPunct" id="omitPunct"
                                                class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                                <?php echo isset($_POST['omitPunct']) ? 'checked' : ''; ?>>
                                            <label for="omitPunct" class="ml-2 text-sm text-gray-700">Omitir Acentos</label>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Categorías</label>
                                            <input type="text" name="category"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                                value="<?php echo htmlspecialchars($_POST['category'] ?? ''); ?>">
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Sexo</label>
                                            <select name="sex" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                                                <option value="">Seleccione...</option>
                                                <option value="M" <?php echo ($_POST['sex'] ?? '') === 'M' ? 'selected' : ''; ?>>Masculino</option>
                                                <option value="F" <?php echo ($_POST['sex'] ?? '') === 'F' ? 'selected' : ''; ?>>Femenino</option>
                                            </select>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">País</label>
                                            <select name="country" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                                                <option value="">Seleccione...</option>
                                                <?php foreach ($paises as $pais): ?>
                                                    <option value="<?php echo htmlspecialchars($pais); ?>"
                                                        <?php echo ($_POST['country'] ?? '') === $pais ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($pais); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Organización</label>
                                            <select name="org" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                                                <option value="">Seleccione...</option>
                                                <option value="REFIPRE" <?php echo ($_POST['org'] ?? '') === 'REFIPRE' ? 'selected' : ''; ?>>REFIPRE</option>
                                                <option value="OCDE" <?php echo ($_POST['org'] ?? '') === 'OCDE' ? 'selected' : ''; ?>>OCDE</option>
                                                <option value="GAFI" <?php echo ($_POST['org'] ?? '') === 'GAFI' ? 'selected' : ''; ?>>GAFI</option>
                                                <option value="UE" <?php echo ($_POST['org'] ?? '') === 'UE' ? 'selected' : ''; ?>>UE</option>
                                            </select>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Alias</label>
                                            <input type="text" name="alias"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                                value="<?php echo htmlspecialchars($_POST['alias'] ?? ''); ?>">
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Pasaporte</label>
                                            <input type="text" name="passport"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                                value="<?php echo htmlspecialchars($_POST['passport'] ?? ''); ?>">
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Residencia</label>
                                            <input type="text" name="residence_id"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                                value="<?php echo htmlspecialchars($_POST['residence_id'] ?? ''); ?>">
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Licencia de conducir</label>
                                            <input type="text" name="driver_license"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                                value="<?php echo htmlspecialchars($_POST['driver_license'] ?? ''); ?>">
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Visa</label>
                                            <input type="text" name="visa_number"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                                value="<?php echo htmlspecialchars($_POST['visa_number'] ?? ''); ?>">
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Cédula universitaria</label>
                                            <input type="text" name="id_card"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                                value="<?php echo htmlspecialchars($_POST['id_card'] ?? ''); ?>">
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Número de Seguridad Social</label>
                                            <input type="text" name="ss_number"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                                value="<?php echo htmlspecialchars($_POST['ss_number'] ?? ''); ?>">
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Nacionalidades</label>
                                            <input type="text" name="nationality"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                                value="<?php echo htmlspecialchars($_POST['nationality'] ?? ''); ?>">
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Ciudadanías</label>
                                            <input type="text" name="citizenship"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                                value="<?php echo htmlspecialchars($_POST['citizenship'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Resultados -->
                    <div class="lg:col-span-2">
                        <div class="mt-4 p-3 bg-gray-50 rounded border border-gray-200">
                            <div id="contadorFiltros" class="text-xs text-gray-600 mb-2 hidden"></div>
                            <p class="text-xs text-gray-600">
                                <span class="font-medium">ID Match:</span> <?php echo htmlspecialchars($resultado['id_match'] ?? 'N/A'); ?>
                            </p>
                            <p class="text-xs text-gray-500 mt-1">
                                <span class="font-medium"><b>Registros encontrados: </b></span> <?php echo count($resultado['data']); ?>
                            </p>
                        </div>
                        <div id="resultadosContainer" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                <i class="fas fa-table text-blue-600"></i>
                                Resultados
                            </h2>
                            <?php if ($resultado && $resultado['success'] && !empty($resultado['data'])): ?>
                                <button type="button" onclick="limpiarFiltros()"
                                    class="ml-auto bg-amber-500 text-white font-medium py-2 px-6 rounded-lg hover:bg-amber-600 transition-colors duration-200">
                                    <i class="fas fa-filter-circle-xmark mr-2"></i>Limpiar Filtros
                                </button>
                            <?php endif; ?>
                            <div id="tablaResultados">
                                <?php if ($resultado && $resultado['success'] && !empty($resultado['data'])): ?>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200" id="tablaResultadosData">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <?php
                                                    $primeraFila = $resultado['data'][0];
                                                    foreach (array_keys($primeraFila) as $encabezado):
                                                    ?>
                                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider whitespace-nowrap">
                                                            <?php echo htmlspecialchars($encabezado); ?>
                                                        </th>
                                                    <?php endforeach; ?>
                                                </tr>
                                                <tr class="bg-white border-t border-gray-200">
                                                    <?php
                                                    $columnIndex = 0;
                                                    foreach (array_keys($primeraFila) as $encabezado):
                                                    ?>
                                                        <td class="px-4 py-2">
                                                            <input type="text" placeholder="Filtrar..."
                                                                class="filtro-columna w-full px-2 py-1 text-xs border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                                                data-column="<?php echo $columnIndex; ?>"
                                                                onkeyup="filtrarTabla()">
                                                        </td>
                                                    <?php
                                                        $columnIndex++;
                                                    endforeach;
                                                    ?>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <?php foreach ($resultado['data'] as $registro): ?>
                                                    <tr class="hover:bg-gray-50 fila-resultado">
                                                        <?php foreach ($registro as $valor): ?>
                                                            <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                                                                <?php echo htmlspecialchars($valor ?? ''); ?>
                                                            </td>
                                                        <?php endforeach; ?>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>


                                <?php elseif ($resultado && $resultado['success'] && empty($resultado['data'])): ?>
                                    <div class="text-center py-12">
                                        <i class="fas fa-search text-gray-300 text-5xl mb-4"></i>
                                        <p class="text-gray-500">No se encontraron resultados para los criterios de búsqueda.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-12">
                                        <i class="fas fa-info-circle text-gray-300 text-5xl mb-4"></i>
                                        <p class="text-gray-400">Complete el formulario y presione "Buscar" para ver los resultados.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <!-- Botón volver arriba -->
    <button
        id="btnTop"
        onclick="scrollToTop()"
        class="hidden fixed bottom-6 right-6 bg-blue-600 hover:bg-blue-700 text-white p-3 rounded-full shadow-lg transition-all duration-300"
        title="Volver arriba">
        <img src="/img/arrow-up.svg" alt="Flecha arriba" class="h-5 w-5" />
    </button>

    <script>
        // Script para el sidebar
        function sidebar(initialState, userId) {
            return {
                sidebarOpen: initialState,
                userId: userId,

                init() {
                    this.sidebarOpen = this.getStoredSidebarState();
                },

                toggleSidebar() {
                    this.sidebarOpen = !this.sidebarOpen;
                    this.saveSidebarState();
                },

                saveSidebarState() {
                    const formData = new FormData();
                    formData.append('accion', 'guardar_estado_sidebar');
                    formData.append('estado', this.sidebarOpen ? 1 : 0);

                    fetch('ajax_handler.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    }).catch(error => console.error('Error guardando estado del sidebar:', error));
                },

                getStoredSidebarState() {
                    return this.sidebarOpen;
                }
            }
        }

        function limpiarTodo() {
            document.getElementById('searchForm').reset();

            const alertasContainer = document.getElementById('alertasContainer');
            if (alertasContainer) alertasContainer.innerHTML = '';

            const tablaResultados = document.getElementById('tablaResultados');
            if (tablaResultados) {
                tablaResultados.innerHTML = `
            <div class="text-center py-12">
                <i class="fas fa-info-circle text-gray-300 text-5xl mb-4"></i>
                <p class="text-gray-400">Complete el formulario y presione "Buscar" para ver los resultados.</p>
            </div>`;
            }

            document.querySelector('input[name="name"]').focus();
        }

        // Copiar error
        function copiarError() {
            const errorText = document.getElementById('errorText').innerText;
            const btn = document.getElementById('btnCopiar');

            navigator.clipboard.writeText(errorText).then(() => {
                const textoOriginal = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check mr-1"></i>Copiado';
                btn.classList.remove('bg-red-600', 'hover:bg-red-700');
                btn.classList.add('bg-green-600', 'hover:bg-green-700');

                setTimeout(() => {
                    btn.innerHTML = textoOriginal;
                    btn.classList.remove('bg-green-600', 'hover:bg-green-700');
                    btn.classList.add('bg-red-600', 'hover:bg-red-700');
                }, 2000);
            }).catch(err => {
                console.error('Error al copiar:', err);
                alert('No se pudo copiar el texto.');
            });
        }
        /*
                // Scroll to top
                window.addEventListener('scroll', () => {
                    const btn = document.getElementById('btnTop');
                    btn.classList.toggle('hidden', window.scrollY <= 200);
                });

                function scrollToTop() {
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                }
        */
        // Mostrar el botón cuando se hace scroll hacia abajo
        window.addEventListener('scroll', () => {
            const btn = document.getElementById('btnTop');
            if (window.scrollY > 200) {
                btn.classList.remove('hidden');
            } else {
                btn.classList.add('hidden');
            }
        });

        // Desplazamiento suave hacia la parte superior
        function scrollToTop() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }

        // Mostrar el botón cuando se hace scroll hacia abajo
        window.addEventListener('scroll', () => {
            const btn = document.getElementById('btnTop');
            if (window.scrollY > 200) {
                btn.classList.remove('hidden');
            } else {
                btn.classList.add('hidden');
            }
        });


        // Filtrar tabla
        function filtrarTabla() {
            const tabla = document.getElementById('tablaResultadosData');
            if (!tabla) return;

            const filas = tabla.querySelectorAll('.fila-resultado');
            const filtros = document.querySelectorAll('.filtro-columna');
            const valoresFiltros = Array.from(filtros).map(input => input.value.toLowerCase().trim());

            let filasVisibles = 0;

            filas.forEach(fila => {
                const celdas = fila.querySelectorAll('td');
                let mostrarFila = true;

                valoresFiltros.forEach((filtro, index) => {
                    if (filtro && celdas[index]) {
                        if (!celdas[index].textContent.toLowerCase().includes(filtro)) {
                            mostrarFila = false;
                        }
                    }
                });

                fila.style.display = mostrarFila ? '' : 'none';
                if (mostrarFila) filasVisibles++;
            });

            actualizarContador(filasVisibles, filas.length);
        }

        function actualizarContador(visibles, total) {
            const contadorDiv = document.getElementById('contadorFiltros');
            if (!contadorDiv) return;

            if (visibles < total) {
                contadorDiv.innerHTML = `<span class="font-medium">Mostrando:</span> ${visibles} de ${total} registros`;
                contadorDiv.classList.remove('hidden');
            } else {
                contadorDiv.classList.add('hidden');
            }
        }

        function limpiarFiltros() {
            document.querySelectorAll('.filtro-columna').forEach(input => input.value = '');
            document.querySelectorAll('.fila-resultado').forEach(fila => fila.style.display = '');

            const contadorDiv = document.getElementById('contadorFiltros');
            if (contadorDiv) contadorDiv.classList.add('hidden');
        }
    </script>
</body>

</html>