<?php
/**
 * Clase Auth - Sistema de autenticación
 * Soporta migración de SHA256 a password_hash
 * Incluye bloqueo temporal por intentos fallidos
 */
class Auth {
    
    private $db;
    private $maxAttempts = 5; // Máximo de intentos fallidos
    private $lockoutTime = 900; // 15 minutos de bloqueo (en segundos)
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Autentica un usuario por email o nombre
     * @param string $identifier - Email o nombre de usuario
     * @param string $password - Contraseña en texto plano
     * @param bool $rememberMe - Si debe recordar la sesión
     * @return array - ['success' => bool, 'message' => string, 'user_id' => int]
     */
    public function login($identifier, $password, $rememberMe = false) {
        // Limpia intentos fallidos antiguos
        $this->cleanOldAttempts();
        
        // Verifica si está bloqueado
        if ($this->isLockedOut($identifier)) {
            $remainingTime = $this->getRemainingLockoutTime($identifier);
            return [
                'success' => false,
                'message' => "Demasiados intentos fallidos. Intente nuevamente en {$remainingTime} minutos.",
                'locked' => true
            ];
        }
        
        // Busca el usuario por email o nombre
        $user = $this->getUserByIdentifier($identifier);
        
        if (!$user) {
            $this->recordFailedAttempt($identifier);
            return [
                'success' => false,
                'message' => 'Credenciales incorrectas.'
            ];
        }
        
        // Verifica si el usuario está activo
        if ($user['statut'] != 1) {
            return [
                'success' => false,
                'message' => 'Usuario inactivo. Contacte al administrador.'
            ];
        }
        
        // Verifica la contraseña
        $passwordValid = $this->verifyPassword($password, $user['password'], $user['id']);
        
        if (!$passwordValid) {
            $this->recordFailedAttempt($identifier);
            return [
                'success' => false,
                'message' => 'Credenciales incorrectas.'
            ];
        }
        
        // Limpia intentos fallidos
        $this->clearFailedAttempts($identifier);
        
        // Carga permisos y perfil
        $permissions = new Permissions();
        $userPermissions = $permissions->getUserPermissions($user['id']);
        $userPerfil = $permissions->getUserPerfil($user['id']);
        
        // Inicia la sesión
        $session = new Session();
        $session->login(
            $user['id'],
            $user,
            $userPermissions,
            $userPerfil,
            $rememberMe
        );
        
        return [
            'success' => true,
            'message' => 'Login exitoso.',
            'user_id' => $user['id']
        ];
    }
    
    /**
     * Busca un usuario por email o nombre
     */
    private function getUserByIdentifier($identifier) {
        try {
            $sql = "
                SELECT 
                    id,
                    name,
                    email,
                    password,
                    admin,
                    empleado,
                    statut
                FROM users
                WHERE (email = :identifier OR name = :identifier)
                LIMIT 1
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['identifier' => $identifier]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error getting user: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verifica la contraseña (soporta SHA256 legacy y password_hash)
     * Si detecta SHA256, automáticamente migra a password_hash
     */
    private function verifyPassword($plainPassword, $hashedPassword, $userId) {
        // Intenta verificar con password_hash primero (nuevo método)
        if (password_verify($plainPassword, $hashedPassword)) {
            // Si necesita rehash (por ejemplo, cambió el algoritmo), actualiza
            if (password_needs_rehash($hashedPassword, PASSWORD_DEFAULT)) {
                $this->updatePassword($userId, $plainPassword);
            }
            return true;
        }
        
        // Si no funcionó, intenta con SHA256 (método legacy)
        $sha256Hash = hash('sha256', $plainPassword);
        
        if ($sha256Hash === $hashedPassword) {
            // Migra automáticamente a password_hash
            $this->updatePassword($userId, $plainPassword);
            return true;
        }
        
        return false;
    }
    
    /**
     * Actualiza la contraseña a password_hash
     */
    private function updatePassword($userId, $plainPassword) {
        try {
            $newHash = password_hash($plainPassword, PASSWORD_DEFAULT);
            
            $sql = "UPDATE users SET password = :password, updated_at = NOW() WHERE id = :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'password' => $newHash,
                'user_id' => $userId
            ]);
            
            error_log("Password migrated to password_hash for user ID: {$userId}");
            
        } catch (PDOException $e) {
            error_log("Error updating password: " . $e->getMessage());
        }
    }
    
