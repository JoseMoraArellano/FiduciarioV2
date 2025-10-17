<?php
/**
 * modules/grupos/usuarios.php
 * Gestión de usuarios para un grupo
 */

// Cargar clase de gestión de grupos
require_once 'includes/GruposManager.php';

$gruposManager = new GruposManager();

// Verificar permisos
if (!$isAdmin && !$session->hasPermission('catalogos', 'modifier', 'grupos')) {
    die('<div class="text-center py-12">
        <i class="fas fa-lock text-6xl text-red-500 mb-4"></i>
        <p class="text-xl text-gray-700">No tienes permisos para gestionar usuarios de grupos</p>
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
$usuariosActuales = $grupo['usuarios'];
$usuariosActualesIds = array_column($usuariosActuales, 'id');

// Obtener todos los usuarios disponibles
$todosUsuarios = $gruposManager->getAllUsuarios();

// Mensajes
$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';
?>

<div x-data="usuariosController(<?php echo htmlspecialchars(json_encode($usuariosActualesIds)); ?>)" x-init="init()">
    
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
                        <span class="text-gray-500">Usuarios</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>
    
    <!-- Mensajes -->
    <?php if ($message === 'usuarios_updated'): ?>
    <div class="mb-6 bg-green-100 border-l-4 border-green-500 text-green-700 p-4">
        <div class="flex">
            <div class="py-1">
                <i class="fas fa-check-circle mr-2"></i>
            </div>
            <div>
                <p class="font-bold">Éxito</p>
                <p>Los usuarios del grupo han sido actualizados correctamente.</p>
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
                        <i class="fas fa-users text-purple-600"></i>
                        Gestión de Usuarios
                    </h2>
                    <p class="text-sm text-gray-600 mt-1">
                        Grupo: <strong><?php echo htmlspecialchars($grupo['nom']); ?></strong>
                        (<span x-text="selectedUsuarios.length"></span> usuarios seleccionados)
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
                        href="catalogos.php?mod=grupos&action=permissions&id=<?php echo $grupoId; ?>"
                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition"
                    >
                        <i class="fas fa-key"></i>
                        <span class="hidden md:inline">Permisos</span>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Búsqueda y filtros -->
        <div class="p-4 bg-gray-50 border-b border-gray-200">
            <div class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <input 
                        type="text"
                        x-model="searchTerm"
                        @input="filterUsuarios()"
                        placeholder="Buscar usuarios por nombre o email..."
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500"
                    >
                </div>
                <div class="flex items-center gap-2">
                    <button 
                        @click="showOnlySelected = !showOnlySelected; filterUsuarios()"
                        class="px-3 py-2 rounded-lg transition"
                        :class="showOnlySelected ? 'bg-purple-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'"
                    >
                        <i class="fas fa-filter mr-1"></i>
                        Solo Seleccionados
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Grid de usuarios existentes (si hay) -->
    <?php if (!empty($usuariosActuales)): ?>
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
                <i class="fas fa-user-check text-green-600"></i>
                Usuarios Actuales del Grupo (<?php echo count($usuariosActuales); ?>)
            </h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($usuariosActuales as $usuario): ?>
                <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg hover:shadow-md transition">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-user text-purple-600"></i>
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">
                                <?php 
                                $nombre = trim($usuario['firstname'] . ' ' . $usuario['lastname']);
                                echo htmlspecialchars($nombre ?: $usuario['name']); 
                                ?>
                            </p>
                            <p class="text-sm text-gray-500"><?php echo htmlspecialchars($usuario['email']); ?></p>
                            <?php if ($usuario['puesto']): ?>
                            <p class="text-xs text-gray-400"><?php echo htmlspecialchars($usuario['puesto']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <button
                        @click="removeUsuario(<?php echo $usuario['id']; ?>, '<?php echo htmlspecialchars($usuario['name']); ?>')"
                        class="text-red-600 hover:text-red-800 transition"
                        title="Remover del grupo"
                    >
                        <i class="fas fa-times-circle"></i>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Formulario de usuarios -->
    <form action="modules/grupos/actions.php" method="POST">
        <input type="hidden" name="action" value="assign-usuarios">
        <input type="hidden" name="grupo_id" value="<?php echo $grupoId; ?>">
        
        <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-user-plus text-blue-600"></i>
                    Seleccionar Usuarios para el Grupo
                </h3>
            </div>
            
            <!-- Lista de usuarios -->
            <div class="p-6">
                <div class="max-h-96 overflow-y-auto">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        <?php foreach ($todosUsuarios as $usuario): ?>
                        <label 
                            class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer transition usuario-item"
                            data-name="<?php echo htmlspecialchars(strtolower($usuario['firstname'] . ' ' . $usuario['lastname'] . ' ' . $usuario['name'])); ?>"
                            data-email="<?php echo htmlspecialchars(strtolower($usuario['email'])); ?>"
                            x-show="shouldShowUsuario(<?php echo $usuario['id']; ?>)"
                        >
                            <input 
                                type="checkbox" 
                                name="usuarios[]" 
                                value="<?php echo $usuario['id']; ?>"
                                x-model="selectedUsuarios"
                                x-bind:value="<?php echo $usuario['id']; ?>"
                                class="mr-3 text-purple-600 rounded focus:ring-purple-500"
                            >
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <p class="font-medium text-gray-900">
                                        <?php 
                                        $nombre = trim($usuario['firstname'] . ' ' . $usuario['lastname']);
                                        echo htmlspecialchars($nombre ?: $usuario['name']); 
                                        ?>
                                    </p>
                                    <?php if ($usuario['admin'] == 1): ?>
                                    <span class="px-1 py-0.5 bg-red-100 text-red-700 text-xs rounded">Admin</span>
                                    <?php endif; ?>
                                    <?php if ($usuario['statut'] == 0): ?>
                                    <span class="px-1 py-0.5 bg-gray-100 text-gray-700 text-xs rounded">Inactivo</span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($usuario['email']); ?></p>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Estadísticas -->
                <div class="mt-4 p-3 bg-gray-50 rounded-lg text-sm text-gray-600">
                    <i class="fas fa-info-circle mr-1"></i>
                    Total de usuarios disponibles: <?php echo count($todosUsuarios); ?> | 
                    Seleccionados: <span x-text="selectedUsuarios.length"></span>
                </div>
            </div>
            
            <!-- Botones de acción -->
            <div class="p-6 bg-gray-50 border-t border-gray-200">
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
                        Guardar Usuarios
                    </button>
                </div>
            </div>
        </div>
    </form>
    
</div>

<script>
function usuariosController(initialUsuarios) {
    return {
        selectedUsuarios: initialUsuarios.map(id => String(id)) || [],
        searchTerm: '',
        showOnlySelected: false,
        
        init() {
            console.log('Usuarios controller inicializado');
        },
        
        filterUsuarios() {
            // La filtración se maneja con x-show en Alpine
        },
        
        shouldShowUsuario(userId) {
            const userIdStr = String(userId);
            
            // Filtro de solo seleccionados
            if (this.showOnlySelected && !this.selectedUsuarios.includes(userIdStr)) {
                return false;
            }
            
            // Filtro de búsqueda
            if (this.searchTerm) {
                const element = document.querySelector(`input[value="${userId}"]`);
                if (element) {
                    const label = element.closest('.usuario-item');
                    const name = label.dataset.name || '';
                    const email = label.dataset.email || '';
                    const searchLower = this.searchTerm.toLowerCase();
                    
                    return name.includes(searchLower) || email.includes(searchLower);
                }
            }
            
            return true;
        },
        
        async removeUsuario(userId, userName) {
            if (!confirm(`¿Deseas remover a "${userName}" del grupo?`)) {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'remove-usuario');
                formData.append('grupo_id', <?php echo $grupoId; ?>);
                formData.append('user_id', userId);
                
                const response = await fetch('modules/grupos/actions.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    location.reload();
                } else {
                    alert(`Error: ${result.message}`);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error al remover usuario del grupo');
            }
        }
    };
}
</script>