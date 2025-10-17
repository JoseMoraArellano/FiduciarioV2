<?php
/**
 * Clase GruposManager - Gestión completa de grupos de usuarios
 * Maneja: CRUD de grupos, permisos de grupos, miembros
 */
class GruposManager {
    
    private $db;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    // ========================================
    // CRUD DE GRUPOS
    // ========================================
    
    /**
     * Obtener todos los grupos con filtros
     * @param array $filters - Filtros opcionales (search)
     * @param int $page - Página actual
     * @param int $perPage - Items por página
     * @return array
     */
    public function getGrupos($filters = [], $page = 1, $perPage = 20) {
        try {
            $where = ['1=1'];
            $params = [];
            
            // Filtro de búsqueda
            if (!empty($filters['search'])) {
                $where[] = "(g.nom ILIKE :search OR g.note ILIKE :search)";
                $params['search'] = '%' . $filters['search'] . '%';
            }
            
            $whereClause = implode(' AND ', $where);
            
            // Contar total
            $sqlCount = "
                SELECT COUNT(*) as total
                FROM t_usergroup g
                WHERE {$whereClause}
            ";
            
            $stmtCount = $this->db->prepare($sqlCount);
            $stmtCount->execute($params);
            $total = $stmtCount->fetch()['total'];
            
            // Obtener registros paginados
            $offset = ($page - 1) * $perPage;
            
            $sql = "
                SELECT 
                    g.id,
                    g.nom,
                    g.note,
                    g.datec,
                    (SELECT COUNT(*) FROM t_usergroup_user WHERE fk_usergroup = g.id) as total_usuarios,
                    (SELECT COUNT(*) FROM t_usergroup_rights WHERE fk_usergroup = g.id) as total_permisos
                FROM t_usergroup g
                WHERE {$whereClause}
                ORDER BY g.nom ASC
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
            $grupos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'data' => $grupos,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage)
            ];
            
        } catch (PDOException $e) {
            error_log("Error getting grupos: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al obtener grupos'
            ];
        }
    }
    
    /**
     * Obtener un grupo por ID con toda su información
     */
    public function getGrupo($id) {
        try {
            $sql = "
                SELECT 
                    g.*,
                    (SELECT COUNT(*) FROM t_usergroup_user WHERE fk_usergroup = g.id) as total_usuarios,
                    (SELECT COUNT(*) FROM t_usergroup_rights WHERE fk_usergroup = g.id) as total_permisos
                FROM t_usergroup g
                WHERE g.id = :id
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);
            
            $grupo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$grupo) {
                return [
                    'success' => false,
                    'message' => 'Grupo no encontrado'
                ];
            }
            
            // Obtener usuarios del grupo
            $grupo['usuarios'] = $this->getGrupoUsuarios($id);
            
            // Obtener permisos del grupo
            $grupo['permisos'] = $this->getGrupoPermisos($id);
            
            return [
                'success' => true,
                'data' => $grupo
            ];
            
        } catch (PDOException $e) {
            error_log("Error getting grupo: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al obtener grupo'
            ];
        }
    }
    
    /**
     * Crear nuevo grupo
     */
    public function createGrupo($data) {
        try {
            // Validar datos
            $validation = $this->validateGrupoData($data);
            if (!$validation['success']) {
                return $validation;
            }
            
            // Verificar que el nombre sea único
            if ($this->nombreExists($data['nom'])) {
                return [
                    'success' => false,
                    'message' => 'Ya existe un grupo con ese nombre'
                ];
            }
            
            // Crear grupo
            $sql = "
                INSERT INTO t_usergroup (nom, note, datec)
                VALUES (:nom, :note, NOW())
                RETURNING id
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'nom' => $data['nom'],
                'note' => $data['note'] ?? null
            ]);
            
            $grupoId = $stmt->fetch()['id'];
            
            return [
                'success' => true,
                'message' => 'Grupo creado exitosamente',
                'grupo_id' => $grupoId
            ];
            
        } catch (PDOException $e) {
            error_log("Error creating grupo: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al crear grupo'
            ];
        }
    }
    
    /**
     * Actualizar grupo existente
     */
    public function updateGrupo($id, $data) {
        try {
            // Validar datos
            $validation = $this->validateGrupoData($data, $id);
            if (!$validation['success']) {
                return $validation;
            }
            
            // Verificar nombre único (excepto el mismo grupo)
            if ($this->nombreExists($data['nom'], $id)) {
                return [
                    'success' => false,
                    'message' => 'Ya existe otro grupo con ese nombre'
                ];
            }
            
            // Actualizar grupo
            $sql = "
                UPDATE t_usergroup 
                SET nom = :nom,
                    note = :note
                WHERE id = :id
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'nom' => $data['nom'],
                'note' => $data['note'] ?? null,
                'id' => $id
            ]);
            
            return [
                'success' => true,
                'message' => 'Grupo actualizado exitosamente'
            ];
            
        } catch (PDOException $e) {
            error_log("Error updating grupo: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al actualizar grupo'
            ];
        }
    }
    
    /**
     * Eliminar grupo
     */
    public function deleteGrupo($id) {
        try {
            // Verificar si tiene usuarios asignados
            $sql = "SELECT COUNT(*) as total FROM t_usergroup_user WHERE fk_usergroup = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);
            $totalUsuarios = $stmt->fetch()['total'];
            
            if ($totalUsuarios > 0) {
                return [
                    'success' => false,
                    'message' => "No se puede eliminar. El grupo tiene {$totalUsuarios} usuario(s) asignado(s)"
                ];
            }
            
            $this->db->beginTransaction();
            
            // Eliminar permisos del grupo
            $sql = "DELETE FROM t_usergroup_rights WHERE fk_usergroup = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);
            
            // Eliminar grupo
            $sql = "DELETE FROM t_usergroup WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Grupo eliminado exitosamente'
            ];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error deleting grupo: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al eliminar grupo'
            ];
        }
    }
    
    /**
     * Duplicar grupo (copiar permisos)
     */
    public function duplicateGrupo($id) {
        try {
            $this->db->beginTransaction();
            
            // Obtener grupo original
            $result = $this->getGrupo($id);
            if (!$result['success']) {
                return $result;
            }
            
            $original = $result['data'];
            
            // Crear nuevo nombre único
            $newNom = $original['nom'] . ' (Copia)';
            $counter = 1;
            while ($this->nombreExists($newNom)) {
                $newNom = $original['nom'] . ' (Copia ' . $counter . ')';
                $counter++;
            }
            
            // Crear nuevo grupo
            $sql = "
                INSERT INTO t_usergroup (nom, note, datec)
                VALUES (:nom, :note, NOW())
                RETURNING id
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'nom' => $newNom,
                'note' => $original['note']
            ]);
            
            $newGrupoId = $stmt->fetch()['id'];
            
            // Copiar permisos
            $sql = "
                INSERT INTO t_usergroup_rights (fk_usergroup, fk_id)
                SELECT :new_grupo_id, fk_id
                FROM t_usergroup_rights
                WHERE fk_usergroup = :original_grupo_id
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'new_grupo_id' => $newGrupoId,
                'original_grupo_id' => $id
            ]);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Grupo duplicado exitosamente',
                'new_grupo_id' => $newGrupoId,
                'new_nombre' => $newNom
            ];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error duplicating grupo: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al duplicar grupo'
            ];
        }
    }
    
    // ========================================
    // GESTIÓN DE PERMISOS DEL GRUPO
    // ========================================
    
    /**
     * Obtener permisos del grupo
     */
    public function getGrupoPermisos($grupoId) {
        try {
            $sql = "
                SELECT rd.*
                FROM t_usergroup_rights ugr
                INNER JOIN t_rights_def rd ON ugr.fk_id = rd.id
                WHERE ugr.fk_usergroup = :grupo_id
                ORDER BY rd.modulo_posicion, rd.modulo, rd.permiso
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['grupo_id' => $grupoId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error getting grupo permisos: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Asignar permisos a grupo
     */
    public function assignPermissions($grupoId, $permissionIds) {
        try {
            $this->db->beginTransaction();
            
            // Eliminar permisos existentes
            $sql = "DELETE FROM t_usergroup_rights WHERE fk_usergroup = :grupo_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['grupo_id' => $grupoId]);
            
            // Asignar nuevos permisos
            if (!empty($permissionIds)) {
                $sql = "INSERT INTO t_usergroup_rights (fk_usergroup, fk_id) VALUES (:grupo_id, :permission_id)";
                $stmt = $this->db->prepare($sql);
                
                foreach ($permissionIds as $permissionId) {
                    $stmt->execute([
                        'grupo_id' => $grupoId,
                        'permission_id' => $permissionId
                    ]);
                }
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Permisos asignados exitosamente'
            ];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error assigning permissions to grupo: " . $e->getMessage());
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
        try {
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
            
        } catch (PDOException $e) {
            error_log("Error getting all permissions: " . $e->getMessage());
            return [];
        }
    }
    
    // ========================================
    // GESTIÓN DE USUARIOS DEL GRUPO
    // ========================================
    
    /**
     * Obtener usuarios del grupo
     */
    public function getGrupoUsuarios($grupoId) {
        try {
            $sql = "
                SELECT 
                    u.id,
                    u.name,
                    u.email,
                    u.statut,
                    u.admin,
                    p.firstname,
                    p.lastname,
                    p.puesto
                FROM t_usergroup_user ugu
                INNER JOIN users u ON ugu.fk_user = u.id
                LEFT JOIN t_perfil p ON u.id = p.fk_user
                WHERE ugu.fk_usergroup = :grupo_id
                ORDER BY p.lastname, p.firstname, u.name
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['grupo_id' => $grupoId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error getting grupo usuarios: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Asignar usuarios al grupo
     */
    public function assignUsuarios($grupoId, $usuarioIds) {
        try {
            $this->db->beginTransaction();
            
            // Eliminar usuarios existentes
            $sql = "DELETE FROM t_usergroup_user WHERE fk_usergroup = :grupo_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['grupo_id' => $grupoId]);
            
            // Asignar nuevos usuarios
            if (!empty($usuarioIds)) {
                $sql = "INSERT INTO t_usergroup_user (fk_usergroup, fk_user, entity) VALUES (:grupo_id, :user_id, 1)";
                $stmt = $this->db->prepare($sql);
                
                foreach ($usuarioIds as $userId) {
                    $stmt->execute([
                        'grupo_id' => $grupoId,
                        'user_id' => $userId
                    ]);
                }
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Usuarios asignados exitosamente'
            ];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error assigning usuarios to grupo: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al asignar usuarios'
            ];
        }
    }
    
    /**
     * Remover usuario del grupo
     */
    public function removeUsuario($grupoId, $userId) {
        try {
            $sql = "DELETE FROM t_usergroup_user WHERE fk_usergroup = :grupo_id AND fk_user = :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'grupo_id' => $grupoId,
                'user_id' => $userId
            ]);
            
            return [
                'success' => true,
                'message' => 'Usuario removido del grupo'
            ];
            
        } catch (PDOException $e) {
            error_log("Error removing usuario from grupo: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al remover usuario'
            ];
        }
    }
    
    /**
     * Obtener todos los usuarios disponibles para asignar
     */
    public function getAllUsuarios() {
        try {
            $sql = "
                SELECT 
                    u.id,
                    u.name,
                    u.email,
                    u.statut,
                    u.admin,
                    p.firstname,
                    p.lastname
                FROM users u
                LEFT JOIN t_perfil p ON u.id = p.fk_user
                WHERE u.statut >= 0
                ORDER BY p.lastname, p.firstname, u.name
            ";
            
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error getting all usuarios: " . $e->getMessage());
            return [];
        }
    }
    
    // ========================================
    // VALIDACIONES Y UTILIDADES
    // ========================================
    
    /**
     * Validar datos de grupo
     */
    private function validateGrupoData($data, $excludeId = null) {
        $errors = [];
        
        // Validar nombre
        if (empty($data['nom'])) {
            $errors[] = 'El nombre del grupo es requerido';
        } elseif (strlen($data['nom']) < 3) {
            $errors[] = 'El nombre debe tener al menos 3 caracteres';
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
     * Verificar si nombre de grupo existe
     */
    private function nombreExists($nombre, $excludeId = null) {
        $sql = "SELECT id FROM t_usergroup WHERE nom = :nom";
        
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
        }
        
        $stmt = $this->db->prepare($sql);
        $params = ['nom' => $nombre];
        
        if ($excludeId) {
            $params['exclude_id'] = $excludeId;
        }
        
        $stmt->execute($params);
        
        return $stmt->fetch() !== false;
    }
    
    /**
     * Obtener estadísticas de grupos
     */
    public function getStats() {
        try {
            $stats = [];
            
            // Total de grupos
            $sql = "SELECT COUNT(*) as total FROM t_usergroup";
            $stmt = $this->db->query($sql);
            $stats['total'] = $stmt->fetch()['total'];
            
            // Grupos con usuarios
            $sql = "SELECT COUNT(DISTINCT fk_usergroup) as total FROM t_usergroup_user";
            $stmt = $this->db->query($sql);
            $stats['con_usuarios'] = $stmt->fetch()['total'];
            
            // Grupos sin usuarios
            $stats['sin_usuarios'] = $stats['total'] - $stats['con_usuarios'];
            
            // Grupos con permisos
            $sql = "SELECT COUNT(DISTINCT fk_usergroup) as total FROM t_usergroup_rights";
            $stmt = $this->db->query($sql);
            $stats['con_permisos'] = $stmt->fetch()['total'];
            
            // Promedio de usuarios por grupo
            $sql = "
                SELECT AVG(cnt) as promedio
                FROM (
                    SELECT COUNT(*) as cnt 
                    FROM t_usergroup_user 
                    GROUP BY fk_usergroup
                ) as subq
            ";
            $stmt = $this->db->query($sql);
            $result = $stmt->fetch();
            $stats['promedio_usuarios'] = $result['promedio'] ? round($result['promedio'], 1) : 0;
            
            return $stats;
            
        } catch (PDOException $e) {
            error_log("Error getting grupo stats: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Exportar grupos a CSV
     */
    public function exportToCSV($filters = []) {
        try {
            $result = $this->getGrupos($filters, 1, 10000);
            
            if (!$result['success']) {
                return $result;
            }
            
            $grupos = $result['data'];
            
            // Crear CSV en memoria
            $output = fopen('php://temp', 'r+');
            
            // Encabezados
            fputcsv($output, [
                'ID',
                'Nombre',
                'Descripción',
                'Total Usuarios',
                'Total Permisos',
                'Fecha Creación'
            ]);
            
            // Datos
            foreach ($grupos as $grupo) {
                fputcsv($output, [
                    $grupo['id'],
                    $grupo['nom'],
                    $grupo['note'] ?? '',
                    $grupo['total_usuarios'],
                    $grupo['total_permisos'],
                    $grupo['datec']
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
            error_log("Error exporting grupos to CSV: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al exportar a CSV'
            ];
        }
    }
}
?>