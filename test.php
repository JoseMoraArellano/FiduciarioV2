<?php
$host = '10.1.1.152'; // Ejemplo: '192.168.1.5'
$port = '5432';
$dbname = 'tes_db';
$user = 'postgres';
$password = 'O27j19e07xz';

$connection_string = "host=$host port=$port dbname=$dbname user=$user password=$password";

$dbconn = pg_connect($connection_string);

if (!$dbconn) {
    die("Error al conectar a la base de datos: " . pg_last_error());
}

echo "Conexión exitosa a la base de datos remota!";
// ... el resto de tu código ...
pg_close($dbconn);
?>