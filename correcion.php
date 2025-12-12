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

try {
    $query = "SELECT * FROM t_cat_articulo_69b ORDER BY rfc ASC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo '<div class="p-6"><div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded">Error al obtener datos: ' . htmlspecialchars($e->getMessage()) . '</div></div>';
    $registros = [];
}
?>

<link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.4.0/dist/tailwind.min.css" rel="stylesheet">
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

<div x-data="ArticuloController()" x-init="init()" class="p-6 space-y-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800">Catálogo Artículo 69-B</h1>
            <p class="text-sm text-gray-600 mt-1">Contribuyentes publicados por el SAT</p>
        </div>
        <?php if ($canCreate): ?>
            <div class="flex gap-2">
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
                    <p><strong>Actualizados:</strong> <span x-text="alertaImport.stats?.actualizados"></span></p>
                    <p><strong>Errores:</strong> <span x-text="alertaImport.stats?.errores"></span></p>
                    <p><strong>Total procesados:</strong> <span x-text="alertaImport.stats?.total_procesados"></span></p>
                </div>
            </div>
            <button @click="alertaImport.show = false" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    <!-- Filtros de búsqueda -->
    <div class="bg-white rounded-lg shadow p-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">RFC</label>
                <input type="text" x-model="filtros.rfc" @input="aplicarFiltros()"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Buscar por RFC...">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre Contribuyente</label>
                <input type="text" x-model="filtros.nombre" @input="aplicarFiltros()"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Buscar por nombre...">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Situación</label>
                <select x-model="filtros.situacion" @change="aplicarFiltros()"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todas</option>
                    <option value="Presunto">Presunto</option>
                    <option value="Desvirtuado">Desvirtuado</option>
                    <option value="Definitivo">Definitivo</option>
                    <option value="Sentencia Favorable">Sentencia Favorable</option>
                </select>
            </div>
        </div>

        <div class="mt-4 flex justify-end">
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
                                    <button @click="verDetalle(row.id)" class="px-2 py-1 bg-blue-500 text-white rounded hover:brightness-90" title="Ver detalle">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if ($canEdit): ?>
                                        <button @click="openEditModal(row.id)" class="px-2 py-1 bg-yellow-400 text-white rounded hover:brightness-90" title="Editar">
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
                </tbody>
            </table>
        </div>
        <?php if (empty($registros)): ?>
            <div class="p-8 text-center text-gray-500">No se encontraron registros.</div>
        <?php endif; ?>
    </div>

    <!-- Modal Crear/Editar -->
    <div x-show="modal.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/40" @click="closeModal()"></div>
        <div class="bg-white rounded-lg shadow-lg w-full max-w-4xl z-50 overflow-hidden">
            <div class="p-4 border-b flex items-center justify-between bg-blue-50">
                <h3 class="text-lg font-medium" x-text="modal.title"></h3>
                <button @click="closeModal()" class="text-gray-500 hover:text-gray-800">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form @submit.prevent="submitForm" class="p-6 space-y-6 max-h-[75vh] overflow-y-auto">
                <input type="hidden" name="id" x-model="form.id">

                <!-- Información básica -->
                <div class="border-b pb-4">
                    <h4 class="text-md font-semibold text-gray-700 mb-3">Información del Contribuyente</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">RFC *</label>
                            <input type="text" x-model="form.rfc" required maxlength="13"
                                class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                placeholder="XAXX010101000">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Situación</label>
                            <select x-model="form.situacion_contribuyente"
                                class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">Seleccionar...</option>
                                <option value="Presunto">Presunto</option>
                                <option value="Desvirtuado">Desvirtuado</option>
                                <option value="Definitivo">Definitivo</option>
                                <option value="Sentencia Favorable">Sentencia Favorable</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Nombre del Contribuyente</label>
                            <input type="text" x-model="form.nombre_contribuyente" maxlength="120"
                                class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <!-- Presuntos -->
                <div class="border-b pb-4">
                    <h4 class="text-md font-semibold text-gray-700 mb-3 flex items-center gap-2">
                        <span class="w-3 h-3 bg-yellow-500 rounded-full"></span>
                        Presuntos
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Núm/Fecha Oficio Presunción SAT</label>
                            <input type="text" x-model="form.numero_fecha_oficio_presuncion_sat" maxlength="100"
                                class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Publicación SAT Presuntos</label>
                            <input type="text" x-model="form.publicacion_sat_presuntos" maxlength="100"
                                class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Núm/Fecha Oficio Presunción DOF</label>
                            <input type="text" x-model="form.numero_fecha_oficio_presuncion_dof" maxlength="100"
                                class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Publicación DOF Presuntos</label>
                            <input type="text" x-model="form.publicacion_dof_presuntos" maxlength="100"
                                class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <!-- Desvirtuados -->
                <div class="border-b pb-4">
                    <h4 class="text-md font-semibold text-gray-700 mb-3 flex items-center gap-2">
                        <span class="w-3 h-3 bg-blue-500 rounded-full"></span>
                        Desvirtuados
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Núm/Fecha Oficio Desvirtuar SAT</label>
                            <input type="text" x-model="form.numero_fecha_oficio_desvirtuar_sat" maxlength="100"
                                class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Publicación SAT Desvirtuados</label>
                            <input type="text" x-model="form.publicacion_sat_desvirtuados" maxlength="100"
                                class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Núm/Fecha Oficio Desvirtuar DOF</label>
                            <input type="text" x-model="form.numero_fecha_oficio_desvirtuar_dof" maxlength="100"
                                class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Publicación DOF Desvirtuados</label>
                            <input type="text" x-model="form.publicacion_dof_desvirtuados" maxlength="100"
                                class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <!-- Definitivos -->
                <div class="border-b pb-4">
                    <h4 class="text-md font-semibold text-gray-700 mb-3 flex items-center gap-2">
                        <span class="w-3 h-3 bg-red-500 rounded-full"></span>
                        Definitivos
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Núm/Fecha Oficio Definitivos SAT</label>
                            <input type="text" x-model="form.numero_fecha_oficio_definitivos_sat" maxlength="100"
                                class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Publicación SAT Definitivos</label>
                            <input type="text" x-model="form.publicacion_sat_definitivos" maxlength="100"
                                class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Núm/Fecha Oficio Definitivos DOF</label>
                            <input type="text" x-model="form.numero_fecha_oficio_definitivos_dof" maxlength="100"
                                class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Publicación DOF Definitivos</label>
                            <input type="text" x-model="form.publicacion_dof_definitivos" maxlength="100"
                                class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <!-- Sentencia Favorable -->
                <div>
                    <h4 class="text-md font-semibold text-gray-700 mb-3 flex items-center gap-2">
                        <span class="w-3 h-3 bg-green-500 rounded-full"></span>
                        Sentencia Favorable
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Núm/Fecha Oficio Sentencia SAT</label>
                            <input type="text" x-model="form.numero_fecha_oficio_sentencia_sat" maxlength="100"
                                class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Publicación SAT Sentencia</label>
                            <input type="text" x-model="form.publicacion_sat_sentencia_favorable" maxlength="100"
                                class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Núm/Fecha Oficio Sentencia DOF</label>
                            <input type="text" x-model="form.numero_fecha_oficio_sentencia_dof" maxlength="100"
                                class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Publicación DOF Sentencia</label>
                            <input type="text" x-model="form.publicacion_dof_sentencia_favorable" maxlength="100"
                                class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <!-- Botones -->
                <div class="flex justify-end gap-2 pt-4 border-t">
                    <button type="button" @click="closeModal()"
                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                        Cancelar
                    </button>
                    <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
                        x-text="modal.actionText">
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Detalle -->
    <div x-show="modalDetalle.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/40" @click="modalDetalle.open = false"></div>
        <div class="bg-white rounded-lg shadow-lg w-full max-w-4xl z-50 overflow-hidden">
            <div class="p-4 border-b flex items-center justify-between bg-blue-50">
                <h3 class="text-lg font-medium">Detalle del Registro</h3>
                <button @click="modalDetalle.open = false" class="text-gray-500 hover:text-gray-800">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="p-6 space-y-4 max-h-[75vh] overflow-y-auto">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <span class="text-sm font-medium text-gray-500">RFC:</span>
                        <p class="text-base font-semibold" x-text="modalDetalle.data.rfc"></p>
                    </div>
                    <div>
                        <span class="text-sm font-medium text-gray-500">Situación:</span>
                        <p class="text-base" x-text="modalDetalle.data.situacion_contribuyente || 'N/A'"></p>
                    </div>
                    <div class="col-span-2">
                        <span class="text-sm font-medium text-gray-500">Nombre:</span>
                        <p class="text-base" x-text="modalDetalle.data.nombre_contribuyente || 'N/A'"></p>
                    </div>
                </div>

                <template x-if="modalDetalle.data.numero_fecha_oficio_presuncion_sat || modalDetalle.data.publicacion_sat_presuntos || modalDetalle.data.numero_fecha_oficio_presuncion_dof || modalDetalle.data.publicacion_dof_presuntos">
                    <div class="border-t pt-4">
                        <h4 class="font-semibold text-gray-700 mb-2 flex items-center gap-2">
                            <span class="w-3 h-3 bg-yellow-500 rounded-full"></span>
                            Presuntos
                        </h4>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div x-show="modalDetalle.data.numero_fecha_oficio_presuncion_sat">
                                <span class="text-gray-500">Oficio SAT:</span>
                                <p x-text="modalDetalle.data.numero_fecha_oficio_presuncion_sat"></p>
                            </div>
                            <div x-show="modalDetalle.data.publicacion_sat_presuntos">
                                <span class="text-gray-500">Publicación SAT:</span>
                                <p x-text="modalDetalle.data.publicacion_sat_presuntos"></p>
                            </div>
                            <div x-show="modalDetalle.data.numero_fecha_oficio_presuncion_dof">
                                <span class="text-gray-500">Oficio DOF:</span>
                                <p x-text="modalDetalle.data.numero_fecha_oficio_presuncion_dof"></p>
                            </div>
                            <div x-show="modalDetalle.data.publicacion_dof_presuntos">
                                <span class="text-gray-500">Publicación DOF:</span>
                                <p x-text="modalDetalle.data.publicacion_dof_presuntos"></p>
                            </div>
                        </div>
                    </div>
                </template>

                <template x-if="modalDetalle.data.numero_fecha_oficio_desvirtuar_sat || modalDetalle.data.publicacion_sat_desvirtuados || modalDetalle.data.numero_fecha_oficio_desvirtuar_dof || modalDetalle.data.publicacion_dof_desvirtuados">
                    <div class="border-t pt-4">
                        <h4 class="font-semibold text-gray-700 mb-2 flex items-center gap-2">
                            <span class="w-3 h-3 bg-blue-500 rounded-full"></span>
                            Desvirtuados
                        </h4>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div x-show="modalDetalle.data.numero_fecha_oficio_desvirtuar_sat">
                                <span class="text-gray-500">Oficio SAT:</span>
                                <p x-text="modalDetalle.data.numero_fecha_oficio_desvirtuar_sat"></p>
                            </div>
                            <div x-show="modalDetalle.data.publicacion_sat_desvirtuados">
                                <span class="text-gray-500">Publicación SAT:</span>
                                <p x-text="modalDetalle.data.publicacion_sat_desvirtuados"></p>
                            </div>
                            <div x-show="modalDetalle.data.numero_fecha_oficio_desvirtuar_dof">
                                <span class="text-gray-500">Oficio DOF:</span>
                                <p x-text="modalDetalle.data.numero_fecha_oficio_desvirtuar_dof"></p>
                            </div>
                            <div x-show="modalDetalle.data.publicacion_dof_desvirtuados">
                                <span class="text-gray-500">Publicación DOF:</span>
                                <p x-text="modalDetalle.data.publicacion_dof_desvirtuados"></p>
                            </div>
                        </div>
                    </div>
                </template>

                <template x-if="modalDetalle.data.numero_fecha_oficio_definitivos_sat || modalDetalle.data.publicacion_sat_definitivos || modalDetalle.data.numero_fecha_oficio_definitivos_dof || modalDetalle.data.publicacion_dof_definitivos">
                    <div class="border-t pt-4">
                        <h4 class="font-semibold text-gray-700 mb-2 flex items-center gap-2">
                            <span class="w-3 h-3 bg-red-500 rounded-full"></span>
                            Definitivos
                        </h4>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div x-show="modalDetalle.data.numero_fecha_oficio_definitivos_sat">
                                <span class="text-gray-500">Oficio SAT:</span>
                                <p x-text="modalDetalle.data.numero_fecha_oficio_definitivos_sat"></p>
                            </div>
                            <div x-show="modalDetalle.data.publicacion_sat_definitivos">
                                <span class="text-gray-500">Publicación SAT:</span>
                                <p x-text="modalDetalle.data.publicacion_sat_definitivos"></p>
                            </div>
                            <div x-show="modalDetalle.data.numero_fecha_oficio_definitivos_dof">
                                <span class="text-gray-500">Oficio DOF:</span>
                                <p x-text="modalDetalle.data.numero_fecha_oficio_definitivos_dof"></p>
                            </div>
                            <div x-show="modalDetalle.data.publicacion_dof_definitivos">
                                <span class="text-gray-500">Publicación DOF:</span>
                                <p x-text="modalDetalle.data.publicacion_dof_definitivos"></p>
                            </div>
                        </div>
                    </div>
                </template>

                <template x-if="modalDetalle.data.numero_fecha_oficio_sentencia_sat || modalDetalle.data.publicacion_sat_sentencia_favorable || modalDetalle.data.numero_fecha_oficio_sentencia_dof || modalDetalle.data.publicacion_dof_sentencia_favorable">
                    <div class="border-t pt-4">
                        <h4 class="font-semibold text-gray-700 mb-2 flex items-center gap-2">
                            <span class="w-3 h-3 bg-green-500 rounded-full"></span>
                            Sentencia Favorable
                        </h4>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div x-show="modalDetalle.data.numero_fecha_oficio_sentencia_sat">
                                <span class="text-gray-500">Oficio SAT:</span>
                                <p x-text="modalDetalle.data.numero_fecha_oficio_sentencia_sat"></p>
                            </div>
                            <div x-show="modalDetalle.data.publicacion_sat_sentencia_favorable">
                                <span class="text-gray-500">Publicación SAT:</span>
                                <p x-text="modalDetalle.data.publicacion_sat_sentencia_favorable"></p>
                            </div>
                            <div x-show="modalDetalle.data.numero_fecha_oficio_sentencia_dof">
                                <span class="text-gray-500">Oficio DOF:</span>
                                <p x-text="modalDetalle.data.numero_fecha_oficio_sentencia_dof"></p>
                            </div>
                            <div x-show="modalDetalle.data.publicacion_dof_sentencia_favorable">
                                <span class="text-gray-500">Publicación DOF:</span>
                                <p x-text="modalDetalle.data.publicacion_dof_sentencia_favorable"></p>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

