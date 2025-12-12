<?php
require_once 'config.php';
require_once 'includes/Database.php';
require_once 'includes/Session.php';
require_once 'includes/Permissions.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug - Permisos Usuario</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .section { background: white; padding: 20px; margin: 10px 0; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .info { color: blue; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
        th { background: #f0f0f0; }
        pre { background: #f8f8f8; padding: 10px; border-radius: 3px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔍 Diagnóstico de Permisos - Artículo 69-B</h1>

<?php
try {
    echo '<div class="section">';
    echo '<h2>1. Información de Sesión</h2>';
    
    $session = new Session();
    $userId = $session->getUserId();
    $isAdmin = $session->isAdmin();
    
    echo '<table>';
    echo '<tr><th>Propiedad</th><th>Valor</th></tr>';
    echo '<tr><td>User ID</td><td class="' . ($userId ? 'success' : 'error') . '">' . ($userId ?: 'NO DETECTADO') . '</td></tr>';
    echo '<tr><td>Es Admin</td><td class="' . ($isAdmin ? 'success' : 'info') . '">' . ($isAdmin ? 'SÍ ✓' : 'NO') . '</td></tr>';
    echo '<tr><td>Session ID</td><td>' . session_id() . '</td></tr>';
    echo '</table>';
    
    echo '<h3>Variables de Sesión:</h3>';
    echo '<pre>' . print_r($_SESSION, true) . '</pre>';
    echo '</div>';

    echo '<div class="section">';
    echo '<h2>2. Verificación de Permisos</h2>';
    
    $permissions = new Permissions();
    
    $permisos = [
        'lire' => 'Ver/Leer',
        'creer' => 'Crear',
        'modifier' => 'Modificar',
        'supprimer' => 'Eliminar'
    ];
    
    echo '<table>';
    echo '<tr><th>Permiso</th><th>Método 1: hasPermission</th><th>Método 2: session->hasPermission</th></tr>';
    
    foreach ($permisos as $key => $label) {
        $metodo1 = $permissions->hasPermission($userId, 'articulo_69b', $key);
        $metodo2 = $session->hasPermission('catalogos', $key, 'articulo_69b');
        
        $resultado1 = $metodo1 ? '<span class="success">✓ SÍ</span>' : '<span class="error">✗ NO</span>';
        $resultado2 = $metodo2 ? '<span class="success">✓ SÍ</span>' : '<span class="error">✗ NO</span>';
        
        echo "<tr><td><strong>$label ($key)</strong></td><td>$resultado1</td><td>$resultado2</td></tr>";
    }
    echo '</table>';
    echo '</div>';

    echo '<div class="section">';
    echo '<h2>3. Verificación de Permiso CREAR (import_excel.php)</h2>';
    
    $canCreate = $isAdmin 
        || $permissions->hasPermission($userId, 'articulo_69b', 'creer')
        || $session->hasPermission('catalogos', 'creer', 'articulo_69b');
    
    if ($canCreate) {
        echo '<p class="success">✓ TIENES PERMISO PARA IMPORTAR</p>';
        echo '<p>Puedes usar la función de importar Excel.</p>';
    } else {
        echo '<p class="error">✗ NO TIENES PERMISO PARA IMPORTAR</p>';
        echo '<p>Necesitas uno de los siguientes permisos:</p>';
        echo '<ul>';
        echo '<li>Ser administrador del sistema</li>';
        echo '<li>Tener permiso "creer" en el módulo "articulo_69b"</li>';
        echo '<li>Tener permiso "creer" en catalogos/articulo_69b</li>';
        echo '</ul>';
    }
    echo '</div>';

    echo '<div class="section">';
    echo '<h2>4. Consulta Directa a Base de Datos</h2>';
    
    $db = Database::getInstance()->getConnection();
    
    // Verificar tabla de permisos
    echo '<h3>Permisos del usuario en la BD:</h3>';
    
    $queries = [
        "SELECT * FROM t_user_permissions WHERE user_id = ? AND module_name LIKE '%69b%'",
        "SELECT * FROM t_permissions WHERE user_id = ? AND resource LIKE '%69b%'",
        "SELECT * FROM t_role_permissions rp 
         INNER JOIN t_user_roles ur ON rp.role_id = ur.role_id 
         WHERE ur.user_id = ? AND rp.module LIKE '%69b%'"
    ];
    
    foreach ($queries as $index => $query) {
        try {
            $stmt = $db->prepare($query);
            $stmt->execute([$userId]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<h4>Query " . ($index + 1) . ":</h4>";
            echo "<pre>$query</pre>";
            
            if (!empty($results)) {
                echo '<table>';
                echo '<tr>';
                foreach (array_keys($results[0]) as $col) {
                    echo "<th>$col</th>";
                }
                echo '</tr>';
                
                foreach ($results as $row) {
                    echo '<tr>';
                    foreach ($row as $val) {
                        echo '<td>' . htmlspecialchars($val ?? '') . '</td>';
                    }
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<p class="info">No se encontraron resultados</p>';
            }
        } catch (Exception $e) {
            echo '<p class="error">Error en query: ' . $e->getMessage() . '</p>';
        }
    }
    
    echo '</div>';

    echo '<div class="section">';
    echo '<h2>5. Información del Sistema</h2>';
    echo '<table>';
    echo '<tr><td>PHP Version</td><td>' . phpversion() . '</td></tr>';
    echo '<tr><td>Usuario conectado</td><td>' . ($_SESSION['username'] ?? 'N/A') . '</td></tr>';
    echo '<tr><td>Rol del usuario</td><td>' . ($_SESSION['user_role'] ?? $_SESSION['role'] ?? 'N/A') . '</td></tr>';
    echo '</table>';
    echo '</div>';

} catch (Exception $e) {
    echo '<div class="section">';
    echo '<h2 class="error">Error:</h2>';
    echo '<p>' . $e->getMessage() . '</p>';
    echo '<pre>' . $e->getTraceAsString() . '</pre>';
    echo '</div>';
}
?>

    <div class="section">
        <h2>6. Soluciones Posibles</h2>
        <ol>
            <li><strong>Si eres administrador:</strong> Verifica que tu sesión tenga correctamente el flag de administrador</li>
            <li><strong>Si no eres admin:</strong> Solicita al administrador que te otorgue el permiso "creer" para el módulo "articulo_69b"</li>
            <li><strong>Verificar en list.php:</strong> El botón de "Importar Excel" solo aparece si <code>$canCreate</code> es verdadero</li>
            <li><strong>Solución temporal:</strong> Puedes comentar la verificación de permisos en <code>import_excel.php</code> (líneas 24-29) SOLO PARA PRUEBAS</li>
        </ol>
    </div>

    <div class="section">
        <p><a href="../../modules/articulo_69b/list.php" style="padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;">← Volver al módulo</a></p>
    </div>

</body>
</html>