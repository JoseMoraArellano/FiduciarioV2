<?php
/**
 * Dashboard.php - Página principal del sistema
 * Requiere autenticación y muestra el sidebar dinámico
 */

// Cargar configuración y clases
require_once 'config.php';

require_once 'includes/Database.php';
require_once 'includes/Session.php';
require_once 'includes/Permissions.php';
require_once 'includes/Auth.php';

// Iniciar sesión
$session = new Session();

// Verificar autenticación
if (!$session->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Obtener datos del usuario
$userData = $session->getUserData();
$userId = $session->getUserId();
$userName = $userData['name'];
$userEmail = $userData['email'];
$isAdmin = $session->isAdmin();
$perfil = $userData['perfil'];

// Obtener nombre completo del perfil si existe
$fullName = '';
if (!empty($perfil['firstname']) || !empty($perfil['lastname'])) {
    $fullName = trim(($perfil['firstname'] ?? '') . ' ' . ($perfil['lastname'] ?? ''));
} else {
    $fullName = $userName;
}

// Ejemplo: Verificar permiso específico
$canViewClients = $session->hasPermission('clients', 'lire');
$canCreateClients = $session->hasPermission('clients', 'creer');

// Estadísticas de ejemplo (aquí conectarías con tu base de datos real)
$stats = [
    'total_users' => 0,
    'active_sessions' => 0,
    'pending_tasks' => 0,
    'system_alerts' => 0
];

// Si es admin, obtener estadísticas reales
if ($isAdmin) {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Contar usuarios activos
        $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE statut = 1");
        $result = $stmt->fetch();
        $stats['total_users'] = $result['total'];
        
        // Contar sesiones activas (tokens de remember me válidos)
        $stmt = $db->query("SELECT COUNT(DISTINCT fk_user) as total FROM t_remember_tokens WHERE expires_at > NOW()");
        $result = $stmt->fetch();
        $stats['active_sessions'] = $result['total'];
        
    } catch (PDOException $e) {
        error_log("Error loading dashboard stats: " . $e->getMessage());
    }
}

