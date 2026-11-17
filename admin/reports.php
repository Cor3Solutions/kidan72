<?php
/* KIDAN 72 — Admin: sales reports by month, year or all time,
   on screen and as a formatted Excel file. */
require __DIR__ . '/_inc.php';
require __DIR__ . '/xlsx.php';
$me = require_login();
$pdo = db();

/* ---------- which period ---------- */
$months = $pdo->query("SELECT DISTINCT DATE_FORMAT(created_at, '%Y-%m') FROM orders ORDER BY 1 DESC")->fetchAll(PDO::FETCH_COLUMN);
$thisMonth = date('Y-m');
if (!in_array($thisMonth, $months, true)) array_unshift($months, $thisMonth);
$years = array_values(array_unique(array_map(fn($m) => substr($m, 0, 4), $months)));

$p = (string)($_GET['p'] ?? $thisMonth);
if (preg_match('/^\d{4}-\d{2}$/', $p) && checkdate((int)substr($p, 5, 2), 1, (int)substr($p, 0, 4))) {
    $from = "$p-01 00:00:00"; $to = date('Y-m-d H:i:s', strtotime("$from +1 month"));
    $label = date('F Y', strtotime($from)); $short = date('M Y', strtotime($from));
    $prevP = date('Y-m', strtotime("$from -1 month")); $prevFrom = "$prevP-01 00:00:00";
} elseif (preg_match('/^\d{4}$/', $p)) {
    $from = "$p-01-01 00:00:00"; $to = ($p + 1) . '-01-01 00:00:00'; $label = "Year $p"; $short = $p;
    $prevP = (string)($p - 1); $prevFrom = "$prevP-01-01 00:00:00";
} else {
    $p = 'all'; $from = '2000-01-01 00:00:00'; $to = '2100-01-01 00:00:00'; $label = 'All time'; $short = 'All time';
    $prevP = null; $prevFrom = null;
}

