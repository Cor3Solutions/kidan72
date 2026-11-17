<?php
/* KIDAN 72 — Admin: customers (CRM list) */
require __DIR__ . '/_inc.php';
$me = require_login();
$pdo = db();

/* ---------- add a customer by hand (phone inquiry, walk-in, event) ---------- */
if (is_post() && ($_POST['action'] ?? '') === 'add') {
    check_csrf();
    $t = fn($k, $max = 255) => mb_substr(trim((string)($_POST[$k] ?? '')), 0, $max);
    $name = $t('name', 120); $mobile = $t('mobile', 40);
    $type = in_array($_POST['type'] ?? '', CUSTOMER_TYPES, true) ? $_POST['type'] : 'Individual';
    if ($name === '') { flash('Please type the customer\'s name.', 'error'); redirect('customers.php'); }
    $key = $mobile === '' ? null : mobile_key($mobile);
    if ($key !== null) {
        $st = $pdo->prepare('SELECT id FROM customers WHERE mobile_key = ?'); $st->execute([$key]);
        if ($id = $st->fetchColumn()) { flash('A customer with that mobile number already exists. Here is their profile.', 'error'); redirect('customer.php?id=' . $id); }
    }
    $pdo->prepare("INSERT INTO customers (name, mobile, mobile_key, email, type, organization, province, city, barangay, street, source)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'admin')")
        ->execute([$name, $mobile, $key, $t('email', 160), $type, $t('organization', 160), $t('province', 80), $t('city', 80), $t('barangay', 120), $t('street')]);
    $id = (int)$pdo->lastInsertId();
    log_action("Added customer $name");
    flash('Customer added.');
    redirect('customer.php?id=' . $id);
}

/* ---------- list ---------- */
$q = trim((string)($_GET['q'] ?? ''));
$province = (string)($_GET['province'] ?? '');
$type = (string)($_GET['type'] ?? '');
$shows = ['all' => 'All', 'followup' => 'Follow-up due', 'repeat' => 'Repeat customers', 'noorder' => 'No order yet'];
$show = isset($shows[$_GET['show'] ?? '']) ? $_GET['show'] : 'all';
$sorts = ['last' => 'Latest order', 'spent' => 'Total spent', 'name' => 'Name', 'new' => 'Newest customer'];
$sort = isset($sorts[$_GET['sort'] ?? '']) ? $_GET['sort'] : 'last';

$where = []; $args = [];
if ($q !== '') {
    $where[] = '(c.name LIKE ? OR c.mobile LIKE ? OR c.organization LIKE ? OR c.city LIKE ? OR c.barangay LIKE ? OR c.email LIKE ?)';
    array_push($args, ...array_fill(0, 6, "%$q%"));
}
if ($province !== '') { $where[] = 'c.province = ?'; $args[] = $province; }
if ($type !== '') { $where[] = 'c.type = ?'; $args[] = $type; }
if ($show === 'followup') $where[] = 'c.follow_up_on IS NOT NULL AND c.follow_up_on <= CURDATE()';
$having = match ($show) { 'repeat' => 'HAVING orders >= 2', 'noorder' => 'HAVING orders = 0', default => '' };
$order = match ($sort) {
    'spent' => 'spent DESC, c.name',
    'name'  => 'c.name',
    'new'   => 'c.id DESC',
    default => 'MAX(o.created_at) IS NULL, MAX(o.created_at) DESC, c.id DESC',
};
$sql = "SELECT c.*, COUNT(o.id) AS orders,
               COALESCE(SUM(CASE WHEN o.status <> 'cancelled' THEN o.total END), 0) AS spent,
               MAX(o.created_at) AS last_order
        FROM customers c LEFT JOIN orders o ON o.customer_id = c.id"
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . " GROUP BY c.id $having ORDER BY $order LIMIT 500";
$st = $pdo->prepare($sql);
$st->execute($args);
$customers = $st->fetchAll();

$provinces = $pdo->query("SELECT province, COUNT(*) FROM customers WHERE province <> '' GROUP BY province ORDER BY province")->fetchAll(PDO::FETCH_KEY_PAIR);
$total = (int)$pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
$tabCount = [
    'all' => $total,
    'followup' => (int)$pdo->query('SELECT COUNT(*) FROM customers WHERE follow_up_on IS NOT NULL AND follow_up_on <= CURDATE()')->fetchColumn(),
    'repeat' => (int)$pdo->query('SELECT COUNT(*) FROM (SELECT customer_id FROM orders WHERE customer_id IS NOT NULL GROUP BY customer_id HAVING COUNT(*) >= 2) r')->fetchColumn(),
    'noorder' => (int)$pdo->query('SELECT COUNT(*) FROM customers c WHERE NOT EXISTS (SELECT 1 FROM orders o WHERE o.customer_id = c.id)')->fetchColumn(),
];
$filtered = $province !== '' || $type !== '' || $sort !== 'last';
$today = date('Y-m-d');

page_top('Customers', 'customers.php', $me);
?>
<div class="page-head">
  <h1>Customers</h1>
  <a class="btn primary" href="#add" onclick="var d=document.getElementById('add');d.open=true;setTimeout(function(){document.getElementById('n-name').focus()},50)">+ Add customer</a>
  <p class="lede">Everyone who orders is added by themselves, matched by mobile number. Add people who called or visited by hand.</p>
