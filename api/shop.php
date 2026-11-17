<?php
/* =========================================================
   KIDAN 72 — Orders: creating them, their status history and
   the emails that go to the shop and to the customer.
   Used by api/orders.php (website) and the admin page
   (orders taken by phone / Messenger / walk-in).
   ========================================================= */

declare(strict_types=1);

require_once __DIR__ . '/lib.php';

const ORDER_STATUSES = ['new' => 'Received', 'confirmed' => 'Confirmed', 'paid' => 'Paid', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'];
const ORDER_CHANNELS = ['website' => 'Website order form', 'messenger' => 'Website · Messenger', 'email' => 'Website · email', 'viber' => 'Website · Viber', 'copy' => 'Website · copied',
                        'phone' => 'Phone call', 'fb' => 'Facebook / Messenger chat', 'walkin' => 'Walk-in', 'event' => 'Event / booth', 'other' => 'Other'];

class OrderError extends RuntimeException {}

/* One line of text: no control characters or line breaks (safe in email headers) */
function clean_line($v, int $max): string {
    $v = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string)($v ?? '')) ?? '';
    return mb_substr(trim(preg_replace('/\s{2,}/u', ' ', $v) ?? ''), 0, $max);
}
/* Several lines of text (notes) */
function clean_text($v, int $max): string {
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string)($v ?? '')) ?? '';
    return mb_substr(trim($v), 0, $max);
}

function peso_text($v): string {
    return $v === null ? 'to be confirmed' : 'PHP ' . number_format((float)$v, (floor((float)$v) == (float)$v) ? 0 : 2);
}

/* Creates an order. $in: name, mobile, email, province, city, barangay, street, address,
   notes, channel, items [serial => qty], and optionally status, delivery_fee, discount,
   payment_method, payment_ref, admin_note, ip, by (who entered it).
   $allowSoldOut: admins may still record an order for an item marked sold out. */
