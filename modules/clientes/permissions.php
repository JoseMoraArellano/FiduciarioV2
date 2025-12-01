<?php
/**
 * Definición de permisos para el módulo de Clientes
 * Este archivo define los permisos disponibles y verifica los permisos del usuario actual
 */
require_once 'includes/ClienteManager.php';

$clientManager = new ClienteManager();

$clienteId=isset($_GET['id'])? (int)$_GET['id'] : 0;

// Definir los permisos del módulo
$CLIENTE_PERMISSIONS = [
    'view' => [
        'id' => 'clientes_lire',
        'name' => 'Ver Clientes',
        'description' => 'Permite ver la lista y detalles de clientes',
        'sql_check' => "SELECT 1 FROM t_user_rights ur 
                       INNER JOIN t_rights_def rd ON ur.fk_id = rd.id 
                       WHERE ur.fk_user = :user_id 
                       AND rd.modulo = 'catalogos' 
                       AND rd.permiso = 'lire' 
                       AND rd.subpermiso = 'clientes'"
    ],
    'create' => [
        'id' => 'clientes_creer',
        'name' => 'Crear Clientes',
        'description' => 'Permite crear nuevos clientes',
        'sql_check' => "SELECT 1 FROM t_user_rights ur 
                       INNER JOIN t_rights_def rd ON ur.fk_id = rd.id 
                       WHERE ur.fk_user = :user_id 
                       AND rd.modulo = 'catalogos' 
                       AND rd.permiso = 'creer' 
                       AND rd.subpermiso = 'clientes'"
    ],
    'edit' => [
        'id' => 'clientes_modifier',
        'name' => 'Modificar Clientes',
        'description' => 'Permite editar clientes existentes',
        'sql_check' => "SELECT 1 FROM t_user_rights ur 
                       INNER JOIN t_rights_def rd ON ur.fk_id = rd.id 
                       WHERE ur.fk_user = :user_id 
                       AND rd.modulo = 'catalogos' 
                       AND rd.permiso = 'modifier' 
                       AND rd.subpermiso = 'clientes'"
    ],
    'delete' => [
        'id' => 'clientes_supprimer',
        'name' => 'Eliminar Clientes',
        'description' => 'Permite eliminar clientes',
        'sql_check' => "SELECT 1 FROM t_user_rights ur 
                       INNER JOIN t_rights_def rd ON ur.fk_id = rd.id 
                       WHERE ur.fk_user = :user_id 
                       AND rd.modulo = 'catalogos' 
                       AND rd.permiso = 'supprimer' 
                       AND rd.subpermiso = 'clientes'"
    ],
    'export' => [
        'id' => 'clientes_export',
        'name' => 'Exportar Clientes',
        'description' => 'Permite exportar datos de clientes a Excel',
        'sql_check' => "SELECT 1 FROM t_user_rights ur 
                       INNER JOIN t_rights_def rd ON ur.fk_id = rd.id 
                       WHERE ur.fk_user = :user_id 
                       AND rd.modulo = 'catalogos' 
                       AND rd.permiso = 'export' 
                       AND rd.subpermiso = 'clientes'"
    ],
    'import' => [
        'id' => 'clientes_import',
        'name' => 'Importar Clientes',
        'description' => 'Permite importar datos de clientes desde archivos',
        'sql_check' => "SELECT 1 FROM t_user_rights ur 
                       INNER JOIN t_rights_def rd ON ur.fk_id = rd.id 
                       WHERE ur.fk_user = :user_id 
                       AND rd.modulo = 'catalogos' 
                       AND rd.permiso = 'import' 
                       AND rd.subpermiso = 'clientes'"
    ],
    'documents' => [
        'id' => 'clientes_documents',
        'name' => 'Gestionar Documentos',
        'description' => 'Permite subir, ver y eliminar documentos de clientes',
        'sql_check' => "SELECT 1 FROM t_user_rights ur 
                       INNER JOIN t_rights_def rd ON ur.fk_id = rd.id 
                       WHERE ur.fk_user = :user_id 
                       AND rd.modulo = 'catalogos' 
                       AND rd.permiso = 'documents' 
                       AND rd.subpermiso = 'clientes'"
    ],
    'history' => [
        'id' => 'clientes_history',
        'name' => 'Ver Historial',
        'description' => 'Permite ver el historial de cambios de clientes',
        'sql_check' => "SELECT 1 FROM t_user_rights ur 
                       INNER JOIN t_rights_def rd ON ur.fk_id = rd.id 
                       WHERE ur.fk_user = :user_id 
                       AND rd.modulo = 'catalogos' 
                       AND rd.permiso = 'history' 
                       AND rd.subpermiso = 'clientes'"
    ],
    'verify_qsq' => [
        'id' => 'clientes_verify_qsq',
        'name' => 'Verificar QSQ',
        'description' => 'Permite ejecutar verificación QSQ en clientes',
        'sql_check' => "SELECT 1 FROM t_user_rights ur 
                       INNER JOIN t_rights_def rd ON ur.fk_id = rd.id 
                       WHERE ur.fk_user = :user_id 
                       AND rd.modulo = 'catalogos' 
                       AND rd.permiso = 'verify_qsq' 
                       AND rd.subpermiso = 'clientes'"
    ]
];

