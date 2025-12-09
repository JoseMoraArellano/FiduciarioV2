<?php
/**
 * modules/grupos/list.php
 * Listado de grupos con búsqueda y acciones
 */

// Cargar clase de gestión de grupos
require_once 'includes/GruposManager.php';

$gruposManager = new GruposManager();

// Obtener parámetros de filtro y paginación
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;

$filters = [
    'search' => $_GET['search'] ?? ''
];

// Obtener grupos
$result = $gruposManager->getGrupos($filters, $page, $perPage);
$grupos = $result['data'] ?? [];
$totalPages = $result['total_pages'] ?? 1;
$total = $result['total'] ?? 0;

// Obtener estadísticas
$stats = $gruposManager->getStats();

// Verificar permisos
$canCreate = $isAdmin || $session->hasPermission('catalogos', 'creer', 'grupos');
$canEdit = $isAdmin || $session->hasPermission('catalogos', 'modifier', 'grupos');
$canDelete = $isAdmin || $session->hasPermission('catalogos', 'supprimer', 'grupos');
?>

<div x-data="gruposListController()" x-init="init()">
    
    <!-- Estadísticas -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        
        <!-- Total Grupos -->
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 font-medium">Total Grupos</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total'] ?? 0; ?></p>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-users-cog text-2xl text-purple-600"></i>
                </div>
            </div>
        </div>
        
        <!-- Con Usuarios -->
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 font-medium">Con Usuarios</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $stats['con_usuarios'] ?? 0; ?></p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-user-check text-2xl text-green-600"></i>
                </div>
            </div>
        </div>
        
        <!-- Con Permisos -->
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 font-medium">Con Permisos</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $stats['con_permisos'] ?? 0; ?></p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-shield-alt text-2xl text-blue-600"></i>
                </div>
            </div>
        </div>
        
        <!-- Promedio Usuarios -->
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-orange-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 font-medium">Promedio Usuarios</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $stats['promedio_usuarios'] ?? 0; ?></p>
                </div>
                <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-chart-line text-2xl text-orange-600"></i>
                </div>
            </div>
        </div>
        
    </div>

    <!-- Barra de acciones y filtros -->
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="p-6">
            
            <!-- Fila superior: Título y botones -->
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-list text-purple-600"></i>
                    Listado de Grupos
                    <span class="text-sm font-normal text-gray-500">(<?php echo $total; ?> registros)</span>
                </h2>
                
                <div class="flex items-center gap-2">
                    <!-- Botón Volver a Usuarios -->
                    <a 
                        href="catalogos.php?mod=usuarios&action=list"
                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition flex items-center gap-2"
                    >
                        <i class="fas fa-users"></i>
                        <span class="hidden md:inline">Usuarios</span>
                    </a>
                    
<?php 
$canExport = $isAdmin || $session->hasPermission('catalogos', 'export', 'grupos');
if ($canExport): 
?>
<button
    @click="exportToCSV()"
    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center gap-2"
>
    <i class="fas fa-file-excel"></i>
    <span class="hidden md:inline">Exportar</span>
