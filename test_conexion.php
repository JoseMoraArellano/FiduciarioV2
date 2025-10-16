<?php
$host = 'localhost';
$port = '5432';
$dbname = 'tes_db';
$usuario = 'postgres';
$password = 'O27j19e07xz';

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $usuario, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Conexión exitosa a PostgreSQL<br>";
    echo "Base de datos: " . $dbname . "<br>";
    echo "Usuario: " . $usuario;
} catch(PDOException $e) {
    echo "❌ Error de conexión: " . $e->getMessage();
}
?>