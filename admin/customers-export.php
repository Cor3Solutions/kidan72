<?php
/* KIDAN 72 — Admin: download the customer list (Excel) */
require __DIR__ . '/_inc.php';
require __DIR__ . '/xlsx.php';
$me = require_login();
$pdo = db();

$rows = $pdo->query("SELECT c.*, COUNT(o.id) AS orders,
                            COALESCE(SUM(CASE WHEN o.status <> 'cancelled' THEN o.total END), 0) AS spent,
                            MIN(o.created_at) AS first_order, MAX(o.created_at) AS last_order
                     FROM customers c LEFT JOIN orders o ON o.customer_id = c.id
                     GROUP BY c.id ORDER BY c.name")->fetchAll();
log_action('Downloaded the customer list');

$S = fn($v, $s) => ['v' => $v, 's' => $s];
$out = [[$S('KIDAN 72 customer list', 'title')],
        [$S(count($rows) . ' customers · made ' . date('M j, Y g:i A') . '. Total spent does not count cancelled orders.', 'sub')],
        array_map(fn($h) => $S($h, 'head'), ['Name', 'Mobile', 'Email', 'Type', 'Office / school / barangay', 'Province', 'City / Municipality', 'Barangay', 'Street',
                                            'Orders', 'Total spent', 'First order', 'Last order', 'Follow up on', 'Follow-up about', 'Customer since'])];
foreach ($rows as $c) {
    $out[] = [$c['name'], $c['mobile'], $c['email'], $c['type'], $c['organization'], $c['province'], $c['city'], $c['barangay'], $c['street'],
              $S((int)$c['orders'], 'int'), $S((float)$c['spent'], 'peso'),
              $c['first_order'] ? $S(strtotime($c['first_order']), 'day') : '', $c['last_order'] ? $S(strtotime($c['last_order']), 'day') : '',
              $c['follow_up_on'] ? $S(strtotime($c['follow_up_on']), 'day') : '', $c['follow_up_note'], $S(strtotime($c['created_at']), 'day')];
}
$x = new Xlsx();
$x->sheet('Customers', $out, ['widths' => [24, 15, 26, 13, 24, 14, 18, 18, 26, 8, 14, 13, 13, 13, 28, 14], 'header' => 3, 'filter' => true, 'landscape' => true, 'tall' => [3 => 30]]);
$x->download('kidan72-customers-' . date('Y-m-d') . '.xlsx');
