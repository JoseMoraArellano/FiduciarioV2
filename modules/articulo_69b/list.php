<?php
require_once 'includes/Database.php';
require_once 'includes/Session.php';
require_once 'includes/Permissions.php';
require_once 'includes/Auth.php';

if (isset($database)) {
    $db = $database;
} elseif (class_exists('Database')) {
    $db = Database::getInstance()->getConnection();
} else {
    die('No se pudo obtener conexión a la base de datos');
}

$session = new Session();
$permissions = new Permissions();

$userId = $session->getUserId();
$isAdmin = $session->isAdmin();

$canView = $isAdmin
    || $permissions->hasPermission($userId, 'articulo_69b', 'lire')
    || $session->hasPermission('catalogos', 'lire', 'articulo_69b');

$canCreate = $isAdmin
    || $permissions->hasPermission($userId, 'articulo_69b', 'creer')
    || $session->hasPermission('catalogos', 'creer', 'articulo_69b');

$canEdit = $isAdmin
    || $permissions->hasPermission($userId, 'articulo_69b', 'modifier')
    || $session->hasPermission('catalogos', 'modifier', 'articulo_69b');

$canDelete = $isAdmin
    || $permissions->hasPermission($userId, 'articulo_69b', 'supprimer')
    || $session->hasPermission('catalogos', 'supprimer', 'articulo_69b');

if (!$canView) {
    echo '<div class="p-6"><div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded">No tienes permisos para ver este módulo</div></div>';
    exit;
}

// Obtener comparativo desde la base de datos
$comparativoData = null;
try {
    $stmtComp = $db->prepare("SELECT val FROM t_const WHERE nom = 'Art_69B_Comparativo' LIMIT 1");
    $stmtComp->execute();
    $resultComp = $stmtComp->fetch(PDO::FETCH_ASSOC);
    
    if ($resultComp && !empty($resultComp['val'])) {
        $comparativoData = json_decode($resultComp['val'], true);
    }
} catch (PDOException $e) {
    error_log("Error al obtener comparativo: " . $e->getMessage());
}

// Obtener total de registros
$totalRegistros = 0;
try {
    $stmtCount = $db->query("SELECT COUNT(*) as total FROM t_cat_articulo_69b");
    $resultCount = $stmtCount->fetch(PDO::FETCH_ASSOC);
    $totalRegistros = $resultCount['total'];
} catch (PDOException $e) {
    error_log("Error al contar registros: " . $e->getMessage());
}
?>

<link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.4.0/dist/tailwind.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

