<?php
/* KIDAN 72 — Order status for the customer (track.html)

   Either the private link from the confirmation (order no. + token),
   or the order no. + the mobile number used for the order.
   Returns only what the customer already knows: their items, totals,
   status history, the payment instructions and the payment proofs
   they sent (date, reference, amount; never the picture). */

require __DIR__ . '/shop.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_out(['error' => 'Method not allowed'], 405);
$in = json_decode((string)file_get_contents('php://input', false, null, 0, 4096), true);
if (!is_array($in)) json_out(['error' => 'Invalid request.'], 400);

try {
    $pdo = db();
    $id = customer_order_id($pdo, (string)($in['order'] ?? ''), (string)($in['token'] ?? ''), (string)($in['mobile'] ?? ''));
    $o = load_order($pdo, $id);
    $st = $pdo->prepare('SELECT status, created_at FROM order_status_log WHERE order_id = ? ORDER BY created_at, id');
    $st->execute([$o['id']]);
    $history = [];
    foreach ($st as $h) $history[] = ['status' => $h['status'], 'label' => ORDER_STATUSES[$h['status']] ?? $h['status'], 'at' => date('M j, Y g:i A', strtotime($h['created_at']))];

    $num = fn($v) => $v === null ? null : (float)$v;
    $proofs = payment_proofs($pdo, $id);
    json_out([
        'orderNo'  => $o['order_no'],
        'placed'   => date('F j, Y g:i A', strtotime($o['created_at'])),
        'status'   => $o['status'],
        'label'    => ORDER_STATUSES[$o['status']] ?? $o['status'],
        'name'     => $o['customer'],
        'address'  => full_address_text($o),
        'items'    => array_map(fn($l) => ['serial' => $l['serial'], 'name' => $l['name'], 'qty' => (int)$l['qty'], 'lineTotal' => $num($l['line_total'])], $o['lines']),
        'subtotal' => $num($o['subtotal']),
        'deliveryFee' => (float)$o['delivery_fee'],
        'discount' => (float)$o['discount'],
        'total'    => $num($o['total']),
        'payment'  => in_array($o['status'], ['confirmed'], true) ? trim(setting('payment_instructions')) : '',
        'paymentRef' => $o['payment_ref'],
        'history'  => $history,
        'proofs'   => array_map(fn($p) => ['at' => date('M j, Y g:i A', strtotime($p['created_at'])), 'ref' => $p['ref'], 'amount' => $num($p['amount'])], $proofs),
        'canUpload' => in_array($o['status'], PROOF_STATUSES, true) && count($proofs) < PROOF_MAX_PER_ORDER,
    ]);
} catch (OrderError $e) {
    json_out(['error' => $e->getMessage()], $e->getCode() ?: 422);
} catch (Throwable $e) {
    error_log('KIDAN 72 track: ' . $e->getMessage());
    json_out(['error' => 'Order status is not available right now.'], 500);
}
