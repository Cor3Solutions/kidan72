<?php
/* KIDAN 72 — Admin home: what needs doing now, this month in numbers,
   and the latest orders. Each line links to the page where it is done. */
require __DIR__ . '/_inc.php';
$me = require_login();
$pdo = db();

/* Encoder: a home page with only their own work (no orders, customers or sales) */
if (is_encoder($me)) {
    require_once KIDAN_ROOT . '/api/stock.php';
    $c = fn(string $sql) => (int)$pdo->query($sql)->fetchColumn();
    $lowStock = 0; try { $lowStock = stock_low_count($pdo); } catch (Throwable $e) { }
    $notCounted = $c("SELECT COUNT(*) FROM products WHERE kind = 'item' AND track_stock = 0");
    // same rules as the "No photo" / "No video" filters on the Products page
    $uploads = [];
    foreach ($pdo->query('SELECT serial, position, file, updated_at FROM product_photos') as $r) $uploads[$r['serial']][(int)$r['position']] = $r;
    $noPhoto = 0; $noVideo = 0;
    foreach ($pdo->query('SELECT serial, kind, video, photo_hidden FROM products') as $p) {
        if (!$p['photo_hidden'] && !product_has_photo($p['serial'], $uploads[$p['serial']] ?? [])) $noPhoto++;
        if ($p['kind'] === 'item' && $p['video'] === '') $noVideo++;
    }
    $todo = [];
    if ($lowStock)   $todo[] = ['stock.php?show=low', $lowStock, $lowStock === 1 ? 'item low on stock' : 'items low on stock', 'Tell the owner so more can be ordered. Record it under Stock arrived when it comes.', 'warn'];
    if ($notCounted) $todo[] = ['stock.php?do=count', $notCounted, $notCounted === 1 ? 'item not counted yet' : 'items not counted yet', 'Count the shelf and type the numbers in.', 'setup'];
    if ($noPhoto)    $todo[] = ['products.php?show=nophoto', $noPhoto, $noPhoto === 1 ? 'product without a photo' : 'products without a photo', 'Upload a photo on the product page.', 'setup'];
    if ($noVideo)    $todo[] = ['products.php?show=novideo', $noVideo, $noVideo === 1 ? 'item without a video' : 'items without a video', 'Paste the YouTube link on the item page.', 'setup'];
    page_top('Home', 'index.php', $me);
    ?>
<div class="page-head">
  <h1>Hello<?= $me['display_name'] ? ', ' . h(explode(' ', trim($me['display_name']))[0]) : '' ?></h1>
  <a class="btn primary" href="stock.php?do=arrived">+ Stock arrived</a>
</div>
<h2 style="margin-top:0">To do</h2>
<?php if (!$todo): ?>
  <div class="all-done">✓ All caught up.</div>
<?php else: ?>
<div class="todo">
  <?php foreach ($todo as [$href, $num, $title, $sub, $cls]): ?>
    <a href="<?= h($href) ?>"<?= $cls ? ' class="' . $cls . '"' : '' ?>><b><?= h((string)$num) ?></b><span><strong><?= h($title) ?></strong><small><?= h($sub) ?></small></span></a>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<h2>What you can do</h2>
<div class="menu-list">
  <a href="stock.php?do=arrived"><b>Stock arrived</b><small>Add the items that were delivered.</small></a>
  <a href="stock.php?do=count"><b>Count the shelf</b><small>Type in what is really on the shelf.</small></a>
  <a href="stock.php?do=remove"><b>Remove items</b><small>Damaged, expired or lost items.</small></a>
  <a href="products.php"><b>Products</b><small>Names, descriptions, photos and videos.</small></a>
  <a href="training.php"><b>Training modules</b><small>The lessons on the Training page.</small></a>
</div>
<?php
    page_bottom();
    exit;
}

$n = fn(string $sql) => (int)$pdo->query($sql)->fetchColumn();
$newOrders   = $n("SELECT COUNT(*) FROM orders WHERE status = 'new'");
$proofs      = $n("SELECT COUNT(*) FROM orders o WHERE o.status IN ('new','confirmed') AND EXISTS (SELECT 1 FROM payment_proofs p WHERE p.order_id = o.id)");
$toDeliver   = $n("SELECT COUNT(*) FROM orders WHERE status = 'paid'");
$noPrice     = $n("SELECT COUNT(*) FROM products WHERE price IS NULL AND sold_separately = 1");
$followups   = $pdo->query("SELECT id, name, follow_up_note FROM customers WHERE follow_up_on IS NOT NULL AND follow_up_on <= CURDATE() ORDER BY follow_up_on, name LIMIT 4")->fetchAll();
$followCount = $n('SELECT COUNT(*) FROM customers WHERE follow_up_on IS NOT NULL AND follow_up_on <= CURDATE()');
$smsLast     = json_decode(setting('sms_last'), true) ?: [];
$noPayInfo   = trim(setting('payment_instructions')) === '';
$recent      = $pdo->query('SELECT o.*, (SELECT COUNT(*) FROM payment_proofs p WHERE p.order_id = o.id) AS proofs FROM orders o ORDER BY o.id DESC LIMIT 5')->fetchAll();