<div x-data="ArticuloController()" x-init="init()" class="p-6 space-y-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800">Catálogo Artículo 69-B</h1>
            <p class="text-sm text-gray-600 mt-1">Contribuyentes publicados por el SAT - Total: <?= number_format($totalRegistros) ?> registros</p>
        </div>
        
        <?php if ($canCreate): ?>
            <div class="flex gap-2">
                <button @click="openImportExcelModal()" 
                    class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    <i class="fas fa-file-excel"></i> 
                    <span>Importar Excel</span>
                </button>
                <button @click="importarSAT()" 
                    :disabled="importando"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas" :class="importando ? 'fa-spinner fa-spin' : 'fa-download'"></i> 
                    <span x-text="importando ? 'Importando...' : 'Importar desde SAT'"></span>
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Alerta de resultado de importación -->
    <div x-show="alertaImport.show" 
        x-transition
        class="rounded-lg p-4 shadow"
        :class="alertaImport.tipo === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'">
        <div class="flex items-start gap-3">
            <i class="fas mt-0.5" :class="alertaImport.tipo === 'success' ? 'fa-check-circle text-green-600' : 'fa-exclamation-circle text-red-600'"></i>
            <div class="flex-1">
                <p class="font-medium" x-text="alertaImport.mensaje"></p>
                <div x-show="alertaImport.stats" class="mt-2 text-sm space-y-1">
                    <p><strong>Insertados:</strong> <span x-text="alertaImport.stats?.insertados"></span></p>
                    <p><strong>Errores:</strong> <span x-text="alertaImport.stats?.errores"></span></p>
                    <p><strong>Total procesados:</strong> <span x-text="alertaImport.stats?.total_procesados"></span></p>
                </div>
            </div>
            <button @click="alertaImport.show = false" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    <!-- Comparativo -->
    <div x-show="comparativo.mostrar" 
         x-transition 
         class="p-4 bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg shadow-sm sticky top-0 z-10">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-8 text-sm">
                <div class="flex items-center gap-2">
                    <i class="fas fa-history text-blue-500"></i>
                    <div>
                        <span class="text-gray-600 text-xs uppercase font-medium">Anterior</span>
                        <p class="text-xl font-bold text-gray-800" x-text="comparativo.anterior.toLocaleString()"></p>
                    </div>
                </div>
                
                <div class="text-blue-400 text-2xl">
                    <i class="fas fa-arrow-right"></i>
                </div>
                
                <div class="flex items-center gap-2">
                    <i class="fas fa-database text-green-500"></i>
                    <div>
                        <span class="text-gray-600 text-xs uppercase font-medium">Actual</span>
                        <p class="text-xl font-bold text-gray-800" x-text="comparativo.actual.toLocaleString()"></p>
                    </div>
                </div>
                
                <div class="flex items-center gap-2 px-4 py-2 rounded-lg" 
                     :class="comparativo.diferencia >= 0 ? 'bg-green-100' : 'bg-red-100'">
                    <i class="fas" :class="comparativo.diferencia >= 0 ? 'fa-arrow-up text-green-600' : 'fa-arrow-down text-red-600'"></i>
                    <div>
                        <span class="text-gray-600 text-xs uppercase font-medium">Diferencia</span>
                        <p class="text-xl font-bold" :class="comparativo.diferencia >= 0 ? 'text-green-600' : 'text-red-600'">
                            <span x-text="comparativo.diferencia >= 0 ? '+' : ''"></span>
                            <span x-text="comparativo.diferencia.toLocaleString()"></span>
                        </p>
                    </div>
                </div>
                
                <div class="text-xs text-gray-500 ml-4 flex items-center gap-2">
                    <i class="fas fa-clock"></i>
                    <div>
                        <span class="text-gray-600 text-xs uppercase font-medium">Última actualización</span>
                        <p class="font-semibold text-gray-700" x-text="comparativo.fecha || 'Sin fecha'"></p>
                    </div>
                </div>
            </div>
            <button @click="comparativo.mostrar = false" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    <!-- Filtros de búsqueda -->
    <div class="bg-white rounded-lg shadow p-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">RFC</label>
                <input type="text" x-model="filtros.rfc" @input="buscarConDebounce()"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Buscar por RFC (min. 3 caracteres)...">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre Contribuyente</label>
                <input type="text" x-model="filtros.nombre" @input="buscarConDebounce()"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Buscar por nombre (min. 3 caracteres)...">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Situación</label>
                <select x-model="filtros.situacion" @change="buscarAjax()"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todas</option>
                    <option value="Presunto">Presunto</option>
                    <option value="Desvirtuado">Desvirtuado</option>
                    <option value="Definitivo">Definitivo</option>
                    <option value="Sentencia Favorable">Sentencia Favorable</option>
                </select>
            </div>
        </div>

        <div class="mt-4 flex justify-between items-center">
            <div class="text-sm text-gray-600">
                <i class="fas fa-info-circle mr-1"></i>
                <span x-show="!buscando && registrosFiltrados.length === 0">Ingrese al menos 3 caracteres en RFC o Nombre para buscar</span>
                <span x-show="buscando"><i class="fas fa-spinner fa-spin mr-1"></i>Buscando...</span>
                <span x-show="!buscando && registrosFiltrados.length > 0" x-text="'Mostrando ' + registrosFiltrados.length + ' resultado(s)'"></span>
            </div>
            <button @click="limpiarFiltros()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                <i class="fas fa-eraser mr-1"></i> Limpiar filtros
            </button>
        </div>
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase cursor-pointer" @click="sortBy('id')">
                            <div class="flex items-center justify-center gap-1">ID
                                <span x-show="sort.column === 'id'">
                                    <template x-if="sort.desc"><i class="fas fa-sort-down"></i></template>
                                    <template x-if="!sort.desc"><i class="fas fa-sort-up"></i></template>
                                </span>
                            </div>
                        </th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase cursor-pointer" @click="sortBy('rfc')">
                            <div class="flex items-center justify-center gap-1">RFC
                                <span x-show="sort.column === 'rfc'">
                                    <template x-if="sort.desc"><i class="fas fa-sort-down"></i></template>
                                    <template x-if="!sort.desc"><i class="fas fa-sort-up"></i></template>
                                </span>
                            </div>
                        </th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase cursor-pointer" @click="sortBy('nombre_contribuyente')">
                            <div class="flex items-center justify-center gap-1">Nombre
                                <span x-show="sort.column === 'nombre_contribuyente'">
                                    <template x-if="sort.desc"><i class="fas fa-sort-down"></i></template>
                                    <template x-if="!sort.desc"><i class="fas fa-sort-up"></i></template>
                                </span>
                            </div>
                        </th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Situación</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <template x-if="buscando">
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                                <p>Buscando registros...</p>
                            </td>
                        </tr>
                    </template>
                    <template x-if="!buscando && registrosFiltrados.length === 0">
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                <i class="fas fa-search text-2xl mb-2"></i>
                                <p>No se encontraron resultados. Utilice los filtros para buscar.</p>
                            </td>
                        </tr>
                    </template>
                    <template x-if="!buscando && registrosFiltrados.length > 0">
                        <template x-for="row in registrosFiltrados" :key="row.id">
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm text-gray-700 text-center" x-text="row.id"></td>
                                <td class="px-4 py-3 text-sm text-gray-700 text-center font-medium" x-text="row.rfc"></td>
                                <td class="px-4 py-3 text-sm text-gray-700" x-text="row.nombre_contribuyente"></td>
                                <td class="px-4 py-3 text-sm text-center">
                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs"
                                        :class="{
                                            'bg-yellow-100 text-yellow-800': row.situacion_contribuyente === 'Presunto',
                                            'bg-blue-100 text-blue-800': row.situacion_contribuyente === 'Desvirtuado',
                                            'bg-red-100 text-red-800': row.situacion_contribuyente === 'Definitivo',
                                            'bg-green-100 text-green-800': row.situacion_contribuyente === 'Sentencia Favorable'
                                        }"
                                        x-text="row.situacion_contribuyente || 'Sin especificar'"></span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="inline-flex gap-2">
                                        <button @click="verDetalle(row)" class="px-2 py-1 bg-blue-500 text-white rounded hover:brightness-90" title="Ver detalle">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($canEdit): ?>
                                            <button @click="openEditModal(row)" class="px-2 py-1 bg-yellow-400 text-white rounded hover:brightness-90" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($canDelete): ?>
                                            <button @click="confirmDelete(row.id)" class="px-2 py-1 bg-red-500 text-white rounded hover:brightness-90" title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Detalle -->
    <div x-show="modalDetalle.open" 
         x-transition
         class="fixed inset-0 z-50 overflow-y-auto" 
         @click.self="modalDetalle.open = false">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black opacity-50"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
                <div class="sticky top-0 bg-white border-b px-6 py-4 flex justify-between items-center">
                    <h3 class="text-xl font-semibold text-gray-800">Detalle del Registro</h3>
                    <button @click="modalDetalle.open = false" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <div class="p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-600">RFC</label>
                            <p class="mt-1 text-gray-900 font-semibold" x-text="modalDetalle.data.rfc"></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600">Nombre Contribuyente</label>
                            <p class="mt-1 text-gray-900" x-text="modalDetalle.data.nombre_contribuyente"></p>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-600">Situación</label>
                            <p class="mt-1 text-gray-900" x-text="modalDetalle.data.situacion_contribuyente"></p>
                        </div>
                    </div>

                    <!-- Presuntos -->
                    <div class="border-t pt-4">
                        <h4 class="font-semibold text-gray-700 mb-3">Información Presuntos</h4>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <label class="block text-gray-600">Oficio SAT</label>
                                <p class="text-gray-900" x-text="modalDetalle.data.numero_fecha_oficio_presuncion_sat || 'N/A'"></p>
                            </div>
                            <div>
                                <label class="block text-gray-600">Publicación SAT</label>
                                <p class="text-gray-900" x-text="modalDetalle.data.publicacion_sat_presuntos || 'N/A'"></p>
                            </div>
                            <div>
                                <label class="block text-gray-600">Oficio DOF</label>
                                <p class="text-gray-900" x-text="modalDetalle.data.numero_fecha_oficio_presuncion_dof || 'N/A'"></p>
                            </div>
                            <div>
                                <label class="block text-gray-600">Publicación DOF</label>
                                <p class="text-gray-900" x-text="modalDetalle.data.publicacion_dof_presuntos || 'N/A'"></p>
                            </div>
                        </div>
                    </div>

                    <!-- Desvirtuados -->
                    <div class="border-t pt-4">
                        <h4 class="font-semibold text-gray-700 mb-3">Información Desvirtuados</h4>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <label class="block text-gray-600">Oficio SAT</label>
                                <p class="text-gray-900" x-text="modalDetalle.data.numero_fecha_oficio_desvirtuar_sat || 'N/A'"></p>
                            </div>
                            <div>
                                <label class="block text-gray-600">Publicación SAT</label>
                                <p class="text-gray-900" x-text="modalDetalle.data.publicacion_sat_desvirtuados || 'N/A'"></p>
                            </div>
                            <div>
                                <label class="block text-gray-600">Oficio DOF</label>
                                <p class="text-gray-900" x-text="modalDetalle.data.numero_fecha_oficio_desvirtuar_dof || 'N/A'"></p>
                            </div>
                            <div>
                                <label class="block text-gray-600">Publicación DOF</label>
                                <p class="text-gray-900" x-text="modalDetalle.data.publicacion_dof_desvirtuados || 'N/A'"></p>
                            </div>
                        </div>
                    </div>

                    <!-- Definitivos -->
                    <div class="border-t pt-4">
                        <h4 class="font-semibold text-gray-700 mb-3">Información Definitivos</h4>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <label class="block text-gray-600">Oficio SAT</label>
                                <p class="text-gray-900" x-text="modalDetalle.data.numero_fecha_oficio_definitivos_sat || 'N/A'"></p>
                            </div>
                            <div>
                                <label class="block text-gray-600">Publicación SAT</label>
                                <p class="text-gray-900" x-text="modalDetalle.data.publicacion_sat_definitivos || 'N/A'"></p>
                            </div>
                            <div>
                                <label class="block text-gray-600">Oficio DOF</label>
                                <p class="text-gray-900" x-text="modalDetalle.data.numero_fecha_oficio_definitivos_dof || 'N/A'"></p>
                            </div>
                            <div>
                                <label class="block text-gray-600">Publicación DOF</label>
                                <p class="text-gray-900" x-text="modalDetalle.data.publicacion_dof_definitivos || 'N/A'"></p>
                            </div>
                        </div>
                    </div>

                    <!-- Sentencia Favorable -->
                    <div class="border-t pt-4">
                        <h4 class="font-semibold text-gray-700 mb-3">Información Sentencia Favorable</h4>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <label class="block text-gray-600">Oficio SAT</label>
                                <p class="text-gray-900" x-text="modalDetalle.data.numero_fecha_oficio_sentencia_sat || 'N/A'"></p>
                            </div>
                            <div>
                                <label class="block text-gray-600">Publicación SAT</label>
                                <p class="text-gray-900" x-text="modalDetalle.data.publicacion_sat_sentencia_favorable || 'N/A'"></p>
                            </div>
                            <div>
                                <label class="block text-gray-600">Oficio DOF</label>
                                <p class="text-gray-900" x-text="modalDetalle.data.numero_fecha_oficio_sentencia_dof || 'N/A'"></p>
                            </div>
                            <div>
                                <label class="block text-gray-600">Publicación DOF</label>
                                <p class="text-gray-900" x-text="modalDetalle.data.publicacion_dof_sentencia_favorable || 'N/A'"></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Editar -->
    <div x-show="modalEdit.open" 
         x-transition
         class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black opacity-50" @click="modalEdit.open = false"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
                <div class="sticky top-0 bg-white border-b px-6 py-4 flex justify-between items-center">
                    <h3 class="text-xl font-semibold text-gray-800">Editar Registro</h3>
                    <button @click="modalEdit.open = false" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <form @submit.prevent="submitEdit()" class="p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">RFC *</label>
                            <input type="text" x-model="form.rfc" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre Contribuyente *</label>
                            <input type="text" x-model="form.nombre_contribuyente" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Situación</label>
                            <select x-model="form.situacion_contribuyente"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Seleccionar...</option>
                                <option value="Presunto">Presunto</option>
                                <option value="Desvirtuado">Desvirtuado</option>
                                <option value="Definitivo">Definitivo</option>
                                <option value="Sentencia Favorable">Sentencia Favorable</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-4 border-t">
                        <button type="button" @click="modalEdit.open = false"
                            class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                            Cancelar
                        </button>
                        <button type="submit" :disabled="guardando"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
                            <span x-text="guardando ? 'Guardando...' : 'Guardar'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Importar Excel -->
    <div x-show="modalImportExcel.open" 
         x-transition
         class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black opacity-50" @click="modalImportExcel.open = false"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-2xl w-full">
                <div class="bg-white border-b px-6 py-4 flex justify-between items-center rounded-t-lg">
                    <h3 class="text-xl font-semibold text-gray-800">Importar desde Excel</h3>
                    <button @click="modalImportExcel.open = false" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <form @submit.prevent="submitImportExcel()" class="p-6">
                    <div class="space-y-4">
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <p class="text-sm text-blue-800">
                                <i class="fas fa-info-circle mr-2"></i>
                                El archivo Excel debe contener las siguientes columnas: RFC, Nombre del Contribuyente, Situación del Contribuyente
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Archivo Excel</label>
                            <input type="file" 
                                   accept=".xlsx,.xls" 
                                   @change="handleFileSelect($event)"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div x-show="modalImportExcel.fileName" class="text-sm text-gray-600">
                            <i class="fas fa-file-excel mr-2 text-green-600"></i>
                            <span x-text="modalImportExcel.fileName"></span>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-6 border-t mt-6">
                        <button type="button" @click="modalImportExcel.open = false"
                            class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                            Cancelar
                        </button>
                        <button type="submit" 
                                :disabled="!modalImportExcel.file || modalImportExcel.uploading"
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas mr-2" :class="modalImportExcel.uploading ? 'fa-spinner fa-spin' : 'fa-upload'"></i>
                            <span x-text="modalImportExcel.uploading ? 'Importando...' : 'Importar'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

