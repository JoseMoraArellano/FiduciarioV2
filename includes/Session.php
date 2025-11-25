<?php
/**
 * Clase Session - Manejo seguro de sesiones
 * Incluye: Remember Me, regeneración de ID, protección contra hijacking
 */
class Session {
    
    private $db;
    private $sessionLifetime = 0; // 0 = hasta cerrar navegador
    private $rememberMeDays = 30; // Duración de "Remember Me"
    
    /**
     * Constructor - Inicializa la sesión de forma segura
     */
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        
        // Configuración segura de sesión
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_secure', 0); // Cambiar a 1 si usas HTTPS
            ini_set('session.cookie_samesite', 'Lax');
            
            session_name('BUSINESS_SESSION');
            session_set_cookie_params([
                'lifetime' => $this->sessionLifetime,
                'path' => '/',
                'domain' => '',
                'secure' => false, // Cambiar a true si usas HTTPS
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            
            session_start();
            
            // Protección contra session hijacking
            $this->validateSession();
        }
    }
    
    /**
     * Valida la sesión actual contra hijacking
     */
    private function validateSession() {
        if (!isset($_SESSION['initiated'])) {
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
            $_SESSION['user_agent'] = $this->getUserAgent();
            $_SESSION['ip_address'] = $this->getIpAddress();
        }
        
        // Verifica que el user agent y IP no hayan cambiado
        if ($_SESSION['user_agent'] !== $this->getUserAgent() || 
            $_SESSION['ip_address'] !== $this->getIpAddress()) {
            $this->destroy();
            return false;
        }
        
        // Regenera ID de sesión periódicamente (cada 30 minutos)
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
        
        return true;
    }
    /**
 * Obtiene el nombre de usuario (o email si no existe)
 */

    /**
     * Inicia sesión de usuario
     */
    public function login($userId, $userData, $permissions, $perfil, $rememberMe = false) {
        // Regenera ID de sesión al hacer login
        session_regenerate_id(true);
        
        // Guarda datos en sesión
        $_SESSION['userid'] = $userId;
        $_SESSION['email'] = $userData['email'];
        $_SESSION['name'] = $userData['name'];
        $_SESSION['admin'] = $userData['admin'];
        $_SESSION['empleado'] = $userData['empleado'];
        $_SESSION['statut'] = $userData['statut'];
        $_SESSION['permissions'] = $permissions;
        $_SESSION['perfil'] = $perfil;
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        
        // Actualiza última fecha de login
        $this->updateLastLogin($userId);
        
        // Maneja "Remember Me"
        if ($rememberMe) {
            $this->setRememberMeCookie($userId);
        }
        
        return true;
    }
    
    /**
     * Verifica si el usuario está autenticado
     */
    public function isLoggedIn() {
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
            return true;
        }
        
