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
    || $permissions->hasPermission($userId, 'honorarios', 'lire')
    || $session->hasPermission('catalogos', 'lire', 'honorarios');

$canCreate = $isAdmin 
    || $permissions->hasPermission($userId, 'honorarios', 'creer')
    || $session->hasPermission('catalogos', 'creer', 'honorarios');

$canEdit = $isAdmin 
    || $permissions->hasPermission($userId, 'honorarios', 'modifier')
    || $session->hasPermission('catalogos', 'modifier', 'honorarios');
$canDelete = $isAdmin 
    || $permissions->hasPermission($userId, 'honorarios', 'supprimer')
    || $session->hasPermission('catalogos', 'supprimer', 'honorarios');


if (!$canView) {
    echo '<div class="p-6"><div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded">No tienes permisos para ver este módulo</div></div>';
    exit;
}


try {
    $query = "SELECT * FROM t_cat_periodos
    WHERE activo = true
    ORDER BY plazo ASC";
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

<div x-data="inpcController()" x-init="init()" class="p-6 space-y-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800"><i class="mr-2"></i> Gestión de Honorarios</h1>
        </div>
        <?php if ($canCreate): ?>
        <button @click="openCreateModal()" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
            <i class="fas fa-plus"></i> Nuevo Honorario
        </button>
        <?php endif; ?>
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

                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" @click="sortBy('plazo')">
                            <div class="flex items-center justify-end gap-2">Plazo <span x-show="sort.column === 'plazo'"> <template x-if="sort.desc"><i class="fas fa-sort-down"></i></template><template x-if="!sort.desc"><i class="fas fa-sort-up"></i></template></span></div>
                        </th>

                        <?php if ($isAdmin): ?>
                    <!-- <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descripción</th>-->
                    <!--    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registro</th> -->
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

                        <td class="px-4 py-3 text-sm text-gray-700"></td>
                        <td class="px-4 py-3 text-sm text-gray-700 text-right"><?= number_format($row['plazo'], 4); ?></td>

                        <?php if ($isAdmin): ?>

                        <td class="px-4 py-3 text-sm text-gray-700"><?= htmlspecialchars($row['nombre'] ?? $row['nombre'] ?? ''); ?></td>                        
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
                    <label class="block text-sm text-gray-600">Indice *</label>
                    <input type="number" step="0.0001" min="0" max="300" x-model="form.indice" required class="mt-1 w-full px-3 py-2 border rounded-lg">
                    <p class="text-xs text-gray-500 mt-1">Ingrese el valor</p>
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
function inpcController() {
    return {
        // Datos iniciales (inyectar registros desde PHP para ordenamiento client-side)
        registros: <?= json_encode(array_map(function($r){
            // asegurar tipos y formato ISO para fechas
            return [
                'id' => $r['id'],
                'plazo' => floatval($r['plazo ']),
                'activo' => !empty($r['activo']) ? 1 : 0,
                'nombre' => $r['nombre'] ?? $r['nombre'] ?? '',
            ];
        }, $registros)); ?>,

        sort: { column: 'fecha', desc: true },

        modal: { open: false, title: '', actionText: '' },
        form: { id: '', fecha: '', indice: '', activo: 1 },

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
                tdDato.textContent = Number(r.plazo).toFixed(4);
                tr.appendChild(tdDato);

                if (isAdmin) {

                    const tdNombre = document.createElement('td');
                    tdNombre.className = 'px-4 py-3 text-sm text-gray-700';
                    tdNombre.textContent = r.nombre || '';
                    tr.appendChild(tdNombre);

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
            this.modal.title = 'Nuevo Indice inpc';
            this.modal.actionText = 'Guardar';
            this.form = { id: '', fecha: '', indice: '' };
        },

        openEditModal(id) {
            fetch(`modules/inpc/actions.php?action=get&id=${id}`)
                .then(r => r.json())
                .then(resp => {
                    if (resp.success) {
                        this.form.id = resp.data.id;
                        this.form.fecha = resp.data.fecha;
                        this.form.indice = resp.data.indice;
                        this.modal.open = true;
                        this.modal.title = 'Editar Indice inpc';
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
            body.append('indice', this.form.indice);
//    console.log('Datos enviados:', Object.fromEntries(body));
//    console.log('Form original:', this.form);            

            fetch('modules/inpc/actions.php', {
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
            fetch('modules/inpc/actions.php', {
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
