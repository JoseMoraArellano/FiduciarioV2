<?php
require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/Session.php';
require_once '../../includes/Permissions.php';
require_once '../../includes/Auth.php';
require_once '../../includes/Sidebar.php';

// Iniciar sesión
$session = new Session();

// Verificar autenticación
if (!$session->isLoggedIn()) {
    header('Location: ../../login.php');
    exit;
}

// Obtener datos del usuario
$userData = $session->getUserData();
$userId = $session->getUserId();
$isAdmin = $session->isAdmin();
$userPermissions = $userData['permissions'] ?? [];
$sidebar = new Sidebar($userPermissions, $userId, 'catalogos.php', $isAdmin);
$db = Database::getInstance()->getConnection();

// Verificar permisos
$canRead = $session->hasPermission('configuracion', 'lire', 'leer') || $isAdmin;
$canUpdate = $session->hasPermission('configuracion', 'modifier', 'actualizar') || $isAdmin;
$canDelete = $session->hasPermission('configuracion', 'supprimer', 'eliminar') || $isAdmin;

if (!$canRead) {
    header('Location: ../../login.php?error=permission_denied');
    exit();
}

// Mensajes de respuesta
$message = '';
$messageType = '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'update':
    if (!$canUpdate) {
        throw new Exception('No tienes permiso para actualizar variables');
    }
    
    $id = (int)$_POST['id'];
    $nom = trim($_POST['nom']);
    $val = trim($_POST['val']);
    $type = trim($_POST['type']);
    $activo = isset($_POST['activo']) && $_POST['activo'] === '1';
    $nota = trim($_POST['nota'] ?? '');
    
    $sql = "UPDATE t_const SET 
            nom = :nom,
            val = :val,
            type = :type,
            activo = :activo,
            nota = :nota,
            fechaedit = CURRENT_TIMESTAMP,
            useredit = :useredit
            WHERE id = :id";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'nom' => $nom,
        'val' => $val,
        'type' => $type,
        'activo' => $activo,
        'nota' => $nota,
        'useredit' => $userId,
        'id' => $id
    ]);
    
    $message = 'Variable actualizada correctamente';
    $messageType = 'success';
    break;
                
            case 'delete':
                if (!$canDelete) {
                    throw new Exception('No tienes permiso para eliminar variables');
                }
                
                $id = (int)$_POST['id'];
                
                $sql = "DELETE FROM t_const WHERE id = :id";
                $stmt = $db->prepare($sql);
                $stmt->execute(['id' => $id]);
                
                $message = 'Variable eliminada correctamente';
                $messageType = 'success';
                break;
                
case 'create':
    if (!$canUpdate) {
        throw new Exception('No tienes permiso para crear variables');
    }
    
    $nom = trim($_POST['nom']);
    $val = trim($_POST['val']);
    $type = trim($_POST['type']);
    $visible = isset($_POST['visible']) && $_POST['visible'] === '1'; // Convertir a booleano
    $nota = trim($_POST['nota'] ?? '');
    
    // Insertar con valores por defecto para activo, fechaedit y useredit
    $sql = "INSERT INTO t_const (nom, val, type, visible, activo, nota, fechaedit, useredit) 
            VALUES (:nom, :val, :type, :visible, :activo, :nota, CURRENT_TIMESTAMP, :useredit)";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'nom' => $nom,
        'val' => $val,
        'type' => $type,
        'visible' => $visible ? 'true' : 'false',  // Convertir a string boolean de PostgreSQL
        'activo' => 'true',  // Valor por defecto como string
        'nota' => $nota,
        'useredit' => $userId
    ]);
    
    $message = 'Variable creada correctamente';
    $messageType = 'success';
    break;

        }
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'error';
    }
}


if ($isAdmin) {
    // Administradores ven todas las variables
    $sql = "SELECT * FROM t_const ORDER BY nom, id";
} else {
    // Usuarios normales solo ven las visibles
    $sql = "SELECT * FROM t_const WHERE visible = true ORDER BY nom, id";
}