function kidan_create_order(PDO $pdo, array $in, bool $allowSoldOut = false): array {
    $name = clean_line($in['name'] ?? '', 120);
    $mobile = clean_line($in['mobile'] ?? '', 40);
    $email = clean_line($in['email'] ?? '', 160);
    $province = clean_line($in['province'] ?? '', 80);
    $city = clean_line($in['city'] ?? '', 80);
    $barangay = clean_line($in['barangay'] ?? '', 120);
    $street = clean_line($in['street'] ?? '', 255);
    $address = clean_line($in['address'] ?? '', 255);
    if ($province !== '') $address = full_address_text(compact('street', 'barangay', 'city', 'province'));
    $notes = clean_text($in['notes'] ?? '', 2000);
    $channel = isset(ORDER_CHANNELS[$in['channel'] ?? '']) ? $in['channel'] : '';
    $status = isset(ORDER_STATUSES[$in['status'] ?? '']) ? $in['status'] : 'new';

    if ($name === '' || $mobile === '') throw new OrderError('Please enter your name and mobile number.');
    if (mobile_key($mobile) === null) throw new OrderError('Please check your mobile number.');
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new OrderError('Please check your email address.');

    // Keys are a serial number, or serial|choice for a product with a
    // choice (e.g. "K72-PCH-001|Brown" for a brown pouch)
    $want = [];
    foreach ((array)($in['items'] ?? []) as $key => $qty) {
        [$sn, $pick] = array_pad(explode('|', (string)$key, 2), 2, '');
        $sn = strtoupper(trim($sn)); $pick = trim($pick); $qty = (int)$qty;
        if (!preg_match('/^K72-[A-Z]{3}-\d{3}$/', $sn) || $qty < 0 || $qty > 999 || mb_strlen($pick) > 40) throw new OrderError('Your order list has an item we do not recognise.');
        $k = $pick === '' ? $sn : "$sn|$pick";
        if ($qty > 0) $want[$k] = min(999, ($want[$k] ?? 0) + $qty);
    }
    if (!$want || count($want) > 100) throw new OrderError('The order list is empty.');

    $serials = array_values(array_unique(array_map(fn($k) => explode('|', $k)[0], array_keys($want))));
    $in_ = implode(',', array_fill(0, count($serials), '?'));
    $st = $pdo->prepare("SELECT serial, name, price, sold_separately, sold_out, choice FROM products WHERE serial IN ($in_)");
    $st->execute($serials);
    $found = [];
    foreach ($st as $r) $found[$r['serial']] = $r;

    $rows = []; $total = 0.0; $allPriced = true; $count = 0;
    foreach ($want as $key => $qty) {
        [$sn, $pick] = array_pad(explode('|', $key, 2), 2, '');
        $p = $found[$sn] ?? null;
        if (!$p || !$p['sold_separately']) throw new OrderError("Item $sn is not available on its own.");
        if (!empty($p['sold_out']) && !$allowSoldOut) throw new OrderError($p['name'] . ' is sold out. Please remove it from your list.');
        $choice = product_choice($p['choice'] ?? null);
        $picked = '';
        if ($choice) {
            // match the choice whatever the capital letters; a missing one is asked for
            $match = array_values(array_filter($choice['values'], fn($v) => strcasecmp($v, $pick) === 0));
            if (!$match) throw new OrderError('Please choose the ' . mb_strtolower($choice['label']) . ' for the ' . $p['name'] . '.');
            $p['name'] .= ' (' . $match[0] . ')';
            $picked = $match[0];
        } elseif ($pick !== '') {
            throw new OrderError('Your order list has an item we do not recognise.');
        }
        $unit = price_value($p['price']);
        if ($unit === null || $unit <= 0) { $unit = null; $allPriced = false; }
        $lineTotal = $unit === null ? null : round($unit * $qty, 2);
        if ($lineTotal !== null) $total += $lineTotal;
        $count += $qty;
        $rows[] = [$sn, $p['name'], $picked, $qty, $unit, $lineTotal];
    }
    // Enough stock? (website orders; the admin may record an order anyway)
    require_once __DIR__ . '/stock.php';
    if (!$allowSoldOut) {
        $short = stock_check_order($pdo, $want);
        if ($short !== '') throw new OrderError($short);
    }
    $subtotal = $allPriced ? round($total, 2) : null;
    $fee = max(0, (float)($in['delivery_fee'] ?? 0));
    $discount = max(0, (float)($in['discount'] ?? 0));
    $grand = $subtotal === null ? null : max(0, round($subtotal + $fee - $discount, 2));

    $pdo->beginTransaction();
    try {
        $customerId = kidan_customer_for_order($pdo, $name, $mobile, compact('province', 'city', 'barangay', 'street') + ($province === '' ? ['street' => $address] : []));
        if ($email !== '') $pdo->prepare('UPDATE customers SET email = ? WHERE id = ?')->execute([$email, $customerId]);
        $token = bin2hex(random_bytes(16));
        $pdo->prepare('INSERT INTO orders (customer_id, customer, mobile, email, address, province, city, barangay, street, notes, channel, status, item_count,
                                           subtotal, delivery_fee, discount, total, payment_method, payment_ref, admin_note, ip, track_token)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$customerId, $name, $mobile, $email, $address, $province, $city, $barangay, $street, $notes, $channel, $status, $count,
                       $subtotal, $fee, $discount, $grand, clean_line($in['payment_method'] ?? '', 40), clean_line($in['payment_ref'] ?? '', 80),
                       clean_text($in['admin_note'] ?? '', 5000), substr((string)($in['ip'] ?? ''), 0, 45), $token]);
        $id = (int)$pdo->lastInsertId();
        $orderNo = sprintf('K72-%04d', $id);
        $pdo->prepare('UPDATE orders SET order_no = ? WHERE id = ?')->execute([$orderNo, $id]);
        $st = $pdo->prepare('INSERT INTO order_items (order_id, serial, name, choice, qty, unit_price, line_total) VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($rows as $r) $st->execute([$id, ...$r]);
        log_order_status($pdo, $id, $status, (string)($in['by'] ?? ''));
        $stock = stock_sync_order($pdo, $id, (string)($in['by'] ?? ''));   // an order recorded as Confirmed / Paid takes its stock now
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return ['id' => $id, 'order_no' => $orderNo, 'token' => $token, 'stock' => $stock];
}

