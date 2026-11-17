<?php
/* KIDAN 72 — Admin: all bags and items, with quick price editing */
require __DIR__ . '/_inc.php';
$me = require_login();
$pdo = db();

$cats = $pdo->query('SELECT code, name FROM categories ORDER BY sort, code')->fetchAll(PDO::FETCH_KEY_PAIR);

if (is_post()) {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'prices' && sees_sales($me)) {
        $st = $pdo->prepare('UPDATE products SET price = ? WHERE serial = ?');
        $bad = [];
        foreach ((array)($_POST['price'] ?? []) as $serial => $raw) {
            $raw = str_replace([',', 'PHP', 'php', '₱', ' '], '', (string)$raw);
            if ($raw === '') { $st->execute([null, $serial]); continue; }
            if (!is_numeric($raw) || (float)$raw < 0 || (float)$raw > 9999999) { $bad[] = $serial; continue; }
            $st->execute([round((float)$raw, 2), $serial]);
        }
        log_action('Updated prices on the Products page');
        flash($bad ? 'Prices saved, except: ' . implode(', ', $bad) . ' (numbers only, please).' : 'Prices saved.', $bad ? 'error' : 'ok');
        redirect('products.php');
    }

    if ($action === 'add') {
        $cat = (string)($_POST['cat'] ?? '');
        $name = trim((string)($_POST['name'] ?? ''));
        if (!isset($cats[$cat]) || $name === '') { flash('Choose a category and type a name.', 'error'); redirect('products.php'); }
        $st = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(serial, 9) AS UNSIGNED)) FROM products WHERE serial LIKE ?");
        $st->execute(["K72-$cat-%"]);
        $serial = sprintf('K72-%s-%03d', $cat, ((int)$st->fetchColumn()) + 1);
        $sort = (int)$pdo->query("SELECT COALESCE(MAX(sort), 0) + 10 FROM products")->fetchColumn();
        $pdo->prepare("INSERT INTO products (serial, kind, cat, icon, name, short_text, summary, why, steps, spec, maintain, sort)
                       VALUES (?, 'item', ?, 'tool', ?, '', '', '', '[]', '', '', ?)")->execute([$serial, $cat, mb_substr($name, 0, 120), $sort]);
        log_action("Created new item $serial ($name)");
        flash("New item $serial created. Fill in its details below.");
        redirect('product.php?sn=' . urlencode($serial));
    }
}

$products = $pdo->query('SELECT * FROM products ORDER BY kind = \'item\', sort, serial')->fetchAll();
$uploads = [];
foreach ($pdo->query('SELECT serial, position, file, updated_at FROM product_photos') as $r) $uploads[$r['serial']][(int)$r['position']] = $r;

// Quick filters, with how many products each one has
$shows = ['all' => 'All', 'noprice' => 'No price', 'nophoto' => 'No photo', 'novideo' => 'No video', 'soldout' => 'Sold out'];
$prices = sees_sales($me);                 // an Encoder does not see prices
if (!$prices) unset($shows['noprice']);
$show = $_GET['show'] ?? 'all';
if (!isset($shows[$show])) $show = 'all';
$test = fn($p, $k) => match ($k) {
    'noprice' => $p['price'] === null && $p['sold_separately'],
    'nophoto' => !$p['photo_hidden'] && !product_has_photo($p['serial'], $uploads[$p['serial']] ?? []),
    'novideo' => $p['kind'] === 'item' && $p['video'] === '',
    'soldout' => (bool)$p['sold_out'],
    default   => true,
};
$showCount = [];
foreach ($shows as $k => $label) $showCount[$k] = count(array_filter($products, fn($p) => $test($p, $k)));
$products = array_values(array_filter($products, fn($p) => $test($p, $show)));

$groups = ['Bags' => []];
foreach ($cats as $code => $name) $groups[$name] = [];
foreach ($products as $p) {
    $g = $p['kind'] === 'bag' ? 'Bags' : ($cats[$p['cat']] ?? 'Other');
    $groups[$g][] = $p;
}

