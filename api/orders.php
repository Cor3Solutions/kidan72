<?php
/* KIDAN 72 — Saves an order from the My Order page.

   Receives:  { customer: {name, mobile, email, province, city, barangay, street,
                           address, notes, website}, items: [{serial, qty, choice?}], channel }
   Returns:   { orderNo: "K72-0001", trackUrl: "...", emailed: true|false }

   Prices and names always come from the database, never from the
   browser, so nobody can change a price by editing the page.
   The customer gets a confirmation email (if they gave an email)
   and the shop gets an alert by email and text message. */

require __DIR__ . '/shop.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_out(['error' => 'Method not allowed'], 405);

$body = json_decode((string)file_get_contents('php://input', false, null, 0, 65536), true);
if (!is_array($body)) json_out(['error' => 'Invalid order.'], 400);

$c = is_array($body['customer'] ?? null) ? $body['customer'] : [];

// Hidden "website" box: people never see it, spam robots fill it in
if (trim((string)($c['website'] ?? '')) !== '') json_out(['error' => 'Your order could not be sent. Please message us directly.'], 422);

$items = [];
foreach ((array)($body['items'] ?? []) as $l) {
    if (!is_array($l)) json_out(['error' => 'Invalid order.'], 400);
    $sn = strtoupper(trim((string)($l['serial'] ?? '')));
    $pick = trim((string)($l['choice'] ?? ''));   // e.g. pouch colour
    if ($pick !== '') $sn .= '|' . $pick;
    $items[$sn] = ($items[$sn] ?? 0) + (int)($l['qty'] ?? 0);
    if ((int)($l['qty'] ?? 0) < 1) json_out(['error' => 'Your order list has an item we do not recognise.'], 422);
}
if (!$items) json_out(['error' => 'Your order list is empty.'], 422);

try {
    $pdo = db();

    // Simple flood protection: at most 15 orders per hour from one connection
    $ip = client_ip();
    $st = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE ip = ? AND created_at > NOW() - INTERVAL 1 HOUR');
    $st->execute([$ip]);
    if ((int)$st->fetchColumn() >= 15) json_out(['error' => 'Too many orders from this connection. Please message us directly.'], 429);

    $channel = in_array($body['channel'] ?? '', ['website', 'messenger', 'email', 'viber', 'copy'], true) ? $body['channel'] : '';
    $order = kidan_create_order($pdo, [
        'name' => $c['name'] ?? '', 'mobile' => $c['mobile'] ?? '', 'email' => $c['email'] ?? '',
        'province' => $c['province'] ?? '', 'city' => $c['city'] ?? '', 'barangay' => $c['barangay'] ?? '',
        'street' => $c['street'] ?? '', 'address' => $c['address'] ?? '', 'notes' => $c['notes'] ?? '',
        'channel' => $channel, 'items' => $items, 'ip' => $ip, 'by' => 'Website',
    ]);
} catch (OrderError $e) {
    json_out(['error' => $e->getMessage()], 422);
} catch (Throwable $e) {
    error_log('KIDAN 72 order: ' . $e->getMessage());
    json_out(['error' => 'We could not record your order right now.'], 500);
}

// Emails never block the order
$emailed = false;
try { email_shop_new_order($pdo, $order['id']); } catch (Throwable $e) { error_log('KIDAN 72 order alert: ' . $e->getMessage()); }
try { $emailed = email_customer($pdo, $order['id'], 'new'); } catch (Throwable $e) { error_log('KIDAN 72 customer email: ' . $e->getMessage()); }
try { sms_new_order($pdo, $order['id']); } catch (Throwable $e) { error_log('KIDAN 72 order SMS: ' . $e->getMessage()); }

json_out([
    'orderNo'  => $order['order_no'],
    'trackUrl' => 'track.html?o=' . rawurlencode($order['order_no']) . '&t=' . rawurlencode($order['token']),
    'emailed'  => $emailed,
]);
