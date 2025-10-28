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
// $isAdmin = $session->isAdmin();
$isAdmin= true;
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
    'clientes',
    'configuracion',
    'variables'
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
    'clientes' => [
        'title' => 'Clientes',
        'icon' => 'fa-users',
        'description' => 'Administra clientes y sus datos',
        'path' => 'modules/clientes/'
    ],
    'configuracion' => [
        'title' => 'Configuración',
        'icon' => 'fa-cogs',
        'description' => 'Ajustes y configuración del sistema',
        'path' => 'modules/configuracion/'
    ],
    'variables' => [
        'title' => 'Variables',
        'icon' => 'fa-list',
        'description' => 'Administra variables del sistema',
        'path' => 'modules/variables/'
    ]

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
    case 'view':
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
    case 'verify_qsq':
    case 'verify_qsq_single':
    case 'export':
        // Para acciones AJAX, usar el action.php
        $actionFile = 'accion.php';
        break;
    default:
        $actionFile = 'list.php';
}

$fullPath = $modulePath . $actionFile;
/*
if (!file_exists($fullPath)) {
    die("Archivo no encontrado: {$fullPath}");
}
*/
// Verificar si el archivo existe
if (!file_exists($fullPath)) {
    // Si no existe el archivo específico, intentar con list.php
    if (file_exists($modulePath . 'list.php')) {
        $fullPath = $modulePath . 'list.php';
    } else {
        die("
            <div style='display:flex;align-items:center;justify-content:center;height:100vh;font-family:sans-serif;'>
                <div style='text-align:center;'>
                    <h1 style='font-size:4rem;margin:0;color:#ef4444;'>404</h1>
                    <p style='font-size:1.5rem;color:#6b7280;'>Archivo no encontrado</p>
                    <p style='color:#9ca3af;'>No se pudo cargar: {$fullPath}</p>
                    <a href='catalogos.php?mod={$mod}&action=list' style='color:#3b82f6;text-decoration:none;'>← Volver a la lista</a>
                </div>
            </div>
        ");
    }
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
                        
                        <div class="flex items-center gap-4">
                            <!-- Breadcrumb para navegación -->
                            <nav class="flex items-center text-sm text-gray-500">
                                <a href="dashboard.php" class="hover:text-gray-700">Dashboard</a>
                                <span class="mx-2">/</span>
                                <a href="catalogos.php?mod=<?php echo $mod; ?>&action=list" class="hover:text-gray-700">
                                    <?php echo htmlspecialchars($currentModule['title']); ?>
                                </a>
                                <?php if ($action !== 'list'): ?>
                                <span class="mx-2">/</span>
                                <span class="text-gray-700 capitalize">
                                    <?php 
                                    $actionTitles = [
                                        'create' => 'Nuevo',
                                        'edit' => 'Editar',
                                        'view' => 'Ver',
                                        'permissions' => 'Permisos'
                                    ];
                                    echo $actionTitles[$action] ?? ucfirst($action);
                                    ?>
                                </span>
                                <?php endif; ?>
                            </nav>
                            
                            <span class="text-sm text-gray-600">
                                <i class="fas fa-user mr-1"></i>
                                <?php echo htmlspecialchars($userData['name']); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Mensajes de sesión -->
            <?php if (isset($_SESSION['success'])): ?>
            <div class="mx-8 mt-4">
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <strong class="font-bold">Éxito!</strong>
                    <span class="block sm:inline"><?php echo htmlspecialchars($_SESSION['success']); ?></span>
                    <button class="absolute top-0 right-0 px-4 py-3" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
            <div class="mx-8 mt-4">
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <strong class="font-bold">Error!</strong>
                    <span class="block sm:inline"><?php echo htmlspecialchars($_SESSION['error']); ?></span>
                    <button class="absolute top-0 right-0 px-4 py-3" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

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
    
    <!-- Script para manejar mensajes flash -->
    <script>
        // Auto-ocultar mensajes después de 5 segundos
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const alerts = document.querySelectorAll('[role="alert"]');
                alerts.forEach(alert => {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                });
            }, 5000);
        });
    </script>
</body>
</html>