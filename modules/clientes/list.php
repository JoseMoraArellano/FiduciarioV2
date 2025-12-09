<?php
require_once 'includes/ClienteManager.php';
// require_once 'modules/clientes/permissions.php';

$clienteManager = new ClienteManager();

// Paginación
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;

// Filtros
$filters = [
    'search' => $_GET['search'] ?? '',
    'activo' => $_GET['activo'] ?? '',
    'tipo_persona' => $_GET['tipo_persona'] ?? '',
    'regimen_fiscal' => $_GET['regimen_fiscal'] ?? '',
    'edo' => $_GET['edo'] ?? '',
    'altoriesg' => $_GET['altoriesg'] ?? '',
    'fecha_desde' => $_GET['fecha_desde'] ?? '',
    'fecha_hasta' => $_GET['fecha_hasta'] ?? ''
];

// Obtener datos
$result = $clienteManager->getClientes($filters, $page, $perPage);
$clientes = $result['data'] ?? [];
$totalPages = $result['total_pages'] ?? 1;
$total = $result['total'] ?? 0;

// Obtener estadísticas
$stats = $clienteManager->getStats();

// Obtener listas para filtros
$estados = $clienteManager->getEstados();
$regimenesFiscales = $clienteManager->getRegimenesFiscales();

// Verificar permisos
$canCreate = $isAdmin || $session->hasPermission('catalogos', 'creer', 'clientes');
$canEdit = $isAdmin || $session->hasPermission('catalogos', 'modifier', 'clientes');
$canDelete = $isAdmin || $session->hasPermission('catalogos', 'supprimer', 'clientes');
$canExport = $isAdmin || $session->hasPermission('catalogos', 'export', 'clientes');
$canVerifyQSQ = $isAdmin || $session->hasPermission('catalogos', 'verify_qsq', 'clientes');
$canViewDocs = $isAdmin || $session->hasPermission('catalogos', 'documents', 'clientes');
?>

