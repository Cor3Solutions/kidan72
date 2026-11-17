<?php
/* KIDAN 72 — Admin: list of orders */
require __DIR__ . '/_inc.php';
$me = require_login();
$pdo = db();

$filters = ['all' => 'All', 'new' => 'New', 'proof' => 'Proof to check', 'confirmed' => 'Confirmed', 'paid' => 'Paid', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'];
$conds = [
    'all'       => '1',
    'new'       => "o.status = 'new'",
    'proof'     => "o.status IN ('new','confirmed') AND EXISTS (SELECT 1 FROM payment_proofs p WHERE p.order_id = o.id)",
    'confirmed' => "o.status = 'confirmed'",
    'paid'      => "o.status = 'paid'",
    'delivered' => "o.status = 'delivered'",
    'cancelled' => "o.status = 'cancelled'",
    'open'      => "o.status IN ('confirmed','paid')",   // older links
];
$status = $_GET['status'] ?? 'all';
if (!isset($conds[$status])) $status = 'all';
$find = trim((string)($_GET['q'] ?? ''));

$counts = [];
foreach ($filters as $k => $label) $counts[$k] = (int)$pdo->query("SELECT COUNT(*) FROM orders o WHERE {$conds[$k]}")->fetchColumn();

$where = [$conds[$status]]; $args = [];
if ($find !== '') {
    $where[] = '(o.order_no LIKE ? OR o.customer LIKE ? OR o.mobile LIKE ? OR o.city LIKE ? OR o.province LIKE ? OR o.barangay LIKE ?)';
    array_push($args, ...array_fill(0, 6, "%$find%"));
}
$st = $pdo->prepare('SELECT o.*, (SELECT COUNT(*) FROM payment_proofs p WHERE p.order_id = o.id) AS proofs FROM orders o WHERE '
                  . implode(' AND ', $where) . ' ORDER BY o.id DESC LIMIT 300');
$st->execute($args);
$orders = $st->fetchAll();

page_top('Orders', 'orders.php', $me);
?>
<div class="page-head">
  <h1>Orders</h1>
  <a class="btn primary" href="order-new.php">+ Record an order</a>
  <p class="lede">Website orders arrive here by themselves. Use "Record an order" for orders by phone, chat or walk-in.</p>
</div>

<nav class="tabs" aria-label="Show orders">
<?php foreach ($filters as $k => $label): if ($k !== 'all' && $k !== $status && !$counts[$k]) continue; ?>
  <a href="?<?= h(http_build_query(array_filter(['status' => $k === 'all' ? '' : $k, 'q' => $find]))) ?>"<?= $status === $k ? ' aria-current="page"' : '' ?>><?= h($label) ?> <span class="n"><?= $counts[$k] ?></span></a>
<?php endforeach; ?>
</nav>

<form class="search" method="get" role="search">
  <?php if ($status !== 'all'): ?><input type="hidden" name="status" value="<?= h($status) ?>"><?php endif; ?>
  <input type="search" name="q" value="<?= h($find) ?>" placeholder="Order no., name, mobile or place" aria-label="Search orders">
  <button class="btn ghost" type="submit">Search</button>
</form>

<?php if ($find !== ''): ?><p class="count-line"><?= count($orders) ?> found for "<?= h($find) ?>" · <a href="?<?= h(http_build_query(array_filter(['status' => $status === 'all' ? '' : $status]))) ?>">Clear search</a></p><?php endif; ?>
<?php if (!$orders): ?>
  <div class="list"><div class="empty"><p><?= $find !== '' ? 'No orders match that search.' : ($status === 'all' ? 'No orders yet. Website orders appear here the moment they are submitted.' : 'No orders here right now.') ?></p></div></div>
<?php else: ?>
  <div class="list"><?php foreach ($orders as $o) echo order_row($o); ?></div>
<?php endif; ?>
<?php page_bottom();
