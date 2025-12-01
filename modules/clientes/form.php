<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'includes/ClienteManager.php';
require_once 'modules/clientes/permissions.php';

$clienteManager = new ClienteManager();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;
// Variables para el formulario
$cliente = null;
$pageTitle = $isEdit ? 'Editar Cliente' : 'Nuevo Cliente';
$submitText = $isEdit ? 'Actualizar Cliente' : 'Crear Cliente';

// Verificar permisos
if ($isEdit) {
    $result = $clienteManager->getCliente($id);
    if (!$result['success']) {
        echo '<div class="p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded">';
        echo '<i class="fas fa-exclamation-circle mr-2"></i>';
        echo htmlspecialchars($result['message']);
        echo '</div>';
        return;
    }
    $cliente = $result['data'];

    if (!$clienteManager->canEdit($userId, $id, $isAdmin)) {
        echo '<div class="p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded">';
        echo '<i class="fas fa-lock mr-2"></i>';
        echo 'No tienes permiso para editar este cliente';
        echo '</div>';
        return;
    }
} else {
    // Verificar permiso para crear
    if (!$isAdmin && !$session->hasPermission('catalogos', 'creer' . 'clientes')) {
        echo '<div class="p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded">';
        echo '<i class="fas fa-lock mr-2"></i>';
        echo 'No tienes permiso para crear clientes';
        echo '</div>';
        return;
    }
}

// Obtener listas para selects
$estados = $clienteManager->getEstados();
$paises = $clienteManager->getPaises();
$regimenesFiscales = $clienteManager->getRegimenesFiscales();

// Recuperar datos del formulario si hay error
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

function getFieldValue($fieldName, $cliente, $formData, $default = '')
{
    if (!empty($formData[$fieldName])) {
        return $formData[$fieldName];
    }
    if ($cliente && isset($cliente[$fieldName])) {
        return $cliente[$fieldName];
    }
    return $default;
}
?>

