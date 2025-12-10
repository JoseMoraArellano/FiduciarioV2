<?php
require_once 'includes/Database.php';
require_once 'includes/Session.php';
require_once 'includes/Permissions.php';

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

// Solo admin puede ver historial
if (!$isAdmin) {
    echo '<div class="p-6"><div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded">No tienes permisos para ver el historial</div></div>';
    exit;
}

// Definición de campos para labels
$camposConfig = [
    'iva' => 'IVA (%)',
    'ufactura' => 'Última Factura',
    'fechahh' => 'Fecha HH',
    'direcbanam' => 'Dirección Banamex',
    'fechabanco' => 'Fecha Banco',
    'fechabbv' => 'Fecha BBVA',
    'direcbbv' => 'Dirección BBVA',
    'fechascot' => 'Fecha Scotiabank',
    'fechahsbc' => 'Fecha HSBC',
    'direchsbc' => 'Dirección HSBC',
    'direcpdf' => 'Directorio PDF',
];

// Obtener historial
$historial = [];
$usuariosCache = [];

try {
    $stmt = $db->prepare("SELECT historico FROM t_parametros LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row && !empty($row['historico'])) {
        $data = json_decode($row['historico'], true);
        if (isset($data['cambios']) && is_array($data['cambios'])) {
            $historial = $data['cambios'];
            // Ordenar por fecha descendente (más reciente primero)
            usort($historial, function($a, $b) {
                return strtotime($b['fecha']) - strtotime($a['fecha']);
            });
        }
    }
    
    // Obtener nombres de usuarios
    $userIds = array_unique(array_column($historial, 'usuario_id'));
    if (!empty($userIds)) {
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $stmtUsers = $db->prepare("SELECT id, name, email FROM users WHERE id IN ($placeholders)");
        $stmtUsers->execute($userIds);
        while ($user = $stmtUsers->fetch(PDO::FETCH_ASSOC)) {
            $usuariosCache[$user['id']] = trim($user['name']);
        }
    }
    
} catch (PDOException $e) {
    echo '<div class="p-6"><div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded">Error al obtener historial: ' . htmlspecialchars($e->getMessage()) . '</div></div>';
}
?>

<link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.4.0/dist/tailwind.min.css" rel="stylesheet">
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

<div x-data="historialController()" class="p-6 space-y-6">

    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800">
                <i class="fas fa-history mr-2"></i> Historial de Cambios en Parámetros
            </h1>
            <p class="text-sm text-gray-500 mt-1">Registro de todas las modificaciones realizadas</p>
        </div>
        
<a href="catalogos.php?mod=parametros" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
            <i class="fas fa-arrow-left"></i> Volver a Parámetros
        </a>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex flex-wrap items-end gap-4">
            <!-- Filtro por campo -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Campo</label>
                <select x-model="filtros.campo" @change="aplicarFiltros()" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="">-- Todos --</option>
                    <?php foreach ($camposConfig as $campo => $label): ?>
                    <option value="<?= $campo ?>"><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Filtro fecha desde -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Desde</label>
                <input type="date" x-model="filtros.fechaDesde" @change="aplicarFiltros()" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            
            <!-- Filtro fecha hasta -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Hasta</label>
                <input type="date" x-model="filtros.fechaHasta" @change="aplicarFiltros()" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            
            <!-- Botón limpiar -->
            <div>
                <button @click="limpiarFiltros()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                    <i class="fas fa-eraser mr-1"></i> Limpiar Filtros
                </button>
            </div>
            
            <!-- Contador -->
            <div class="ml-auto">
                <span class="text-sm text-gray-500">
                    Mostrando <strong x-text="registrosFiltrados.length"></strong> de <strong><?= count($historial) ?></strong> cambios
                </span>
            </div>
        </div>
    </div>

    <!-- Tabla de historial -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usuario</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Campo</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor Anterior</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor Nuevo</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <template x-for="(cambio, index) in registrosFiltrados" :key="index">
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-700">
                                <div x-text="formatearFecha(cambio.fecha)"></div>
                                <div class="text-xs text-gray-400" x-text="formatearHora(cambio.fecha)"></div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">
                                <span x-text="obtenerNombreUsuario(cambio.usuario_id)"></span>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex items-center px-2 py-1 rounded text-xs bg-blue-100 text-blue-800" x-text="obtenerLabelCampo(cambio.campo)"></span>
                            </td>
                            <td class="px-4 py-3 text-sm text-red-600">
                                <span x-text="cambio.valor_anterior || '(vacío)'"></span>
                            </td>
                            <td class="px-4 py-3 text-sm text-green-600 font-medium">
                                <span x-text="cambio.valor_nuevo || '(vacío)'"></span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        
        <!-- Sin resultados -->
        <div x-show="registrosFiltrados.length === 0" class="p-8 text-center text-gray-500">
            <i class="fas fa-search text-4xl mb-2"></i>
            <p>No se encontraron cambios con los filtros seleccionados</p>
        </div>
    </div>

</div>

<script>
function historialController() {
    return {
        // Datos del historial desde PHP
        historial: <?= json_encode($historial, JSON_UNESCAPED_UNICODE) ?>,
        usuarios: <?= json_encode($usuariosCache, JSON_UNESCAPED_UNICODE) ?>,
        camposLabels: <?= json_encode($camposConfig, JSON_UNESCAPED_UNICODE) ?>,
        
        filtros: {
            campo: '',
            fechaDesde: '',
            fechaHasta: ''
        },
        
        registrosFiltrados: [],
        
        init() {
            this.registrosFiltrados = [...this.historial];
        },
        
        aplicarFiltros() {
            this.registrosFiltrados = this.historial.filter(cambio => {
                // Filtro por campo
                if (this.filtros.campo && cambio.campo !== this.filtros.campo) {
                    return false;
                }
                
                // Filtro fecha desde
                if (this.filtros.fechaDesde) {
                    const fechaCambio = new Date(cambio.fecha).setHours(0,0,0,0);
                    const fechaDesde = new Date(this.filtros.fechaDesde).setHours(0,0,0,0);
                    if (fechaCambio < fechaDesde) {
                        return false;
                    }
                }
                
                // Filtro fecha hasta
                if (this.filtros.fechaHasta) {
                    const fechaCambio = new Date(cambio.fecha).setHours(0,0,0,0);
                    const fechaHasta = new Date(this.filtros.fechaHasta).setHours(23,59,59,999);
                    if (fechaCambio > fechaHasta) {
                        return false;
                    }
                }
                
                return true;
            });
        },
        
        limpiarFiltros() {
            this.filtros = { campo: '', fechaDesde: '', fechaHasta: '' };
            this.registrosFiltrados = [...this.historial];
        },
        
        formatearFecha(fecha) {
            if (!fecha) return '';
            const d = new Date(fecha);
            return d.toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' });
        },
        
        formatearHora(fecha) {
            if (!fecha) return '';
            const d = new Date(fecha);
            return d.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
        },
        
        obtenerNombreUsuario(id) {
            return this.usuarios[id] || 'Usuario #' + id;
        },
        
        obtenerLabelCampo(campo) {
            return this.camposLabels[campo] || campo;
        }
    }
}
</script>