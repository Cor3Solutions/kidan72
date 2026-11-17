<?php
/* KIDAN 72 — Admin: record an order taken by phone, Messenger chat, walk-in or at an event */
require __DIR__ . '/_inc.php';
$me = require_login();
$pdo = db();

$methods = ['', 'GCash', 'Maya', 'Bank transfer', 'Cash on delivery', 'Cash on pick-up', 'Other'];
$channels = array_intersect_key(ORDER_CHANNELS, array_flip(['fb', 'phone', 'walkin', 'event', 'other']));

$c = ['name' => '', 'mobile' => '', 'email' => '', 'province' => '', 'city' => '', 'barangay' => '', 'street' => ''];
if ($cid = (int)($_GET['customer'] ?? 0)) {
    $st = $pdo->prepare('SELECT * FROM customers WHERE id = ?');
    $st->execute([$cid]);
    if ($row = $st->fetch()) $c = array_intersect_key($row, $c) + $c;
}
$f = $c + ['channel' => 'fb', 'status' => 'new', 'delivery_fee' => '', 'discount' => '', 'payment_method' => '', 'payment_ref' => '', 'notes' => '', 'admin_note' => '', 'qty' => []];

if (is_post()) {
    check_csrf();
    foreach (array_keys($f) as $k) if ($k !== 'qty') $f[$k] = trim((string)($_POST[$k] ?? ''));
    $f['qty'] = array_map('intval', (array)($_POST['qty'] ?? []));
    try {
        $order = kidan_create_order($pdo, [
            'name' => $f['name'], 'mobile' => $f['mobile'], 'email' => $f['email'],
            'province' => $f['province'], 'city' => $f['city'], 'barangay' => $f['barangay'], 'street' => $f['street'],
            'notes' => $f['notes'], 'admin_note' => $f['admin_note'],
            'channel' => isset($channels[$f['channel']]) ? $f['channel'] : 'other',
            'status' => isset(ORDER_STATUSES[$f['status']]) ? $f['status'] : 'new',
            'delivery_fee' => parse_money($f['delivery_fee']) ?? 0, 'discount' => parse_money($f['discount']) ?? 0,
            'payment_method' => in_array($f['payment_method'], $methods, true) ? $f['payment_method'] : '',
            'payment_ref' => $f['payment_ref'], 'items' => $f['qty'], 'by' => $me['display_name'] ?: $me['username'],
        ], true);
        log_action("Recorded order {$order['order_no']} for {$f['name']} (" . ($channels[$f['channel']] ?? 'other') . ')');
        $note = '';
        if (!empty($_POST['notify']) && $f['email'] !== '') {
            $note = email_customer($pdo, $order['id'], $f['status'] === 'new' ? 'new' : $f['status']) ? ' A confirmation was emailed to the customer.' : ' (The email could not be sent from this server.)';
        }
        $sk = $order['stock'] ?? ['taken' => 0, 'short' => []];
        if ($sk['taken']) $note .= " {$sk['taken']} item" . ($sk['taken'] === 1 ? '' : 's') . ' taken out of stock.';
        if ($sk['short']) $note .= ' NOT ENOUGH STOCK: ' . implode(', ', $sk['short']) . '.';
        flash("Order {$order['order_no']} saved." . $note, $sk['short'] ? 'error' : 'ok');
        redirect('order.php?id=' . $order['id']);
    } catch (RuntimeException $e) {
        flash($e->getMessage(), 'error');
    }
}

$products = [];
// A product with a choice (e.g. pouch colour) gets one row per value: qty[K72-PCH-001|Brown]
foreach ($pdo->query("SELECT serial, kind, name, price, sold_out, choice FROM products WHERE sold_separately = 1 ORDER BY kind = 'item', sort, serial") as $p) {
    $ch = product_choice($p['choice'] ?? null);
    if (!$ch) { $products[] = $p + ['key' => $p['serial']]; continue; }
    foreach ($ch['values'] as $v) $products[] = ['key' => $p['serial'] . '|' . $v, 'name' => $p['name'] . ' (' . $v . ')'] + $p;
}

page_top('New order', 'orders.php', $me);
?>
<a class="back" href="orders.php">← Orders</a>
<div class="page-head"><h1>Record an order</h1><p class="lede">For orders by phone, chat, walk-in or at an event. It gets an order number, joins the customer's history and can be tracked, like a website order.</p></div>