$st = $pdo->prepare("SELECT COUNT(*) AS n, COALESCE(SUM(total), 0) AS t FROM orders WHERE status <> 'cancelled' AND created_at >= ?");
$st->execute([date('Y-m-01')]);
$month = $st->fetch();
$newCustomers = $n("SELECT COUNT(*) FROM customers WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");

$todo = [];
if ($proofs)      $todo[] = ['orders.php?status=proof', $proofs, $proofs === 1 ? 'proof of payment to check' : 'proofs of payment to check', 'Check your GCash / bank app, then mark the order Paid.', ''];
if ($newOrders)   $todo[] = ['orders.php?status=new', $newOrders, $newOrders === 1 ? 'new order to confirm' : 'new orders to confirm', 'Check stock, add the delivery fee, then confirm.', ''];
if ($toDeliver)   $todo[] = ['orders.php?status=paid', $toDeliver, $toDeliver === 1 ? 'paid order to deliver' : 'paid orders to deliver', 'Pack and send, then mark Delivered.', ''];
if ($followCount) $todo[] = ['customers.php?show=followup', $followCount, $followCount === 1 ? 'customer to follow up' : 'customers to follow up',
                             implode(', ', array_map(fn($f) => $f['name'], $followups)) . ($followCount > count($followups) ? ' and more' : ''), ''];
if ($smsLast && empty($smsLast['ok'])) $todo[] = ['settings.php#alerts', '!', 'Phone alert not sent', when($smsLast['at']) . ': ' . $smsLast['error'], 'warn'];
$mailFails = $n('SELECT COUNT(*) FROM email_log WHERE ok = 0 AND created_at > NOW() - INTERVAL 7 DAY');
if ($mailFails)   $todo[] = ['settings.php#email', $mailFails, $mailFails === 1 ? 'email was not sent' : 'emails were not sent', 'In the last 7 days. Set up or check "Email sending".', 'warn'];
elseif (!smtp_settings()) $todo[] = ['settings.php#email', '1', 'Set up email sending', 'So order emails reach customers and do not land in spam.', 'setup'];
if ($noPayInfo)   $todo[] = ['settings.php#payments', '1', 'Add your payment details', 'GCash / bank details customers see when you confirm their order.', 'setup'];
require_once KIDAN_ROOT . '/api/stock.php';
$lowStock = 0; try { $lowStock = stock_low_count($pdo); } catch (Throwable $e) { }
if ($lowStock)    $todo[] = ['stock.php?show=low', $lowStock, $lowStock === 1 ? 'item low on stock' : 'items low on stock', 'Order more before they run out. Out-of-stock items show Sold out on the website.', 'warn'];
if ($noPrice)     $todo[] = ['products.php?show=noprice', $noPrice, $noPrice === 1 ? 'product without a price' : 'products without a price', 'They show "Price to follow" on the website.', 'setup'];

page_top('Home', 'index.php', $me);
?>
<div class="page-head">
  <h1>Hello<?= $me['display_name'] ? ', ' . h(explode(' ', trim($me['display_name']))[0]) : '' ?></h1>
  <a class="btn primary" href="order-new.php">+ Record an order</a>
</div>

<h2 style="margin-top:0">To do</h2>
<?php if (!$todo): ?>
  <div class="all-done">✓ All caught up. New orders and payments will show here.</div>
<?php else: ?>
<div class="todo">
  <?php foreach ($todo as [$href, $num, $title, $sub, $cls]): ?>
    <a href="<?= h($href) ?>"<?= $cls ? ' class="' . $cls . '"' : '' ?>><b><?= h((string)$num) ?></b><span><strong><?= h($title) ?></strong><small><?= h($sub) ?></small></span></a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="section-title"><h2><?= h(date('F')) ?> so far</h2><a href="reports.php">See report</a></div>
<div class="figures">
  <div><b><?= (int)$month['n'] ?></b><span>Orders</span></div>
  <div><b><?= h(money((float)$month['t'])) ?></b><span>Sales</span></div>
  <div><b><?= $newCustomers ?></b><span>New customers</span></div>
</div>

<div class="section-title"><h2>Latest orders</h2><?php if ($recent): ?><a href="orders.php">See all</a><?php endif; ?></div>
<?php if (!$recent): ?>
  <div class="list"><div class="empty"><p>No orders yet. Orders from the website appear here the moment they are submitted.</p></div></div>
<?php else: ?>
  <div class="list"><?php foreach ($recent as $o) echo order_row($o); ?></div>
<?php endif; ?>
<?php page_bottom();