function full_address_text(array $r): string {
    $b = trim((string)($r['barangay'] ?? ''));
    if ($b !== '' && !preg_match('/^(barangay|brgy|poblacion)\b/i', $b)) $b = 'Brgy. ' . $b;
    $parts = array_filter([trim((string)($r['street'] ?? '')), $b, trim((string)($r['city'] ?? '')), trim((string)($r['province'] ?? ''))], fn($s) => $s !== '');
    return $parts ? implode(', ', $parts) : trim((string)($r['address'] ?? ''));
}

function log_order_status(PDO $pdo, int $orderId, string $status, string $by = ''): void {
    $pdo->prepare('INSERT INTO order_status_log (order_id, status, by_name) VALUES (?, ?, ?)')->execute([$orderId, $status, mb_substr($by, 0, 80)]);
}

function load_order(PDO $pdo, int $id): ?array {
    $st = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
    $st->execute([$id]);
    $o = $st->fetch();
    if (!$o) return null;
    $st = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id');
    $st->execute([$id]);
    $o['lines'] = $st->fetchAll();
    return $o;
}

function track_url(array $o): string {
    return site_base_url() . 'track.html?o=' . rawurlencode((string)$o['order_no']) . '&t=' . rawurlencode((string)$o['track_token']);
}

function order_lines_text(array $o): string {
    $out = [];
    foreach ($o['lines'] as $l) $out[] = sprintf('- %d x %s (%s)%s', $l['qty'], $l['name'], $l['serial'], $l['line_total'] === null ? '' : '  ' . peso_text($l['line_total']));
    return implode("\n", $out);
}

function order_totals_text(array $o): string {
    $t = 'Items: ' . peso_text($o['subtotal']) . "\n";
    if ((float)$o['delivery_fee'] > 0) $t .= 'Delivery fee: ' . peso_text($o['delivery_fee']) . "\n";
    if ((float)$o['discount'] > 0) $t .= 'Discount: -' . peso_text($o['discount']) . "\n";
    $t .= 'Total: ' . peso_text($o['total']);
    return $t;
}

function shop_signature(): string {
    $lines = ["KIDAN 72 · Always Ready."];
    if (($m = setting('contact_messenger')) !== '') $lines[] = 'Messenger: https://m.me/' . $m;
    if (($e = setting('contact_email')) !== '') $lines[] = 'Email: ' . $e;
    return implode("\n", $lines);
}

/* Email to the shop for every new order */
function email_shop_new_order(PDO $pdo, int $id): void {
    $to = setting('order_alert_email');
    if ($to === '' || !($o = load_order($pdo, $id))) return;
    $body = "New order" . (str_contains((string)$o['channel'], 'phone') ? '' : ' from the KIDAN 72 website') . ".\n\n"
          . "Order no.: {$o['order_no']}\nName: {$o['customer']}\nMobile: {$o['mobile']}\n"
          . ($o['email'] !== '' ? "Email: {$o['email']}\n" : '')
          . (full_address_text($o) !== '' ? 'Address: ' . full_address_text($o) . "\n" : '')
          . ($o['notes'] !== '' ? "Notes: {$o['notes']}\n" : '')
          . 'Sent by: ' . (ORDER_CHANNELS[$o['channel']] ?? '-') . "\n\n" . order_lines_text($o) . "\n\n" . order_totals_text($o) . "\n\n"
          . 'Open the order: ' . site_base_url() . "admin/order.php?id=$id\n";
    send_mail($to, "New KIDAN 72 order {$o['order_no']} - {$o['customer']}", $body, '', $id);
}