<form method="post" id="new-order" data-warn>
  <?= csrf_field() ?>
  <div class="row2">
    <div class="card">
      <h3>Customer</h3>
      <div class="row2">
        <div class="field"><label for="o-name">Name *</label><input type="text" id="o-name" name="name" value="<?= h($f['name']) ?>" required maxlength="120"></div>
        <div class="field"><label for="o-mobile">Mobile *</label><input type="text" id="o-mobile" name="mobile" value="<?= h($f['mobile']) ?>" required maxlength="40" placeholder="09XX XXX XXXX"></div>
      </div>
      <div class="field"><label for="o-email">Email <span class="hint">Optional. Lets the customer get a confirmation and status updates.</span></label><input type="email" id="o-email" name="email" value="<?= h($f['email']) ?>" maxlength="160"></div>
      <?= address_fields($f, 'o') ?>
      <p class="hint">If this mobile number already belongs to a customer, the order is added to their history.</p>
    </div>
    <div class="card">
      <h3>Order details</h3>
      <div class="row2">
        <div class="field"><label for="channel">Came in by</label><select id="channel" name="channel"><?php foreach ($channels as $k => $label): ?><option value="<?= $k ?>"<?= $f['channel'] === $k ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="status">Status</label><select id="status" name="status"><?php foreach (ORDER_STATUSES as $k => $label): if ($k === 'cancelled') continue; ?><option value="<?= $k ?>"<?= $f['status'] === $k ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="delivery_fee">Delivery fee (PHP)</label><input type="text" inputmode="decimal" id="delivery_fee" name="delivery_fee" value="<?= h($f['delivery_fee']) ?>" placeholder="0"></div>
        <div class="field"><label for="discount">Discount (PHP)</label><input type="text" inputmode="decimal" id="discount" name="discount" value="<?= h($f['discount']) ?>" placeholder="0"></div>
        <div class="field"><label for="payment_method">Payment method</label><select id="payment_method" name="payment_method"><?php foreach ($methods as $m): ?><option value="<?= h($m) ?>"<?= $f['payment_method'] === $m ? ' selected' : '' ?>><?= $m === '' ? '—' : h($m) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="payment_ref">Payment reference</label><input type="text" id="payment_ref" name="payment_ref" value="<?= h($f['payment_ref']) ?>" maxlength="80"></div>
      </div>
      <div class="field"><label for="notes">Customer's notes</label><textarea id="notes" name="notes" style="min-height:70px"><?= h($f['notes']) ?></textarea></div>
      <div class="field"><label for="admin_note">Private note</label><textarea id="admin_note" name="admin_note" style="min-height:70px"><?= h($f['admin_note']) ?></textarea></div>
      <label class="check"><input type="checkbox" name="notify" value="1" checked> Email the customer a confirmation (if an email is filled in)</label>
    </div>
  </div>

  <h2>Items</h2>
  <div class="table-wrap"><table>
    <thead><tr><th>Item</th><th>Serial</th><th class="num">Price</th><th class="num" style="width:140px">Quantity</th></tr></thead>
    <tbody>
    <?php foreach ($products as $p): ?>
      <tr>
        <td><b><?= h($p['name']) ?></b><?= $p['sold_out'] ? ' <span class="badge">sold out</span>' : '' ?></td>
        <td class="mono"><?= h($p['serial']) ?></td>
        <td class="num" data-price="<?= $p['price'] === null ? '' : h((string)(float)$p['price']) ?>"><?= h(money($p['price'])) ?></td>
        <td class="num"><input type="number" class="price-input qty" name="qty[<?= h($p['key']) ?>]" min="0" max="999" value="<?= (int)($f['qty'][$p['key']] ?? 0) ?: '' ?>" placeholder="0" aria-label="Quantity of <?= h($p['name']) ?>"></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>

  <div class="sticky-save actions"><button class="btn primary" type="submit" data-busy="Saving…">Save order</button><span class="hint" id="sum">No items yet</span></div>
</form>
<script>
(function () {
  var form = document.getElementById('new-order'), sum = document.getElementById('sum');
  function peso(n) { return 'PHP ' + n.toLocaleString('en-PH', { minimumFractionDigits: n % 1 ? 2 : 0, maximumFractionDigits: 2 }); }
  function num(v) { v = parseFloat(String(v).replace(/[^\d.]/g, '')); return isNaN(v) ? 0 : v; }
  function update() {
    var items = 0, total = 0, unpriced = false;
    form.querySelectorAll('input.qty').forEach(function (i) {
      var q = parseInt(i.value, 10) || 0; if (!q) return;
      var p = i.closest('tr').querySelector('[data-price]').dataset.price;
      items += q; if (p === '') unpriced = true; else total += q * parseFloat(p);
    });
    total += num(form.delivery_fee.value) - num(form.discount.value);
    sum.textContent = items ? items + ' item' + (items === 1 ? '' : 's') + ' · total ' + (unpriced ? 'to be confirmed (some prices not set)' : peso(Math.max(0, total))) : 'No items yet';
  }
  form.addEventListener('input', update); update();
})();
</script>
<?php page_bottom();
