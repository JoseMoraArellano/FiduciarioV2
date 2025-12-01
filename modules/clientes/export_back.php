
<?php
/**
 * Exportación de clientes a Excel
 */

require_once 'includes/cliente.manager.php';
require_once 'modules/clientes/permissions.php';

// Verificar permisos
if (!$clientePermissions->canExport()) {
    header('HTTP/1.0 403 Forbidden');
    echo 'No tiene permisos para exportar clientes';
    exit;
}

// Verificar que sea una solicitud GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('HTTP/1.0 405 Method Not Allowed');
    exit;
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
    
    // Ejecutar exportación
    $result = $clientesManager->exportToExcel($filters);
    
    if (!$result['success']) {
        throw new Exception($result['message']);
    }
    
    $data = $result['data'];
    $filename = $result['filename'];
    
    // Configurar headers para descarga
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    
    // Crear archivo Excel usando PhpSpreadsheet (requiere instalación)
    // Si no tienes PhpSpreadsheet, usaremos CSV como alternativa
    exportToCSV($data, $filename);
    
} catch (Exception $e) {
    error_log("Error en exportación: " . $e->getMessage());
    
    // Redirigir con mensaje de error
    $_SESSION['error'] = 'Error al exportar: ' . $e->getMessage();
    header('Location: catalogos.php?mod=clientes&action=list');
    exit;
}

/**
 * Exportar a CSV (alternativa si no hay PhpSpreadsheet)
 */
function exportToCSV($data, $filename) {
    $output = fopen('php://output', 'w');
    
    // Agregar BOM para UTF-8 (para Excel)
    fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));
    
    foreach ($data as $row) {
        // Convertir array a CSV
        fputcsv($output, $row, ',', '"');
    }
    
    fclose($output);
    exit;
}

/**
 * Exportar a Excel usando PhpSpreadsheet (requiere composer require phpoffice/phpspreadsheet)
 */
function exportToExcelWithPhpSpreadsheet($data, $filename) {
    require_once 'vendor/autoload.php';
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Agregar datos
    $sheet->fromArray($data, null, 'A1');
    
    // Autoajustar columnas
    foreach (range('A', $sheet->getHighestDataColumn()) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Estilo para headers
    $headerStyle = [
        'font' => ['bold' => true],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'E6E6FA']
        ]
    ];
    
    $sheet->getStyle('A1:' . $sheet->getHighestDataColumn() . '1')->applyFromArray($headerStyle);
    
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
?>