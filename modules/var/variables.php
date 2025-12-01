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

$permissionsObj = new Permissions();

//$sidebar = new Sidebar($userPermissions, $userId, 'catalogos.php', $isAdmin);
//$sidebar = new Sidebar($userPermissions, $userId, 'variables.php', $isAdmin);
$currentFile = basename(__FILE__);
$sidebar = new Sidebar($userPermissions, $userId, $currentFile, $isAdmin);
$menuStats = $sidebar->getMenuStats();
$db = Database::getInstance()->getConnection();
// Verificar permisos
//$canRead = $session->hasPermission('configuracion', 'lire', 'leer') || $isAdmin;
// $canUpdate = $session->hasPermission('configuracion', 'modifier', 'actualizar') || $isAdmin;
// $canDelete = $session->hasPermission('configuracion', 'supprimer', 'eliminar') || $isAdmin;


$canRead = $permissionsObj->hasPermission($userId, 'configuracion', 'lire', 'tiie') || $isAdmin;
$canUpdate = $permissionsObj->hasPermission($userId, 'configuracion', 'modifier', 'tiie') || $isAdmin;
$canDelete = $permissionsObj->hasPermission($userId, 'configuracion', 'supprimer', 'tiie') || $isAdmin;



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
                    $visible = isset($_POST['visible']) && $_POST['visible'] === '1';
                    $nota = trim($_POST['nota'] ?? '');
                    
                    // Insertar con valores por defecto para activo, fechaedit y useredit
                    $sql = "INSERT INTO t_const (nom, val, type, visible, activo, nota, fechaedit, useredit) 
                            VALUES (:nom, :val, :type, :visible, :activo, :nota, CURRENT_TIMESTAMP, :useredit)";
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute([
                        'nom' => $nom,
                        'val' => $val,
                        'type' => $type,
                        'visible' => $visible,  // Booleano
                        'activo' => true,       // Booleano
                        'nota' => $nota,
                        'useredit' => $userId
                    ]);
                    
                    $message = 'Variable creada correctamente';
                    $messageType = 'success';
                    break;
                
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
    $sql = "SELECT * FROM t_const ORDER BY nom, id";
} else {
    $sql = "SELECT * FROM t_const WHERE visible = true ORDER BY nom, id";
}

