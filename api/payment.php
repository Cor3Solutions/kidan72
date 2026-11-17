<?php
/* KIDAN 72 — Proof of payment from the customer (track.html)

   Receives (form upload): order, token or mobile (same check as the
   tracking page), file (photo, screenshot or PDF), ref, amount, note.
   Saves the file where only the admin page can open it, alerts the
   shop by email and text message, and returns the order's proofs. */

require __DIR__ . '/shop.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_out(['error' => 'Method not allowed'], 405);
if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 1024 * 1024) {   // the server dropped an oversized upload
    json_out(['error' => 'That file is too large. Please send a smaller photo or a screenshot.'], 413);
}

try {
    $pdo = db();
    $id = customer_order_id($pdo, (string)($_POST['order'] ?? ''), (string)($_POST['token'] ?? ''), (string)($_POST['mobile'] ?? ''));
    $o = load_order($pdo, $id);

    if (!in_array($o['status'], PROOF_STATUSES, true)) {
        throw new OrderError($o['status'] === 'cancelled'
            ? 'This order was cancelled, so no payment is needed. Please message us if this is a mistake.'
            : 'We already received the payment for this order. Thank you!');
    }
    if (count(payment_proofs($pdo, $id)) >= PROOF_MAX_PER_ORDER) {
        throw new OrderError('We already have ' . PROOF_MAX_PER_ORDER . ' proofs for this order. Please message us if you need to send another.');
    }
    $ip = client_ip();
    $st = $pdo->prepare('SELECT COUNT(*) FROM payment_proofs WHERE ip = ? AND created_at > NOW() - INTERVAL 1 HOUR');
    $st->execute([$ip]);
    if ((int)$st->fetchColumn() >= 10) throw new OrderError('Too many uploads from this connection. Please try again later or message us.', 429);

    $ref = clean_line($_POST['ref'] ?? '', 80);
    $note = clean_line($_POST['note'] ?? '', 255);
    $amountIn = preg_replace('/[^\d.]/', '', str_replace(',', '', (string)($_POST['amount'] ?? '')));
    $amount = ($amountIn !== '' && is_numeric($amountIn) && (float)$amountIn > 0 && (float)$amountIn < 10000000) ? round((float)$amountIn, 2) : null;

    $saved = save_payment_file($_FILES['file'] ?? [], $o['order_no']);
    $pdo->prepare('INSERT INTO payment_proofs (order_id, file, mime, ref, amount, note, ip) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$id, $saved['file'], $saved['mime'], $ref, $amount, $note, $ip]);
    // The reference also goes on the order, so it is ready when the admin marks it paid
    if ($ref !== '' && trim((string)$o['payment_ref']) === '') {
        $pdo->prepare('UPDATE orders SET payment_ref = ? WHERE id = ?')->execute([$ref, $id]);
    }
} catch (OrderError $e) {
    json_out(['error' => $e->getMessage()], $e->getCode() ?: 422);
} catch (Throwable $e) {
    error_log('KIDAN 72 payment proof: ' . $e->getMessage());
    json_out(['error' => 'Your proof could not be saved right now. Please try again, or message us the screenshot.'], 500);
}

// Alerts never block the upload
$proof = ['ref' => $ref, 'amount' => $amount, 'note' => $note];
try { email_shop_payment_proof($pdo, $id, $proof); } catch (Throwable $e) { error_log('KIDAN 72 proof alert: ' . $e->getMessage()); }
try { sms_payment_proof($pdo, $id, $proof); } catch (Throwable $e) { error_log('KIDAN 72 proof SMS: ' . $e->getMessage()); }

$num = fn($v) => $v === null ? null : (float)$v;
$proofs = payment_proofs($pdo, $id);
json_out([
    'ok' => true,
    'proofs' => array_map(fn($p) => ['at' => date('M j, Y g:i A', strtotime($p['created_at'])), 'ref' => $p['ref'], 'amount' => $num($p['amount'])], $proofs),
    'canUpload' => count($proofs) < PROOF_MAX_PER_ORDER,
]);