        // Intenta autenticar con cookie "Remember Me"
        return $this->checkRememberMe();
    }
    
    /**
     * Obtiene el ID del usuario actual
     */
    public function getUserId() {
        return $_SESSION['userid'] ?? null;
    }
    
    /**
     * Obtiene datos del usuario actual
     */
    public function getUserData($key = null) {
        if ($key === null) {
            return [
                'userid' => $_SESSION['userid'] ?? null,
                'email' => $_SESSION['email'] ?? null,
                'name' => $_SESSION['name'] ?? null,
                'admin' => $_SESSION['admin'] ?? 0,
                'empleado' => $_SESSION['empleado'] ?? 0,
                'perfil' => $_SESSION['perfil'] ?? [],
                'permissions' => $_SESSION['permissions'] ?? []
            ];
        }
        
        return $_SESSION[$key] ?? null;
    }
    
    /**
     * Verifica si el usuario tiene un permiso específico
     */

    public function hasPermission($modulo, $permiso, $subpermiso = null) {
        if (!isset($_SESSION['permissions']) || empty($_SESSION['permissions'])) {
            return false;
        }
        
        foreach ($_SESSION['permissions'] as $perm) {
            if ($perm['modulo'] === $modulo && $perm['permiso'] === $permiso) {
                if ($subpermiso === null || $perm['subpermiso'] === $subpermiso) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Verifica si es administrador
     */
public function isAdmin() {
    return isset($_SESSION['admin']) && $_SESSION['admin'] == 1;
}
public function getUsername() {
    return $_SESSION['name'] ?? $_SESSION['email'] ?? 'Sistema';
}    
    /**
     * Cierra la sesión del usuario
     */
    public function logout() {
        // Elimina cookie "Remember Me"
        $this->deleteRememberMeCookie();
        
        // Destruye la sesión
        $this->destroy();
    }
    
    /**
     * Destruye completamente la sesión
     */
    public function destroy() {
        $_SESSION = [];
        
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        
        session_destroy();
    }
    
    /**
     * Actualiza la última fecha de login
     */
    private function updateLastLogin($userId) {
        try {
            // Guarda el login anterior en dateprevioslogin
            $sql = "UPDATE t_perfil 
                    SET dateprevioslogin = datelastlogin, 
                        datelastlogin = NOW() 
                    WHERE fk_user = :user_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
            
        } catch (PDOException $e) {
            error_log("Error updating last login: " . $e->getMessage());
        }
    }
    
    /**
     * Crea cookie "Remember Me"
     */
    private function setRememberMeCookie($userId) {
        $token = bin2hex(random_bytes(32));
        $expiry = time() + ($this->rememberMeDays * 86400);
        
        // Guarda el token en la base de datos (necesitarás crear esta tabla)
        try {
            $sql = "INSERT INTO t_remember_tokens (fk_user, token, expires_at) 
                    VALUES (:user_id, :token, to_timestamp(:expires))
                    ON CONFLICT (fk_user) 
                    DO UPDATE SET token = :token, expires_at = to_timestamp(:expires)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'user_id' => $userId,
                'token' => hash('sha256', $token),
                'expires' => $expiry
            ]);
            
            // Establece la cookie
            setcookie(
                'remember_me',
                $userId . ':' . $token,
                $expiry,
                '/',
                '',
                false, // Cambiar a true si usas HTTPS
                true // httponly
            );
            
        } catch (PDOException $e) {
            error_log("Error setting remember me: " . $e->getMessage());
        }
    }
    
    /**
     * Verifica cookie "Remember Me" y autentica automáticamente
     */
    private function checkRememberMe() {
        if (!isset($_COOKIE['remember_me'])) {
            return false;
        }
        
        list($userId, $token) = explode(':', $_COOKIE['remember_me'], 2);
        
        try {
            $sql = "SELECT rt.*, u.email, u.name, u.admin, u.empleado, u.statut
                    FROM t_remember_tokens rt
                    INNER JOIN users u ON rt.fk_user = u.id
                    WHERE rt.fk_user = :user_id 
                    AND rt.token = :token 
                    AND rt.expires_at > NOW()
                    AND u.statut = 1";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'user_id' => $userId,
                'token' => hash('sha256', $token)
            ]);
            
            $result = $stmt->fetch();
            
            if ($result) {
                // Carga permisos y perfil
                require_once 'Permissions.php';
                $permissionsObj = new Permissions();
                $permissions = $permissionsObj->getUserPermissions($userId);
                $perfil = $permissionsObj->getUserPerfil($userId);
                
                // Autentica al usuario
                $this->login($userId, $result, $permissions, $perfil, true);
                return true;
            }
            
        } catch (PDOException $e) {
            error_log("Error checking remember me: " . $e->getMessage());
        }
        
        $this->deleteRememberMeCookie();
        return false;
    }
    
    /**
     * Elimina cookie "Remember Me"
     */
    private function deleteRememberMeCookie() {
        if (isset($_COOKIE['remember_me'])) {
            list($userId) = explode(':', $_COOKIE['remember_me'], 2);
            
            try {
                $sql = "DELETE FROM t_remember_tokens WHERE fk_user = :user_id";
                $stmt = $this->db->prepare($sql);
                $stmt->execute(['user_id' => $userId]);
            } catch (PDOException $e) {
                error_log("Error deleting remember token: " . $e->getMessage());
            }
            
            setcookie('remember_me', '', time() - 3600, '/');
        }
    }
    
    /**
     * Obtiene el User Agent del navegador
     */
    private function getUserAgent() {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    }
    
    /**
     * Obtiene la IP del usuario
     */
    private function getIpAddress() {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}
?>