</button>
<?php endif; ?>
                    
                    <!-- Botón Crear -->
                    <?php if ($canCreate): ?>
                    <a 
                        href="catalogos.php?mod=grupos&action=create"
                        class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition flex items-center gap-2"
                    >
                        <i class="fas fa-plus"></i>
                        <span class="hidden md:inline">Nuevo Grupo</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Búsqueda -->
            <form method="GET" action="catalogos.php" class="flex gap-4">
                <input type="hidden" name="mod" value="grupos">
                <input type="hidden" name="action" value="list">
                
                <!-- Campo de búsqueda -->
                <div class="flex-1 max-w-md">
                    <div class="relative">
                        <input 
                            type="text" 
                            name="search" 
                            placeholder="Buscar grupos por nombre..."
                            value="<?php echo htmlspecialchars($filters['search']); ?>"
                            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                        >
                        <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                    </div>
                </div>
                
                <button 
                    type="submit"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center gap-2"
                >
                    <i class="fas fa-filter"></i>
                    Filtrar
                </button>
                
                <?php if (!empty($filters['search'])): ?>
                <a 
                    href="catalogos.php?mod=grupos&action=list"
                    class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition flex items-center gap-2"
                >
                    <i class="fas fa-times"></i>
                    Limpiar
                </a>
                <?php endif; ?>
            </form>
            
        </div>
    </div>

    <!-- Tabla de grupos -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        
        <?php if (empty($grupos)): ?>
            <!-- Sin resultados -->
            <div class="p-12 text-center">
                <i class="fas fa-users-cog text-6xl text-gray-300 mb-4"></i>
                <p class="text-xl text-gray-600 mb-2">No se encontraron grupos</p>
                <p class="text-gray-500 mb-4">Crea el primer grupo para organizar permisos</p>
                <?php if ($canCreate): ?>
                <a 
                    href="catalogos.php?mod=grupos&action=create"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition"
                >
                    <i class="fas fa-plus"></i>
                    Crear Primer Grupo
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
                                Grupo
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Descripción
                            </th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Usuarios
                            </th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Permisos
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Fecha Creación
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Acciones
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($grupos as $grupo): ?>
                        <tr class="hover:bg-gray-50 transition">
                            
                            <!-- Nombre del grupo -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-users text-purple-600"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($grupo['nom']); ?>
                                        </p>
                                        <p class="text-xs text-gray-500">ID: <?php echo $grupo['id']; ?></p>
                                    </div>
                                </div>
                            </td>
                            
                            <!-- Descripción -->
                            <td class="px-6 py-4">
                                <p class="text-sm text-gray-900 max-w-xs truncate">
                                    <?php echo htmlspecialchars($grupo['note'] ?? 'Sin descripción'); ?>
                                </p>
                            </td>
                            
                            <!-- Total Usuarios -->
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                    <i class="fas fa-users mr-1"></i>
                                    <?php echo $grupo['total_usuarios']; ?>
                                </span>
                            </td>
                            
                            <!-- Total Permisos -->
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                    <i class="fas fa-key mr-1"></i>
                                    <?php echo $grupo['total_permisos']; ?>
                                </span>
                            </td>
                            
                            <!-- Fecha Creación -->
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo date('d/m/Y', strtotime($grupo['datec'])); ?>
                            </td>
                            
                            <!-- Acciones -->
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end gap-2">
                                    
                                    <!-- Ver/Editar -->
                                    <?php if ($canEdit): ?>
                                    <a 
                                        href="catalogos.php?mod=grupos&action=edit&id=<?php echo $grupo['id']; ?>"
                                        class="text-blue-600 hover:text-blue-900 transition"
                                        title="Editar"
                                    >
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <!-- Permisos -->
                                    <?php if ($isAdmin || $canEdit): ?>
                                    <a 
                                        href="catalogos.php?mod=grupos&action=permissions&id=<?php echo $grupo['id']; ?>"
                                        class="text-green-600 hover:text-green-900 transition"
                                        title="Gestionar Permisos"
                                    >
                                        <i class="fas fa-key"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <!-- Usuarios -->
                                    <?php if ($canEdit): ?>
                                    <a 
                                        href="catalogos.php?mod=grupos&action=usuarios&id=<?php echo $grupo['id']; ?>"
                                        class="text-purple-600 hover:text-purple-900 transition"
                                        title="Gestionar Usuarios"
                                    >
                                        <i class="fas fa-users"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <!-- Duplicar -->
                                    <?php if ($canCreate): ?>
                                    <button
                                        @click="duplicateGrupo(<?php echo $grupo['id']; ?>, '<?php echo htmlspecialchars($grupo['nom']); ?>')"
                                        class="text-orange-600 hover:text-orange-900 transition"
                                        title="Duplicar Grupo"
                                    >
                                        <i class="fas fa-copy"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <!-- Eliminar -->
                                    <?php if ($canDelete): ?>
                                    <button
                                        @click="deleteGrupo(<?php echo $grupo['id']; ?>, '<?php echo htmlspecialchars($grupo['nom']); ?>', <?php echo $grupo['total_usuarios']; ?>)"
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
                            href="?mod=grupos&action=list&page=<?php echo $page - 1; ?>&<?php echo http_build_query($filters); ?>"
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
                            href="?mod=grupos&action=list&page=<?php echo $i; ?>&<?php echo http_build_query($filters); ?>"
                            class="px-3 py-2 <?php echo $i == $page ? 'bg-purple-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?> border border-gray-300 rounded-lg transition"
                        >
                            <?php echo $i; ?>
                        </a>
                        <?php endfor; ?>
                        
                        <!-- Siguiente -->
                        <?php if ($page < $totalPages): ?>
                        <a 
                            href="?mod=grupos&action=list&page=<?php echo $page + 1; ?>&<?php echo http_build_query($filters); ?>"
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
// Controlador Alpine.js para la lista de grupos
function gruposListController() {
    return {
        init() {
            console.log('Lista de grupos inicializada');
        },
        
        // Duplicar grupo
        async duplicateGrupo(grupoId, grupoNom) {
            if (!confirm(`¿Deseas duplicar el grupo "${grupoNom}"?\n\nSe creará una copia con los mismos permisos.`)) {
                return;
            }
            
            try {
                const response = await fetch('modules/grupos/actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=duplicate&id=${grupoId}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(`Grupo duplicado exitosamente.\n\nNuevo grupo: ${result.new_nombre}`);
                    location.reload();
                } else {
                    alert(`Error: ${result.message}`);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error al duplicar grupo');
            }
        },
        
        // Eliminar grupo
        async deleteGrupo(grupoId, grupoNom, totalUsuarios) {
            if (totalUsuarios > 0) {
                alert(`No se puede eliminar el grupo "${grupoNom}".\n\nTiene ${totalUsuarios} usuario(s) asignado(s).\n\nPrimero debes remover todos los usuarios del grupo.`);
                return;
            }
            
            if (!confirm(`¿Estás seguro de eliminar el grupo "${grupoNom}"?\n\nEsta acción no se puede deshacer.`)) {
                return;
            }
            
            try {
                const response = await fetch('modules/grupos/actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete&id=${grupoId}`
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
                alert('Error al eliminar grupo');
            }
        },
        
        // Exportar a CSV
        async exportToCSV() {
            const params = new URLSearchParams(window.location.search);
            params.set('action', 'export-csv');
            
            window.location.href = `modules/grupos/actions.php?${params.toString()}`;
        }
    };
}
</script>