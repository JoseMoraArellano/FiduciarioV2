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
    || $permissions->hasPermission($userId, 'gestores', 'lire')
    || $session->hasPermission('catalogos', 'lire', 'gestores');

$canCreate = $isAdmin
    || $permissions->hasPermission($userId, 'gestores', 'creer')
    || $session->hasPermission('catalogos', 'creer', 'gestores');

$canEdit = $isAdmin
    || $permissions->hasPermission($userId, 'gestores', 'modifier')
    || $session->hasPermission('catalogos', 'modifier', 'gestores');
$canDelete = $isAdmin
    || $permissions->hasPermission($userId, 'gestores', 'supprimer')
    || $session->hasPermission('catalogos', 'supprimer', 'gestores');


if (!$canView) {
    echo '<div class="p-6"><div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded">No tienes permisos para ver este módulo</div></div>';
    exit;
}


try {
    $query = "SELECT id,nombres,paterno,materno,correo,ext,firmante,adminfide,contacto,promotor,url_gestor,activo FROM t_gestores";
    if (!$isAdmin && $canEdit) {
        $query .= " WHERE activo = true";
    }

    $query .= " ORDER BY nombres ASC";

    $stmt = $db->prepare($query);
    $stmt->execute();
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo '<div class="p-6"><div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded">Error al obtener datos: ' . htmlspecialchars($e->getMessage()) . '</div></div>';
    $registros = [];
}




?>

<!-- Tailwind + Alpine (CDN). Ya tenías tailwind; incluí Alpine -->
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.4.0/dist/tailwind.min.css" rel="stylesheet">
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