<div x-data="clientesListController()" x-init="init()">
    
    <!-- Estadísticas -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        
        <!-- Total Clientes -->
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 font-medium">Total Clientes</p>
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
                    <i class="fas fa-check-circle text-2xl text-green-600"></i>
                </div>
            </div>
        </div>
        
        <!-- Personas Físicas -->
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 font-medium">P. Físicas</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $stats['tipo_fisica'] ?? 0; ?></p>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-user text-2xl text-purple-600"></i>
                </div>
            </div>
        </div>
        
        <!-- Personas Morales -->
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-indigo-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 font-medium">P. Morales</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $stats['tipo_moral'] ?? 0; ?></p>
                </div>
                <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-building text-2xl text-indigo-600"></i>
                </div>
            </div>
        </div>
        
        <!-- Alto Riesgo -->
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-red-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 font-medium">Alto Riesgo</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $stats['alto_riesgo'] ?? 0; ?></p>
                </div>
                <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-2xl text-red-600"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filtros y acciones -->
    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <form method="GET" action="" class="space-y-4">
            <input type="hidden" name="mod" value="clientes">
            <input type="hidden" name="action" value="list">
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                
                <!-- Búsqueda -->
                <div class="lg:col-span-2">
                    <div class="relative">
                        <input 
                            type="text" 
                            name="search" 
                            value="<?php echo htmlspecialchars($filters['search']); ?>"
                            placeholder="Buscar por nombre, RFC, CURP o email..."
                            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                        <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                    </div>
                </div>
                
                <!-- Estado -->
                <div>
                    <select 
                        name="activo" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                        <option value="">Todos los estados</option>
                        <option value="1" <?php echo $filters['activo'] === '1' ? 'selected' : ''; ?>>Activos</option>
                        <option value="0" <?php echo $filters['activo'] === '0' ? 'selected' : ''; ?>>Inactivos</option>
                    </select>
                </div>
                
                <!-- Tipo Persona -->
                <div>
                    <select 
                        name="tipo_persona" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                        <option value="">Todos los tipos</option>
                        <option value="FISICA" <?php echo $filters['tipo_persona'] === 'FISICA' ? 'selected' : ''; ?>>Persona Física</option>
                        <option value="MORAL" <?php echo $filters['tipo_persona'] === 'MORAL' ? 'selected' : ''; ?>>Persona Moral</option>
                    </select>
                </div>
                
                <!-- Régimen Fiscal -->
                <div>
                    <select 
                        name="regimen_fiscal" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                        <option value="">Todos los regímenes</option>
                        <?php foreach ($regimenesFiscales as $regimen): ?>
                        <option value="<?php echo $regimen['id']; ?>" 
                                <?php echo $filters['regimen_fiscal'] == $regimen['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($regimen['descripcion']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Estado (Entidad) -->
                <div>
                    <select 
                        name="edo" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                        <option value="">Todos los estados</option>
                        <?php foreach ($estados as $estado): ?>
                        <option value="<?php echo $estado['id']; ?>" 
                                <?php echo $filters['edo'] == $estado['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($estado['nom']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Alto Riesgo -->
                <div>
                    <select 
                        name="altoriesg" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                        <option value="">Todos</option>
                        <option value="1" <?php echo $filters['altoriesg'] === '1' ? 'selected' : ''; ?>>Solo Alto Riesgo</option>
                        <option value="0" <?php echo $filters['altoriesg'] === '0' ? 'selected' : ''; ?>>Sin Alto Riesgo</option>
                    </select>
                </div>
                
                <!-- Fecha desde -->
                <div>
                    <input 
                        type="date" 
                        name="fecha_desde" 
                        value="<?php echo $filters['fecha_desde']; ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Desde"
                    >
                </div>
                
                <!-- Fecha hasta -->
                <div>
                    <input 
                        type="date" 
                        name="fecha_hasta" 
                        value="<?php echo $filters['fecha_hasta']; ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Hasta"
                    >
                </div>
            </div>
            
            <!-- Botones de acción -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <button 
                        type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center gap-2"
                    >
                        <i class="fas fa-filter"></i> Filtrar
                    </button>
                    
                    <a 
                        href="catalogos.php?mod=clientes&action=list"
                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition flex items-center gap-2"
                    >
                        <i class="fas fa-eraser mr-1"></i> Limpiar filtros
                    </a>
                </div>
                
                <div class="flex items-center gap-2">
                    <?php if ($canExport): ?>
                    <!-- Dropdown para exportación -->
                    <div class="relative" x-data="{ open: false }">
                        <button 
                            @click="open = !open"
                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center gap-2"
                        >
                            <i class="fas fa-file-export"></i>
                            Exportar
                            <i class="fas fa-chevron-down text-xs ml-1"></i>
                        </button>
                        
                        <!-- Dropdown menu -->
                        <div 
                            x-show="open" 
                            @click.away="open = false"
                            class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border z-10"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="transform opacity-0 scale-95"
                            x-transition:enter-end="transform opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="transform opacity-100 scale-100"
                            x-transition:leave-end="transform opacity-0 scale-95"
                        >
                            <div class="py-1">
                                <a 
                                    href="#" 
                                    @click="exportToExcel(); open = false"
                                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-2"
                                >
                                    <i class="fas fa-file-excel text-green-600"></i>
                                    Exportar a Excel
                                </a>
                                <a 
                                    href="#" 
                                    @click="exportToCSV(); open = false"
                                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-2"
                                >
                                    <i class="fas fa-file-csv text-blue-600"></i>
                                    Exportar a CSV
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($canCreate): ?>
                    <a 
                        href="catalogos.php?mod=clientes&action=create"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center gap-2"
                    >
                        <i class="fas fa-plus"></i>
                        Nuevo Cliente
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Tabla de clientes -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        
        <?php if (empty($clientes)): ?>
            <!-- Sin resultados -->
            <div class="p-12 text-center">
                <i class="fas fa-users text-6xl text-gray-300 mb-4"></i>
                <p class="text-xl text-gray-600 mb-2">No se encontraron clientes</p>
                <p class="text-gray-500 mb-4">Intenta ajustar los filtros o crear un nuevo cliente</p>
                <?php if ($canCreate): ?>
                <a 
                    href="catalogos.php?mod=clientes&action=create"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
                >
                    <i class="fas fa-plus"></i>
                    Crear Primer Cliente
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
                                Cliente
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                RFC / CURP
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Tipo
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Contacto
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Estado
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Riesgo
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Docs
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Acciones
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($clientes as $cliente): ?>
                        <tr class="hover:bg-gray-50 transition">
                            
                            <!-- Cliente -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                                        <span class="text-blue-600 font-bold">
                                            <?php 
                                            if ($cliente['tipo_persona'] == 'MORAL') {
                                                echo '<i class="fas fa-building"></i>';
                                            } else {
                                                echo strtoupper(substr($cliente['nombres'], 0, 1));
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($cliente['nombre_completo']); ?>
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            ID: <?php echo $cliente['id']; ?>
                                            <?php if ($cliente['regimen_fiscal_desc']): ?>
                                            | <?php echo htmlspecialchars($cliente['regimen_fiscal_desc']); ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                            </td>
                            
                            <!-- RFC/CURP -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm">
                                    <?php if ($cliente['rfc']): ?>
                                    <p class="text-gray-900 font-mono"><?php echo htmlspecialchars($cliente['rfc']); ?></p>
                                    <?php endif; ?>
                                    <?php if ($cliente['curp']): ?>
                                    <p class="text-xs text-gray-500 font-mono"><?php echo htmlspecialchars($cliente['curp']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!$cliente['rfc'] && !$cliente['curp']): ?>
                                    <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            
                            <!-- Tipo -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs rounded-full <?php echo $cliente['tipo_persona'] == 'MORAL' ? 'bg-indigo-100 text-indigo-800' : 'bg-purple-100 text-purple-800'; ?>">
                                    <?php echo $cliente['tipo_persona'] == 'MORAL' ? 'Moral' : 'Física'; ?>
                                </span>
                            </td>
                            
                            <!-- Contacto -->
                            <td class="px-6 py-4">
                                <div class="text-sm">
                                    <?php if ($cliente['emal']): ?>
                                    <p class="text-gray-900"><?php echo htmlspecialchars($cliente['emal']); ?></p>
                                    <?php endif; ?>
                                    <?php if ($cliente['tel']): ?>
                                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($cliente['tel']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!$cliente['emal'] && !$cliente['tel']): ?>
                                    <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            
                            <!-- Estado -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($cliente['activo']): ?>
                                <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                                    <i class="fas fa-check-circle mr-1"></i>Activo
                                </span>
                                <?php else: ?>
                                <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">
                                    <i class="fas fa-times-circle mr-1"></i>Inactivo
                                </span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Riesgo -->
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <?php if ($cliente['altoriesg']): ?>
                                <span class="text-red-600 font-bold" title="Alto Riesgo">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </span>
                                <?php endif; ?>
                                <?php if ($cliente['fideicomitente'] || $cliente['fideicomisario']): ?>
                                <span class="text-blue-600" title="Fideicomiso">
                                    <i class="fas fa-file-contract"></i>
                                </span>
                                <?php endif; ?>
                                <?php if (!$cliente['altoriesg'] && !$cliente['fideicomitente'] && !$cliente['fideicomisario']): ?>
                                <span class="text-green-600">
                                    <i class="fas fa-check"></i>
                                </span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Documentos -->
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <?php if ($canViewDocs && $cliente['total_documentos'] > 0): ?>
                                <span class="px-2 py-1 text-xs bg-gray-100 text-gray-800 rounded-full">
                                    <?php echo $cliente['total_documentos']; ?>
                                </span>
                                <?php else: ?>
                                <span class="text-gray-400">0</span>
                                <?php endif; ?>
                            </td>
                            
<!-- Acciones -->
<td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
    <div class="flex items-center justify-end gap-2">
        
        <!-- Ver Detalles -->
        <button 
            @click="viewCliente(<?php echo $cliente['id']; ?>)"
            class="text-gray-600 hover:text-gray-900 transition"
            title="Ver detalles"
        >
            <i class="fas fa-eye"></i>
        </button>
        
        <?php if ($canEdit): ?>
        <!-- Editar -->
        <button 
            @click="editCliente(<?php echo $cliente['id']; ?>)"
            class="text-blue-600 hover:text-blue-900 transition"
            title="Editar cliente"
        >
            <i class="fas fa-edit"></i>
        </button>
        <?php endif; ?>
        
        <?php if ($canVerifyQSQ): ?>
        <!-- Verificar QSQ -->
        <button 
            @click="verifyQSQ(<?php echo $cliente['id']; ?>, '<?php echo htmlspecialchars(addslashes($cliente['nombre_completo'])); ?>')"
            class="text-orange-600 hover:text-orange-900 transition <?php echo $cliente['altoriesg'] ? 'animate-pulse' : ''; ?>"
            title="Verificar QSQ <?php echo $cliente['altoriesg'] ? '(Alto Riesgo)' : ''; ?>"
        >
            <i class="fas fa-shield-alt"></i>
        </button>
        <?php endif; ?>
        
        <?php if ($canEdit): ?>
        <!-- Toggle Estado -->
        <button 
            @click="toggleStatus(<?php echo $cliente['id']; ?>, '<?php echo htmlspecialchars(addslashes($cliente['nombre_completo'])); ?>', <?php echo $cliente['activo'] ? '1' : '0'; ?>)"
            class="<?php echo $cliente['activo'] ? 'text-yellow-600 hover:text-yellow-900' : 'text-green-600 hover:text-green-900'; ?> transition"
            title="<?php echo $cliente['activo'] ? 'Desactivar cliente' : 'Activar cliente'; ?>"
        >
            <i class="fas fa-power-off"></i>
        </button>
        <?php endif; ?>
        
        <?php if ($canDelete): ?>
        <!-- Eliminar -->
        <button 
            @click="deleteCliente(<?php echo $cliente['id']; ?>, '<?php echo htmlspecialchars(addslashes($cliente['nombre_completo'])); ?>')"
            class="text-red-600 hover:text-red-900 transition"
            title="Eliminar cliente"
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
            <div class="px-6 py-3 bg-gray-50 border-t flex items-center justify-between">
                <div class="text-sm text-gray-700">
                    Mostrando 
                    <span class="font-medium"><?php echo (($page - 1) * $perPage) + 1; ?></span>
                    a 
                    <span class="font-medium"><?php echo min($page * $perPage, $total); ?></span>
                    de 
                    <span class="font-medium"><?php echo $total; ?></span>
                    resultados
                </div>
                
                <div class="flex items-center gap-2">
                    <?php
                    $queryParams = $_GET;
                    unset($queryParams['page']);
                    $baseUrl = 'catalogos.php?' . http_build_query($queryParams);
                    ?>
                    
                    <!-- Anterior -->
                    <?php if ($page > 1): ?>
                    <a 
                        href="<?php echo $baseUrl . '&page=' . ($page - 1); ?>"
                        class="px-3 py-1 bg-white border border-gray-300 rounded hover:bg-gray-50 transition"
                    >
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <?php endif; ?>
                    
                    <!-- Páginas -->
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    for ($i = $startPage; $i <= $endPage; $i++):
                    ?>
                    <a 
                        href="<?php echo $baseUrl . '&page=' . $i; ?>"
                        class="px-3 py-1 <?php echo $i == $page ? 'bg-blue-600 text-white' : 'bg-white border border-gray-300 hover:bg-gray-50'; ?> rounded transition"
                    >
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>
                    
                    <!-- Siguiente -->
                    <?php if ($page < $totalPages): ?>
                    <a 
                        href="<?php echo $baseUrl . '&page=' . ($page + 1); ?>"
                        class="px-3 py-1 bg-white border border-gray-300 rounded hover:bg-gray-50 transition"
                    >
                        <i class="fas fa-chevron-right"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
        <?php endif; ?>
    </div>
    
</div>

<script>
function clientesListController() {
    return {
        init() {

        },
        

        // Ver detalles del cliente
        viewCliente(clienteId) {
            window.location.href = `catalogos.php?mod=clientes&action=view&id=${clienteId}`;
        },
        // Editar cliente
        editCliente(clienteId) {
            window.location.href = `catalogos.php?mod=clientes&action=edit&id=${clienteId}`;
        },

        // Verificar QSQ de un cliente
        async verifyQSQ(clienteId, clienteNombre) {
            if (!confirm(`¿Deseas verificar el cliente "${clienteNombre}" en las listas negras?\n\nEsta acción puede tomar unos segundos.`)) {
                return;
            }
            ;
            try {
                // Mostrar loading en el botón
                const buttons = document.querySelectorAll(`button[onclick*="verifyQSQ(${clienteId}"]`);
                buttons.forEach(btn => {
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                    btn.disabled = true;
                });
                
                const response = await fetch('modules/clientes/action.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=verify_qsq_single&id=${clienteId}`
                });
                
                const result = await response.json();
                
                // Restaurar botones
                buttons.forEach(btn => {
                    btn.innerHTML = '<i class="fas fa-shield-alt"></i>';
                    btn.disabled = false;
                });
                
                if (result.success) {
                    let message = `✓ Verificación QSQ completada para "${clienteNombre}"\n\n`;
                    
                    if (result.data.valid) {
                        message += "✅ Cliente verificado correctamente\n";
                    } else {
                        message += "⚠ Se encontraron alertas\n";
                    }
                    
                    if (result.data.messages && result.data.messages.length > 0) {
                        message += "\nDetalles:\n• " + result.data.messages.join('\n• ');
                    }
                    
                    if (result.data.requires_approval) {
                        message += "\n\n🔒 Este cliente requiere aprobación adicional";
                    }
                    
                    alert(message);
                    
                    // Recargar para mostrar posibles cambios de estado
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                    
                } else {
                    alert(`❌ Error en verificación QSQ: ${result.message}`);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('❌ Error al conectar con el servidor para verificación QSQ');
                
                // Restaurar botones en caso de error
                const buttons = document.querySelectorAll(`button[onclick*="verifyQSQ(${clienteId}"]`);
                buttons.forEach(btn => {
                    btn.innerHTML = '<i class="fas fa-shield-alt"></i>';
                    btn.disabled = false;
                });
            }
        },

        // Eliminar cliente
        async deleteCliente(clienteId, clienteNombre) {
            if (!confirm(`⚠️ ELIMINACIÓN DE CLIENTE\n\n¿Estás seguro de eliminar el cliente "${clienteNombre}"?\n\nEsta acción marcará al cliente como inactivo y no se podrá deshacer fácilmente.`)) {
                return;
            }
            
            if (!confirm(`🚨 CONFIRMACIÓN FINAL\n\n¿Realmente deseas eliminar definitivamente el cliente "${clienteNombre}"?\n\nEsta acción es permanente y afectará todos los registros relacionados.`)) {
                return;
            }
            
            try {
                // Mostrar loading en el botón
                const buttons = document.querySelectorAll(`button[onclick*="deleteCliente(${clienteId}"]`);
                buttons.forEach(btn => {
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                    btn.disabled = true;
                });
                
                const response = await fetch('modules/clientes/action.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete&id=${clienteId}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(`✅ ${result.message}`);
                    location.reload();
                } else {
                    alert(`❌ Error al eliminar: ${result.message}`);
                    
                    // Restaurar botones en caso de error
                    buttons.forEach(btn => {
                        btn.innerHTML = '<i class="fas fa-trash"></i>';
                        btn.disabled = false;
                    });
                }
            } catch (error) {
                console.error('Error:', error);
                alert('❌ Error al eliminar cliente');
                
                // Restaurar botones en caso de error
                const buttons = document.querySelectorAll(`button[onclick*="deleteCliente(${clienteId}"]`);
                buttons.forEach(btn => {
                    btn.innerHTML = '<i class="fas fa-trash"></i>';
                    btn.disabled = false;
                });
            }
        },
               

// Cambiar estado activo/inactivo
async toggleStatus(clienteId, clienteNombre, currentStatus) {
    const action = currentStatus == 1 ? 'desactivar' : 'activar';
    
    if (!confirm(`¿Deseas ${action} el cliente "${clienteNombre}"?`)) {
        return;
    }
    
    try {
        const response = await fetch('modules/clientes/action.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=toggle-status&id=${clienteId}`
        });
        
        const text = await response.text();
        const result = JSON.parse(text);
        
        if (result.success) {
            alert(result.message);
            location.reload();
        } else {
            alert(`Error: ${result.message}`);
        }
    } catch (error) {
        console.error('Error completo:', error);
        alert(`Error al cambiar estado: ${error.message}`);
    }
},
 
         

        async exportToExcel() {
            try {
                // Mostrar loading
                const exportBtn = event.target;
                const originalText = exportBtn.innerHTML;
                exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exportando...';
                exportBtn.disabled = true;
                
                // Obtener parámetros actuales
                const params = new URLSearchParams(window.location.search);
                params.set('action', 'export-excel');
                
                // Crear URL de exportación
                const exportUrl = `modules/clientes/export.php?${params.toString()}`;
                
                // Descargar archivo
                const response = await fetch(exportUrl);
                if (!response.ok) {
                    throw new Error('Error en la exportación');
                }
                
                // Crear blob y descargar
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                
                // Obtener nombre del archivo del header
                const contentDisposition = response.headers.get('Content-Disposition');
                let filename = 'clientes_export.xlsx';
                if (contentDisposition) {
                    const filenameMatch = contentDisposition.match(/filename="(.+)"/);
                    if (filenameMatch) {
                        filename = filenameMatch[1];
                    }
                }
                
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                
                // Restaurar botón
                exportBtn.innerHTML = originalText;
                exportBtn.disabled = false;
                
            } catch (error) {
                console.error('Error en exportación:', error);
                alert('Error al exportar: ' + error.message);
                
                // Restaurar botón en caso de error
                const exportBtn = event.target;
                exportBtn.innerHTML = '<i class="fas fa-file-excel"></i> Exportar Excel';
                exportBtn.disabled = false;
                    }
                },       
                // Búsqueda rápida
                quickSearch() {
                    const searchInput = document.querySelector('input[name="search"]');
                    if (searchInput.value.length < 3 && searchInput.value.length > 0) {
                        alert('Por favor ingresa al menos 3 caracteres para buscar');
                        return false;
                    }
                    return true;
                },
                
                // Limpiar filtros
                clearFilters() {
                    window.location.href = 'catalogos.php?mod=clientes&action=list';
                }
            };
}

</script>