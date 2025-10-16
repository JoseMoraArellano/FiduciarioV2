<?php
/**
 * modules/usuarios/permissions.php
 * Gestión de permisos y grupos de usuario
 */

// Cargar clase de gestión de usuarios
require_once 'includes/UsuariosManager.php';

$usuariosManager = new UsuariosManager();

// Obtener ID del usuario
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($userId <= 0) {
    echo '<div class="p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded">';
    echo '<i class="fas fa-exclamation-circle mr-2"></i>';
    echo 'ID de usuario inválido';
    echo '</div>';
    return;
}

// Solo admin puede gestionar permisos
if (!$isAdmin) {
    echo '<div class="p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded">';
    echo '<i class="fas fa-lock mr-2"></i>';
    echo 'Solo los administradores pueden gestionar permisos';
    echo '</div>';
    return;
}

// Obtener datos del usuario
$result = $usuariosManager->getUsuario($userId);

if (!$result['success']) {
    echo '<div class="p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded">';
    echo '<i class="fas fa-exclamation-circle mr-2"></i>';
    echo htmlspecialchars($result['message']);
    echo '</div>';
    return;
}

$usuario = $result['data'];

// Si es admin, mostrar advertencia
$isTargetAdmin = $usuario['admin'] == 1;

// Obtener todos los permisos agrupados por módulo
$permisosGrouped = $usuariosManager->getAllPermissionsGrouped();

// Obtener permisos actuales del usuario
$permisosActuales = $usuariosManager->getUserPermissions($userId);
$permisosActualesIds = array_column($permisosActuales, 'id');

// Obtener todos los grupos
$todosGrupos = $usuariosManager->getAllGroups();

// Obtener grupos actuales del usuario
$gruposActuales = $usuariosManager->getUserGroups($userId);
$gruposActualesIds = array_column($gruposActuales, 'id');

// Determinar tab activo
$activeTab = $_GET['tab'] ?? 'permissions';

// Calcular estadísticas
$totalPermisos = count($permisosActualesIds);
$totalGrupos = count($gruposActualesIds);

// Nombre completo del usuario
$fullName = '';
if (!empty($usuario['firstname']) || !empty($usuario['lastname'])) {
    $fullName = trim($usuario['firstname'] . ' ' . $usuario['lastname']);
} else {
    $fullName = $usuario['name'];
}
?>