/* Email to the customer: right after ordering, and when the status changes */
function email_customer(PDO $pdo, int $id, string $event): bool {
    $o = load_order($pdo, $id);
    if (!$o || $o['email'] === '') return false;
    $first = trim(explode(' ', trim($o['customer']))[0] ?? '');
    $hi = 'Hi' . ($first !== '' ? " $first" : '') . ",\n\n";
    $pay = trim(setting('payment_instructions'));
    $track = "Check your order any time:\n" . track_url($o);
    $no = $o['order_no'];

    switch ($event) {
        case 'new':
            $subject = "We received your KIDAN 72 order $no";
            $body = $hi . "Thank you for your order! This email confirms we received it.\n\n"
                  . "Order no.: $no\n" . order_lines_text($o) . "\n\n" . order_totals_text($o) . "\n"
                  . (full_address_text($o) !== '' ? 'Deliver to: ' . full_address_text($o) . "\n" : '')
                  . "\nWHAT HAPPENS NEXT\n1. We check stock and work out the delivery fee.\n2. We message you to confirm, with the total and how to pay (GCash or bank transfer).\n3. After payment we pack and send your kit.\n\n$track";
            break;
        case 'confirmed':
            $subject = "Your KIDAN 72 order $no is confirmed";
            $body = $hi . "Good news: your order $no is confirmed and your items are reserved.\n\n" . order_lines_text($o) . "\n\n" . order_totals_text($o) . "\n\n"
                  . ($pay !== '' ? "HOW TO PAY\n$pay\n\nAfter paying, upload a screenshot of your payment on your order page (link below), or reply to this email with your reference number.\n\n" : "We will message you with the payment details.\n\n") . $track;
            break;
        case 'paid':
            $subject = "Payment received for KIDAN 72 order $no";
            $body = $hi . "We received your payment" . ($o['payment_ref'] !== '' ? " (reference {$o['payment_ref']})" : '') . " for order $no. Thank you!\n\n"
                  . "We are now preparing your kit for delivery" . (full_address_text($o) !== '' ? ' to ' . full_address_text($o) : '') . ".\n\n$track";
            break;
        case 'delivered':
            $subject = "Your KIDAN 72 order $no was delivered";
            $body = $hi . "Your order $no has been delivered. Salamat po!\n\n"
                  . "Next step: learn to use your kit. Short videos for every item and the training modules are here:\n" . site_base_url() . "training.html\n\n"
                  . "Check your bag every month and replace food and water tablets before they expire. Reply to this email any time you need refills.\n\n$track";
            break;
        case 'cancelled':
            $subject = "Your KIDAN 72 order $no was cancelled";
            $body = $hi . "Your order $no has been cancelled. If this is a mistake or you would like to order again, just reply to this email or message us.\n";
            break;
        default:
            return false;
    }
    return send_mail($o['email'], $subject, $body . "\n\n" . shop_signature() . "\n", setting('contact_email'), $id);
}


/* =========================================================
   Customer access to their own order (tracking page, payment proof):
   the private link (order no. + token) or order no. + the mobile used.
   Wrong tries count against the connection (20 per 15 minutes).
   Returns the order id, or throws OrderError with a friendly message.
   ========================================================= */
function customer_order_id(PDO $pdo, string $no, string $token, string $mobile): int {
    $no = strtoupper(trim($no));
    if (preg_match('/^\d{1,6}$/', $no)) $no = sprintf('K72-%04d', (int)$no);   // "4" or "0004" → K72-0004
    $ip = 't:' . client_ip();
    $st = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > NOW() - INTERVAL 15 MINUTE');
    $st->execute([$ip]);
    if ((int)$st->fetchColumn() >= 20) throw new OrderError('Too many tries. Please wait 15 minutes, or message us.', 429);

    $st = $pdo->prepare('SELECT id, mobile, track_token FROM orders WHERE order_no = ?');
    $st->execute([$no]);
    $row = $st->fetch();
    $ok = $row && (
        ($token !== '' && $row['track_token'] && hash_equals($row['track_token'], $token)) ||
        ($mobile !== '' && mobile_key($mobile) !== null && mobile_key($mobile) === mobile_key($row['mobile']))
    );
    if (!$ok) {
        $pdo->prepare('INSERT INTO login_attempts (ip) VALUES (?)')->execute([$ip]);
        throw new OrderError('We could not find an order with that number and mobile number. Please check both.', 404);
    }
    return (int)$row['id'];
}

/* =========================================================
   Proof of payment
   ========================================================= */
const PROOF_STATUSES = ['new', 'confirmed'];   // the customer can upload while the order waits for payment
const PROOF_MAX_PER_ORDER = 5;

function payment_proofs(PDO $pdo, int $orderId): array {
    $st = $pdo->prepare('SELECT * FROM payment_proofs WHERE order_id = ? ORDER BY created_at, id');
    $st->execute([$orderId]);
    return $st->fetchAll();
}

