<?php
require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/Session.php';
require_once '../../includes/ClienteManager.php';

$session = new Session();

if (!$session->isLoggedIn()) {
    die('Acceso no autorizado');
}

$docId = (int)($_GET['id'] ?? 0);

if ($docId <= 0) {
    die('ID de documento inválido');
}

$clienteManager = new ClienteManager();
$db = Database::getInstance()->getConnection();

$sql = "SELECT * FROM t_cliente_docs WHERE id = :id";
$stmt = $db->prepare($sql);
$stmt->execute(['id' => $docId]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doc) {
    die('Documento no encontrado');
}

if (!file_exists($doc['filepath'])) {
    die('Archivo no encontrado en el servidor');
}

header('Content-Type: ' . $doc['mime_type']);
header('Content-Disposition: attachment; filename="' . $doc['filename'] . '"');
header('Content-Length: ' . filesize($doc['filepath']));
readfile($doc['filepath']);
exit;