<script>
 // ========== Reemplazar el script completo en list.php ==========

function ArticuloController() {
    return {
        registrosFiltrados: [],
        importando: false,
        guardando: false,
        buscando: false,
        debounceTimer: null,
        
        // Detectar la ruta base correcta
        baseUrl: window.location.pathname.includes('catalogos.php') 
            ? 'modules/articulo_69b/' 
            : '',

        alertaImport: {
            show: false,
            tipo: 'success',
            mensaje: '',
            stats: null
        },
        
        comparativo: {
            mostrar: <?= $comparativoData ? 'true' : 'false' ?>,
            anterior: <?= $comparativoData ? (int)$comparativoData['anterior'] : 0 ?>,
            actual: <?= $comparativoData ? (int)$comparativoData['actual'] : 0 ?>,
            diferencia: <?= $comparativoData ? (int)$comparativoData['diferencia'] : 0 ?>,
            fecha: <?= $comparativoData && isset($comparativoData['fecha']) ? '"' . htmlspecialchars($comparativoData['fecha']) . '"' : '""' ?>
        },

        filtros: {
            rfc: '',
            nombre: '',
            situacion: ''
        },

        sort: {
            column: 'rfc',
            desc: false
        },

        modalDetalle: {
            open: false,
            data: {}
        },

        modalEdit: {
            open: false
        },

        modalImportExcel: {
            open: false,
            file: null,
            fileName: '',
            uploading: false
        },

        form: {
            id: '',
            rfc: '',
            nombre_contribuyente: '',
            situacion_contribuyente: ''
        },

        init() {
            console.log('Controlador inicializado');
            console.log('Base URL detectada:', this.baseUrl);
            console.log('URL actual:', window.location.pathname);
            
            // Verificar si hay resultado de importación en sessionStorage
            const importResultado = sessionStorage.getItem('import_resultado');
            if (importResultado) {
                try {
                    const data = JSON.parse(importResultado);
                    this.alertaImport = {
                        show: true,
                        tipo: data.tipo || 'success',
                        mensaje: data.mensaje || 'Operación completada',
                        stats: data.stats || null
                    };
                    
                    if (data.comparativo) {
                        this.comparativo = {
                            mostrar: true,
                            anterior: data.comparativo.anterior || 0,
                            actual: data.comparativo.actual || 0,
                            diferencia: data.comparativo.diferencia || 0,
                            fecha: data.comparativo.fecha || ''
                        };
                    }
                } catch (e) {
                    console.error('Error al parsear resultado:', e);
                }
                
                sessionStorage.removeItem('import_resultado');
            }
        },

        buscarConDebounce() {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => {
                this.buscarAjax();
            }, 500);
        },

        buscarAjax() {
            const rfcLen = this.filtros.rfc.trim().length;
            const nombreLen = this.filtros.nombre.trim().length;
            const tieneSituacion = this.filtros.situacion !== '';

            // Validar criterios mínimos
            if (rfcLen < 3 && nombreLen < 3 && !tieneSituacion) {
                this.registrosFiltrados = [];
                return;
            }

            this.buscando = true;

            const params = new URLSearchParams();
            if (this.filtros.rfc) params.append('rfc', this.filtros.rfc);
            if (this.filtros.nombre) params.append('nombre', this.filtros.nombre);
            if (this.filtros.situacion) params.append('situacion', this.filtros.situacion);

            // CORRECCIÓN: Usa la baseUrl detectada
            const url = this.baseUrl + 'search.php?' + params.toString();
            console.log('Buscando en URL:', url);

            fetch(url)
                .then(response => {
                    console.log('Response status:', response.status);
                    console.log('Response URL:', response.url);
                    if (!response.ok) {
                        throw new Error(`Error HTTP: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Datos recibidos:', data);
                    this.buscando = false;
                    if (data.success) {
                        this.registrosFiltrados = data.data || [];
                        this.applySort();
                        console.log('Resultados encontrados:', this.registrosFiltrados.length);
                    } else {
                        console.error('Error en búsqueda:', data.message);
                        this.registrosFiltrados = [];
                        alert('Error: ' + (data.message || 'Error desconocido'));
                    }
                })
                .catch(err => {
                    this.buscando = false;
                    console.error('Error en búsqueda:', err);
                    this.registrosFiltrados = [];
                    alert('Error de conexión: ' + err.message + '\nURL intentada: ' + url);
                });
        },

        limpiarFiltros() {
            this.filtros = {
                rfc: '',
                nombre: '',
                situacion: ''
            };
            this.registrosFiltrados = [];
        },

        sortBy(column) {
            if (this.sort.column === column) {
                this.sort.desc = !this.sort.desc;
            } else {
                this.sort.column = column;
                this.sort.desc = false;
            }
            this.applySort();
        },

        applySort() {
            const col = this.sort.column;
            this.registrosFiltrados.sort((a, b) => {
                let va = a[col];
                let vb = b[col];

                if (col === 'id') {
                    va = parseInt(va) || 0;
                    vb = parseInt(vb) || 0;
                } else if (typeof va === 'string') {
                    va = va.toLowerCase();
                    vb = (vb || '').toLowerCase();
                }

                if (va < vb) return this.sort.desc ? 1 : -1;
                if (va > vb) return this.sort.desc ? -1 : 1;
                return 0;
            });
        },

        importarSAT() {
            if (this.importando) return;
            
            if (!confirm('¿Desea importar los datos del SAT? Esto puede tardar varios minutos.')) {
                return;
            }

            this.importando = true;
            this.alertaImport.show = false;

            fetch(this.baseUrl + 'import_sat.php', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded'
                }
            })
            .then(response => response.json())
            .then(resp => {
                this.importando = false;
                
                if (resp && resp.success) {
                    const dataParaGuardar = {
                        tipo: 'success',
                        mensaje: resp.message || 'Importación completada',
                        stats: resp.stats
                    };
                    
                    if (resp.comparativo) {
                        dataParaGuardar.comparativo = resp.comparativo;
                    }
                    
                    sessionStorage.setItem('import_resultado', JSON.stringify(dataParaGuardar));
                    location.reload();
                } else {
                    this.alertaImport.show = true;
                    this.alertaImport.tipo = 'error';
                    this.alertaImport.mensaje = (resp && resp.message) || 'Error en la importación';
                    this.alertaImport.stats = (resp && resp.stats) || null;
                }
            })
            .catch(err => {
                this.importando = false;
                this.alertaImport.show = true;
                this.alertaImport.tipo = 'error';
                this.alertaImport.mensaje = 'Error de conexión con el servidor';
                console.error('Error:', err);
            });
        },

        openImportExcelModal() {
            this.modalImportExcel.open = true;
            this.modalImportExcel.file = null;
            this.modalImportExcel.fileName = '';
        },

        handleFileSelect(event) {
            const file = event.target.files[0];
            if (file) {
                this.modalImportExcel.file = file;
                this.modalImportExcel.fileName = file.name;
            }
        },

        submitImportExcel() {
            if (!this.modalImportExcel.file) {
                alert('Por favor seleccione un archivo');
                return;
            }

            const formData = new FormData();
            formData.append('excel_file', this.modalImportExcel.file);

            this.modalImportExcel.uploading = true;

            fetch(this.baseUrl + 'import_excel.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(resp => {
                this.modalImportExcel.uploading = false;
                this.modalImportExcel.open = false;

                if (resp && resp.success) {
                    const dataParaGuardar = {
                        tipo: 'success',
                        mensaje: resp.message || 'Importación desde Excel completada',
                        stats: resp.stats
                    };
                    
                    if (resp.comparativo) {
                        dataParaGuardar.comparativo = resp.comparativo;
                    }
                    
                    sessionStorage.setItem('import_resultado', JSON.stringify(dataParaGuardar));
                    location.reload();
                } else {
                    this.alertaImport.show = true;
                    this.alertaImport.tipo = 'error';
                    this.alertaImport.mensaje = resp.message || 'Error al importar Excel';
                    this.alertaImport.stats = resp.stats || null;
                }
            })
            .catch(err => {
                this.modalImportExcel.uploading = false;
                this.modalImportExcel.open = false;
                this.alertaImport.show = true;
                this.alertaImport.tipo = 'error';
                this.alertaImport.mensaje = 'Error de conexión';
                console.error('Error:', err);
            });
        },

        verDetalle(row) {
            this.modalDetalle.data = row;
            this.modalDetalle.open = true;
        },

        openEditModal(row) {
            this.form = { ...row };
            this.modalEdit.open = true;
        },

        submitEdit() {
            this.guardando = true;

            const formData = new FormData();
            formData.append('action', 'update');
            Object.keys(this.form).forEach(key => {
                formData.append(key, this.form[key] || '');
            });

            fetch(this.baseUrl + 'actions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(resp => {
                this.guardando = false;
                if (resp.success) {
                    this.modalEdit.open = false;
                    alert('Registro actualizado correctamente');
                    
                    // Actualizar el registro en la lista si existe
                    const index = this.registrosFiltrados.findIndex(r => r.id === this.form.id);
                    if (index !== -1) {
                        this.registrosFiltrados[index] = { ...this.form };
                    }
                } else {
                    alert('Error: ' + resp.message);
                }
            })
            .catch(err => {
                this.guardando = false;
                alert('Error de conexión');
                console.error(err);
            });
        },

        confirmDelete(id) {
            if (!confirm('¿Está seguro de eliminar este registro?')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', id);

            fetch(this.baseUrl + 'actions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(resp => {
                if (resp.success) {
                    alert('Registro eliminado');
                    
                    // Eliminar de la lista actual
                    this.registrosFiltrados = this.registrosFiltrados.filter(r => r.id !== id);
                } else {
                    alert('Error: ' + resp.message);
                }
            })
            .catch(err => {
                alert('Error de conexión');
                console.error(err);
            });
        }
    }
}
</script>