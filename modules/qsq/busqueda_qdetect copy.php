<?php
require_once $_SERVER['DOCUMENT_ROOT'].'/config.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/Session.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/Permissions.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/includes/Sidebar.php';

// Procesar búsqueda
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
        
        // Guardar info de debug
        $debugInfo = [
            'url_completa' => $urlCompleta,
            'params_enviados' => $params,
            'authorization' => substr($authorization, 0, 200) . '...' // Solo mostrar inicio
        ];
        
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
        
        // Agregar respuesta al debug
        $debugInfo['http_code'] = $httpCode;
        $debugInfo['response_raw'] = substr($response, 0, 1000); // Primeros 1000 caracteres
        
        if ($error) {
            $resultado = [
                'success' => false, 
                'error' => 'Error en la petición: ' . $error,
                'debug' => $debugInfo
            ];
        } elseif ($httpCode !== 200) {
            $resultado = [
                'success' => false, 
                'error' => 'Error HTTP ' . $httpCode . ' - Respuesta: ' . $response,
                'debug' => $debugInfo
            ];
        } else {
            $data = json_decode($response, true);
            if (is_array($data) && isset($data['success'])) {
                $resultado = $data;
                $resultado['debug'] = $debugInfo;
            } else {
                $resultado = [
                    'success' => false, 
                    'error' => 'Respuesta inválida de la API. Respuesta recibida: ' . substr($response, 0, 500),
                    'debug' => $debugInfo
                ];
            }
        }
    }
}

// Obtener países para el select
$pdo = getConnection();
$stmtPaises = $pdo->query("SELECT nom FROM t_pais ORDER BY nom");
$paises = $stmtPaises->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Búsqueda Q-Detect</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen py-8 px-4">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h1 class="text-2xl font-semibold text-gray-900 mb-1">Búsqueda en Q-Detect</h1>
            <p class="text-sm text-gray-600 mb-4">Consulta de información en la base de datos</p>
            
            <!-- Botones de acción -->
            <div class="flex gap-3">
                <button type="submit" form="searchForm" name="buscar" class="bg-blue-600 text-white font-medium py-2 px-6 rounded-lg hover:bg-blue-700 transition-colors duration-200">
                    Buscar
                </button>
                <button type="button" onclick="limpiarTodo()" class="bg-gray-500 text-white font-medium py-2 px-6 rounded-lg hover:bg-gray-600 transition-colors duration-200">
                    Limpiar
                </button>
                <button type="button" onclick="limpiarFiltros()" class="bg-amber-500 text-white font-medium py-2 px-6 rounded-lg hover:bg-amber-600 transition-colors duration-200">
                    Limpiar Filtros
                </button>
                <button type="button" onclick="window.close()" class="bg-gray-400 text-white font-medium py-2 px-6 rounded-lg hover:bg-gray-500 transition-colors duration-200">
                    Cerrar
                </button>
            </div>
        </div>
        
        <!-- Alertas -->
        <div id="alertasContainer">
        <?php if ($resultado): ?>
            <?php if (!$resultado['success']): ?>
                <div class="mb-6 bg-white rounded-lg shadow-sm border border-red-200 p-5">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-red-100 text-red-600">✗</span>
                        </div>
                        <div class="ml-4 flex-1">
                            <h3 class="text-sm font-semibold text-red-900">Error en la operación</h3>
