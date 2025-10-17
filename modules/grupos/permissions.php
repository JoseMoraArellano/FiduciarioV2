<?php
/**
 * modules/grupos/permissions.php
 * Gestión de permisos para un grupo
 */

// Cargar clase de gestión de grupos
require_once 'includes/GruposManager.php';

$gruposManager = new GruposManager();

// Verificar permisos
if (!$isAdmin && !$session->hasPermission('catalogos', 'modifier', 'grupos')) {
    die('<div class="text-center py-12">
        <i class="fas fa-lock text-6xl text-red-500 mb-4"></i>
        <p class="text-xl text-gray-700">No tienes permisos para gestionar permisos de grupos</p>
    </div>');
}

// Obtener ID del grupo
$grupoId = $_GET['id'] ?? 0;

if (!$grupoId) {
    header('Location: catalogos.php?mod=grupos&action=list');
    exit;
}

// Cargar datos del grupo
$result = $gruposManager->getGrupo($grupoId);
if (!$result['success']) {
    die('<div class="text-center py-12">
        <i class="fas fa-exclamation-triangle text-6xl text-red-500 mb-4"></i>
        <p class="text-xl text-gray-700">' . $result['message'] . '</p>
    </div>');
}

$grupo = $result['data'];
$permisosActuales = array_column($grupo['permisos'], 'id');

// Obtener todos los permisos disponibles agrupados
$todosPermisos = $gruposManager->getAllPermissionsGrouped();

// Mensajes
$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';
?>

<div x-data="permissionsController(<?php echo htmlspecialchars(json_encode($permisosActuales)); ?>)" x-init="init()">
    
    <!-- Breadcrumb -->
    <div class="mb-6">
        <nav class="flex text-gray-600" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="catalogos.php?mod=grupos&action=list" class="text-gray-700 hover:text-purple-600">
                        <i class="fas fa-users-cog mr-2"></i>
                        Grupos
                    </a>
                </li>
                <li>
                    <div class="flex items-center">
                        <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                        <a href="catalogos.php?mod=grupos&action=edit&id=<?php echo $grupoId; ?>" class="text-gray-700 hover:text-purple-600">
                            <?php echo htmlspecialchars($grupo['nom']); ?>
                        </a>
                    </div>
                </li>
                <li>
                    <div class="flex items-center">
                        <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                        <span class="text-gray-500">Permisos</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>
    
    <!-- Mensajes -->
    <?php if ($message === 'permissions_updated'): ?>
    <div class="mb-6 bg-green-100 border-l-4 border-green-500 text-green-700 p-4">
        <div class="flex">
            <div class="py-1">
                <i class="fas fa-check-circle mr-2"></i>
            </div>
            <div>
                <p class="font-bold">Éxito</p>
                <p>Los permisos del grupo han sido actualizados correctamente.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="mb-6 bg-red-100 border-l-4 border-red-500 text-red-700 p-4">
        <div class="flex">
            <div class="py-1">
                <i class="fas fa-exclamation-circle mr-2"></i>
            </div>
            <div>
                <p class="font-bold">Error</p>
                <p><?php echo htmlspecialchars($error); ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Información del grupo -->
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                        <i class="fas fa-key text-purple-600"></i>
                        Gestión de Permisos
                    </h2>
                    <p class="text-sm text-gray-600 mt-1">
                        Grupo: <strong><?php echo htmlspecialchars($grupo['nom']); ?></strong>
                        (<?php echo count($permisosActuales); ?> permisos actuales)
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <a 
                        href="catalogos.php?mod=grupos&action=edit&id=<?php echo $grupoId; ?>"
                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition"
                    >
                        <i class="fas fa-edit"></i>
                        <span class="hidden md:inline">Editar Grupo</span>
                    </a>
                    <a 
                        href="catalogos.php?mod=grupos&action=usuarios&id=<?php echo $grupoId; ?>"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
                    >
                        <i class="fas fa-users"></i>
                        <span class="hidden md:inline">Usuarios</span>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Controles rápidos -->
        <div class="p-4 bg-gray-50 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <button 
                        @click="selectAll()"
                        class="px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700 transition"
                    >
                        <i class="fas fa-check-square mr-1"></i>
                        Seleccionar Todo
                    </button>
                    <button 
                        @click="deselectAll()"
                        class="px-3 py-1 bg-gray-600 text-white text-sm rounded hover:bg-gray-700 transition"
                    >
                        <i class="fas fa-square mr-1"></i>
                        Deseleccionar Todo
                    </button>
                    <button 
                        @click="toggleModule()"
                        class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 transition"
                    >
                        <i class="fas fa-exchange-alt mr-1"></i>
                        Invertir Selección
                    </button>
                </div>
                <div>
                    <span class="text-sm text-gray-600">
                        <span x-text="selectedCount"></span> permisos seleccionados
                    </span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Formulario de permisos -->
    <form action="modules/grupos/actions.php" method="POST">
        <input type="hidden" name="action" value="assign-permissions">
        <input type="hidden" name="grupo_id" value="<?php echo $grupoId; ?>">
        
        <!-- Permisos agrupados por módulo -->
        <div class="space-y-4">
            <?php foreach ($todosPermisos as $modulo => $permisos): ?>
            <div class="bg-white rounded-lg shadow">
                <!-- Header del módulo -->
                <div 
                    @click="toggleModuleCollapse('<?php echo htmlspecialchars($modulo); ?>')"
                    class="p-4 bg-gray-50 border-b border-gray-200 cursor-pointer hover:bg-gray-100 transition"
                >
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                            <i class="fas fa-folder text-purple-500"></i>
                            <?php echo htmlspecialchars(ucfirst($modulo)); ?>
                            <span class="text-sm font-normal text-gray-500">
                                (<span x-text="getModuleSelectedCount('<?php echo htmlspecialchars($modulo); ?>')"></span>/<?php echo count($permisos); ?>)
                            </span>
                        </h3>
                        <div class="flex items-center gap-2">
                            <button 
                                type="button"
                                @click.stop="selectModuleAll('<?php echo htmlspecialchars($modulo); ?>')"
                                class="px-2 py-1 bg-green-100 text-green-700 text-xs rounded hover:bg-green-200 transition"
                            >
                                Todo
                            </button>
                            <button 
                                type="button"
                                @click.stop="deselectModuleAll('<?php echo htmlspecialchars($modulo); ?>')"
                                class="px-2 py-1 bg-gray-100 text-gray-700 text-xs rounded hover:bg-gray-200 transition"
                            >
                                Nada
                            </button>
                            <i 
                                class="fas fa-chevron-down transition-transform"
                                :class="{'rotate-180': isModuleOpen('<?php echo htmlspecialchars($modulo); ?>')}"
                            ></i>
                        </div>
                    </div>
                </div>
                
                <!-- Permisos del módulo -->
                <div 
                    x-show="isModuleOpen('<?php echo htmlspecialchars($modulo); ?>')"
                    x-transition
                    class="p-4"
                >
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        <?php foreach ($permisos as $permiso): ?>
                        <label class="flex items-start space-x-2 p-2 rounded hover:bg-gray-50 cursor-pointer">
                            <input 
                                type="checkbox" 
                                name="permissions[]" 
                                value="<?php echo $permiso['id']; ?>"
                                x-model="selectedPermissions"
                                data-module="<?php echo htmlspecialchars($modulo); ?>"
                                class="mt-1 text-purple-600 rounded focus:ring-purple-500"
                            >
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-700">
                                    <?php echo htmlspecialchars($permiso['descripcion']); ?>
                                </p>
                                <p class="text-xs text-gray-500">
                                    <?php echo htmlspecialchars($permiso['permiso']); ?>
                                    <?php if ($permiso['subpermiso']): ?>
                                        / <?php echo htmlspecialchars($permiso['subpermiso']); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Botones de acción -->
        <div class="mt-6 bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <a 
                    href="catalogos.php?mod=grupos&action=list" 
                    class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition"
                >
                    <i class="fas fa-arrow-left mr-2"></i>
                    Volver al Listado
                </a>
                <button 
                    type="submit"
                    class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition flex items-center gap-2"
                >
                    <i class="fas fa-save"></i>
                    Guardar Permisos
                </button>
            </div>
        </div>
    </form>
    
</div>

<script>
function permissionsController(initialPermissions) {
    return {
        selectedPermissions: initialPermissions || [],
        openModules: {},
        
        init() {
            // Abrir módulos que tienen permisos seleccionados
            document.querySelectorAll('input[name="permissions[]"]:checked').forEach(input => {
                const module = input.dataset.module;
                if (module) {
                    this.openModules[module] = true;
                }
            });
        },
        
        get selectedCount() {
            return this.selectedPermissions.length;
        },
        
        selectAll() {
            const checkboxes = document.querySelectorAll('input[name="permissions[]"]');
            this.selectedPermissions = Array.from(checkboxes).map(cb => parseInt(cb.value));
        },
        
        deselectAll() {
            this.selectedPermissions = [];
        },
        
        toggleModule() {
            const checkboxes = document.querySelectorAll('input[name="permissions[]"]');
            const allValues = Array.from(checkboxes).map(cb => parseInt(cb.value));
            const newSelection = [];
            
            allValues.forEach(val => {
                if (!this.selectedPermissions.includes(val)) {
                    newSelection.push(val);
                }
            });
            
            this.selectedPermissions = newSelection;
        },
        
        selectModuleAll(module) {
            const checkboxes = document.querySelectorAll(`input[data-module="${module}"]`);
            checkboxes.forEach(cb => {
                const val = parseInt(cb.value);
                if (!this.selectedPermissions.includes(val)) {
                    this.selectedPermissions.push(val);
                }
            });
        },
        
        deselectModuleAll(module) {
            const checkboxes = document.querySelectorAll(`input[data-module="${module}"]`);
            const valuesToRemove = Array.from(checkboxes).map(cb => parseInt(cb.value));
            this.selectedPermissions = this.selectedPermissions.filter(val => !valuesToRemove.includes(val));
        },
        
        getModuleSelectedCount(module) {
            const checkboxes = document.querySelectorAll(`input[data-module="${module}"]`);
            let count = 0;
            checkboxes.forEach(cb => {
                if (this.selectedPermissions.includes(parseInt(cb.value))) {
                    count++;
                }
            });
            return count;
        },
        
        toggleModuleCollapse(module) {
            this.openModules[module] = !this.openModules[module];
        },
        
        isModuleOpen(module) {
            return this.openModules[module] || false;
        }
    };
}
</script>