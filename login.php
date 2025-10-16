<?php
/**
 * Página de Login
 */

// Cargar configuración y clases
require_once 'config.php';
require_once 'includes/Database.php';
require_once 'includes/Session.php';
require_once 'includes/Permissions.php';
require_once 'includes/Auth.php';

// Iniciar sesión
$session = new Session();

// Si ya está logueado, redirigir al dashboard
if ($session->isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

// Variables para mensajes
$error = '';
$success = '';

// Procesar el formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';
    $rememberMe = isset($_POST['remember_me']);
    
    // Validaciones básicas
    if (empty($identifier) || empty($password)) {
        $error = 'Por favor complete todos los campos.';
    } else {
        $auth = new Auth();
        $result = $auth->login($identifier, $password, $rememberMe);
        
        if ($result['success']) {
            // Redirigir al dashboard
            header('Location: dashboard.php');
            exit;
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Fiduciario</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="./public/css/login.css">
</head>
<body class="bg-gradient-to-br from-blue-500 min-h-screen flex items-center justify-center p-4">
    
    <div class="w-full max-w-md">
        
        <!-- Card de Login -->
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
            
            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-600 to-purple-600 p-8 text-center">
                <div class="w-20 h-20 bg-white rounded-full mx-auto mb-4 flex items-center justify-center">
<!--                    <i class="fas fa-briefcase text-4xl text-blue-600"></i>-->
                    <img src="img/Fiduciapalomas.jpg" alt="Logo" class="w-12 h-12">
                </div>
                <h1 class="text-3xl font-bold text-white mb-2">Sistema Fiduciario</h1>                
            </div>
            
            <!-- Formulario -->
            <div class="p-8">
                
                <?php if ($error): ?>
                    <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                            <p class="text-red-700 text-sm"><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-3"></i>
                            <p class="text-green-700 text-sm"><?php echo htmlspecialchars($success); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="login.php" id="loginForm">
                    
                    <!-- Campo Email/Usuario -->
                    <div class="mb-6">
                        <label for="identifier" class="block text-gray-700 text-sm font-semibold mb-2">
                            <i class="fas fa-user mr-2 text-gray-400"></i>
                            Email o Usuario
                        </label>
                        <input 
                            type="text" 
                            id="identifier" 
                            name="identifier" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                            placeholder="correo@ejemplo.com"
                            value="<?php echo htmlspecialchars($_POST['identifier'] ?? ''); ?>"
                            required
                            autofocus
                        >
                    </div>
                    
                    <!-- Campo Contraseña -->
                    <div class="mb-6">
                        <label for="password" class="block text-gray-700 text-sm font-semibold mb-2">
                            <i class="fas fa-lock mr-2 text-gray-400"></i>
                            Contraseña
                        </label>
                        <div class="relative">
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition pr-12"
                                placeholder="••••••••"
                                required
                            >
                            <button 
                                type="button" 
                                id="togglePassword"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition"
                            >
                                <i class="fas fa-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Remember Me y Olvidé Contraseña -->
                    <div class="flex items-center justify-between mb-6">
                        <label class="flex items-center">
                            <input 
                                type="checkbox" 
                                name="remember_me" 
                                id="remember_me"
                                class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                            >
                            <span class="ml-2 text-sm text-gray-700">Recordarme</span>
                        </label>
                        
                        <a href="forgot-password.php" class="text-sm text-blue-600 hover:text-blue-800 font-medium transition">
                            ¿Olvidaste tu contraseña?
                        </a>
                    </div>
                    
                    <!-- Botón Submit -->
                    <button 
                        type="submit" 
                        class="w-full bg-gradient-to-r from-blue-600 to-purple-600 text-white py-3 rounded-lg font-semibold hover:from-blue-700 hover:to-purple-700 transform hover:scale-[1.02] transition duration-200 shadow-lg"
                    >
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        Iniciar Sesión
                    </button>
                    
                </form>
                
                <!-- Divider -->
                <div class="relative my-8">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-300"></div>
                    </div>

<!--                    <div class="relative flex justify-center text-sm">
                        <span class="px-4 bg-white text-gray-500">O continúa con</span>
                    </div>
                -->                    
                </div>                
            </div>
        </div>
        
        <!-- Footer -->
        <div class="mt-8 text-center text-white text-sm">
            <p>&copy; <?php echo date('Y'); ?> Afianzadora fiducia. Todos los derechos reservados. V. 1.1.0</p>
        </div>
        
    </div>
    
    <script src="./public/js/login.js"></script>
</body>
</html>