/**
 * Clase para gestionar permisos de clientes
 */
class ClientePermissions {
    
    private $db;
    private $userId;
    private $isAdmin;
    private $permissions = [];
    
    public function __construct($userId = null) {
        $this->db = Database::getInstance()->getConnection();
        $this->userId = $userId ?? ($_SESSION['user_id'] ?? 0);
        $this->loadUserPermissions();
    }
    
    /**
     * Cargar permisos del usuario actual
     */
    private function loadUserPermissions() {
        // Verificar si es administrador
        $sql = "SELECT admin FROM users WHERE id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $this->userId]);
        $user = $stmt->fetch();
        
        $this->isAdmin = ($user && $user['admin'] == 1);
        
        // Si es admin, tiene todos los permisos
        if ($this->isAdmin) {
            $this->permissions = [
                'view' => true,
                'create' => true,
                'edit' => true,
                'delete' => true,
                'export' => true,
                'import' => true,
                'documents' => true,
                'history' => true,
                'verify_qsq' => true
            ];
            return;
        }
        
        // Cargar permisos específicos del usuario
        global $CLIENTE_PERMISSIONS;
        
        foreach ($CLIENTE_PERMISSIONS as $key => $perm) {
            $stmt = $this->db->prepare($perm['sql_check']);
            $stmt->execute(['user_id' => $this->userId]);
            $this->permissions[$key] = ($stmt->fetch() !== false);
        }
        
        // También verificar permisos por grupos
        $this->loadGroupPermissions();
    }
    
