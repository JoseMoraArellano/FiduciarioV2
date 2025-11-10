
<?php
require_once 'includes/cliente.manager.php';
require_once 'modules/clientes/permissions.php';

$clientesManager = new ClientesManager();
$pageTitle = 'Lista de Clientes';

// Verificar permisos de lectura
if (!$clientePermissions->canView()) {
    echo '<div class="p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded">';
    echo '<i class="fas fa-lock mr-2"></i>';
    echo 'No tienes permiso para ver la lista de clientes';
    echo '</div>';
    return;
}

// Parámetros de paginación y búsqueda
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 20;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterActivo = isset($_GET['activo']) ? $_GET['activo'] : '';
$filterTipo = isset($_GET['tipo_persona']) ? $_GET['tipo_persona'] : '';

// Obtener clientes
$result = $clientesManager->getClientes([
    'page' => $page,
    'per_page' => $perPage,
    'search' => $search,
    'activo' => $filterActivo,
    'tipo_persona' => $filterTipo
]);

$clientes = $result['data'] ?? [];
$total = $result['total'] ?? 0;
$totalPages = ceil($total / $perPage);

// Obtener estadísticas
$stats = $clientesManager->getEstadisticasClientes();

// Verificar permisos
$canCreate = $isAdmin || $session->hasPermission('catalogos', 'creer', 'clientes');
$canEdit = $isAdmin || $session->hasPermission('catalogos', 'modifier', 'clientes');
$canDelete = $isAdmin || $session->hasPermission('catalogos', 'supprimer', 'clientes');

?>

