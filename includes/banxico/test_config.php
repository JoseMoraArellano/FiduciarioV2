<?php
echo "<h3>1. Configuración PHP activa:</h3>";
echo "php.ini cargado: " . php_ini_loaded_file() . "<br>";

echo "<h3>2. Extensiones críticas:</h3>";
echo "cURL: " . (extension_loaded('curl') ? '✅' : '❌') . "<br>";
echo "OpenSSL: " . (extension_loaded('openssl') ? '✅' : '❌') . "<br>";

echo "<h3>3. Configuración SSL:</h3>";
echo "curl.cainfo: " . (ini_get('curl.cainfo') ?: '❌ NO CONFIGURADO') . "<br>";
echo "openssl.cafile: " . (ini_get('openssl.cafile') ?: '❌ NO CONFIGURADO') . "<br>";

echo "<h3>4. Versión OpenSSL:</h3>";
echo defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : '❌ No disponible';

echo "<h3>5. Prueba de conexión a Banxico:</h3>";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://www.banxico.org.mx/SieAPIRest/service/v1/series/SF43718/datos/2024-01-01/2024-01-05");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_VERBOSE, true);

$response = curl_exec($ch);
$error = curl_error($ch);
$errno = curl_errno($ch);
$info = curl_getinfo($ch);
curl_close($ch);

if ($error) {
    echo "❌ Error cURL ($errno): $error<br>";
} else {
    echo "✅ Conexión exitosa<br>";
    echo "HTTP Code: " . $info['http_code'] . "<br>";
}