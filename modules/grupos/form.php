<?php
/**
 * modules/grupos/form.php
 * Formulario para crear/editar grupos
 */

// Cargar clase de gestión de grupos
require_once 'includes/GruposManager.php';

$gruposManager = new GruposManager();

// Verificar permisos
$canCreate = $isAdmin || $session->hasPermission('catalogos', 'creer', 'grupos');
$canEdit = $isAdmin || $session->hasPermission('catalogos', 'modifier', 'grupos');

// Determinar si es edición o creación
$isEdit = ($_GET['action'] === 'edit');
$grupoId = $_GET['id'] ?? 0;
$grupo = null;
$error = $_GET['error'] ?? '';

if ($isEdit) {
    if (!$canEdit) {
        die('<div class="text-center py-12">
            <i class="fas fa-lock text-6xl text-red-500 mb-4"></i>
            <p class="text-xl text-gray-700">No tienes permisos para editar grupos</p>
        </div>');
    }
    
    // Cargar datos del grupo
    $result = $gruposManager->getGrupo($grupoId);
    if (!$result['success']) {
        die('<div class="text-center py-12">
            <i class="fas fa-exclamation-triangle text-6xl text-red-500 mb-4"></i>
            <p class="text-xl text-gray-700">' . $result['message'] . '</p>
        </div>');
    }
    $grupo = $result['data'];
} else {
    if (!$canCreate) {
        die('<div class="text-center py-12">
            <i class="fas fa-lock text-6xl text-red-500 mb-4"></i>
            <p class="text-xl text-gray-700">No tienes permisos para crear grupos</p>
        </div>');
    }
}
?>

<div x-data="grupoFormController()" x-init="init()">
    
    <!-- Breadcrumb -->
    <div class="mb-6">
        <nav class="flex text-gray-600" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="catalogos.php?mod=grupos&action=list" class="text-gray-700 hover:text-purple-600">
                        <i class="fas fa-users-cog mr-2"></i>
                        Grupos
                    </a>
                </li>
                <li>
                    <div class="flex items-center">
                        <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                        <span class="text-gray-500">
                            <?php echo $isEdit ? 'Editar Grupo' : 'Nuevo Grupo'; ?>
                        </span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>
    
    <!-- Mensajes de error -->
    <?php if ($error): ?>
    <div class="mb-6 bg-red-100 border-l-4 border-red-500 text-red-700 p-4">
        <div class="flex">
            <div class="py-1">
                <i class="fas fa-exclamation-circle mr-2"></i>
            </div>
            <div>
                <p class="font-bold">Error</p>
                <p><?php echo htmlspecialchars($error); ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Formulario -->
    <div class="bg-white rounded-lg shadow">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <i class="fas <?php echo $isEdit ? 'fa-edit' : 'fa-plus-circle'; ?> text-purple-600"></i>
                <?php echo $isEdit ? 'Editar Grupo: ' . htmlspecialchars($grupo['nom']) : 'Nuevo Grupo'; ?>
            </h2>
            <?php if ($isEdit): ?>
            <p class="text-sm text-gray-600 mt-1">ID: <?php echo $grupoId; ?></p>
            <?php endif; ?>
        </div>
        
        <form action="modules/grupos/actions.php" method="POST" class="p-6">
            <input type="hidden" name="action" value="<?php echo $isEdit ? 'update' : 'create'; ?>">
            <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?php echo $grupoId; ?>">
            <?php endif; ?>
            
            <div class="grid grid-cols-1 gap-6">
                
                <!-- Nombre del grupo -->
                <div>
                    <label for="nom" class="block text-sm font-medium text-gray-700 mb-2">
                        Nombre del Grupo <span class="text-red-500">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="nom"
                        name="nom" 
                        value="<?php echo $isEdit ? htmlspecialchars($grupo['nom']) : ''; ?>"
                        required
                        maxlength="100"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                        placeholder="Ej: Administradores, Contabilidad, Ventas..."
                    >
                    <p class="mt-1 text-sm text-gray-500">
                        Nombre descriptivo para identificar el grupo
                    </p>
                </div>
                
                <!-- Descripción -->
                <div>
                    <label for="note" class="block text-sm font-medium text-gray-700 mb-2">
                        Descripción
                    </label>
                    <textarea 
                        id="note"
                        name="note" 
                        rows="4"
                        maxlength="500"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                        placeholder="Describe el propósito o función de este grupo..."
                    ><?php echo $isEdit ? htmlspecialchars($grupo['note'] ?? '') : ''; ?></textarea>
                    <p class="mt-1 text-sm text-gray-500">
                        Opcional: Información adicional sobre el grupo
                    </p>
                </div>
                
                <?php if ($isEdit): ?>
                <!-- Información adicional (solo en edición) -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 p-4 bg-gray-50 rounded-lg">
                    <div>
                        <p class="text-sm font-medium text-gray-700">Fecha de Creación</p>
                        <p class="text-sm text-gray-900">
                            <?php echo date('d/m/Y H:i', strtotime($grupo['datec'])); ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-700">Total Usuarios</p>
                        <p class="text-sm text-gray-900">
                            <i class="fas fa-users text-green-600 mr-1"></i>
                            <?php echo $grupo['total_usuarios']; ?> usuarios
                        </p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-700">Total Permisos</p>
                        <p class="text-sm text-gray-900">
                            <i class="fas fa-key text-blue-600 mr-1"></i>
                            <?php echo $grupo['total_permisos']; ?> permisos
                        </p>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Acciones después de guardar -->
                <?php if (!$isEdit): ?>
                <div class="p-4 bg-blue-50 rounded-lg">
                    <p class="text-sm font-medium text-blue-900 mb-2">
                        <i class="fas fa-info-circle mr-1"></i>
                        Después de crear el grupo:
                    </p>
                    <div class="space-y-2">
                        <label class="flex items-center">
                            <input 
                                type="radio" 
                                name="redirect" 
                                value="list" 
                                checked
                                class="mr-2 text-purple-600"
                            >
                            <span class="text-sm text-gray-700">Volver al listado de grupos</span>
                        </label>
                        <label class="flex items-center">
                            <input 
                                type="radio" 
                                name="redirect" 
                                value="permissions"
                                class="mr-2 text-purple-600"
                            >
                            <span class="text-sm text-gray-700">Ir a asignar permisos al grupo</span>
                        </label>
                    </div>
                </div>
                <?php endif; ?>
                
            </div>
            
            <!-- Botones -->
            <div class="mt-6 pt-6 border-t border-gray-200 flex items-center justify-between">
                <a 
                    href="catalogos.php?mod=grupos&action=list" 
                    class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition flex items-center gap-2"
                >
                    <i class="fas fa-arrow-left"></i>
                    Cancelar
                </a>
                
                <div class="flex items-center gap-2">
                    <?php if ($isEdit): ?>
                    <!-- Enlaces rápidos en modo edición -->
                    <a 
                        href="catalogos.php?mod=grupos&action=permissions&id=<?php echo $grupoId; ?>"
                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center gap-2"
                    >
                        <i class="fas fa-key"></i>
                        <span class="hidden md:inline">Permisos</span>
                    </a>
                    <a 
                        href="catalogos.php?mod=grupos&action=usuarios&id=<?php echo $grupoId; ?>"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center gap-2"
                    >
                        <i class="fas fa-users"></i>
                        <span class="hidden md:inline">Usuarios</span>
                    </a>
                    <?php endif; ?>
                    
                    <button 
                        type="submit"
                        class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition flex items-center gap-2"
                    >
                        <i class="fas fa-save"></i>
                        <?php echo $isEdit ? 'Actualizar' : 'Crear Grupo'; ?>
                    </button>
                </div>
            </div>
            
        </form>
    </div>
    
</div>

<script>
// Controlador Alpine.js para el formulario
function grupoFormController() {
    return {
        init() {
            console.log('Formulario de grupo inicializado');
        }
    };
}
</script>