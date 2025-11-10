<?php
/**
 * Clase UsuariosManager - Gestión completa de usuarios
 * Maneja: CRUD, permisos, grupos, validaciones, duplicación
 */
class UsuariosManager {
    
    private $db;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    // ============== CRUD DE USUARIOS ==============
    
    
    /**
     * Obtener todos los usuarios con filtros
     * @param array $filters - Filtros opcionales (search, status, role, group)
     * @param int $page - Página actual
     * @param int $perPage - Items por página
     * @return array
     */
    public function getUsuarios($filters = [], $page = 1, $perPage = 20) {
        try {
            $where = ['1=1'];
            $params = [];
            
            // Filtro de búsqueda
            if (!empty($filters['search'])) {
                $where[] = "(u.name ILIKE :search OR u.email ILIKE :search OR 
                            p.firstname ILIKE :search OR p.lastname ILIKE :search)";
                $params['search'] = '%' . $filters['search'] . '%';
            }
            
            // Filtro de estado
            if (isset($filters['status']) && $filters['status'] !== '') {
                $where[] = "u.statut = :status";
                $params['status'] = (int)$filters['status'];
            }
            
            // Filtro de rol
            if (isset($filters['role'])) {
                if ($filters['role'] === 'admin') {
                    $where[] = "u.admin = 1";
                } elseif ($filters['role'] === 'empleado') {
                    $where[] = "u.empleado = 1";
                } elseif ($filters['role'] === 'normal') {
                    $where[] = "u.admin = 0 AND u.empleado = 0";
                }
            }
            
            // Filtro por grupo
            if (!empty($filters['group'])) {
                $where[] = "EXISTS (
                    SELECT 1 FROM t_usergroup_user ugu 
                    WHERE ugu.fk_user = u.id 
                    AND ugu.fk_usergroup = :group_id
                )";
                $params['group_id'] = (int)$filters['group'];
            }
            
            $whereClause = implode(' AND ', $where);
            
            // Contar total
            $sqlCount = "
                SELECT COUNT(DISTINCT u.id) as total
                FROM users u
                LEFT JOIN t_perfil p ON u.id = p.fk_user
                WHERE {$whereClause}
            ";
            
            $stmtCount = $this->db->prepare($sqlCount);
            $stmtCount->execute($params);
            $total = $stmtCount->fetch()['total'];
            
            // Obtener registros paginados
            $offset = ($page - 1) * $perPage;
            
            $sql = "
                SELECT 
                    u.id,
                    u.name,
                    u.email,
                    u.admin,
                    u.empleado,
                    u.statut,
                    u.created_at,
                    p.firstname,
                    p.lastname,
                    p.civility,
                    p.puesto,
                    p.datelastlogin,
                    (SELECT COUNT(*) FROM t_user_rights WHERE fk_user = u.id) as permisos_directos,
                    (SELECT COUNT(DISTINCT fk_usergroup) FROM t_usergroup_user WHERE fk_user = u.id) as grupos
                FROM users u
                LEFT JOIN t_perfil p ON u.id = p.fk_user
                WHERE {$whereClause}
                ORDER BY u.created_at DESC
                LIMIT :limit OFFSET :offset
            ";
            
            $stmt = $this->db->prepare($sql);
            