page_top('Products', 'products.php', $me);
?>
<div class="page-head">
  <h1>Products</h1>
  <p class="lede"><?= $prices ? 'Type a price in pesos and press <b>Save prices</b>. An empty price shows "Price to follow" on the website. ' : '' ?>Tap a name to change its text, photos or video.</p>
</div>

<nav class="tabs" aria-label="Show products">
<?php foreach ($shows as $k => $label): if ($k !== 'all' && $k !== $show && !$showCount[$k]) continue; ?>
  <a href="?<?= $k === 'all' ? '' : 'show=' . h($k) ?>"<?= $show === $k ? ' aria-current="page"' : '' ?>><?= h($label) ?> <span class="n"><?= $showCount[$k] ?></span></a>
<?php endforeach; ?>
</nav>
<div class="search"><input type="search" id="find" placeholder="Find a product by name or serial" aria-label="Find a product" autocomplete="off"></div>

<?php if (!$products): ?><div class="list"><div class="empty"><p>Nothing here. All done!</p></div></div><?php endif; ?>

<form method="post" id="prices" data-warn>
  <?= csrf_field() ?><input type="hidden" name="action" value="prices">
  <?php foreach ($groups as $group => $list): if (!$list) continue; ?>
    <section class="pgroup">
    <h2 class="group-title"><?= h($group) ?> <span class="n"><?= count($list) ?></span></h2>
    <div class="list">
      <?php foreach ($list as $p): $s = $p['serial']; ?>
        <div class="prod" data-find="<?= h(mb_strtolower($p['name'] . ' ' . $s)) ?>">
          <?php if ($p['photo_hidden']): ?><span class="thumb none">no photo</span><?php else: ?><img class="thumb" src="<?= h(current_photo($s, 1, $uploads[$s] ?? [])) ?>" alt="" loading="lazy" onerror="this.outerHTML='<span class=&quot;thumb none&quot;>no photo</span>'"><?php endif; ?>
          <div class="info">
            <a href="product.php?sn=<?= h(urlencode($s)) ?>"><?= h($p['name']) ?></a>
            <small><?= h($s) ?><?= !$p['sold_separately'] ? ' · comes with a bag only' : '' ?></small>
            <?php if ($p['sold_out']): ?><span class="flags"><span class="status cancelled">Sold out</span></span><?php endif; ?>
          </div>
          <?php if ($prices): ?><span class="peso"><input type="text" inputmode="decimal" name="price[<?= h($s) ?>]" value="<?= $p['price'] === null ? '' : h(rtrim(rtrim($p['price'], '0'), '.')) ?>" placeholder="xx" aria-label="Price of <?= h($p['name']) ?> in pesos"></span><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    </section>
  <?php endforeach; ?>
  <?php if ($products && $prices): ?><div class="sticky-save actions"><button class="btn primary" type="submit" data-busy="Saving…">Save prices</button><span class="hint" id="dirty-note" style="margin:0">Saves every price above.</span></div><?php endif; ?>
</form>

<details class="more" style="margin-top:24px">
  <summary>Add a new item <small>gets the next serial number</small></summary>
  <form class="inner" method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="add">
    <div class="row2">
      <div class="field"><label for="new-cat">Category</label>
        <select id="new-cat" name="cat"><?php foreach ($cats as $code => $name): ?><option value="<?= h($code) ?>"><?= h($name) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="new-name">Name</label><input type="text" id="new-name" name="name" required></div>
    </div>
    <button class="btn" type="submit" data-busy="Creating…">Create item</button>
  </form>
</details>
<script>
(function () {
  var box = document.getElementById('find');
  box.addEventListener('input', function () {
    var q = box.value.trim().toLowerCase();
    document.querySelectorAll('.prod').forEach(function (r) { r.hidden = q !== '' && r.dataset.find.indexOf(q) < 0; });
    document.querySelectorAll('.pgroup').forEach(function (g) { g.hidden = !g.querySelector('.prod:not([hidden])'); });
  });
  var f = document.getElementById('prices');
  if (f) f.addEventListener('input', function () { var n = document.getElementById('dirty-note'); if (n) n.textContent = 'You have unsaved prices.'; });
})();
</script>
<?php page_bottom();
