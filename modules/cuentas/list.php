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
    || $permissions->hasPermission($userId, 'cuentas', 'lire')
    || $session->hasPermission('catalogos', 'lire', 'cuentas');

$canCreate = $isAdmin 
    || $permissions->hasPermission($userId, 'cuentas', 'creer')
    || $session->hasPermission('catalogos', 'creer', 'cuentas');

$canEdit = $isAdmin 
    || $permissions->hasPermission($userId, 'cuentas', 'modifier')
    || $session->hasPermission('catalogos', 'modifier', 'cuentas');

$canDelete = $isAdmin 
    || $permissions->hasPermission($userId, 'cuentas', 'supprimer')
    || $session->hasPermission('catalogos', 'supprimer', 'cuentas');

if (!$canView) {
    echo '<div class="p-6"><div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded">No tienes permisos para ver este módulo</div></div>';
    exit;
}

try {
    $query = "SELECT c.*, 
                     b.banco as nombre_categoria,
                     bx.banco as nombre_banxico,
                     bf.fideicomiso as nombre_fideicomiso
              FROM t_cuentas c
              LEFT JOIN t_cat_bancos b ON c.categoria::integer = b.id
              LEFT JOIN t_cat_banxico bx ON c.banxico = bx.id
              LEFT JOIN t_fideicomisos bf ON c.fideicomiso::integer = bf.id";

    if (!$isAdmin) {
        $query .= " WHERE c.activo = true";
    }

    $query .= " ORDER BY c.id DESC";

    $stmt = $db->prepare($query);
    $stmt->execute();
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmtCategorias = $db->prepare("SELECT id, banco FROM t_cat_bancos WHERE activo = true ORDER BY banco");
    $stmtCategorias->execute();
    $categorias = $stmtCategorias->fetchAll(PDO::FETCH_ASSOC);

    $stmtBanxico = $db->prepare("SELECT id, banco FROM t_cat_banxico WHERE activo = true ORDER BY banco");
    $stmtBanxico->execute();
    $bancosXico = $stmtBanxico->fetchAll(PDO::FETCH_ASSOC);

    $stmtFideicomiso = $db->prepare("SELECT id, fideicomiso FROM t_fideicomisos WHERE activo = true ORDER BY fideicomiso");
    $stmtFideicomiso->execute();
    $fideicomisos = $stmtFideicomiso->fetchAll(PDO::FETCH_ASSOC);

    $stmtConst = $db->prepare("SELECT val FROM t_const WHERE id = 26");
    $stmtConst->execute();
    $limiteFormatos = $stmtConst->fetchColumn();
    if (!$limiteFormatos || $limiteFormatos < 1) {
        $limiteFormatos = 10;
    }

    $stmtFechasTDC = $db->prepare("SELECT DISTINCT DATE(fecha) as fecha FROM t_tdc ORDER BY fecha DESC");
    $stmtFechasTDC->execute();
    $fechasTDC = $stmtFechasTDC->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    echo '<div class="p-6"><div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded">Error al obtener datos: ' . htmlspecialchars($e->getMessage()) . '</div></div>';
    $registros = [];
    $categorias = [];
    $bancosXico = [];
    $fideicomisos = [];
    $limiteFormatos = 10;
    $fechasTDC = [];
}
?>

<link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.4.0/dist/tailwind.min.css" rel="stylesheet">
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

