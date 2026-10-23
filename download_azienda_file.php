<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) {
    http_response_code(403);
    exit('Non autorizzato.');
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$mode = $_GET['mode'] ?? 'download';

if ($id <= 0) {
    http_response_code(400);
    exit('File non valido.');
}

$stmt = $pdo->prepare('SELECT * FROM aziende_file WHERE id = :id');
$stmt->execute([':id' => $id]);
$file = $stmt->fetch();

if (!$file) {
    http_response_code(404);
    exit('File non trovato.');
}

$path = __DIR__ . '/uploads/aziende/' . (int)$file['azienda_id'] . '/' . $file['nome_salvato'];
if (!is_file($path)) {
    http_response_code(404);
    exit('File non presente su disco.');
}

$mime = $file['mime_type'] ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));

if ($mode === 'download') {
    header('Content-Disposition: attachment; filename="' . basename($file['nome_originale']) . '"');
} else {
    header('Content-Disposition: inline; filename="' . basename($file['nome_originale']) . '"');
}

readfile($path);
exit;