<div x-data="ServController()" x-init="init()" class="p-6 space-y-6">

    <!-- Header -->
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800"><i class="fa-solid fa-user-pen"></i> Catálogo de Gestores</h1>
        </div>
        <?php if ($canCreate): ?>
            <button @click="openCreateModal()" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                <i class="fas fa-plus"></i> Nuevo Gestor
            </button>
        <?php endif; ?>
    </div>

    <!-- Filtros de búsqueda -->
    <div class="bg-white rounded-lg shadow p-4">
        <!-- Fila 1: Filtros de texto -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nombres</label>
                <input type="text" x-model="filtros.nombres" @input="aplicarFiltros()"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Buscar por nombres...">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Apellido Paterno</label>
                <input type="text" x-model="filtros.paterno" @input="aplicarFiltros()"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Buscar por paterno...">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Apellido Materno</label>
                <input type="text" x-model="filtros.materno" @input="aplicarFiltros()"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Buscar por materno...">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Correo</label>
                <input type="text" x-model="filtros.correo" @input="aplicarFiltros()"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Buscar por correo...">
            </div>
        </div>

        <!-- Fila 2: Filtros de roles (checkboxes) -->
        <div class="mt-4 pt-4 border-t">
            <div class="border border-gray-300 rounded-lg p-4 bg-gray-50">
                <label class="block text-sm font-medium text-gray-700 mb-3">Filtrar por rol:</label>
                <div class="flex flex-wrap gap-6">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" x-model="filtros.firmante" @change="aplicarFiltros()"
                            class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                        <span class="ml-2 text-sm text-gray-700">Firmante</span>
                    </label>
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" x-model="filtros.adminfide" @change="aplicarFiltros()"
                            class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-2 focus:ring-purple-500">
                        <span class="ml-2 text-sm text-gray-700">Admin Fideicomiso</span>
                    </label>
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" x-model="filtros.contacto" @change="aplicarFiltros()"
                            class="w-4 h-4 text-teal-600 border-gray-300 rounded focus:ring-2 focus:ring-teal-500">
                        <span class="ml-2 text-sm text-gray-700">Contacto</span>
                    </label>
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" x-model="filtros.promotor" @change="aplicarFiltros()"
                            class="w-4 h-4 text-orange-600 border-gray-300 rounded focus:ring-2 focus:ring-orange-500">
                        <span class="ml-2 text-sm text-gray-700">Promotor</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Botón limpiar -->
        <div class="mt-4 flex justify-end">
            <button @click="limpiarFiltros()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                <i class="fas fa-eraser mr-1"></i> Limpiar filtros
            </button>
        </div>
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200" role="table" x-ref="table">
                <thead class="bg-gray-50">
                    <tr>
                        <?php if ($isAdmin): ?>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" @click="sortBy('id')">
                                <div class="flex items-center justify-center gap-1">ID
                                    <span x-show="sort.column === 'id'">
                                        <template x-if="sort.desc"><i class="fas fa-sort-down"></i></template>
                                        <template x-if="!sort.desc"><i class="fas fa-sort-up"></i></template>
                                    </span>
                                </div>
                            </th>
                        <?php endif; ?>

                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" @click="sortBy('nombres')">
                            <div class="flex items-center justify-center gap-1">Nombres
                                <span x-show="sort.column === 'nombres'">
                                    <template x-if="sort.desc"><i class="fas fa-sort-down"></i></template>
                                    <template x-if="!sort.desc"><i class="fas fa-sort-up"></i></template>
                                </span>
                            </div>
                        </th>

                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" @click="sortBy('paterno')">
                            <div class="flex items-center justify-center gap-1">Paterno
                                <span x-show="sort.column === 'paterno'">
                                    <template x-if="sort.desc"><i class="fas fa-sort-down"></i></template>
                                    <template x-if="!sort.desc"><i class="fas fa-sort-up"></i></template>
                                </span>
                            </div>
                        </th>

                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" @click="sortBy('materno')">
                            <div class="flex items-center justify-center gap-1">Materno
                                <span x-show="sort.column === 'materno'">
                                    <template x-if="sort.desc"><i class="fas fa-sort-down"></i></template>
                                    <template x-if="!sort.desc"><i class="fas fa-sort-up"></i></template>
                                </span>
                            </div>
                        </th>

                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" @click="sortBy('correo')">
                            <div class="flex items-center justify-center gap-1">Correo
                                <span x-show="sort.column === 'correo'">
                                    <template x-if="sort.desc"><i class="fas fa-sort-down"></i></template>
                                    <template x-if="!sort.desc"><i class="fas fa-sort-up"></i></template>
                                </span>
                            </div>
                        </th>

                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Ext</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Firmante</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Admin Fide</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Contacto</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Promotor</th>

                        <?php if ($isAdmin): ?>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Activo</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" x-ref="tbody">
                    <template x-for="row in registrosFiltrados" :key="row.id">
                        <tr class="hover:bg-gray-50">
                            <?php if ($isAdmin): ?>
                                <td class="px-4 py-3 text-sm text-gray-700 text-center" x-text="row.id"></td>
                            <?php endif; ?>
                            <td class="px-4 py-3 text-sm text-gray-700 text-center" x-text="row.nombres"></td>
                            <td class="px-4 py-3 text-sm text-gray-700 text-center" x-text="row.paterno"></td>
                            <td class="px-4 py-3 text-sm text-gray-700 text-center" x-text="row.materno"></td>
                            <td class="px-4 py-3 text-sm text-gray-700 text-center" x-text="row.correo"></td>
                            <td class="px-4 py-3 text-sm text-gray-700 text-center" x-text="row.ext"></td>
                            <td class="px-4 py-3 text-sm text-center">
                                <span x-show="row.firmante" class="inline-flex items-center px-2 py-1 rounded text-xs bg-blue-100 text-blue-800">Sí</span>
                                <span x-show="!row.firmante" class="inline-flex items-center px-2 py-1 rounded text-xs bg-gray-100 text-gray-500">No</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-center">
                                <span x-show="row.adminfide" class="inline-flex items-center px-2 py-1 rounded text-xs bg-purple-100 text-purple-800">Sí</span>
                                <span x-show="!row.adminfide" class="inline-flex items-center px-2 py-1 rounded text-xs bg-gray-100 text-gray-500">No</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-center">
                                <span x-show="row.contacto" class="inline-flex items-center px-2 py-1 rounded text-xs bg-teal-100 text-teal-800">Sí</span>
                                <span x-show="!row.contacto" class="inline-flex items-center px-2 py-1 rounded text-xs bg-gray-100 text-gray-500">No</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-center">
                                <span x-show="row.promotor" class="inline-flex items-center px-2 py-1 rounded text-xs bg-orange-100 text-orange-800">Sí</span>
                                <span x-show="!row.promotor" class="inline-flex items-center px-2 py-1 rounded text-xs bg-gray-100 text-gray-500">No</span>
                            </td>
                            <?php if ($isAdmin): ?>
                                <td class="px-4 py-3 text-sm text-center">
                                    <span x-show="row.activo" class="inline-flex items-center px-2 py-1 rounded text-xs bg-green-100 text-green-800">Activo</span>
                                    <span x-show="!row.activo" class="inline-flex items-center px-2 py-1 rounded text-xs bg-red-100 text-red-800">Inactivo</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="inline-flex gap-2">
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
                            <?php endif; ?>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <?php if (empty($registros)): ?>
            <div class="p-8 text-center text-gray-500">No se encontraron registros para el rango seleccionado.</div>
        <?php endif; ?>
    </div>

    <!-- Modal (Alpine) -->
    <div x-show="modal.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/40" @click="closeModal()"></div>
        <div class="bg-white rounded-lg shadow-lg w-full max-w-xl z-50 overflow-hidden">
            <div class="p-4 border-b flex items-center justify-between">
                <h3 class="text-lg font-medium" x-text="modal.title"></h3>
                <button @click="closeModal()" class="text-gray-500 hover:text-gray-800">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form @submit.prevent="submitForm" class="p-4 space-y-4 max-h-[70vh] overflow-y-auto">
                <input type="hidden" name="id" x-model="form.id">

                <!-- Fila 1: Nombres -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Nombres *</label>
                    <input type="text" x-model="form.nombres" required maxlength="100"
                        class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="Ej: Juan Carlos">
                </div>

                <!-- Fila 2: Apellidos -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Apellido Paterno *</label>
                        <input type="text" x-model="form.paterno" required maxlength="50"
                            class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Ej: García">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Apellido Materno</label>
                        <input type="text" x-model="form.materno" maxlength="50"
                            class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Ej: López">
                    </div>
                </div>

                <!-- Fila 3: Correo y Extensión -->
                <div class="grid grid-cols-3 gap-4">
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Correo *</label>
                        <input type="email" x-model="form.correo" required maxlength="100"
                            class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Ej: juan.garcia@empresa.com">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Extensión</label>
                        <input type="text" x-model="form.ext" maxlength="10"
                            class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Ej: 1234">
                    </div>
                </div>

                <!-- Fila 4: Checkboxes de roles -->
                <div class="border rounded-lg p-4 bg-gray-50">
                    <label class="block text-sm font-medium text-gray-700 mb-3">Roles del Gestor</label>
                    <div class="grid grid-cols-2 gap-4">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" x-model="form.firmante"
                                class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                            <span class="ml-2 text-sm text-gray-700">Firmante</span>
                        </label>
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" x-model="form.adminfide"
                                class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-2 focus:ring-purple-500">
                            <span class="ml-2 text-sm text-gray-700">Admin Fideicomiso</span>
                        </label>
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" x-model="form.contacto"
                                class="w-4 h-4 text-teal-600 border-gray-300 rounded focus:ring-2 focus:ring-teal-500">
                            <span class="ml-2 text-sm text-gray-700">Contacto</span>
                        </label>
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" x-model="form.promotor"
                                class="w-4 h-4 text-orange-600 border-gray-300 rounded focus:ring-2 focus:ring-orange-500">
                            <span class="ml-2 text-sm text-gray-700">Promotor</span>
                        </label>
                    </div>
                </div>

                <!-- Campo Activo -->
                <div class="flex items-center">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" x-model="form.activo"
                            class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-2 focus:ring-green-500">
                        <span class="ml-2 text-sm font-medium text-gray-700">Activo</span>
                    </label>
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