<div x-data="cuentasController()" x-init="init()" class="p-6 space-y-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800"><i class="fa-regular fa-id-card mr-2"></i>Gestión de Cuentas</h1>
        </div>
        <?php if ($canCreate): ?>
        <button @click="openCreateModal()" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
            <i class="fas fa-plus"></i> Nueva Cuenta
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
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" @click="sortBy('id')">
                            <div class="flex items-center justify-center gap-2">ID <span x-show="sort.column === 'id'"><template x-if="sort.desc"><i class="fas fa-sort-down"></i></template><template x-if="!sort.desc"><i class="fas fa-sort-up"></i></template></span></div>
                        </th>
                        <?php endif; ?>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" @click="sortBy('nombre_de_banco')">
                            <div class="flex items-center justify-center gap-2">Banco <span x-show="sort.column === 'nombre_de_banco'"><template x-if="sort.desc"><i class="fas fa-sort-down"></i></template><template x-if="!sort.desc"><i class="fas fa-sort-up"></i></template></span></div>
                        </th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" @click="sortBy('nombre_categoria')">
                            <div class="flex items-center justify-center gap-2">Categoría <span x-show="sort.column === 'nombre_categoria'"><template x-if="sort.desc"><i class="fas fa-sort-down"></i></template><template x-if="!sort.desc"><i class="fas fa-sort-up"></i></template></span></div>
                        </th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" @click="sortBy('nombre_banxico')">
                            <div class="flex items-center justify-center gap-2">Banxico <span x-show="sort.column === 'nombre_banxico'"><template x-if="sort.desc"><i class="fas fa-sort-down"></i></template><template x-if="!sort.desc"><i class="fas fa-sort-up"></i></template></span></div>
                        </th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" @click="sortBy('cuenta')">
                            <div class="flex items-center justify-center gap-2">Cuenta <span x-show="sort.column === 'cuenta'"><template x-if="sort.desc"><i class="fas fa-sort-down"></i></template><template x-if="!sort.desc"><i class="fas fa-sort-up"></i></template></span></div>
                        </th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" @click="sortBy('clabe')">
                            <div class="flex items-center justify-center gap-2">CLABE <span x-show="sort.column === 'clabe'"><template x-if="sort.desc"><i class="fas fa-sort-down"></i></template><template x-if="!sort.desc"><i class="fas fa-sort-up"></i></template></span></div>
                        </th>
                         <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" @click="sortBy('nombre_fideicomiso')">
                            <div class="flex items-center justify-center gap-2">Fideicomiso <span x-show="sort.column === 'nombre_fideicomiso'"><template x-if="sort.desc"><i class="fas fa-sort-down"></i></template><template x-if="!sort.desc"><i class="fas fa-sort-up"></i></template></span></div>
                        </th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" @click="sortBy('saldo_actual')">
                            <div class="flex items-center justify-center gap-2">Saldo <span x-show="sort.column === 'saldo_actual'"><template x-if="sort.desc"><i class="fas fa-sort-down"></i></template><template x-if="!sort.desc"><i class="fas fa-sort-up"></i></template></span></div>
                        </th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" @click="sortBy('tipo_moneda')">
                            <div class="flex items-center justify-center gap-2">Moneda <span x-show="sort.column === 'tipo_moneda'"><template x-if="sort.desc"><i class="fas fa-sort-down"></i></template><template x-if="!sort.desc"><i class="fas fa-sort-up"></i></template></span></div>
                        </th>
                        <?php if ($isAdmin): ?>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                        <?php endif; ?>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" x-ref="tbody">
                    <?php foreach ($registros as $row): ?>
                    <tr class="hover:bg-gray-50">
                        <?php if ($isAdmin): ?>
                        <td class="px-4 py-3 text-sm text-gray-700 text-center"><?= htmlspecialchars($row['id']); ?></td>
                        <?php endif; ?>
                        <td class="px-4 py-3 text-sm text-gray-700 text-center"><?= htmlspecialchars($row['nombre_categoria'] ?? 'N/A'); ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700 text-center"><?= htmlspecialchars($row['nombre_de_banco'] ?? ''); ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700 text-center"><?= htmlspecialchars($row['nombre_banxico'] ?? 'N/A'); ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700 text-center"><?= htmlspecialchars($row['nombre_fideicomiso'] ?? 'N/A'); ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700 text-center"><?= htmlspecialchars($row['cuenta'] ?? ''); ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700 text-center"><?= htmlspecialchars($row['clabe'] ?? ''); ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700 text-center"><?= number_format($row['saldo_actual'] ?? 0, 2); ?></td>
                        <td class="px-4 py-3 text-sm text-center">
                            <span class="inline-flex items-center px-2 py-1 rounded text-xs <?= ($row['tipo_moneda'] ?? 'MN') === 'MN' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' ?>">
                                <?= htmlspecialchars($row['tipo_moneda'] ?? 'MN'); ?>
                            </span>
                        </td>
                        <?php if ($isAdmin): ?>
                        <td class="px-4 py-3 text-sm">
                            <?php if ($row['activo']): ?>
                                <span class="inline-flex items-center px-2 py-1 rounded text-xs bg-green-100 text-green-800">Activo</span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2 py-1 rounded text-xs bg-red-100 text-red-800">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
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
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (empty($registros)): ?>
            <div class="p-8 text-center text-gray-500">No se encontraron cuentas.</div>
        <?php endif; ?>
    </div>

