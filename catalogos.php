<?php
/**
 * catalogos.php - Router principal de catálogos
 * Carga módulos dinámicamente según parámetro ?mod=
 * Verifica permisos antes de mostrar contenido
 */

// Cargar configuración y clases
require_once 'config.php';
require_once 'includes/Database.php';
require_once 'includes/Session.php';
require_once 'includes/Permissions.php';
require_once 'includes/Auth.php';
require_once 'includes/Sidebar.php';

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
$isAdmin = $session->isAdmin();
$userPermissions = $userData['permissions'] ?? [];

// Obtener módulo solicitado
$mod = $_GET['mod'] ?? '';
$action = $_GET['action'] ?? 'list';

// Validar módulo
$allowedModules = [
    'honorarios',
    'patrimonios',
    'tiie',
    'inpc',
    'udis',
    'tdc',
    'cpp',
    'servicios',
    'usuarios',
    'grupos',
    'clientes'
];

if (!in_array($mod, $allowedModules)) {
    header('Location: dashboard.php');
    exit;
}

// Verificar permiso de lectura del módulo
if (!$isAdmin && !$session->hasPermission('catalogos', 'lire', $mod)) {
    die('
        <div style="display:flex;align-items:center;justify-content:center;height:100vh;font-family:sans-serif;">
            <div style="text-align:center;">
                <h1 style="font-size:4rem;margin:0;color:#ef4444;">403</h1>
                <p style="font-size:1.5rem;color:#6b7280;">Acceso Denegado</p>
                <p style="color:#9ca3af;">No tienes permisos para acceder a este módulo.</p>
                <a href="dashboard.php" style="color:#3b82f6;text-decoration:none;">← Volver al Dashboard</a>
            </div>
        </div>
    ');
}

// Definir metadata de módulos
$modulesMetadata = [
    'usuarios' => [
        'title' => 'Gestión de Usuarios',
        'icon' => 'fa-users-cog',
        'description' => 'Administra usuarios del sistema, permisos y grupos',
        'path' => 'modules/usuarios/'
    ],
    'grupos' => [
        'title' => 'Gestión de Grupos',
        'icon' => 'fa-users-cog',
        'description' => 'Administra grupos de usuarios y sus permisos',
        'path' => 'modules/grupos/'
    ],
    'honorarios' => [
        'title' => 'Honorarios',
        'icon' => 'fa-money-bill-wave',
        'description' => 'Catálogo de honorarios',
        'path' => 'modules/honorarios/'
    ],
    // ... otros módulos (agregar según necesites)
];

$currentModule = $modulesMetadata[$mod] ?? null;

if (!$currentModule) {
    die('Módulo no configurado');
}

// Cargar el archivo del módulo según la acción
$modulePath = $currentModule['path'];
$actionFile = '';

switch ($action) {
    case 'list':
        $actionFile = 'list.php';
        break;
    case 'create':
    case 'edit':
        $actionFile = 'form.php';
        break;
    case 'permissions':
        $actionFile = 'permissions.php';
        break;
    case 'groups':
        $actionFile = 'groups.php';
        break;
    case 'delete':
    case 'toggle-status':
    case 'duplicate':
    case 'save':
        $actionFile = 'actions.php';
        break;
    default:
        $actionFile = 'list.php';
}

$fullPath = $modulePath . $actionFile;

if (!file_exists($fullPath)) {
    die("Archivo no encontrado: {$fullPath}");
}

// Crear instancia del Sidebar
$sidebar = new Sidebar($userPermissions, $userId, 'catalogos.php', $isAdmin);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($currentModule['title']); ?>Fiducia</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS personalizados -->
    <link rel="stylesheet" href="./public/css/dashboard.css">
    <link rel="stylesheet" href="./public/css/sidebar.css">
    <link rel="stylesheet" href="./public/css/usuarios.css">
    <link rel="icon" type="image/x-icon" href="./img/afiducialogo.png">
</head>
<body class="bg-gray-100">
    
    <div class="flex h-screen overflow-hidden">
        
        <!-- Sidebar -->
        <?php echo $sidebar->render($userData); ?>

        <!-- Contenido principal -->
        <main class="flex-1 overflow-y-auto">
            
            <!-- Header del módulo -->
            <header class="bg-white shadow-sm sticky top-0 z-30">
                <div class="px-8 py-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <a href="dashboard.php" class="text-gray-600 hover:text-gray-800 transition">
                                <i class="fas fa-arrow-left"></i>
                            </a>
                            <div>
                                <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                                    <i class="fas <?php echo $currentModule['icon']; ?> text-blue-600"></i>
                                    <?php echo htmlspecialchars($currentModule['title']); ?>
                                </h1>
                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($currentModule['description']); ?></p>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-600">
                                <i class="fas fa-user mr-1"></i>
                                <?php echo htmlspecialchars($userData['name']); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Contenido del módulo -->
            <div class="p-8">
                <?php 
                // Incluir el archivo del módulo
                include $fullPath; 
                ?>
            </div>
            
        </main>
        
    </div>
    
    <!-- Scripts -->
    <script src="./public/js/sidebar.js"></script>
    <script src="./public/js/usuarios.js"></script>
</body>
</html>