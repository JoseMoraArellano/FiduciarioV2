<?php
/**
 * Exportación de clientes a Excel/CSV
 * Este archivo se llama directamente desde el listado
 */

require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/Session.php';
require_once 'includes/cliente.manager.php';
require_once 'permissions.php';

$session = new Session();

// Verificar autenticación
if (!$session->isLoggedIn()) {
    header('HTTP/1.0 401 Unauthorized');
    die('Sesión expirada');
}

// Verificar permisos de exportación
if (!$session->isAdmin() && !$session->hasPermission('catalogos', 'export', 'clientes')) {
    header('HTTP/1.0 403 Forbidden');
    die('No tienes permisos para exportar clientes');
}

try {
    $clientesManager = new ClientesManager();
    
    // Obtener filtros de la URL
    $filters = [
        'search' => $_GET['search'] ?? '',
        'activo' => $_GET['activo'] ?? '',
        'tipo_persona' => $_GET['tipo_persona'] ?? '',
        'regimen_fiscal' => $_GET['regimen_fiscal'] ?? '',
        'edo' => $_GET['edo'] ?? '',
        'altoriesg' => $_GET['altoriesg'] ?? '',
        'fecha_desde' => $_GET['fecha_desde'] ?? '',
        'fecha_hasta' => $_GET['fecha_hasta'] ?? ''
    ];
    
    // Determinar formato (Excel o CSV)
    $format = $_GET['format'] ?? 'excel';
    
    if ($format === 'csv') {
        // Exportar a CSV
        $result = $clientesManager->exportToCSV($filters);
        
        if (!$result['success']) {
            throw new Exception($result['message']);
        }
        
        $filename = 'clientes_' . date('Y-m-d_His') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // BOM para Excel
        echo "\xEF\xBB\xBF";
        echo $result['data'];
        
    } else {
        // Exportar a Excel (formato array)
        $result = $clientesManager->exportToExcel($filters);
        
        if (!$result['success']) {
            throw new Exception($result['message']);
        }
        
        $data = $result['data'];
        $filename = $result['filename'];
        
        // Usar CSV como alternativa (puedes implementar PhpSpreadsheet después)
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // BOM para Excel
        echo "\xEF\xBB\xBF";
        
        $output = fopen('php://output', 'w');
        foreach ($data as $row) {
            fputcsv($output, $row, ',', '"');
        }
        fclose($output);
    }
    
} catch (Exception $e) {
    error_log("Error en exportación: " . $e->getMessage());
    
    // Redirigir con mensaje de error
    header('Location: ../../catalogos.php?mod=clientes&action=list&error=' . urlencode('Error al exportar: ' . $e->getMessage()));
    exit;
}
?>