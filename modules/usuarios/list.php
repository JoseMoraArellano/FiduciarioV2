<?php
/**
 * modules/usuarios/list.php
 * Listado de usuarios con búsqueda, filtros y acciones
 */

// Cargar clase de gestión de usuarios
require_once 'includes/UsuariosManager.php';

$usuariosManager = new UsuariosManager();

// Obtener parámetros de filtro y paginación
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;

$filters = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? '',
    'role' => $_GET['role'] ?? '',
    'group' => $_GET['group'] ?? ''
];

// Obtener usuarios
$result = $usuariosManager->getUsuarios($filters, $page, $perPage);
$usuarios = $result['data'] ?? [];
$totalPages = $result['total_pages'] ?? 1;
$total = $result['total'] ?? 0;

// Obtener estadísticas
$stats = $usuariosManager->getStats();

// Obtener grupos para filtro
$grupos = $usuariosManager->getAllGroups();

// Verificar permisos
$canCreate = $isAdmin || $session->hasPermission('catalogos', 'creer', 'usuarios');
$canEdit = $isAdmin || $session->hasPermission('catalogos', 'modifier', 'usuarios');
$canDelete = $isAdmin || $session->hasPermission('catalogos', 'supprimer', 'usuarios');
?>

<div x-data="usuariosListController()" x-init="init()">
    
    <!-- Estadísticas -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        
        <!-- Total Usuarios -->
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 font-medium">Total Usuarios</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total'] ?? 0; ?></p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-users text-2xl text-blue-600"></i>
                </div>
            </div>
        </div>
        
        <!-- Activos -->
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 font-medium">Activos</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $stats['activos'] ?? 0; ?></p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-user-check text-2xl text-green-600"></i>
                </div>
            </div>
        </div>
        
        <!-- Administradores -->
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 font-medium">Administradores</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $stats['administradores'] ?? 0; ?></p>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-user-shield text-2xl text-purple-600"></i>
                </div>
            </div>
        </div>
        
        <!-- Sin Permisos -->
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-orange-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 font-medium">Sin Permisos</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $stats['sin_permisos'] ?? 0; ?></p>
                </div>
                <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-2xl text-orange-600"></i>
                </div>
            </div>
        </div>
        
    </div>

    <!-- Barra de acciones y filtros -->
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="p-6">
            
            <!-- Fila superior: Título y botón crear -->
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-list text-blue-600"></i>
                    Listado de Usuarios
                    <span class="text-sm font-normal text-gray-500">(<?php echo $total; ?> registros)</span>
                </h2>
                
                <div class="flex items-center gap-2">
                    <!-- Botón Exportar -->
                    <button
                        @click="exportToCSV()"
                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center gap-2"
                    >
                        <i class="fas fa-file-excel"></i>
                        <span class="hidden md:inline">Exportar</span>
                    </button>
                    <a 
                        href="catalogos.php?mod=grupos&action=list"
                        class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition flex items-center gap-2"
                    >
                        <i class="fas fa-users-cog"></i>
                        <span class="hidden md:inline">Gestionar Grupos</span>
                    </a>
                    <!-- Botón Crear -->
                    <?php if ($canCreate): ?>
                    <a 
                        href="catalogos.php?mod=usuarios&action=create"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center gap-2"
                    >
                        <i class="fas fa-plus"></i>
                        <span class="hidden md:inline">Nuevo Usuario</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Filtros -->
            <form method="GET" action="catalogos.php" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <input type="hidden" name="mod" value="usuarios">
                <input type="hidden" name="action" value="list">
                
                <!-- Búsqueda -->
                <div class="lg:col-span-2">
                    <div class="relative">
                        <input 
                            type="text" 
                            name="search" 
                            placeholder="Buscar por nombre, email..."
                            value="<?php echo htmlspecialchars($filters['search']); ?>"
                            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                        <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                    </div>
                </div>
                
                <!-- Filtro Estado -->
                <div>
                    <select 
                        name="status" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                        <option value="">Todos los estados</option>
                        <option value="1" <?php echo $filters['status'] === '1' ? 'selected' : ''; ?>>Activos</option>
                        <option value="0" <?php echo $filters['status'] === '0' ? 'selected' : ''; ?>>Inactivos</option>
                    </select>
                </div>
                
                <!-- Filtro Rol -->
                <div>
                    <select 
                        name="role" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                        <option value="">Todos los roles</option>
                        <option value="admin" <?php echo $filters['role'] === 'admin' ? 'selected' : ''; ?>>Administradores</option>
                        <option value="empleado" <?php echo $filters['role'] === 'empleado' ? 'selected' : ''; ?>>Empleados</option>
                        <option value="normal" <?php echo $filters['role'] === 'normal' ? 'selected' : ''; ?>>Usuarios</option>
                    </select>
                </div>
                
                <!-- Filtro Grupo -->
                <div>
                    <select 
                        name="group" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                        <option value="">Todos los grupos</option>
                        <?php foreach ($grupos as $grupo): ?>
                        <option value="<?php echo $grupo['id']; ?>" <?php echo $filters['group'] == $grupo['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($grupo['nom']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Botones de acción del filtro (móvil y desktop) -->
                <div class="lg:col-span-5 flex gap-2">
                    <button 
                        type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center gap-2"
                    >
                        <i class="fas fa-filter"></i>
                        Filtrar
                    </button>
                    
                    <a 
                        href="catalogos.php?mod=usuarios&action=list"
                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition flex items-center gap-2"
                    >
                        <i class="fas fa-times"></i>
                        Limpiar
                    </a>
                </div>
            </form>
            
        </div>
    </div>

    <!-- Tabla de usuarios -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        
        <?php if (empty($usuarios)): ?>
            <!-- Sin resultados -->
            <div class="p-12 text-center">
                <i class="fas fa-users text-6xl text-gray-300 mb-4"></i>
                <p class="text-xl text-gray-600 mb-2">No se encontraron usuarios</p>
                <p class="text-gray-500 mb-4">Intenta ajustar los filtros o crear un nuevo usuario</p>
                <?php if ($canCreate): ?>
                <a 
                    href="catalogos.php?mod=usuarios&action=create"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
                >
                    <i class="fas fa-plus"></i>
                    Crear Primer Usuario
                </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            
            <!-- Tabla responsive -->
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Usuario
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Email
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Rol
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Permisos
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Estado
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Último Login
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Acciones
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($usuarios as $usuario): ?>
                        <tr class="hover:bg-gray-50 transition">
                            
                            <!-- Usuario -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                                        <span class="text-blue-600 font-bold">
                                            <?php 
                                            if (!empty($usuario['firstname'])) {
                                                echo strtoupper(substr($usuario['firstname'], 0, 1));
                                            } else {
                                                echo strtoupper(substr($usuario['name'], 0, 1));
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-gray-900">
                                            <?php 
                                            if (!empty($usuario['firstname']) || !empty($usuario['lastname'])) {
                                                echo htmlspecialchars(trim($usuario['firstname'] . ' ' . $usuario['lastname']));
                                            } else {
                                                echo htmlspecialchars($usuario['name']);
                                            }
                                            ?>
                                        </p>
                                        <p class="text-xs text-gray-500">@<?php echo htmlspecialchars($usuario['name']); ?></p>
                                    </div>
                                </div>
                            </td>
                            
                            <!-- Email -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($usuario['email']); ?></div>
                                <?php if (!empty($usuario['puesto'])): ?>
                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($usuario['puesto']); ?></div>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Rol -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($usuario['admin'] == 1): ?>
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800 flex items-center gap-1 w-fit">
                                    <i class="fas fa-shield-alt"></i>
                                    Admin
                                </span>
                                <?php elseif ($usuario['empleado'] == 1): ?>
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 flex items-center gap-1 w-fit">
                                    <i class="fas fa-user-tie"></i>
                                    Empleado
                                </span>
                                <?php else: ?>
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800 flex items-center gap-1 w-fit">
                                    <i class="fas fa-user"></i>
                                    Usuario
                                </span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Permisos -->
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <div class="flex items-center gap-3">
                                    <span class="flex items-center gap-1" title="Permisos directos">
                                        <i class="fas fa-key text-blue-500"></i>
                                        <?php echo $usuario['permisos_directos']; ?>
                                    </span>
                                    <span class="flex items-center gap-1" title="Grupos">
                                        <i class="fas fa-users text-green-500"></i>
                                        <?php echo $usuario['grupos']; ?>
                                    </span>
                                </div>
                            </td>
                            
                            <!-- Estado -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($usuario['statut'] == 1): ?>
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                    Activo
                                </span>
                                <?php else: ?>
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                    Inactivo
                                </span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Último Login -->
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php if (!empty($usuario['datelastlogin'])): ?>
                                    <?php echo date('d/m/Y H:i', strtotime($usuario['datelastlogin'])); ?>
                                <?php else: ?>
                                    <span class="text-gray-400">Nunca</span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Acciones -->
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end gap-2">
                                    
                                    <!-- Ver/Editar -->
                                    <?php if ($canEdit): ?>
                                    <a 
                                        href="catalogos.php?mod=usuarios&action=edit&id=<?php echo $usuario['id']; ?>"
                                        class="text-blue-600 hover:text-blue-900 transition"
                                        title="Editar"
                                    >
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <!-- Permisos -->
                                    <?php if ($isAdmin): ?>
                                    <a 
                                        href="catalogos.php?mod=usuarios&action=permissions&id=<?php echo $usuario['id']; ?>"
                                        class="text-green-600 hover:text-green-900 transition"
                                        title="Gestionar Permisos"
                                    >
                                        <i class="fas fa-key"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <!-- Duplicar -->
                                    <?php if ($canCreate): ?>
                                    <button
                                        @click="duplicateUser(<?php echo $usuario['id']; ?>, '<?php echo htmlspecialchars($usuario['name']); ?>')"
                                        class="text-purple-600 hover:text-purple-900 transition"
                                        title="Duplicar Usuario"
                                    >
                                        <i class="fas fa-copy"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <!-- Activar/Desactivar -->
                                    <?php if ($canEdit && $usuario['id'] != 1): ?>
                                    <button
                                        @click="toggleStatus(<?php echo $usuario['id']; ?>, '<?php echo htmlspecialchars($usuario['name']); ?>', <?php echo $usuario['statut']; ?>)"
                                        class="<?php echo $usuario['statut'] == 1 ? 'text-orange-600 hover:text-orange-900' : 'text-green-600 hover:text-green-900'; ?> transition"
                                        title="<?php echo $usuario['statut'] == 1 ? 'Desactivar' : 'Activar'; ?>"
                                    >
                                        <i class="fas fa-<?php echo $usuario['statut'] == 1 ? 'ban' : 'check-circle'; ?>"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <!-- Eliminar -->
                                    <?php if ($canDelete && $usuario['id'] != 1): ?>
                                    <button
                                        @click="deleteUser(<?php echo $usuario['id']; ?>, '<?php echo htmlspecialchars($usuario['name']); ?>')"
                                        class="text-red-600 hover:text-red-900 transition"
                                        title="Eliminar"
                                    >
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Paginación -->
            <?php if ($totalPages > 1): ?>
            <div class="bg-gray-50 px-6 py-4 border-t">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        Mostrando página <span class="font-medium"><?php echo $page; ?></span> de <span class="font-medium"><?php echo $totalPages; ?></span>
                    </div>
                    
                    <div class="flex gap-2">
                        <!-- Anterior -->
                        <?php if ($page > 1): ?>
                        <a 
                            href="?mod=usuarios&action=list&page=<?php echo $page - 1; ?>&<?php echo http_build_query($filters); ?>"
                            class="px-3 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition"
                        >
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <?php endif; ?>
                        
                        <!-- Números de página -->
                        <?php 
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        
                        for ($i = $startPage; $i <= $endPage; $i++): 
                        ?>
                        <a 
                            href="?mod=usuarios&action=list&page=<?php echo $i; ?>&<?php echo http_build_query($filters); ?>"
                            class="px-3 py-2 <?php echo $i == $page ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?> border border-gray-300 rounded-lg transition"
                        >
                            <?php echo $i; ?>
                        </a>
                        <?php endfor; ?>
                        
                        <!-- Siguiente -->
                        <?php if ($page < $totalPages): ?>
                        <a 
                            href="?mod=usuarios&action=list&page=<?php echo $page + 1; ?>&<?php echo http_build_query($filters); ?>"
                            class="px-3 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition"
                        >
                            <i class="fas fa-chevron-right"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
        <?php endif; ?>
        
    </div>
    
</div>

<script>
// Controlador Alpine.js para la lista
function usuariosListController() {
    return {
        init() {
            console.log('Lista de usuarios inicializada');
        },
        
        // Duplicar usuario
        async duplicateUser(userId, userName) {
            if (!confirm(`¿Deseas duplicar el usuario "${userName}"?\n\nSe creará una copia con los mismos permisos y grupos.`)) {
                return;
            }
            
            try {
                const response = await fetch('modules/usuarios/actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=duplicate&id=${userId}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(`Usuario duplicado exitosamente.\n\nNuevo usuario: ${result.new_username}`);
                    location.reload();
                } else {
                    alert(`Error: ${result.message}`);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error al duplicar usuario');
            }
        },
        
        // Cambiar estado
        async toggleStatus(userId, userName, currentStatus) {
            const action = currentStatus == 1 ? 'desactivar' : 'activar';
            
            if (!confirm(`¿Deseas ${action} el usuario "${userName}"?`)) {
                return;
            }
            
            try {
                const response = await fetch('modules/usuarios/actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=toggle-status&id=${userId}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(result.message);
                    location.reload();
                } else {
                    alert(`Error: ${result.message}`);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error al cambiar estado');
            }
        },
        
        // Eliminar usuario
        async deleteUser(userId, userName) {
            if (!confirm(`¿Estás seguro de eliminar el usuario "${userName}"?\n\nEsta acción no se puede deshacer.`)) {
                return;
            }
            
            try {
                const response = await fetch('modules/usuarios/actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete&id=${userId}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(result.message);
                    location.reload();
                } else {
                    alert(`Error: ${result.message}`);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error al eliminar usuario');
            }
        },
        
        // Exportar a CSV
        async exportToCSV() {
            const params = new URLSearchParams(window.location.search);
            params.set('action', 'export-csv');
            
            window.location.href = `modules/usuarios/actions.php?${params.toString()}`;
        }
    };
}
</script>