<?php

/**
 * modules/usuarios/form.php
 * Formulario para crear/editar usuarios
 */

// Cargar clase de gestión de usuarios
require_once 'includes/UsuariosManager.php';

$usuariosManager = new UsuariosManager();

// Determinar si es edición o creación
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;

// Variables para el formulario
$usuario = null;
$pageTitle = $isEdit ? 'Editar Usuario' : 'Nuevo Usuario';
$submitText = $isEdit ? 'Actualizar Usuario' : 'Crear Usuario';

// Si es edición, cargar datos del usuario
if ($isEdit) {
    $result = $usuariosManager->getUsuario($id);
    
    if (!$result['success']) {
        echo '<div class="p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded">';
        echo '<i class="fas fa-exclamation-circle mr-2"></i>';
        echo htmlspecialchars($result['message']);
        echo '</div>';
        return;
    }
    
    $usuario = $result['data'];
    
    // Verificar si puede editar este usuario
    if (!$usuariosManager->canEdit($userId, $id, $isAdmin)) {
        echo '<div class="p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded">';
        echo '<i class="fas fa-lock mr-2"></i>';
        echo 'No tienes permiso para editar este usuario';
        echo '</div>';
        return;
    }
} else {
    // Verificar permiso para crear
    if (!$isAdmin && !$session->hasPermission('catalogos', 'creer', 'usuarios')) {
        echo '<div class="p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded">';
        echo '<i class="fas fa-lock mr-2"></i>';
        echo 'No tienes permiso para crear usuarios';
        echo '</div>';
        return;
    }
}

// Recuperar datos del formulario si hay error
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

// Función helper para obtener valor del campo
function getFieldValue($fieldName, $usuario, $formData, $default = '') {
    if (!empty($formData[$fieldName])) {
        return $formData[$fieldName];
    }
    if ($usuario && isset($usuario[$fieldName])) {
        return $usuario[$fieldName];
    }
    return $default;
}

// Obtener lista de supervisores para el campo adminfide
$supervisores = $usuariosManager->getSupervisores();

// Obtener historial de login si es edición
$loginHistory = [];
if ($isEdit) {
    $loginHistory = $usuariosManager->getLoginHistory($id);
}
?>

