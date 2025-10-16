<?php
// Tus credenciales
$client_id = "201756-1950-8760";
$secret_id = "CCT80j2SbmB9AzKCOigz8OCr8zEnz22x94pLFrmMLetXed3wfgbTfNSul8jA3QBUkd1yrBEtKU9wyYJXrNg3pPn3nQYFddXeeu9EmAlxQFWLijehNwwf6McxMAfcqegP";
$username = "Afianzadora_02"; // Usuario que hace la consulta
$name = "JOSE MORA ARELLANO"; // Nombre a buscar
$rfc = "MOAJ89011IP5"; // RFC opcional

// --------------------------
// Paso 1: Generar token
// --------------------------
$tokenUrl = "https://app.q-detect.com/api/token?client_id={$client_id}&type=token";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $tokenUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $secret_id",
]);

$response = curl_exec($ch);
if(curl_errno($ch)){
    die("Error en cURL: " . curl_error($ch));
}
curl_close($ch);

$tokenData = json_decode($response, true);
if(!isset($tokenData['access_token'])){
    die("No se pudo obtener token: " . $response);
}
$access_token = $tokenData['access_token'];

// --------------------------
// Paso 2: Consumir API find
// --------------------------
$findUrl = "https://app.q-detect.com/api/find?client_id={$client_id}&username={$username}";
if(!empty($name)) $findUrl .= "&name=" . urlencode($name);
if(!empty($rfc)) $findUrl .= "&rfc=" . urlencode($rfc);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $findUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $access_token",
]);

$response = curl_exec($ch);
if(curl_errno($ch)){
    die("Error en cURL: " . curl_error($ch));
}
curl_close($ch);

$result = json_decode($response, true);
echo "<pre>";
print_r($result);
echo "</pre>";
