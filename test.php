<?php
/**
 * Importador de Excel a PostgreSQL
 * - Valida estructura del Excel
 * - No reemplaza datos existentes (solo INSERT)
 * - Requiere PhpSpreadsheet: composer require phpoffice/phpspreadsheet
 */

// CARGAR AUTOLOAD DE COMPOSER desde XAMPP raíz
require 'librerias/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ExcelPostgresImporter {
    private $conn;
    private $tableName;
    private $requiredColumns;
    
    public function __construct($dbConfig, $tableName, $requiredColumns) {
        $this->tableName = $tableName;
        $this->requiredColumns = $requiredColumns;
        
        // Conexión a PostgreSQL
        $connString = sprintf(
            "host=%s port=%s dbname=%s user=%s password=%s",
            $dbConfig['host'],
            $dbConfig['port'],
            $dbConfig['database'],
            $dbConfig['user'],
            $dbConfig['password']
        );
        
        $this->conn = pg_connect($connString);
        
        if (!$this->conn) {
            throw new Exception("Error al conectar con PostgreSQL");
        }
    }
    
    /**
     * Valida que el archivo Excel tenga la estructura correcta
     */
    private function validateStructure($headerRow) {
        $headerColumns = [];
        
        foreach ($headerRow as $cell) {
            if ($cell->getValue() !== null) {
                $headerColumns[] = trim(strtolower($cell->getValue()));
            }
        }
        
        // Verifica que todas las columnas requeridas existan
        foreach ($this->requiredColumns as $required) {
            if (!in_array(strtolower($required), $headerColumns)) {
                throw new Exception("Columna requerida no encontrada: {$required}");
            }
        }
        
        // Verifica que no haya columnas extras
        $extra = array_diff($headerColumns, array_map('strtolower', $this->requiredColumns));
        if (count($extra) > 0) {
            throw new Exception("Columnas no permitidas encontradas: " . implode(', ', $extra));
        }
        
        return $headerColumns;
    }
    
    /**
     * Importa el archivo Excel a la base de datos
     */
    public function import($filePath) {
        $results = [
            'success' => 0,
            'errors' => [],
            'skipped' => 0
        ];
        
        try {
            // Verificar que el archivo existe
            if (!file_exists($filePath)) {
                throw new Exception("El archivo no existe: {$filePath}");
            }
            
            // Verificar que el archivo es legible
            if (!is_readable($filePath)) {
                throw new Exception("El archivo no es legible: {$filePath}");
            }
            
            // Cargar el archivo Excel
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            if (count($rows) < 2) {
                throw new Exception("El archivo debe contener al menos una fila de encabezados y una de datos");
            }
            
            // Validar estructura (primera fila)
            $headerRow = $worksheet->getRowIterator(1)->current()->getCellIterator();
            $headerColumns = $this->validateStructure($headerRow);
            
            // Crear mapeo de columnas
            $columnMap = [];
            foreach ($headerColumns as $index => $column) {
                $columnMap[$column] = $index;
            }
            
            // Iniciar transacción
            pg_query($this->conn, "BEGIN");
            
            // Procesar cada fila (saltar encabezado)
            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                
                // Saltar filas vacías
                if (empty(array_filter($row))) {
                    continue;
                }
                
                try {
                    // Preparar datos para INSERT
                    $columns = [];
                    $values = [];
                    $params = [];
                    $paramCount = 1;
                    
                    foreach ($this->requiredColumns as $column) {
                        $columnLower = strtolower($column);
                        $columnIndex = $columnMap[$columnLower];
                        $value = $row[$columnIndex] ?? null;
                        
                        $columns[] = $column;
                        $values[] = '$' . $paramCount;
                        $params[] = $value;
                        $paramCount++;
                    }
                    
                    // Construir query INSERT
                    $query = sprintf(
                        "INSERT INTO %s (%s) VALUES (%s)",
                        pg_escape_identifier($this->conn, $this->tableName),
                        implode(', ', array_map(function($col) {
                            return pg_escape_identifier($this->conn, $col);
                        }, $columns)),
                        implode(', ', $values)
                    );
                    
                    // Ejecutar INSERT
                    $result = pg_query_params($this->conn, $query, $params);
                    
                    if ($result) {
                        $results['success']++;
                    } else {
                        $error = pg_last_error($this->conn);
                        $results['errors'][] = "Fila " . ($i + 1) . ": " . $error;
                        
                        // Si es error de duplicado, contar como omitido
                        if (strpos($error, 'duplicate key') !== false) {
                            $results['skipped']++;
                        }
                    }
                    
                } catch (Exception $e) {
                    $results['errors'][] = "Fila " . ($i + 1) . ": " . $e->getMessage();
                }
            }
            
            // Confirmar transacción
            pg_query($this->conn, "COMMIT");
            
        } catch (Exception $e) {
            // Revertir en caso de error
            pg_query($this->conn, "ROLLBACK");
            throw $e;
        }
        
        return $results;
    }
    
    public function __destruct() {
        if ($this->conn) {
            pg_close($this->conn);
        }
    }
}

// ============================================
// EJEMPLO DE USO
// ============================================

// Configuración de la base de datos
$dbConfig = [
    'host' => '10.1.1.152',
    'port' => '5432',
    'database' => 'tes_db',
    'user' => 'postgres',
    'password' => 'O27j19e07xz'
];

// Definir tabla y columnas requeridas (debe coincidir exactamente con el Excel)
$tableName = 'empleados';
$requiredColumns = ['id', 'nombre', 'apellido', 'email', 'fecha_ingreso', 'salario'];

// Procesar archivo subido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    
    $uploadedFile = $_FILES['excel_file'];
    
    // Validar tipo de archivo
    $allowedExtensions = ['xlsx', 'xls'];
    $fileExtension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
    
    if (!in_array($fileExtension, $allowedExtensions)) {
        die("Error: Solo se permiten archivos Excel (.xlsx, .xls)");
    }
    
    try {
        $importer = new ExcelPostgresImporter($dbConfig, $tableName, $requiredColumns);
        $results = $importer->import($uploadedFile['tmp_name']);
        
        echo "<h3>Resultado de la importación:</h3>";
        echo "<p>Registros insertados exitosamente: {$results['success']}</p>";
        echo "<p>Registros omitidos (duplicados): {$results['skipped']}</p>";
        
        if (count($results['errors']) > 0) {
            echo "<h4>Errores:</h4>";
            echo "<ul>";
            foreach ($results['errors'] as $error) {
                echo "<li>" . htmlspecialchars($error) . "</li>";
            }
            echo "</ul>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Excel a PostgreSQL</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
        }
        .form-container {
            background: #f5f5f5;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .info-box {
            background: #e3f2fd;
            padding: 15px;
            border-left: 4px solid #2196f3;
            margin-bottom: 20px;
        }
        input[type="file"] {
            margin: 10px 0;
        }
        button {
            background: #4caf50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background: #45a049;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>Importar Excel a PostgreSQL</h2>
        
        <div class="info-box">
            <strong>Estructura requerida del Excel:</strong>
            <p>El archivo debe contener exactamente estas columnas:</p>
            <p><code><?php echo implode(', ', $requiredColumns); ?></code></p>
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <label for="excel_file">Seleccionar archivo Excel:</label><br>
            <input type="file" name="excel_file" id="excel_file" accept=".xlsx,.xls" required>
            <br><br>
            <button type="submit">Importar Datos</button>
        </form>
    </div>
</body>
</html>