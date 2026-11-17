<?php
/* KIDAN 72 — Admin: shows a customer's proof of payment.
   The files sit in uploads/payments/, which the public cannot open;
   only logged-in admins see them, through this page. */
require __DIR__ . '/_inc.php';
require_login();
$pdo = db();

$st = $pdo->prepare('SELECT file, mime FROM payment_proofs WHERE id = ?');
$st->execute([(int)($_GET['id'] ?? 0)]);
$p = $st->fetch();
$path = $p ? KIDAN_ROOT . '/uploads/payments/' . basename($p['file']) : '';
if (!$p || !is_file($path)) { http_response_code(404); exit('Not found'); }

header('Content-Type: ' . ($p['mime'] === 'application/pdf' ? 'application/pdf' : 'image/jpeg'));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . basename($p['file']) . '"');
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');
readfile($path);