<div x-data="clienteFormController(<?php echo $isEdit ? 'true' : 'false'; ?>, <?php echo $id; ?>)" x-init="init()">

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
    <?php unset($_SESSION['success']);
    endif; ?>

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
    <?php unset($_SESSION['error']);
    endif; ?>

    <!-- Header -->
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="p-6 border-b flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-<?php echo $isEdit ? 'user-edit' : 'user-plus'; ?> text-blue-600"></i>
                    <?php echo $pageTitle; ?>
                </h2>
                <?php if ($isEdit && $cliente): ?>
                    <p class="text-sm text-gray-600 mt-1">
                        ID: <?php echo $cliente['id']; ?> |
                        RFC: <?php echo $cliente['rfc'] ?: 'No registrado'; ?> |
                        Creado: <?php echo date('d/m/Y', strtotime($cliente['fechac'])); ?>
                        <?php if ($cliente['fechaedit']): ?>
                            | Modificado: <?php echo date('d/m/Y', strtotime($cliente['fechaedit'])); ?>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="flex items-center gap-2">
                <?php if ($isEdit): ?>
                    <button
                        @click="showHistory = true"
                        class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition flex items-center gap-2">
                        <i class="fas fa-history"></i>
                        Historial
                    </button>
                <?php endif; ?>

                <a
                    href="catalogos.php?mod=clientes&action=list"
                    class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition flex items-center gap-2">
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
                    class="px-6 py-3 border-b-2 font-medium text-sm transition">
                    <i class="fas fa-user mr-2"></i>
                    Información General
                </button>

                <button
                    @click="activeTab = 'address'"
                    :class="activeTab === 'address' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="px-6 py-3 border-b-2 font-medium text-sm transition">
                    <i class="fas fa-map-marker-alt mr-2"></i>
                    Dirección
                </button>

                <button
                    @click="activeTab = 'fiscal'"
                    :class="activeTab === 'fiscal' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="px-6 py-3 border-b-2 font-medium text-sm transition">
                    <i class="fas fa-file-invoice mr-2"></i>
                    Datos Fiscales y Doumentos
                </button>

                <?php if (isset($_SESSION['success'])): ?>
                    <button
                        @click="activeTab = 'documents'"
                        :class="activeTab === 'documents' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="px-6 py-3 border-b-2 font-medium text-sm transition">
                        <i class="fas fa-folder mr-2"></i>
                        Documentos
                        <?php if ($isEdit && isset($cliente['total_documentos']) && $cliente['total_documentos'] > 0): ?>
                            <span class="ml-2 px-2 py-0.5 bg-blue-100 text-blue-800 text-xs rounded-full">
                                <?php echo $cliente['total_documentos']; ?>
                            </span>
                        <?php endif; ?>
                    </button>
                <?php endif; ?>
            </nav>
        </div>
    </div>

    <!-- Mensaje de verificación QSQ -->
    <div x-show="!qsqVerified && !isEdit" class="mb-6 p-4 bg-yellow-50 border-l-4 border-yellow-500 text-yellow-800 rounded flex items-start">
        <i class="fas fa-exclamation-triangle mt-1 mr-3"></i>
        <div>
            <p class="font-medium">Verificación QSQ Requerida</p>
            <p class="text-sm mt-1">Debe verificar el cliente en las listas antes de poder guardarlo. Complete los campos RFC, CURP o nombre y haga clic en "Verificar QSQ".</p>
        </div>
    </div>

    <!-- Formulario -->
    <form method="POST" action="modules/clientes/action.php" @submit="validateForm" id="clienteForm" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save">
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?php echo $id; ?>">
        <?php endif; ?>
        <input type="hidden" name="qsq_verified" x-model="qsqVerified">

        <!-- TAB: Información General -->
        <div x-show="activeTab === 'basic'" class="bg-white rounded-lg shadow p-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- Columna izquierda: Info del cliente -->
                <div class="lg:col-span-1">
                    <div class="text-center">
                        <div class="w-32 h-32 mx-auto bg-blue-100 rounded-full flex items-center justify-center text-4xl font-bold text-blue-600 mb-4">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <p class="text-sm text-gray-600">
                            <?php echo $isEdit ? 'Cliente #' . $id : 'Nuevo Cliente'; ?>
                        </p>
                    </div>

                    <?php if ($isEdit): ?>
                        <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                            <h4 class="font-semibold text-gray-800 mb-3">Información</h4>
                            <div class="space-y-2 text-sm">

                                <div class="flex justify-between">
                                    <span class="text-gray-600">Estado:</span>
                                    <span class="font-medium">
                                        <?php echo $cliente['activo'] ? 'Activo ✅' : 'Inactivo 🚫'; ?>
                                    </span>
                                </div>

                                <div class="flex justify-between">
                                    <span class="text-gray-600">Alto Riesgo:</span>
                                    <span class="font-medium">
                                        <?php echo $cliente['altoriesg'] ? 'Alto Riesgo ⚠️' : 'No Alto Riesgo ✅'; ?>
                                    </span>
                                </div>

                                <div class="flex justify-between">
                                    <span class="text-gray-600">Fideicomitente:</span>
                                    <span class="font-medium">
                                        <?php echo $cliente['fideicomitente'] ? 'Sí' : 'No'; ?>
                                    </span>
                                </div>

                                <div class="flex justify-between">
                                    <span class="text-gray-600">Fideicomisario:</span>
                                    <span class="font-medium">
                                        <?php echo $cliente['fideicomisario'] ? 'Sí' : 'No'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Botón Verificar QSQ -->
                    <?php if ($clientePermissions->canVerifyQSQ()): ?>
                        <div class="mt-6">
                            <button
                                type="button"
                                @click="verificarQSQ()"
                                :disabled="!canVerifyQSQ || isVerifying"
                                :class="qsqVerified ? 'bg-green-600 hover:bg-green-700' : 'bg-orange-600 hover:bg-orange-700 disabled:bg-gray-400'"
                                class="w-full px-4 py-3 text-white rounded-lg transition flex items-center justify-center gap-2">
                                <i class="fas" :class="isVerifying ? 'fa-spinner fa-spin' : (qsqVerified ? 'fa-check-double' : 'fa-shield-alt')"></i>
                                <span x-text="qsqVerified ? 'QSQ Verificado ✓' : (isVerifying ? 'Verificando...' : 'Verificar QSQ')"></span>
                            </button>
                            <p class="text-xs text-gray-600 mt-2 text-center">
                                Validación en listas negras y PLD
                            </p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Columna derecha: Campos del formulario -->
                <div class="lg:col-span-2 space-y-6">

                    <!-- Tipo de Persona -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Tipo de Persona <span class="text-red-500">*</span>
                        </label>
                        <div class="grid grid-cols-2 gap-4">
                            <label class="relative flex items-center p-4 border-2 rounded-lg cursor-pointer transition"
                                :class="tipoPersona === 'FISICA' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'">
                                <input
                                    type="radio"
                                    name="tipo_persona"
                                    value="FISICA"
                                    x-model="tipoPersona"
                                    @change="onTipoPersonaChange"
                                    <?php echo getFieldValue('tipo_persona', $cliente, [], 'FISICA') == 'FISICA' ? 'checked' : ''; ?>
                                    class="w-4 h-4 text-blue-600">
                                <div class="ml-3">
                                    <p class="font-medium">Persona Física</p>
                                    <p class="text-xs text-gray-600">RFC: 13 caracteres</p>
                                </div>
                            </label>

                            <label class="relative flex items-center p-4 border-2 rounded-lg cursor-pointer transition"
                                :class="tipoPersona === 'MORAL' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'">
                                <input
                                    type="radio"
                                    name="tipo_persona"
                                    value="MORAL"
                                    x-model="tipoPersona"
                                    @change="onTipoPersonaChange"
                                    <?php echo getFieldValue('tipo_persona', $cliente, [], '') == 'MORAL' ? 'checked' : ''; ?>
                                    class="w-4 h-4 text-blue-600">
                                <div class="ml-3">
                                    <p class="font-medium">Persona Moral</p>
                                    <p class="text-xs text-gray-600">RFC: 12 caracteres</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Nombres -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <span x-text="tipoPersona === 'MORAL' ? 'Razón Social' : 'Nombre(s)'"></span>
                                <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                name="nombres"
                                value="<?php echo htmlspecialchars(getFieldValue('nombres', $cliente, [])); ?>"
                                required
                                @input="checkCanVerify"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                :placeholder="tipoPersona === 'MORAL' ? 'Razón Social' : 'Nombre(s)'">
                        </div>

                        <div x-show="tipoPersona === 'FISICA'">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Apellido Paterno
                            </label>
                            <input
                                type="text"
                                name="paterno"
                                value="<?php echo htmlspecialchars(getFieldValue('paterno', $cliente, [])); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div x-show="tipoPersona === 'FISICA'">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Apellido Materno
                            </label>
                            <input
                                type="text"
                                name="materno"
                                value="<?php echo htmlspecialchars(getFieldValue('materno', $cliente, [])); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                    </div>

                    <!-- RFC y CURP -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                RFC
                            </label>
                            <input
                                type="text"
                                name="rfc"
                                id="rfc"
                                value="<?php echo htmlspecialchars(getFieldValue('rfc', $cliente, [])); ?>"
                                @input="validateRFC(); checkCanVerify()"
                                :maxlength="tipoPersona === 'MORAL' ? 12 : 13"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent uppercase"
                                :placeholder="tipoPersona === 'MORAL' ? 'AAA######XXX' : 'AAAA######XXX'">
                            <p x-show="rfcError" class="text-red-500 text-xs mt-1" x-text="rfcError"></p>
                        </div>

                        <div x-show="tipoPersona === 'FISICA'">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                CURP
                            </label>
                            <input
                                type="text"
                                name="curp"
                                id="curp"
                                value="<?php echo htmlspecialchars(getFieldValue('curp', $cliente, [])); ?>"
                                @input="validateCURP(); checkCanVerify()"
                                maxlength="18"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent uppercase"
                                placeholder="AAAA######HXXXXXX##">
                            <p x-show="curpError" class="text-red-500 text-xs mt-1" x-text="curpError"></p>
                        </div>
                    </div>

                    <!-- Contacto -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Email
                            </label>
                            <input
                                type="email"
                                name="emal"
                                value="<?php echo htmlspecialchars(getFieldValue('emal', $cliente, [])); ?>"
                                @input="validateEmail"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="correo@ejemplo.com">
                            <p x-show="emailError" class="text-red-500 text-xs mt-1" x-text="emailError"></p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Teléfono Principal
                            </label>
                            <div class="flex gap-2">
                                <input
                                    type="tel"
                                    name="tel"
                                    value="<?php echo htmlspecialchars(getFieldValue('tel', $cliente, [])); ?>"
                                    class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="10 dígitos">
                                <input
                                    type="text"
                                    name="ext"
                                    value="<?php echo htmlspecialchars(getFieldValue('ext', $cliente, [])); ?>"
                                    class="w-20 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="Ext">
                            </div>
                        </div>
                    </div>

                    <!-- Teléfono secundario -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Teléfono Secundario
                        </label>
                        <input
                            type="tel"
                            name="tel2"
                            value="<?php echo htmlspecialchars(getFieldValue('tel2', $cliente, [])); ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="10 dígitos (opcional)">
                    </div>

                    <!-- Comentarios -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Comentarios / Observaciones
                        </label>
                        <textarea
                            name="coment"
                            rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="Notas adicionales sobre el cliente..."><?php echo htmlspecialchars(getFieldValue('coment', $cliente, [])); ?></textarea>
                    </div>

                </div>
            </div>
        </div>

        <!-- TAB: Dirección -->
        <div x-show="activeTab === 'address'" class="bg-white rounded-lg shadow p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                <!-- Dirección -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Calle
                    </label>
                    <input
                        type="text"
                        name="calle"
                        value="<?php echo htmlspecialchars(getFieldValue('calle', $cliente, [])); ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            No. Exterior
                        </label>
                        <input
                            type="text"
                            name="nroext"
                            value="<?php echo htmlspecialchars(getFieldValue('nroext', $cliente, [])); ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            No. Interior
                        </label>
                        <input
                            type="text"
                            name="nroint"
                            value="<?php echo htmlspecialchars(getFieldValue('nroint', $cliente, [])); ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Colonia
                    </label>
                    <input
                        type="text"
                        name="colonia"
                        value="<?php echo htmlspecialchars(getFieldValue('colonia', $cliente, [])); ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Código Postal
                    </label>
                    <input
                        type="text"
                        name="cp"
                        id="cp"
                        value="<?php echo htmlspecialchars(getFieldValue('cp', $cliente, [])); ?>"
                        @input="validateCP"
                        maxlength="5"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="00000">
                    <p x-show="cpError" class="text-red-500 text-xs mt-1" x-text="cpError"></p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Delegación / Municipio
                    </label>
                    <input
                        type="text"
                        name="delegacion"
                        value="<?php echo htmlspecialchars(getFieldValue('delegacion', $cliente, [])); ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Estado
                    </label>
                    <select
                        name="edo"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">Seleccionar...</option>
                        <?php foreach ($estados as $estado): ?>
                            <option value="<?php echo $estado['id']; ?>"
                                <?php echo getFieldValue('edo', $cliente, []) == $estado['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($estado['nom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        País
                    </label>
                    <select
                        name="pais"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <?php foreach ($paises as $pais): ?>
                            <option value="<?php echo $pais['id']; ?>"
                                <?php echo getFieldValue('pais', $cliente, [], 1) == $pais['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($pais['nom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>
        </div>

        <!-- TAB: Datos Fiscales -->
        <div x-show="activeTab === 'fiscal'" class="bg-white rounded-lg shadow p-6">
            <div class="space-y-6">

                <!-- Régimen Fiscal -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Régimen Fiscal
                    </label>
                    <select
                        name="regimen_fiscal"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">Seleccionar...</option>
                        <?php foreach ($regimenesFiscales as $regimen): ?>
                            <option value="<?php echo $regimen['id']; ?>"
                                <?php echo getFieldValue('regimen_fiscal', $cliente, []) == $regimen['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($regimen['descripcion']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Opciones de Riesgo y Fideicomiso -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <label class="flex items-start p-4 border-2 border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer transition">
                        <input
                            type="checkbox"
                            name="altoriesg"
                            value="1"
                            <?php echo getFieldValue('altoriesg', $cliente, []) ? 'checked' : ''; ?>
                            class="mt-1 w-4 h-4 text-red-600 rounded focus:ring-red-500">
                        <div class="ml-3">
                            <p class="font-medium text-gray-800">Alto Riesgo</p>
                            <p class="text-xs text-gray-600">Cliente clasificado como alto riesgo PLD</p>
                        </div>
                    </label>

                    <label class="flex items-start p-4 border-2 border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer transition">
                        <input type="hidden" name="fideicomitente" value="0">
                        <input
                            type="checkbox"
                            name="fideicomitente"
                            value="1"
                            <?php echo getFieldValue('fideicomitente', $cliente, []) ? 'checked' : ''; ?>
                            class="mt-1 w-4 h-4 text-blue-600 rounded focus:ring-blue-500">
                        <div class="ml-3">
                            <p class="font-medium text-gray-800">Fideicomitente</p>
                            <p class="text-xs text-gray-600">Es fideicomitente en algún fideicomiso</p>
                        </div>
                    </label>

                    <label class="flex items-start p-4 border-2 border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer transition">
                        <input type="hidden" name="fideicomisario" value="0">
                        <input
                            type="checkbox"
                            name="fideicomisario"
                            value="1"
                            <?php echo getFieldValue('fideicomisario', $cliente, []) ? 'checked' : ''; ?>
                            class="mt-1 w-4 h-4 text-blue-600 rounded focus:ring-blue-500">
                        <div class="ml-3">
                            <p class="font-medium text-gray-800">Fideicomisario</p>
                            <p class="text-xs text-gray-600">Es beneficiario de algún fideicomiso</p>
                        </div>
                    </label>
                </div>

                <!-- Estado del Cliente -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Estado del Cliente
                    </label>
                    <div class="flex items-center gap-6">
                        <label class="flex items-center">
                            <input
                                type="radio"
                                name="activo"
                                value="1"
                                <?php echo getFieldValue('activo', $cliente, [], '1') == '1' ? 'checked' : ''; ?>
                                class="w-4 h-4 text-green-600">
                            <span class="ml-2">Activo</span>
                        </label>
                        <label class="flex items-center">
                            <input
                                type="radio"
                                name="activo"
                                value="0"
                                <?php echo getFieldValue('activo', $cliente, [], '1') == '0' ? 'checked' : ''; ?>
                                class="w-4 h-4 text-red-600">
                            <span class="ml-2">Inactivo</span>
                        </label>
                    </div>
                </div>

                <!-- Sección de documentos -->
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-file-upload text-blue-600"></i>
                        Documentos Fiscales
                    </h3>

                    <div
                        x-data="{ isDragging: false }"
                        @dragover.prevent="isDragging = true"
                        @dragleave.prevent="isDragging = false"
                        @drop.prevent="isDragging = false; handleFileDrop($event)"
                        :class="isDragging ? 'border-blue-500 bg-blue-50' : 'border-gray-300'"
                        class="border-2 border-dashed rounded-lg p-6 text-center transition">
                        <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-3"></i>
                        <p class="text-gray-600 mb-2">Arrastra archivos aquí o</p>
                        <label class="inline-block">
                            <input
                                type="file"
                                name="documentos_fiscales[]"
                                multiple
                                accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                @change="handleFileSelect($event)"
                                class="hidden">
                            <span class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 cursor-pointer transition inline-flex items-center gap-2">
                                <i class="fas fa-file-upload"></i>
                                Seleccionar Archivos
                            </span>
                        </label>
                        <p class="text-xs text-gray-500 mt-2">
                            PDF, JPG, PNG, DOC, DOCX (Máx. 10MB por archivo)
                        </p>
                    </div>

                    <!-- Archivos seleccionados -->
                    <div x-show="selectedFiles.length > 0" class="mt-4">
                        <h4 class="font-medium text-gray-800 mb-2">Archivos para subir:</h4>
                        <div class="space-y-2">
                            <template x-for="(file, index) in selectedFiles" :key="index">
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-file text-blue-600"></i>
                                        <span x-text="file.name" class="text-sm"></span>
                                        <span x-text="formatFileSize(file.size)" class="text-xs text-gray-500"></span>
                                    </div>
                                    <button
                                        type="button"
                                        @click="removeFile(index)"
                                        class="text-red-500 hover:text-red-700">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Documentos existentes -->
                    <?php if ($isEdit): ?>
                        <?php
                        $documentos = $clienteManager->getClienteDocumentos($id);
                        if (!empty($documentos)):
                        ?>
                            <div class="mt-6">
                                <h4 class="font-medium text-gray-800 mb-3">Documentos guardados:</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <?php foreach ($documentos as $doc): ?>
                                        <div class="border border-gray-200 rounded-lg p-3 hover:shadow-md transition flex items-center justify-between">
                                            <div class="flex items-center gap-3">
                                                <i class="fas fa-file-pdf text-2xl text-red-600"></i>
                                                <div>
                                                    <p class="font-medium text-sm text-gray-800 truncate" style="max-width: 200px;">
                                                        <?php echo htmlspecialchars($doc['filename']); ?>
                                                    </p>
                                                    <p class="text-xs text-gray-500">
                                                        <?php echo date('d/m/Y H:i', strtotime($doc['fecha_upload'])); ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <a
                                                    href="modules/clientes/download.php?id=<?php echo $doc['id']; ?>"
                                                    target="_blank"
                                                    class="text-blue-600 hover:text-blue-800"
                                                    title="Descargar">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                                <button
                                                    type="button"
                                                    @click="deleteDocument(<?php echo $doc['id']; ?>)"
                                                    class="text-red-500 hover:text-red-700"
                                                    title="Eliminar">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

            </div>
        </div>



        <!-- Botones de acción -->
        <div class="mt-6 flex items-center justify-between bg-white rounded-lg shadow p-6">
            <p class="text-sm text-gray-600">
                <span class="text-red-500">*</span> Campos obligatorios
            </p>

            <div class="flex items-center gap-3">
                <a
                    href="catalogos.php?mod=clientes&action=list"
                    class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                    Cancelar
                </a>

                <button
                    type="submit"
                    :disabled="(!qsqVerified && !isEdit) || isSubmitting"
                    :class="((!qsqVerified && !isEdit) || isSubmitting) ? 'bg-gray-400 cursor-not-allowed' : 'bg-blue-600 hover:bg-blue-700'"
                    class="px-6 py-2 text-white rounded-lg transition flex items-center gap-2">
                    <i class="fas" :class="isSubmitting ? 'fa-spinner fa-spin' : 'fa-save'"></i>
                    <span x-text="isSubmitting ? 'Guardando...' : '<?php echo $submitText; ?>'"></span>
                </button>
            </div>
        </div>

    </form>

    <!-- Modal de Historial -->
    <?php if ($isEdit && $clientePermissions->canViewHistory()): ?>
        <div x-show="showHistory"
            x-cloak
            class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center"
            @click.self="showHistory = false">
            <div class="bg-white rounded-lg max-w-4xl w-full mx-4 max-h-[80vh] overflow-hidden">
                <div class="p-6 border-b flex items-center justify-between">
                    <h3 class="text-xl font-semibold">Historial del Cliente</h3>
                    <button @click="showHistory = false" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="p-6 overflow-y-auto max-h-[60vh]">
                    <?php if (!empty($cliente['historial'])): ?>
                        <div class="space-y-4">
                            <?php foreach ($cliente['historial'] as $evento): ?>
                                <div class="flex gap-4 p-4 bg-gray-50 rounded-lg">
                                    <div class="flex-shrink-0">
                                        <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                            <i class="fas fa-history text-blue-600"></i>
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-medium text-gray-800">
                                            <?php echo htmlspecialchars($evento['descripcion']); ?>
                                        </p>
                                        <p class="text-sm text-gray-600 mt-1">
                                            <?php echo htmlspecialchars($evento['usuario_nombre'] ?? 'Sistema'); ?> -
                                            <?php echo date('d/m/Y H:i', strtotime($evento['fecha'])); ?>
                                        </p>
                                        <?php if ($evento['datos_anteriores']): ?>
                                            <details class="mt-2">
                                                <summary class="text-xs text-blue-600 cursor-pointer">Ver cambios</summary>
                                                <pre class="text-xs bg-white p-2 rounded mt-1"><?php echo htmlspecialchars($evento['datos_anteriores']); ?></pre>
                                            </details>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-500 text-center py-8">No hay historial disponible</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

<script>
    function clienteFormController(isEdit, clienteId) {
        return {
            activeTab: 'basic',
            isEdit: isEdit,
            isSubmitting: false,
            showHistory: false,
            tipoPersona: '<?php echo getFieldValue('tipo_persona', $cliente, [], 'FISICA'); ?>',
            qsqVerified: isEdit, // Si es edición, ya está verificado
            isVerifying: false,
            canVerifyQSQ: false,
            selectedFiles: [],

            // Errores de validación
            rfcError: '',
            curpError: '',
            emailError: '',
            cpError: '',

            init() {
                console.log('Formulario de cliente inicializado', {
                    isEdit,
                    clienteId
                });
                this.checkCanVerify();
            },

            // Cambio de tipo de persona
            onTipoPersonaChange() {
                // Limpiar validaciones
                this.rfcError = '';
                this.curpError = '';

                // Limpiar campos si cambia el tipo
                if (this.tipoPersona === 'MORAL') {
                    document.querySelector('input[name="paterno"]').value = '';
                    document.querySelector('input[name="materno"]').value = '';
                    document.querySelector('input[name="curp"]').value = '';
                }

                // Revalidar RFC
                this.validateRFC();
                this.checkCanVerify();
            },

            // Validación de RFC
            validateRFC() {
                const rfc = document.getElementById('rfc').value.toUpperCase();
                if (!rfc) {
                    this.rfcError = '';
                    return true;
                }

                let pattern;
                if (this.tipoPersona === 'MORAL') {
                    pattern = /^[A-Z]{3}[0-9]{6}[A-Z0-9]{3}$/;
                    if (!pattern.test(rfc)) {
                        this.rfcError = 'RFC de persona moral inválido (AAA######XXX)';
                        return false;
                    }
                } else {
                    pattern = /^[A-Z]{4}[0-9]{6}[A-Z0-9]{3}$/;
                    if (!pattern.test(rfc)) {
                        this.rfcError = 'RFC de persona física inválido (AAAA######XXX)';
                        return false;
                    }
                }

                this.rfcError = '';
                return true;
            },

            // Validación de CURP
            validateCURP() {
                const curp = document.getElementById('curp').value.toUpperCase();
                if (!curp) {
                    this.curpError = '';
                    return true;
                }

                const pattern = /^[A-Z]{4}[0-9]{6}[HM][A-Z]{5}[0-9]{2}$/;
                if (!pattern.test(curp)) {
                    this.curpError = 'CURP inválido (AAAA######HXXXXXX##)';
                    return false;
                }

                this.curpError = '';
                return true;
            },

            // Validación de Email
            validateEmail() {
                const email = document.querySelector('input[name="emal"]').value;
                if (!email) {
                    this.emailError = '';
                    return true;
                }

                const pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!pattern.test(email)) {
                    this.emailError = 'Email inválido';
                    return false;
                }

                this.emailError = '';
                return true;
            },

            // Validación de CP
            validateCP() {
                const cp = document.getElementById('cp').value;
                if (!cp) {
                    this.cpError = '';
                    return true;
                }

                const pattern = /^[0-9]{5}$/;
                if (!pattern.test(cp)) {
                    this.cpError = 'Código postal debe ser de 5 dígitos';
                    return false;
                }

                this.cpError = '';
                return true;
            },

            // Verificar si puede ejecutar QSQ
            checkCanVerify() {
                const nombres = document.querySelector('input[name="nombres"]').value;
                const rfc = document.getElementById('rfc').value;
                const curp = document.getElementById('curp').value;

                this.canVerifyQSQ = (nombres.length > 2) || (rfc.length > 10) || (curp.length > 10);
            },

            // Verificar QSQ
            async verificarQSQ() {
                if (!this.canVerifyQSQ || this.isVerifying) return;

                this.isVerifying = true;

                const formData = new FormData();
                formData.append('action', 'verify_qsq');
                formData.append('nombres', document.querySelector('input[name="nombres"]').value);
                formData.append('paterno', document.querySelector('input[name="paterno"]').value);
                formData.append('materno', document.querySelector('input[name="materno"]').value);
                formData.append('rfc', document.getElementById('rfc').value);
                formData.append('curp', document.getElementById('curp').value);
                formData.append('tipo_persona', this.tipoPersona);

                try {
                    const response = await fetch('modules/clientes/action.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        if (result.data.valid) {
                            this.qsqVerified = true;
                            this.showAlert('success', 'Verificación QSQ exitosa', result.data.messages.join(', '));
                        } else {
                            this.qsqVerified = false;
                            this.showAlert('warning', 'Verificación QSQ con alertas', result.data.messages.join(', '));
                        }
                    } else {
                        this.showAlert('error', 'Error en verificación', result.message);
                    }
                } catch (error) {
                    console.error('Error:', error);
                    this.showAlert('error', 'Error de conexión', 'No se pudo conectar con el servicio de verificación');
                } finally {
                    this.isVerifying = false;
                }
            },

            // Manejo de archivos
            // Manejo de archivos
            handleFileDrop(event) {
                const files = Array.from(event.dataTransfer.files);
                this.addFiles(files, event.target);
            },

            handleFileSelect(event) {
                const files = Array.from(event.target.files);
                this.addFiles(files, event.target);
            },

            addFiles(files) {
                const validTypes = [
                    'application/pdf',
                    'image/jpeg',
                    'image/png',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                ];

                const input = document.querySelector('input[name="documentos_fiscales[]"]');
                if (!input) return;

                // Construimos base con lo que ya existe en selectedFiles sin duplicar
                const uniqueFiles = [...this.selectedFiles];

                files.forEach(file => {
                    if (!validTypes.includes(file.type)) {
                        this.showAlert('warning', 'Archivo no permitido', `${file.name} no es válido`);
                        return;
                    }

                    if (file.size > 10 * 1024 * 1024) {
                        this.showAlert('warning', 'Archivo muy grande', `${file.name} excede 10MB`);
                        return;
                    }

                    // Evitar duplicados exactos por nombre-size
                    if (!uniqueFiles.some(f => f.name === file.name && f.size === file.size)) {
                        uniqueFiles.push(file);
                    }
                });

                // Actualizamos estado único
                this.selectedFiles = uniqueFiles;

                // Actualizamos el input file
                const dt = new DataTransfer();
                this.selectedFiles.forEach(f => dt.items.add(f));
                input.files = dt.files;
            },

            removeFile(index) {
                this.selectedFiles.splice(index, 1);

                // CRÍTICO: Actualizar el input file también
                const fileInput = document.querySelector('input[name="documentos[]"][type="file"]');
                if (fileInput) {
                    const dataTransfer = new DataTransfer();

                    this.selectedFiles.forEach(file => {
                        dataTransfer.items.add(file);
                    });

                    fileInput.files = dataTransfer.files;
                }
            },

            async deleteDocument(docId) {
                if (!confirm('¿Eliminar este documento?')) return;

                const formData = new FormData();
                formData.append('action', 'delete_document');
                formData.append('document_id', docId);

                try {
                    const response = await fetch('modules/clientes/action.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        this.showAlert('success', 'Documento eliminado', 'El archivo se eliminó correctamente');
                        location.reload();
                    } else {
                        this.showAlert('error', 'Error', result.message || 'No se pudo eliminar el documento');
                    }
                } catch (err) {
                    console.error(err);
                    this.showAlert('error', 'Error', 'Problema de conexión con el servidor');
                }
            },
            // Validación del formulario
            validateForm(e) {
                // Validar que esté verificado QSQ si es nuevo
                if (!this.isEdit && !this.qsqVerified) {
                    e.preventDefault();
                    this.showAlert('warning', 'Verificación requerida', 'Debe verificar el cliente con QSQ antes de guardar');
                    return false;
                }

                // Validaciones básicas
                const nombres = document.querySelector('input[name="nombres"]').value.trim();
                if (!nombres) {
                    e.preventDefault();
                    this.activeTab = 'basic';
                    this.showAlert('error', 'Campo requerido', 'El nombre es obligatorio');
                    return false;
                }

                // Validar RFC si está presente
                if (!this.validateRFC()) {
                    e.preventDefault();
                    this.activeTab = 'basic';
                    return false;
                }

                // Validar CURP si está presente
                if (!this.validateCURP()) {
                    e.preventDefault();
                    this.activeTab = 'basic';
                    return false;
                }

                // Validar Email si está presente
                if (!this.validateEmail()) {
                    e.preventDefault();
                    this.activeTab = 'basic';
                    return false;
                }

                // Validar CP si está presente
                if (!this.validateCP()) {
                    e.preventDefault();
                    this.activeTab = 'address';
                    return false;
                }

                this.isSubmitting = true;
                return true;
            },

            // Mostrar alertas
            showAlert(type, title, message) {
                // Aquí puedes implementar tu sistema de alertas preferido
                // Por ahora uso alert simple
                alert(`${title}\n${message}`);
            }
        };
    }
</script>