/* ---------- numbers ---------- */
function period_totals(PDO $pdo, string $from, string $to): array {
    $st = $pdo->prepare("SELECT COUNT(*) AS orders,
            COALESCE(SUM(total), 0) AS sales,
            COALESCE(SUM(CASE WHEN status IN ('paid','delivered') THEN total END), 0) AS collected,
            COALESCE(SUM(CASE WHEN status IN ('new','confirmed') THEN total END), 0) AS pending,
            COALESCE(SUM(delivery_fee), 0) AS fees, COALESCE(SUM(discount), 0) AS discounts,
            COALESCE(SUM(item_count), 0) AS items, SUM(total IS NULL) AS unpriced
        FROM orders WHERE status <> 'cancelled' AND created_at >= ? AND created_at < ?");
    $st->execute([$from, $to]);
    return $st->fetch();
}
$t = period_totals($pdo, $from, $to);
$prev = $prevFrom ? period_totals($pdo, $prevFrom, $from) : null;

$st = $pdo->prepare("SELECT status, COUNT(*) AS n, COALESCE(SUM(total), 0) AS t FROM orders WHERE created_at >= ? AND created_at < ? GROUP BY status");
$st->execute([$from, $to]);
$byStatus = [];
foreach ($st as $r) $byStatus[$r['status']] = $r;
$cancelled = (int)($byStatus['cancelled']['n'] ?? 0);

$st = $pdo->prepare("SELECT oi.serial, oi.name, SUM(oi.qty) AS qty, COALESCE(SUM(oi.line_total), 0) AS sales, COUNT(DISTINCT o.id) AS orders
                     FROM order_items oi JOIN orders o ON o.id = oi.order_id
                     WHERE o.status <> 'cancelled' AND o.created_at >= ? AND o.created_at < ?
                     GROUP BY oi.serial, oi.name ORDER BY qty DESC, sales DESC");
$st->execute([$from, $to]);
$products = $st->fetchAll();

$st = $pdo->prepare("SELECT COALESCE(NULLIF(province, ''), 'No address') AS province, COUNT(*) AS n, COALESCE(SUM(total), 0) AS t
                     FROM orders WHERE status <> 'cancelled' AND created_at >= ? AND created_at < ? GROUP BY 1 ORDER BY n DESC, t DESC");
$st->execute([$from, $to]);
$places = $st->fetchAll();

$st = $pdo->prepare("SELECT channel, COUNT(*) AS n, COALESCE(SUM(total), 0) AS t FROM orders
                     WHERE status <> 'cancelled' AND created_at >= ? AND created_at < ? GROUP BY channel ORDER BY n DESC");
$st->execute([$from, $to]);
$channels = $st->fetchAll();

$st = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE created_at >= ? AND created_at < ?");
$st->execute([$from, $to]);
$newCustomers = (int)$st->fetchColumn();
$st = $pdo->prepare("SELECT COUNT(DISTINCT customer_id) FROM orders WHERE status <> 'cancelled' AND customer_id IS NOT NULL AND created_at >= ? AND created_at < ?");
$st->execute([$from, $to]);
$buyers = (int)$st->fetchColumn();

// Sales per day (month) or per month (year / all time), for the bar list
$byDay = $p !== 'all' && strlen($p) === 7;
$st = $pdo->prepare("SELECT DATE_FORMAT(created_at, '" . ($byDay ? '%Y-%m-%d' : '%Y-%m') . "') AS k, COUNT(*) AS n, COALESCE(SUM(total), 0) AS t
                     FROM orders WHERE status <> 'cancelled' AND created_at >= ? AND created_at < ? GROUP BY k ORDER BY k");
$st->execute([$from, $to]);
$series = $st->fetchAll();

$avg = (int)$t['orders'] ? (float)$t['sales'] / (int)$t['orders'] : 0;
$change = function ($now, $before) {
    if ($before === null || (float)$before == 0.0) return '';
    $pct = ((float)$now - (float)$before) / (float)$before * 100;
    return ($pct >= 0 ? '▲ ' : '▼ ') . number_format(abs($pct), 0) . '%';
};

// Sample orders (Downloads & backup → Sample data) are counted like real ones; say so clearly
$st = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE ip = 'sample' AND created_at >= ? AND created_at < ?");
$st->execute([$from, $to]);
$sampleN = (int)$st->fetchColumn();

// Sales per day (a month: every day, also the quiet ones) or per month
$seriesMap = [];
foreach ($series as $r) $seriesMap[$r['k']] = $r;
$points = [];
if ($byDay) {
    $endTs = min(strtotime($to), strtotime('tomorrow'));
    for ($d = strtotime($from); $d < strtotime($to); $d = strtotime('+1 day', $d)) {
        if ($d >= $endTs && $points) break;       // this month: up to today
        $k = date('Y-m-d', $d);
        $points[] = [date('j', $d), date('D, M j', $d), (int)($seriesMap[$k]['n'] ?? 0), (float)($seriesMap[$k]['t'] ?? 0)];
    }
} else {
    $first = $p === 'all' ? ($series[0]['k'] ?? date('Y-m')) : "$p-01";
    $last = $p === 'all' ? ($series ? end($series)['k'] : date('Y-m')) : "$p-12";
    for ($m = strtotime(substr($first, 0, 7) . '-01'); $m <= strtotime(substr($last, 0, 7) . '-01'); $m = strtotime('+1 month', $m)) {
        $k = date('Y-m', $m);
        if ($p !== 'all' && $m > time()) break;
        $points[] = [$p === 'all' ? date('M Y', $m) : date('M', $m), date('Y', $m), (int)($seriesMap[$k]['n'] ?? 0), (float)($seriesMap[$k]['t'] ?? 0)];
    }
}
$seriesName = $byDay ? 'Sales per day' : 'Sales per month';

/* ---------- Excel download ----------
   Sheet 1 "Summary": a one-page report (header, key numbers with the change
   from the period before, a sales chart, top products, places, status and
   how orders came in). Then the full lists: Orders, Products, Customers and
   the sales per day / month that the chart is drawn from. */
if (($_GET['download'] ?? '') === 'xlsx') {
    $x = new Xlsx();
    $S = fn($v, $s) => ['v' => $v, 's' => $s];
    $who = $me['display_name'] ?: $me['username'];
    $footer = "KIDAN 72 sales report · $label";
    $prevShort = $prevP === null ? '' : (strlen($prevP) === 7 ? date('M Y', strtotime("$prevP-01")) : $prevP);

    // The change from the period before, as card text: [text, style]
    $vs = function ($now, $before) use ($prev, $prevShort) {
        if ($prev === null) return ['', 'flat'];
        if ((float)$before == 0.0) return ['', 'flat'];          // nothing to compare with
        $pct = ((float)$now - (float)$before) / (float)$before * 100;
        if (abs($pct) < 0.5) return ["Same as $prevShort", 'flat'];
        return [($pct > 0 ? '▲ ' : '▼ ') . number_format(abs($pct), 0) . "% vs $prevShort", $pct > 0 ? 'up' : 'down'];
    };
    $prevBuyers = null;
    if ($prev !== null) {
        $q = $pdo->prepare("SELECT COUNT(DISTINCT customer_id) FROM orders WHERE status <> 'cancelled' AND customer_id IS NOT NULL AND created_at >= ? AND created_at < ?");
        $q->execute([$prevFrom, $from]);
        $prevBuyers = (int)$q->fetchColumn();
    }


    /* ----- Sheet 1: Summary ----- */
    $W = 7;   // columns A–G: left table A–C, gap D, right table E–G
    $blank = fn() => array_fill(0, $W, null);
    $rows = [];
    $merge = ['A1:G1', 'A2:G2'];
    $heights = [1 => 38, 2 => 22, 3 => 12];
    $banner = $blank(); $banner[0] = $S('KIDAN 72  ·  Sales report', 'banner');
    for ($c = 1; $c < $W; $c++) $banner[$c] = $S('', 'banner');
    $line2 = $blank(); $line2[0] = $S($label . '   ·   Made ' . date('M j, Y g:i A') . ' by ' . $who . ($sampleN ? "   ·   INCLUDES $sampleN SAMPLE ORDERS (made-up data)" : ''), 'bannersub');
    for ($c = 1; $c < $W; $c++) $line2[$c] = $S('', 'bannersub');
    $rows[] = $banner; $rows[] = $line2; $rows[] = $blank();

    // Key numbers: 2 rows of 4 cards (A, B:C, E, F:G)
    $cards = [
        [['SALES', (float)$t['sales'], 'kpi', $vs($t['sales'], $prev['sales'] ?? null)],
         ['ORDERS', (int)$t['orders'], 'kpiint', $vs($t['orders'], $prev['orders'] ?? null)],
         ['AVERAGE ORDER', round($avg, 0), 'kpi', $prev ? $vs($avg, (int)$prev['orders'] ? (float)$prev['sales'] / (int)$prev['orders'] : 0) : ['', 'flat']],
         ['PAID SO FAR', (float)$t['collected'], 'kpi', [(float)$t['pending'] > 0 ? '₱' . number_format((float)$t['pending']) . ' waiting for payment' : 'Nothing waiting for payment', 'flat']]],
        [['ITEMS SOLD', (int)$t['items'], 'kpiint', $vs($t['items'], $prev['items'] ?? null)],
         ['CUSTOMERS WHO ORDERED', $buyers, 'kpiint', $vs($buyers, $prevBuyers)],
         ['NEW CUSTOMERS', $newCustomers, 'kpiint', ['First order with KIDAN 72', 'flat']],
         ['DELIVERY FEES', (float)$t['fees'], 'kpi', [(float)$t['discounts'] > 0 ? '₱' . number_format((float)$t['discounts']) . ' in discounts' : 'No discounts given', 'flat']]],
    ];
    $slots = [[0, 0], [1, 2], [4, 4], [5, 6]];
    foreach ($cards as $set) {
        $r0 = count($rows) + 1;
        $lab = $blank(); $val = $blank(); $chg = $blank();
        foreach ($set as $k => [$name, $value, $style, [$ctext, $cstyle]]) {
            [$a, $b] = $slots[$k];
            for ($c = $a; $c <= $b; $c++) { $lab[$c] = $S('', 'kpilabel'); $val[$c] = $S('', $style); $chg[$c] = $S('', $cstyle); }
            $lab[$a] = $S($name, 'kpilabel'); $val[$a] = $S($value, $style); $chg[$a] = $S($ctext, $cstyle);
            if ($a !== $b) foreach ([$r0, $r0 + 1, $r0 + 2] as $rr) $merge[] = Xlsx_col($a) . $rr . ':' . Xlsx_col($b) . $rr;
        }
        $rows[] = $lab; $rows[] = $val; $rows[] = $chg; $rows[] = $blank();
        $heights[$r0] = 22; $heights[$r0 + 1] = 30; $heights[$r0 + 2] = 20; $heights[$r0 + 3] = 10;
    }

    // Chart
    $chartTop = count($rows);              // 0-based row where the chart starts
    $sec = $blank(); $sec[0] = $S($seriesName, 'section');
    for ($c = 1; $c < $W; $c++) $sec[$c] = $S('', 'section');
    $rows[] = $sec; $heights[count($rows)] = 22;
    for ($i = 0; $i < 16; $i++) $rows[] = $blank();
    $rows[] = $blank();

    // Two tables side by side
    $pair = function (string $lt, array $lh, array $left, string $rt, array $rh, array $right) use (&$rows, &$heights, $blank, $S, $W) {
        $sec = $blank();
        $sec[0] = $S($lt, 'section'); $sec[1] = $S('', 'section'); $sec[2] = $S('', 'section');
        $sec[4] = $S($rt, 'section'); $sec[5] = $S('', 'section'); $sec[6] = $S('', 'section');
        $rows[] = $sec; $heights[count($rows)] = 22;
        $h = $blank();
        foreach ($lh as $i => $v) $h[$i] = $S($v, $i ? 'headr' : 'head');
        foreach ($rh as $i => $v) $h[4 + $i] = $S($v, $i ? 'headr' : 'head');
        $rows[] = $h; $heights[count($rows)] = 20;
        $first = count($rows) + 1;
        $n = max(count($left), count($right), 1);
        for ($i = 0; $i < $n; $i++) {
            $r = $blank();
            foreach ($left[$i] ?? [] as $c => $v) $r[$c] = $v;
            foreach ($right[$i] ?? [] as $c => $v) $r[4 + $c] = $v;
            $rows[] = $r;
        }
        if (!$left) $rows[$first - 1][0] = $S('Nothing in this period', 'note');
        if (!$right) $rows[$first - 1][4] = $S('Nothing in this period', 'note');
        $rows[] = $blank();
        return [$first, $first + count($left) - 1, $first + count($right) - 1];
    };
    $top = array_slice($products, 0, 8);
    $left = array_map(fn($r) => [$S($r['name'], 'text'), $S((int)$r['qty'], 'int'), $S((float)$r['sales'], 'peso')], $top);
    $right = array_map(fn($r) => [$S($r['province'], 'text'), $S((int)$r['n'], 'int'), $S((float)$r['t'], 'peso')], array_slice($places, 0, 8));
    [$f1, $l1, $r1] = $pair('Top products', ['Product', 'Qty', 'Sales'], $left, 'Where orders came from', ['Province', 'Orders', 'Sales'], $right);
    $bars = [];
    if ($l1 >= $f1) $bars[] = ["C$f1:C$l1", 'F2A27C'];
    if ($r1 >= $f1) $bars[] = ["G$f1:G$r1", 'F2A27C'];

    $left = []; foreach (STATUS_LABELS as $k => $lab) if (isset($byStatus[$k])) $left[] = [$S($lab, $k === 'cancelled' ? 'muted' : 'text'), $S((int)$byStatus[$k]['n'], 'int'), $S((float)$byStatus[$k]['t'], 'peso')];
    $right = array_map(fn($r) => [$S(ORDER_CHANNELS[$r['channel']] ?? ($r['channel'] ?: 'Other'), 'text'), $S((int)$r['n'], 'int'), $S((float)$r['t'], 'peso')], $channels);
    [$f2, $l2, $r2] = $pair('Order status', ['Status', 'Orders', 'Total'], $left, 'How orders came in', ['Came in by', 'Orders', 'Sales'], $right);
    if ($r2 >= $f2) $bars[] = ["F$f2:F$r2", 'C9BBA6'];

    $note = $blank();
    $note[0] = $S('Cancelled orders' . ($cancelled ? " ($cancelled)" : '') . ' are not counted in the totals.'
                  . ((int)$t['unpriced'] ? ' ' . (int)$t['unpriced'] . ' order(s) had items without a price yet, so the totals are lower than the final amounts.' : '')
                  . ' The full lists are on the next sheets: Orders, Products, Customers and ' . $seriesName . '.', 'note');
    $rows[] = $note; $merge[] = 'A' . count($rows) . ':G' . count($rows); $heights[count($rows)] = 30;

    $seriesSheet = $seriesName;
    $x->sheet('Summary', $rows, [
        'widths' => [36, 8, 15, 3, 28, 9, 15], 'grid' => false, 'onepage' => true, 'merge' => $merge, 'heights' => $heights, 'bars' => $bars, 'footer' => $footer,
        'chart' => ['title' => $seriesName . ' · ' . $label . ($byDay ? ' (day of the month)' : ''), 'sheet' => $seriesSheet, 'cats' => 'A4:A' . (3 + count($points)), 'vals' => 'D4:D' . (3 + count($points)),
                    'labels' => array_column($points, 0), 'values' => array_column($points, 3), 'from' => [0, $chartTop + 1], 'to' => [7, $chartTop + 17]],
    ]);

    /* ----- Orders ----- */
    $st = $pdo->prepare('SELECT * FROM orders WHERE created_at >= ? AND created_at < ? ORDER BY created_at, id');
    $st->execute([$from, $to]);
    $orders = $st->fetchAll();
    $items = [];
    $st = $pdo->prepare('SELECT oi.order_id, oi.qty, oi.name FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE o.created_at >= ? AND o.created_at < ? ORDER BY oi.id');
    $st->execute([$from, $to]);
    foreach ($st as $r) $items[$r['order_id']][] = $r['qty'] . ' × ' . $r['name'];
    $heads = ['Order no.', 'Date', 'Status', 'Customer', 'Mobile', 'City / Municipality', 'Province', 'Items', 'Qty', 'Items total', 'Delivery fee', 'Discount', 'Total',
              'Paid by', 'Reference no.', 'Came in by', 'Email', 'Barangay / street', 'Customer note', 'Private note'];
    $rows = [[$S("Orders · $label", 'title')], [$S(count($orders) . ' orders, including cancelled. Use the arrows in the headings to filter or sort. Printing shows the first 13 columns.', 'sub')],
        array_map(fn($h, $i) => $S($h, $i >= 8 && $i <= 12 ? 'headr' : 'head'), $heads, array_keys($heads))];
    $sum = ['n' => 0, 'sub' => 0, 'fee' => 0, 'disc' => 0, 'tot' => 0];
    foreach ($orders as $o) {
        $c = $o['status'] === 'cancelled';
        $rows[] = [$o['order_no'], $S(strtotime($o['created_at']), 'date'), $S(STATUS_LABELS[$o['status']] ?? $o['status'], $c ? 'muted' : 'text'), $o['customer'], $o['mobile'],
                   $o['city'], $o['province'], $S(implode("\n", $items[$o['id']] ?? []), 'wrap'), $S((int)$o['item_count'], 'int'),
                   $o['subtotal'] === null ? 'to follow' : $S((float)$o['subtotal'], 'peso'), $S((float)$o['delivery_fee'], 'peso'), $S((float)$o['discount'], 'peso'),
                   $o['total'] === null ? 'to follow' : $S((float)$o['total'], 'peso'),
                   $o['payment_method'], $o['payment_ref'], ORDER_CHANNELS[$o['channel']] ?? $o['channel'], $o['email'],
                   trim($o['barangay'] . ($o['street'] !== '' ? ', ' . $o['street'] : ''), ', '), $S($o['notes'], 'wrap'), $S($o['admin_note'], 'wrap')];
        if (!$c) { $sum['n'] += (int)$o['item_count']; $sum['sub'] += (float)$o['subtotal']; $sum['fee'] += (float)$o['delivery_fee']; $sum['disc'] += (float)$o['discount']; $sum['tot'] += (float)$o['total']; }
    }
    $tr = [$S('Total (not counting cancelled)', 'totallabel')];
    for ($i = 1; $i < 8; $i++) $tr[] = $S('', 'totallabel');
    $rows[] = array_merge($tr, [$S($sum['n'], 'intbold'), $S($sum['sub'], 'pesobold'), $S($sum['fee'], 'pesobold'), $S($sum['disc'], 'pesobold'), $S($sum['tot'], 'pesobold')]);
    $x->sheet('Orders', $rows, ['widths' => [11, 21, 10, 20, 13, 17, 12, 34, 6, 12, 11, 10, 12, 13, 16, 20, 24, 24, 28, 28], 'header' => 3, 'filter' => true,
                                'landscape' => true, 'printcols' => 13, 'heights' => [1 => 24, 3 => 30], 'footer' => $footer]);

    /* ----- Products ----- */
    $rows = [[$S("Products sold · $label", 'title')], [$S('Not counting cancelled orders. Sorted by quantity. Colours (e.g. pouch colour) are listed separately.', 'sub')],
        [$S('Serial', 'head'), $S('Product', 'head'), $S('Quantity', 'headr'), $S('Orders', 'headr'), $S('Sales', 'headr'), $S('Share of sales', 'headr')]];
    $q = 0; $tot = 0; foreach ($products as $r) $tot += (float)$r['sales'];
    foreach ($products as $r) { $rows[] = [$r['serial'], $r['name'], $S((int)$r['qty'], 'int'), $S((int)$r['orders'], 'int'), $S((float)$r['sales'], 'peso'), $S($tot ? (float)$r['sales'] / $tot : 0, 'pct')]; $q += (int)$r['qty']; }
    $rows[] = [$S('Total', 'totallabel'), $S('', 'totallabel'), $S($q, 'intbold'), $S('', 'totallabel'), $S($tot, 'pesobold'), $S('', 'totallabel')];
    $n = count($products);
    $x->sheet('Products', $rows, ['widths' => [14, 42, 11, 10, 15, 15], 'header' => 3, 'filter' => true, 'heights' => [1 => 24, 3 => 22], 'footer' => $footer,
                                  'bars' => $n ? [['E4:E' . (3 + $n), 'F2A27C']] : []]);

    /* ----- Customers who ordered in the period ----- */
    $st = $pdo->prepare("SELECT c.name, c.mobile, c.email, c.type, c.organization, c.province, c.city, c.created_at,
                                COUNT(o.id) AS n, COALESCE(SUM(o.total), 0) AS t, MAX(o.created_at) AS last_order,
                                (SELECT COUNT(*) FROM orders x WHERE x.customer_id = c.id AND x.status <> 'cancelled') AS all_orders
                         FROM orders o JOIN customers c ON c.id = o.customer_id
                         WHERE o.status <> 'cancelled' AND o.created_at >= ? AND o.created_at < ? GROUP BY c.id ORDER BY t DESC, c.name");
    $st->execute([$from, $to]);
    $rows = [[$S("Customers who ordered · $label", 'title')], [$S('Sorted by amount spent in this period.', 'sub')],
        array_map(fn($h, $i) => $S($h, in_array($i, [7, 8, 10], true) ? 'headr' : 'head'), $hh = ['Name', 'Mobile', 'Email', 'Type', 'Office / school / barangay', 'Province', 'City / Municipality', 'Orders in period', 'Spent in period', 'Last order', 'Orders ever', 'Customer since'], array_keys($hh))];
    foreach ($st as $c) $rows[] = [$c['name'], $c['mobile'], $c['email'], $c['type'], $c['organization'], $c['province'], $c['city'],
                                   $S((int)$c['n'], 'int'), $S((float)$c['t'], 'peso'), $S(strtotime($c['last_order']), 'day'), $S((int)$c['all_orders'], 'int'), $S(strtotime($c['created_at']), 'day')];
    $n = count($rows) - 3;
    $x->sheet('Customers', $rows, ['widths' => [24, 14, 26, 12, 22, 13, 18, 10, 15, 15, 9, 15], 'header' => 3, 'filter' => true, 'landscape' => true,
                                   'heights' => [1 => 24, 3 => 30], 'footer' => $footer, 'bars' => $n > 0 ? [['I4:I' . (3 + $n), 'F2A27C']] : []]);

    /* ----- Sales per day / month (the chart is drawn from this) ----- */
    $rows = [[$S("$seriesName · $label", 'title')], [$S('Not counting cancelled orders. The chart on the Summary sheet is drawn from this table.', 'sub')],
        [$S($byDay ? 'Day' : 'Month', 'head'), $S($byDay ? 'Date' : 'Year', 'head'), $S('Orders', 'headr'), $S('Sales', 'headr')]];
    $on = 0; $ot = 0;
    foreach ($points as [$a, $b, $cnt, $amt]) { $rows[] = [$a, $b, $S($cnt, 'int'), $S($amt, 'peso')]; $on += $cnt; $ot += $amt; }
    $rows[] = [$S('Total', 'totallabel'), $S('', 'totallabel'), $S($on, 'intbold'), $S($ot, 'pesobold')];
    $x->sheet($seriesSheet, $rows, ['widths' => [10, 16, 10, 16], 'header' => 3, 'heights' => [1 => 24, 3 => 20], 'footer' => $footer,
                                    'bars' => $points ? [['D4:D' . (3 + count($points)), 'F2A27C']] : []]);

    log_action("Downloaded the sales report ($label)");
    $x->download('kidan72-report-' . ($p === 'all' ? 'all-time' : $p) . '.xlsx');
}

require __DIR__ . '/charts.php';
page_top('Reports', 'reports.php', $me);
$money = fn($v) => money((float)$v);
$prevLabel = $prevP === null ? '' : (strlen($prevP) === 7 ? date('M Y', strtotime("$prevP-01")) : $prevP);
// "▲ 12% vs Aug 2026" as a small coloured pill
$pill = function ($now, $before) use ($prev, $prevLabel): string {
    if ($prev === null || (float)$before == 0.0) return '';
    $pct = ((float)$now - (float)$before) / (float)$before * 100;
    if (abs($pct) < 0.5) return '<span class="pill same">same as ' . h($prevLabel) . '</span>';
    return '<span class="pill ' . ($pct > 0 ? 'up' : 'down') . '">' . ($pct > 0 ? '▲' : '▼') . ' ' . number_format(abs($pct)) . '% <small>vs ' . h($prevLabel) . '</small></span>';
};
$prevAvg = $prev && (int)$prev['orders'] ? (float)$prev['sales'] / (int)$prev['orders'] : null;
$statusColor = ['new' => '#8A8279', 'confirmed' => '#D9A441', 'paid' => '#8FB3C9', 'delivered' => '#3F7A4A', 'cancelled' => '#D9CFC2'];
?>
<div class="rep-head">
  <h1>Reports</h1>
  <form method="get" id="period">
    <select name="p" aria-label="Period" onchange="this.form.submit()">
      <optgroup label="Month"><?php foreach ($months as $m): ?><option value="<?= h($m) ?>"<?= $p === $m ? ' selected' : '' ?>><?= h(date('F Y', strtotime("$m-01"))) ?><?= $m === $thisMonth ? ' (this month)' : '' ?></option><?php endforeach; ?></optgroup>
      <optgroup label="Year"><?php foreach ($years as $y): ?><option value="<?= h($y) ?>"<?= $p === $y ? ' selected' : '' ?>>Year <?= h($y) ?></option><?php endforeach; ?></optgroup>
      <option value="all"<?= $p === 'all' ? ' selected' : '' ?>>All time</option>
    </select>
    <noscript><button class="btn ghost" type="submit">Show</button></noscript>
  </form>
  <div class="rep-actions">
    <button class="btn ghost" type="button" onclick="window.print()">Print</button>
    <a class="btn primary" href="?p=<?= h($p) ?>&amp;download=xlsx">Excel</a>
  </div>
</div>

<?php if ($sampleN): ?><p class="rep-note">Includes <?= $sampleN ?> sample orders · <a href="sample-data.php">remove</a></p><?php endif; ?>

<?php if (!(int)$t['orders'] && !$cancelled): ?>
  <div class="list"><div class="empty"><p>No orders in <?= h($label) ?>.</p></div></div>
<?php else: ?>

<div class="kpis">
  <div class="kpi main"><span>Sales</span><b><?= h($money(round((float)$t['sales']))) ?></b><?= $pill($t['sales'], $prev['sales'] ?? null) ?><?= chart_spark(array_column($points, 3)) ?></div>
  <div class="kpi"><span>Orders</span><b><?= (int)$t['orders'] ?></b><?= $pill($t['orders'], $prev['orders'] ?? null) ?></div>
  <div class="kpi"><span>Average order</span><b><?= h($money(round($avg))) ?></b><?= $prevAvg !== null ? $pill($avg, $prevAvg) : '' ?></div>
  <div class="kpi"><span>Items sold</span><b><?= (int)$t['items'] ?></b><?= $pill($t['items'], $prev['items'] ?? null) ?></div>
</div>
<div class="kpi-mini">
  <span><i class="dot ok"></i>Paid <b><?= h(peso_short((float)$t['collected'])) ?></b></span>
  <?php if ((float)$t['pending'] > 0): ?><span><i class="dot wait"></i>Waiting for payment <b><?= h(peso_short((float)$t['pending'])) ?></b></span><?php endif; ?>
  <span><i class="dot"></i>Customers <b><?= $buyers ?></b><?= $newCustomers ? ' <small>(' . $newCustomers . ' new)</small>' : '' ?></span>
  <span><i class="dot"></i>Delivery fees <b><?= h(peso_short((float)$t['fees'])) ?></b></span>
  <?php if ((float)$t['discounts'] > 0): ?><span><i class="dot"></i>Discounts <b><?= h(peso_short((float)$t['discounts'])) ?></b></span><?php endif; ?>
</div>

<section class="card rep-card">
  <div class="rep-card-head"><h3><?= h($seriesName) ?></h3><span class="muted"><?= h($label) ?></span></div>
  <?= chart_columns(array_map(fn($pt) => [$pt[0], $pt[3], ($byDay ? $pt[1] : $pt[0] . ($p === 'all' ? '' : ' ' . $pt[1])) . ': ' . money($pt[3]) . ' · ' . $pt[2] . ' order' . ($pt[2] === 1 ? '' : 's')], $points), ['label' => $seriesName]) ?>
</section>

<div class="rep-grid">
  <section class="card rep-card">
    <div class="rep-card-head"><h3>Top products</h3><span class="muted">by sales</span></div>
    <?php $bySales = $products; usort($bySales, fn($a, $b) => (float)$b['sales'] <=> (float)$a['sales']); ?>
    <?= chart_hbars(array_map(fn($r) => [$r['name'], (float)$r['sales'], (int)$r['qty'] . ' sold'], array_slice($bySales, 0, 6))) ?>
  </section>
  <section class="card rep-card">
    <div class="rep-card-head"><h3>Where orders came from</h3><span class="muted">by sales</span></div>
    <?php $bySales = $places; usort($bySales, fn($a, $b) => (float)$b['t'] <=> (float)$a['t']); ?>
    <?= chart_hbars(array_map(fn($r) => [$r['province'], (float)$r['t'], (int)$r['n'] . ' order' . ((int)$r['n'] === 1 ? '' : 's')], array_slice($bySales, 0, 6))) ?>
  </section>
  <section class="card rep-card">
    <div class="rep-card-head"><h3>Order status</h3><span class="muted">number of orders</span></div>
    <?php $rows = []; foreach (STATUS_LABELS as $k => $lab) if (isset($byStatus[$k])) $rows[] = [$lab, (int)$byStatus[$k]['n'], $statusColor[$k], (int)$byStatus[$k]['n'] . ' · ' . peso_short((float)$byStatus[$k]['t'])]; ?>
    <?= chart_donut($rows, (string)array_sum(array_column($rows, 1)), 'orders') ?>
  </section>
  <section class="card rep-card">
    <div class="rep-card-head"><h3>How orders came in</h3><span class="muted">number of orders</span></div>
    <?php $rows = []; foreach (array_values($channels) as $i => $r) $rows[] = [ORDER_CHANNELS[$r['channel']] ?? ($r['channel'] ?: 'Other'), (int)$r['n'], CHART_COLORS[$i % count(CHART_COLORS)], (int)$r['n'] . ' · ' . peso_short((float)$r['t'])]; ?>
    <?= chart_donut($rows, (string)array_sum(array_column($rows, 1)), 'orders') ?>
  </section>
</div>

<p class="rep-foot">Cancelled orders are not counted<?= $cancelled ? " ($cancelled)" : '' ?>.<?= (int)$t['unpriced'] ? ' ' . (int)$t['unpriced'] . ' order(s) had items without a price yet.' : '' ?> Every order, product and customer is in the Excel file.</p>
<?php endif; ?>
<?php page_bottom();