/* Saves the uploaded file into uploads/payments/ (a folder the public can
   never open). Photos are resized and re-saved as JPG, which also removes
   anything hidden in the file. PDFs are kept as they are. */
function save_payment_file(array $file, string $orderNo): array {
    $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err !== UPLOAD_ERR_OK) {
        throw new OrderError([UPLOAD_ERR_INI_SIZE => 'That file is too large. Please send a smaller photo or screenshot.',
                              UPLOAD_ERR_FORM_SIZE => 'That file is too large.', UPLOAD_ERR_PARTIAL => 'The upload was interrupted. Please try again.',
                              UPLOAD_ERR_NO_FILE => 'Please choose a photo or screenshot of your payment.'][$err] ?? 'The file could not be uploaded. Please try again.');
    }
    if ($file['size'] > 10 * 1024 * 1024) throw new OrderError('That file is larger than 10 MB. Please send a screenshot instead.');

    $dir = KIDAN_ROOT . '/uploads/payments';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) throw new RuntimeException('uploads/payments could not be created');
    if (!is_file("$dir/.htaccess")) @file_put_contents("$dir/.htaccess", "# Customers' payment proofs: only the admin page may show them\n<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n");
    if (!is_file("$dir/index.html")) @file_put_contents("$dir/index.html", '');
    $name = preg_replace('/[^A-Z0-9-]/', '', strtoupper($orderNo)) . '-' . bin2hex(random_bytes(8));

    $head = (string)@file_get_contents($file['tmp_name'], false, null, 0, 5);
    if ($head === '%PDF-') {
        if (!move_uploaded_file($file['tmp_name'], "$dir/$name.pdf") && !rename($file['tmp_name'], "$dir/$name.pdf")) throw new RuntimeException('PDF could not be saved');
        return ['file' => "$name.pdf", 'mime' => 'application/pdf'];
    }
    if (!function_exists('imagecreatetruecolor')) throw new OrderError('Photo uploads are not available right now. Please message us your payment screenshot.');
    $info = @getimagesize($file['tmp_name']);
    $type = $info[2] ?? 0;
    if (!in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
        throw new OrderError('Please send a photo or screenshot (JPG or PNG) or a PDF. iPhone tip: take a screenshot of the payment and upload that.');
    }
    $img = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($file['tmp_name']),
        IMAGETYPE_PNG  => @imagecreatefrompng($file['tmp_name']),
        IMAGETYPE_WEBP => @imagecreatefromwebp($file['tmp_name']),
        IMAGETYPE_GIF  => @imagecreatefromgif($file['tmp_name']),
    };
    if (!$img) throw new OrderError('That picture could not be read. Please try a screenshot instead.');
    if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $angle = [3 => 180, 6 => -90, 8 => 90][(int)(@exif_read_data($file['tmp_name'])['Orientation'] ?? 1)] ?? 0;
        if ($angle && ($r = imagerotate($img, $angle, 0))) { imagedestroy($img); $img = $r; }
    }
    $w = imagesx($img); $h = imagesy($img);
    $scale = min(1, 1800 / max($w, $h));
    $nw = max(1, (int)round($w * $scale)); $nh = max(1, (int)round($h * $scale));
    $out = imagecreatetruecolor($nw, $nh);
    imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
    imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($img);
    if (!imagejpeg($out, "$dir/$name.jpg", 88)) throw new RuntimeException('JPG could not be saved');
    imagedestroy($out);
    return ['file' => "$name.jpg", 'mime' => 'image/jpeg'];
}

function email_shop_payment_proof(PDO $pdo, int $orderId, array $proof): void {
    $to = setting('order_alert_email');
    if ($to === '' || !($o = load_order($pdo, $orderId))) return;
    $body = "{$o['customer']} sent a proof of payment for order {$o['order_no']}.\n\n"
          . ($proof['ref'] !== '' ? "Reference no.: {$proof['ref']}\n" : '')
          . ($proof['amount'] !== null ? 'Amount: ' . peso_text($proof['amount']) . "\n" : '')
          . ($proof['note'] !== '' ? "Note: {$proof['note']}\n" : '')
          . 'Order total: ' . peso_text($o['total']) . "\n\n"
          . "Check the payment in your GCash / bank app, then set the order to Paid:\n" . site_base_url() . "admin/order.php?id=$orderId\n";
    send_mail($to, "Proof of payment for KIDAN 72 order {$o['order_no']}", $body, '', $orderId);
}