</div>

<nav class="tabs" aria-label="Show customers">
<?php foreach ($shows as $k => $label): if ($k !== 'all' && $k !== $show && !$tabCount[$k]) continue; ?>
  <a href="?<?= h(http_build_query(array_filter(['show' => $k === 'all' ? '' : $k, 'q' => $q, 'province' => $province, 'type' => $type, 'sort' => $sort === 'last' ? '' : $sort]))) ?>"<?= $show === $k ? ' aria-current="page"' : '' ?>><?= h($label) ?> <span class="n"><?= $tabCount[$k] ?></span></a>
<?php endforeach; ?>
</nav>

<form method="get" role="search">
  <?php if ($show !== 'all'): ?><input type="hidden" name="show" value="<?= h($show) ?>"><?php endif; ?>
  <div class="search">
    <input type="search" id="q" name="q" value="<?= h($q) ?>" placeholder="Name, mobile, office, city or barangay" aria-label="Search customers">
    <button class="btn ghost" type="submit">Search</button>
  </div>
  <details class="more"<?= $filtered ? ' open' : '' ?>>
    <summary>Filter and sort <small><?= $filtered ? 'on' : 'province, type, order' ?></small></summary>
    <div class="inner">
      <div class="filters-row">
        <div class="field"><label for="province">Province</label><select id="province" name="province"><option value="">All provinces</option>
          <?php foreach ($provinces as $p => $cnt): ?><option value="<?= h($p) ?>"<?= $province === $p ? ' selected' : '' ?>><?= h("$p ($cnt)") ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="type">Type</label><select id="type" name="type"><option value="">All types</option>
          <?php foreach (CUSTOMER_TYPES as $ty): ?><option<?= $type === $ty ? ' selected' : '' ?>><?= h($ty) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="sort">Order by</label><select id="sort" name="sort">
          <?php foreach ($sorts as $k => $label): ?><option value="<?= $k ?>"<?= $sort === $k ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></div>
        <div class="actions"><button class="btn" type="submit">Show</button><?php if ($filtered): ?><a class="btn ghost" href="?<?= h(http_build_query(array_filter(['show' => $show === 'all' ? '' : $show, 'q' => $q]))) ?>">Clear</a><?php endif; ?></div>
      </div>
    </div>
  </details>
</form>

<details class="more" id="add">
  <summary>Add a customer <small>someone who called, messaged or visited</small></summary>
  <form class="inner" method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="add">
    <div class="row3">
      <div class="field"><label for="n-name">Name *</label><input type="text" id="n-name" name="name" required maxlength="120"></div>
      <div class="field"><label for="n-mobile">Mobile</label><input type="tel" id="n-mobile" name="mobile" maxlength="40" placeholder="09XX XXX XXXX"></div>
      <div class="field"><label for="n-email">Email</label><input type="email" id="n-email" name="email" maxlength="160"></div>
    </div>
    <div class="row2">
      <div class="field"><label for="n-type">Type</label><select id="n-type" name="type"><?php foreach (CUSTOMER_TYPES as $ty): ?><option><?= h($ty) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="n-org">Office / school / barangay name</label><input type="text" id="n-org" name="organization" maxlength="160"></div>
    </div>
    <?= address_fields([], 'n') ?>
    <button class="btn primary" type="submit" data-busy="Adding…">Add customer</button>
  </form>
</details>

<?php if ($q !== '' || $filtered || $show !== 'all'): ?><p class="count-line"><?= count($customers) ?> of <?= $total ?> customers</p><?php endif; ?>
<?php if (!$customers): ?>
  <div class="list"><div class="empty"><p><?= $total ? 'No customers match.' : 'No customers yet. They are added when the first order comes in.' ?></p></div></div>
<?php else: ?>
<div class="list">
  <?php foreach ($customers as $c): $due = $c['follow_up_on'] && $c['follow_up_on'] <= $today; $place = implode(', ', array_filter([$c['city'], $c['province']])); ?>
    <a class="row" href="customer.php?id=<?= (int)$c['id'] ?>">
      <span class="row-main">
        <span class="row-title"><?= h($c['name']) ?></span>
        <span class="row-sub"><?= h(implode(' · ', array_filter([$c['organization'], $c['mobile'], $place]))) ?: 'No details yet' ?></span>
      </span>
      <span class="row-side">
        <span class="row-money"><?= (int)$c['orders'] ? h(money((float)$c['spent'])) : '<span class="muted">No order yet</span>' ?></span>
        <span class="row-tags">
          <?php if ($c['follow_up_on']): ?><span class="badge<?= $due ? ' due' : '' ?>">Follow up <?= h($due && $c['follow_up_on'] === $today ? 'today' : date('M j', strtotime($c['follow_up_on']))) ?></span><?php endif; ?>
          <?php if ((int)$c['orders'] >= 2): ?><span class="badge repeat"><?= (int)$c['orders'] ?> orders</span><?php elseif ((int)$c['orders'] === 1): ?><span class="row-sub">1 order</span><?php endif; ?>
        </span>
      </span>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php page_bottom();
