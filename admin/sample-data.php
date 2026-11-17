<?php
/* KIDAN 72 — Admin: sample orders for trying out the Reports.

   "Add sample orders" makes about 200 made-up orders over the last
   6 months (with made-up customers), so the reports and charts have
   something to show. No emails or text messages are sent.
   "Remove sample data" deletes every sample order and sample customer,
   and nothing else. Real orders are never touched.

   Sample orders are marked: ip = 'sample' and a private note
   "[SAMPLE DATA]". Sample customers: source = 'sample'. */
require __DIR__ . '/_inc.php';
require KIDAN_ROOT . '/api/sample.php';
$me = require_login();
$pdo = db();

function sample_counts(PDO $pdo): array {
    return [
        'orders' => (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE ip = 'sample'")->fetchColumn(),
        'customers' => (int)$pdo->query("SELECT COUNT(*) FROM customers WHERE source = 'sample'")->fetchColumn(),
        'real' => (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE ip <> 'sample'")->fetchColumn(),
    ];
}

if (is_post()) {
    check_csrf();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            if (sample_counts($pdo)['orders'] > 0) throw new RuntimeException('Sample orders are already there. Remove them first if you want a fresh set.');
            @set_time_limit(120);
            $made = kidan_make_sample_orders($pdo, $me['display_name'] ?: $me['username']);
            log_action("Added $made sample orders (for trying out the reports)");
            flash("$made sample orders added over the last 6 months. Open Reports to see them. Remove them here before real orders start.");
        }
        if ($action === 'remove') {
            $n = (int)$pdo->exec("DELETE FROM orders WHERE ip = 'sample'");
            $c = (int)$pdo->exec("DELETE FROM customers WHERE source = 'sample' AND NOT EXISTS (SELECT 1 FROM orders o WHERE o.customer_id = customers.id)");
            $left = (int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
            if ($left === 0) {
                try { $pdo->exec('ALTER TABLE orders AUTO_INCREMENT = 1'); } catch (Throwable $e) { /* numbers just continue */ }
                try { $pdo->exec('ALTER TABLE customers AUTO_INCREMENT = 1'); } catch (Throwable $e) { }
            }
            log_action("Removed the sample data ($n orders, $c customers)");
            flash("Sample data removed: $n orders and $c customers." . ($left === 0 ? ' The next real order will be K72-0001.' : ''));
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash($e instanceof RuntimeException ? $e->getMessage() : 'Something went wrong: ' . $e->getMessage(), 'error');
    }
    redirect('sample-data.php');
}

$n = sample_counts($pdo);
page_top('Sample data', 'more.php', $me);
?>
<a class="back" href="more.php">← More</a>
<div class="page-head"><h1>Sample data</h1>
  <p class="lede">Made-up orders for trying out the Reports and the Excel download before real orders come in. No emails or text messages are sent.</p></div>

<div class="card">
  <?php if ($n['orders']): ?>
    <p><b><?= $n['orders'] ?> sample orders</b> and <b><?= $n['customers'] ?> sample customers</b> are in the system<?= $n['real'] ? ", next to {$n['real']} real order" . ($n['real'] === 1 ? '' : 's') : '' ?>.
      They show in Orders, Customers and Reports, marked "[SAMPLE DATA]" in the private note.</p>
    <p class="next-step"><b>Before the shop opens:</b> remove them, so the reports only count real orders.</p>
    <form method="post" onsubmit="return confirm('Remove all <?= $n['orders'] ?> sample orders and the sample customers? Real orders are not touched.')">
      <?= csrf_field() ?><input type="hidden" name="action" value="remove">
      <div class="actions"><button class="btn danger" type="submit" data-busy="Removing…">Remove sample data</button>
        <a class="btn ghost" href="reports.php">See the reports</a></div>
    </form>
  <?php else: ?>
    <p>Adds about 200 orders over the last 6 months from about 70 made-up customers in Laguna, Metro Manila, Batangas, Cavite and other places,
      with different statuses, payment methods and pouch colours. Products without a price get a sample price in these orders only; your product prices do not change.</p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add">
      <div class="actions"><button class="btn primary" type="submit" data-busy="Adding…">Add sample orders</button></div>
    </form>
    <p class="hint">You can remove them again here with one tap. Real orders are never touched.</p>
  <?php endif; ?>
</div>
<?php page_bottom();
