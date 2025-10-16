<?php
/**
 * Logout.php - Cierra la sesión del usuario
 */

// Cargar configuración y clases necesarias
require_once 'config.php';
require_once 'includes/Database.php';
require_once 'includes/Session.php';
require_once 'includes/Permissions.php';
require_once 'includes/Auth.php';

// Iniciar sesión
$session = new Session();

// Verificar si hay una sesión activa
if ($session->isLoggedIn()) {
    // Registrar el logout en logs (opcional)
    $userId = $session->getUserId();
    $userName = $session->getUserData('name');
    error_log("User logout: ID={$userId}, Name={$userName}");
}

// Cerrar sesión usando el método de Auth
$auth = new Auth();
$auth->logout();

// El método logout() ya incluye redirección a login.php
// pero por si acaso, agregamos una redirección de respaldo
header('Location: login.php');
exit;
?>