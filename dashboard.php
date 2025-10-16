<?php
/**
 * Dashboard.php - Página principal del sistema
 * Actualizado para usar Sidebar dinámico desde base de datos
 */

// Cargar configuración y clases
require_once 'config.php';
require_once 'includes/Database.php';
require_once 'includes/Session.php';
require_once 'includes/Permissions.php';
require_once 'includes/Auth.php';
require_once 'includes/Sidebar.php'; // ← NUEVA CLASE

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

// Obtener permisos del usuario (ya cargados en sesión)
$userPermissions = $userData['permissions'] ?? [];

// Estadísticas de ejemplo
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
        
        // Contar sesiones activas
        $stmt = $db->query("SELECT COUNT(DISTINCT fk_user) as total FROM t_remember_tokens WHERE expires_at > NOW()");
        $result = $stmt->fetch();
        $stats['active_sessions'] = $result['total'];
        
    } catch (PDOException $e) {
        error_log("Error loading dashboard stats: " . $e->getMessage());
    }
}

// Crear instancia del Sidebar
// $sidebar = new Sidebar($userPermissions, $userId, 'dashboard.php');
// Crear instancia del Sidebar (PASAR PARÁMETRO $isAdmin)
$sidebar = new Sidebar($userPermissions, $userId, 'dashboard.php', $isAdmin);

// Obtener estadísticas del menú (opcional, para mostrar info)
$menuStats = $sidebar->getMenuStats();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Business Manager</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS personalizados -->
    <link rel="stylesheet" href="./public/css/dashboard.css">
    <link rel="stylesheet" href="./public/css/sidebar.css">
