<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
header('Content-Type: application/json; charset=utf-8');
if (!isLoggedIn()) { http_response_code(401); echo json_encode(['error' => 'Accesso richiesto.']); exit; }
$query = trim((string)($_GET['q'] ?? ''));
if (!preg_match('/^\d{2,11}$/', $query)) { echo '[]'; exit; }
$stmt = $pdo->prepare('SELECT id, partita_iva, ragione_sociale FROM aziende WHERE partita_iva LIKE :prefix ORDER BY partita_iva, ragione_sociale LIMIT 10');
$stmt->execute([':prefix' => $query . '%']);
echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
