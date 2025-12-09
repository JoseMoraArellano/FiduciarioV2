<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Permissions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Sidebar.php';

// Iniciar sesión
$session = new Session();

// Verificar autenticación
if (!$session->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$userData = $session->getUserData();
$userId = $session->getUserId();
$isAdmin = $session->isAdmin();
$userPermissions = $userData['permissions'] ?? [];
$currentFile = basename(__FILE__);
$sidebar = new Sidebar($userPermissions, $userId, $currentFile, $isAdmin);
//$sidebar = new Sidebar($userPermissions, $userId, 'dashboard.php', $isAdmin);
$menuStats = $sidebar->getMenuStats();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../../img/afiducialogo.png">
    <title>Sincronización Banxico</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        .loader {
            border: 4px solid #f3f3f3;
            border-radius: 50%;
            border-top: 4px solid #3498db;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .log-container {
            background-color: #1e1e1e;
            color: #d4d4d4;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            padding: 15px;
            border-radius: 5px;
            height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
        }

        .indicator-card {
            transition: all 0.3s;
        }

        .indicator-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        /* Scrollbar personalizado */
        .scrollbar-thin::-webkit-scrollbar {
            width: 6px;
        }

        .scrollbar-thin::-webkit-scrollbar-track {
            background: #374151;
        }

        .scrollbar-thin::-webkit-scrollbar-thumb {
            background: #6b7280;
            border-radius: 3px;
        }

        .scrollbar-thin::-webkit-scrollbar-thumb:hover {
            background: #9ca3af;
        }
    </style>
</head>

<body class="bg-gray-100">

    <!-- Layout principal con sidebar -->
    <div class="flex h-screen">
        <!-- Sidebar lateral -->
        <?php echo $sidebar->render($userData); ?>

        <!-- Contenido principal -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header superior -->
            <header class="bg-white shadow-sm border-b border-gray-200">
                <div class="flex items-center justify-between px-6 py-4">
                    <div class="flex items-center gap-4">
                        <a href="../../dashboard.php" class="text-gray-600 hover:text-gray-800 transition">
                            <i class="fas fa-arrow-left"></i>
                        </a>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                                <i class="fas fa-chart-line text-blue-600"></i>
                                Panel de Sincronización Manual a Banxico
                            </h1>
                            <p class="text-sm text-gray-600">Gestión de indicadores financieros de México</p>
                        </div>
                    </div>

                    <!-- Información de usuario en header -->
                    <nav class="flex items-center text-sm text-gray-500">
                        <a href="../../dashboard.php" class="hover:text-gray-700">Dashboard</a>
                        <span class="mx-2">/</span>
                        <a href="/catalogos.php?<?= htmlspecialchars($userData['name'] ?? 'Usuario') ?>" class="hover:text-gray-700">
                        </a>
                        <span class="text-gray-700 capitalize">
                            <a href="#">API Banxico</a>
                        </span>
                        <span class="mx-2">/</span>
                        <span class="text-gray-700 capitalize">
                            <i class="fas fa-user mr-1"></i>
                            <?= htmlspecialchars($userData['name'] ?? 'Usuario') ?>

                        </span>
                    </nav>
                </div>
            </header>

            <!-- Contenido de la aplicación -->
            <main class="flex-1 overflow-y-auto p-6" x-data="banxicoApp()">

                <!-- Alertas -->
                <div x-show="alerta.mostrar" x-transition class="mb-6">
                    <div :class="`rounded-md p-4 ${
                    alerta.tipo === 'success' ? 'bg-green-50 border border-green-200' :
                    alerta.tipo === 'danger' ? 'bg-red-50 border border-red-200' :
                    alerta.tipo === 'warning' ? 'bg-yellow-50 border border-yellow-200' :
                    'bg-blue-50 border border-blue-200'
                }`">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i :class="`${
                                alerta.tipo === 'success' ? 'fas fa-check-circle text-green-400' :
                                alerta.tipo === 'danger' ? 'fas fa-exclamation-circle text-red-400' :
                                alerta.tipo === 'warning' ? 'fas fa-exclamation-triangle text-yellow-400' :
                                'fas fa-info-circle text-blue-400'
                            }`"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium" x-text="alerta.titulo"></h3>
                                <div class="text-sm mt-1" x-text="alerta.mensaje"></div>
                            </div>
                            <div class="ml-auto pl-3">
                                <div class="-mx-1.5 -my-1.5">
                                    <button @click="alerta.mostrar = false" class="inline-flex rounded-md p-1.5 hover:bg-gray-100">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Controles principales -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Sincronización de Datos Actuales -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                        <div class="bg-blue-600 text-white px-4 py-3 rounded-t-lg">
                            <h3 class="text-lg font-semibold">
                                <i class="fas fa-sync mr-2"></i>
                                Sincronización de Datos Actuales
                            </h3>
                        </div>
                        <div class="p-4">
                            <p class="text-gray-600 mb-4">Obtiene los datos más recientes de cada indicador</p>

                            <div class="flex flex-col sm:flex-row gap-3">
                                <select class="flex-1 border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    x-model="indicadorSeleccionado">
                                    <option value="todos">Todos los indicadores</option>
                                    <option value="tiie">TIIE a 28 días</option>
                                    <option value="tipoCambio">Tipo de Cambio</option>
                                    <option value="inpc">INPC</option>
                                    <option value="cpp">CPP</option>
                                    <option value="udis">UDIS</option>
                                </select>

                                <button
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md font-medium transition duration-200 flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed"
                                    @click="sincronizarDiario()"
                                    :disabled="procesando">
                                    <i class="fas fa-download mr-2"></i>
                                    <span x-show="!procesando">Sincronizar</span>
                                    <span x-show="procesando">Procesando...</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Carga de Datos Históricos -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                        <div class="bg-yellow-500 text-gray-900 px-4 py-3 rounded-t-lg">
                            <h3 class="text-lg font-semibold">
                                <i class="fas fa-history mr-2"></i>
                                Carga de Datos Históricos
                            </h3>
                        </div>
                        <div class="p-4">
                            <p class="text-gray-600 mb-4">Carga completa de datos históricos (puede tardar hasta 30 min)</p>

                            <div class="flex flex-col sm:flex-row gap-3 mb-4">
                                <select class="flex-1 border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                                    x-model="indicadorHistorico">
                                    <option value="todos">Todos los históricos</option>
                                    <option value="tiie">TIIE (desde 1993)</option>
                                    <option value="tdc">Tipo Cambio (desde 2021)</option>
                                    <option value="inpc">INPC (desde 1969)</option>
                                    <option value="cpp">CPP (desde 1975)</option>
                                    <option value="udis">UDIS (desde 1995)</option>
                                </select>

                                <button
                                    class="bg-yellow-500 hover:bg-yellow-600 text-gray-900 px-4 py-2 rounded-md font-medium transition duration-200 flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed"
                                    @click="cargarHistorico()"
                                    :disabled="procesando">
                                    <i class="fas fa-database mr-2"></i>
                                    <span x-show="!procesando">Cargar</span>
                                    <span x-show="procesando">Cargando...</span>
                                </button>
                            </div>

                            <!-- Opciones avanzadas -->
                            <div>
                                <button @click="mostrarAvanzadas = !mostrarAvanzadas" class="flex items-center text-sm text-gray-600 hover:text-gray-800">
                                    <i :class="`fas fa-chevron-${mostrarAvanzadas ? 'up' : 'down'} mr-1`"></i>
                                    Opciones avanzadas
                                </button>

                                <div x-show="mostrarAvanzadas" x-transition class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Fecha inicio:</label>
                                        <input type="date" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                                            x-model="fechaInicio">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Fecha fin:</label>
                                        <input type="date" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
                                            x-model="fechaFin">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Estadísticas -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
                    <div class="bg-blue-500 text-white px-4 py-3 rounded-t-lg flex justify-between items-center">
                        <h3 class="text-lg font-semibold">
                            <i class="fas fa-chart-bar mr-2"></i>
                            Estadísticas de Indicadores
                        </h3>
                        <button class="bg-white text-blue-600 hover:bg-gray-100 px-3 py-1 rounded-md text-sm font-medium transition duration-200"
                            @click="obtenerEstadisticas()">
                            <i class="fas fa-refresh mr-1"></i> Actualizar
                        </button>
                    </div>
                    <div class="p-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
                            <template x-for="(stats, key) in estadisticas" :key="key">
                                <div class="bg-gray-50 rounded-lg border border-gray-200 p-4 text-center transition duration-200 hover:shadow-md">
                                    <h4 class="font-semibold text-blue-600 mb-3" x-text="stats.nombre"></h4>
                                    <div class="space-y-2">
                                        <div>
                                            <p class="text-xs text-gray-500">Total registros:</p>
                                            <p class="text-lg font-bold text-gray-800" x-text="stats.total_registros || '0'"></p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500">Último valor:</p>
                                            <p class="text-md font-semibold text-gray-800" x-text="stats.ultimo_dato ? stats.ultimo_dato.toFixed(4) : 'N/A'"></p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500">Fecha:</p>
                                            <!-- <p class="text-sm text-gray-600" x-text="stats.fecha_ultimo || 'N/A'"></p>-->
                                            <p class="text-sm text-gray-600" x-text="formatearFecha(stats.fecha_ultimo)"></p>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div x-show="Object.keys(estadisticas).length === 0" class="text-center py-8">
                            <p class="text-gray-500">No hay estadísticas disponibles. Haz clic en "Actualizar" para cargarlas.</p>
                        </div>
                    </div>
                </div>

                <?php if ($isAdmin): ?>
                    <!-- Visor de Logs -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                        <div class="bg-gray-800 text-white px-4 py-3 rounded-t-lg flex justify-between items-center">
                            <h3 class="text-lg font-semibold">
                                <i class="fas fa-terminal mr-2"></i>
                                Registro de Actividad (Log)
                            </h3>
                            <div class="flex space-x-2">
                                <button class="bg-white text-gray-800 hover:bg-gray-100 px-3 py-1 rounded-md text-sm font-medium transition duration-200"
                                    @click="verLog()">
                                    <i class="fas fa-eye mr-1"></i> Ver Log
                                </button>
                                <button class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded-md text-sm font-medium transition duration-200"
                                    @click="limpiarLog()">
                                    <i class="fas fa-trash mr-1"></i> Limpiar
                                </button>
                            </div>
                        </div>
                        <div class="p-0">
                            <div class="log-container" x-html="logContent"></div>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script>
        function banxicoApp() {
            return {
                procesando: false,
                mensajeProcesando: 'Procesando...',
                indicadorSeleccionado: 'todos',
                indicadorHistorico: 'todos',
                mostrarAvanzadas: false,
                fechaInicio: '',
                fechaFin: '',
                estadisticas: {},
                logContent: 'Log vacío. Ejecuta una acción para ver el registro.',
                alerta: {
                    mostrar: false,
                    tipo: 'info',
                    titulo: '',
                    mensaje: ''
                },

                init() {
                    this.obtenerEstadisticas();
                },

                mostrarAlerta(tipo, titulo, mensaje) {
                    this.alerta = {
                        mostrar: true,
                        tipo: tipo,
                        titulo: titulo,
                        mensaje: mensaje
                    };

                    setTimeout(() => {
                        this.alerta.mostrar = false;
                    }, 5000);
                },

                async sincronizarDiario() {
                    this.procesando = true;
                    this.mensajeProcesando = 'Sincronizando datos actuales...';

                    try {
                        const formData = new FormData();
                        formData.append('accion', 'sincronizar_diario');
                        formData.append('indicador', this.indicadorSeleccionado);

                        const response = await fetch('ajax_handler.php', {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: formData
                        });

                        const data = await response.json();

                        if (data.success) {
                            this.mostrarAlerta('success', '¡Éxito!', data.mensaje);
                            this.obtenerEstadisticas();
                            this.verLog();
                        } else {
                            this.mostrarAlerta('danger', 'Error', data.mensaje);
                        }

                    } catch (error) {
                        this.mostrarAlerta('danger', 'Error', 'Error de conexión: ' + error.message);
                    } finally {
                        this.procesando = false;
                    }
                },

                async cargarHistorico() {
                    if (!confirm('La carga histórica puede tardar hasta 30 minutos. ¿Deseas continuar?')) {
                        return;
                    }

                    this.procesando = true;
                    this.mensajeProcesando = 'Cargando datos históricos... Esto puede tardar varios minutos.';

                    try {
                        const formData = new FormData();
                        formData.append('accion', 'sincronizar_historico');
                        formData.append('indicador', this.indicadorHistorico);

                        if (this.fechaInicio) {
                            formData.append('fecha_inicio', this.fechaInicio);
                        }
                        if (this.fechaFin) {
                            formData.append('fecha_fin', this.fechaFin);
                        }

                        const response = await fetch('ajax_handler.php', {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: formData
                        });

                        const data = await response.json();

                        if (data.success) {
                            this.mostrarAlerta('success', '¡Carga Completa!', data.mensaje);
                            this.obtenerEstadisticas();
                            this.verLog();
                        } else {
                            this.mostrarAlerta('danger', 'Error', data.mensaje);
                        }

                    } catch (error) {
                        this.mostrarAlerta('danger', 'Error', 'Error de conexión: ' + error.message);
                    } finally {
                        this.procesando = false;
                    }
                },

                async obtenerEstadisticas() {
                    try {
                        const formData = new FormData();
                        formData.append('accion', 'obtener_estadisticas');

                        const response = await fetch('ajax_handler.php', {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: formData
                        });

                        const data = await response.json();

                        if (data.success) {
                            this.estadisticas = data.data;
                        }

                    } catch (error) {
                        console.error('Error obteniendo estadísticas:', error);
                    }
                },

                async verLog() {
                    try {
                        const formData = new FormData();
                        formData.append('accion', 'ver_log');

                        const response = await fetch('ajax_handler.php', {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: formData
                        });

                        const data = await response.json();

                        if (data.success && data.data.log) {
                            // Escapar HTML y preservar saltos de línea
                            this.logContent = this.escapeHtml(data.data.log);
                        }

                    } catch (error) {
                        console.error('Error obteniendo log:', error);
                    }
                },

                async limpiarLog() {
                    if (!confirm('¿Estás seguro de que deseas limpiar el log?')) {
                        return;
                    }

                    try {
                        const formData = new FormData();
                        formData.append('accion', 'limpiar_log');

                        const response = await fetch('ajax_handler.php', {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: formData
                        });

                        const data = await response.json();

                        if (data.success) {
                            this.logContent = 'Log limpiado.';
                            this.mostrarAlerta('info', 'Log limpiado', 'El archivo de log ha sido limpiado correctamente.');
                        }

                    } catch (error) {
                        console.error('Error limpiando log:', error);
                    }
                },

                formatearFecha(fecha) {
                    if (!fecha) return 'N/A';
                    const fechaParte = fecha.split(' ')[0]; 
                    const [anio, mes, dia] = fechaParte.split('-');
                    return `${dia}-${mes}-${anio}`;
                },

                escapeHtml(text) {
                    const map = {
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#039;'
                    };

                    return text.replace(/[&<>"']/g, m => map[m]);
                }
            }
        }

        // Script para el sidebar (compatible con el código existente)
        function sidebar(initialState, userId) {
            return {
                sidebarOpen: initialState,
                userId: userId,

                init() {
                    // Cargar estado inicial del sidebar
                    this.sidebarOpen = this.getStoredSidebarState();
                },

                toggleSidebar() {
                    this.sidebarOpen = !this.sidebarOpen;
                    this.saveSidebarState();
                },

                saveSidebarState() {
                    // Guardar estado usando fetch
                    const formData = new FormData();
                    formData.append('accion', 'guardar_estado_sidebar');
                    formData.append('estado', this.sidebarOpen ? 1 : 0);

                    fetch('ajax_handler.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    }).catch(error => {
                        console.error('Error guardando estado del sidebar:', error);
                    });
                },

                getStoredSidebarState() {
                    return this.sidebarOpen;
                }
            }
        }
    </script>

</body>

</html>