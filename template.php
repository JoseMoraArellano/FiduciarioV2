<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// =============================================================================
// 1. CONFIGURACIÓN INICIAL Y SEGURIDAD
// =============================================================================

// Ajusta estas rutas según la ubicación segun requieras
// Si el archivo está en la raíz, usa './' 
// Si está en subdirectorios, ajusta '../../' según la profundidad
require_once [$_SERVER].'/config.php';
require_once 'includes/Database.php';
require_once 'includes/Session.php';
require_once 'includes/Permissions.php';
require_once 'includes/Auth.php';
require_once 'includes/Sidebar.php';

// --- INICIAR Y VERIFICAR SESIÓN ---
// Esto es CRUCIAL para seguridad: previene acceso sin autenticación
$session = new Session();

// --- CONEXIÓN A BD (si es necesario) ---
if (isset($database)) {
    $db = $database;
} elseif (class_exists('Database')) {
    $db = Database::getInstance()->getConnection();
} else {
    die('No se pudo obtener conexión a la base de datos');
}

// Si el usuario no está logueado, redirige al login
if (!$session->isLoggedIn()) {
//    header('Location: ../../login.php');
    exit; // Siempre usar exit después de header redirect
}

// =============================================================================
// 2. EJEMPLO: DATOS DEL USUARIO Y PERMISOS
// =============================================================================

// --- OBTENER INFORMACIÓN DEL USUARIO ---
// Estos datos se usan para personalizar la experiencia y verificar permisos
$userData = $session->getUserData();    // Datos completos del usuario
$userId = $session->getUserId();        // ID único del usuario
$isAdmin = $session->isAdmin();         // Si es administrador
$userPermissions = $userData['permissions'] ?? []; // Permisos específicos para visualizar el menú


// --- CONFIGURACIÓN DEL SIDEBAR ---
// IMPORTANTE: Esta línea determina qué menú se marca como activo
$currentFile = basename(__FILE__);      // Obtiene el nombre del archivo actual
$sidebar = new Sidebar($userPermissions, $userId, $currentFile, $isAdmin);
$menuStats = $sidebar->getMenuStats();  // Estadísticas para el menú

// =============================================================================
// 3. VERIFICACIÓN DE PERMISOS ESPECÍFICOS 
// =============================================================================

// --- AJUSTAR SEGÚN EL MÓDULO ACTUAL ---
// Cambia 'configuracion' por el nombre del módulo tabla t_rights_def y t_usergroup_user
// Cambia 'lire' por la acción correspondiente (lire, modifier, supprimer)

 //$canRead = $session->hasPermission('configuracion', 'lire', 'leer') || $isAdmin;
 //$canUpdate = $session->hasPermission('configuracion', 'modifier', 'actualizar') || $isAdmin;
 // $canDelete = $session->hasPermission('configuracion', 'supprimer', 'eliminar') || $isAdmin;

// Verificar permiso mínimo para acceder a la página
//if (!$canRead) {
//    header('Location: ../../login.php?error=permission_denied');
//    exit();
// }



// --- AQUÍ VA LA LÓGICA ESPECÍFICA ---
// Procesamiento de formularios, consultas a BD, etc.
// Ejemplo:
// if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//     // Procesar formulario
// }

// =============================================================================
// 5. CONFIGURACIÓN DE LA PÁGINA
// =============================================================================

// --- DEFINIR TÍTULO Y METADATOS ---
$pageTitle = 'Modulo / Seccion'; // Cambiar por el título específico

// =============================================================================
// 6. ESTRUCTURA HTML (HEAD + BODY)
// =============================================================================
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../../img/afiducialogo.png">
    <title><?php echo $pageTitle; ?></title>
    
    <!-- CDNs NECESARIOS - NO MODIFICAR -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- Estilos adicionales específicos de la página -->
    <style>
        [x-cloak] { display: none !important; }
        /* Agregar aquí estilos CSS específicos si son necesarios */        
       
    </style>
        <!--      Usamos  Tailwind + Alpine  -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.4.0/dist/tailwind.min.css" rel="stylesheet"> 
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <!--Icono superior de la pestaña-->
    <link rel="icon" type="image/x-icon" href="./img/afiducialogo.png">
</head>