/* =========================================================
   Text-message (SMS) alerts to the shop's phone, through Semaphore
   (semaphore.co, a Philippine SMS service). Turned on in the admin page
   (Contact & site → Phone alerts) by saving the API key there.
   Never blocks or breaks an order: failures are only noted.
   ========================================================= */
function sms_numbers(): array {
    $out = [];
    foreach (preg_split('/[,;\s]+/', setting('sms_numbers')) as $n) {
        $k = mobile_key($n);
        if ($k !== null && preg_match('/^09\d{9}$/', $k)) $out[$k] = $k;
    }
    return array_values($out);
}

function send_sms(array $numbers, string $text): array {
    $key = setting('sms_api_key');
    if ($key === '' || !$numbers) return ['ok' => false, 'error' => 'Phone alerts are not set up yet.'];
    if (!function_exists('curl_init')) return ['ok' => false, 'error' => 'This server cannot send text messages (cURL is off).'];
    $text = trim(preg_replace('/\s+/u', ' ', $text));
    if (stripos($text, 'test') === 0) $text = 'KIDAN 72 ' . $text;   // Semaphore drops messages that start with "TEST"
    $post = ['apikey' => $key, 'number' => implode(',', $numbers), 'message' => mb_substr($text, 0, 300)];
    if (($sender = setting('sms_sender')) !== '') $post['sendername'] = $sender;
    $cfg = kidan_config() ?? [];
    $ch = curl_init($cfg['sms_url'] ?? 'https://api.semaphore.co/api/v4/messages');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post), CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => 8]);
    $res = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);
    $data = is_string($res) ? json_decode($res, true) : null;
    $ok = $code >= 200 && $code < 300 && is_array($data) && isset($data[0]['message_id']);
    $error = '';
    if (!$ok) {
        if ($cerr !== '') $error = 'Could not reach the SMS service.';
        elseif (is_array($data)) {
            $flat = [];
            array_walk_recursive($data, function ($v) use (&$flat) { if (is_string($v)) $flat[] = $v; });
            $error = mb_substr(implode(' ', $flat), 0, 200) ?: "SMS service answered $code.";
        } else $error = "SMS service answered $code.";
    }
    try {
        db()->prepare("INSERT INTO settings (k, v) VALUES ('sms_last', ?) ON DUPLICATE KEY UPDATE v = VALUES(v)")
            ->execute([json_encode(['at' => date('Y-m-d H:i:s'), 'ok' => $ok, 'error' => $error], JSON_UNESCAPED_UNICODE)]);
    } catch (Throwable $e) { /* ignore */ }
    if (!$ok) error_log('KIDAN 72 SMS: ' . $error);
    return ['ok' => $ok, 'error' => $error];
}

/* Short texts (one SMS is 160 letters). No links: Philippine networks
   block text messages that contain links. */
function sms_new_order(PDO $pdo, int $id): void {
    $o = load_order($pdo, $id);
    if (!$o || !sms_numbers()) return;
    $place = implode(', ', array_filter([$o['city'], $o['province']]));
    send_sms(sms_numbers(), "KIDAN 72 new order {$o['order_no']}: {$o['customer']}, {$o['mobile']}"
        . ($place !== '' ? ", $place" : '') . '. ' . (int)$o['item_count'] . ' item' . ((int)$o['item_count'] === 1 ? '' : 's')
        . ($o['total'] !== null ? ', ' . peso_text($o['total']) : '') . '. Open the admin page to confirm.');
}

function sms_payment_proof(PDO $pdo, int $id, array $proof): void {
    $o = load_order($pdo, $id);
    if (!$o || !sms_numbers()) return;
    send_sms(sms_numbers(), "KIDAN 72: {$o['customer']} sent proof of payment for {$o['order_no']}"
        . ($proof['ref'] !== '' ? ", ref {$proof['ref']}" : '')
        . ($proof['amount'] !== null ? ', ' . peso_text($proof['amount']) : '') . '. Check your GCash/bank, then mark it Paid.');
}
