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
    || $permissions->hasPermission($userId, 'tiie', 'lire')
    || $session->hasPermission('catalogos', 'lire', 'tiie');

$canCreate = $isAdmin 
    || $permissions->hasPermission($userId, 'tiie', 'creer')
    || $session->hasPermission('catalogos', 'creer', 'tiie');

$canEdit = $isAdmin 
    || $permissions->hasPermission($userId, 'tiie', 'modifier')
    || $session->hasPermission('catalogos', 'modifier', 'tiie');

$canDelete = $isAdmin 
    || $permissions->hasPermission($userId, 'tiie', 'supprimer')
    || $session->hasPermission('catalogos', 'supprimer', 'tiie');


if (!$canView) {
    echo '<div class="p-6"><div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded">No tienes permisos para ver este módulo</div></div>';
    exit;
}

$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');

try {
    $query = "SELECT * FROM t_tiie 
              WHERE fecha BETWEEN ? AND ? 
              ORDER BY fecha DESC";
    $stmt = $db->prepare($query);
    $stmt->execute([$fecha_inicio, $fecha_fin]);
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo '<div class="p-6"><div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded">Error al obtener datos: ' . htmlspecialchars($e->getMessage()) . '</div></div>';
    $registros = [];
}


// Calcular estadísticas
$stats = [
    'total' => count($registros),
    'promedio' => 0,
    'max' => null,
    'min' => null,
    'activos' => 0,
];

if (!empty($registros)) {
    $sum = 0;
    $max = null;
    $min = null;
    $activos = 0;
    foreach ($registros as $r) {
        $val = floatval($r['dato']);
        $sum += $val;
        if ($max === null || $val > $max) $max = $val;
        if ($min === null || $val < $min) $min = $val;
        if (!empty($r['activo'])) $activos++;
    }
    $stats['promedio'] = $sum / count($registros);
    $stats['max'] = $max;
    $stats['min'] = $min;
    $stats['activos'] = $activos;
}
?>

<!-- Tailwind + Alpine (CDN). Ya tenías tailwind; incluí Alpine -->
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.4.0/dist/tailwind.min.css" rel="stylesheet">
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