</head>
<body class="bg-gray-100">
    
    <div class="flex h-screen overflow-hidden">
        
        <!-- ============================================ -->
        <!-- SIDEBAR DINÁMICO DESDE BD -->
        <!-- ============================================ -->
        <?php echo $sidebar->render($userData); ?>

        <!-- ============================================ -->
        <!-- CONTENIDO PRINCIPAL -->
        <!-- ============================================ -->
        <main class="flex-1 overflow-y-auto">
            
            <!-- Header superior -->
            <header class="bg-white shadow-sm sticky top-0 z-30">
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
                                title="Notificaciones"
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
                        <div class="text-sm text-gray-600 hidden md:flex items-center">
                            <i class="fas fa-clock mr-2"></i>
                            <span id="currentTime"></span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Contenido del Dashboard -->
            <div class="p-8">
                
                <!-- Alert informativo (mostrar solo primera vez) -->
                <div class="mb-6 bg-blue-50 border-l-4 border-blue-500 p-4 rounded-lg">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-blue-500 mt-1 mr-3"></i>
                        <div class="flex-1">
                         <!--   <h3 class="text-sm font-semibold text-blue-800 mb-1">Sistema de Menú Dinámico Activado</h3> -->
                            <p class="text-sm text-blue-700">
                                Tienes acceso a <strong><?php echo $menuStats['main_items']; ?> módulos principales</strong> 
                                y <strong><?php echo $menuStats['sub_items']; ?> submenús</strong>.
                            </p>
                        </div>
                        <button 
                            onclick="this.parentElement.parentElement.remove()" 
                            class="text-blue-500 hover:text-blue-700 transition"
                        >
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Tarjetas de estadísticas -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    
                    <!-- Card 1 -->
                    <div class="bg-white rounded-lg shadow p-6 border-l-4 border-blue-500 hover:shadow-lg transition-shadow">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-users text-2xl text-blue-600"></i>
                            </div>
                            <span class="text-xs font-semibold text-gray-500 uppercase">Usuarios</span>
                        </div>
                        <div class="text-3xl font-bold text-gray-800 mb-1" data-stat="total_users"><?php echo $stats['total_users']; ?></div>
                        <p class="text-sm text-gray-600">Usuarios activos</p>
                    </div>
                    
                    <!-- Card 2 -->
                    <div class="bg-white rounded-lg shadow p-6 border-l-4 border-green-500 hover:shadow-lg transition-shadow">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-user-check text-2xl text-green-600"></i>
                            </div>
                            <span class="text-xs font-semibold text-gray-500 uppercase">Sesiones</span>
                        </div>
                        <div class="text-3xl font-bold text-gray-800 mb-1" data-stat="active_sessions"><?php echo $stats['active_sessions']; ?></div>
                        <p class="text-sm text-gray-600">Sesiones activas</p>
                    </div>
                    
                    <!-- Card 3 -->
                    <div class="bg-white rounded-lg shadow p-6 border-l-4 border-orange-500 hover:shadow-lg transition-shadow">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-list-check text-2xl text-orange-600"></i>
                            </div>
                            <span class="text-xs font-semibold text-gray-500 uppercase">Módulos</span>
                        </div>
                        <div class="text-3xl font-bold text-gray-800 mb-1"><?php echo $menuStats['main_items']; ?></div>
                        <p class="text-sm text-gray-600">Módulos disponibles</p>
                    </div>
                    
                    <!-- Card 4 -->
                    <div class="bg-white rounded-lg shadow p-6 border-l-4 border-red-500 hover:shadow-lg transition-shadow">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-exclamation-triangle text-2xl text-red-600"></i>
                            </div>
                            <span class="text-xs font-semibold text-gray-500 uppercase">Alertas</span>
                        </div>
                        <div class="text-3xl font-bold text-gray-800 mb-1" data-stat="system_alerts"><?php echo $stats['system_alerts']; ?></div>
                        <p class="text-sm text-gray-600">Alertas del sistema</p>
                    </div>
                    
                </div>

                <!-- Información del usuario -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    
                    <!-- Información de sesión -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
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
                                <span class="font-medium text-gray-800 truncate ml-2"><?php echo htmlspecialchars($userEmail); ?></span>
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
                            <div class="flex justify-between">
                                <span class="text-gray-600">ID Usuario:</span>
                                <span class="font-medium text-gray-800">#<?php echo $userId; ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Permisos del usuario -->
                    <div class="bg-white rounded-lg shadow p-6 lg:col-span-2">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-shield-alt mr-2 text-green-500"></i>
                            Tus Permisos Activos
                        </h3>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-3 max-h-64 overflow-y-auto">
                            <?php if (!empty($userPermissions)): ?>
                                <?php 
                                // Agrupar permisos por módulo
                                $permisosPorModulo = [];
                                foreach ($userPermissions as $perm) {
                                    $permisosPorModulo[$perm['modulo']][] = $perm;
                                }
                                
                                foreach ($permisosPorModulo as $modulo => $permisos):
                                ?>
                                <div class="bg-gray-50 rounded-lg p-3 hover:bg-gray-100 transition-colors">
                                    <div class="font-semibold text-sm text-gray-800 mb-2 capitalize flex items-center">
                                        <i class="fas fa-folder text-blue-500 mr-2 text-xs"></i>
                                        <?php echo htmlspecialchars($modulo); ?>
                                    </div>
                                    <div class="space-y-1">
                                        <?php 
                                        // Mostrar solo primeros 3 permisos
                                        $permisosLimitados = array_slice($permisos, 0, 3);
                                        foreach ($permisosLimitados as $perm): 
                                        ?>
                                        <div class="flex items-center text-xs text-gray-600">
                                            <i class="fas fa-check text-green-500 mr-2" style="font-size: 8px;"></i>
                                            <span class="truncate"><?php echo htmlspecialchars($perm['permiso']); ?></span>
                                        </div>
                                        <?php endforeach; ?>
                                        <?php if (count($permisos) > 3): ?>
                                        <div class="text-xs text-gray-500 italic">
                                            +<?php echo count($permisos) - 3; ?> más
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-sm text-gray-500 col-span-full text-center py-4">
                                    <i class="fas fa-exclamation-triangle text-yellow-500 mr-2"></i>
                                    No tienes permisos asignados
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                </div>

                <!-- Actividad reciente -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                            <i class="fas fa-history mr-2 text-purple-500"></i>
                            Actividad Reciente
                        </h3>
                        <button class="text-sm text-blue-600 hover:text-blue-800 font-medium transition">
                            Ver todo
                        </button>
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
    
    <!-- Scripts -->
    <script src="./public/js/sidebar.js"></script>
    <script src="./public/js/dashboard.js"></script>
</body>
</html>