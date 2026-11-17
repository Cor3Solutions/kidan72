<?php
/* KIDAN 72 — Admin: one order */
require __DIR__ . '/_inc.php';
require_once KIDAN_ROOT . '/api/stock.php';
$me = require_login();
$pdo = db();

$id = (int)($_GET['id'] ?? 0);
$statuses = ['new' => 'New', 'confirmed' => 'Confirmed', 'paid' => 'Paid', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'];
$emailable = ['confirmed', 'paid', 'delivered', 'cancelled'];
$methods = ['', 'GCash', 'Maya', 'Bank transfer', 'Cash on delivery', 'Cash on pick-up', 'Other'];

$st = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
$st->execute([$id]);
$o = $st->fetch();
if (!$o) { flash('That order was not found.', 'error'); redirect('orders.php'); }

if (is_post()) {
    check_csrf();
    if (($_POST['action'] ?? '') === 'delete_proof') {
        $st = $pdo->prepare('SELECT * FROM payment_proofs WHERE id = ? AND order_id = ?');
        $st->execute([(int)($_POST['proof'] ?? 0), $id]);
        if ($pf = $st->fetch()) {
            $pdo->prepare('DELETE FROM payment_proofs WHERE id = ?')->execute([$pf['id']]);
            @unlink(KIDAN_ROOT . '/uploads/payments/' . basename($pf['file']));
            log_action("Removed a proof of payment from order {$o['order_no']}");
            flash('Proof of payment removed.');
        }
        redirect('order.php?id=' . $id);
    }
    if (($_POST['action'] ?? '') === 'delete') {
        // stock taken by this order goes back first
        if ($o['stock_taken']) { $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?")->execute([$id]); stock_sync_order($pdo, $id, $me['display_name'] ?: $me['username']); }
        foreach (payment_proofs($pdo, $id) as $pf) @unlink(KIDAN_ROOT . '/uploads/payments/' . basename($pf['file']));
        $pdo->prepare('DELETE FROM orders WHERE id = ?')->execute([$id]);
        log_action("Deleted order {$o['order_no']} ({$o['customer']})");
        // Test orders deleted and none left: the first real order is K72-0001 again
        $left = (int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
        if ($left === 0) { try { $pdo->exec('ALTER TABLE orders AUTO_INCREMENT = 1'); } catch (Throwable $e) { /* not allowed: numbers just continue */ } }
        flash($left === 0 ? 'Order deleted. No orders left, so the next order will be K72-0001.' : 'Order deleted.');
        redirect('orders.php');
    }
    try {
        $s = (string)($_POST['status'] ?? '');
        if (!isset($statuses[$s])) throw new RuntimeException('Please choose a status.');
        $fee = parse_money((string)($_POST['delivery_fee'] ?? '')) ?? 0.0;
        $discount = parse_money((string)($_POST['discount'] ?? '')) ?? 0.0;
        $method = in_array($_POST['payment_method'] ?? '', $methods, true) ? $_POST['payment_method'] : '';
        $total = $o['subtotal'] === null ? null : max(0, round((float)$o['subtotal'] + $fee - $discount, 2));
        $pdo->prepare('UPDATE orders SET status = ?, delivery_fee = ?, discount = ?, total = ?, payment_method = ?, payment_ref = ?, admin_note = ? WHERE id = ?')
            ->execute([$s, $fee, $discount, $total, $method, mb_substr(trim((string)($_POST['payment_ref'] ?? '')), 0, 80),
                       mb_substr(trim((string)($_POST['admin_note'] ?? '')), 0, 5000), $id]);
        $changes = [];
        $emailNote = '';
        $stockNote = '';
        if ($s !== $o['status']) {
            $changes[] = "status {$o['status']} → $s";
            log_order_status($pdo, $id, $s, $me['display_name'] ?: $me['username']);
            // stock out when Confirmed / Paid / Delivered, back in when Cancelled or New
            $sk = stock_sync_order($pdo, $id, $me['display_name'] ?: $me['username']);
            if ($sk['taken']) { $stockNote = " {$sk['taken']} item" . ($sk['taken'] === 1 ? '' : 's') . ' taken out of stock.'; $changes[] = 'stock taken'; }
            if ($sk['returned']) { $stockNote = " {$sk['returned']} item" . ($sk['returned'] === 1 ? '' : 's') . ' put back in stock.'; $changes[] = 'stock returned'; }
            if ($sk['short']) $stockNote .= ' NOT ENOUGH STOCK: ' . implode(', ', $sk['short']) . '. Add the stock you receive on the Stock page.';
            if (!empty($_POST['notify']) && in_array($s, $emailable, true) && $o['email'] !== '') {
                $sent = email_customer($pdo, $id, $s);
                $emailNote = $sent ? " The customer was emailed at {$o['email']}." : ' But the email to the customer was NOT sent: ' . mail_last_error();
                if ($sent) $changes[] = 'customer emailed';
            }
        }
        if ((float)$o['delivery_fee'] != $fee) $changes[] = 'delivery fee ' . money($fee);
        if ((float)$o['discount'] != $discount) $changes[] = 'discount ' . money($discount);
        if ($method !== $o['payment_method']) $changes[] = 'payment ' . ($method ?: 'cleared');
        log_action("Updated order {$o['order_no']}" . ($changes ? ': ' . implode(', ', $changes) : ''));
        flash('Order updated.' . $emailNote . ($stockNote ?? ''), ($emailNote !== '' && !str_contains($emailNote, 'was emailed')) || str_contains($stockNote ?? '', 'NOT ENOUGH') ? 'error' : 'ok');
    } catch (RuntimeException $e) {
        flash($e->getMessage(), 'error');
    }
    redirect('order.php?id=' . $id);
}

$st = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id');
$st->execute([$id]);
$lines = $st->fetchAll();

$cust = null;
if ($o['customer_id']) {
    $st = $pdo->prepare("SELECT c.id, c.type, c.organization, c.follow_up_on, COUNT(x.id) AS orders,
                                COALESCE(SUM(CASE WHEN x.status <> 'cancelled' THEN x.total END), 0) AS spent
                         FROM customers c LEFT JOIN orders x ON x.customer_id = c.id WHERE c.id = ? GROUP BY c.id");
    $st->execute([$o['customer_id']]);
    $cust = $st->fetch() ?: null;
}

/* A ready-made reply the admin can paste into Messenger */
$first = trim(explode(' ', trim($o['customer']))[0] ?? '');
$reply = "Hi" . ($first !== '' ? " $first" : '') . "! Thank you for your KIDAN 72 order {$o['order_no']}.\n\n";
foreach ($lines as $l) $reply .= "• {$l['qty']} × {$l['name']}" . ($l['line_total'] !== null ? ' — ' . money($l['line_total']) : '') . "\n";
$reply .= "\n";
if ($o['subtotal'] !== null) $reply .= 'Items: ' . money($o['subtotal']) . "\n";
if ((float)$o['delivery_fee'] > 0) $reply .= 'Delivery fee: ' . money($o['delivery_fee']) . "\n";
if ((float)$o['discount'] > 0) $reply .= 'Discount: −' . money($o['discount']) . "\n";
if ($o['total'] !== null) $reply .= 'Total to pay: ' . money($o['total']) . "\n";
$pay = trim(setting('payment_instructions'));
$reply .= "\nYour items are available and your order is confirmed. "
        . ($pay !== '' ? "Please pay using:\n$pay\n\nThen send us the screenshot of your payment here, or upload it on your order page (link below)." : 'Please send your payment via GCash or bank transfer, then send us the screenshot of your payment here, or upload it on your order page (link below).')
        . " We will then arrange delivery to " . (full_address($o) !== '' ? full_address($o) : 'your address') . ".\n\n"
        . "Track your order: " . track_url($o) . "\n\nSalamat po! — KIDAN 72";

$st = $pdo->prepare('SELECT status, by_name, created_at FROM order_status_log WHERE order_id = ? ORDER BY created_at, id');
$st->execute([$id]);
$history = $st->fetchAll();

$st = $pdo->prepare("SELECT reason, SUM(-change_qty) AS n, MAX(created_at) AS at FROM stock_moves WHERE order_id = ? GROUP BY reason, DATE_FORMAT(created_at, '%Y-%m-%d %H:%i')");
$st->execute([$id]);
$stockMoves = $st->fetchAll();

$proofs = payment_proofs($pdo, $id);
$st = $pdo->prepare('SELECT * FROM email_log WHERE order_id = ? ORDER BY id');
$st->execute([$id]);
$emails = $st->fetchAll();

page_top('Order ' . $o['order_no'], 'orders.php', $me);
$tel = preg_replace('/[^\d+]/', '', $o['mobile']);
$waitingPay = in_array($o['status'], PROOF_STATUSES, true);
$next = [
    'new' => 'Check stock, add the delivery fee, then set it to Confirmed.' . ($o['email'] !== '' ? ' The customer gets an email with the total and how to pay.' : ' Then send the customer the message below.'),
    'confirmed' => $proofs ? 'The customer sent proof of payment. Check your GCash / bank app, then set it to Paid.' : 'Waiting for the customer to pay.',
    'paid' => 'Pack the kit and send it, then set it to Delivered.',
    'delivered' => 'Done. The customer can find training for every item on the website.',
    'cancelled' => 'This order was cancelled.',
][$o['status']] ?? '';
// Before confirming: is there enough stock for this order?
$stockCheck = '';
if (!$o['stock_taken'] && in_array($o['status'], ['new'], true) && $o['ip'] !== 'sample') {
    $info = stock_info($pdo);
    $short = []; $tracked = 0;
    foreach (stock_order_needs($pdo, $info, $id) as $k => $q) {
        [$s, $v] = explode('|', $k, 2);
        $tracked++;
        $have = (int)stock_on_hand($info, $s, $v);
        if ($have < $q) $short[] = $info['products'][$s]['name'] . ($v !== '' ? " ($v)" : '') . " (need $q, have " . max(0, $have) . ')';
    }
    if ($short) $stockCheck = '<div class="next-step" style="border-color:var(--bad)"><b>Not enough stock:</b> ' . h(implode(', ', $short)) . '. You can still confirm; the count goes below zero until you add the stock.</div>';
    elseif ($tracked) $stockCheck = '<p class="hint" style="margin:-6px 0 14px">📦 Stock check: everything for this order is in stock.</p>';
}
?>
<a class="back" href="orders.php">← Orders</a>
<div class="order-top">
  <h1><?= h($o['order_no']) ?></h1>
  <?= status_chip($o['status']) ?>
  <?php if ($proofs && $waitingPay): ?><a class="badge due" href="#proofs">Proof sent</a><?php endif; ?>
</div>
<p class="order-meta"><?= h(date('M j, Y · g:i A', strtotime($o['created_at']))) ?> · <?= h(ORDER_CHANNELS[$o['channel']] ?? (ucfirst($o['channel']) ?: 'Order')) ?></p>
<?php if ($next !== ''): ?><div class="next-step"><b>Next step:</b> <?= h($next) ?></div><?php endif; ?>
<?= $stockCheck ?>

<div class="order-cols">
<div class="left">
  <div class="card who">
    <span class="name"><?= $cust ? '<a href="customer.php?id=' . (int)$cust['id'] . '">' . h($o['customer']) . '</a>' : h($o['customer']) ?></span>
    <?php if ($cust): ?>
      <span class="quick"><?php if ((int)$cust['orders'] >= 2): ?><span class="badge repeat">Repeat customer · <?= (int)$cust['orders'] ?> orders</span><?php else: ?><span class="badge">First order</span><?php endif; ?><?php if ($cust['type'] !== 'Individual'): ?><span class="badge"><?= h($cust['type']) ?></span><?php endif; ?></span>
    <?php endif; ?>
    <span><?= h($o['mobile']) ?><?= $o['email'] !== '' ? ' · ' . h($o['email']) : '' ?></span>
    <span><?= full_address($o) !== '' ? h(full_address($o)) : '<span class="muted">No address given</span>' ?></span>
    <?php if ($o['notes'] !== ''): ?><span class="muted">Note from customer: <?= nl2br(h($o['notes'])) ?></span><?php endif; ?>
    <div class="contact-btns">
      <?php if ($tel !== ''): ?><a class="btn ghost small" href="tel:<?= h($tel) ?>">Call</a><a class="btn ghost small" href="sms:<?= h($tel) ?>">Text</a><?php endif; ?>
      <?php if ($o['email'] !== ''): ?><a class="btn ghost small" href="mailto:<?= h($o['email']) ?>">Email</a><?php endif; ?>
    </div>
  </div>

  <div class="card">
    <h3>Items</h3>
    <ul class="lines">
    <?php foreach ($lines as $l): ?>
      <li><span><?= (int)$l['qty'] ?> × <?= h($l['name']) ?><span class="sn"><?= h($l['serial']) ?> · <?= h(money($l['unit_price'])) ?> each</span></span><span class="amt"><?= h(money($l['line_total'])) ?></span></li>
    <?php endforeach; ?>
    </ul>
    <div class="sums">
      <div><span>Items</span><span><?= h(money($o['subtotal'])) ?></span></div>
      <?php if ((float)$o['delivery_fee'] > 0): ?><div><span>Delivery fee</span><span><?= h(money($o['delivery_fee'])) ?></span></div><?php endif; ?>
      <?php if ((float)$o['discount'] > 0): ?><div><span>Discount</span><span>−<?= h(money($o['discount'])) ?></span></div><?php endif; ?>
      <div class="total"><span>Total</span><span><?= h(money($o['total'])) ?></span></div>
    </div>
    <?php if ($o['subtotal'] === null): ?><p class="hint" style="margin:8px 0 0">Some prices were not set when this order came in. Set the prices on the Products page; this order keeps the prices it was made with.</p><?php endif; ?>
  </div>

  <?php if ($proofs): ?>
  <div class="card" id="proofs">
    <h3>Proof of payment <span class="badge<?= $waitingPay ? ' due' : '' ?>"><?= count($proofs) ?> from the customer</span></h3>
    <div class="proofs">
    <?php foreach ($proofs as $pf): ?>
      <div class="proof">
        <a href="proof.php?id=<?= (int)$pf['id'] ?>" target="_blank" rel="noopener" class="pic" aria-label="Open the proof full size">
          <?php if ($pf['mime'] === 'application/pdf'): ?><span>PDF · tap to open</span><?php else: ?><img src="proof.php?id=<?= (int)$pf['id'] ?>" alt="Proof of payment" loading="lazy"><?php endif; ?>
        </a>
        <dl class="kv">
          <dt>Sent</dt><dd><?= h(when($pf['created_at'])) ?></dd>
          <dt>Reference</dt><dd><?= $pf['ref'] !== '' ? h($pf['ref']) : '<span class="muted">—</span>' ?></dd>
          <dt>Amount</dt><dd><?= $pf['amount'] !== null ? h(money($pf['amount'])) . ($o['total'] !== null && abs((float)$pf['amount'] - (float)$o['total']) >= 0.01 ? ' <span class="badge due">total is ' . h(money($o['total'])) . '</span>' : '') : '<span class="muted">—</span>' ?></dd>
          <?php if ($pf['note'] !== ''): ?><dt>Note</dt><dd><?= h($pf['note']) ?></dd><?php endif; ?>
        </dl>
        <form method="post" onsubmit="return confirm('Remove this proof of payment?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_proof"><input type="hidden" name="proof" value="<?= (int)$pf['id'] ?>"><button class="btn danger small" type="submit">Remove (wrong picture)</button></form>
      </div>
    <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<div class="right">
  <form class="card" method="post" data-warn>
    <?= csrf_field() ?>
    <h3>Update order</h3>
    <fieldset class="choice">
      <legend>Status</legend>
      <?php foreach ($statuses as $k => $label): ?>
        <label><input type="radio" name="status" value="<?= $k ?>"<?= $o['status'] === $k ? ' checked' : '' ?>><span><?= $label ?></span></label>
      <?php endforeach; ?>
    </fieldset>
    <div class="row2 keep">
      <div class="field"><label for="delivery_fee">Delivery fee</label><input type="text" inputmode="decimal" id="delivery_fee" name="delivery_fee" value="<?= h(plain_money($o['delivery_fee'])) ?>" placeholder="0"></div>
      <div class="field"><label for="discount">Discount</label><input type="text" inputmode="decimal" id="discount" name="discount" value="<?= h(plain_money($o['discount'])) ?>" placeholder="0"></div>
      <div class="field"><label for="payment_method">Paid by</label>
        <select id="payment_method" name="payment_method"><?php foreach ($methods as $m): ?><option value="<?= h($m) ?>"<?= $o['payment_method'] === $m ? ' selected' : '' ?>><?= $m === '' ? '—' : h($m) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="payment_ref">Reference no.</label><input type="text" id="payment_ref" name="payment_ref" value="<?= h($o['payment_ref']) ?>" maxlength="80"></div>
    </div>
    <div class="field"><label for="admin_note">Private note <span class="hint">Only admins see this.</span></label>
      <textarea id="admin_note" name="admin_note" style="min-height:70px"><?= h($o['admin_note']) ?></textarea></div>
    <?php if ($o['email'] !== ''): ?>
      <label class="check field"><input type="checkbox" name="notify" value="1" checked> Email <?= h($o['email']) ?> when the status changes</label>
    <?php endif; ?>
    <button class="btn primary block" type="submit" data-busy="Saving…">Save changes</button>
  </form>

  <details class="more">
    <summary>Message for the customer <small>for Messenger / Viber</small></summary>
    <div class="inner">
      <p class="hint" style="margin-top:0">Ready to paste. Save the delivery fee first so the total is right. You can edit it before copying.</p>
      <textarea id="reply" style="min-height:220px"><?= h($reply) ?></textarea>
      <div class="actions" style="margin-top:10px">
        <button type="button" class="btn" id="copy-reply">Copy message</button>
        <span class="hint" id="copy-msg" role="status"></span>
      </div>
    </div>
  </details>

  <details class="more"<?= array_filter($emails, fn($m) => !$m['ok']) ? ' open' : '' ?>>
    <summary>History and emails <small><?= count($history) ?> update<?= count($history) === 1 ? '' : 's' ?> · <?= count($emails) ?> email<?= count($emails) === 1 ? '' : 's' ?></small></summary>
    <div class="inner">
      <ul class="history">
      <?php
        $events = [];
        foreach ($history as $hst) $events[] = [$hst['created_at'], 'status', $hst];
        foreach ($emails as $m) $events[] = [$m['created_at'], 'email', $m];
        foreach ($stockMoves as $m) $events[] = [$m['at'], 'stock', $m];
        usort($events, fn($a, $b) => strcmp($a[0], $b[0]));
        foreach ($events as [$at, $kind, $e]): ?>
        <?php if ($kind === 'status'): ?>
          <li><span><?= status_chip($e['status']) ?> <span class="by"><?= $e['by_name'] !== '' ? 'by ' . h($e['by_name']) : '' ?></span></span><span class="by"><?= h(date('M j, g:i A', strtotime($at))) ?></span></li>
        <?php elseif ($kind === 'stock'): ?>
          <li><span>📦 <?= $e['reason'] === 'sold' ? (int)$e['n'] . ' item' . ((int)$e['n'] === 1 ? '' : 's') . ' taken out of stock' : -(int)$e['n'] . ' item' . (-(int)$e['n'] === 1 ? '' : 's') . ' put back in stock' ?> <a class="by" href="stock.php?order=<?= (int)$o['id'] ?>">see items</a></span><span class="by"><?= h(date('M j, g:i A', strtotime($at))) ?></span></li>
        <?php else: ?>
          <li><span><?= $e['ok'] ? '✉️ Email sent' : '<b style="color:var(--bad)">✉️ Email NOT sent</b>' ?> <span class="by">"<?= h($e['subject']) ?>" to <?= h($e['to_addr']) ?></span><?= !$e['ok'] && $e['error'] !== '' ? '<br><span class="by">' . h($e['error']) . ' <a href="settings.php#email">Fix in Settings</a></span>' : '' ?></span><span class="by"><?= h(date('M j, g:i A', strtotime($at))) ?></span></li>
        <?php endif; ?>
      <?php endforeach; ?>
      </ul>
      <p class="hint" style="margin:12px 0 0">The customer sees the status on their <a href="<?= h(track_url($o)) ?>" target="_blank" rel="noopener">tracking page ↗</a></p>
    </div>
  </details>

  <div class="danger-zone">
    <a class="btn ghost small" href="order-print.php?id=<?= (int)$o['id'] ?>" target="_blank" rel="noopener">Print packing slip</a>
    <form method="post" onsubmit="return confirm('Delete order <?= h($o['order_no']) ?> for good? This cannot be undone.')">
      <?= csrf_field() ?><input type="hidden" name="action" value="delete">
      <button class="btn danger small" type="submit">Delete order</button>
    </form>
  </div>
</div>
</div>
<script>
document.getElementById('copy-reply').addEventListener('click', async function () {
  var t = document.getElementById('reply'), msg = document.getElementById('copy-msg');
  try { await navigator.clipboard.writeText(t.value); msg.textContent = 'Copied. Paste it in Messenger.'; }
  catch (e) { t.select(); msg.textContent = 'Press Ctrl+C (or Copy) to copy the selected text.'; }
});
</script>
<?php page_bottom();