    /**
     * Registra un intento fallido de login
     */
    private function recordFailedAttempt($identifier) {
        try {
            $ip = $this->getIpAddress();
            
            $sql = "
                INSERT INTO t_login_attempts (identifier, ip_address, attempted_at)
                VALUES (:identifier, :ip, NOW())
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'identifier' => $identifier,
                'ip' => $ip
            ]);
            
        } catch (PDOException $e) {
            error_log("Error recording failed attempt: " . $e->getMessage());
        }
    }
    
    /**
     * Limpia intentos fallidos de un usuario
     */
    private function clearFailedAttempts($identifier) {
        try {
            $ip = $this->getIpAddress();
            
            $sql = "DELETE FROM t_login_attempts WHERE identifier = :identifier AND ip_address = :ip";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'identifier' => $identifier,
                'ip' => $ip
            ]);
            
        } catch (PDOException $e) {
            error_log("Error clearing failed attempts: " . $e->getMessage());
        }
    }
    
    /**
     * Verifica si un usuario está bloqueado por intentos fallidos
     */
    private function isLockedOut($identifier) {
        try {
            $ip = $this->getIpAddress();
            $lockoutThreshold = time() - $this->lockoutTime;
            
            $sql = "
                SELECT COUNT(*) as attempts
                FROM t_login_attempts
                WHERE identifier = :identifier 
                AND ip_address = :ip
                AND attempted_at > to_timestamp(:threshold)
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'identifier' => $identifier,
                'ip' => $ip,
                'threshold' => $lockoutThreshold
            ]);
            
            $result = $stmt->fetch();
            
            return ($result['attempts'] >= $this->maxAttempts);
            
        } catch (PDOException $e) {
            error_log("Error checking lockout: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtiene el tiempo restante de bloqueo en minutos
     */
    private function getRemainingLockoutTime($identifier) {
        try {
            $ip = $this->getIpAddress();
            
            $sql = "
                SELECT MAX(attempted_at) as last_attempt
                FROM t_login_attempts
                WHERE identifier = :identifier AND ip_address = :ip
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'identifier' => $identifier,
                'ip' => $ip
            ]);
            
            $result = $stmt->fetch();
            
            if ($result && $result['last_attempt']) {
                $lastAttempt = strtotime($result['last_attempt']);
                $unlockTime = $lastAttempt + $this->lockoutTime;
                $remaining = $unlockTime - time();
                
                return ceil($remaining / 60); // En minutos
            }
            
            return 0;
            
        } catch (PDOException $e) {
            error_log("Error getting remaining lockout time: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Limpia intentos fallidos antiguos (más viejos que el tiempo de bloqueo)
     */
    private function cleanOldAttempts() {
        try {
            $threshold = time() - $this->lockoutTime;
            
            $sql = "DELETE FROM t_login_attempts WHERE attempted_at < to_timestamp(:threshold)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['threshold' => $threshold]);
            
        } catch (PDOException $e) {
            error_log("Error cleaning old attempts: " . $e->getMessage());
        }
    }
    
    /**
     * Cierra la sesión del usuario actual
     */
    public function logout() {
        $session = new Session();
        $session->logout();
        
        header('Location: /login.php');
        exit;
    }
    
    /**
     * Registra un nuevo usuario
     * @param array $userData - Datos del usuario
     * @return array - ['success' => bool, 'message' => string, 'user_id' => int]
     */
    public function register($userData) {
        try {
            // Valida que el email no exista
            $sql = "SELECT id FROM users WHERE email = :email";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['email' => $userData['email']]);
            
            if ($stmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'El email ya está registrado.'
                ];
            }
            
            // Hash de la contraseña con password_hash
            $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
            
            // Inserta el usuario
            $sql = "
                INSERT INTO users (name, email, password, created_at, statut, admin, empleado)
                VALUES (:name, :email, :password, NOW(), 1, 0, 0)
                RETURNING id
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => $hashedPassword
            ]);
            
            $result = $stmt->fetch();
            $userId = $result['id'];
            
            // Crea el perfil vacío
            $sql = "INSERT INTO t_perfil (fk_user) VALUES (:user_id)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
            
            // Asigna permisos por defecto (bydefault = 1)
            $this->assignDefaultPermissions($userId);
            
            return [
                'success' => true,
                'message' => 'Usuario registrado exitosamente.',
                'user_id' => $userId
            ];
            
        } catch (PDOException $e) {
            error_log("Error registering user: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al registrar el usuario.'
            ];
        }
    }
    
    /**
     * Asigna permisos por defecto a un nuevo usuario
     */
    private function assignDefaultPermissions($userId) {
        try {
            $sql = "
                INSERT INTO t_user_rights (fk_user, fk_id)
                SELECT :user_id, id
                FROM t_rights_def
                WHERE bydefault = 1
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
            
        } catch (PDOException $e) {
            error_log("Error assigning default permissions: " . $e->getMessage());
        }
    }
    
    /**
     * Prepara el sistema para recuperación de contraseña
     * (PREPARADO - NO IMPLEMENTADO AÚN)
     */
    public function requestPasswordReset($email) {
        // TODO: Implementar lógica de recuperación de contraseña
        // 1. Validar que el email existe
        // 2. Generar token único
        // 3. Guardar token en tabla t_password_resets
        // 4. Enviar email con link de reset
        
        return [
            'success' => false,
            'message' => 'Funcionalidad no implementada aún.'
        ];
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