$stmt = $db->query($sql);
$variables = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Gestión de Variables del Sistema';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-100" x-data="{ 
    showModal: false, 
    showEditModal: false, 
    showDeleteModal: false,
    editData: {},
    deleteId: null,
    deleteName: ''
}">
    <div class="flex min-h-screen">

        <!-- Sidebar (menú lateral) -->
        <?php echo $sidebar->render(); ?>


        <!-- Contenedor principal (Navbar + contenido) -->
        <div class="flex-1 flex flex-col lg:ml-64">

            <!-- NAVBAR SUPERIOR -->
            <header class="bg-white shadow sticky top-0 z-30">
                <div class="flex items-center justify-between px-6 py-3">
                    <!-- Botón para abrir/cerrar sidebar en pantallas pequeñas -->
                    <button 
                        class="lg:hidden text-gray-700 focus:outline-none"
                        @click="sidebarOpen = !sidebarOpen"
                    >
                        <i class="fas fa-bars fa-lg"></i>
                    </button>

                    <!-- Título del módulo -->
                    <h1 class="text-xl font-semibold text-gray-800 flex items-center gap-2">
                        <i class="fas fa-cog text-blue-600"></i>
                        <?php echo $pageTitle; ?>
                    </h1>

                    <!-- Usuario / Logout -->
                    <div class="flex items-center gap-4">
                        <span class="text-gray-700 text-sm font-medium">
                            <?php echo htmlspecialchars($userData['name'] ?? 'Usuario'); ?>                             
                        </span>
                        <a href="../../logout.php" class="text-gray-500 hover:text-red-600 transition-colors">
                            <i class="fas fa-sign-out-alt"></i>
                        </a>
                    </div>
                </div>
            </header>
        <!-- Contenido principal -->
        <main class="flex-1 p-8">
            <div class="mb-6 flex justify-between items-center">
<!--
            <div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">
                        <i class="fas fa-cog text-blue-600"></i>
                        <?php echo $pageTitle; ?>
                    </h1>
                    <p class="text-gray-600">Administra las variables de configuración del sistema</p>
                </div>
-->
                <a href="../../dashboard.php" class="text-blue-600 hover:text-blue-800 transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Volver al Dashboard
                </a>
            </div>

        <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
            <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-2"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>
            
    <?php if ($canUpdate): ?>
    <div class="mb-6">
        <button 
            @click="showModal = true"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors inline-flex items-center gap-2">
            <i class="fas fa-plus"></i>
            Nueva Variable
        </button>

     </div>
     <?php endif; ?>        
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/5">Nombre</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-2/5">Valor</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/6">Activo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/6">Última Edición</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/6">Nota</th>
                            <?php if ($canUpdate || $canDelete): ?>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($variables)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-inbox text-4xl mb-3 block"></i>
                                No hay variables registradas
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($variables as $var): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <span class="font-semibold text-blue-600">
                                        <?php echo htmlspecialchars($var['nom'] ?? ''); ?>
                                    </span>
                                    <?php if (!empty($var['type'])): ?>
                                    <div class="mt-1">
                                        <span class="px-2 py-0.5 bg-purple-100 text-purple-800 rounded text-xs">
                                            <?php echo htmlspecialchars($var['type']); ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <div class="max-w-md">
                                        <div class="break-words whitespace-pre-wrap">
                                            <?php echo htmlspecialchars($var['val'] ?? ''); ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php if ($var['activo']): ?>
                                        <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs">
                                            <i class="fas fa-check-circle"></i> Activo
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs">
                                            <i class="fas fa-times-circle"></i> Inactivo
                                        </span>
                                    <?php endif; ?>
                                </td>                          
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php if ($var['fechaedit']): ?>
                                        <?php echo date('d/m/Y', strtotime($var['fechaedit'])); ?>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-5 text-sm text-gray-900">
                                    <div class="max-w-md">
                                        <div class="break-words whitespace-pre-wrap">
                                            <?php echo htmlspecialchars($var['nota'] ?? ''); ?>
                                        </div>
                                    </div>
                                </td>                                      
                                <?php if ($canUpdate): ?>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end gap-2">
                                            <?php if ($canUpdate): ?>
<button 
    @click="editData = <?php echo htmlspecialchars(json_encode($var)); ?>; showEditModal = true"
    class="text-blue-600 hover:text-blue-800 transition-colors"
    title="Editar variable"
>
    <i class="fas fa-edit"></i>
</button>
                                                <?php endif; ?>
                                        
                                                <?php if ($canDelete): ?>
<button
    @click="deleteId = <?php echo $var['id']; ?>; deleteName = '<?php echo addslashes($var['nom']); ?>'; showDeleteModal = true"
    class="text-red-600 hover:text-red-900 transition-colors"
    title="Eliminar"
>
    <i class="fas fa-trash"></i>
