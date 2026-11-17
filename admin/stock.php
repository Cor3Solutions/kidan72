<?php
/* KIDAN 72 — Admin: Stock (inventory)
   Built around the jobs of the person doing the stock:
     Overview          what we have, what is running low, what is out
     Stock arrived     a delivery came in: type how many of each item
     Count the shelf   compare the shelf with the system, save the real numbers
     Remove items      damaged, expired, lost, used for display ...
     History           every change, who made it and why
   How the numbers move by themselves (orders) is in api/stock.php. */
require __DIR__ . '/_inc.php';
require __DIR__ . '/xlsx.php';
require_once KIDAN_ROOT . '/api/stock.php';
$me = require_login();
$pdo = db();
$who = $me['display_name'] ?: $me['username'];

$label = fn(array $p, string $v) => $p['name'] . ($v !== '' ? " ($v)" : '');
$plural = fn(int $n, string $one, string $many) => $n . ' ' . ($n === 1 ? $one : $many);

if (is_post()) {
    check_csrf();
    $action = $_POST['action'] ?? '';
    $back = 'stock.php';
    try {
        $info = stock_info($pdo, true);
        $valid = function (string $sn, string $v) use ($info): ?array {
            $p = $info['products'][$sn] ?? null;
            return $p && $p['kind'] !== 'bag' && in_array($v, stock_variants($p), true) ? $p : null;
        };

        // A delivery: several items at once, only the boxes that were filled in
        if ($action === 'arrived') {
            $back = 'stock.php?do=arrived';
            $note = trim((string)($_POST['note'] ?? ''));
            $rows = [];
            foreach ((array)($_POST['in'] ?? []) as $sn => $vals) foreach ((array)$vals as $vk => $raw) {
                $raw = trim((string)$raw);
                if ($raw === '' || $raw === '0') continue;
                $v = $vk === '_' ? '' : (string)$vk;
                if (!($p = $valid((string)$sn, $v))) continue;
                if (!ctype_digit($raw) || (int)$raw > 100000) throw new RuntimeException('"' . $label($p, $v) . '": please type a whole number.');
                $rows[] = [$sn, $v, (int)$raw, $p];
            }
            if (!$rows) throw new RuntimeException('Type how many arrived next to at least one item.');
            $pdo->beginTransaction();
            $done = [];
            foreach ($rows as [$sn, $v, $q, $p]) { stock_move($pdo, $sn, $v, $q, 'received', $note, null, $who); $done[] = "$q × " . $label($p, $v); }
            $pdo->commit();
            log_action('Stock arrived: ' . implode(', ', $done) . ($note !== '' ? " ($note)" : ''));
            flash('Added to stock: ' . implode(', ', $done) . '.');
            $back = 'stock.php';
        }

        // Shelf count: only the boxes that were filled in are saved
        if ($action === 'count') {
            $back = 'stock.php?do=count';
            $pdo->beginTransaction();
            $changed = 0; $same = 0; $first = 0; $bad = [];
            foreach ($info['products'] as $sn => $p) {
                if ($p['kind'] === 'bag') continue;
                $low = trim((string)($_POST['low'][$sn] ?? ''));
                if ($low !== '' && ctype_digit($low) && (int)$low !== (int)$p['low_stock']) {
                    $pdo->prepare('UPDATE products SET low_stock = ? WHERE serial = ?')->execute([min(9999, (int)$low), $sn]);
                }
                foreach (stock_variants($p) as $v) {
                    $raw = trim((string)($_POST['qty'][$sn][$v === '' ? '_' : $v] ?? ''));
                    if ($raw === '') continue;
                    if (!ctype_digit($raw) || (int)$raw > 100000) { $bad[] = $label($p, $v); continue; }
                    $now = $p['track_stock'] ? ($info['qty'][$sn][$v] ?? 0) : null;
                    if ($now === (int)$raw) { $same++; continue; }
                    stock_move($pdo, $sn, $v, 0, 'count', trim((string)($_POST['note'] ?? '')), null, $who, (int)$raw);
                    if ($now === null) $first++; else $changed++;
                }
            }
            $pdo->commit();
            log_action("Shelf count saved: $first first counts, $changed corrected, $same matched");
            $parts = [];
            if ($first) $parts[] = $plural($first, 'item', 'items') . ' counted for the first time';
            if ($changed) $parts[] = $plural($changed, 'number', 'numbers') . ' corrected to what you counted';
            if ($same) $parts[] = $plural($same, 'item', 'items') . ' already matched';
            if ($bad) flash('Saved, except: ' . implode(', ', $bad) . ' (whole numbers only, please).', 'error');
            else flash($parts ? 'Shelf count saved: ' . implode(', ', $parts) . '.' : 'Nothing was typed, so nothing changed.');
            $back = 'stock.php';
        }

        // Remove: damaged, expired, lost ...
        if ($action === 'remove') {
            $back = 'stock.php?do=remove';
            [$sn, $v] = array_pad(explode('|', (string)($_POST['item'] ?? ''), 2), 2, '');
            if (!($p = $valid($sn, $v))) throw new RuntimeException('Please choose the item.');
            $q = (int)($_POST['qty'] ?? 0);
            if ($q < 1 || $q > 100000) throw new RuntimeException('Please type how many to remove.');
            $why = in_array($_POST['why'] ?? '', STOCK_REMOVE_WHY, true) ? $_POST['why'] : 'Other';
            $note = trim((string)($_POST['note'] ?? ''));
            $pdo->beginTransaction();
            $after = stock_move($pdo, $sn, $v, -$q, 'damaged', $why . ($note !== '' ? " — $note" : ''), null, $who);
            $pdo->commit();
            log_action("Stock removed: $q × {$label($p, $v)} ($why), now $after");
            flash("Removed $q × {$label($p, $v)} ($why). " . $after . ' left.');
            $back = 'stock.php';
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash($e instanceof RuntimeException ? $e->getMessage() : 'Something went wrong: ' . $e->getMessage(), 'error');
    }
    redirect($back);
}

$info = stock_info($pdo, true);
$cats = $pdo->query('SELECT code, name FROM categories ORDER BY sort, code')->fetchAll(PDO::FETCH_KEY_PAIR);

// Each item (per colour): on hand and state (ok / low / out / off = not counted yet)
$rows = []; $count = ['ok' => 0, 'low' => 0, 'out' => 0, 'off' => 0];
foreach ($info['products'] as $sn => $p) {
    if ($p['kind'] === 'bag') continue;
    $vars = [];
    foreach (stock_variants($p) as $v) {
        $q = $p['track_stock'] ? ($info['qty'][$sn][$v] ?? 0) : null;
        $vars[$v] = [$q, $q === null ? 'off' : ($q <= 0 ? 'out' : ($q <= (int)$p['low_stock'] ? 'low' : 'ok'))];
    }
    $states = array_column($vars, 1);
    $state = in_array('off', $states, true) ? 'off' : (in_array('out', $states, true) ? 'out' : (in_array('low', $states, true) ? 'low' : 'ok'));
    $rows[$sn] = ['p' => $p, 'vars' => $vars, 'state' => $state];
    $count[$state]++;
}
$byCat = function (callable $keep) use ($rows, $cats): array {
    $g = [];
    foreach ($cats as $code => $name) {
        $list = array_filter($rows, fn($r) => $r['p']['cat'] === $code && $keep($r));
        if ($list) $g[$name] = $list;
    }
    return $g;
};

/* ---------- Excel ---------- */
if (($_GET['download'] ?? '') === 'xlsx') {
    $x = new Xlsx();
    $S = fn($v, $s) => ['v' => $v, 's' => $s];
    $states = ['ok' => 'In stock', 'low' => 'Running low', 'out' => 'Out of stock', 'off' => 'Not counted yet'];
    $money = sees_sales($me);                  // an Encoder's list has no prices or value
    $out = [[$S('Stock · ' . date('F j, Y g:i A'), 'title')], [$S('What is on hand right now.' . ($money ? ' "Value" uses the selling price.' : '') . ' Made by ' . $who . '.', 'sub')],
        array_slice([$S('Item', 'head'), $S('Serial', 'head'), $S('Colour', 'head'), $S('On hand', 'headr'), $S('Low at', 'headr'), $S('Status', 'head'), $S('Price', 'headr'), $S('Value', 'headr')], 0, $money ? 8 : 6)];
    $tq = 0; $tv = 0;
    foreach ($rows as $sn => $r) foreach ($r['vars'] as $v => [$q, $vs]) {
        $price = $r['p']['price'] === null ? null : (float)$r['p']['price'];
        $out[] = array_slice([$r['p']['name'], $sn, $v, $q === null ? '' : $S($q, 'int'), $S((int)$r['p']['low_stock'], 'int'), $S($states[$vs], $vs === 'out' ? 'muted' : 'text'),
                  $price === null ? 'to follow' : $S($price, 'peso'), $q === null || $price === null ? '' : $S(max(0, $q) * $price, 'peso')], 0, $money ? 8 : 6);
        if ($q !== null) { $tq += max(0, $q); $tv += $price === null ? 0 : max(0, $q) * $price; }
    }
    $out[] = array_slice([$S('Total', 'totallabel'), $S('', 'totallabel'), $S('', 'totallabel'), $S($tq, 'intbold'), $S('', 'totallabel'), $S('', 'totallabel'), $S('', 'totallabel'), $S($tv, 'pesobold')], 0, $money ? 8 : 6);
    $x->sheet('Stock', $out, ['widths' => array_slice([40, 14, 10, 10, 9, 16, 12, 14], 0, $money ? 8 : 6), 'header' => 3, 'filter' => true, 'heights' => [1 => 24, 3 => 22],
                              'footer' => 'KIDAN 72 stock · ' . date('M j, Y'), 'bars' => count($out) > 4 ? [['D4:D' . (count($out) - 1), 'F2A27C']] : []]);
    $st = $pdo->query('SELECT m.*, o.order_no FROM stock_moves m LEFT JOIN orders o ON o.id = m.order_id WHERE m.created_at >= NOW() - INTERVAL 1 YEAR ORDER BY m.created_at DESC, m.id DESC LIMIT 5000');
    $out = [[$S('Stock history · last 12 months', 'title')], [$S('Every change: deliveries, orders, cancellations, shelf counts and removed items. Newest first.', 'sub')],
        [$S('Date', 'head'), $S('Item', 'head'), $S('Colour', 'head'), $S('Change', 'headr'), $S('Left after', 'headr'), $S('What happened', 'head'), $S($money ? 'Order / note' : 'Note', 'head'), $S('By', 'head')]];
    foreach ($st as $m) {
        $p = $info['products'][$m['serial']] ?? ['name' => $m['serial']];
        $out[] = [$S(strtotime($m['created_at']), 'date'), $p['name'], $m['variant'], $S((int)$m['change_qty'], 'int'), $S((int)$m['qty_after'], 'int'),
                  STOCK_REASONS[$m['reason']] ?? $m['reason'], !$money && $m['order_id'] ? '' : ($m['note'] !== '' ? $m['note'] : (string)$m['order_no']), $m['by_name']];
    }
    $x->sheet('History', $out, ['widths' => [20, 38, 10, 9, 10, 28, 30, 16], 'header' => 3, 'filter' => true, 'heights' => [1 => 24, 3 => 22], 'footer' => 'KIDAN 72 stock history']);
    log_action('Downloaded the stock list');
    $x->download('kidan72-stock-' . date('Y-m-d') . '.xlsx');
}

/* ---------- which screen ---------- */
$do = $_GET['do'] ?? 'overview';
if (isset($_GET['order']) || isset($_GET['sn'])) $do = 'history';
if (!in_array($do, ['overview', 'arrived', 'count', 'remove', 'history'], true)) $do = 'overview';
$show = $_GET['show'] ?? 'all';
if (!in_array($show, ['all', 'ok', 'low', 'out', 'off'], true)) $show = 'all';
$started = $count['off'] < count($rows);

// Bags: how many can still be packed (per pouch colour) and what runs out first
$bags = [];
foreach ($info['products'] as $sn => $p) {
    if ($p['kind'] !== 'bag' || !$p['sold_separately']) continue;
    $per = [];
    foreach (stock_variants($p) as $v) $per[$v] = stock_available($info, $sn, $v);
    $bags[$sn] = ['p' => $p, 'per' => $per];
}

$stateText = ['ok' => 'In stock', 'low' => 'Running low', 'out' => 'Out of stock', 'off' => 'Not counted yet'];
$chip = fn(string $s) => '<span class="stk-chip ' . $s . '">' . $stateText[$s] . '</span>';
$num = fn(?int $q, string $s) => $q === null ? '<span class="stk-num off">–</span>' : '<span class="stk-num ' . $s . '">' . $q . '</span>';
$tasks = ['overview' => ['Overview', 'What we have'], 'arrived' => ['Stock arrived', 'Add a delivery'], 'count' => ['Count the shelf', 'Check the real numbers'],
          'remove' => ['Remove items', 'Damaged, expired, lost'], 'history' => ['History', 'Every change']];

page_top('Stock', 'stock.php', $me);
?>
<div class="page-head">
  <h1>Stock</h1>
  <a class="btn ghost" href="?download=xlsx">Download Excel</a>
</div>

<nav class="stk-tasks" aria-label="What do you want to do?">
  <?php foreach ($tasks as $k => [$t, $sub]): ?>
    <a href="<?= $k === 'overview' ? 'stock.php' : '?do=' . $k ?>"<?= $do === $k ? ' aria-current="page"' : '' ?>><b><?= h($t) ?></b><small><?= h($sub) ?></small></a>
  <?php endforeach; ?>
</nav>

<?php if ($do === 'overview'): ?>

  <?php if (!$started): ?>
    <div class="card stk-start">
      <h2>Let's start: count what you have</h2>
      <ol>
        <li>Go to the shelf with your phone and open <a href="?do=count"><b>Count the shelf</b></a>.</li>
        <li>Type how many you have of each item. For the KIDAN Backpack and the KIDAN Pouch, count each colour (Black, Brown, Green) separately.</li>
        <li>Press <b>Save</b>. From then on the numbers go down by themselves when orders are confirmed.</li>
      </ol>
      <a class="btn primary" href="?do=count">Count the shelf</a>
      <p class="hint">Items you don't count are simply not tracked yet. They never show "Sold out" on the website.</p>
    </div>
  <?php else: ?>
    <div class="stk-tiles">
      <a class="stk-tile ok<?= $show === 'ok' ? ' on' : '' ?>" href="?show=ok"><b><?= $count['ok'] ?></b><span>In stock</span></a>
      <a class="stk-tile low<?= $show === 'low' ? ' on' : '' ?>" href="?show=low"><b><?= $count['low'] ?></b><span>Running low</span></a>
      <a class="stk-tile out<?= $show === 'out' ? ' on' : '' ?>" href="?show=out"><b><?= $count['out'] ?></b><span>Out of stock</span></a>
      <?php if ($count['off']): ?><a class="stk-tile off<?= $show === 'off' ? ' on' : '' ?>" href="?show=off"><b><?= $count['off'] ?></b><span>Not counted yet</span></a><?php endif; ?>
    </div>

    <?php foreach ($bags as $sn => $b): $tracked = array_filter($b['per'], fn($a) => $a !== null); if (!$tracked) continue; ?>
      <div class="card stk-bag">
        <h3>How many <?= h($b['p']['name']) ?>s can we still pack?</h3>
        <div class="stk-pack">
          <?php foreach ($b['per'] as $v => $a): if (!$a) continue; $warn = $a['qty'] <= (int)$b['p']['low_stock']; ?>
            <div class="<?= $a['qty'] <= 0 ? 'out' : ($warn ? 'low' : 'ok') ?>">
              <b><?= (int)$a['qty'] ?></b>
              <span>with a <?= $v !== '' ? '<strong>' . h(strtolower($v)) . '</strong> ' : '' ?>pouch</span>
              <?php if ($a['limit'] !== '' && $warn): ?><small><?= $a['qty'] <= 0 ? 'Stopped by' : 'Runs out first' ?>: <?= h($a['limit']) ?></small><?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <p class="hint" style="margin:10px 0 0">Every bag needs 1 of each item inside it plus 1 pouch, so the item you have the fewest of decides this number.<?= $count['off'] ? ' Items not counted yet are left out.' : '' ?></p>
      </div>
    <?php endforeach; ?>

    <?php if ($show !== 'all'): ?><p class="stk-filter">Showing: <b><?= h($stateText[$show]) ?></b> · <a href="stock.php">Show all items</a></p><?php endif; ?>
    <div class="search"><input type="search" id="find" placeholder="Find an item" aria-label="Find an item" autocomplete="off"></div>
    <?php foreach ($byCat(fn($r) => $show === 'all' || $r['state'] === $show) as $cname => $list): ?>
      <section class="pgroup">
        <h2 class="group-title"><?= h($cname) ?></h2>
        <div class="list">
        <?php foreach ($list as $sn => $r): $p = $r['p']; ?>
          <div class="prod stk-row" data-find="<?= h(mb_strtolower($p['name'] . ' ' . $sn)) ?>">
            <div class="info">
              <a href="?do=history&amp;sn=<?= h(urlencode($sn)) ?>" title="See the history of this item"><?= h($p['name']) ?></a>
              <small><?= h($sn) ?></small>
            </div>
            <div class="stk-have">
              <?php foreach ($r['vars'] as $v => [$q, $vs]): ?>
                <div><?php if ($v !== ''): ?><span class="stk-v"><?= h($v) ?></span><?php endif; ?><?= $num($q, $vs) ?><?= $chip($vs) ?></div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>
  <?php endif; ?>

  <details class="more stk-help">
    <summary>How stock works <small>read this once</small></summary>
    <div class="inner">
      <ul>
        <li><b>Orders take stock by themselves.</b> When an order is set to <b>Confirmed</b>, its items come out of stock. A Preparedness Bag takes 1 of every item inside it and 1 pouch in the colour the customer chose.</li>
        <li><b>Cancelled orders give it back.</b> Setting an order to Cancelled (or back to New), or deleting it, puts the items back.</li>
        <li><b>Deliveries:</b> when stock comes in, use <a href="?do=arrived">Stock arrived</a>.</li>
        <li><b>Damaged, expired or lost:</b> use <a href="?do=remove">Remove items</a>, so the numbers stay true.</li>
        <li><b>Once a month:</b> <a href="?do=count">Count the shelf</a>. If a number is wrong, type the real number and save.</li>
        <li><b>On the website:</b> at 0 an item shows <b>Sold out</b> (each pouch colour separately). When it is running low, customers see "Only 3 left".</li>
        <li><b>Running low</b> means at or below the warning number you set for each item when counting (5 if you don't change it). The Home page reminds you.</li>
      </ul>
    </div>
  </details>

<?php elseif ($do === 'arrived'): ?>

  <div class="card stk-intro"><h2>Stock arrived</h2>
    <p>Type <b>how many arrived</b> next to each item in the delivery. Leave the others empty. Then press <b>Add to stock</b> at the bottom.</p></div>
  <div class="search"><input type="search" id="find" placeholder="Find an item" aria-label="Find an item" autocomplete="off"></div>
  <form method="post" id="stk-form" data-warn>
    <?= csrf_field() ?><input type="hidden" name="action" value="arrived">
    <?php foreach ($byCat(fn($r) => true) as $cname => $list): ?>
      <section class="pgroup">
        <h2 class="group-title"><?= h($cname) ?></h2>
        <div class="list">
        <?php foreach ($list as $sn => $r): $p = $r['p']; ?>
          <div class="prod stk-row" data-find="<?= h(mb_strtolower($p['name'] . ' ' . $sn)) ?>">
            <div class="info"><span class="stk-name"><?= h($p['name']) ?></span><small><?= h($sn) ?></small></div>
            <div class="stk-inputs">
              <?php foreach ($r['vars'] as $v => [$q, $vs]): ?>
                <label><span><b><?= $v !== '' ? h($v) . ': a' : 'A' ?>rrived</b></span>
                  <input type="text" inputmode="numeric" pattern="[0-9]*" name="in[<?= h($sn) ?>][<?= h($v === '' ? '_' : $v) ?>]" aria-label="How many <?= h($label($p, $v)) ?> arrived" data-now="<?= $q === null ? '' : (int)$q ?>">
                  <small class="stk-after">In stock now: <?= $q === null ? '–' : (int)$q ?></small></label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>
    <div class="card">
      <div class="field" style="margin:0"><label for="a-note">Note <span class="hint">optional, e.g. supplier or receipt no.</span></label>
        <input type="text" id="a-note" name="note" maxlength="200" placeholder="e.g. From supplier, DR no. 1234"></div>
    </div>
    <div class="sticky-save actions"><button class="btn primary" type="submit" data-busy="Saving…">Add to stock</button><span class="hint" id="stk-sum" style="margin:0">Nothing typed yet.</span></div>
  </form>

<?php elseif ($do === 'count'): ?>

  <div class="card stk-intro"><h2>Count the shelf</h2>
    <p>Count each item on the shelf and type the number under <b>You counted</b>. The page tells you right away if it matches what the system says.</p>
    <p class="hint" style="margin:6px 0 0">Leave an item empty if you didn't count it; it stays as it is. Press <b>Save count</b> at the bottom when you are done.</p></div>
  <div class="search"><input type="search" id="find" placeholder="Find an item" aria-label="Find an item" autocomplete="off"></div>
  <form method="post" id="stk-form" data-warn>
    <?= csrf_field() ?><input type="hidden" name="action" value="count">
    <?php foreach ($byCat(fn($r) => true) as $cname => $list): ?>
      <section class="pgroup">
        <h2 class="group-title"><?= h($cname) ?></h2>
        <div class="list">
        <?php foreach ($list as $sn => $r): $p = $r['p']; ?>
          <div class="prod stk-row" data-find="<?= h(mb_strtolower($p['name'] . ' ' . $sn)) ?>">
            <div class="info"><span class="stk-name"><?= h($p['name']) ?></span><small><?= h($sn) ?></small></div>
            <div class="stk-inputs">
              <?php foreach ($r['vars'] as $v => [$q, $vs]): ?>
                <label><span><b><?= $v !== '' ? h($v) . ': y' : 'Y' ?>ou counted</b></span>
                  <input type="text" inputmode="numeric" pattern="[0-9]*" name="qty[<?= h($sn) ?>][<?= h($v === '' ? '_' : $v) ?>]" aria-label="You counted: <?= h($label($p, $v)) ?>" data-now="<?= $q === null ? '' : (int)$q ?>">
                  <small class="stk-sys">System says <?= $q === null ? '–' : (int)$q ?></small>
                  <small class="stk-diff"></small></label>
              <?php endforeach; ?>
              <label class="stk-low"><span>Low at</span>
                <input type="text" inputmode="numeric" pattern="[0-9]*" name="low[<?= h($sn) ?>]" value="<?= (int)$p['low_stock'] ?>" aria-label="Warn when <?= h($p['name']) ?> is at or below"></label>
            </div>
          </div>
        <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>
    <div class="card">
      <div class="field" style="margin:0"><label for="c-note">Note <span class="hint">optional</span></label>
        <input type="text" id="c-note" name="note" maxlength="200" placeholder="e.g. Monthly count, October"></div>
      <p class="hint" style="margin:8px 0 0"><b>Low at</b>: the item shows as <em>Running low</em> (and "Only … left" on the website) when it reaches this number.</p>
    </div>
    <div class="sticky-save actions"><button class="btn primary" type="submit" data-busy="Saving…">Save count</button><span class="hint" id="stk-sum" style="margin:0">Nothing typed yet.</span></div>
  </form>

<?php elseif ($do === 'remove'): ?>

  <div class="card stk-intro"><h2>Remove items</h2>
    <p>For items that are <b>damaged, expired, lost</b> or <b>used</b> (display, demo, training, donation). They come out of stock and the reason is kept in the History.</p></div>
  <form class="card" method="post" data-warn>
    <?= csrf_field() ?><input type="hidden" name="action" value="remove">
    <div class="field"><label for="r-item">Which item?</label>
      <select id="r-item" name="item" required><option value="">Choose…</option>
        <?php foreach ($byCat(fn($r) => true) as $cname => $list): ?>
          <optgroup label="<?= h($cname) ?>">
          <?php foreach ($list as $sn => $r): foreach ($r['vars'] as $v => [$q, $vs]): ?>
            <option value="<?= h($sn . '|' . $v) ?>"><?= h($label($r['p'], $v)) ?> · <?= $q === null ? 'not counted' : (int)$q . ' in stock' ?></option>
          <?php endforeach; endforeach; ?>
          </optgroup>
        <?php endforeach; ?>
      </select></div>
    <div class="row2">
      <div class="field"><label for="r-qty">How many?</label><input type="number" id="r-qty" name="qty" min="1" max="100000" required inputmode="numeric"></div>
      <div class="field"><label for="r-why">Why?</label>
        <select id="r-why" name="why"><?php foreach (STOCK_REMOVE_WHY as $w): ?><option><?= h($w) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="field"><label for="r-note">Note <span class="hint">optional</span></label><input type="text" id="r-note" name="note" maxlength="200" placeholder="e.g. Water damage in storage"></div>
    <button class="btn primary" type="submit" data-busy="Saving…">Remove from stock</button>
  </form>

<?php else: /* history */
    $where = []; $args = [];
    $orderFilter = (int)($_GET['order'] ?? 0);
    if ($orderFilter) { $where[] = 'm.order_id = ?'; $args[] = $orderFilter; }
    $snFilter = (string)($_GET['sn'] ?? '');
    if ($snFilter !== '' && isset($info['products'][$snFilter])) { $where[] = 'm.serial = ?'; $args[] = $snFilter; } else $snFilter = '';
    $st = $pdo->prepare('SELECT m.*, o.order_no FROM stock_moves m LEFT JOIN orders o ON o.id = m.order_id' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY m.created_at DESC, m.id DESC LIMIT 300');
    $st->execute($args);
    $moves = $st->fetchAll();
    $icon = ['received' => '📦', 'sold' => '🛒', 'returned' => '↩️', 'count' => '📋', 'damaged' => '🗑️', 'other' => '•']; ?>
  <div class="card stk-intro"><h2>History<?= $snFilter !== '' ? ' · ' . h($info['products'][$snFilter]['name']) : '' ?></h2>
    <p>Every change to the stock: deliveries, orders, cancellations, shelf counts and removed items, newest first.</p>
    <?php if ($orderFilter || $snFilter !== ''): ?><p class="hint" style="margin:6px 0 0"><a href="?do=history">Show the history of all items</a></p><?php endif; ?></div>
  <div class="list">
    <?php if (!$moves): ?><div class="empty"><p>No stock changes yet.</p></div><?php endif; ?>
    <?php foreach ($moves as $m): $p = $info['products'][$m['serial']] ?? ['name' => $m['serial']]; $c = (int)$m['change_qty']; ?>
      <div class="stk-move">
        <span class="stk-ic" aria-hidden="true"><?= $icon[$m['reason']] ?? '•' ?></span>
        <div class="info">
          <b><?= h($p['name']) ?><?= $m['variant'] !== '' ? ' (' . h($m['variant']) . ')' : '' ?></b>
          <small><?= h(STOCK_REASONS[$m['reason']] ?? $m['reason']) ?><?= $m['order_id'] && $m['order_no'] && sees_sales($me) ? ' · <a href="order.php?id=' . (int)$m['order_id'] . '">' . h($m['order_no']) . '</a>' : '' ?><?= $m['note'] !== '' && !str_starts_with($m['note'], 'Order ') ? ' · ' . h($m['note']) : '' ?></small>
          <small><?= h(date('M j, Y g:i A', strtotime($m['created_at']))) ?><?= $m['by_name'] !== '' ? ' · ' . h($m['by_name']) : '' ?></small>
        </div>
        <div class="stk-change"><b class="<?= $c > 0 ? 'plus' : ($c < 0 ? 'minus' : '') ?>"><?= $c > 0 ? '+' . $c : ($c === 0 ? '±0' : '−' . abs($c)) ?></b><small><?= (int)$m['qty_after'] ?> left</small></div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<script>
(function () {
  var box = document.getElementById('find');
  if (box) box.addEventListener('input', function () {
    var q = box.value.trim().toLowerCase();
    document.querySelectorAll('.prod').forEach(function (r) { r.hidden = q !== '' && r.dataset.find.indexOf(q) < 0; });
    document.querySelectorAll('.pgroup').forEach(function (g) { g.hidden = !g.querySelector('.prod:not([hidden])'); });
  });
  // Live feedback while typing: what the number will become / does it match
  var form = document.getElementById('stk-form');
  if (!form) return;
  var arrived = form.querySelector('[name=action]').value === 'arrived';
  function update(inp) {
    var v = inp.value.trim(), now = inp.dataset.now, out = inp.parentNode.querySelector(arrived ? '.stk-after' : '.stk-diff');
    if (!out) return;
    inp.classList.remove('bad');
    if (v === '') { out.textContent = arrived ? 'In stock now: ' + (now === '' ? '–' : now) : ''; out.className = arrived ? 'stk-after' : 'stk-diff'; return; }
    if (!/^\d+$/.test(v)) { out.textContent = 'numbers only'; out.className = (arrived ? 'stk-after' : 'stk-diff') + ' bad'; inp.classList.add('bad'); return; }
    var n = parseInt(v, 10);
    if (arrived) { out.textContent = 'New total: ' + ((now === '' ? 0 : parseInt(now, 10)) + n); out.className = 'stk-after plus'; return; }
    if (now === '') { out.textContent = 'first count'; out.className = 'stk-diff first'; return; }
    var d = n - parseInt(now, 10);
    out.textContent = d === 0 ? '✓ matches' : (d < 0 ? (-d) + ' missing' : d + ' extra');
    out.className = 'stk-diff ' + (d === 0 ? 'ok' : 'off');
  }
  function summary() {
    var filled = 0, diff = 0, total = 0;
    form.querySelectorAll('input[name^="' + (arrived ? 'in[' : 'qty[') + '"]').forEach(function (i) {
      var v = i.value.trim(); if (!/^\d+$/.test(v)) return;
      filled++; total += parseInt(v, 10);
      if (!arrived && i.dataset.now !== '' && parseInt(v, 10) !== parseInt(i.dataset.now, 10)) diff++;
    });
    var s = document.getElementById('stk-sum');
    if (!s) return;
    s.textContent = !filled ? 'Nothing typed yet.' : arrived ? filled + (filled === 1 ? ' item, ' : ' items, ') + total + ' pieces in total.'
      : filled + (filled === 1 ? ' item counted' : ' items counted') + (diff ? ', ' + diff + ' different from the system.' : ', all match.');
  }
  form.addEventListener('input', function (e) { if (e.target.matches('input[data-now]')) { update(e.target); summary(); } });
})();
</script>
<?php page_bottom();
