<?php
require_once '../config.php';
require_once 'TokenManager.php';
date_default_timezone_set('America/Mexico_City');
// Procesar solicitud de actualización
$resultado = null;
$tokenActual = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_token'])) {
    $resultado = TokenManager::renovarToken();
}

// Obtener token actual de la BD
try {
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT val, fechaedit FROM t_const WHERE id = 5");
    $stmt->execute();
    $tokenData = $stmt->fetch();
    $tokenActual = $tokenData['val'] ?? 'No encontrado';
    $fechaEdit = $tokenData['fechaedit'] ?? null;
} catch (Exception $e) {
    $tokenActual = 'Error al obtener: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actualizar Token - API</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .spinner {
            border: 3px solid #e5e7eb;
            border-top: 3px solid #3b82f6;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            animation: spin 0.8s linear infinite;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen py-8 px-4">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h1 class="text-2xl font-semibold text-gray-900 mb-1">Gestión de Token API</h1>
            <p class="text-sm text-gray-600">Actualiza el token de autorización para la API de Q-Detect</p>
        </div>
        
        <!-- Alert Messages -->
        <?php if ($resultado): ?>
            <div class="mb-6 bg-white rounded-lg shadow-sm border <?php echo $resultado['success'] ? 'border-green-200' : 'border-red-200'; ?> p-5">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full <?php echo $resultado['success'] ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'; ?>">
                            <?php echo $resultado['success'] ? '✓' : '✗'; ?>
                        </span>
                    </div>
                    <div class="ml-4 flex-1">
                        <h3 class="text-sm font-semibold <?php echo $resultado['success'] ? 'text-green-900' : 'text-red-900'; ?>">
                            <?php echo $resultado['success'] ? 'Operación exitosa' : 'Error en la operación'; ?>
                        </h3>
                        
                        <?php if (!$resultado['success']): ?>
                            <p class="text-sm text-red-700 mt-1">
                                Ocurrió un error. Copie el siguiente error y envíelo al departamento de sistemas a 
                                <span class="text-blue-600 hover:text-blue-800 font-medium">info@fianzasfiducia.com</span>
                            </p>
                            <div class="mt-3 p-3 bg-red-50 rounded border border-red-200">
                                <div class="flex items-center justify-between mb-2">
                                    <p class="text-xs font-medium text-red-900">Detalle del error:</p>
                                    <button 
                                        type="button" 
                                        id="btnCopiar"
                                        onclick="copiarError()" 
                                        class="text-xs bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded transition-colors duration-200"
                                    >
                                        Copiar
                                    </button>
                                </div>
                                <p id="errorText" class="text-xs font-mono text-red-800 break-all">
                                    <?php echo htmlspecialchars($resultado['error']); ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <p class="text-sm text-green-700 mt-1">
                                <?php echo $resultado['mensaje']; ?>
                            </p>
                        <?php endif; ?>
                        
                        <?php if ($resultado['success'] && isset($resultado['token'])): ?>
                            <div class="mt-3 p-3 bg-gray-50 rounded border border-gray-200">
                                <p class="text-xs font-medium text-gray-700 mb-1">Nuevo token generado</p>
                                <p class="text-xs font-mono text-gray-600 break-all">
                                    
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Token Actual -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Token Actual</h2>
            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                <p class="font-mono text-xs text-gray-700 break-all max-h-40 overflow-y-auto leading-relaxed">
                    <?php echo htmlspecialchars($tokenActual); ?>
                </p>
            </div>
            <?php if ($fechaEdit): ?>
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <p class="text-xs text-gray-600">
                        
                        <span class="font-medium text-gray-700">Última actualización:</span> 
                        <?php echo date('Y/m/d H:i:s', strtotime($fechaEdit)); ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Form -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <form method="POST" id="tokenForm">
                <div class="flex space-x-4">
                <button 
                    type="submit" 
                    name="actualizar_token" 
                    class="w-full bg-blue-600 text-white font-medium py-3 px-6 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors duration-200">
                    Renovar Token
                </button>
                <div>
                <button type="button" onclick="window.close()" class="bg-gray-400 text-white font-medium py-2 px-6 rounded-lg hover:bg-gray-500 transition-colors duration-200">
                    Cerrar
                </button>
            </div>
            </form>
        
            <!-- 
            <div id="loading" class="hidden mt-6 text-center py-4">
                <div class="spinner mx-auto"></div>
                <p class="mt-3 text-sm text-gray-600">Procesando solicitud...</p>
            </div>
        </div>
            Loading State -->
        <!-- Footer Info -->
        <div class="mt-6 text-center">
            <p class="text-xs text-gray-500">Sistema de gestión de tokens API v1.0</p>
        </div>
    </div>
    
    <script>
        document.getElementById('tokenForm').addEventListener('submit', function() {
            document.getElementById('loading').classList.remove('hidden');
        });
        
        function copiarError() {
            const errorText = document.getElementById('errorText').innerText;
            const btn = document.getElementById('btnCopiar');
            
            navigator.clipboard.writeText(errorText).then(function() {
                // Guardar estado original
                const textoOriginal = btn.textContent;
                
                // Cambiar a estado "copiado"
                btn.textContent = 'Copiado';
                btn.classList.remove('bg-red-600', 'hover:bg-red-700');
                btn.classList.add('bg-green-600', 'hover:bg-green-700');
                
                // Restaurar después de 2 segundos
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
    </script>
</body>
</html>