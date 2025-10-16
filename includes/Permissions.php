<?php
/**
 * Clase Permissions - Manejo de permisos de usuarios
 * Combina permisos individuales y de grupos según modelo Dolibarr
 */
class Permissions {
    
    private $db;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Obtiene TODOS los permisos de un usuario (individuales + grupos)
     * Retorna array con estructura: [modulo, permiso, subpermiso, type, descripcion]
     */
    public function getUserPermissions($userId) {
        try {
            $sql = "
                -- Permisos individuales del usuario
                SELECT DISTINCT 
                    rd.id,
                    rd.modulo,
                    rd.permiso,
                    rd.subpermiso,
                    rd.type,
                    rd.descripcion
                FROM t_user_rights ur
                INNER JOIN t_rights_def rd ON ur.fk_id = rd.id
                WHERE ur.fk_user = :user_id
                
                UNION
                
                -- Permisos heredados de grupos
                SELECT DISTINCT 
                    rd.id,
                    rd.modulo,
                    rd.permiso,
                    rd.subpermiso,
                    rd.type,
                    rd.descripcion
                FROM t_usergroup_user ugu
                INNER JOIN t_usergroup_rights ugr ON ugu.fk_usergroup = ugr.fk_usergroup
                INNER JOIN t_rights_def rd ON ugr.fk_id = rd.id
                WHERE ugu.fk_user = :user_id
                
                ORDER BY modulo, permiso, subpermiso
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error loading user permissions: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Verifica si un usuario tiene un permiso específico
     * @param int $userId
     * @param string $modulo
     * @param string $permiso
     * @param string|null $subpermiso
     * @return bool
     */
    public function hasPermission($userId, $modulo, $permiso, $subpermiso = null) {
        try {
            if ($subpermiso === null) {
                $sql = "
                    SELECT COUNT(*) as total
                    FROM (
                        -- Permisos individuales
                        SELECT rd.id
                        FROM t_user_rights ur
                        INNER JOIN t_rights_def rd ON ur.fk_id = rd.id
                        WHERE ur.fk_user = :user_id 
                        AND rd.modulo = :modulo 
                        AND rd.permiso = :permiso
                        
                        UNION
                        
                        -- Permisos de grupos
                        SELECT rd.id
                        FROM t_usergroup_user ugu
                        INNER JOIN t_usergroup_rights ugr ON ugu.fk_usergroup = ugr.fk_usergroup
                        INNER JOIN t_rights_def rd ON ugr.fk_id = rd.id
                        WHERE ugu.fk_user = :user_id 
                        AND rd.modulo = :modulo 
                        AND rd.permiso = :permiso
                    ) AS combined_permissions
                ";
                
                $params = [
                    'user_id' => $userId,
                    'modulo' => $modulo,
                    'permiso' => $permiso
                ];
            } else {
                $sql = "
                    SELECT COUNT(*) as total
                    FROM (
                        -- Permisos individuales
                        SELECT rd.id
                        FROM t_user_rights ur
                        INNER JOIN t_rights_def rd ON ur.fk_id = rd.id
                        WHERE ur.fk_user = :user_id 
                        AND rd.modulo = :modulo 
                        AND rd.permiso = :permiso
                        AND rd.subpermiso = :subpermiso
                        
                        UNION
                        
                        -- Permisos de grupos
                        SELECT rd.id
                        FROM t_usergroup_user ugu
                        INNER JOIN t_usergroup_rights ugr ON ugu.fk_usergroup = ugr.fk_usergroup
                        INNER JOIN t_rights_def rd ON ugr.fk_id = rd.id
                        WHERE ugu.fk_user = :user_id 
                        AND rd.modulo = :modulo 
                        AND rd.permiso = :permiso
                        AND rd.subpermiso = :subpermiso
                    ) AS combined_permissions
                ";
                
                $params = [
                    'user_id' => $userId,
                    'modulo' => $modulo,
                    'permiso' => $permiso,
                    'subpermiso' => $subpermiso
                ];
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            $result = $stmt->fetch();
            return ($result['total'] > 0);
            
        } catch (PDOException $e) {
            error_log("Error checking permission: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtiene el perfil completo del usuario
     */
    public function getUserPerfil($userId) {
        try {
            $sql = "SELECT * FROM t_perfil WHERE fk_user = :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
            
            $perfil = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Si no existe perfil, retorna array vacío
            return $perfil ?: [];
            
        } catch (PDOException $e) {
            error_log("Error loading user profile: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtiene los grupos a los que pertenece un usuario
     */
    public function getUserGroups($userId) {
        try {
            $sql = "
                SELECT 
                    ug.id,
                    ug.nom as nombre,
                    ug.note
                FROM t_usergroup ug
                INNER JOIN t_usergroup_user ugu ON ug.id = ugu.fk_usergroup
                WHERE ugu.fk_user = :user_id
                ORDER BY ug.nom
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error loading user groups: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtiene todos los permisos disponibles en el sistema
     * Útil para pantallas de administración
     */
    public function getAllPermissions() {
        try {
            $sql = "
                SELECT 
                    id,
                    descripcion,
                    modulo,
                    modulo_posicion,
                    permiso,
                    subpermiso,
                    type,
                    bydefault
                FROM t_rights_def
                ORDER BY modulo_posicion, modulo, permiso, subpermiso
            ";
            
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error loading all permissions: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Asigna un permiso individual a un usuario
     */
    public function assignPermissionToUser($userId, $rightId) {
        try {
            $sql = "
                INSERT INTO t_user_rights (fk_user, fk_id)
                VALUES (:user_id, :right_id)
                ON CONFLICT DO NOTHING
            ";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'user_id' => $userId,
                'right_id' => $rightId
            ]);
            
        } catch (PDOException $e) {
            error_log("Error assigning permission to user: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Remueve un permiso individual de un usuario
     */
    public function removePermissionFromUser($userId, $rightId) {
        try {
            $sql = "DELETE FROM t_user_rights WHERE fk_user = :user_id AND fk_id = :right_id";
            $stmt = $this->db->prepare($sql);
            
            return $stmt->execute([
                'user_id' => $userId,
                'right_id' => $rightId
            ]);
            
        } catch (PDOException $e) {
            error_log("Error removing permission from user: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Asigna un usuario a un grupo
     */
    public function assignUserToGroup($userId, $groupId, $entity = 1) {
        try {
            $sql = "
                INSERT INTO t_usergroup_user (fk_user, fk_usergroup, entity)
                VALUES (:user_id, :group_id, :entity)
                ON CONFLICT DO NOTHING
            ";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'user_id' => $userId,
                'group_id' => $groupId,
                'entity' => $entity
            ]);
            
        } catch (PDOException $e) {
            error_log("Error assigning user to group: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Remueve un usuario de un grupo
     */
    public function removeUserFromGroup($userId, $groupId) {
        try {
            $sql = "DELETE FROM t_usergroup_user WHERE fk_user = :user_id AND fk_usergroup = :group_id";
            $stmt = $this->db->prepare($sql);
            
            return $stmt->execute([
                'user_id' => $userId,
                'group_id' => $groupId
            ]);
            
        } catch (PDOException $e) {
            error_log("Error removing user from group: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtiene permisos por módulo (útil para construir menús dinámicos)
     */
    public function getPermissionsByModule($userId) {
        try {
            $sql = "
                SELECT DISTINCT 
                    rd.modulo,
                    rd.modulo_posicion,
                    array_agg(
                        json_build_object(
                            'permiso', rd.permiso,
                            'subpermiso', rd.subpermiso,
                            'type', rd.type,
                            'descripcion', rd.descripcion
                        )
                    ) as permisos
                FROM (
                    -- Permisos individuales
                    SELECT rd.*
                    FROM t_user_rights ur
                    INNER JOIN t_rights_def rd ON ur.fk_id = rd.id
                    WHERE ur.fk_user = :user_id
                    
                    UNION
                    
                    -- Permisos de grupos
                    SELECT rd.*
                    FROM t_usergroup_user ugu
                    INNER JOIN t_usergroup_rights ugr ON ugu.fk_usergroup = ugr.fk_usergroup
                    INNER JOIN t_rights_def rd ON ugr.fk_id = rd.id
                    WHERE ugu.fk_user = :user_id
                ) rd
                GROUP BY rd.modulo, rd.modulo_posicion
                ORDER BY rd.modulo_posicion, rd.modulo
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error loading permissions by module: " . $e->getMessage());
            return [];
        }
    }
}
?>