<!--                            <p class="text-sm text-red-700 mt-1">
                                Ocurrió un error. Copie el siguiente error y envíelo al departamento de sistemas a 
                                <a href="mailto:info@fianzasfiducia.com?subject=Error%20en%20B%C3%BAsqueda%20Q-Detect&body=<?php echo rawurlencode('Se produjo el siguiente error:\n\n' . ($resultado['error'] ?? 'No se han encontrado coincidencias')); ?>" class="text-blue-600 hover:text-blue-800 underline font-medium">
                                    info@fianzasfiducia.com
                                </a>
                            </p>-->
                            <div class="mt-3 p-3 bg-red-50 rounded border border-red-200">
                                <div class="flex items-center justify-between mb-2">
                                    <p class="text-xs font-medium text-red-900">Detalle:</p>
                                    <button type="button" id="btnCopiar" onclick="copiarError()" class="text-xs bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded transition-colors duration-200">
                                        Copiar
                                    </button>
                                </div>
                                <p id="errorText" class="text-xs font-mono text-red-800 break-all">
                                    <?php echo htmlspecialchars($resultado['error'] ?? 'No se han encontrado coincidencias'); ?>
                                </p>
                            </div>
                            
                            <!-- Información de Debug -->

                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Formulario -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Parámetros de Búsqueda</h2>
                    
                    <form method="POST" id="searchForm">
                        <!-- Campos obligatorios -->
                        <div class="mb-4 p-3 bg-blue-50 rounded border border-blue-200">
                            <p class="text-xs font-medium text-blue-900 mb-3">* Al menos uno de estos campos es obligatorio:</p>

                            <div class="mb-3">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                                <input type="text" name="name" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                            </div>

                            <div class="mb-3">
                                <label class="block text-sm font-medium text-gray-700 mb-1">RFC</label>
                                <input type="text" name="rfc" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                            </div>
                            

                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">CURP</label>
                                <input type="text" name="curp" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                            </div>
                        </div>
                        
                        <!-- Campos opcionales -->
                        <div class="space-y-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Porcentaje</label>
                                <input type="number" name="percent" min="0" max="100" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                            </div>
                            
                            <div class="flex items-center">
                                <input type="checkbox" name="include_history" id="include_history" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                <label for="include_history" class="ml-2 text-sm text-gray-700">Incluir historial</label>
                            </div>
                            
                            <div class="flex items-center">
                                <input type="checkbox" name="related" id="related" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                <label for="related" class="ml-2 text-sm text-gray-700">Familiares relacionados</label>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Físicas / Morales</label>
                                <select name="type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                                    <option value="">Seleccione...</option>
                                    <option value="fisica">Física</option>
                                    <option value="moral">Moral</option>
                                </select>
                            </div>
                            
                            <div class="flex items-center">
                                <input type="checkbox" name="companyCoincidence" id="companyCoincidence" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                <label for="companyCoincidence" class="ml-2 text-sm text-gray-700">Encontrar empresas</label>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Ponderación Global</label>
                                <input type="number" name="coincidenceType" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Comparten apellidos</label>
                                <input type="number" name="commonAlert" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                            </div>
                            
                            <div class="flex items-center">
                                <input type="checkbox" name="omitPunct" id="omitPunct" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                <label for="omitPunct" class="ml-2 text-sm text-gray-700">Omitir Acentos</label>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Categorías</label>
                                <input type="text" name="category" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Sexo</label>
                                <select name="sex" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                                    <option value="">Seleccione...</option>
                                    <option value="M">M</option>
                                    <option value="F">F</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">País</label>
                                <select name="country" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                                    <option value="">Seleccione...</option>
                                    <?php foreach ($paises as $pais): ?>
                                        <option value="<?php echo htmlspecialchars($pais); ?>"><?php echo htmlspecialchars($pais); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Organización</label>
                                <select name="org" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                                    <option value="">Seleccione...</option>
                                    <option value="REFIPRE">REFIPRE</option>
                                    <option value="OCDE">OCDE</option>
                                    <option value="GAFI">GAFI</option>
                                    <option value="UE">UE</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Alias</label>
                                <input type="text" name="alias" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Pasaporte</label>
                                <input type="text" name="passport" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Residencia</label>
                                <input type="text" name="residence_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Licencia de conducir</label>
                                <input type="text" name="driver_license" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Visa</label>
                                <input type="text" name="visa_number" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Cédula universitaria</label>
                                <input type="text" name="id_card" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Número de Seguridad Social</label>
                                <input type="text" name="ss_number" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nacionalidades</label>
                                <input type="text" name="nationality" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Ciudadanías</label>
                                <input type="text" name="citizenship" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                            </div>
                        </div>
                    </form>
                </div>
                
            </div>
            
            <!-- Resultados -->
            <div class="lg:col-span-2">
                <div id="resultadosContainer" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Resultados</h2>
                    
                    <div id="tablaResultados">
                    <?php if ($resultado && $resultado['success'] && !empty($resultado['data'])): ?>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200" id="tablaResultadosData">
            <thead class="bg-gray-50">
                <!-- Fila de encabezados -->
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
                
                <!-- Fila de filtros -->
                <tr class="bg-white border-t border-gray-200">
                    <?php 
                    $columnIndex = 0;
                    foreach (array_keys($primeraFila) as $encabezado): 
                    ?>
                        <td class="px-4 py-2">
                            <input 
                                type="text" 
                                placeholder="Filtrar..." 
                                class="filtro-columna w-full px-2 py-1 text-xs border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                data-column="<?php echo $columnIndex; ?>"
                                onkeyup="filtrarTabla()"
                            >
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
    
    <div class="mt-4 p-3 bg-gray-50 rounded border border-gray-200">
        <div id="contadorFiltros" class="text-xs text-gray-600 mb-2 hidden">
            <!-- Se llenará dinámicamente -->
        </div>
        <p class="text-xs text-gray-600">
            <span class="font-medium">ID Match:</span> <?php echo htmlspecialchars($resultado['id_match'] ?? 'N/A'); ?>
        </p>
        <p class="text-xs text-gray-500 mt-1">
            <span class="font-medium">Registros encontrados:</span> <?php echo count($resultado['data']); ?>
        </p>
    </div>
<?php elseif ($resultado && $resultado['success'] && empty($resultado['data'])): ?>
    <div class="text-center py-12">
        <p class="text-gray-500">No se encontraron resultados para los criterios de búsqueda.</p>
    </div>