<div x-data="permissionsController()" x-init="init()">
    
    <!-- Mensajes de éxito/error -->
    <?php if (isset($_SESSION['success'])): ?>
    <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-500 text-green-700 rounded flex items-start">
        <i class="fas fa-check-circle mt-1 mr-3"></i>
        <div>
            <p class="font-medium"><?php echo htmlspecialchars($_SESSION['success']); ?></p>
        </div>
        <button onclick="this.parentElement.remove()" class="ml-auto text-green-700 hover:text-green-900">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <?php unset($_SESSION['success']); endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
    <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded flex items-start">
        <i class="fas fa-exclamation-circle mt-1 mr-3"></i>
        <div>
            <p class="font-medium"><?php echo htmlspecialchars($_SESSION['error']); ?></p>
        </div>
        <button onclick="this.parentElement.remove()" class="ml-auto text-red-700 hover:text-red-900">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <?php unset($_SESSION['error']); endif; ?>

    <!-- Header -->
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="p-6 border-b">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <!-- Avatar -->
                    <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center text-2xl font-bold text-blue-600">
                        <?php echo strtoupper(substr($fullName, 0, 1)); ?>
                    </div>
                    
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                            <i class="fas fa-key text-green-600"></i>
                            Gestión de Permisos
                        </h2>
                        <p class="text-sm text-gray-600 mt-1">
                            Usuario: <span class="font-medium"><?php echo htmlspecialchars($fullName); ?></span>
                            <?php if ($isTargetAdmin): ?>
                            <span class="ml-2 px-2 py-0.5 text-xs bg-red-100 text-red-800 rounded-full">
                                <i class="fas fa-shield-alt"></i> Administrador
                            </span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                
                <div class="flex items-center gap-2">
                    <a 
                        href="catalogos.php?mod=usuarios&action=edit&id=<?php echo $userId; ?>"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center gap-2"
                    >
                        <i class="fas fa-edit"></i>
                        Editar Usuario
                    </a>
                    <a 
                        href="catalogos.php?mod=usuarios&action=list"
                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition flex items-center gap-2"
                    >
                        <i class="fas fa-arrow-left"></i>
                        Volver
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Estadísticas rápidas -->
        <div class="grid grid-cols-3 divide-x">
            <div class="p-4 text-center">
                <p class="text-2xl font-bold text-blue-600"><?php echo $totalPermisos; ?></p>
                <p class="text-sm text-gray-600">Permisos Directos</p>
            </div>
            <div class="p-4 text-center">
                <p class="text-2xl font-bold text-green-600"><?php echo $totalGrupos; ?></p>
                <p class="text-sm text-gray-600">Grupos Asignados</p>
            </div>
            <div class="p-4 text-center">
                <p class="text-2xl font-bold text-purple-600"><?php echo count($permisosGrouped); ?></p>
                <p class="text-sm text-gray-600">Módulos Disponibles</p>
            </div>
        </div>
        
        <!-- Tabs -->
        <div class="border-t">
            <nav class="flex -mb-px">
                
                    href="?mod=usuarios&action=permissions&id=<?php echo $userId; ?>&tab=permissions"
                    class="px-6 py-3 border-b-2 font-medium text-sm transition <?php echo $activeTab === 'permissions' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>"
                >
                    <i class="fas fa-shield-alt mr-2"></i>
                    Permisos Individuales
                </a>
                
                    href="?mod=usuarios&action=permissions&id=<?php echo $userId; ?>&tab=groups"
                    class="px-6 py-3 border-b-2 font-medium text-sm transition <?php echo $activeTab === 'groups' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>"
                >
                    <i class="fas fa-users mr-2"></i>
                    Grupos
                </a>
            </nav>
        </div>
    </div>

    <?php if ($isTargetAdmin): ?>
    <!-- Advertencia para usuarios admin -->
    <div class="mb-6 p-4 bg-yellow-50 border-l-4 border-yellow-500 text-yellow-800 rounded flex items-start">
        <i class="fas fa-exclamation-triangle mt-1 mr-3"></i>
        <div>
            <p class="font-medium">Usuario Administrador</p>
            <p class="text-sm mt-1">Este usuario tiene permisos de administrador y acceso total al sistema por defecto. Los permisos individuales y grupos son opcionales.</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- TAB: Permisos Individuales -->
    <?php if ($activeTab === 'permissions'): ?>
    
    <form method="POST" action="modules/usuarios/actions.php" @submit="validatePermissionsForm">
        <input type="hidden" name="action" value="save-permissions">
        <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
        
        <div class="bg-white rounded-lg shadow">
            
            <!-- Barra de herramientas -->
            <div class="p-4 border-b bg-gray-50">
                <div class="flex items-center justify-between gap-4">
                    
                    <!-- Búsqueda de permisos -->
                    <div class="flex-1 max-w-md">
                        <div class="relative">
                            <input 
                                type="text"
                                x-model="searchPermission"
                                @input="filterPermissions()"
                                placeholder="Buscar permisos..."
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            >
                            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                        </div>
                    </div>
                    
                    <!-- Acciones rápidas -->
                    <div class="flex items-center gap-2">
                        <button 
                            type="button"
                            @click="selectAll()"
                            class="px-3 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition text-sm"
                        >
                            <i class="fas fa-check-double mr-1"></i>
                            Seleccionar Todo
                        </button>
                        <button 
                            type="button"
                            @click="deselectAll()"
                            class="px-3 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition text-sm"
                        >
                            <i class="fas fa-times mr-1"></i>
                            Deseleccionar Todo
                        </button>
                        <span class="text-sm text-gray-600 ml-2">
                            <span x-text="selectedCount"></span> seleccionados
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Lista de permisos por módulo -->
            <div class="p-6 max-h-[600px] overflow-y-auto">
                <div class="space-y-6">
                    
                    <?php foreach ($permisosGrouped as $modulo => $permisos): ?>
                    <div class="border border-gray-200 rounded-lg" x-data="{ expanded: true }">
                        
                        <!-- Header del módulo -->
                        <div class="bg-gray-50 px-4 py-3 flex items-center justify-between cursor-pointer hover:bg-gray-100 transition"
                             @click="expanded = !expanded">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-chevron-down transition-transform" :class="expanded ? '' : '-rotate-90'"></i>
                                <h3 class="font-semibold text-gray-800 capitalize">
                                    <?php echo htmlspecialchars($modulo); ?>
                                </h3>
                                <span class="text-xs text-gray-500">
                                    (<?php echo count($permisos); ?> permisos)
                                </span>
                            </div>
                            
                            <button 
                                type="button"
                                @click.stop="toggleModule('<?php echo $modulo; ?>')"
                                class="px-3 py-1 bg-blue-100 text-blue-700 rounded text-xs hover:bg-blue-200 transition"
                            >
                                <i class="fas fa-check-double mr-1"></i>
                                Toggle Módulo
                            </button>
                        </div>
                        
                        <!-- Permisos del módulo -->
                        <div x-show="expanded" x-collapse class="p-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                
                                <?php foreach ($permisos as $permiso): ?>
                                <label class="flex items-start p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition cursor-pointer permission-item"
                                       data-module="<?php echo htmlspecialchars($modulo); ?>"
                                       data-permission="<?php echo htmlspecialchars($permiso['descripcion']); ?>">
                                    <input 
                                        type="checkbox" 
                                        name="permissions[]" 
                                        value="<?php echo $permiso['id']; ?>"
                                        <?php echo in_array($permiso['id'], $permisosActualesIds) ? 'checked' : ''; ?>
                                        class="mt-1 w-4 h-4 text-blue-600 rounded focus:ring-blue-500 permission-checkbox"
                                        data-module="<?php echo htmlspecialchars($modulo); ?>"
                                        @change="updateCount()"
                                    >
                                    <div class="ml-3 flex-1">
                                        <p class="text-sm font-medium text-gray-800">
                                            <?php echo htmlspecialchars($permiso['descripcion']); ?>
                                        </p>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="text-xs px-2 py-0.5 rounded bg-blue-100 text-blue-700">
                                                <?php echo htmlspecialchars($permiso['permiso']); ?>
                                            </span>
                                            <?php if (!empty($permiso['subpermiso'])): ?>
                                            <span class="text-xs px-2 py-0.5 rounded bg-purple-100 text-purple-700">
                                                <?php echo htmlspecialchars($permiso['subpermiso']); ?>
                                            </span>
                                            <?php endif; ?>
                                            <span class="text-xs px-2 py-0.5 rounded <?php 
                                                echo $permiso['type'] === 'r' ? 'bg-green-100 text-green-700' : 
                                                    ($permiso['type'] === 'w' ? 'bg-orange-100 text-orange-700' : 
                                                    ($permiso['type'] === 'd' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700')); 
                                            ?>">
                                                <?php 
                                                echo $permiso['type'] === 'r' ? 'Lectura' : 
                                                    ($permiso['type'] === 'w' ? 'Escritura' : 
                                                    ($permiso['type'] === 'd' ? 'Eliminar' : 'Otro')); 
                                                ?>
                                            </span>
                                        </div>
                                    </div>
                                </label>
                                <?php endforeach; ?>
                                
                            </div>
                        </div>
                        
                    </div>
                    <?php endforeach; ?>
                    
                </div>
            </div>
            
            <!-- Footer con botones -->
            <div class="p-6 border-t bg-gray-50 flex items-center justify-between">
                <p class="text-sm text-gray-600">
                    <i class="fas fa-info-circle mr-1"></i>
                    Los permisos se aplicarán inmediatamente después de guardar
                </p>
                
                <div class="flex items-center gap-3">
                    <a 
                        href="catalogos.php?mod=usuarios&action=edit&id=<?php echo $userId; ?>"
                        class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition"
                    >
                        Cancelar
                    </a>
                    <button 
                        type="submit"
                        class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center gap-2"
                        :disabled="isSubmitting"
                        :class="isSubmitting ? 'opacity-50 cursor-not-allowed' : ''"
                    >
                        <i class="fas" :class="isSubmitting ? 'fa-spinner fa-spin' : 'fa-save'"></i>
                        <span x-text="isSubmitting ? 'Guardando...' : 'Guardar Permisos'"></span>
                    </button>
                </div>
            </div>
            
        </div>
    </form>
    
    <?php endif; ?>

    <!-- TAB: Grupos -->
    <?php if ($activeTab === 'groups'): ?>
    
    <form method="POST" action="modules/usuarios/actions.php" @submit="validateGroupsForm">
        <input type="hidden" name="action" value="save-groups">
        <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
        
        <div class="bg-white rounded-lg shadow">
            
            <!-- Info sobre grupos -->
            <div class="p-6 border-b bg-blue-50">
                <div class="flex items-start gap-3">
                    <i class="fas fa-info-circle text-blue-600 mt-1"></i>
                    <div>
                        <p class="font-medium text-blue-900">¿Qué son los grupos?</p>
                        <p class="text-sm text-blue-800 mt-1">
                            Los grupos permiten asignar conjuntos de permisos a múltiples usuarios. 
                            Cuando un usuario pertenece a un grupo, hereda automáticamente todos los permisos del grupo.
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Lista de grupos -->
            <div class="p-6">
                
                <?php if (empty($todosGrupos)): ?>
                <!-- Sin grupos -->
                <div class="text-center py-12">
                    <i class="fas fa-users text-6xl text-gray-300 mb-4"></i>
                    <p class="text-xl text-gray-600 mb-2">No hay grupos disponibles</p>
                    <p class="text-gray-500">Los grupos deben ser creados primero por un administrador</p>
                </div>
                <?php else: ?>
                
                <!-- Grid de grupos -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    
                    <?php foreach ($todosGrupos as $grupo): ?>
                    <label class="relative flex items-start p-4 border-2 rounded-lg cursor-pointer transition hover:shadow-md <?php echo in_array($grupo['id'], $gruposActualesIds) ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'; ?>">
                        <input 
                            type="checkbox" 
                            name="groups[]" 
                            value="<?php echo $grupo['id']; ?>"
                            <?php echo in_array($grupo['id'], $gruposActualesIds) ? 'checked' : ''; ?>
                            class="mt-1 w-5 h-5 text-blue-600 rounded focus:ring-blue-500"
                        >
                        <div class="ml-3 flex-1">
                            <p class="font-semibold text-gray-800">
                                <?php echo htmlspecialchars($grupo['nom']); ?>
                            </p>
                            <?php if (!empty($grupo['note'])): ?>
                            <p class="text-sm text-gray-600 mt-1">
                                <?php echo htmlspecialchars($grupo['note']); ?>
                            </p>
                            <?php endif; ?>
                            
                            <!-- Mostrar cantidad de permisos del grupo -->
                            <?php
                            $sql = "SELECT COUNT(*) as total FROM t_usergroup_rights WHERE fk_usergroup = :group_id";
                            $stmt = Database::getInstance()->getConnection()->prepare($sql);
                            $stmt->execute(['group_id' => $grupo['id']]);
                            $groupPermsCount = $stmt->fetch()['total'];
                            ?>
                            <p class="text-xs text-gray-500 mt-2">
                                <i class="fas fa-key mr-1"></i>
                                <?php echo $groupPermsCount; ?> permisos
                            </p>
                        </div>
                    </label>
                    <?php endforeach; ?>
                    
                </div>
                
                <?php endif; ?>
                
            </div>
            
            <!-- Footer -->
            <div class="p-6 border-t bg-gray-50 flex items-center justify-between">
                <p class="text-sm text-gray-600">
                    <i class="fas fa-info-circle mr-1"></i>
                    El usuario heredará todos los permisos de los grupos seleccionados
                </p>
                
                <div class="flex items-center gap-3">
                    <a 
                        href="catalogos.php?mod=usuarios&action=edit&id=<?php echo $userId; ?>"
                        class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition"
                    >
                        Cancelar
                    </a>
                    <button 
                        type="submit"
                        class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center gap-2"
                        :disabled="isSubmitting"
                        :class="isSubmitting ? 'opacity-50 cursor-not-allowed' : ''"
                    >
                        <i class="fas" :class="isSubmitting ? 'fa-spinner fa-spin' : 'fa-save'"></i>
                        <span x-text="isSubmitting ? 'Guardando...' : 'Guardar Grupos'"></span>
                    </button>
                </div>
            </div>
            
        </div>
    </form>
    
    <?php endif; ?>
    
</div>

<script>
// Controlador Alpine.js para permisos
function permissionsController() {
    return {
        searchPermission: '',
        selectedCount: 0,
        isSubmitting: false,
        
        init() {
            this.updateCount();
            console.log('Gestor de permisos inicializado');
        },
        
        // Actualizar contador de seleccionados
        updateCount() {
            this.selectedCount = document.querySelectorAll('.permission-checkbox:checked').length;
        },
        
        // Seleccionar todos los permisos visibles
        selectAll() {
            document.querySelectorAll('.permission-item:not([style*="display: none"]) .permission-checkbox').forEach(checkbox => {
                checkbox.checked = true;
            });
            this.updateCount();
        },
        
        // Deseleccionar todos
        deselectAll() {
            document.querySelectorAll('.permission-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
            this.updateCount();
        },
        
        // Toggle todos los permisos de un módulo
        toggleModule(module) {
            const checkboxes = document.querySelectorAll(`.permission-checkbox[data-module="${module}"]`);
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = !allChecked;
            });
            
            this.updateCount();
        },
        
        // Filtrar permisos por búsqueda
        filterPermissions() {
            const search = this.searchPermission.toLowerCase();
            
            document.querySelectorAll('.permission-item').forEach(item => {
                const text = item.getAttribute('data-permission').toLowerCase();
                const module = item.getAttribute('data-module').toLowerCase();
                
                if (text.includes(search) || module.includes(search)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        },
        
        // Validar formulario de permisos
        validatePermissionsForm(e) {
            const checked = document.querySelectorAll('.permission-checkbox:checked').length;
            
            if (checked === 0) {
                if (!confirm('No has seleccionado ningún permiso. ¿Deseas continuar?')) {
                    e.preventDefault();
                    return false;
                }
            }
            
            this.isSubmitting = true;
            return true;
        },
        
        // Validar formulario de grupos
        validateGroupsForm(e) {
            this.isSubmitting = true;
            return true;
        }
    };
}
</script>