<div x-data="tiieController()" x-init="init()" class="p-6 space-y-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800"><i class="fas fa-percentage mr-2"></i> Gestión de Gestión de Tasas de Interés Interbancarias de Equilibrio</h1>
<!--            <p class="text-sm text-gray-500">Tasas de Interés Interbancarias de Equilibrio</p> -->
        </div>
        <?php if ($canCreate): ?>
        <button @click="openCreateModal()" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
            <i class="fas fa-plus"></i> Nueva Tasa
        </button>
        <?php endif; ?>
        <?php if ($isAdmin): ?>
        <!--
            <button @click=" " class="inline-flex items-center gap-2 px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition">
             <i class="fa-solid fa-angles-down"></i> Sincronizar
        </button>-->
        <?php endif; ?>
        
    </div>

    <!-- Estadísticas -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 font-medium">Total Registros</p>
                    <p class="text-2xl font-bold text-gray-800"><?= $stats['total']; ?></p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-list text-2xl text-blue-600"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-indigo-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 font-medium">Promedio (%)</p>
                    <p class="text-2xl font-bold text-gray-800"><?= $stats['promedio'] !== 0 ? number_format($stats['promedio'], 4) : '0.0000'; ?>%</p>
                </div>
                <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-chart-line text-2xl text-indigo-600"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 font-medium">Máxima (%)</p>
                    <p class="text-2xl font-bold text-gray-800"><?= $stats['max'] !== null ? number_format($stats['max'], 4) : '-'; ?>%</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-chevron-up text-2xl text-green-600"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 font-medium">Mínima (%)</p>
                    <p class="text-2xl font-bold text-gray-800"><?= $stats['min'] !== null ? number_format($stats['min'], 4) : '-'; ?>%</p>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-chevron-down text-2xl text-purple-600"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-teal-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 font-medium">Activos</p>
                    <p class="text-2xl font-bold text-gray-800"><?= $stats['activos']; ?></p>
                </div>
                <div class="w-12 h-12 bg-teal-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-check-circle text-2xl text-teal-600"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-lg shadow p-4">
        <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <input type="hidden" name="mod" value="tiie">
            <div>
                <label class="block text-sm text-gray-600">Fecha inicio</label>
                <input type="date" name="fecha_inicio" value="<?= htmlspecialchars($fecha_inicio); ?>" class="mt-1 w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm text-gray-600">Fecha fin</label>
                <input type="date" name="fecha_fin" value="<?= htmlspecialchars($fecha_fin); ?>" class="mt-1 w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"><i class="fas fa-filter"></i> Filtrar</button>
                <a href="catalogos.php?mod=tiie" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition" ><i class="fas fa-eraser mr-1"></i> Limpiar filtros</a>
            </div>
        </form>
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200" role="table" x-ref="table">
                <thead class="bg-gray-50">
                    <tr>
                        <?php if ($isAdmin): ?>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" @click="sortBy('id')">
                            <div class="flex items-center gap-2">ID <span x-show="sort.column === 'id'"> <template x-if="sort.desc"><i class="fas fa-sort-down"></i></template><template x-if="!sort.desc"><i class="fas fa-sort-up"></i></template></span></div>
                        </th>
                        <?php endif; ?>

                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" @click="sortBy('fecha')">
                            <div class="flex items-center gap-2">Fecha <span x-show="sort.column === 'fecha'"> <template x-if="sort.desc"><i class="fas fa-sort-down"></i></template><template x-if="!sort.desc"><i class="fas fa-sort-up"></i></template></span></div>
                        </th>

                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" @click="sortBy('dato')">
                            <div class="flex items-center justify-end gap-2">Tasa <span x-show="sort.column === 'dato'"> <template x-if="sort.desc"><i class="fas fa-sort-down"></i></template><template x-if="!sort.desc"><i class="fas fa-sort-up"></i></template></span></div>
                        </th>

                        <?php if ($isAdmin): ?>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usuario</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registro</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" x-ref="tbody">
                    <?php foreach ($registros as $row): ?>
                    <tr class="hover:bg-gray-50">
                        <?php if ($isAdmin): ?>
                        <td class="px-4 py-3 text-sm text-gray-700"><?= htmlspecialchars($row['id']); ?></td>
                        <?php endif; ?>

                        <td class="px-4 py-3 text-sm text-gray-700"><?= date('d/m/Y', strtotime($row['fecha'])); ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700 text-right"><?= number_format($row['dato'], 4); ?></td>

                        <?php if ($isAdmin): ?>
                        <td class="px-4 py-3 text-sm">
                            <?php if ($row['activo']): ?>
                                <span class="inline-flex items-center px-2 py-1 rounded text-xs bg-green-100 text-green-800">Activo</span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2 py-1 rounded text-xs bg-red-100 text-red-800">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700"><?= htmlspecialchars($row['usuausuario'] ?? $row['usuario'] ?? ''); ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700"><?= date('d/m/Y', strtotime($row['fecha_insercion'])) . ' ' . htmlspecialchars($row['hora_insercion']); ?></td>
                        <td class="px-4 py-3 text-center">
                            <div class="inline-flex gap-2">
                                <?php if ($canEdit): ?>
                                <button @click="openEditModal(<?= htmlspecialchars($row['id']); ?>)" class="px-2 py-1 bg-yellow-400 text-white rounded hover:brightness-90" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php endif; ?>
                                <?php if ($canDelete): ?>
                                <button @click="confirmDelete(<?= htmlspecialchars($row['id']); ?>)" class="px-2 py-1 bg-red-500 text-white rounded hover:brightness-90" title="Eliminar">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
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
                <button @click="closeModal()" class="text-gray-500 hover:text-gray-800"><i class="fas fa-times"></i></button>
            </div>

            <form @submit.prevent="submitForm" class="p-4 space-y-4">
                <input type="hidden" name="id" x-model="form.id">

                <div>
                    <label class="block text-sm text-gray-600">Fecha *</label>
                    <input type="date" x-model="form.fecha" required class="mt-1 w-full px-3 py-2 border rounded-lg">
                </div>

                <div>
                    <label class="block text-sm text-gray-600">Tasa</label>
                    <input type="number" step="0.0001" min="0" max="100" x-model="form.dato" required class="mt-1 w-full px-3 py-2 border rounded-lg">
                    <p class="text-xs text-gray-500 mt-1">Ingrese el valor</p>
                </div>

                <div class="flex items-center gap-3">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" x-model="form.activo" checked  class="form-checkbox h-4 w-4">
                        <span class="text-sm text-gray-600">Registro Activo</span>
                    </label>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" @click="closeModal()" class="px-4 py-2 bg-gray-200 rounded">Cancelar</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded" x-text="modal.actionText"></button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