<div x-data="usuarioFormController(<?php echo $isEdit ? 'true' : 'false'; ?>, <?php echo $id; ?>)" x-init="init()">
    
    <!-- Mensajes de éxito/error -->
    <?php if (isset($_SESSION['success'])): ?>
    <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-500 text-green-700 rounded flex items-start">
        <i class="fas fa-check-circle mt-1 mr-3"></i>
        <div>
            <p class="font-medium"><?php echo htmlspecialchars($_SESSION['success']); ?></p>
        </div>
        <button onclick="this.parentElement.remove()" class="ml-auto text-green-700 hover:text-green-900">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <?php unset($_SESSION['success']); endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
    <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded flex items-start">
        <i class="fas fa-exclamation-circle mt-1 mr-3"></i>
        <div>
            <p class="font-medium"><?php echo htmlspecialchars($_SESSION['error']); ?></p>
        </div>
        <button onclick="this.parentElement.remove()" class="ml-auto text-red-700 hover:text-red-900">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <?php unset($_SESSION['error']); endif; ?>

    <!-- Header del formulario -->
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="p-6 border-b flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-<?php echo $isEdit ? 'user-edit' : 'user-plus'; ?> text-blue-600"></i>
                    <?php echo $pageTitle; ?>
                </h2>
                <?php if ($isEdit && $usuario): ?>
                <p class="text-sm text-gray-600 mt-1">
                    ID: <?php echo $usuario['user_id']; ?> | 
                    Creado: <?php echo date('d/m/Y', strtotime($usuario['created_at'])); ?>
                    <?php if (!empty($loginHistory)): ?>
                    | Último login: <?php echo date('d/m/Y H:i', strtotime($loginHistory[0]['date'])); ?>
                    <?php endif; ?>
                </p>
                <?php endif; ?>
            </div>
            
            <div class="flex items-center gap-2">
                <?php if ($isEdit): ?>
                <!-- Acciones adicionales -->
                <a 
                    href="catalogos.php?mod=usuarios&action=permissions&id=<?php echo $id; ?>"
                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center gap-2"
                >
                    <i class="fas fa-key"></i>
                    Permisos
                </a>
                <?php endif; ?>
                
                <a 
                    href="catalogos.php?mod=usuarios&action=list"
                    class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition flex items-center gap-2"
                >
                    <i class="fas fa-arrow-left"></i>
                    Volver
                </a>
            </div>
        </div>
        
        <!-- Tabs -->
        <div class="border-b">
            <nav class="flex -mb-px">
                <button
                    @click="activeTab = 'basic'"
                    :class="activeTab === 'basic' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="px-6 py-3 border-b-2 font-medium text-sm transition"
                >
                    <i class="fas fa-user mr-2"></i>
                    Información Básica
                </button>
                <button
                    @click="activeTab = 'personal'"
                    :class="activeTab === 'personal' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="px-6 py-3 border-b-2 font-medium text-sm transition"
                >
                    <i class="fas fa-id-card mr-2"></i>
                    Datos Personales
                </button>
                <button
                    @click="activeTab = 'config'"
                    :class="activeTab === 'config' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="px-6 py-3 border-b-2 font-medium text-sm transition"
                >
                    <i class="fas fa-cog mr-2"></i>
                    Configuración
                </button>
            </nav>
        </div>
    </div>

    <!-- Formulario -->
    <form method="POST" action="modules/usuarios/actions.php" @submit="validateForm" id="userForm">
        <input type="hidden" name="action" value="save">
        <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?php echo $id; ?>">
        <?php endif; ?>
        
        <!-- TAB: Información Básica -->
        <div x-show="activeTab === 'basic'" class="bg-white rounded-lg shadow p-6">
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- Columna izquierda: Avatar (placeholder) -->
                <div class="lg:col-span-1">
                    <div class="text-center">
                        <div class="w-32 h-32 mx-auto bg-blue-100 rounded-full flex items-center justify-center text-4xl font-bold text-blue-600 mb-4">
                            <?php 
                            $initial = '';
                            if ($usuario && !empty($usuario['firstname'])) {
                                $initial = strtoupper(substr($usuario['firstname'], 0, 1));
                            } elseif ($usuario && !empty($usuario['name'])) {
                                $initial = strtoupper(substr($usuario['name'], 0, 1));
                            } else {
                                $initial = '?';
                            }
                            echo $initial;
                            ?>
                        </div>
                        <p class="text-sm text-gray-600">Avatar del usuario</p>
                        <!-- Aquí podrías agregar upload de imagen en el futuro -->
                    </div>
                    
                    <?php if ($isEdit): ?>
                    <!-- Info adicional -->
                    <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                        <h4 class="font-semibold text-gray-800 mb-3">Información</h4>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">ID:</span>
                                <span class="font-medium"><?php echo $usuario['user_id']; ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Estado:</span>
                                <span class="font-medium">
                                    <?php echo $usuario['statut'] == 1 ? '✓ Activo' : '✗ Inactivo'; ?>
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Permisos:</span>
                                <span class="font-medium"><?php echo count($usuario['permisos'] ?? []); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Grupos:</span>
                                <span class="font-medium"><?php echo count($usuario['grupos'] ?? []); ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Columna derecha: Campos del formulario -->
                <div class="lg:col-span-2 space-y-6">
                    
                    <!-- Nombre de usuario -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Nombre de Usuario <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="name" 
                            required
                            value="<?php echo htmlspecialchars(getFieldValue('name', $usuario, $formData)); ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="usuario123"
                        >
                        <p class="mt-1 text-xs text-gray-500">Nombre único para iniciar sesión</p>
                    </div>
                    
                    <!-- Email -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Email <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="email" 
                            name="email" 
                            required
                            value="<?php echo htmlspecialchars(getFieldValue('email', $usuario, $formData)); ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="usuario@ejemplo.com"
                        >
                    </div>
                    
                    <!-- Contraseña -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Contraseña <?php echo $isEdit ? '' : '<span class="text-red-500">*</span>'; ?>
                        </label>
                        <div class="relative">
                            <input 
                                type="password" 
                                name="password" 
                                id="password"
                                <?php echo $isEdit ? '' : 'required'; ?>
                                class="w-full px-4 py-2 pr-20 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="<?php echo $isEdit ? 'Dejar en blanco para no cambiar' : 'Mínimo 6 caracteres'; ?>"
                            >
                            <button 
                                type="button"
                                @click="togglePasswordVisibility()"
                                class="absolute right-12 top-2.5 text-gray-400 hover:text-gray-600"
                            >
                                <i :class="showPassword ? 'fa-eye-slash' : 'fa-eye'" class="fas"></i>
                            </button>
                            <button 
                                type="button"
                                @click="generatePassword()"
                                class="absolute right-2 top-2 px-2 py-1 bg-blue-100 text-blue-600 rounded hover:bg-blue-200 text-xs"
                                title="Generar contraseña"
                            >
                                <i class="fas fa-key"></i>
                            </button>
                        </div>
                        <?php if ($isEdit): ?>
                        <p class="mt-1 text-xs text-gray-500">Dejar en blanco para mantener la contraseña actual</p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Roles y Estado -->