<div x-data="clientesListController()" x-init="init()">
    
    <!-- Header -->
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="p-6 border-b flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-users text-blue-600"></i>
                    <?php echo $pageTitle; ?>
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Gestión y administración de clientes
                </p>
            </div>
            
            <?php if ($clientePermissions->canCreate()): ?>
            <a 
                href="catalogos.php?mod=clientes&action=form"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center gap-2"
            >
                <i class="fas fa-plus"></i>
                Nuevo Cliente
            </a>
            <?php endif; ?>
        </div>
        
        <!-- Estadísticas -->
        <div class="p-4 bg-gray-50 border-b">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="text-center p-3 bg-white rounded-lg border">
                    <p class="text-2xl font-bold text-blue-600"><?php echo $stats['total'] ?? 0; ?></p>
                    <p class="text-sm text-gray-600">Total Clientes</p>
                </div>
                <div class="text-center p-3 bg-white rounded-lg border">
                    <p class="text-2xl font-bold text-green-600"><?php echo $stats['activos'] ?? 0; ?></p>
                    <p class="text-sm text-gray-600">Activos</p>
                </div>
                <div class="text-center p-3 bg-white rounded-lg border">
                    <p class="text-2xl font-bold text-red-600"><?php echo $stats['inactivos'] ?? 0; ?></p>
                    <p class="text-sm text-gray-600">Inactivos</p>
                </div>
                <div class="text-center p-3 bg-white rounded-lg border">
                    <p class="text-2xl font-bold text-orange-600"><?php echo $stats['alto_riesgo'] ?? 0; ?></p>
                    <p class="text-sm text-gray-600">Alto Riesgo</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros y Búsqueda -->
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="p-4 border-b">
            <form method="GET" action="catalogos.php" x-ref="searchForm">
                <input type="hidden" name="mod" value="clientes">
                <input type="hidden" name="action" value="list">
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <!-- Búsqueda general -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Búsqueda</label>
                        <div class="relative">
                            <input 
                                type="text" 
                                name="search" 
                                value="<?php echo htmlspecialchars($search); ?>"
                                placeholder="Nombre, RFC, Email..."
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            >
                            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                        </div>
                    </div>
                    
                    <!-- Filtro por estado -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                        <select 
                            name="activo"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            @change="$refs.searchForm.submit()"
                        >
                            <option value="">Todos</option>
                            <option value="1" <?php echo $filterActivo === '1' ? 'selected' : ''; ?>>Activos</option>
                            <option value="0" <?php echo $filterActivo === '0' ? 'selected' : ''; ?>>Inactivos</option>
                        </select>
                    </div>
                    
                    <!-- Filtro por tipo de persona -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de Persona</label>
                        <select 
                            name="tipo_persona"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            @change="$refs.searchForm.submit()"
                        >
                            <option value="">Todos</option>
                            <option value="FISICA" <?php echo $filterTipo === 'FISICA' ? 'selected' : ''; ?>>Persona Física</option>
                            <option value="MORAL" <?php echo $filterTipo === 'MORAL' ? 'selected' : ''; ?>>Persona Moral</option>
                        </select>
                    </div>
                    
                    <!-- Botones de acción -->
                    <div class="flex items-end gap-2">
                        <button 
                            type="submit"
                            class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center justify-center gap-2"
                        >
                            <i class="fas fa-filter"></i>
                            Filtrar
                        </button>
                        <a 
                            href="catalogos.php?mod=clientes&action=list"
                            class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition flex items-center gap-2"
                        >
                            <i class="fas fa-redo"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Resultados -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        
        <!-- Header de la tabla -->
        <div class="px-6 py-4 border-b flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600">
                    Mostrando <span class="font-medium"><?php echo count($clientes); ?></span> de 
                    <span class="font-medium"><?php echo $total; ?></span> clientes
                </p>
            </div>
            
            <?php if ($clientePermissions->canExport()): ?>
            <div class="flex items-center gap-2">
                <button 
                    @click="exportarClientes()"
                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center gap-2"
                >
                    <i class="fas fa-file-export"></i>
                    Exportar
                </button>
            </div>
            <?php endif; ?>
        </div>

        <!-- Tabla de clientes -->
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Cliente
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Contacto
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Información Fiscal
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Estado
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Acciones
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($clientes)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                            <i class="fas fa-users text-4xl text-gray-300 mb-2"></i>
                            <p class="text-lg">No se encontraron clientes</p>
                            <?php if ($search || $filterActivo || $filterTipo): ?>
                            <p class="text-sm mt-1">Intenta ajustar los filtros de búsqueda</p>
                            <?php elseif ($clientePermissions->canCreate()): ?>
                            <a 
                                href="catalogos.php?mod=clientes&action=form"
                                class="inline-block mt-3 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
                            >
                                <i class="fas fa-plus mr-2"></i>Crear primer cliente
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($clientes as $cliente): ?>
                    <tr class="hover:bg-gray-50 transition">
                        <!-- Información del cliente -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10 bg-blue-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-user-tie text-blue-600"></i>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($cliente['nombres'] . ' ' . ($cliente['paterno'] ?? '') . ' ' . ($cliente['materno'] ?? '')); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?php echo $cliente['tipo_persona'] === 'MORAL' ? 'Persona Moral' : 'Persona Física'; ?>
                                        <?php if ($cliente['altoriesg']): ?>
                                        <span class="ml-2 px-2 py-0.5 bg-red-100 text-red-800 text-xs rounded-full">Alto Riesgo</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        
                        <!-- Contacto -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">
                                <?php if ($cliente['emal']): ?>
                                <div class="flex items-center gap-1 mb-1">
                                    <i class="fas fa-envelope text-gray-400 w-4"></i>
                                    <span class="truncate max-w-xs"><?php echo htmlspecialchars($cliente['emal']); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($cliente['tel']): ?>
                                <div class="flex items-center gap-1">
                                    <i class="fas fa-phone text-gray-400 w-4"></i>
                                    <span><?php echo htmlspecialchars($cliente['tel']); ?></span>
                                    <?php if ($cliente['ext']): ?>
                                    <span class="text-gray-500">ext. <?php echo htmlspecialchars($cliente['ext']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </td>
                        
                        <!-- Información Fiscal -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">
                                <?php if ($cliente['rfc']): ?>
                                <div class="mb-1">
                                    <span class="font-medium">RFC:</span>
                                    <span class="font-mono"><?php echo htmlspecialchars($cliente['rfc']); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($cliente['curp']): ?>
                                <div>
                                    <span class="font-medium">CURP:</span>
                                    <span class="font-mono"><?php echo htmlspecialchars($cliente['curp']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </td>
                        
                        <!-- Estado -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex flex-col gap-1">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $cliente['activo'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo $cliente['activo'] ? 'Activo' : 'Inactivo'; ?>
                                </span>
                                <?php if ($cliente['fideicomitente'] || $cliente['fideicomisario']): ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                    Fideicomiso
                                </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        
                        <!-- Acciones -->
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex items-center gap-2">
                                <?php if ($clientePermissions->canView()): ?>
                                <a 
                                    href="catalogos.php?mod=clientes&action=view&id=<?php echo $cliente['id']; ?>"
                                    class="text-blue-600 hover:text-blue-900 transition"
                                    title="Ver detalles"
                                >
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($clientePermissions->canEdit()): ?>
                                <a 
                                    href="catalogos.php?mod=clientes&action=form&id=<?php echo $cliente['id']; ?>"
                                    class="text-green-600 hover:text-green-900 transition"
                                    title="Editar"
                                >
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($clientePermissions->canDelete()): ?>
                                <button 
                                    @click="confirmarEliminacion(<?php echo $cliente['id']; ?>, '<?php echo addslashes($cliente['nombres'] . ' ' . ($cliente['paterno'] ?? '')); ?>')"
                                    class="text-red-600 hover:text-red-900 transition"
                                    title="Eliminar"
                                >
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                                
                                <?php if ($clientePermissions->canManageDocuments()): ?>
                                <a 
                                    href="catalogos.php?mod=clientes&action=documents&id=<?php echo $cliente['id']; ?>"
                                    class="text-purple-600 hover:text-purple-900 transition"
                                    title="Documentos"
                                >
                                    <i class="fas fa-folder"></i>
                                    <?php if (($cliente['total_documentos'] ?? 0) > 0): ?>
                                    <span class="text-xs bg-purple-100 text-purple-800 rounded-full px-1 ml-1">
                                        <?php echo $cliente['total_documentos']; ?>
                                    </span>
                                    <?php endif; ?>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <?php if ($totalPages > 1): ?>
        <div class="px-6 py-4 border-t bg-gray-50">
            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-700">
                    Página <span class="font-medium"><?php echo $page; ?></span> de 
                    <span class="font-medium"><?php echo $totalPages; ?></span>
                </div>
                
                <div class="flex items-center gap-1">
                    <!-- Primera página -->
                    <?php if ($page > 1): ?>
                    <a 
                        href="catalogos.php?mod=clientes&action=list&page=1&search=<?php echo urlencode($search); ?>&activo=<?php echo $filterActivo; ?>&tipo_persona=<?php echo $filterTipo; ?>"
                        class="px-3 py-1 border border-gray-300 rounded text-sm text-gray-700 hover:bg-gray-50 transition"
                    >
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                    <?php endif; ?>
                    
                    <!-- Página anterior -->
                    <?php if ($page > 1): ?>
                    <a 
                        href="catalogos.php?mod=clientes&action=list&page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&activo=<?php echo $filterActivo; ?>&tipo_persona=<?php echo $filterTipo; ?>"
                        class="px-3 py-1 border border-gray-300 rounded text-sm text-gray-700 hover:bg-gray-50 transition"
                    >
                        <i class="fas fa-angle-left"></i>
                    </a>
                    <?php endif; ?>
                    
                    <!-- Números de página -->
                    <?php 
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    for ($i = $startPage; $i <= $endPage; $i++): 
                    ?>
                    <a 
                        href="catalogos.php?mod=clientes&action=list&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&activo=<?php echo $filterActivo; ?>&tipo_persona=<?php echo $filterTipo; ?>"
                        class="px-3 py-1 border text-sm transition <?php echo $i == $page ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 text-gray-700 hover:bg-gray-50'; ?>"
                    >
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>
                    
                    <!-- Página siguiente -->
                    <?php if ($page < $totalPages): ?>
                    <a 
                        href="catalogos.php?mod=clientes&action=list&page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&activo=<?php echo $filterActivo; ?>&tipo_persona=<?php echo $filterTipo; ?>"
                        class="px-3 py-1 border border-gray-300 rounded text-sm text-gray-700 hover:bg-gray-50 transition"
                    >
                        <i class="fas fa-angle-right"></i>
                    </a>
                    <?php endif; ?>
                    
                    <!-- Última página -->
                    <?php if ($page < $totalPages): ?>
                    <a 
                        href="catalogos.php?mod=clientes&action=list&page=<?php echo $totalPages; ?>&search=<?php echo urlencode($search); ?>&activo=<?php echo $filterActivo; ?>&tipo_persona=<?php echo $filterTipo; ?>"
                        class="px-3 py-1 border border-gray-300 rounded text-sm text-gray-700 hover:bg-gray-50 transition"
                    >
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function clientesListController() {
    return {
        init() {
        },
        
        async confirmarEliminacion(clienteId, clienteNombre) {
            if (!confirm(`¿Estás seguro de eliminar al cliente "${clienteNombre}"?\n\nEsta acción no se puede deshacer.`)) {
                return;
            }
            
            try {
                const response = await fetch('modules/clientes/accion.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=delete&id=${clienteId}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    this.mostrarAlerta('success', 'Cliente eliminado', 'El cliente ha sido eliminado correctamente');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    this.mostrarAlerta('error', 'Error', result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                this.mostrarAlerta('error', 'Error', 'No se pudo eliminar el cliente');
            }
        },
        
        async exportarClientes() {
            try {
                // Mostrar indicador de carga
                const boton = event.target;
                const textoOriginal = boton.innerHTML;
                boton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exportando...';
                boton.disabled = true;
                
                const params = new URLSearchParams(window.location.search);
                params.set('action', 'export');
                
                const response = await fetch(`catalogos.php?${params.toString()}`);
                const blob = await response.blob();
                
                // Crear enlace de descarga
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `clientes_${new Date().toISOString().split('T')[0]}.xlsx`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                
                this.mostrarAlerta('success', 'Exportación completada', 'Los datos se han exportado correctamente');
                
            } catch (error) {
                console.error('Error:', error);
                this.mostrarAlerta('error', 'Error', 'No se pudo exportar los datos');
            } finally {
                // Restaurar botón
                const boton = event.target;
                boton.innerHTML = '<i class="fas fa-file-export"></i> Exportar';
                boton.disabled = false;
            }
        },
        
        mostrarAlerta(tipo, titulo, mensaje) {
            // Crear elemento de alerta
            const alerta = document.createElement('div');
            alerta.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 max-w-sm ${
                tipo === 'success' ? 'bg-green-50 border-l-4 border-green-500 text-green-700' :
                tipo === 'error' ? 'bg-red-50 border-l-4 border-red-500 text-red-700' :
                'bg-blue-50 border-l-4 border-blue-500 text-blue-700'
            }`;
            
            alerta.innerHTML = `
                <div class="flex items-start">
                    <i class="fas ${
                        tipo === 'success' ? 'fa-check-circle' :
                        tipo === 'error' ? 'fa-exclamation-circle' :
                        'fa-info-circle'
                    } mt-1 mr-3"></i>
                    <div class="flex-1">
                        <p class="font-medium">${titulo}</p>
                        <p class="text-sm mt-1">${mensaje}</p>
                    </div>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            document.body.appendChild(alerta);
            
            // Auto-eliminar después de 5 segundos
            setTimeout(() => {
                if (alerta.parentElement) {
                    alerta.remove();
                }
            }, 5000);
        }
    };
}
</script>