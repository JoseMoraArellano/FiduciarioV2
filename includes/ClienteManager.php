<?php

/**
 * Clase ClientesManager - Gestión completa de clientes
 * Maneja: CRUD, validaciones, exportación, histórico, documentos
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
class ClienteManager
{

    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    // ==================== CRUD DE CLIENTES ====================

    /**
     * Obtener todos los clientes con filtros
     * @param array $filters - Filtros opcionales
     * @param int $page - Página actual
     * @param int $perPage - Items por página
     * @return array
     */
    public function getClientes($filters = [], $page = 1, $perPage = 20)
    {
        try {
            $where = ['1=1'];
            $params = [];

            // Filtro de búsqueda
            if (!empty($filters['search'])) {
                $where[] = "(
                    c.nombres ILIKE :search OR 
                    c.paterno ILIKE :search OR 
                    c.materno ILIKE :search OR
                    c.rfc ILIKE :search OR
                    c.curp ILIKE :search OR
                    c.emal ILIKE :search OR
                    CONCAT(c.nombres, ' ', c.paterno, ' ', c.materno) ILIKE :search
                )";
                $params['search'] = '%' . $filters['search'] . '%';
            }

            // Filtro por estado activo/inactivo
            if (isset($filters['activo']) && $filters['activo'] !== '') {
                $where[] = "c.activo = :activo";
                $params['activo'] = (bool)$filters['activo'];
            }

            // Filtro por tipo de persona
            if (!empty($filters['tipo_persona'])) {
                $where[] = "c.tipo_persona = :tipo_persona";
                $params['tipo_persona'] = $filters['tipo_persona'];
            }

            // Filtro por régimen fiscal
            if (!empty($filters['regimen_fiscal'])) {
                $where[] = "c.regimen_fiscal = :regimen_fiscal";
                $params['regimen_fiscal'] = (int)$filters['regimen_fiscal'];
            }

            // Filtro por estado (entidad federativa)
            if (!empty($filters['edo'])) {
                $where[] = "c.edo = :edo";
                $params['edo'] = (int)$filters['edo'];
            }

            // Filtro por alto riesgo
            if (isset($filters['altoriesg']) && $filters['altoriesg'] !== '') {
                $where[] = "c.altoriesg = :altoriesg";
                $params['altoriesg'] = (bool)$filters['altoriesg'];
            }

            // Filtro por fecha de creación
            if (!empty($filters['fecha_desde'])) {
                $where[] = "DATE(c.fechac) >= :fecha_desde";
                $params['fecha_desde'] = $filters['fecha_desde'];
            }

            if (!empty($filters['fecha_hasta'])) {
                $where[] = "DATE(c.fechac) <= :fecha_hasta";
                $params['fecha_hasta'] = $filters['fecha_hasta'];
            }

            $whereClause = implode(' AND ', $where);

            // Contar total de registros
            $sqlCount = "
                SELECT COUNT(*) as total
                FROM t_cliente c
                WHERE {$whereClause}
            ";

            $stmtCount = $this->db->prepare($sqlCount);
            $stmtCount->execute($params);
            $total = $stmtCount->fetch()['total'];

            // Calcular offset
            $offset = ($page - 1) * $perPage;

            // Obtener registros con paginación
            $sql = "
                SELECT 
                    c.*,
                    e.nom as estado_nombre,
                    p.nom as pais_nombre,
                    rf.descripcion as regimen_fiscal_desc,
                    uc.name as usuario_creador,
                    ue.name as usuario_editor,
                    (SELECT COUNT(*) FROM t_cliente_docs WHERE fk_cliente = c.id) as total_documentos
                FROM t_cliente c
                LEFT JOIN t_edo e ON c.edo = e.id
                LEFT JOIN t_pais p ON c.pais = p.id
                LEFT JOIN t_fiscales rf ON c.regimen_fiscal = rf.id
                LEFT JOIN users uc ON c.userc = uc.id
                LEFT JOIN users ue ON c.useredit = ue.id
                WHERE {$whereClause}
                ORDER BY c.fechac DESC
                LIMIT :limit OFFSET :offset
            ";

            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();
            $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Formatear nombres completos
            foreach ($clientes as &$cliente) {
                $cliente['nombre_completo'] = trim(
                    $cliente['nombres'] . ' ' .
                        $cliente['paterno'] . ' ' .
                        $cliente['materno']
                );
            }

            return [
                'success' => true,
                'data' => $clientes,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage)
            ];
        } catch (PDOException $e) {
            error_log("Error getting clientes: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al obtener clientes'
            ];
        }
    }

    /**
     * Exportar a CSV
     */
    public function exportToCSV($filters = [])
    {
        try {
            // Obtener todos los registros con los filtros aplicados
            $result = $this->getClientes($filters, 1, 100000);

            if (!$result['success']) {
                return $result;
            }

            $clientes = $result['data'];

            // Crear CSV
            $output = '';

            // Headers
            $headers = [
                'ID',
                'Nombre Completo',
                'RFC',
                'CURP',
                'Tipo Persona',
                'Régimen Fiscal',
                'Dirección',
                'Colonia',
                'Delegación/Municipio',
                'CP',
                'Estado',
                'País',
                'Email',
                'Teléfono',
                'Teléfono 2',
                'Extensión',
                'Alto Riesgo',
                'Fideicomitente',
                'Fideicomisario',
                'Estado',
                'Fecha Creación',
                'Usuario Creó',
                'Fecha Modificación',
                'Usuario Modificó',
                'Comentarios'
            ];

            $output .= $this->arrayToCsv($headers);

            // Datos
            foreach ($clientes as $cliente) {
                $row = [
                    $cliente['id'],
                    $cliente['nombre_completo'],
                    $cliente['rfc'],
                    $cliente['curp'],
                    $cliente['tipo_persona'],
                    $cliente['regimen_fiscal_desc'] ?? '',
                    trim($cliente['calle'] . ' ' . $cliente['nroext'] . ' ' . $cliente['nroint']),
                    $cliente['colonia'],
                    $cliente['delegacion'],
                    $cliente['cp'],
                    $cliente['estado_nombre'] ?? '',
                    $cliente['pais_nombre'] ?? '',
                    $cliente['emal'],
                    $cliente['tel'],
                    $cliente['tel2'],
                    $cliente['ext'],
                    $cliente['altoriesg'] ? 'Sí' : 'No',
                    $cliente['fideicomitente'] ? 'Sí' : 'No',
                    $cliente['fideicomisario'] ? 'Sí' : 'No',
                    $cliente['activo'] ? 'Activo' : 'Inactivo',
                    $cliente['fechac'],
                    $cliente['usuario_creador'] ?? '',
                    $cliente['fechaedit'],
                    $cliente['usuario_editor'] ?? '',
                    $cliente['coment']
                ];

                $output .= $this->arrayToCsv($row);
            }

            return [
                'success' => true,
                'data' => $output
            ];
        } catch (Exception $e) {
            error_log("Error exporting to CSV: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al exportar a CSV'
            ];
        }
    }

    /**
     * Convertir array a línea CSV
     */
    private function arrayToCsv($array)
    {
        $output = '';
        foreach ($array as $value) {
            $value = str_replace('"', '""', $value);
            $output .= '"' . $value . '",';
        }
        $output = rtrim($output, ',') . "\n";
        return $output;
    }

    /**
     * Obtener un cliente por ID
     */
    public function getCliente($id)
    {
        try {
            $sql = "
                SELECT 
                    c.*,
                    e.nom as estado_nombre,
                    p.nom as pais_nombre,
                    rf.descripcion as regimen_fiscal_desc,
                    uc.name as usuario_creador,
                    ue.name as usuario_editor
                FROM t_cliente c
                LEFT JOIN t_edo e ON c.edo = e.id
                LEFT JOIN t_pais p ON c.pais = p.id
                LEFT JOIN t_fiscales rf ON c.regimen_fiscal = rf.id
                LEFT JOIN users uc ON c.userc = uc.id
                LEFT JOIN users ue ON c.useredit = ue.id
                WHERE c.id = :id
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);

            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$cliente) {
                return [
                    'success' => false,
                    'message' => 'Cliente no encontrado'
                ];
            }

            // Obtener documentos del cliente
            $cliente['documentos'] = $this->getClienteDocumentos($id);

            // Obtener historial si existe
            $cliente['historial'] = $this->getClienteHistorial($id);

            return [
                'success' => true,
                'data' => $cliente
            ];
        } catch (PDOException $e) {
            error_log("Error getting cliente: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al obtener cliente'
            ];
        }
    }

    /**
     * Crear nuevo cliente
     */
    public function createCliente($data)
    {
        try {
            $this->db->beginTransaction();

            // Validar datos
            $validation = $this->validateClienteData($data);
            if (!$validation['success']) {
                return $validation;
            }

            // Verificar RFC único
            if (!empty($data['rfc']) && $this->rfcExists($data['rfc'])) {
                return [
                    'success' => false,
                    'message' => 'El RFC ya está registrado'
                ];
            }

            // Verificar CURP único
            //            if (!empty($data['curp']) && $this->curpExists($data['curp'])) {
            //                return [
            //                    'success' => false,
            //                    'message' => 'El CURP ya está registrado'
            //                ];
            //            }

            $sql = "
                INSERT INTO t_cliente (
                    nombres, paterno, materno, rfc, curp,
                    calle, nroint, nroext, cp, colonia,
                    delegacion, edo, emal, tel, tel2, ext,
                    tipo_persona, regimen_fiscal, userc, fechac,
                    coment, pais, altoriesg, fideicomitente,
                    fideicomisario, activo
                )
                VALUES (
                    :nombres, :paterno, :materno, :rfc, :curp,
                    :calle, :nroint, :nroext, :cp, :colonia,
                    :delegacion, :edo, :emal, :tel, :tel2, :ext,
                    :tipo_persona, :regimen_fiscal, :userc, NOW(),
                    :coment, :pais, :altoriesg, :fideicomitente,
                    :fideicomisario, :activo
                )
                RETURNING id
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'nombres' => $data['nombres'],
                'paterno' => $data['paterno'] ?? null,
                'materno' => $data['materno'] ?? null,
                'rfc' => strtoupper($data['rfc'] ?? ''),
                'curp' => strtoupper($data['curp'] ?? ''),
                'calle' => $data['calle'] ?? null,
                'nroint' => $data['nroint'] ?? null,
                'nroext' => $data['nroext'] ?? null,
                'cp' => $data['cp'] ?? null,
                'colonia' => $data['colonia'] ?? null,
                'delegacion' => $data['delegacion'] ?? null,
                'edo' => $data['edo'] ?? null,
                'emal' => $data['emal'] ?? null,
                'tel' => $data['tel'] ?? null,
                'tel2' => $data['tel2'] ?? null,
                'ext' => $data['ext'] ?? null,
                'tipo_persona' => $data['tipo_persona'],
                'regimen_fiscal' => $data['regimen_fiscal'] ?? null,
                'userc' => $_SESSION['user_id'] ?? 1,
                'coment' => $data['coment'] ?? null,
                'pais' => $data['pais'] ?? 1, // México por defecto
                'altoriesg' => isset($data['altoriesg']) ? (bool)$data['altoriesg'] : false,
                'fideicomitente' => isset($data['fideicomitente']) ? (bool)$data['fideicomitente'] : false,
                'fideicomisario' => isset($data['fideicomisario']) ? (bool)$data['fideicomisario'] : false,
                'activo' => isset($data['activo']) ? (bool)$data['activo'] : true
            ]);

            $clienteId = $stmt->fetch()['id'];

            // Registrar en historial
            $this->addHistorial($clienteId, 'CREATE', 'Cliente creado', $data);

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Cliente creado exitosamente',
                'cliente_id' => $clienteId
            ];
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error creating cliente: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al crear cliente'
            ];
        }
    }

    /**
     * Actualizar cliente
     */
    public function updateCliente($id, $data)
    {
        try {
            $this->db->beginTransaction();

            // Obtener datos anteriores para historial
            $oldData = $this->getCliente($id);
            if (!$oldData['success']) {
                return $oldData;
            }

            // Validar datos
            $validation = $this->validateClienteData($data, $id);
            if (!$validation['success']) {
                return $validation;
            }

            // Verificar RFC único
            if (!empty($data['rfc']) && $this->rfcExists($data['rfc'], $id)) {
                return [
                    'success' => false,
                    'message' => 'El RFC ya está registrado por otro cliente'
                ];
            }

            // Verificar CURP único
            if (!empty($data['curp']) && $this->curpExists($data['curp'], $id)) {
                return [
                    'success' => false,
                    'message' => 'El CURP ya está registrado por otro cliente'
                ];
            }

            $sql = "
                UPDATE t_cliente SET
                    nombres = :nombres,
                    paterno = :paterno,
                    materno = :materno,
                    rfc = :rfc,
                    curp = :curp,
                    calle = :calle,
                    nroint = :nroint,
                    nroext = :nroext,
                    cp = :cp,
                    colonia = :colonia,
                    delegacion = :delegacion,
                    edo = :edo,
                    emal = :emal,
                    tel = :tel,
                    tel2 = :tel2,
                    ext = :ext,
                    tipo_persona = :tipo_persona,
                    regimen_fiscal = :regimen_fiscal,
                    useredit = :useredit,
                    fechaedit = NOW(),
                    coment = :coment,
                    pais = :pais,
                    altoriesg = :altoriesg,
                    fideicomitente = :fideicomitente,
                    fideicomisario = :fideicomisario,
                    activo = :activo
                WHERE id = :id
            ";


            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'id' => $id,
                'nombres' => $data['nombres'],
                'paterno' => $data['paterno'] ?? null,
                'materno' => $data['materno'] ?? null,
                'rfc' => strtoupper($data['rfc'] ?? ''),
                'curp' => strtoupper($data['curp'] ?? ''),
                'calle' => $data['calle'] ?? null,
                'nroint' => $data['nroint'] ?? null,
                'nroext' => $data['nroext'] ?? null,
                'cp' => $data['cp'] ?? null,
                'colonia' => $data['colonia'] ?? null,
                'delegacion' => $data['delegacion'] ?? null,
                'edo' => $data['edo'] ?? null,
                'emal' => $data['emal'] ?? null,
                'tel' => $data['tel'] ?? null,
                'tel2' => $data['tel2'] ?? null,
                'ext' => $data['ext'] ?? null,
                'tipo_persona' => $data['tipo_persona'],
                'regimen_fiscal' => $data['regimen_fiscal'] ?? null,
                'useredit' => $_SESSION['user_id'] ?? 1,
                'coment' => $data['coment'] ?? null,
                'pais' => $data['pais'] ?? 1,
                'altoriesg' => ((bool)($data['altoriesg'] ?? false)) ? 't' : 'f',
                'fideicomitente' => ((bool)($data['fideicomitente'] ?? false)) ? 't' : 'f',
                'fideicomisario' => ((bool)($data['fideicomisario'] ?? false)) ? 't' : 'f',
                'activo' => ((bool)($data['activo'] ?? true)) ? 't' : 'f'
            ]);

            // Registrar cambios en historial
            $changes = $this->compareChanges($oldData['data'], $data);
            if (!empty($changes)) {
                $this->addHistorial($id, 'UPDATE', 'Cliente actualizado', $changes);
            }

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Cliente actualizado exitosamente'
            ];
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error updating cliente: " . $e->getMessage());
            error_log("SQL Error Code: " . $e->getCode());
            error_log("Data received: " . print_r($data, true));
            return [
                'success' => false,
                'message' => 'Error al actualizar cliente: ' . $e->getMessage()  // ← Agregamos el mensaje real
            ];
        }
    }

    /**
     * Eliminar cliente (soft delete)
     */
    public function deleteCliente($id)
    {
        try {
            // Verificar si el cliente puede ser eliminado
            //            $canDelete = $this->canDeleteCliente($id);
            //            if (!$canDelete['success']) {
            //                return $canDelete;
            //            }

            $sql = "UPDATE t_cliente SET activo = false, fechaedit = NOW(), useredit = :useredit WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'id' => $id,
                'useredit' => $_SESSION['user_id'] ?? 1
            ]);

            // Registrar en historial
            $this->addHistorial($id, 'DELETE', 'Cliente eliminado');

            return [
                'success' => true,
                'message' => 'Cliente eliminado exitosamente'
            ];
        } catch (PDOException $e) {
            error_log("Error deleting cliente: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al eliminar cliente'
            ];
        }
    }

    /**
     * Cambiar estado activo/inactivo
     */
    public function toggleStatus($id)
    {
        try {
            $sql = "
                UPDATE t_cliente 
                SET activo = CASE WHEN activo = true THEN FALSE ELSE TRUE END,
                    fechaedit = NOW(),
                    useredit = :useredit
                WHERE id = :id
                RETURNING activo
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'id' => $id,
                'useredit' => $_SESSION['user_id'] ?? 1
            ]);

            $newStatus = $stmt->fetch()['activo'];

            // Registrar en historial
            $this->addHistorial($id, 'STATUS_CHANGE', $newStatus ? 'Cliente activado' : 'Cliente desactivado');

            return [
                'success' => true,
                'message' => $newStatus ? 'Cliente activado' : 'Cliente desactivado',
                'new_status' => $newStatus
            ];
        } catch (PDOException $e) {
            error_log("Error toggling status: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al cambiar estado'
            ];
        }
    }
    
    // ==================== VALIDACIONES ====================

    /**
     * Validar datos del cliente
     */
    private function validateClienteData($data, $excludeId = null)
    {
        $errors = [];

        // Campos requeridos
        if (empty($data['nombres'])) {
            $errors[] = 'El nombre es requerido';
        }

        if (empty($data['tipo_persona'])) {
            $errors[] = 'El tipo de persona es requerido';
        } elseif (!in_array($data['tipo_persona'], ['FISICA', 'MORAL'])) {
            $errors[] = 'Tipo de persona inválido';
        }

        // Validar RFC si se proporciona
        if (!empty($data['rfc'])) {
            if ($data['tipo_persona'] == 'FISICA') {
                if (!preg_match('/^[A-Z]{4}[0-9]{6}[A-Z0-9]{3}$/', strtoupper($data['rfc']))) {
                    $errors[] = 'RFC inválido para persona física (formato: AAAA######XXX)';
                }
            } else {
                if (!preg_match('/^[A-Z]{3}[0-9]{6}[A-Z0-9]{3}$/', strtoupper($data['rfc']))) {
                    $errors[] = 'RFC inválido para persona moral (formato: AAA######XXX)';
                }
            }
        }

        // Validar CURP si se proporciona
        if (!empty($data['curp'])) {
            if (!preg_match('/^[A-Z]{4}[0-9]{6}[HM][A-Z]{5}[0-9]{2}$/', strtoupper($data['curp']))) {
                $errors[] = 'CURP inválido (formato: AAAA######HXXXXXX##)';
            }
        }

        // Validar email
        if (!empty($data['emal']) && !filter_var($data['emal'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email inválido';
        }

        // Validar CP
        if (!empty($data['cp'])) {
            $cpValue = (int)$data['cp'];
            if ($cpValue < 1000 || $cpValue > 99999) {
                $errors[] = 'Código postal inválido';
            }
        }

        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => implode(', ', $errors),
                'errors' => $errors
            ];
        }

        return ['success' => true];
    }

    /**
     * Verificar si RFC existe
     */
    private function rfcExists($rfc, $excludeId = null)
    {
        if (empty($rfc)) return false;

        $sql = "SELECT id FROM t_cliente WHERE UPPER(rfc) = UPPER(:rfc)";

        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
        }

        $stmt = $this->db->prepare($sql);
        $params = ['rfc' => $rfc];

        if ($excludeId) {
            $params['exclude_id'] = $excludeId;
        }

        $stmt->execute($params);
        return $stmt->fetch() !== false;
    }

    /**
     * Verificar si CURP existe
     */
    private function curpExists($curp, $excludeId = null)
    {
        if (empty($curp)) return false;

        $sql = "SELECT id FROM t_cliente WHERE UPPER(curp) = UPPER(:curp)";

        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
        }

        $stmt = $this->db->prepare($sql);
        $params = ['curp' => $curp];

        if ($excludeId) {
            $params['exclude_id'] = $excludeId;
        }

        $stmt->execute($params);
        return $stmt->fetch() !== false;
    }

    public function canEdit($editorUserId, $targetUserId, $isEditorAdmin = false)
    {
        // Permitir si el editor es admin
        // Admin puede editar a todos excepto a sí mismo en ciertos casos
        if ($isEditorAdmin) {
            return true;
        }

        // No admin solo puede editarse a sí mismo
        return $editorUserId == $targetUserId;
    }

    /**
     * Verificar si cliente puede ser eliminado
     */
    private function canDeleteCliente($id)
    {
        // Verificar que el cliente exista
        $sql = "SELECT COUNT(*) FROM t_cliente WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        if ($stmt->fetchColumn() == 0) {
            return [
                'success' => false,
                'message' => 'Cliente no encontrado'
            ];
        }

        // Verificar si está como fideicomisario
        $sql = "SELECT COUNT(id_clit) FROM t_fideicomisarios WHERE id_clit = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        if ($stmt->fetchColumn() > 0) {
            return [
                'success' => false,
                'message' => 'Cliente no puede ser eliminado, está asignado como fideicomisario'
            ];
        }

        // Verificar si está como fideicomitente
        $sql = "SELECT COUNT(id_clit) FROM t_fideicomitentes WHERE id_clit = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        if ($stmt->fetchColumn() > 0) {
            return [
                'success' => false,
                'message' => 'Cliente no puede ser eliminado, está asignado como fideicomitente'
            ];
        }

        // Si pasa todas las validaciones, se puede eliminar
        return ['success' => true];
    }
    
    // ==================== FUNCIONES ESPECIALES ====================

    /**
     * Verificar QSQ - Función personalizada
     * TODO: Implementar lógica específica
     */
    public function verificarQSQ($data)
    {
        try {
            // Aquí implementar la lógica de verificación QSQ
            // Por ejemplo: verificar en SAT, listas negras, etc.

            $result = [
                'valid' => true,
                'messages' => []
            ];

            // Verificaciones ejemplo
            if (!empty($data['rfc'])) {
                // Verificar RFC en SAT o base de datos externa
                // ...
                $result['messages'][] = 'RFC verificado correctamente';
            }

            if (!empty($data['curp'])) {
                // Verificar CURP en RENAPO o base de datos externa
                // ...
                $result['messages'][] = 'CURP verificado correctamente';
            }

            // Verificar listas negras
            if (isset($data['altoriesg']) && $data['altoriesg']) {
                $result['messages'][] = 'Cliente marcado como alto riesgo';
                $result['requires_approval'] = true;
            }

            return [
                'success' => true,
                'data' => $result
            ];
        } catch (Exception $e) {
            error_log("Error en verificarQSQ: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al verificar datos'
            ];
        }
    }
    
    // ==================== GESTIÓN DE DOCUMENTOS ====================

    /**
     * Obtener documentos del cliente
     */
    public function getClienteDocumentos($clienteId)
    {
        try {
            $sql = "
                SELECT *
                FROM t_cliente_docs
                WHERE fk_cliente = :cliente_id
                ORDER BY fecha_upload DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['cliente_id' => $clienteId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting documentos: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Agregar documento al cliente
     */
    public function addDocumento($clienteId, $fileData)
    {
        try {
            $sql = "
                INSERT INTO t_cliente_docs (
                    fk_cliente, filename, filepath, tipo,
                    size, mime_type, usuario_upload, fecha_upload
                )
                VALUES (
                    :cliente_id, :filename, :filepath, :tipo,
                    :size, :mime_type, :usuario, NOW()
                )
                RETURNING id
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'cliente_id' => $clienteId,
                'filename' => $fileData['filename'],
                'filepath' => $fileData['filepath'],
                'tipo' => $fileData['tipo'] ?? 'general',
                'size' => $fileData['size'],
                'mime_type' => $fileData['mime_type'],
                'usuario' => $_SESSION['user_id'] ?? 1
            ]);

            $docId = $stmt->fetch()['id'];

            // Registrar en historial
            $this->addHistorial($clienteId, 'DOC_ADD', "Documento agregado: {$fileData['filename']}");

            return [
                'success' => true,
                'message' => 'Documento agregado exitosamente',
                'doc_id' => $docId
            ];
        } catch (PDOException $e) {
            error_log("Error adding documento: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al agregar documento'
            ];
        }
    }

    /**
     * Eliminar documento
     */
    public function deleteDocumento($docId)
    {
        try {
            // Obtener info del documento
            $sql = "SELECT * FROM t_cliente_docs WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $docId]);
            $doc = $stmt->fetch();

            if (!$doc) {
                return [
                    'success' => false,
                    'message' => 'Documento no encontrado'
                ];
            }

            // Eliminar archivo físico
            if (file_exists($doc['filepath'])) {
                unlink($doc['filepath']);
            }

            // Eliminar registro
            $sql = "DELETE FROM t_cliente_docs WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $docId]);

            // Registrar en historial
            $this->addHistorial($doc['fk_cliente'], 'DOC_DELETE', "Documento eliminado: {$doc['filename']}");

            return [
                'success' => true,
                'message' => 'Documento eliminado exitosamente'
            ];
        } catch (PDOException $e) {
            error_log("Error deleting documento: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al eliminar documento'
            ];
        }
    }
    
    // ==================== GESTIÓN DE HISTORIAL ====================

    /**
     * Obtener historial del cliente
     */
    public function getClienteHistorial($clienteId, $limit = 50)
    {
        try {
            $sql = "
                SELECT 
                    h.*,
                    u.name as usuario_nombre
                FROM t_cliente_log h
                LEFT JOIN users u ON h.usuario_id = u.id
                WHERE h.fk_cliente = :cliente_id
                ORDER BY h.fecha DESC
                LIMIT :limit
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':cliente_id', $clienteId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting historial: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Agregar entrada al historial
     */
    private function addHistorial($clienteId, $accion, $descripcion, $datosAnteriores = null)
    {
        try {
            $sql = "
                INSERT INTO t_cliente_log (
                    fk_cliente, accion, descripcion,
                    datos_anteriores, usuario_id, fecha
                )
                VALUES (
                    :cliente_id, :accion, :descripcion,
                    :datos_anteriores, :usuario_id, NOW()
                )
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'cliente_id' => $clienteId,
                'accion' => $accion,
                'descripcion' => $descripcion,
                'datos_anteriores' => $datosAnteriores ? json_encode($datosAnteriores) : null,
                'usuario_id' => $_SESSION['user_id'] ?? 1
            ]);
        } catch (PDOException $e) {
            error_log("Error adding historial: " . $e->getMessage());
        }
    }

    /**
     * Comparar cambios para historial
     */
    private function compareChanges($oldData, $newData)
    {
        $changes = [];

        $fieldsToCompare = [
            'nombres',
            'paterno',
            'materno',
            'rfc',
            'curp',
            'calle',
            'nroint',
            'nroext',
            'cp',
            'colonia',
            'delegacion',
            'edo',
            'emal',
            'tel',
            'tel2',
            'tipo_persona',
            'regimen_fiscal',
            'altoriesg',
            'fideicomitente',
            'fideicomisario',
            'activo'
        ];

        foreach ($fieldsToCompare as $field) {
            $old = $oldData[$field] ?? null;
            $new = $newData[$field] ?? null;

            if ($old != $new) {
                $changes[$field] = [
                    'old' => $old,
                    'new' => $new
                ];
            }
        }

        return $changes;
    }
    
    // ==================== FUNCIONES AUXILIARES ====================

    /**
     * Obtener lista de estados
     */
    public function getEstados()
    {
        $sql = "SELECT id, nom FROM t_edo ORDER BY nom";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener lista de países
     */
    public function getPaises()
    {
        $sql = "SELECT id, nom FROM t_pais ORDER BY nom";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener regímenes fiscales
     */
    public function getRegimenesFiscales()
    {
        $sql = "SELECT id, descripcion FROM t_fiscales ORDER BY descripcion";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Búsqueda rápida de clientes
     */
    public function quickSearch($query, $limit = 10)
    {
        try {
            $sql = "
                SELECT 
                    c.id,
                    c.nombres,
                    c.paterno,
                    c.materno,
                    c.rfc,
                    c.curp,
                    c.emal,
                    c.activo,
                    CONCAT(c.nombres, ' ', c.paterno, ' ', c.materno) as nombre_completo
                FROM t_cliente c
                WHERE (
                    c.nombres ILIKE :query 
                    OR c.paterno ILIKE :query
                    OR c.materno ILIKE :query
                    OR c.rfc ILIKE :query
                    OR c.curp ILIKE :query
                    OR CONCAT(c.nombres, ' ', c.paterno, ' ', c.materno) ILIKE :query
                )
                AND c.activo = true
                ORDER BY c.nombres, c.paterno
                LIMIT :limit
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':query', '%' . $query . '%', PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return [
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];
        } catch (PDOException $e) {
            error_log("Error in quick search: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error en la búsqueda'
            ];
        }
    }

    /**
     * Obtener estadísticas de clientes
     */
    public function getStats()
    {
        try {
            $stats = [];

            // Total de clientes
            $sql = "SELECT COUNT(*) as total FROM t_cliente";
            $stmt = $this->db->query($sql);
            $stats['total'] = $stmt->fetch()['total'];

            // Clientes activos
            $sql = "SELECT COUNT(*) as total FROM t_cliente WHERE activo = true";
            $stmt = $this->db->query($sql);
            $stats['activos'] = $stmt->fetch()['total'];

            // Clientes inactivos
            $sql = "SELECT COUNT(*) as total FROM t_cliente WHERE activo = false";
            $stmt = $this->db->query($sql);
            $stats['inactivos'] = $stmt->fetch()['total'];

            // Por tipo de persona
            $sql = "SELECT tipo_persona, COUNT(*) as total FROM t_cliente GROUP BY tipo_persona";
            $stmt = $this->db->query($sql);
            $tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($tipos as $tipo) {
                $stats['tipo_' . strtolower($tipo['tipo_persona'])] = $tipo['total'];
            }

            // Alto riesgo
            $sql = "SELECT COUNT(*) as total FROM t_cliente WHERE altoriesg = true";
            $stmt = $this->db->query($sql);
            $stats['alto_riesgo'] = $stmt->fetch()['total'];

            // Fideicomisos
            $sql = "SELECT COUNT(*) as total FROM t_cliente WHERE fideicomitente = true OR fideicomisario = true";
            $stmt = $this->db->query($sql);
            $stats['fideicomisos'] = $stmt->fetch()['total'];

            // Creados este mes
            $sql = "
                SELECT COUNT(*) as total 
                FROM t_cliente 
                WHERE DATE_TRUNC('month', fechac) = DATE_TRUNC('month', CURRENT_DATE)
            ";
            $stmt = $this->db->query($sql);
            $stats['creados_mes'] = $stmt->fetch()['total'];

            // Con documentos
            $sql = "
                SELECT COUNT(DISTINCT fk_cliente) as total
                FROM t_cliente_docs
            ";
            $stmt = $this->db->query($sql);
            $stats['con_documentos'] = $stmt->fetch()['total'];

            return $stats;
        } catch (PDOException $e) {
            error_log("Error getting stats: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Exportar a Excel
     */
    public function exportToExcel($filters = [])
    {
        try {
            // Obtener todos los registros con los filtros aplicados
            $result = $this->getClientes($filters, 1, 100000);

            if (!$result['success']) {
                return $result;
            }

            $clientes = $result['data'];

            // Crear array para Excel
            $data = [];

            // Headers
            $headers = [
                'ID',
                'Nombre Completo',
                'RFC',
                'CURP',
                'Tipo Persona',
                'Régimen Fiscal',
                'Dirección',
                'Colonia',
                'Delegación/Municipio',
                'CP',
                'Estado',
                'País',
                'Email',
                'Teléfono',
                'Teléfono 2',
                'Extensión',
                'Alto Riesgo',
                'Fideicomitente',
                'Fideicomisario',
                'Estado',
                'Fecha Creación',
                'Usuario Creó',
                'Fecha Modificación',
                'Usuario Modificó',
                'Comentarios'
            ];

            $data[] = $headers;

            // Datos
            foreach ($clientes as $cliente) {
                $data[] = [
                    $cliente['id'],
                    $cliente['nombre_completo'],
                    $cliente['rfc'],
                    $cliente['curp'],
                    $cliente['tipo_persona'],
                    $cliente['regimen_fiscal_desc'] ?? '',
                    trim($cliente['calle'] . ' ' . $cliente['nroext'] . ' ' . $cliente['nroint']),
                    $cliente['colonia'],
                    $cliente['delegacion'],
                    $cliente['cp'],
                    $cliente['estado_nombre'] ?? '',
                    $cliente['pais_nombre'] ?? '',
                    $cliente['emal'],
                    $cliente['tel'],
                    $cliente['tel2'],
                    $cliente['ext'],
                    $cliente['altoriesg'] ? 'Sí' : 'No',
                    $cliente['fideicomitente'] ? 'Sí' : 'No',
                    $cliente['fideicomisario'] ? 'Sí' : 'No',
                    $cliente['activo'] ? 'Activo' : 'Inactivo',
                    $cliente['fechac'],
                    $cliente['usuario_creador'] ?? '',
                    $cliente['fechaedit'],
                    $cliente['usuario_editor'] ?? '',
                    $cliente['coment']
                ];
            }

            return [
                'success' => true,
                'data' => $data,
                'filename' => 'clientes_' . date('Y-m-d_His') . '.xlsx'
            ];
        } catch (Exception $e) {
            error_log("Error exporting to Excel: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al exportar a Excel'
            ];
        }
    }
    /**
     * Procesar y guardar múltiples archivos subidos
     * 
     * @param int $clienteId ID del cliente
     * @param array $files Array de archivos del formato $_FILES
     * @param string $tipo Tipo de documento ('fiscal', 'general', etc)
     * @return array Resultado de la operación
     */
    public function procesarArchivosSubidos($clienteId, $files, $tipo = 'general')
    {
        try {
            if (empty($files['name'][0])) {
                return [
                    'success' => true,
                    'message' => 'No hay archivos para procesar',
                    'archivos_subidos' => []
                ];
            }

            // Definir directorio de uploads
            $uploadBaseDir = __DIR__ . '/../uploads/clientes/';
            $uploadDir = $uploadBaseDir . $clienteId . '/';

            // Crear directorio si no existe
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    error_log("ERROR: No se pudo crear directorio: $uploadDir");
                    return [
                        'success' => false,
                        'message' => 'Error al crear directorio de uploads',
                        'archivos_subidos' => []
                    ];
                }
                error_log("Directorio creado: $uploadDir");
            }

            $archivosSubidos = [];
            $errores = [];
            $tiposPermitidos = [
                'application/pdf',
                'image/jpeg',
                'image/png',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ];
            $tamañoMaximo = 10 * 1024 * 1024; // 10MB

            foreach ($files['name'] as $key => $filename) {
                // Verificar si hubo error en la carga
                if ($files['error'][$key] !== UPLOAD_ERR_OK) {
                    $errores[] = "Error al subir $filename: código " . $files['error'][$key];
                    error_log("Error upload archivo $filename: " . $files['error'][$key]);
                    continue;
                }

                $tmpName = $files['tmp_name'][$key];
                $size = $files['size'][$key];
                $mimeType = $files['type'][$key];

                // Validar tipo de archivo
                if (!in_array($mimeType, $tiposPermitidos)) {
                    $errores[] = "$filename: tipo de archivo no permitido";
                    error_log("Archivo rechazado por tipo: $filename ($mimeType)");
                    continue;
                }

                // Validar tamaño
                if ($size > $tamañoMaximo) {
                    $errores[] = "$filename: excede el tamaño máximo de 10MB";
                    error_log("Archivo rechazado por tamaño: $filename (" . ($size / 1024 / 1024) . "MB)");
                    continue;
                }

                // Generar nombre único para el archivo
                $ext = pathinfo($filename, PATHINFO_EXTENSION);
                $newFilename = uniqid() . '_' . time() . '_' . $key . '.' . $ext;
                $filepath = $uploadDir . $newFilename;

                error_log("Procesando archivo: $filename");
                error_log("Destino: $filepath");

                // Mover archivo
                if (move_uploaded_file($tmpName, $filepath)) {
                    error_log("Archivo movido exitosamente");

                    // Guardar en base de datos
                    $docResult = $this->addDocumento($clienteId, [
                        'filename' => $filename,
                        'filepath' => $filepath,
                        'tipo' => $tipo,
                        'size' => $size,
                        'mime_type' => $mimeType
                    ]);

                    if ($docResult['success']) {
                        $archivosSubidos[] = [
                            'filename' => $filename,
                            'doc_id' => $docResult['doc_id']
                        ];
                        error_log("Documento guardado en BD: ID " . $docResult['doc_id']);
                    } else {
                        $errores[] = "$filename: error al guardar en base de datos";
                        error_log("Error al guardar en BD: " . $docResult['message']);
                        // Eliminar archivo físico si falló el guardado en BD
                        @unlink($filepath);
                    }
                } else {
                    $errores[] = "$filename: no se pudo mover al directorio de destino";
                    error_log("ERROR: No se pudo mover archivo $filename");
                }
            }

            // Preparar mensaje de resultado
            $totalSubidos = count($archivosSubidos);
            $totalErrores = count($errores);

            if ($totalSubidos > 0 && $totalErrores === 0) {
                $message = "$totalSubidos archivo(s) subido(s) correctamente";
            } elseif ($totalSubidos > 0 && $totalErrores > 0) {
                $message = "$totalSubidos archivo(s) subido(s), $totalErrores error(es)";
            } elseif ($totalSubidos === 0 && $totalErrores > 0) {
                $message = "Error al subir archivos";
            } else {
                $message = "No se procesaron archivos";
            }

            return [
                'success' => $totalSubidos > 0,
                'message' => $message,
                'archivos_subidos' => $archivosSubidos,
                'errores' => $errores,
                'total_subidos' => $totalSubidos,
                'total_errores' => $totalErrores
            ];
        } catch (Exception $e) {
            error_log("Exception en procesarArchivosSubidos: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al procesar archivos: ' . $e->getMessage(),
                'archivos_subidos' => []
            ];
        }
    }
    
    /**
     * Obtener actividad reciente
     */
    public function getRecentActivity($limit = 10)
    {
        try {
            $sql = "
                SELECT 
                    c.id,
                    c.nombres,
                    c.paterno,
                    c.materno,
                    c.rfc,
                    c.fechac,
                    c.fechaedit,
                    CONCAT(c.nombres, ' ', c.paterno, ' ', c.materno) as nombre_completo,
                    uc.name as usuario_creador,
                    ue.name as usuario_editor,
                    CASE 
                        WHEN c.fechaedit > c.fechac THEN 'Modificado'
                        ELSE 'Creado'
                    END as accion
                FROM t_cliente c
                LEFT JOIN users uc ON c.userc = uc.id
                LEFT JOIN users ue ON c.useredit = ue.id
                ORDER BY GREATEST(c.fechac, COALESCE(c.fechaedit, c.fechac)) DESC
                LIMIT :limit
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return [
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];
        } catch (PDOException $e) {
            error_log("Error getting recent activity: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al obtener actividad reciente'
            ];
        }
    }
    
}