</button>
                                                <?php endif; ?>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>       
        <div class="mt-6 text-sm text-gray-600">
            <i class="fas fa-info-circle mr-1"></i>
            Total de variables: <strong><?php echo count($variables); ?></strong>
        </div>
    </div>
   <!-- Modal Crear Variable -->
    <div x-show="showModal" 
         x-cloak
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
         @click.self="showModal = false">
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-bold text-gray-800">
                        <i class="fas fa-plus-circle text-blue-600"></i>
                        Nueva Variable
                    </h2>
                    <button @click="showModal = false" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="create">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Nombre <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="nom" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Ej: MAX_UPLOAD_SIZE">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Valor <span class="text-red-500">*</span>
                        </label>
                        <textarea name="val" required rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                  placeholder="Valor de la variable"></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Tipo <span class="text-red-500">*</span>
                        </label>
                        <select name="type" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Seleccionar tipo</option>
                            <option value="string">String</option>
                            <option value="number">Number</option>
                            <option value="boolean">Boolean</option>
                            <option value="json">JSON</option>
                            <option value="url">URL</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Nota
                        </label>
                        <textarea name="nota" rows="2"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                  placeholder="Descripción o comentarios sobre la variable"></textarea>
                    </div>
                    
                    <div class="flex items-center">
                        <input type="checkbox" name="visible" id="visible" value="1"
                               class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                        <label for="visible" class="ml-2 text-sm text-gray-700">
                            Variable visible para usuarios no administradores
                        </label>
                    </div>
                    
                    <div class="flex justify-end gap-3 pt-4 border-t">
                        <button type="button" @click="showModal = false"
                                class="px-4 py-2 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                            <i class="fas fa-times mr-2"></i>
                            Cancelar
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-blue-600 text-white hover:bg-blue-700 rounded-lg transition-colors">
                            <i class="fas fa-save mr-2"></i>
                            Crear Variable
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Variable -->
    <div x-show="showEditModal" 
         x-cloak
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
         @click.self="showEditModal = false">
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-bold text-gray-800">
                        <i class="fas fa-edit text-blue-600"></i>
                        Editar Variable
                    </h2>
                    <button @click="showEditModal = false" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" x-model="editData.id">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Nombre <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="nom" x-model="editData.nom" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Valor <span class="text-red-500">*</span>
                        </label>
                        <textarea name="val" x-model="editData.val" required rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Tipo <span class="text-red-500">*</span>
                        </label>
                        <select name="type" x-model="editData.type" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Seleccionar tipo</option>
                            <option value="string">String</option>
                            <option value="number">Number</option>
                            <option value="boolean">Boolean</option>
                            <option value="json">JSON</option>
                            <option value="url">URL</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Nota
                        </label>
                        <textarea name="nota" x-model="editData.nota" rows="2"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                    </div>
                    
                    <div class="flex items-center">
                        <input type="checkbox" name="activo" id="activo_edit" value="1"
                               :checked="editData.activo == 1 || editData.activo == true"
                               class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                        <label for="activo_edit" class="ml-2 text-sm text-gray-700">
                            Variable activa
                        </label>
                    </div>
                    
                    <div class="flex justify-end gap-3 pt-4 border-t">
                        <button type="button" @click="showEditModal = false"
                                class="px-4 py-2 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                            <i class="fas fa-times mr-2"></i>
                            Cancelar
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-blue-600 text-white hover:bg-blue-700 rounded-lg transition-colors">
                            <i class="fas fa-save mr-2"></i>
                            Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Eliminar Variable -->
    <div x-show="showDeleteModal" 
         x-cloak
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
         @click.self="showDeleteModal = false">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
            <div class="p-6">
                <div class="flex items-center mb-4">
                    <div class="flex-shrink-0 w-12 h-12 rounded-full bg-red-100 flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                    </div>
                    <h2 class="ml-4 text-xl font-bold text-gray-800">
                        Confirmar Eliminación
                    </h2>
                </div>
                
                <p class="text-gray-600 mb-4">
                    ¿Estás seguro de que deseas eliminar la variable 
                    <strong class="text-gray-900" x-text="deleteName"></strong>?
                </p>
                
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4">
                    <p class="text-sm text-yellow-700">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        Esta acción no se puede deshacer.
                    </p>
                </div>
                
                <form method="POST" class="flex justify-end gap-3">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" x-model="deleteId">
                    
                    <button type="button" @click="showDeleteModal = false"
                            class="px-4 py-2 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                        <i class="fas fa-times mr-2"></i>
                        Cancelar
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-red-600 text-white hover:bg-red-700 rounded-lg transition-colors">
                        <i class="fas fa-trash mr-2"></i>
                        Eliminar
                    </button>
                </form>
            </div>
        </div>
    </div>

    <style>
        [x-cloak] { display: none !important; }
    </style> 
        <script src="../../public/js/sidebar.js"></script>
    <script src=".../../public/js/dashboard.js"></script>
</body>
</html>