    /**
     * Cargar permisos heredados de grupos
     */
    private function loadGroupPermissions() {
        $sql = "
            SELECT DISTINCT rd.permiso, rd.subpermiso, rd.type
            FROM t_usergroup_user ugu
            INNER JOIN t_usergroup_rights ugr ON ugu.fk_usergroup = ugr.fk_usergroup
            INNER JOIN t_rights_def rd ON ugr.fk_id = rd.id
            WHERE ugu.fk_user = :user_id
            AND rd.modulo = 'catalogos'
            AND rd.subpermiso = 'clientes'
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $this->userId]);
        
        while ($row = $stmt->fetch()) {
            switch ($row['permiso']) {
                case 'lire':
                    $this->permissions['view'] = true;
                    if ($row['type'] == 'r') {
                        $this->permissions['export'] = true;
                    }
                    break;
                case 'creer':
                    $this->permissions['create'] = true;
                    break;
                case 'modifier':
                    $this->permissions['edit'] = true;
                    break;
                case 'supprimer':
                    $this->permissions['delete'] = true;
                    break;
                case 'export':
                    $this->permissions['export'] = true;
                    break;
                case 'import':
                    $this->permissions['import'] = true;
                    break;
                case 'documents':
                    $this->permissions['documents'] = true;
                    break;
                case 'history':
                    $this->permissions['history'] = true;
                    break;
                case 'verify_qsq':
                    $this->permissions['verify_qsq'] = true;
                    break;
            }
        }
    }
    
    /**
     * Verificar si el usuario tiene un permiso específico
     */
    public function hasPermission($permission) {
        return isset($this->permissions[$permission]) && $this->permissions[$permission];
    }
    
    /**
     * Verificar múltiples permisos (requiere todos)
     */
    public function hasAllPermissions($permissions) {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Verificar múltiples permisos (requiere al menos uno)
     */
    public function hasAnyPermission($permissions) {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Obtener todos los permisos del usuario
     */
    public function getAllPermissions() {
        return $this->permissions;
    }
    
    /**
     * Verificar si es administrador
     */
    public function isAdmin() {
        return $this->isAdmin;
    }
    
    /**
     * Verificar si puede ver clientes
     */
    public function canView() {
        return $this->hasPermission('view');
    }
    
    /**
     * Verificar si puede crear clientes
     */
    public function canCreate() {
        return $this->hasPermission('create');
    }
    
    /**
     * Verificar si puede editar clientes
     */
    public function canEdit() {
        return $this->hasPermission('edit');
    }
    
    /**
     * Verificar si puede eliminar clientes
     */
    public function canDelete() {
        return $this->hasPermission('delete');
    }
    
    /**
     * Verificar si puede exportar clientes
     */
    public function canExport() {
        return $this->hasPermission('export');
    }
    
    /**
     * Verificar si puede importar clientes
     */
    public function canImport() {
        return $this->hasPermission('import');
    }
    
    /**
     * Verificar si puede gestionar documentos
     */
    public function canManageDocuments() {
        return $this->hasPermission('documents');
    }
    
    /**
     * Verificar si puede ver historial
     */
    public function canViewHistory() {
        return $this->hasPermission('history');
    }
    
    /**
     * Verificar si puede ejecutar verificación QSQ
     */
    public function canVerifyQSQ() {
        return $this->hasPermission('verify_qsq');
    }
    
    /**
     * Generar mensaje de error de permisos
     */
    public function getPermissionDeniedMessage($permission = null) {
        if ($permission && isset($CLIENTE_PERMISSIONS[$permission])) {
            return "No tienes permiso para: " . $CLIENTE_PERMISSIONS[$permission]['name'];

        }
        return "No tienes permiso para realizar esta acción";
    }
    
    /**
     * Verificar y redirigir si no tiene permisos
     */
    public function requirePermission($permission, $redirect = true) {
        if (!$this->hasPermission($permission)) {
            if ($redirect) {
                $_SESSION['error'] = $this->getPermissionDeniedMessage($permission);
                header('Location: catalogos.php?mod=clientes&action=list');
                exit;
            }
            return false;
        }
        return true;
    }
    
    /**
     * Obtener permisos formateados para mostrar
     */
    public function getPermissionsDisplay() {
        global $CLIENTE_PERMISSIONS;
        $display = [];
        
        foreach ($this->permissions as $key => $hasPermission) {
            if ($hasPermission && isset($CLIENTE_PERMISSIONS[$key])) {
                $display[] = [
                    'key' => $key,
                    'name' => $CLIENTE_PERMISSIONS[$key]['name'],
                    'description' => $CLIENTE_PERMISSIONS[$key]['description'],
                    'icon' => $this->getPermissionIcon($key)
                ];
            }
        }
        
        return $display;
    }
    
    /**
     * Obtener icono para cada permiso
     */
    private function getPermissionIcon($permission) {
        $icons = [
            'view' => 'fa-eye',
            'create' => 'fa-plus',
            'edit' => 'fa-edit',
            'delete' => 'fa-trash',
            'export' => 'fa-file-excel',
            'import' => 'fa-file-import',
            'documents' => 'fa-file-alt',
            'history' => 'fa-history',
            'verify_qsq' => 'fa-check-double'
        ];
        
        return $icons[$permission] ?? 'fa-key';
    }
}

// Crear instancia global de permisos para el usuario actual
$clientePermissions = new ClientePermissions();

// Variables globales para usar en las vistas
$canViewClientes = $clientePermissions->canView();
$canCreateClientes = $clientePermissions->canCreate();
$canEditClientes = $clientePermissions->canEdit();
$canDeleteClientes = $clientePermissions->canDelete();
$canExportClientes = $clientePermissions->canExport();
$canImportClientes = $clientePermissions->canImport();
$canManageDocsClientes = $clientePermissions->canManageDocuments();
$canViewHistoryClientes = $clientePermissions->canViewHistory();
$canVerifyQSQClientes = $clientePermissions->canVerifyQSQ();

// Función helper para verificar permisos en templates
function checkClientePermission($permission) {
    global $clientePermissions;
    return $clientePermissions->hasPermission($permission);
}

// Función para mostrar botones condicionalmente basado en permisos
function showIfClientePermission($permission, $html) {
    if (checkClientePermission($permission)) {
        echo $html;
    }
}
?>