</div>

<script>
    function ArticuloController() {
        return {
            registros: <?= json_encode($registros); ?>,
            registrosFiltrados: [],
            importando: false,

            alertaImport: {
                show: false,
                tipo: 'success',
                mensaje: '',
                stats: null
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

            modal: {
                open: false,
                title: '',
                actionText: ''
            },

            modalDetalle: {
                open: false,
                data: {}
            },

            form: {
                id: '',
                rfc: '',
                nombre_contribuyente: '',
                situacion_contribuyente: '',
                numero_fecha_oficio_presuncion_sat: '',
                publicacion_sat_presuntos: '',
                numero_fecha_oficio_presuncion_dof: '',
                publicacion_dof_presuntos: '',
                numero_fecha_oficio_desvirtuar_sat: '',
                publicacion_sat_desvirtuados: '',
                numero_fecha_oficio_desvirtuar_dof: '',
                publicacion_dof_desvirtuados: '',
                numero_fecha_oficio_definitivos_sat: '',
                publicacion_sat_definitivos: '',
                numero_fecha_oficio_definitivos_dof: '',
                publicacion_dof_definitivos: '',
                numero_fecha_oficio_sentencia_sat: '',
                publicacion_sat_sentencia_favorable: '',
                numero_fecha_oficio_sentencia_dof: '',
                publicacion_dof_sentencia_favorable: ''
            },

            init() {
                this.registrosFiltrados = [...this.registros];
                this.aplicarFiltros();
            },

            importarSAT() {
                if (this.importando) return;
                
                if (!confirm('¿Desea importar los datos del SAT? Esto puede tardar varios minutos.')) {
                    return;
                }

                this.importando = true;
                this.alertaImport.show = false;

                fetch('modules/articulo_69b/import_sat.php', {
                    method: 'POST'
                })
                .then(r => r.json())
                .then(resp => {
                    this.importando = false;
                    this.alertaImport.show = true;
                    
                    if (resp.success) {
                        this.alertaImport.tipo = 'success';
                        this.alertaImport.mensaje = resp.message || 'Importación completada exitosamente';
                        this.alertaImport.stats = resp.stats;
                        
                        // Recargar la página después de 3 segundos
                        setTimeout(() => {
                            location.reload();
                        }, 3000);
                    } else {
                        this.alertaImport.tipo = 'error';
                        this.alertaImport.mensaje = resp.message || 'Error en la importación';
                        this.alertaImport.stats = null;
                    }
                })
                .catch(err => {
                    this.importando = false;
                    this.alertaImport.show = true;
                    this.alertaImport.tipo = 'error';
                    this.alertaImport.mensaje = 'Error de conexión: ' + err.message;
                    this.alertaImport.stats = null;
                    console.error(err);
                });
            },

            aplicarFiltros() {
                let resultado = [...this.registros];

                if (this.filtros.rfc.trim()) {
                    const busqueda = this.filtros.rfc.toLowerCase().trim();
                    resultado = resultado.filter(r =>
                        (r.rfc || '').toLowerCase().includes(busqueda)
                    );
                }

                if (this.filtros.nombre.trim()) {
                    const busqueda = this.filtros.nombre.toLowerCase().trim();
                    resultado = resultado.filter(r =>
                        (r.nombre_contribuyente || '').toLowerCase().includes(busqueda)
                    );
                }

                if (this.filtros.situacion) {
                    resultado = resultado.filter(r =>
                        r.situacion_contribuyente === this.filtros.situacion
                    );
                }

                this.registrosFiltrados = resultado;
                this.applySort();
            },

            limpiarFiltros() {
                this.filtros = {
                    rfc: '',
                    nombre: '',
                    situacion: ''
                };
                this.aplicarFiltros();
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

            openCreateModal() {
                this.form = {
                    id: '',
                    rfc: '',
                    nombre_contribuyente: '',
                    situacion_contribuyente: '',
                    numero_fecha_oficio_presuncion_sat: '',
                    publicacion_sat_presuntos: '',
                    numero_fecha_oficio_presuncion_dof: '',
                    publicacion_dof_presuntos: '',
                    numero_fecha_oficio_desvirtuar_sat: '',
                    publicacion_sat_desvirtuados: '',
                    numero_fecha_oficio_desvirtuar_dof: '',
                    publicacion_dof_desvirtuados: '',
                    numero_fecha_oficio_definitivos_sat: '',
                    publicacion_sat_definitivos: '',
                    numero_fecha_oficio_definitivos_dof: '',
                    publicacion_dof_definitivos: '',
                    numero_fecha_oficio_sentencia_sat: '',
                    publicacion_sat_sentencia_favorable: '',
                    numero_fecha_oficio_sentencia_dof: '',
                    publicacion_dof_sentencia_favorable: ''
                };
                this.modal.open = true;
                this.modal.title = 'Nuevo Registro Art. 69-B';
                this.modal.actionText = 'Guardar';
            },

            openEditModal(id) {
                fetch(`modules/articulo_69b/actions.php?action=get&id=${id}`)
                    .then(r => r.json())
                    .then(resp => {
                        if (resp.success) {
                            const d = resp.data;
                            this.form = {
                                id: d.id,
                                rfc: d.rfc || '',
                                nombre_contribuyente: d.nombre_contribuyente || '',
                                situacion_contribuyente: d.situacion_contribuyente || '',
                                numero_fecha_oficio_presuncion_sat: d.numero_fecha_oficio_presuncion_sat || '',
                                publicacion_sat_presuntos: d.publicacion_sat_presuntos || '',
                                numero_fecha_oficio_presuncion_dof: d.numero_fecha_oficio_presuncion_dof || '',
                                publicacion_dof_presuntos: d.publicacion_dof_presuntos || '',
                                numero_fecha_oficio_desvirtuar_sat: d.numero_fecha_oficio_desvirtuar_sat || '',
                                publicacion_sat_desvirtuados: d.publicacion_sat_desvirtuados || '',
                                numero_fecha_oficio_desvirtuar_dof: d.numero_fecha_oficio_desvirtuar_dof || '',
                                publicacion_dof_desvirtuados: d.publicacion_dof_desvirtuados || '',
                                numero_fecha_oficio_definitivos_sat: d.numero_fecha_oficio_definitivos_sat || '',
                                publicacion_sat_definitivos: d.publicacion_sat_definitivos || '',
                                numero_fecha_oficio_definitivos_dof: d.numero_fecha_oficio_definitivos_dof || '',
                                publicacion_dof_definitivos: d.publicacion_dof_definitivos || '',
                                numero_fecha_oficio_sentencia_sat: d.numero_fecha_oficio_sentencia_sat || '',
                                publicacion_sat_sentencia_favorable: d.publicacion_sat_sentencia_favorable || '',
                                numero_fecha_oficio_sentencia_dof: d.numero_fecha_oficio_sentencia_dof || '',
                                publicacion_dof_sentencia_favorable: d.publicacion_dof_sentencia_favorable || ''
                            };
                            this.modal.open = true;
                            this.modal.title = 'Editar Registro Art. 69-B';
                            this.modal.actionText = 'Actualizar';
                        } else {
                            alert(resp.message || 'No se pudo obtener el registro.');
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        alert('Error al obtener el registro.');
                    });
            },

            verDetalle(id) {
                fetch(`modules/articulo_69b/actions.php?action=get&id=${id}`)
                    .then(r => r.json())
                    .then(resp => {
                        if (resp.success) {
                            this.modalDetalle.data = resp.data;
                            this.modalDetalle.open = true;
                        } else {
                            alert(resp.message || 'No se pudo obtener el registro.');
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        alert('Error al obtener el registro.');
                    });
            },

            closeModal() {
                this.modal.open = false;
            },

            submitForm() {
                const action = this.form.id ? 'update' : 'create';
                const body = new URLSearchParams();

                body.append('action', action);
                if (this.form.id) body.append('id', this.form.id);
                
                Object.keys(this.form).forEach(key => {
                    if (key !== 'id') {
                        body.append(key, this.form[key]);
                    }
                });

                fetch('modules/articulo_69b/actions.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: body.toString()
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert(data.message || 'Error al guardar.');
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        alert('Error en la petición.');
                    });
            },

            confirmDelete(id) {
                if (!confirm('¿Está seguro de eliminar este registro?')) return;

                const body = new URLSearchParams();
                body.append('action', 'delete');
                body.append('id', id);

                fetch('modules/articulo_69b/actions.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: body.toString()
                    })
                    .then(r => r.json())
                    .then(resp => {
                        if (resp.success) {
                            location.reload();
                        } else {
                            alert(resp.message || 'No se pudo eliminar.');
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        alert('Error en la petición.');
                    });
            }
        }
    }
</script>