$stmt = $db->query($sql);
$variables = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Variables del Sistema';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../../img/afiducialogo.png">
    <title><?php echo $pageTitle; ?></title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <style>
        .loader {
            border: 4px solid #f3f3f3;
            border-radius: 50%;
            border-top: 4px solid #3498db;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Scrollbar personalizado */
        .scrollbar-thin::-webkit-scrollbar {
            width: 6px;
        }
        
        .scrollbar-thin::-webkit-scrollbar-track {
            background: #374151;
        }
        
        .scrollbar-thin::-webkit-scrollbar-thumb {
            background: #6b7280;
            border-radius: 3px;
        }
        
        .scrollbar-thin::-webkit-scrollbar-thumb:hover {
            background: #9ca3af;
        }
        
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-100" x-data="{ 
    showModal: false, 
    showEditModal: false, 
    showDeleteModal: false,
    editData: {},
    deleteId: null,
    deleteName: ''
}">

<!-- Layout principal con sidebar -->
<div class="flex h-screen">
    <!-- Sidebar lateral -->
    <?php echo $sidebar->render($userData); ?>
    
    <!-- Contenido principal -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <!-- Header superior -->
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="flex items-center justify-between px-6 py-4">
                <div class="flex items-center gap-4">
                    <a href="../../dashboard.php" class="text-gray-600 hover:text-gray-800 transition">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                            <i class="fas fa-cog text-blue-600"></i>
                            <?php echo $pageTitle; ?>
                        </h1>
                        <p class="text-sm text-gray-600">Administra las variables de configuración del sistema</p>
                    </div>
                </div>
                
                <!-- Información de usuario en header -->
                <nav class="flex items-center text-sm text-gray-500">
                    <a href="../../dashboard.php" class="hover:text-gray-700">Dashboard</a>
                    <span class="mx-2">/</span>
                    <a href="/catalogos.php" class="hover:text-gray-700">Catálogos</a>
                    <span class="mx-2">/</span>
                    <span class="text-gray-700 capitalize">Variables del Sistema</span>
                    <span class="mx-2">/</span>
                    <span class="text-gray-700 capitalize">
                        <i class="fas fa-user mr-1"></i>    
                        <?= htmlspecialchars($userData['name'] ?? 'Usuario') ?>
                    </span>
                </nav>
            </div>
        </header>

        <!-- Contenido de la aplicación -->
        <main class="flex-1 overflow-y-auto p-6">
            
            <!-- Alertas -->
            <?php if ($message): ?>
            <div class="mb-6" x-data="{ showAlert: true }" x-show="showAlert" x-transition>
                <div class="rounded-md p-4 <?php 
                    echo $messageType === 'success' ? 'bg-green-50 border border-green-200' : 
                    ($messageType === 'error' ? 'bg-red-50 border border-red-200' : 'bg-blue-50 border border-blue-200'); 
                ?>">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="<?php 
                                echo $messageType === 'success' ? 'fas fa-check-circle text-green-400' : 
                                ($messageType === 'error' ? 'fas fa-exclamation-circle text-red-400' : 'fas fa-info-circle text-blue-400'); 
                            ?>"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium">
                                <?php echo $messageType === 'success' ? '¡Éxito!' : 
                                    ($messageType === 'error' ? 'Error' : 'Información'); ?>
                            </h3>
                            <div class="text-sm mt-1"><?php echo htmlspecialchars($message); ?></div>
                        </div>
                        <div class="ml-auto pl-3">
                            <div class="-mx-1.5 -my-1.5">
                                <button @click="showAlert = false" class="inline-flex rounded-md p-1.5 hover:bg-gray-100">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Controles principales -->
            <div class="mb-6 flex justify-between items-center">
                <?php if ($canUpdate): ?>
                <button 
                    @click="showModal = true"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors inline-flex items-center gap-2">
                    <i class="fas fa-plus"></i>
                    Nueva Variable
                </button>
                <?php endif; ?>
                
                <div class="text-sm text-gray-600">
                    <i class="fas fa-info-circle mr-1"></i>
                    Total de variables: <strong><?php echo count($variables); ?></strong>
                </div>
            </div>

            <!-- Tabla de variables -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
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
                                <td colspan="<?php echo ($canUpdate || $canDelete) ? '6' : '5'; ?>" class="px-6 py-12 text-center text-gray-500">
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
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <div class="max-w-md">
                                            <div class="break-words whitespace-pre-wrap">
                                                <?php echo htmlspecialchars($var['nota'] ?? ''); ?>
                                            </div>
                                        </div>
                                    </td>                                      
                                    <?php if ($canUpdate || $canDelete): ?>
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
        </main>
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
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Ej: MAX_UPLOAD_SIZE">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Valor <span class="text-red-500">*</span>
                    </label>
                    <textarea name="val" required rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                              placeholder="Valor de la variable"></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Tipo <span class="text-red-500">*</span>
                    </label>
                    <select name="type" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
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
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
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
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Valor <span class="text-red-500">*</span>
                    </label>
                    <textarea name="val" x-model="editData.val" required rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Tipo <span class="text-red-500">*</span>
                    </label>
                    <select name="type" x-model="editData.type" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
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
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
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

<script>
// Script para el sidebar (compatible con el código existente)
function sidebar(initialState, userId) {
    return {
        sidebarOpen: initialState,
        userId: userId,
        
        init() {
            // Cargar estado inicial del sidebar
            this.sidebarOpen = this.getStoredSidebarState();
        },
        
        toggleSidebar() {
            this.sidebarOpen = !this.sidebarOpen;
            this.saveSidebarState();
        },
        
        saveSidebarState() {
            // Guardar estado usando fetch
            const formData = new FormData();
            formData.append('accion', 'guardar_estado_sidebar');
            formData.append('estado', this.sidebarOpen ? 1 : 0);
            
            fetch('ajax_handler.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            }).catch(error => {
                console.error('Error guardando estado del sidebar:', error);
            });
        },
        
        getStoredSidebarState() {
            return this.sidebarOpen;
        }
    }
}
</script>

</body>
</html>