<?php else: ?>
    <div class="text-center py-12">
        <p class="text-gray-400">Complete el formulario y presione "Buscar" para ver los resultados.</p>
    </div>
<?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function limpiarTodo() {
            // Limpiar el formulario
            document.getElementById('searchForm').reset();
            
            // Limpiar alertas de error
            const alertasContainer = document.getElementById('alertasContainer');
            if (alertasContainer) {
                alertasContainer.innerHTML = '';
            }
            
            // Limpiar tabla de resultados
            const tablaResultados = document.getElementById('tablaResultados');
            if (tablaResultados) {
                tablaResultados.innerHTML = '<div class="text-center py-12"><p class="text-gray-400">Complete el formulario y presione "Buscar" para ver los resultados.</p></div>';
            }
            
            // Poner el foco en el campo nombre
            document.querySelector('input[name="name"]').focus();
        }
        
        function copiarError() {
            const errorText = document.getElementById('errorText').innerText;
            const btn = document.getElementById('btnCopiar');
            
            navigator.clipboard.writeText(errorText).then(function() {
                const textoOriginal = btn.textContent;
                btn.textContent = 'Copiado';
                btn.classList.remove('bg-red-600', 'hover:bg-red-700');
                btn.classList.add('bg-green-600', 'hover:bg-green-700');
                
                setTimeout(function() {
                    btn.textContent = textoOriginal;
                    btn.classList.remove('bg-green-600', 'hover:bg-green-700');
                    btn.classList.add('bg-red-600', 'hover:bg-red-700');
                }, 2000);
            }).catch(function(err) {
                console.error('Error al copiar:', err);
                alert('No se pudo copiar el texto. Por favor, cópielo manualmente.');
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

            // Desplazamiento suave hacia la parte superior
            function scrollToTop() {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
            
// Función para filtrar la tabla
        function filtrarTabla() {
            const tabla = document.getElementById('tablaResultadosData');
            if (!tabla) return;
            
            const tbody = tabla.querySelector('tbody');
            const filas = tbody.querySelectorAll('.fila-resultado');
            const filtros = document.querySelectorAll('.filtro-columna');
            
            // Obtener valores de todos los filtros
            const valoresFiltros = Array.from(filtros).map(input => 
                input.value.toLowerCase().trim()
            );
            
            let filasVisibles = 0;
            
            // Filtrar cada fila
            filas.forEach(fila => {
                const celdas = fila.querySelectorAll('td');
                let mostrarFila = true;
                
                // Verificar si la fila cumple con todos los filtros
                valoresFiltros.forEach((filtro, index) => {
                    if (filtro && celdas[index]) {
                        const textoCelda = celdas[index].textContent.toLowerCase();
                        if (!textoCelda.includes(filtro)) {
                            mostrarFila = false;
                        }
                    }
                });
                
                // Mostrar u ocultar la fila
                if (mostrarFila) {
                    fila.style.display = '';
                    filasVisibles++;
                } else {
                    fila.style.display = 'none';
                }
            });
            
            // Actualizar contador
            actualizarContador(filasVisibles, filas.length);
        }

// Función para actualizar el contador
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

// Función para limpiar filtros
function limpiarFiltros() {
    const filtros = document.querySelectorAll('.filtro-columna');
    filtros.forEach(input => {
        input.value = '';
    });
    
    const filas = document.querySelectorAll('.fila-resultado');
    filas.forEach(fila => {
        fila.style.display = '';
    });
    
    const contadorDiv = document.getElementById('contadorFiltros');
    if (contadorDiv) {
        contadorDiv.classList.add('hidden');
    }
}

// Modificar la función limpiarTodo para incluir filtros
function limpiarTodo() {
    // Limpiar el formulario
    document.getElementById('searchForm').reset();
    
    // Limpiar alertas de error
    const alertasContainer = document.getElementById('alertasContainer');
    if (alertasContainer) {
        alertasContainer.innerHTML = '';
    }
    
    // Limpiar tabla de resultados
    const tablaResultados = document.getElementById('tablaResultados');
    if (tablaResultados) {
        tablaResultados.innerHTML = '<div class="text-center py-12"><p class="text-gray-400">Complete el formulario y presione "Buscar" para ver los resultados.</p></div>';
    }
    
    // Poner el foco en el campo nombre
    document.querySelector('input[name="name"]').focus();
}
    </script>
</body>
<!-- Botón para volver arriba -->
<button 
  id="btnTop" 
  onclick="scrollToTop()" 
  class="hidden fixed bottom-6 right-6 bg-blue-600 hover:bg-blue-700 text-white p-3 rounded-full shadow-lg transition-all duration-300"
  title="Volver arriba">
  <img src="/img/arrow-up.svg" alt="Flecha arriba" class="h-5 w-5" />
</button>

        <div class="mt-6 text-center">
            <p class="text-xs text-gray-500">Sistema busqueda por API v1.0</p>
        </div>

</html>