// Obtener permisos del usuario para el menú
$permissions = new Permissions();
$userPermissionsList = $permissions->getUserPermissions($userId);
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Fiduciario</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="./public/css/dashboard.css">
</head>
<body class="bg-gray-100">
    
    <div x-data="{ sidebarOpen: true }" class="flex h-screen overflow-hidden">
        
        <!-- ============================================ -->
        <!-- SIDEBAR (Placeholder - Lo crearemos después) -->
        <!-- ============================================ -->
        <aside 
            :class="sidebarOpen ? 'w-64' : 'w-20'" 
            class="bg-gray-900 text-white transition-all duration-300 flex flex-col overflow-hidden"
        >
            <!-- Header del Sidebar -->
            <div class="p-4 border-b border-gray-700 flex items-center justify-between">
                <div x-show="sidebarOpen" class="flex items-center gap-3">
                    <div class="p-2 rounded-lg">
                        <!-- <i class="fas fa-briefcase text-xl"></i>-->
                         <img src="img/Fiduciapalomas.jpg" alt="Briefcase" class="w-6 h-6">
                    </div>
                    <div>
                        <h1 class="font-bold text-sm whitespace-nowrap">Afianzadora Fiducia</h1>
                        <p class="text-xs text-gray-400">Sistema Fiduciario</p>
                    </div>
                </div>
                
                <button 
                    @click="sidebarOpen = !sidebarOpen"
                    class="p-2 hover:bg-gray-800 rounded-lg transition-colors"
                >
                    <i class="fas fa-bars text-lg"></i>
                </button>
            </div>

            <!-- Menú de navegación (Ejemplo básico) -->
            <nav class="flex-1 overflow-y-auto py-4">
                <ul class="space-y-1 px-2">
                    
                    <!-- Dashboard -->
                    <li>
                        <a href="dashboard.php" 
                           class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-gray-800 text-white transition-colors group"
                        >
                            <i class="fas fa-th-large text-lg w-5"></i>
                            <span x-show="sidebarOpen" class="whitespace-nowrap">Dashboard</span>
                        </a>
                    </li>

                    <?php if ($canViewClients): ?>
                    <!-- Clientes (ejemplo con permiso) -->
                    <li x-data="{ open: false }">
                        <button 
                            @click="open = !open"
                            class="w-full flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg hover:bg-gray-800 transition-colors group"
                        >
                            <div class="flex items-center gap-3">
                                <i class="fas fa-users text-lg w-5"></i>
                                <span x-show="sidebarOpen" class="whitespace-nowrap">Clientes</span>
                            </div>
                            <i 
                                x-show="sidebarOpen"
                                :class="open ? 'fa-chevron-down' : 'fa-chevron-right'" 
                                class="fas text-xs transition-transform"
                            ></i>
                        </button>
                        
                        <!-- Submenu -->
                        <ul 
                            x-show="open && sidebarOpen" 
                            x-collapse
                            class="mt-1 ml-8 space-y-1"
                        >
                            <li>
                                <a href="clients/index.php" 
                                   class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-800 transition-colors text-sm text-gray-300 hover:text-white"
                                >
                                    <i class="fas fa-list w-4"></i>
                                    <span>Ver Todos</span>
                                </a>
                            </li>
                            
                            <?php if ($canCreateClients): ?>
                            <li>
                                <a href="clients/create.php" 
                                   class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-800 transition-colors text-sm text-gray-300 hover:text-white"
                                >
                                    <i class="fas fa-plus w-4"></i>
                                    <span>Crear Nuevo</span>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>

                    <!-- Más opciones de menú aquí -->
                    
                </ul>
            </nav>

            <!-- Footer del Sidebar - Perfil de usuario -->
            <div class="p-4 border-t border-gray-700">
                <div class="flex items-center gap-3">
                    <?php
                    $avatarUrl = !empty($perfil['avatar']) 
                        ? $perfil['avatar'] 
                        : "https://ui-avatars.com/api/?name=" . urlencode($fullName) . "&background=3b82f6&color=fff";
                    ?>
                    <img 
                        src="<?php echo htmlspecialchars($avatarUrl); ?>" 
                        alt="<?php echo htmlspecialchars($fullName); ?>" 
                        class="w-10 h-10 rounded-full"
                    >
                    <div x-show="sidebarOpen" class="flex-1 overflow-hidden">
                        <p class="text-sm font-medium truncate"><?php echo htmlspecialchars($fullName); ?></p>
                        <p class="text-xs text-gray-400 truncate"><?php echo htmlspecialchars($userEmail); ?></p>
                    </div>
                    
                    <!-- Menú desplegable del usuario -->
                    <div x-data="{ userMenuOpen: false }" class="relative" x-show="sidebarOpen">
                        <button 
                            @click="userMenuOpen = !userMenuOpen"
                            class="p-1 hover:bg-gray-800 rounded transition-colors"
                        >
                            <i class="fas fa-ellipsis-v text-gray-400"></i>
                        </button>
                        
                        <!-- Dropdown -->
                        <div 
                            x-show="userMenuOpen"
                            @click.away="userMenuOpen = false"
                            x-transition
                            class="absolute bottom-full right-0 mb-2 w-48 bg-gray-800 rounded-lg shadow-lg py-2 z-50"
                        >
                            <a href="profile.php" class="flex items-center gap-3 px-4 py-2 hover:bg-gray-700 transition-colors">
                                <i class="fas fa-user w-4"></i>
                                <span>Mi Perfil</span>
                            </a>
                            <a href="settings.php" class="flex items-center gap-3 px-4 py-2 hover:bg-gray-700 transition-colors">
                                <i class="fas fa-cog w-4"></i>
                                <span>Configuración</span>
                            </a>
                            <hr class="my-2 border-gray-700">
                            <a href="logout.php" class="flex items-center gap-3 px-4 py-2 hover:bg-gray-700 transition-colors text-red-400">
                                <i class="fas fa-sign-out-alt w-4"></i>
                                <span>Cerrar Sesión</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- ============================================ -->
        <!-- CONTENIDO PRINCIPAL -->
        <!-- ============================================ -->
        <main class="flex-1 overflow-y-auto">
            
            <!-- Header superior -->
            <header class="bg-white shadow-sm">
                <div class="px-8 py-4 flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Dashboard</h1>
                        <p class="text-sm text-gray-600">Bienvenido de nuevo, <?php echo htmlspecialchars($fullName); ?></p>
                    </div>
                    
                    <div class="flex items-center gap-4">
                        <!-- Notificaciones -->
                        <div x-data="{ notifOpen: false }" class="relative">
                            <button 
                                @click="notifOpen = !notifOpen"
                                class="relative p-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                            >
                                <i class="fas fa-bell text-xl"></i>
                                <?php if ($stats['system_alerts'] > 0): ?>
                                <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
                                <?php endif; ?>
                            </button>
                            
                            <!-- Dropdown notificaciones -->
                            <div 
                                x-show="notifOpen"
                                @click.away="notifOpen = false"
                                x-transition
                                class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-xl py-2 z-50 border"
                            >
                                <div class="px-4 py-2 border-b">
                                    <h3 class="font-semibold text-gray-800">Notificaciones</h3>
                                </div>
                                <div class="max-h-96 overflow-y-auto">
                                    <div class="px-4 py-3 hover:bg-gray-50 cursor-pointer">
                                        <p class="text-sm text-gray-800">No hay notificaciones nuevas</p>
                                        <p class="text-xs text-gray-500 mt-1">Todo está al día</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Hora actual -->
                        <div class="text-sm text-gray-600">
                            <i class="fas fa-clock mr-2"></i>
                            <span id="currentTime"></span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Contenido del Dashboard -->
            <div class="p-8">
                
                <!-- Tarjetas de estadísticas -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    
                    <!-- Card 1 -->
                    <div class="bg-white rounded-lg shadow p-6 border-l-4 border-blue-500">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-users text-2xl text-blue-600"></i>
                            </div>
                            <span class="text-xs font-semibold text-gray-500 uppercase">Usuarios</span>
                        </div>
                        <div class="text-3xl font-bold text-gray-800 mb-1"><?php echo $stats['total_users']; ?></div>
                        <p class="text-sm text-gray-600">Usuarios activos</p>
                    </div>
                    
                    <!-- Card 2 -->
                    <div class="bg-white rounded-lg shadow p-6 border-l-4 border-green-500">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-user-check text-2xl text-green-600"></i>
                            </div>
                            <span class="text-xs font-semibold text-gray-500 uppercase">Sesiones</span>
                        </div>
                        <div class="text-3xl font-bold text-gray-800 mb-1"><?php echo $stats['active_sessions']; ?></div>
                        <p class="text-sm text-gray-600">Sesiones activas</p>
                    </div>
                    
                    <!-- Card 3 -->
                    <div class="bg-white rounded-lg shadow p-6 border-l-4 border-orange-500">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-tasks text-2xl text-orange-600"></i>
                            </div>
                            <span class="text-xs font-semibold text-gray-500 uppercase">Tareas</span>
                        </div>
                        <div class="text-3xl font-bold text-gray-800 mb-1"><?php echo $stats['pending_tasks']; ?></div>
                        <p class="text-sm text-gray-600">Tareas pendientes</p>
                    </div>
                    
                    <!-- Card 4 -->
                    <div class="bg-white rounded-lg shadow p-6 border-l-4 border-red-500">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-exclamation-triangle text-2xl text-red-600"></i>
                            </div>
                            <span class="text-xs font-semibold text-gray-500 uppercase">Alertas</span>
                        </div>
                        <div class="text-3xl font-bold text-gray-800 mb-1"><?php echo $stats['system_alerts']; ?></div>
                        <p class="text-sm text-gray-600">Alertas del sistema</p>
                    </div>
                    
                </div>

                <!-- Información del usuario -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    
                    <!-- Información de sesión -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">
                            <i class="fas fa-info-circle mr-2 text-blue-500"></i>
                            Información de Sesión
                        </h3>
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Usuario:</span>
                                <span class="font-medium text-gray-800"><?php echo htmlspecialchars($userName); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Email:</span>
                                <span class="font-medium text-gray-800"><?php echo htmlspecialchars($userEmail); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Rol:</span>
                                <span class="font-medium text-gray-800">
                                    <?php echo $isAdmin ? 'Administrador' : 'Usuario'; ?>
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Último login:</span>
                                <span class="font-medium text-gray-800">
                                    <?php 
                                    if (!empty($perfil['datelastlogin'])) {
                                        echo date('d/m/Y H:i', strtotime($perfil['datelastlogin']));
                                    } else {
                                        echo 'Primer login';
                                    }
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Permisos del usuario -->
                    <div class="bg-white rounded-lg shadow p-6 lg:col-span-2">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">
                            <i class="fas fa-shield-alt mr-2 text-green-500"></i>
                            Tus Permisos
                        </h3>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                            <?php if (!empty($userPermissionsList)): ?>
                                <?php 
                                $permisosPorModulo = [];
                                foreach ($userPermissionsList as $perm) {
                                    $permisosPorModulo[$perm['modulo']][] = $perm;
                                }
                                
                                foreach ($permisosPorModulo as $modulo => $permisos):
                                ?>
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <div class="font-semibold text-sm text-gray-800 mb-2 capitalize">
                                        <?php echo htmlspecialchars($modulo); ?>
                                    </div>
                                    <div class="space-y-1">
                                        <?php foreach ($permisos as $perm): ?>
                                        <div class="flex items-center text-xs text-gray-600">
                                            <i class="fas fa-check text-green-500 mr-2"></i>
                                            <span><?php echo htmlspecialchars($perm['permiso']); ?></span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-sm text-gray-500 col-span-full">No tienes permisos asignados</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                </div>

                <!-- Actividad reciente (ejemplo) -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b">
                        <h3 class="text-lg font-semibold text-gray-800">
                            <i class="fas fa-history mr-2 text-purple-500"></i>
                            Actividad Reciente
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <div class="flex items-start gap-4">
                                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-sign-in-alt text-blue-600"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-800">Has iniciado sesión</p>
                                    <p class="text-xs text-gray-500">Hace unos momentos</p>
                                </div>
                            </div>
                            
                            <div class="text-center py-8 text-gray-500">
                                <i class="fas fa-inbox text-4xl mb-2 opacity-50"></i>
                                <p class="text-sm">No hay más actividad reciente</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            
        </main>
        
    </div>
    
    <script src="./public/js/dashboard.js"></script>
</body>
</html>