</div>

<script>
    function ServController() {
        return {
            // Datos desde PHP
            registros: <?= json_encode(array_map(function ($r) {
                            return [
                                'id' => (int)$r['id'],
                                'nombres' => $r['nombres'] ?? '',
                                'paterno' => $r['paterno'] ?? '',
                                'materno' => $r['materno'] ?? '',
                                'correo' => $r['correo'] ?? '',
                                'ext' => $r['ext'] ?? '',
                                'firmante' => ($r['firmante'] === 't' || $r['firmante'] === true || $r['firmante'] === '1' || $r['firmante'] === 1),
                                'adminfide' => ($r['adminfide'] === 't' || $r['adminfide'] === true || $r['adminfide'] === '1' || $r['adminfide'] === 1),
                                'contacto' => ($r['contacto'] === 't' || $r['contacto'] === true || $r['contacto'] === '1' || $r['contacto'] === 1),
                                'promotor' => ($r['promotor'] === 't' || $r['promotor'] === true || $r['promotor'] === '1' || $r['promotor'] === 1),
                                'activo' => ($r['activo'] === 't' || $r['activo'] === true || $r['activo'] === '1' || $r['activo'] === 1),
                            ];
                        }, $registros)); ?>,

            registrosFiltrados: [],

            filtros: {
                nombres: '',
                paterno: '',
                materno: '',
                correo: '',
                firmante: false,
                adminfide: false,
                contacto: false,
                promotor: false
            },

            sort: {
                column: 'id',
                desc: true
            },

            modal: {
                open: false,
                title: '',
                actionText: ''
            },

            form: {
                id: '',
                nombres: '',
                paterno: '',
                materno: '',
                correo: '',
                ext: '',
                firmante: false,
                adminfide: false,
                contacto: false,
                promotor: false,
                activo: true
            },

            init() {
                this.registrosFiltrados = [...this.registros];
                this.aplicarFiltros();
            },

            aplicarFiltros() {
                let resultado = [...this.registros];

                // Filtros de texto
                if (this.filtros.nombres.trim()) {
                    const busqueda = this.filtros.nombres.toLowerCase().trim();
                    resultado = resultado.filter(r =>
                        (r.nombres || '').toLowerCase().includes(busqueda)
                    );
                }

                if (this.filtros.paterno.trim()) {
                    const busqueda = this.filtros.paterno.toLowerCase().trim();
                    resultado = resultado.filter(r =>
                        (r.paterno || '').toLowerCase().includes(busqueda)
                    );
                }

                if (this.filtros.materno.trim()) {
                    const busqueda = this.filtros.materno.toLowerCase().trim();
                    resultado = resultado.filter(r =>
                        (r.materno || '').toLowerCase().includes(busqueda)
                    );
                }

                if (this.filtros.correo.trim()) {
                    const busqueda = this.filtros.correo.toLowerCase().trim();
                    resultado = resultado.filter(r =>
                        (r.correo || '').toLowerCase().includes(busqueda)
                    );
                }

                // Filtros de roles (checkboxes)
                if (this.filtros.firmante) {
                    resultado = resultado.filter(r => r.firmante === true);
                }

                if (this.filtros.adminfide) {
                    resultado = resultado.filter(r => r.adminfide === true);
                }

                if (this.filtros.contacto) {
                    resultado = resultado.filter(r => r.contacto === true);
                }

                if (this.filtros.promotor) {
                    resultado = resultado.filter(r => r.promotor === true);
                }

                this.registrosFiltrados = resultado;
                this.applySort();
            },

            limpiarFiltros() {
                this.filtros = {
                    nombres: '',
                    paterno: '',
                    materno: '',
                    correo: '',
                    firmante: false,
                    adminfide: false,
                    contacto: false,
                    promotor: false
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
                    nombres: '',
                    paterno: '',
                    materno: '',
                    correo: '',
                    ext: '',
                    firmante: false,
                    adminfide: false,
                    contacto: false,
                    promotor: false,
                    activo: true
                };
                this.modal.open = true;
                this.modal.title = 'Nuevo Gestor';
                this.modal.actionText = 'Guardar';
            },

            openEditModal(id) {
                fetch(`modules/gestores/actions.php?action=get&id=${id}`)
                    .then(r => r.json())
                    .then(resp => {
                        if (resp.success) {
                            const d = resp.data;
                            this.form = {
                                id: d.id,
                                nombres: d.nombres || '',
                                paterno: d.paterno || '',
                                materno: d.materno || '',
                                correo: d.correo || '',
                                ext: d.ext || '',
                                firmante: d.firmante === 't' || d.firmante === true || d.firmante == 1,
                                adminfide: d.adminfide === 't' || d.adminfide === true || d.adminfide == 1,
                                contacto: d.contacto === 't' || d.contacto === true || d.contacto == 1,
                                promotor: d.promotor === 't' || d.promotor === true || d.promotor == 1,
                                activo: d.activo === 't' || d.activo === true || d.activo == 1
                            };
                            this.modal.open = true;
                            this.modal.title = 'Editar Gestor';
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

            closeModal() {
                this.modal.open = false;
            },

            submitForm() {
                const action = this.form.id ? 'update' : 'create';
                const body = new URLSearchParams();

                body.append('action', action);
                if (this.form.id) body.append('id', this.form.id);
                body.append('nombres', this.form.nombres);
                body.append('paterno', this.form.paterno);
                body.append('materno', this.form.materno);
                body.append('correo', this.form.correo);
                body.append('ext', this.form.ext);
                body.append('firmante', this.form.firmante ? 1 : 0);
                body.append('adminfide', this.form.adminfide ? 1 : 0);
                body.append('contacto', this.form.contacto ? 1 : 0);
                body.append('promotor', this.form.promotor ? 1 : 0);
                body.append('activo', this.form.activo ? 1 : 0);

                fetch('modules/gestores/actions.php', {
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

                fetch('modules/gestores/actions.php', {
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