<!-- Modal (Alpine) -->
<div x-show="modal.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto">
    <div class="absolute inset-0 bg-black/40" @click="closeModal()"></div>
    <div class="bg-white rounded-lg shadow-lg w-full max-w-5xl z-50 overflow-hidden my-8 mx-4 max-h-[90vh] flex flex-col">
        <div class="p-4 border-b flex items-center justify-between">
            <h3 class="text-lg font-medium" x-text="modal.title"></h3>
            <button @click="closeModal()" class="text-gray-500 hover:text-gray-800">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form @submit.prevent="submitForm" class="flex-1 overflow-y-auto">
            <div class="p-6 space-y-3">
                <input type="hidden" name="id" x-model="form.id">

                <!-- Sección: Información General -->
                <div>
                    <h4 class="text-md font-semibold text-gray-700 mb-4 pb-2 border-b">Información General</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <!-- Fecha de apertura -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Fecha de Apertura</label>
                            <input type="date" x-model="form.fecha_de_apertura" 
                                   class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 cursor-pointer"
                                   max="<?php echo date('Y-m-d'); ?>" 
                                   min="1990-01-01">
                            <p class="text-xs text-gray-500 mt-1">Fecha en que se abrió la cuenta</p>
                        </div>

                        <!-- Fideicomiso - OBLIGATORIO -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Fideicomiso <span class="text-red-500">*</span>
                            </label>
                            <select x-model="form.fideicomiso" required
                                    class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Seleccione</option>
                                <?php foreach ($fideicomisos as $fideicomiso): ?>
                                <option value="<?= $fideicomiso['id'] ?>"><?= htmlspecialchars($fideicomiso['fideicomiso']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Tipo de cuenta -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Tipo de Cuenta</label>
                            <select x-model="form.tipo_de_cuenta" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="0">Individual</option>
                                <option value="1">Mancomunada</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Especifica si es individual o compartida</p>
                        </div>

                        <!-- Categoría - OBLIGATORIO -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Categoría <span class="text-red-500">*</span>
                            </label>
                            <select x-model="form.categoria" required
                                    class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Seleccione</option>
                                <?php foreach ($categorias as $categoria): ?>
                                <option value="<?= $categoria['id'] ?>"><?= htmlspecialchars($categoria['banco']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Banxico -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Banxico</label>
                            <select x-model="form.banxico" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Seleccione</option>
                                <?php foreach ($bancosXico as $banxico): ?>
                                <option value="<?= $banxico['id'] ?>"><?= htmlspecialchars($banxico['banco']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Nombre del Banco - OBLIGATORIO -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Nombre del Banco <span class="text-red-500">*</span>
                            </label>
                            <input type="text" x-model="form.nombre_de_banco" required
                                   class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Ej: BBVA Bancomer">
                        </div>

                        <!-- Cuenta - OBLIGATORIO -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Número de Cuenta <span class="text-red-500">*</span>
                            </label>
                            <input type="text" x-model="form.cuenta" maxlength="16" oninput="validarInput(event)" required
                                   class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Máximo 16 dígitos">
                        </div>

                        <!-- CLABE - OBLIGATORIO -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                CLABE <span class="text-red-500">*</span>
                            </label>
                            <input type="text" x-model="form.clabe" maxlength="18" oninput="validarInput(event)" required
                                   class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="18 dígitos">
                        </div>

                        <!-- Sucursal -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Sucursal</label>
                            <input type="text" x-model="form.sucursal" 
                                   class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                </div>

                <!-- Sección: Saldos y Moneda -->
                <div>
                    <h4 class="text-md font-semibold text-gray-700 mb-4 pb-2 border-b">Saldos y Moneda</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4"
                    oninput="validarInput(event)">
                        <!-- Saldo Inicial -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Saldo Inicial</label>
                            <input type="text" x-model="form.saldo_inicial"
                                   class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   oninput="validarInput(event)" placeholder="0.00">
                        </div>

                        <!-- Saldo Actual -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Saldo Actual</label>
                            <input type="text" x-model="form.saldo_actual"
                                   class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="0.00"
                                   oninput="validarInput(event)">
                        </div>

                        <!-- Cuenta Contable - OBLIGATORIO -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Cuenta Contable <span class="text-red-500">*</span>
                            </label>
                            <input type="text" x-model="form.cuenta_contable" oninput="validarInput(event)" required
                                   class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <!-- Tipo de Moneda -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Tipo de Moneda</label>
                            <select x-model="form.tipo_moneda" @change="handleMonedaChange()" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="MN">MN - Moneda Nacional</option>
                                <option value="USD">USD - Dólar Americano</option>
                            </select>
                        </div>

                        <!-- Fecha de Cambio -->
                        <div x-show="form.tipo_moneda !== 'MN' && form.tipo_moneda !== ''" x-cloak>
                            <label class="block text-sm font-medium text-gray-700">Fecha Tipo de Cambio</label>
                            <input type="date" x-model="form.fecha_de_cambio" @change="cargarTasaCambio()" 
                                   class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 cursor-pointer"
                                   max="<?php 
                                       $fecha_ayer = new DateTime();
                                       $fecha_ayer->modify('-1 day');
                                       echo $fecha_ayer->format('Y-m-d'); 
                                   ?>" 
                                   min="1990-01-01">
                        </div>

                        <!-- Tasa de Cambio -->
                        <div x-show="form.tipo_moneda !== 'MN' && form.tipo_moneda !== ''" x-cloak>
                            <label class="block text-sm font-medium text-gray-700">Tasa de Cambio</label>
                            <input type="text" x-model="form.tasa_de_cambio" readonly
                                   class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 cursor-not-allowed"
                                   placeholder="Seleccione fecha">
                        </div>
                    </div>
                </div>

                <!-- Sección: Configuración de Cheques -->
                <div>
                    <h4 class="text-md font-semibold text-gray-700 mb-4 pb-2 border-b">Configuración de Cheques</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- Formato Cheques -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Formato Cheques</label>
                            <select x-model="form.formato_cheques" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Seleccione</option>
                                <?php for ($i = 1; $i <= $limiteFormatos; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <!-- Cuenta Eje -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Cuenta Eje</label>
                            <input type="text" x-model="form.cuenta_eje" oninput="validarInput(event)"
                                   class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <!-- Cheque Inicial -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Cheque Inicial</label>
                            <input type="text" x-model="form.cheque_inicial"  maxlength="3" oninput="validarInput(event)"
                                   class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <!-- Cheque Final -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Cheque Final</label>
                            <input type="text" x-model="form.cheque_final"  maxlength="3" oninput="validarInput(event)"
                                   class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>

                        <!-- Último Cheque Asignado -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Último Cheque</label>
                            <input type="text" x-model="form.ultimo_cheque_asignado" maxlength="3" oninput="validarInput(event)"
                                   class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                </div>

                <!-- Sección: Opciones Adicionales -->
                <div>
                    <h4 class="text-md font-semibold text-gray-700 mb-4 pb-2 border-b">Opciones Adicionales</h4>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                        <!-- Cheques Especiales Imp -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Cheques Esp. Imp.</label>
                            <select x-model="form.cheques_especiales_imp" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="0">No</option>
                                <option value="1">Sí</option>
                            </select>
                        </div>

                        <!-- Cheques con Póliza -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Cheques c/ Póliza</label>
                            <select x-model="form.cheques_especiales_con_poliza" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="0">No</option>
                                <option value="1">Sí</option>
                            </select>
                        </div>

                        <!-- Cheque por Hoja -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Cheque por Hoja</label>
                            <select x-model="form.chueque_x_hoja" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="0">No</option>
                                <option value="1">Sí</option>
                            </select>
                        </div>

                        <!-- Cuenta Doble -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Cuenta Doble</label>
                            <select x-model="form.banco_doble" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="0">No</option>
                                <option value="1">Sí</option>
                            </select>
                        </div>

                        <!-- Estado -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Estado</label>
                            <select x-model="form.activo" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="0">Inactivo</option>
                                <option value="1">Activo</option>
                            </select>
                        </div>
                    </div>
                </div>
                <!-- Sección: Leer archivos de bancos -->
                <div>
                    <h4 class="text-md font-semibold text-gray-700 mb-4 pb-2 border-b">Leer archivos de bancos</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <!-- Leer archivos Banamex -->
                         <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Leer archivos Banamex</label>
                            <select x-model="form.la_banamex" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="0">No</option>
                                <option value="1">Si</option>
                            </select>
                                </div>
                        <!-- Leer archivos Bancomer -->
                         <div>
                            <label class="block text-sm font-medium text-gray-700">Leer archivos Bancomer</label>
                            <select x-model="form.la_bancomer" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="0">No</option>
                                <option value="1">Si</option>
                            </select>
                        </div>
                        <!-- Leer archivos SCOTIA -->
                         <div>
                            <label class="block text-sm font-medium text-gray-700">Leer archivos Scotia</label>
                            <select x-model="form.la_scotia" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="0">No</option>
                                <option value="1">Si</option>
                            </select>
                        </div>
                        <!-- Leer archivos HSBC -->
                         <div>
                            <label class="block text-sm font-medium text-gray-700">Leer archivos HSBC</label>
                            <select x-model="form.la_hsbc" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="0">No</option>
                                <option value="1">Si</option>
                            </select>
                        </div>
                        <!-- Leer archivos nombre -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nombre</label>
                            <input type="text" x-model="form.la_nombre" 
                                   class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Falta concetar a otra tabla para validar">
                        </div>
                        <!-- Leer archivos concepto  -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Concepto</label>
                            <input type="text" x-model="form.la_concepto" 
                                   class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <!-- Leer archivos titular -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Titular</label>
                            <input type="text" x-model="form.la_titular" 
                                   class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <!-- Leer archivos cuenta -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Cuenta</label>
                            <select x-model="form.la_cuenta" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Seleccione</option>
                                <option value="Concentradora">Concentradora</option>
                                <option value="Chequera">Chequera</option>
                                <option value="Chequera">Inversión</option>
                            </select>
                        </div>

            </div>
            
            <!-- Botones -->
            <div class="flex justify-end gap-2 p-6 border-t bg-gray-50">
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

<style>
    input[type="date"]::-webkit-calendar-picker-indicator {
        cursor: pointer;
        padding: 4px;
        border-radius: 4px;
    }
    
    input[type="date"]::-webkit-calendar-picker-indicator:hover {
        background-color: rgba(59, 130, 246, 0.1);
    }
</style>

<script>
function validarInput(event) {
    const input = event.target;
    input.value = input.value.replace(/[^0-9]/g, '');
}



function cuentasController() {
    return {
        registros: <?= json_encode($registros); ?>,
        fechasTDC: <?= json_encode($fechasTDC); ?>,
        
        sort: { column: 'id', desc: true },
        
        modal: { open: false, title: '', actionText: '' },
        form: {
            id: '',
            fideicomiso: null,
            nombre_de_banco: '',
            cuenta: null,
            clabe: '',
            categoria: null,
            banxico: null,
            saldo_inicial: 0,
            saldo_actual: 0,
            formato_cheques: null,
            sucursal: null,
            la_nombre: null,
            la_cocepto: null,
            la_titular: null,
            la_cuenta: null,
            cuenta_contable: null,
            banco_doble: 0,
            cuenta_eje: null,
            cheque_inicial: null,
            cheque_final: null,
            ultimo_cheque_asignado: null,
            cheques_especiales_imp: 0,
            cheques_especiales_con_poliza: 0,
            chueque_x_hoja: 0,
            activo: 1,
            tipo_de_cuenta: 0,
            la_banamex: 0,
            la_bancomer: 0,
            la_scotia: 0,
            la_hsbc: 0,
            tipo_moneda: 'MN',
            fecha_de_cambio: '',
            fecha_de_apertura: '',
            tasa_de_cambio: ''
        },

        init() {
            this.applySort();
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
                let va = a[col] ?? '', vb = b[col] ?? '';
                if (typeof va === 'string') va = va.toLowerCase();
                if (typeof vb === 'string') vb = vb.toLowerCase();
                if (va < vb) return this.sort.desc ? 1 : -1;
                if (va > vb) return this.sort.desc ? -1 : 1;
                return 0;
            });
            this.renderRows();
        },

       renderRows() {
            const tbody = this.$refs.tbody;
            if (!tbody) return;
            tbody.innerHTML = '';
            const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;
            const canEdit = <?= $canEdit ? 'true' : 'false' ?>;
            const canDelete = <?= $canDelete ? 'true' : 'false' ?>;
            
            for (const r of this.registros) {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-gray-50';

                if (isAdmin) {
                    const tdId = document.createElement('td');
                    tdId.className = 'px-4 py-3 text-sm text-gray-700 text-center';
                    tdId.textContent = r.id;
                    tr.appendChild(tdId);
                }
                
                const tdCategoria = document.createElement('td');
                tdCategoria.className = 'px-4 py-3 text-sm text-gray-700 text-center';
                tdCategoria.textContent = r.nombre_categoria || 'N/A';
                tr.appendChild(tdCategoria);

                const tdBanco = document.createElement('td');
                tdBanco.className = 'px-4 py-3 text-sm text-gray-700 text-center';
                tdBanco.textContent = r.nombre_de_banco || '';
                tr.appendChild(tdBanco);

                const tdBanxico = document.createElement('td');
                tdBanxico.className = 'px-4 py-3 text-sm text-gray-700 text-center';
                tdBanxico.textContent = r.nombre_banxico || 'N/A';
                tr.appendChild(tdBanxico);

                const tdFideicomiso = document.createElement('td');
                tdFideicomiso.className = 'px-4 py-3 text-sm text-gray-700 text-center';
                tdFideicomiso.textContent = r.nombre_fideicomiso || 'N/A';
                tr.appendChild(tdFideicomiso);

                const tdCuenta = document.createElement('td');
                tdCuenta.className = 'px-4 py-3 text-sm text-gray-700 text-center';
                tdCuenta.textContent = r.cuenta || '';
                tr.appendChild(tdCuenta);

                const tdClabe = document.createElement('td');
                tdClabe.className = 'px-4 py-3 text-sm text-gray-700 text-center';
                tdClabe.textContent = r.clabe || '';
                tr.appendChild(tdClabe);

                const tdSaldo = document.createElement('td');
                tdSaldo.className = 'px-4 py-3 text-sm text-gray-700 text-center';
                const saldoNum = parseFloat(r.saldo_actual || 0);
                const saldoFormateado = Math.abs(saldoNum).toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                tdSaldo.textContent = saldoNum < 0 ? `-$${saldoFormateado}` : `$${saldoFormateado}`;
                tr.appendChild(tdSaldo);

                const tdMoneda = document.createElement('td');
                tdMoneda.className = 'px-4 py-3 text-sm text-center';
                const moneda = r.tipo_moneda || 'MN';
                const colorMoneda = moneda === 'MN' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800';
                tdMoneda.innerHTML = `<span class="inline-flex items-center px-2 py-1 rounded text-xs ${colorMoneda}">${moneda}</span>`;
                tr.appendChild(tdMoneda);

                if (isAdmin) {
                    const tdEstado = document.createElement('td');
                    tdEstado.className = 'px-4 py-3 text-sm';
                    tdEstado.innerHTML = r.activo 
                        ? '<span class="inline-flex items-center px-2 py-1 rounded text-xs bg-green-100 text-green-800">Activo</span>'
                        : '<span class="inline-flex items-center px-2 py-1 rounded text-xs bg-red-100 text-red-800">Inactivo</span>';
                    tr.appendChild(tdEstado);
                }

                const tdAcc = document.createElement('td');
                tdAcc.className = 'px-4 py-3 text-center';
                const divButtons = document.createElement('div');
                divButtons.className = 'inline-flex gap-2';
                
                if (canEdit) {
                    const btnEdit = document.createElement('button');
                    btnEdit.className = 'px-2 py-1 bg-yellow-400 text-white rounded hover:brightness-90';
                    btnEdit.innerHTML = '<i class="fas fa-edit"></i>';
                    btnEdit.setAttribute('title', 'Editar');
                    btnEdit.addEventListener('click', () => this.openEditModal(r.id));
                    divButtons.appendChild(btnEdit);
                }
                
                if (canDelete) {
                    const btnDelete = document.createElement('button');
                    btnDelete.className = 'px-2 py-1 bg-red-500 text-white rounded hover:brightness-90';
                    btnDelete.innerHTML = '<i class="fas fa-trash"></i>';
                    btnDelete.setAttribute('title', 'Eliminar');
                    btnDelete.addEventListener('click', () => this.confirmDelete(r.id));
                    divButtons.appendChild(btnDelete);
                }
                
                tdAcc.appendChild(divButtons);
                tr.appendChild(tdAcc);

                tbody.appendChild(tr);
            }
        },

        openCreateModal() {
            this.modal.open = true;
            this.modal.title = 'Nueva Cuenta Bancaria';
            this.modal.actionText = 'Guardar';
            this.form = {
                id: '',
                fideicomiso: '',
                nombre_de_banco: '',
                cuenta: '',
                clabe: '',
                categoria: '',
                banxico: '',
                saldo_inicial: 0,
                saldo_actual: 0,
                formato_cheques: '',
                sucursal: '',
                la_nombre:'',
                la_concepto:'',
                la_titular:'',
                la_cuenta:'',
                cuenta_contable: '',
                banco_doble: 0,
                cuenta_eje: '',
                cheque_inicial: '',
                cheque_final: '',
                ultimo_cheque_asignado: '',
                cheques_especiales_imp: 0,
                cheques_especiales_con_poliza: 0,
                chueque_x_hoja: 0,
                activo: 1,
                tipo_de_cuenta: 0,
                la_banamex: 0,
                la_bancomer: 0,
                la_scotia: 0,
                la_hsbc: 0,
                tipo_moneda: 'MN',
                fecha_de_cambio: '',
                fecha_de_apertura: '',
                tasa_de_cambio: ''
            };
        },

        openEditModal(id) {
    fetch(`modules/cuentas/actions.php?action=get&id=${id}`)
        .then(r => r.json())
        .then(resp => {
            if (resp.success) {
                const d = resp.data;
                this.form = {
                    id: d.id,
                    fideicomiso: d.fideicomiso,
                    nombre_de_banco: d.nombre_de_banco || '',
                    cuenta: d.cuenta,
                    clabe: d.clabe || '',
                    categoria: d.categoria,
                    banxico: d.banxico,
                    saldo_inicial: d.saldo_inicial || 0,
                    saldo_actual: d.saldo_actual || 0,
                    formato_cheques: d.formato_cheques,
                    sucursal: d.sucursal,
                    la_nombre: d.la_nombre || '',
                    la_concepto: d.la_concepto || '', // CORRECCIÓN: era la_cocepto
                    la_titular: d.la_titular || '',
                    la_cuenta: d.la_cuenta || '',
                    cuenta_contable: d.cuenta_contable,
                    banco_doble: d.banco_doble == true ? 1 : 0,
                    cuenta_eje: d.cuenta_eje,
                    cheque_inicial: d.cheque_inicial,
                    cheque_final: d.cheque_final,
                    ultimo_cheque_asignado: d.ultimo_cheque_asignado,
                    cheques_especiales_imp: d.cheques_especiales_imp == true ? 1 : 0,
                    cheques_especiales_con_poliza: d.cheques_especiales_con_poliza == true ? 1 : 0,
                    chueque_x_hoja: d.chueque_x_hoja == true ? 1 : 0,
                    activo: d.activo == true ? 1 : 0,
                    tipo_de_cuenta: d.tipo_de_cuenta == true ? 1 : 0,
                    la_banamex: d.la_banamex == true ? 1 : 0,
                    la_bancomer: d.la_bancomer == true ? 1 : 0,
                    la_scotia: d.la_scotia == true ? 1 : 0,
                    la_hsbc: d.la_hsbc == true ? 1 : 0,
                    tipo_moneda: d.tipo_moneda || 'MN',
                    fecha_de_cambio: d.fecha_de_cambio || '',
                    fecha_de_apertura: d.fecha_de_apertura || '',
                    tasa_de_cambio: d.tasa_de_cambio || ''
                };
                this.modal.open = true;
                this.modal.title = 'Editar Cuenta Bancaria';
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
    
    // Lista de campos booleanos - CORRECCIÓN: faltaba la_banamex
    const booleanFields = [
        'cheques_especiales_imp', 
        'cheques_especiales_con_poliza', 
        'chueque_x_hoja', 
        'activo', 
        'banco_doble', 
        'tipo_de_cuenta',
        'la_banamex',  // ← CORREGIDO
        'la_bancomer', 
        'la_scotia', 
        'la_hsbc'
    ];
    
    // Campos numéricos que pueden ser null
    const numericNullableFields = [
        'cuenta_eje', 
        'cheque_inicial', 
        'cheque_final', 
        'ultimo_cheque_asignado'
    ];
    
    // Procesar todos los campos
    for (const [key, value] of Object.entries(this.form)) {
        if (booleanFields.includes(key)) {
            // Campos booleanos → 0 o 1
            body.append(key, value == 1 ? 1 : 0);
        } else if (numericNullableFields.includes(key)) {
            // Campos numéricos que pueden ser null
            body.append(key, value === '' || value === null ? '' : value);
        } else {
            // Todos los demás campos
            body.append(key, value ?? '');
        }
    }

    fetch('modules/cuentas/actions.php', {
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
            console.error('Error parsing JSON:', err);
            location.reload();
        }
    }).catch(err => {
        console.error('Error en la petición:', err);
        alert('Error en la petición.');
    });
},

        confirmDelete(id) {
            if (!confirm('¿Está seguro de eliminar esta cuenta?')) return;
            
            const body = new URLSearchParams();
            body.append('action', 'delete');
            body.append('id', id);
            
            fetch('modules/cuentas/actions.php', {
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

        handleMonedaChange() {
            if (this.form.tipo_moneda === 'MN') {
                this.form.fecha_de_cambio = '';
                this.form.tasa_de_cambio = '';
            }
        },

        cargarTasaCambio() {
            if (!this.form.fecha_de_cambio || this.form.tipo_moneda === 'MN') {
                this.form.tasa_de_cambio = '';
                return;
            }

            fetch(`modules/cuentas/get_tasa.php?fecha=${this.form.fecha_de_cambio}&tipo=${this.form.tipo_moneda}`)
                .then(r => r.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        throw new Error('Respuesta no válida del servidor');
                    }
                }))
                .then(resp => {
                    if (resp.success && resp.tasa) {
                        this.form.tasa_de_cambio = resp.tasa;
                    } else {
                        this.form.tasa_de_cambio = '';
                        alert(resp.message || 'No se encontró tasa de cambio para esta fecha');
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    alert('Error al obtener la tasa de cambio: ' + err.message);
                });
        }
    }
}
</script>