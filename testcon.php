<?php
$host = "10.1.1.152";
$port = "5432";       
$dbname = "tes_db";  
$user = "postgres";       
$pass = "O27j19e07xz";       

$dsn = "pgsql:host=$host;port=$port;dbname=$dbname";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    echo "Conexión exitosa a PostgreSQL";
} catch (PDOException $e) {
    echo "Error de conexión: " . $e->getMessage();
}