            // Bind de parámetros
            foreach ($params as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            
            $stmt->execute();
            $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'data' => $usuarios,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage)
            ];
            
        } catch (PDOException $e) {
            error_log("Error getting usuarios: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al obtener usuarios'
            ];
        }
    }
    
    /**
     * Obtener un usuario por ID con toda su información
     */
    public function getUsuario($id) {
        try {
            $sql = "
                SELECT 
                    u.*,
                    p.*,
                    u.id as user_id,
                    p.id as perfil_id
                FROM users u
                LEFT JOIN t_perfil p ON u.id = p.fk_user
                WHERE u.id = :id
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);
            
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$usuario) {
                return [
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ];
            }
            
            // Obtener grupos del usuario
            $usuario['grupos'] = $this->getUserGroups($id);
            
            // Obtener permisos directos
            $usuario['permisos'] = $this->getUserPermissions($id);
            
            return [
                'success' => true,
                'data' => $usuario
            ];
            
        } catch (PDOException $e) {
            error_log("Error getting usuario: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al obtener usuario'
            ];
        }
    }
    
    /**
     * Crear nuevo usuario
     */
    public function createUsuario($data) {
        try {
            $this->db->beginTransaction();
            
            // Validar datos requeridos
            $validation = $this->validateUsuarioData($data);
            if (!$validation['success']) {
                return $validation;
            }
            
            // Verificar que email y username sean únicos
            if ($this->emailExists($data['email'])) {
                return [
                    'success' => false,
                    'message' => 'El email ya está registrado'
                ];
            }
            
            if ($this->usernameExists($data['name'])) {
                return [
                    'success' => false,
                    'message' => 'El nombre de usuario ya existe'
                ];
            }
            
            // Crear usuario
            $sql = "
                INSERT INTO users (
                    name, email, password, admin, empleado, 
                    statut, created_at, updated_at
                )
                VALUES (
                    :name, :email, :password, :admin, :empleado,
                    :statut, NOW(), NOW()
                )
                RETURNING id
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => password_hash($data['password'], PASSWORD_DEFAULT),
                'admin' => isset($data['admin']) ? 1 : 0,
                'empleado' => isset($data['empleado']) ? 1 : 0,
                'statut' => isset($data['statut']) ? (int)$data['statut'] : 1
            ]);
            
            $userId = $stmt->fetch()['id'];
            // Registrar en historial

            $this->addHistorial($userId, 'CREATE', 'Usuario creado', $data);
            // Crear perfil
            $this->createPerfil($userId, $data);
            
            // Asignar permisos por defecto si no es admin
            if (!isset($data['admin'])) {
                $this->assignDefaultPermissions($userId);
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Usuario creado exitosamente',
                'user_id' => $userId
            ];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error creating usuario: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al crear usuario'
            ];
        }
    }
    
    /**
     * Actualizar usuario existente
     */
    public function updateUsuario($id, $data) {
        try {
            $this->db->beginTransaction();
            
            // Validar datos
            $validation = $this->validateUsuarioData($data, $id);
            if (!$validation['success']) {
                return $validation;
            }
            
            // Verificar email único (excepto el mismo usuario)
            if ($this->emailExists($data['email'], $id)) {
                return [
                    'success' => false,
                    'message' => 'El email ya está registrado por otro usuario'
                ];
            }
            
            // Verificar username único (excepto el mismo usuario)
            if ($this->usernameExists($data['name'], $id)) {
                return [
                    'success' => false,
                    'message' => 'El nombre de usuario ya existe'
                ];
            }
            
            // Actualizar usuario
            $sql = "
                UPDATE users SET
                    name = :name,
                    email = :email,
                    admin = :admin,
                    empleado = :empleado,
                    statut = :statut,
                    updated_at = NOW()
            ";
            
            $params = [
                'name' => $data['name'],
                'email' => $data['email'],
                'admin' => isset($data['admin']) ? 1 : 0,
                'empleado' => isset($data['empleado']) ? 1 : 0,
                'statut' => isset($data['statut']) ? (int)$data['statut'] : 1,
                'id' => $id
            ];
            
            // Actualizar password solo si se proporciona
            if (!empty($data['password'])) {
                $sql .= ", password = :password";
                $params['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            
            // Actualizar API key solo si es admin quien lo solicita
            if (isset($data['api_key']) && !empty($data['api_key'])) {
                $sql .= ", api_key = :api_key";
                $params['api_key'] = $data['api_key'];
            }
            
            $sql .= " WHERE id = :id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            // Actualizar perfil
            $this->updatePerfil($id, $data);

            // Registrar cambios en historial
            $changes = $this->compareChanges($oldData['data'], $data);
            if (!empty($changes)) {
                $this->addHistorial($id, 'UPDATE', 'usuario actualizado', $changes);
            }            
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Usuario actualizado exitosamente'
            ];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error updating usuario: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al actualizar usuario'
            ];
        }
    }
    
    /**
     * Eliminar usuario (soft delete - cambiar statut a -1)
     */
    public function deleteUsuario($id) {
        try {
            // No permitir eliminar usuario ID 1 (super admin)
            if ($id == 1) {
                return [
                    'success' => false,
                    'message' => 'No se puede eliminar el usuario administrador principal'
                ];
            }
            
            $sql = "UPDATE users SET statut = -1, updated_at = NOW() WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);

            // Registrar en historial
            $this->addHistorial($id, 'DELETE', 'Usuario eliminado');
            
            return [
                'success' => true,
                'message' => 'Usuario eliminado exitosamente'
            ];
            
        } catch (PDOException $e) {
            error_log("Error deleting usuario: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al eliminar usuario'
            ];
        }
    }
    
    /**
     * Cambiar estado del usuario (activar/desactivar)
     */
    public function toggleStatus($id) {
        try {
            // No permitir desactivar usuario ID 1
            if ($id == 1) {
                return [
                    'success' => false,
                    'message' => 'No se puede desactivar el usuario administrador principal'
                ];
            }
            
            $sql = "
                UPDATE users 
                SET statut = CASE WHEN statut = 1 THEN 0 ELSE 1 END,
                    updated_at = NOW()
                WHERE id = :id
                RETURNING statut
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);
            
            $newStatus = $stmt->fetch()['statut'];

            // Registrar en historial
            $this->addHistorial($id, 'STATUS_CHANGE', $newStatus ? 'Usuario activado' : 'Usuario desactivado');
            
            return [
                'success' => true,
                'message' => $newStatus == 1 ? 'Usuario activado' : 'Usuario desactivado',
                'new_status' => $newStatus
            ];
            
        } catch (PDOException $e) {
            error_log("Error toggling status: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al cambiar estado'
            ];
        }
    }
    
    /**
     * Duplicar usuario (copiar datos y permisos)
     */
    public function duplicateUsuario($id) {
        try {
            $this->db->beginTransaction();
            
            // Obtener usuario original
            $result = $this->getUsuario($id);
            if (!$result['success']) {
                return $result;
            }
            
            $original = $result['data'];
            
            // Crear nuevo nombre y email únicos
            $newName = $original['name'] . '_copia';
            $newEmail = 'copia_' . $original['email'];
            
            // Asegurar unicidad
            $counter = 1;
            while ($this->usernameExists($newName)) {
                $newName = $original['name'] . '_copia' . $counter;
                $counter++;
            }
            
            $counter = 1;
            while ($this->emailExists($newEmail)) {
                $newEmail = 'copia' . $counter . '_' . $original['email'];
                $counter++;
            }
            
            // Crear nuevo usuario
            $sql = "
                INSERT INTO users (
                    name, email, password, admin, empleado, 
                    statut, created_at, updated_at
                )
                VALUES (
                    :name, :email, :password, :admin, :empleado,
                    0, NOW(), NOW()
                )
                RETURNING id
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'name' => $newName,
                'email' => $newEmail,
                'password' => $original['password'], // Copiar hash
                'admin' => $original['admin'],
                'empleado' => $original['empleado']
            ]);
            
            $newUserId = $stmt->fetch()['id'];
            
            // Copiar perfil
            $sql = "
                INSERT INTO t_perfil (
                    fk_user, civility, lastname, firstname, direccion,
                    zip, ciudad, pais, edo, birth, puesto, tel, tel2, ext,
                    firma, note_public, note_private, gender, adminfide
                )
                SELECT 
                    :new_user_id, civility, lastname, firstname, direccion,
                    zip, ciudad, pais, edo, birth, puesto, tel, tel2, ext,
                    firma, note_public, note_private, gender, adminfide
                FROM t_perfil
                WHERE fk_user = :original_user_id
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'new_user_id' => $newUserId,
                'original_user_id' => $id
            ]);
            
            // Copiar permisos directos
            $sql = "
                INSERT INTO t_user_rights (fk_user, fk_id)
                SELECT :new_user_id, fk_id
                FROM t_user_rights
                WHERE fk_user = :original_user_id
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'new_user_id' => $newUserId,
                'original_user_id' => $id
            ]);
            
            // Copiar grupos
            $sql = "
                INSERT INTO t_usergroup_user (fk_user, fk_usergroup, entity)
                SELECT :new_user_id, fk_usergroup, entity
                FROM t_usergroup_user
                WHERE fk_user = :original_user_id
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'new_user_id' => $newUserId,
                'original_user_id' => $id
            ]);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Usuario duplicado exitosamente',
                'new_user_id' => $newUserId,
                'new_username' => $newName
            ];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error duplicating usuario: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al duplicar usuario'
            ];
        }
    }
    
    // ========================================
    // GESTIÓN DE PERFIL
    // ========================================
    
    /**
     * Crear perfil de usuario
     */
    private function createPerfil($userId, $data) {
        $sql = "
            INSERT INTO t_perfil (
                fk_user, civility, lastname, firstname, direccion,
                zip, ciudad, pais, edo, birth, puesto, tel, tel2, ext,
                firma, note_public, note_private, gender, adminfide
            )
            VALUES (
                :fk_user, :civility, :lastname, :firstname, :direccion,
                :zip, :ciudad, :pais, :edo, :birth, :puesto, :tel, :tel2, :ext,
                :firma, :note_public, :note_private, :gender, :adminfide
            )
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'fk_user' => $userId,
            'civility' => $data['civility'] ?? null,
            'lastname' => $data['lastname'] ?? null,
            'firstname' => $data['firstname'] ?? null,
            'direccion' => $data['direccion'] ?? null,
            'zip' => $data['zip'] ?? null,
            'ciudad' => $data['ciudad'] ?? null,
            'pais' => $data['pais'] ?? 'México',
            'edo' => $data['edo'] ?? null,
            'birth' => $data['birth'] ?? null,
            'puesto' => $data['puesto'] ?? null,
            'tel' => $data['tel'] ?? null,
            'tel2' => $data['tel2'] ?? null,
            'ext' => $data['ext'] ?? null,
            'firma' => $data['firma'] ?? null,
            'note_public' => $data['note_public'] ?? null,
            'note_private' => $data['note_private'] ?? null,
            'gender' => $data['gender'] ?? null,
            'adminfide' => $data['adminfide'] ?? null
        ]);
    }
    
    /**
     * Actualizar perfil de usuario
     */
    private function updatePerfil($userId, $data) {
        // Verificar si existe perfil
        $sql = "SELECT id FROM t_perfil WHERE fk_user = :fk_user";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['fk_user' => $userId]);
        
        if (!$stmt->fetch()) {
            // Si no existe, crear
            $this->createPerfil($userId, $data);
            return;
        }
        
        // Si existe, actualizar
        $sql = "
            UPDATE t_perfil SET
                civility = :civility,
                lastname = :lastname,
                firstname = :firstname,
                direccion = :direccion,
                zip = :zip,
                ciudad = :ciudad,
                pais = :pais,
                edo = :edo,
                birth = :birth,
                puesto = :puesto,
                tel = :tel,
                tel2 = :tel2,
                ext = :ext,
                firma = :firma,
                note_public = :note_public,
                note_private = :note_private,
                gender = :gender,
                adminfide = :adminfide
            WHERE fk_user = :fk_user
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'fk_user' => $userId,
            'civility' => $data['civility'] ?? null,
            'lastname' => $data['lastname'] ?? null,
            'firstname' => $data['firstname'] ?? null,
            'direccion' => $data['direccion'] ?? null,
            'zip' => $data['zip'] ?? null,
            'ciudad' => $data['ciudad'] ?? null,
            'pais' => $data['pais'] ?? 'México',
            'edo' => $data['edo'] ?? null,
            'birth' => $data['birth'] ?? null,
            'puesto' => $data['puesto'] ?? null,
            'tel' => $data['tel'] ?? null,
            'tel2' => $data['tel2'] ?? null,
            'ext' => $data['ext'] ?? null,
            'firma' => $data['firma'] ?? null,
            'note_public' => $data['note_public'] ?? null,
            'note_private' => $data['note_private'] ?? null,
            'gender' => $data['gender'] ?? null,
            'adminfide' => $data['adminfide'] ?? null
        ]);
    }
    
    // ========================================
    // GESTIÓN DE PERMISOS
    // ========================================
    
    /**
     * Obtener permisos directos del usuario
     */
    public function getUserPermissions($userId) {
        $sql = "
            SELECT rd.*
            FROM t_user_rights ur
            INNER JOIN t_rights_def rd ON ur.fk_id = rd.id
            WHERE ur.fk_user = :user_id
            ORDER BY rd.modulo_posicion, rd.modulo, rd.permiso
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Asignar permisos a usuario
     */
    public function assignPermissions($userId, $permissionIds) {
        try {
            $this->db->beginTransaction();
            
            // Eliminar permisos existentes
            $sql = "DELETE FROM t_user_rights WHERE fk_user = :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
            
            // Asignar nuevos permisos
            if (!empty($permissionIds)) {
                $sql = "INSERT INTO t_user_rights (fk_user, fk_id) VALUES (:user_id, :permission_id)";
                $stmt = $this->db->prepare($sql);
                
                foreach ($permissionIds as $permissionId) {
                    $stmt->execute([
                        'user_id' => $userId,
                        'permission_id' => $permissionId
                    ]);
                }
            }
            
            $this->addHistorial($userId, 'PERMISSIONS_UPDATE', 'Permisos actualizados', $permissionIds);
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Permisos asignados exitosamente'
            ];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error assigning permissions: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al asignar permisos'
            ];
        }
    }
    
    /**
     * Obtener todos los permisos disponibles agrupados por módulo
     */
    public function getAllPermissionsGrouped() {
        $sql = "
            SELECT *
            FROM t_rights_def
            ORDER BY modulo_posicion, modulo, permiso, subpermiso
        ";
        
        $stmt = $this->db->query($sql);
        $permisos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Agrupar por módulo
        $grouped = [];
        foreach ($permisos as $permiso) {
            $modulo = $permiso['modulo'];
            if (!isset($grouped[$modulo])) {
                $grouped[$modulo] = [];
            }
            $grouped[$modulo][] = $permiso;
        }
        
        return $grouped;
    }
    
    /**
     * Asignar permisos por defecto (solo dashboard)
     */
    private function assignDefaultPermissions($userId) {
        $sql = "
            INSERT INTO t_user_rights (fk_user, fk_id)
            SELECT :user_id, id
            FROM t_rights_def
            WHERE bydefault = 1
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
    }
    
    // ========================================
    // GESTIÓN DE GRUPOS
    // ========================================
    
    /**
     * Obtener grupos del usuario
     */
    public function getUserGroups($userId) {
        $sql = "
            SELECT g.id, g.nom, g.note
            FROM t_usergroup g
            INNER JOIN t_usergroup_user ugu ON g.id = ugu.fk_usergroup
            WHERE ugu.fk_user = :user_id
            ORDER BY g.nom
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtener todos los grupos disponibles
     */
    public function getAllGroups() {
        $sql = "SELECT * FROM t_usergroup ORDER BY nom";
        $stmt = $this->db->query($sql);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Asignar grupos a usuario
     */
    public function assignGroups($userId, $groupIds) {
        try {
            $this->db->beginTransaction();
            
            // Eliminar grupos existentes
            $sql = "DELETE FROM t_usergroup_user WHERE fk_user = :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
            
            // Asignar nuevos grupos
            if (!empty($groupIds)) {
                $sql = "INSERT INTO t_usergroup_user (fk_user, fk_usergroup, entity) VALUES (:user_id, :group_id, 1)";
                $stmt = $this->db->prepare($sql);
                
                foreach ($groupIds as $groupId) {
                    $stmt->execute([
                        'user_id' => $userId,
                        'group_id' => $groupId
                    ]);
                }
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Grupos asignados exitosamente'
            ];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error assigning groups: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al asignar grupos'
            ];
        }
    }
    
    // ========================================
    // VALIDACIONES Y UTILIDADES
    // ========================================
        /**
     * Agregar entrada al historial
     */
    private function addHistorial($userId, $accion, $descripcion, $datosAnteriores = null) {
        try {
            $sql = "
                INSERT INTO t_client_log (
                    fk_user, accion, descripcion,
                    datos_anteriores, usuario_id, fecha
                )
                VALUES (
                    :user_id, :accion, :descripcion,
                    :datos_anteriores, :usuario_id, NOW()
                )
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'user_id' => $userId,
                'accion' => $accion,
                'descripcion' => $descripcion,
                'datos_anteriores' => $datosAnteriores ? json_encode($datosAnteriores) : null,
                'usuario_id' => $_SESSION['user_id'] ?? 1
            ]);
            
        } catch (PDOException $e) {
            error_log("Error adding historial: " . $e->getMessage());
        }
    }

    /**
     * comparar cambios entre datos antiguos y nuevos
     */ 
      
         private function compareChanges($oldData, $newData) {
        $changes = [];
        
        $fieldsToCompare = [
            'name', 'email', 'email_verified_at', 'password', 'status'
        ];
        
        foreach ($fieldsToCompare as $field) {
            $old = $oldData[$field] ?? null;
            $new = $newData[$field] ?? null;
            
            if ($old != $new) {
                $changes[$field] = [
                    'old' => $old,
                    'new' => $new
                ];
            }
        }
        
        return $changes;
    }
    /**
     * Validar datos de usuario
     */
    private function validateUsuarioData($data, $excludeUserId = null) {
        $errors = [];
        
        // Validar nombre de usuario
        if (empty($data['name'])) {
            $errors[] = 'El nombre de usuario es requerido';
        }
        
        // Validar email
        if (empty($data['email'])) {
            $errors[] = 'El email es requerido';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'El email no es válido';
        }
        
        // Validar password (solo al crear)
        if ($excludeUserId === null && empty($data['password'])) {
            $errors[] = 'La contraseña es requerida';
        }
        
        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => implode(', ', $errors)
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Verificar si email existe
     */
    private function emailExists($email, $excludeUserId = null) {
        $sql = "SELECT id FROM users WHERE email = :email";
        
        if ($excludeUserId) {
            $sql .= " AND id != :exclude_id";
        }
        
        $stmt = $this->db->prepare($sql);
        $params = ['email' => $email];
        
        if ($excludeUserId) {
            $params['exclude_id'] = $excludeUserId;
        }
        
        $stmt->execute($params);
        
        return $stmt->fetch() !== false;
    }
    
    /**
     * Verificar si username existe
     */
    private function usernameExists($username, $excludeUserId = null) {
        $sql = "SELECT id FROM users WHERE name = :name";
        
        if ($excludeUserId) {
            $sql .= " AND id != :exclude_id";
        }
        
        $stmt = $this->db->prepare($sql);
        $params = ['name' => $username];
        
        if ($excludeUserId) {
            $params['exclude__id'] = $excludeUserId;
        }
        
        $stmt->execute($params);
        
        return $stmt->fetch() !== false;
    }
    
    /**
     * Generar API Key única
     */
    public function generateApiKey() {
        do {
            $apiKey = bin2hex(random_bytes(32));
            $sql = "SELECT id FROM users WHERE api_key = :api_key";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['api_key' => $apiKey]);
        } while ($stmt->fetch());
        
        return $apiKey;
    }
    
    /**
     * Actualizar API Key de usuario
     */
    public function updateApiKey($userId, $apiKey) {
        try {
            $sql = "UPDATE users SET api_key = :api_key, updated_at = NOW() WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'api_key' => $apiKey,
                'id' => $userId
            ]);
            
            return [
                'success' => true,
                'message' => 'API Key actualizada exitosamente',
                'api_key' => $apiKey
            ];
            
        } catch (PDOException $e) {
            error_log("Error updating API key: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al actualizar API Key'
            ];
        }
    }
    
    /**
     * Obtener lista de supervisores (usuarios con permisos admin o específicos)
     */
    public function getSupervisores() {
        $sql = "
            SELECT 
                u.id,
                u.name,
                CONCAT(p.firstname, ' ', p.lastname) as fullname
            FROM users u
            LEFT JOIN t_perfil p ON u.id = p.fk_user
            WHERE u.statut = 1 
            AND (u.admin = 1 OR u.empleado = 1)
            ORDER BY p.lastname, p.firstname
        ";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtener estadísticas de usuarios
     */
    public function getStats() {
        try {
            $stats = [];
            
            // Total de usuarios
            $sql = "SELECT COUNT(*) as total FROM users WHERE statut >= 0";
            $stmt = $this->db->query($sql);
            $stats['total'] = $stmt->fetch()['total'];
            
            // Usuarios activos
            $sql = "SELECT COUNT(*) as total FROM users WHERE statut = 1";
            $stmt = $this->db->query($sql);
            $stats['activos'] = $stmt->fetch()['total'];
            
            // Usuarios inactivos
            $sql = "SELECT COUNT(*) as total FROM users WHERE statut = 0";
            $stmt = $this->db->query($sql);
            $stats['inactivos'] = $stmt->fetch()['total'];
            
            // Administradores
            $sql = "SELECT COUNT(*) as total FROM users WHERE admin = 1 AND statut >= 0";
            $stmt = $this->db->query($sql);
            $stats['administradores'] = $stmt->fetch()['total'];
            
            // Empleados
            $sql = "SELECT COUNT(*) as total FROM users WHERE empleado = 1 AND statut >= 0";
            $stmt = $this->db->query($sql);
            $stats['empleados'] = $stmt->fetch()['total'];
            
            // Usuarios sin permisos asignados
            $sql = "
                SELECT COUNT(*) as total 
                FROM users u
                WHERE u.statut >= 0 
                AND u.admin = 0
                AND NOT EXISTS (
                    SELECT 1 FROM t_user_rights WHERE fk_user = u.id
                )
                AND NOT EXISTS (
                    SELECT 1 FROM t_usergroup_user WHERE fk_user = u.id
                )
            ";
            $stmt = $this->db->query($sql);
            $stats['sin_permisos'] = $stmt->fetch()['total'];
            
            // Usuarios creados este mes
            $sql = "
                SELECT COUNT(*) as total 
                FROM users 
                WHERE statut >= 0 
                AND DATE_TRUNC('month', created_at) = DATE_TRUNC('month', CURRENT_DATE)
            ";
            $stmt = $this->db->query($sql);
            $stats['creados_mes'] = $stmt->fetch()['total'];
            
            return $stats;
            
        } catch (PDOException $e) {
            error_log("Error getting stats: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener historial de últimos logins de un usuario
     */
    public function getLoginHistory($userId, $limit = 10) {
        try {
            $sql = "
                SELECT 
                    datelastlogin,
                    dateprevioslogin
                FROM t_perfil
                WHERE fk_user = :user_id
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $history = [];
            
            if ($result) {
                if ($result['datelastlogin']) {
                    $history[] = [
                        'date' => $result['datelastlogin'],
                        'label' => 'Último login'
                    ];
                }
                
                if ($result['dateprevioslogin']) {
                    $history[] = [
                        'date' => $result['dateprevioslogin'],
                        'label' => 'Login anterior'
                    ];
                }
            }
            
            return $history;
            
        } catch (PDOException $e) {
            error_log("Error getting login history: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Verificar si un usuario puede ser editado por otro
     */
    public function canEdit($editorUserId, $targetUserId, $isEditorAdmin = false) {
        // Admin puede editar a todos excepto a sí mismo en ciertos casos
        if ($isEditorAdmin) {
            return true;
        }
        
        // No admin solo puede editarse a sí mismo
        return $editorUserId == $targetUserId;
    }
    
    /**
     * Verificar si un usuario puede ser eliminado
     */
    public function canDelete($userId, $isAdmin = false) {
        // Usuario ID 1 no se puede eliminar
        if ($userId == 1) {
            return false;
        }
        
        // Solo admin puede eliminar
        return $isAdmin;
    }
    
    /**
     * Exportar usuarios a CSV
     */
    public function exportToCSV($filters = []) {
        try {
            $result = $this->getUsuarios($filters, 1, 10000); // Sin paginación
            
            if (!$result['success']) {
                return $result;
            }
            
            $usuarios = $result['data'];
            
            // Crear CSV en memoria
            $output = fopen('php://temp', 'r+');
            
            // Encabezados
            fputcsv($output, [
                'ID',
                'Usuario',
                'Email',
                'Nombre',
                'Apellido',
                'Puesto',
                'Admin',
                'Empleado',
                'Estado',
                'Fecha Creación',
                'Último Login',
                'Permisos Directos',
                'Grupos'
            ]);
            
            // Datos
            foreach ($usuarios as $usuario) {
                fputcsv($output, [
                    $usuario['id'],
                    $usuario['name'],
                    $usuario['email'],
                    $usuario['firstname'] ?? '',
                    $usuario['lastname'] ?? '',
                    $usuario['puesto'] ?? '',
                    $usuario['admin'] == 1 ? 'Sí' : 'No',
                    $usuario['empleado'] == 1 ? 'Sí' : 'No',
                    $usuario['statut'] == 1 ? 'Activo' : 'Inactivo',
                    $usuario['created_at'],
                    $usuario['datelastlogin'] ?? 'Nunca',
                    $usuario['permisos_directos'],
                    $usuario['grupos']
                ]);
            }
            
            rewind($output);
            $csv = stream_get_contents($output);
            fclose($output);
            
            return [
                'success' => true,
                'data' => $csv
            ];
            
        } catch (Exception $e) {
            error_log("Error exporting to CSV: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al exportar a CSV'
            ];
        }
    }
    
    /**
     * Búsqueda rápida de usuarios (para autocompletar)
     */
    public function quickSearch($query, $limit = 10) {
        try {
            $sql = "
                SELECT 
                    u.id,
                    u.name,
                    u.email,
                    CONCAT(p.firstname, ' ', p.lastname) as fullname,
                    u.statut
                FROM users u
                LEFT JOIN t_perfil p ON u.id = p.fk_user
                WHERE (
                    u.name ILIKE :query 
                    OR u.email ILIKE :query
                    OR p.firstname ILIKE :query
                    OR p.lastname ILIKE :query
                )
                AND u.statut >= 0
                ORDER BY u.statut DESC, u.name
                LIMIT :limit
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':query', '%' . $query . '%', PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return [
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];
            
        } catch (PDOException $e) {
            error_log("Error in quick search: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error en la búsqueda'
            ];
        }
    }
    
    /**
     * Resetear contraseña de usuario (generar nueva aleatoria)
     */
    public function resetPassword($userId) {
        try {
            // Generar contraseña aleatoria
            $newPassword = $this->generateRandomPassword();
            
            $sql = "UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'password' => password_hash($newPassword, PASSWORD_DEFAULT),
                'id' => $userId
            ]);
            
            return [
                'success' => true,
                'message' => 'Contraseña reseteada exitosamente',
                'new_password' => $newPassword
            ];
            
        } catch (PDOException $e) {
            error_log("Error resetting password: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al resetear contraseña'
            ];
        }
    }
    
    /**
     * Generar contraseña aleatoria
     */
    private function generateRandomPassword($length = 12) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        return $password;
    }
    
    /**
     * Obtener actividad reciente de usuarios
     */
    public function getRecentActivity($limit = 10) {
        try {
            $sql = "
                SELECT 
                    u.id,
                    u.name,
                    u.email,
                    CONCAT(p.firstname, ' ', p.lastname) as fullname,
                    p.datelastlogin,
                    u.created_at,
                    u.updated_at
                FROM users u
                LEFT JOIN t_perfil p ON u.id = p.fk_user
                WHERE u.statut >= 0
                ORDER BY GREATEST(u.updated_at, COALESCE(p.datelastlogin, u.created_at)) DESC
                LIMIT :limit
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return [
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];
            
        } catch (PDOException $e) {
            error_log("Error getting recent activity: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al obtener actividad reciente'
            ];
        }
    }
}
?>