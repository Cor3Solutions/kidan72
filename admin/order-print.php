<?php
/* KIDAN 72 — Admin: printable packing slip for one order */
require __DIR__ . '/_inc.php';
$me = require_login();
$pdo = db();

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
$st->execute([$id]);
$o = $st->fetch();
if (!$o) { flash('That order was not found.', 'error'); redirect('orders.php'); }
$st = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id');
$st->execute([$id]);
$lines = $st->fetchAll();
$contact = setting('contact_email');
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Packing slip <?= h($o['order_no']) ?> · KIDAN 72</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700&family=Barlow:wght@400;600&family=IBM+Plex+Mono:wght@400;500&display=swap">
<style>
  :root { --ink: #17140F; --line: #DED2C0; --muted: #6B645C; }
  * { box-sizing: border-box; }
  body { margin: 0; background: #fff; color: var(--ink); font: 15px/1.45 "Barlow", Arial, sans-serif; }
  .page { max-width: 780px; margin: 0 auto; padding: 32px 24px; }
  .bar { display: flex; gap: 10px; justify-content: flex-end; margin-bottom: 20px; }
  .bar button, .bar a { font: 600 15px "Barlow", Arial, sans-serif; padding: 9px 16px; border: 1px solid var(--ink); background: var(--ink); color: #fff; cursor: pointer; text-decoration: none; }
  .bar a { background: #fff; color: var(--ink); }
  header { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; border-bottom: 3px solid var(--ink); padding-bottom: 12px; }
  header img { height: 46px; }
  h1 { font: 700 30px/1 "Barlow Condensed", Arial, sans-serif; text-transform: uppercase; margin: 0; text-align: right; }
  .mono { font-family: "IBM Plex Mono", monospace; font-size: 13px; }
  .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin: 20px 0; }
  .label { font: 500 11px "IBM Plex Mono", monospace; text-transform: uppercase; letter-spacing: 1px; color: var(--muted); margin-bottom: 4px; }
  table { width: 100%; border-collapse: collapse; margin-top: 8px; }
  th, td { text-align: left; padding: 9px 8px; border-bottom: 1px solid var(--line); vertical-align: top; }
  th { font: 500 11px "IBM Plex Mono", monospace; text-transform: uppercase; letter-spacing: 1px; color: var(--muted); }
  .num { text-align: right; white-space: nowrap; }
  .tick { width: 18px; height: 18px; border: 1.5px solid var(--ink); display: inline-block; }
  .totals td { border: 0; padding: 4px 8px; }
  .totals .grand td { font-weight: 700; font-size: 17px; border-top: 2px solid var(--ink); padding-top: 8px; }
  footer { margin-top: 36px; padding-top: 12px; border-top: 1px solid var(--line); color: var(--muted); font-size: 13px; display: flex; justify-content: space-between; gap: 16px; }
  .sign { margin-top: 40px; display: grid; grid-template-columns: 1fr 1fr; gap: 40px; }
  .sign div { border-top: 1px solid var(--ink); padding-top: 4px; font-size: 13px; color: var(--muted); }
  @media print { .bar { display: none; } .page { padding: 0; } }
</style>
</head>
<body>
<div class="page">
  <div class="bar"><a href="order.php?id=<?= (int)$o['id'] ?>">← Back to the order</a><button type="button" onclick="window.print()">Print</button></div>
  <header>
    <img src="../assets/img/kidan72-logo-header.png" alt="KIDAN 72">
    <div><h1>Packing slip</h1><div class="mono" style="text-align:right">Order <?= h($o['order_no']) ?> · <?= h(date('F j, Y', strtotime($o['created_at']))) ?></div></div>
  </header>

  <div class="grid">
    <div>
      <div class="label">Deliver to</div>
      <b><?= h($o['customer']) ?></b><br>
      <?= h($o['mobile']) ?><br>
      <?= full_address($o) !== '' ? h(full_address($o)) : '—' ?>
    </div>
    <div>
      <div class="label">Payment</div>
      <?= h($o['payment_method'] ?: '—') ?><?= $o['payment_ref'] !== '' ? '<br><span class="mono">Ref ' . h($o['payment_ref']) . '</span>' : '' ?><br>
      <span class="label" style="display:inline">Status:</span> <?= h(ucfirst($o['status'])) ?>
      <?php if ($o['notes'] !== ''): ?><div class="label" style="margin-top:10px">Customer notes</div><?= nl2br(h($o['notes'])) ?><?php endif; ?>
    </div>
  </div>

  <table>
    <thead><tr><th style="width:34px">✓</th><th>Item</th><th>Serial</th><th class="num">Qty</th><th class="num">Amount</th></tr></thead>
    <tbody>
    <?php foreach ($lines as $l): ?>
      <tr><td><span class="tick"></span></td><td><?= h($l['name']) ?></td><td class="mono"><?= h($l['serial']) ?></td><td class="num"><?= (int)$l['qty'] ?></td><td class="num"><?= h(money($l['line_total'])) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <table class="totals" style="width:auto;margin-left:auto">
    <tr><td>Items</td><td class="num"><?= h(money($o['subtotal'])) ?></td></tr>
    <?php if ((float)$o['delivery_fee'] > 0): ?><tr><td>Delivery fee</td><td class="num"><?= h(money($o['delivery_fee'])) ?></td></tr><?php endif; ?>
    <?php if ((float)$o['discount'] > 0): ?><tr><td>Discount</td><td class="num">−<?= h(money($o['discount'])) ?></td></tr><?php endif; ?>
    <tr class="grand"><td>Total</td><td class="num"><?= h(money($o['total'])) ?></td></tr>
  </table>

  <div class="sign"><div>Packed by / date</div><div>Received by (name, signature, date)</div></div>

  <footer><span>KIDAN 72 · Always Ready.</span><span><?= h($contact) ?></span></footer>
</div>
</body>
</html>