<body class="bg-gray-100">
<!-- ============================================================================= -->
<!-- ESTRUCTURA PRINCIPAL DEL LAYOUT - NO MODIFICAR -->
<!-- ============================================================================= -->
<div class="flex h-screen">
    <!-- SIDEBAR LATERAL -->
    <!-- Se renderiza automáticamente con los datos del usuario -->
    <?php echo $sidebar->render($userData); ?>
    
    <!-- CONTENIDO PRINCIPAL -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <!-- HEADER SUPERIOR -->
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="flex items-center justify-between px-6 py-4">
                <!-- BLOQUE IZQUIERDO: Navegación y título -->
                <div class="flex items-center gap-4">
                    <!-- Botón para volver al dashboard -->
                    <a href="../../dashboard.php" class="text-gray-600 hover:text-gray-800 transition">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <!-- Título de la página -->
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                            <!-- Icono de libro abierto ejemplo -->
                            <i class="fa-solid fa-book-open text-blue-600"></i>
                            <?php echo $pageTitle; ?>
                        </h1>
                        <p class="text-sm text-gray-600">Descripción breve de la página</p>
                    </div>
                </div>
                
                <!-- BLOQUE DERECHO: Migas de pan e información de usuario -->
                <nav class="flex items-center text-sm text-gray-500">
                    <a href="../../dashboard.php" class="hover:text-gray-700">Dashboard</a>
                    <span class="mx-2">/</span>
                    <a href="../catalogos.php" class="hover:text-gray-700">Catálogos</a>
                    <span class="mx-2">/</span>
                    <span class="text-gray-700 capitalize"><?php echo $pageTitle; ?></span>
                    <span class="mx-2">/</span>
                    <span class="text-gray-700 capitalize">
                        <i class="fas fa-user mr-1"></i>    
                        <?= htmlspecialchars($userData['name'] ?? 'Usuario') ?>
                    </span>
                </nav>
            </div>
        </header>

        <!-- ============================================================================= -->
        <!-- CONTENIDO ESPECÍFICO DE LA PÁGINA - MODIFICAR ESTA SECCIÓN -->
        <!-- ============================================================================= -->
        <main class="flex-1 overflow-y-auto p-6">
            <!-- 
            AQUÍ VA EL CONTENIDO ESPECÍFICO DE LA PÁGINA
            Ejemplos:
            - Tablas de datos
            - Formularios
            - Gráficos
            - Listados
            -->
            
            <!-- EJEMPLO: Contenedor básico -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-semibold mb-4">Contenido de la Página</h2>
                <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nullam scelerisque aliquam odio et faucibus.</p>
                
                <!-- Aquí va tu HTML/PHP específico -->
            </div>
            <h1 class="text-2xl font-bold mb-4">Bienvenido a <?php echo $pageTitle; ?></h1>
            <?php
            // Aquí va tu lógica específica de la página
            echo $userData['name'] ?? 'Usuario';        
        // Depuración: imprimir los permisos del usuario
echo '<div class="p-6 bg-yellow-50 border border-yellow-200 text-yellow-800 rounded">';
echo '<h3 class="font-bold">Depuración de Permisos</h3>';
echo '<pre>Menu Stats: ' . print_r($menuStats, true) . '</pre>';
echo '<pre>Current File: ' . $currentFile . '</pre>';
echo '<pre>' . $_SESSION['ip_address'] . '</pre>';
echo '<pre>' . $_SESSION['user_agent'] . '</pre>';
echo '<pre>User ID: ' . $userId . '</pre>';
echo '<pre>Can Read: ' . ($canRead ? 'Yes' : 'No') . '</pre>';
echo '<pre>User Data: ' . print_r($userData, true) . '</pre>';
echo '<pre>Is Admin: ' . ($isAdmin ? 'Yes' : 'No') . '</pre>';
echo '<pre>Permisos del usuario (desde $userData): ' . print_r($userPermissions, true) . '</pre>';

// Si usas la clase Permissions, podrías querer verificar todos los permisos para el módulo 'tiie'
if (class_exists('Permissions')) {
    $permissionsObj = new Permissions();
    

    // Esto es un ejemplo, ajusta según tus métodos reales
    echo '<pre>Permisos para tiie (desde Permissions): </pre>';
    echo '<pre>  - lire: ' . ($permissionsObj->hasPermission($userId, 'catalogos', 'lire', 'tiie') ? 'Yes' : 'No') . '</pre>';
    echo '<pre>  - creer: ' . ($permissionsObj->hasPermission($userId, 'catalogos', 'creer', 'tiie') ? 'Yes' : 'No') . '</pre>';
    echo '<pre>  - modifier: ' . ($permissionsObj->hasPermission($userId, 'catalogos', 'modifier', 'tiie') ? 'Yes' : 'No') . '</pre>';
    echo '<pre>  - supprimer: ' . ($permissionsObj->hasPermission($userId, 'catalogos', 'supprimer', 'tiie') ? 'Yes' : 'No') . '</pre>';
}

// También verificar a través de la sesión
echo '<pre>Permisos para tiie (desde Session): </pre>';
echo '<pre>  - lire: ' . ($session->hasPermission('tiie', 'lire', 'leer') ? 'Yes' : 'No') . '</pre>';
echo '<pre>  - creer: ' . ($session->hasPermission('tiie', 'creer', 'crear') ? 'Yes' : 'No') . '</pre>';
echo '<pre>  - modifier: ' . ($session->hasPermission('tiie', 'modifier', 'modificar') ? 'Yes' : 'No') . '</pre>';
echo '<pre>  - supprimer: ' . ($session->hasPermission('tiie', 'supprimer', 'eliminar') ? 'Yes' : 'No') . '</pre>';

echo '</div>';
            ?>            
        </main>
    </div>
</div>

<!-- ============================================================================= -->
<!-- SCRIPTS JAVASCRIPT - NO MODIFICAR (a menos que necesites funcionalidad extra) -->
<!-- ============================================================================= -->
<script>
// Script para el sidebar - maneja estado abierto/cerrado
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
            }).catch(error => {
                console.error('Error guardando estado del sidebar:', error);
            });
        },
        
        getStoredSidebarState() {
            return this.sidebarOpen;
        }
    }
}

// Script específico de la página (agregar aquí si necesitas)
document.addEventListener('DOMContentLoaded', function() {
    // JavaScript específico aquí
});
</script>
</body>
</html>