<!--                <div class="grid grid-cols-1 md:grid-cols-3 gap-4"> -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        
                        <!-- Administrador -->
                        <label class="flex items-center p-4 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50 transition">
                            <input 
                                type="checkbox" 
                                name="admin" 
                                <?php echo getFieldValue('admin', $usuario, $formData) == 1 ? 'checked' : ''; ?>
                                class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500"
                            >
                            <span class="ml-3">
                                <span class="block text-sm font-medium text-gray-900">Administrador</span>
                                <span class="block text-xs text-gray-500">Acceso total</span>
                            </span>
                        </label>
                        
                        <!-- Empleado -->
                        <label class="flex items-center p-4 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50 transition">
                            <input 
                                type="checkbox" 
                                name="empleado" 
                                <?php echo getFieldValue('empleado', $usuario, $formData) == 1 ? 'checked' : ''; ?>
                                class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500"
                            >
                            <span class="ml-3">
                                <span class="block text-sm font-medium text-gray-900">Empleado</span>
                                <span class="block text-xs text-gray-500">Usuario interno</span>
                            </span>                            
                        </label>
                            <!-- 🔹 NUEVO: Admin Fideicomisos -->
                            <label class="flex items-center p-4 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50 transition">
                                <input 
                                    type="checkbox" 
                                    name="adminfide" 
                                    value="1"
                                    <?php echo getFieldValue('adminfide', $usuario, $formData) ? 'checked' : ''; ?>
                                    class="w-4 h-4 text-purple-600 rounded focus:ring-purple-500"
                                >
                                <span class="ml-3">
                                    <span class="block text-sm font-medium text-gray-900">Fideicomisos</span>
                                    <span class="block text-xs text-gray-500">Administrador de Fideicomisos</span>
                                </span>
                            </label>
                        
                        <!-- Activo -->
                        <label class="flex items-center p-4 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50 transition">
                            <input 
                                type="checkbox" 
                                name="statut" 
                                value="1"
                                <?php echo getFieldValue('statut', $usuario, $formData, 1) == 1 ? 'checked' : ''; ?>
                                class="w-4 h-4 text-green-600 rounded focus:ring-green-500"
                            >
                            <span class="ml-3">
                                <span class="block text-sm font-medium text-gray-900">Activo</span>
                                <span class="block text-xs text-gray-500">Puede iniciar sesión</span>
                            </span>
                        </label>
                        
                    </div>
                    
                </div>
            </div>
            
        </div>
        
        <!-- TAB: Datos Personales -->
        <div x-show="activeTab === 'personal'" class="bg-white rounded-lg shadow p-6">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                <!-- Civilidad -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Título
                    </label>
                    <select 
                        name="civility" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                        <option value="">Seleccionar...</option>
                        <?php 
                        $civilityOptions = ['Sr.', 'Sra.', 'Lic.', 'Ing.', 'Dr.', 'Dra.', 'Mtro.', 'Mtra.'];
                        $currentCivility = getFieldValue('civility', $usuario, $formData);
                        foreach ($civilityOptions as $option): 
                        ?>
                        <option value="<?php echo $option; ?>" <?php echo $currentCivility === $option ? 'selected' : ''; ?>>
                            <?php echo $option; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Género -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Género
                    </label>
                    <select 
                        name="gender" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                        <option value="">Seleccionar...</option>
                        <option value="M" <?php echo getFieldValue('gender', $usuario, $formData) === 'M' ? 'selected' : ''; ?>>Masculino</option>
                        <option value="F" <?php echo getFieldValue('gender', $usuario, $formData) === 'F' ? 'selected' : ''; ?>>Femenino</option>
                    </select>
                </div>
                
                <!-- Nombre -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Nombre(s)
                    </label>
                    <input 
                        type="text" 
                        name="firstname" 
                        value="<?php echo htmlspecialchars(getFieldValue('firstname', $usuario, $formData)); ?>"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Juan Carlos"
                    >
                </div>
                
                <!-- Apellido -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Apellido(s)
                    </label>
                    <input 
                        type="text" 
                        name="lastname" 
                        value="<?php echo htmlspecialchars(getFieldValue('lastname', $usuario, $formData)); ?>"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="García López"
                    >
                </div>
                
                <!-- Fecha de Nacimiento -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Fecha de Nacimiento
                    </label>
                    <input 
                        type="date" 
                        name="birth" 
                        value="<?php echo htmlspecialchars(getFieldValue('birth', $usuario, $formData)); ?>"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                </div>
                
                <!-- Puesto -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Puesto
                    </label>
                    <input 
                        type="text" 
                        name="puesto" 
                        value="<?php echo htmlspecialchars(getFieldValue('puesto', $usuario, $formData)); ?>"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Gerente de Ventas"
                    >
                </div>
                
                    <!-- Supervisor -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Supervisor
                        </label>
                        <select 
                            name="supervisor" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                            <option value="">Sin supervisor</option>
                            <?php 
                            $currentSupervisor = getFieldValue('supervisor', $usuario, $formData);
                            foreach ($supervisores as $supervisor): 
                            ?>
                            <option value="<?php echo $supervisor['id']; ?>" <?php echo $currentSupervisor == $supervisor['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($supervisor['fullname'] ?? $supervisor['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Asignar un supervisor directo a este usuario</p>
                    </div>
                
                <!-- Dirección -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Dirección
                    </label>
                    <input 
                        type="text" 
                        name="direccion" 
                        value="<?php echo htmlspecialchars(getFieldValue('direccion', $usuario, $formData)); ?>"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Calle Ejemplo #123"
                    >
                </div>
                
                <!-- Ciudad -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Ciudad
                    </label>
                    <input 
                        type="text" 
                        name="ciudad" 
                        value="<?php echo htmlspecialchars(getFieldValue('ciudad', $usuario, $formData)); ?>"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Ciudad de México"
                    >
                </div>
                
                <!-- Estado -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Estado
                    </label>
                    <input 
                        type="text" 
                        name="edo" 
                        value="<?php echo htmlspecialchars(getFieldValue('edo', $usuario, $formData)); ?>"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="CDMX"
                    >
                </div>
                
                <!-- Código Postal -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Código Postal
                    </label>
                    <input 
                        type="text" 
                        name="zip" 
                        value="<?php echo htmlspecialchars(getFieldValue('zip', $usuario, $formData)); ?>"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="01000"
                        maxlength="5"
                    >
                </div>
                
                <!-- País -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        País
                    </label>
                    <input 
                        type="text" 
                        name="pais" 
                        value="<?php echo htmlspecialchars(getFieldValue('pais', $usuario, $formData, 'México')); ?>"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                </div>
                
                <!-- Teléfono -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Teléfono Principal
                    </label>
                    <input 
                        type="tel" 
                        name="tel" 
                        value="<?php echo htmlspecialchars(getFieldValue('tel', $usuario, $formData)); ?>"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="5512345678"
                    >
                </div>
                
                <!-- Teléfono 2 -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Teléfono Secundario
                    </label>
                    <div class="flex gap-2">
                        <input 
                            type="tel" 
                            name="tel2" 
                            value="<?php echo htmlspecialchars(getFieldValue('tel2', $usuario, $formData)); ?>"
                            class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="5598765432"
                        >
                        <input 
                            type="text" 
                            name="ext" 
                            value="<?php echo htmlspecialchars(getFieldValue('ext', $usuario, $formData)); ?>"
                            class="w-20 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="Ext"
                        >
                    </div>
                </div>
                
            </div>
            
        </div>
        
        <!-- TAB: Configuración -->
        <div x-show="activeTab === 'config'" class="bg-white rounded-lg shadow p-6">
            
            <div class="space-y-6">
                
                <!-- API Key (solo admin) -->
                <?php if ($isAdmin): ?>
                <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <h3 class="font-semibold text-gray-800 mb-3 flex items-center gap-2">
                        <i class="fas fa-key text-blue-600"></i>
                        API Key
                    </h3>
                    
                    <?php if ($isEdit): ?>
                    <div class="flex gap-2">
                        <input 
                            type="text" 
                            name="api_key" 
                            value="<?php echo htmlspecialchars(getFieldValue('api_key', $usuario, $formData)); ?>"
                            class="flex-1 px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 font-mono text-sm"
                            readonly
                        >
                        <button 
                            type="button"
                            @click="generateApiKey()"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
                        >
                            <i class="fas fa-sync-alt"></i> Generar
                        </button>
                    </div>
                    <p class="mt-2 text-xs text-gray-600">La API Key permite al usuario acceder a servicios externos</p>
                    <?php else: ?>
                    <p class="text-sm text-gray-600">La API Key se generará automáticamente después de crear el usuario</p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- Firma de correo -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Firma de Correo
                    </label>
                    <p class="text-xs text-gray-500 mb-2">Puede incluir HTML o texto plano</p>
                    <textarea 
                        name="firma" 
                        rows="6"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent font-mono text-sm"
                        placeholder="<p>Saludos,<br><strong>Nombre</strong><br>Puesto</p>"
                    ><?php echo htmlspecialchars(getFieldValue('firma', $usuario, $formData)); ?></textarea>
                </div>
                
                <!-- Notas Públicas -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Notas Públicas
                    </label>
                    <p class="text-xs text-gray-500 mb-2">Visibles para todos los usuarios con acceso</p>
                    <textarea 
                        name="note_public" 
                        rows="4"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Información adicional visible para todos..."
                    ><?php echo htmlspecialchars(getFieldValue('note_public', $usuario, $formData)); ?></textarea>
                </div>
                
                <!-- Notas Privadas -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Notas Privadas
                    </label>
                    <p class="text-xs text-gray-500 mb-2">Solo visibles para administradores</p>
                    <textarea 
                        name="note_private" 
                        rows="4"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-yellow-50"
                        placeholder="Información confidencial..."
                    ><?php echo htmlspecialchars(getFieldValue('note_private', $usuario, $formData)); ?></textarea>
                </div>
                
                <?php if ($isEdit && $isAdmin): ?>
                <!-- Acciones administrativas -->
                <div class="border-t pt-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Acciones Administrativas</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Resetear contraseña -->
                        <button 
                            type="button"
                            @click="resetPassword()"
                            class="px-4 py-3 bg-orange-100 text-orange-700 rounded-lg hover:bg-orange-200 transition flex items-center justify-center gap-2"
                        >
                            <i class="fas fa-redo"></i>
                            Resetear Contraseña
                        </button>
                        
                        <!-- Ver historial -->
                        <?php if (!empty($loginHistory)): ?>
                        <button 
                            type="button"
                            @click="showLoginHistory = !showLoginHistory"
                            class="px-4 py-3 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition flex items-center justify-center gap-2"
                        >
                            <i class="fas fa-history"></i>
                            Ver Historial de Logins
                        </button>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Historial de logins -->
                    <?php if (!empty($loginHistory)): ?>
                    <div x-show="showLoginHistory" x-collapse class="mt-4 p-4 bg-gray-50 rounded-lg">
                        <h4 class="font-medium text-gray-800 mb-3">Historial de Logins</h4>
                        <div class="space-y-2">
                            <?php foreach ($loginHistory as $login): ?>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-600"><?php echo htmlspecialchars($login['label']); ?>:</span>
                                <span class="font-medium text-gray-800">
                                    <?php echo date('d/m/Y H:i:s', strtotime($login['date'])); ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
            </div>
            
        </div>
        
        <!-- Botones de acción del formulario -->
        <div class="mt-6 flex items-center justify-between bg-white rounded-lg shadow p-6">
            <div>
                <p class="text-sm text-gray-600">
                    <span class="text-red-500">*</span> Campos obligatorios
                </p>
            </div>
            
            <div class="flex items-center gap-3">
                <a 
                    href="catalogos.php?mod=usuarios&action=list"
                    class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition"
                >
                    Cancelar
                </a>
                
                <button 
                    type="submit"
                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center gap-2"
                    :disabled="isSubmitting"
                    :class="isSubmitting ? 'opacity-50 cursor-not-allowed' : ''"
                >
                    <i class="fas" :class="isSubmitting ? 'fa-spinner fa-spin' : 'fa-save'"></i>
                    <span x-text="isSubmitting ? 'Guardando...' : '<?php echo $submitText; ?>'"></span>
                </button>
            </div>
        </div>
        
    </form>
    
</div>

<script>
// Controlador Alpine.js para el formulario
function usuarioFormController(isEdit, userId) {
    return {
        activeTab: 'basic',
        showPassword: false,
        isSubmitting: false,
        showLoginHistory: false,
        
        init() {
            console.log('Formulario de usuario inicializado', { isEdit, userId });
        },
        
        // Toggle visibilidad de contraseña
        togglePasswordVisibility() {
            this.showPassword = !this.showPassword;
            const input = document.getElementById('password');
            input.type = this.showPassword ? 'text' : 'password';
        },
        
        // Generar contraseña aleatoria
        generatePassword() {
            const length = 12;
            const charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
            let password = '';
            
            for (let i = 0; i < length; i++) {
                password += charset.charAt(Math.floor(Math.random() * charset.length));
            }
            
            const input = document.getElementById('password');
            input.value = password;
            input.type = 'text';
            this.showPassword = true;
            
            // Copiar al portapapeles
            navigator.clipboard.writeText(password).then(() => {
                alert('Contraseña generada y copiada al portapapeles:\n\n' + password);
            });
        },
        
        // Validar formulario antes de enviar
        validateForm(e) {
            const name = document.querySelector('input[name="name"]').value.trim();
            const email = document.querySelector('input[name="email"]').value.trim();
            const password = document.querySelector('input[name="password"]').value;
            
            if (!name || name.length < 3) {
                alert('El nombre de usuario debe tener al menos 3 caracteres');
                e.preventDefault();
                this.activeTab = 'basic';
                return false;
            }
            
            if (!email || !this.isValidEmail(email)) {
                alert('Por favor ingresa un email válido');
                e.preventDefault();
                this.activeTab = 'basic';
                return false;
            }
            
            if (!isEdit && (!password || password.length < 6)) {
                alert('La contraseña debe tener al menos 6 caracteres');
                e.preventDefault();
                this.activeTab = 'basic';
                return false;
            }
            
            this.isSubmitting = true;
            return true;
        },
        
        // Validar formato de email
        isValidEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        },
        
        // Generar API Key (solo admin en edición)
        async generateApiKey() {
            if (!confirm('¿Deseas generar una nueva API Key?\n\nLa API Key actual será reemplazada.')) {
                return;
            }
            
            try {
                const response = await fetch('modules/usuarios/actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=generate-api-key&user_id=${userId}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    document.querySelector('input[name="api_key"]').value = result.api_key;
                    alert('API Key generada exitosamente:\n\n' + result.api_key + '\n\nGuarda esta clave en un lugar seguro.');
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error al generar API Key');
            }
        },
        
        // Resetear contraseña (solo admin)
        async resetPassword() {
            if (!confirm('¿Deseas resetear la contraseña de este usuario?\n\nSe generará una contraseña aleatoria.')) {
                return;
            }
            
            try {
                const response = await fetch('modules/usuarios/actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=reset-password&user_id=${userId}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Contraseña reseteada exitosamente.\n\nNueva contraseña: ' + result.new_password + '\n\nGuarda esta contraseña y entrégala al usuario de forma segura.');
                    
                    // Copiar al portapapeles
                    navigator.clipboard.writeText(result.new_password);
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error al resetear contraseña');
            }
        }
    };
}
</script>