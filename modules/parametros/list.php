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
    || $permissions->hasPermission($userId, 'parametros', 'lire')
    || $session->hasPermission('parametros', 'lire', 'parametros');

$canCreate = $isAdmin
    || $permissions->hasPermission($userId, 'parametros', 'creer')
    || $session->hasPermission('parametros', 'creer', 'parametros');

$canEdit = $isAdmin
    || $permissions->hasPermission($userId, 'parametros', 'modifier')
    || $session->hasPermission('parametros', 'modifier', 'parametros');
$canDelete = $isAdmin
    || $permissions->hasPermission($userId, 'parametros', 'supprimer')
    || $session->hasPermission('parametros', 'supprimer', 'parametros');


if (!$canView) {
    echo '<div class="p-6"><div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded">No tienes permisos para ver este módulo</div></div>';
    exit;
}


try {
    $query = "SELECT id, iva, ufactura, fechahh, direcbanam, fechabanco, fechabbv, direcbbv, fechascot, fechahsbc, direchsbc, direcpdf, useract, historico FROM t_parametros LIMIT 1";

    $stmt = $db->prepare($query);
    $stmt->execute();
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo '<div class="p-6"><div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded">Error al obtener datos: ' . htmlspecialchars($e->getMessage()) . '</div></div>';
    $registros = [];
}

// Definición de campos para mostrar
$camposConfig = [
    'iva' => ['label' => 'IVA (%)', 'tipo' => 'number'],
    'ufactura' => ['label' => 'Última Factura', 'tipo' => 'number'],
    'fechahh' => ['label' => 'Fecha HH', 'tipo' => 'date'],
    'direcbanam' => ['label' => 'Dirección Banamex', 'tipo' => 'text'],
    'fechabanco' => ['label' => 'Fecha Banco', 'tipo' => 'date'],
    'fechabbv' => ['label' => 'Fecha BBVA', 'tipo' => 'date'],
    'direcbbv' => ['label' => 'Dirección BBVA', 'tipo' => 'text'],
    'fechascot' => ['label' => 'Fecha Scotiabank', 'tipo' => 'date'],
    'fechahsbc' => ['label' => 'Fecha HSBC', 'tipo' => 'date'],
    'direchsbc' => ['label' => 'Dirección HSBC', 'tipo' => 'text'],
    'direcpdf' => ['label' => 'Directorio PDF', 'tipo' => 'text'],
];

$parametro = !empty($registros) ? $registros[0] : [];

?>

<!-- Tailwind + Alpine (CDN). Ya tenías tailwind; incluí Alpine -->
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.4.0/dist/tailwind.min.css" rel="stylesheet">
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

<div x-data="honoController()" x-init="init()" class="p-6 space-y-6">

    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800"> Gestión de Parámetros Fiduciarios</h1>
        </div>

        <!-- Botones de acción -->
        <div class="flex items-center gap-4">
            <?php if ($isAdmin): ?>
                <a href="catalogos.php?mod=parametros&view=historial" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                    <i class="fas fa-history"></i> Ver Historial
                </a>
            <?php endif; ?>

            <!-- Filtro de campo -->
            <div class="flex items-center gap-2">
                <label class="text-sm font-medium text-gray-700">Filtrar:</label>

                <select x-model="filtro" @change="aplicarFiltro()" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">-- Todos --</option>
                    <?php foreach ($camposConfig as $campo => $config): ?>
                        <option value="<?= $campo ?>"><?= htmlspecialchars($config['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- Tabla Vertical de Parámetros -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/3">Parámetro</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor</th>
                        <?php if ($canEdit): ?>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-24">Acción</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($camposConfig as $campo => $config): ?>
                        <tr class="hover:bg-gray-50 fila-param" data-campo="<?= $campo ?>">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900 bg-gray-50">
                                <?= htmlspecialchars($config['label']) ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700">
                                <?php
                                $valor = $parametro[$campo] ?? '';
                                if ($config['tipo'] === 'date' && $valor) {
                                    echo date('d/m/Y', strtotime($valor));
                                } else {
                                    echo htmlspecialchars($valor);
                                }
                                ?>
                            </td>
                            <?php if ($canEdit): ?>
                                <td class="px-6 py-4 text-center">
                                    <button
                                        @click="openEditModal('<?= $campo ?>', '<?= htmlspecialchars($config['label']) ?>', '<?= $config['tipo'] ?>', '<?= htmlspecialchars($parametro[$campo] ?? '') ?>')"
                                        class="px-3 py-1 bg-yellow-400 text-white rounded hover:bg-yellow-500 transition"
                                        title="Editar <?= htmlspecialchars($config['label']) ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (empty($parametro)): ?>
            <div class="p-8 text-center text-gray-500">No se encontraron parámetros configurados.</div>
        <?php endif; ?>
    </div>


    <!-- Modal Edición (Alpine) -->
    <div x-show="modal.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/40" @click="closeModal()"></div>
        <div class="bg-white rounded-lg shadow-lg w-full max-w-md z-50 overflow-hidden">
            <div class="p-4 border-b flex items-center justify-between bg-blue-600 text-white">
                <h3 class="text-lg font-medium">
                    <i class="fas fa-edit mr-2"></i>
                    Editar: <span x-text="modal.label"></span>
                </h3>
                <button @click="closeModal()" class="text-white hover:text-gray-200">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form @submit.prevent="submitForm" class="p-6 space-y-4">
                <input type="hidden" x-model="form.campo">

                <!-- Campo dinámico según tipo -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2" x-text="modal.label"></label>

                    <!-- Input tipo texto -->
                    <template x-if="form.tipo === 'text'">
                        <input
                            type="text"
                            x-model="form.valor"
                            maxlength="60"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Ingrese el valor">
                    </template>

                    <!-- Input tipo número -->
                    <template x-if="form.tipo === 'number'">
                        <input
                            type="number"
                            x-model="form.valor"
                            step="1"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Ingrese el valor numérico">
                    </template>

                    <!-- Input tipo fecha -->
                    <template x-if="form.tipo === 'date'">
                        <input
                            type="date"
                            x-model="form.valor"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </template>
                </div>

                <!-- Botones -->
                <div class="flex justify-end gap-2 pt-4 border-t">
                    <button
                        type="button"
                        @click="closeModal()"
                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        <i class="fas fa-save mr-1"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
</div>

</div>

<script>
    function honoController() {
        return {
            filtro: '',
            modal: {
                open: false,
                label: ''
            },
            form: {
                campo: '',
                tipo: '',
                valor: ''
            },

            init() {
                // Inicialización si es necesaria
            },

            aplicarFiltro() {
                const filas = document.querySelectorAll('.fila-param');
                filas.forEach(fila => {
                    if (this.filtro === '' || fila.dataset.campo === this.filtro) {
                        fila.style.display = '';
                    } else {
                        fila.style.display = 'none';
                    }
                });
            },

            openEditModal(campo, label, tipo, valorActual) {
                this.form.campo = campo;
                this.form.tipo = tipo;
                this.form.valor = valorActual;
                this.modal.label = label;
                this.modal.open = true;
            },

            closeModal() {
                this.modal.open = false;
                this.form = {
                    campo: '',
                    tipo: '',
                    valor: ''
                };
            },

            submitForm() {
                const body = new URLSearchParams();
                body.append('action', 'update');
                body.append('campo', this.form.campo);
                body.append('valor', this.form.valor);

                fetch('modules/parametros/actions.php', {
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
                            alert(resp.message || 'Error al guardar.');
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