function tiieController() {
    return {
        // Datos iniciales (inyectar registros desde PHP para ordenamiento client-side)
        registros: <?= json_encode(array_map(function($r){
            // asegurar tipos y formato ISO para fechas
            return [
                'id' => $r['id'],
                'fecha' => $r['fecha'],
                'dato' => floatval($r['dato']),
                'activo' => !empty($r['activo']) ? 1 : 0,
                'usuario' => $r['usuausuario'] ?? $r['usuario'] ?? '',
                'fecha_insercion' => $r['fecha_insercion'],
                'hora_insercion' => $r['hora_insercion'],
            ];
        }, $registros)); ?>,

        sort: { column: 'fecha', desc: true },

        modal: { open: false, title: '', actionText: '' },
        form: { id: '', fecha: '', dato: '', activo: 1 },

        init() {
            // Render inicial (ya están los rows generados por PHP en DOM, pero mantenemos registros para resort)
            // Si quieres reemplazar filas por render client-side, puedes hacerlo aquí.
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
            this.registros.sort((a, b) => {
                let va = a[col], vb = b[col];
                if (col === 'fecha') {
                    va = new Date(va); vb = new Date(vb);
                }
                if (va < vb) return this.sort.desc ? 1 : -1;
                if (va > vb) return this.sort.desc ? -1 : 1;
                return 0;
            });
            // Reconstruir tbody con registros ordenados
            this.renderRows();
        },

        renderRows() {
            // Solo reconstruye si la tabla fue renderizada por PHP; reemplazamos el tbody contenido.
            const tbody = this.$refs.tbody;
            if (!tbody) return;
            tbody.innerHTML = '';
            const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;
            for (const r of this.registros) {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-gray-50';

                if (isAdmin) {
                    const tdId = document.createElement('td');
                    tdId.className = 'px-4 py-3 text-sm text-gray-700';
                    tdId.textContent = r.id;
                    tr.appendChild(tdId);
                }

                const tdFecha = document.createElement('td');
                tdFecha.className = 'px-4 py-3 text-sm text-gray-700';
                const d = new Date(r.fecha);
                const dd = String(d.getDate()).padStart(2,'0') + '/' + String(d.getMonth()+1).padStart(2,'0') + '/' + d.getFullYear();
                tdFecha.textContent = dd;
                tr.appendChild(tdFecha);

                const tdDato = document.createElement('td');
                tdDato.className = 'px-4 py-3 text-sm text-gray-700 text-right';
                tdDato.textContent = Number(r.dato).toFixed(4);
                tr.appendChild(tdDato);

                if (isAdmin) {
                    const tdEstado = document.createElement('td');
                    tdEstado.className = 'px-4 py-3 text-sm';
                    tdEstado.innerHTML = r.activo ? '<span class="inline-flex items-center px-2 py-1 rounded text-xs bg-green-100 text-green-800">Activo</span>' : '<span class="inline-flex items-center px-2 py-1 rounded text-xs bg-red-100 text-red-800">Inactivo</span>';
                    tr.appendChild(tdEstado);

                    const tdUsuario = document.createElement('td');
                    tdUsuario.className = 'px-4 py-3 text-sm text-gray-700';
                    tdUsuario.textContent = r.usuario || '';
                    tr.appendChild(tdUsuario);

                    const tdRegistro = document.createElement('td');
                    tdRegistro.className = 'px-4 py-3 text-sm text-gray-700';
                    tdRegistro.textContent = r.fecha_insercion + ' ' + (r.hora_insercion || '');
                    tr.appendChild(tdRegistro);

                    const tdAcc = document.createElement('td');
                    tdAcc.className = 'px-4 py-3 text-center';
                    tdAcc.innerHTML = `<div class="inline-flex gap-2">
                        <?php if ($canEdit): ?> <button onclick="document.querySelector('[x-data]').__x.$data.openEditModal(${r.id})" class="px-2 py-1 bg-yellow-400 text-white rounded"> <i class="fas fa-edit"></i> </button> <?php endif; ?>
                        <?php if ($canDelete): ?> <button onclick="document.querySelector('[x-data]').__x.$data.confirmDelete(${r.id})" class="px-2 py-1 bg-red-500 text-white rounded"> <i class="fas fa-trash"></i> </button> <?php endif; ?>
                    </div>`;
                    tr.appendChild(tdAcc);
                }

                tbody.appendChild(tr);
            }
        },

        openCreateModal() {
            this.modal.open = true;
            this.modal.title = 'Nueva Tasa TIIE';
            this.modal.actionText = 'Guardar';
            this.form = { id: '', fecha: '', dato: '', activo: 1 };
        },

        openEditModal(id) {
            console.log('Solicitando ID:', id);
            fetch(`modules/tiie/actions.php?action=get&id=${id}`)
                .then(r => r.json())
                .then(resp => {
                    console.log('Respuesta recibida:', resp);
                    if (resp.success) {
                        this.form.id = resp.data.id;
                        this.form.fecha = resp.data.fecha;
                        this.form.dato = resp.data.dato;
                        this.form.activo = resp.data.activo == 1 ? 1 : 0;
                        this.modal.open = true;
                        this.modal.title = 'Editar Tasa TIIE';
                        this.modal.actionText = 'Actualizar';
                    } else {
                        alert(resp.message || 'No se pudo obtener el registro.');
                    }
                }).catch(err => {
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
            body.append('fecha', this.form.fecha);
            body.append('dato', this.form.dato);
            body.append('activo', this.form.activo ? 1 : 0);
    console.log('Datos enviados:', Object.fromEntries(body));
    console.log('Form original:', this.form);            

            fetch('modules/tiie/actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            }).then(async r => {                
                try {
                    const data = await r.json();
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message || 'Error al guardar.');
                    }
                } catch (err) {                    
                    location.reload();
                }
            }).catch(err => {
                console.error(err);
                alert('Error en la petición.');
            });
        },

        confirmDelete(id) {
            if (!confirm('¿Está seguro de eliminar este registro?')) return;
            const body = new URLSearchParams();
            body.append('action', 'delete');
            body.append('id', id);
            fetch('modules/tiie/actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            }).then(r => r.json())
              .then(resp => {
                  if (resp.success) location.reload();
                  else alert(resp.message || 'No se pudo eliminar.');
              }).catch(err => {
                  console.error(err);
                  alert('Error en la petición.');
              });
        },
    }
}
</script>
