<?php
/* KIDAN 72 — Admin: everything that is not on the phone's bottom bar,
   plus the downloads and the backup (one place for all of them). */
require __DIR__ . '/_inc.php';
$me = require_login();
$can = fn(string $f) => can_open($me, $f);
$sales = sees_sales($me);

page_top('More', 'more.php', $me);
?>
<div class="page-head"><h1>More</h1></div>
<?php if ($sales): ?>
<p class="menu-head">Business</p>
<div class="menu-list">
  <a href="reports.php"><b>Reports</b><small>Sales, products and customers by month, year or all time. Download as Excel.</small></a>
  <a href="stock.php"><b>Stock</b><small>How many of each item you have, how many bags you can still pack, stock received and the history.</small></a>
</div>
<?php else: ?>
<p class="menu-head">Stock</p>
<div class="menu-list">
  <a href="stock.php"><b>Stock</b><small>How many of each item you have, stock arrived, shelf counts and the history.</small></a>
  <a href="stock.php?download=xlsx"><b>Stock list (Excel)</b><small>Every item on hand (per colour) and the history of stock changes.</small></a>
</div>
<?php endif; ?>

<p class="menu-head">Website</p>
<div class="menu-list">
  <a href="training.php"><b>Training modules</b><small>The lessons on the Training page and which items each one uses.</small></a>
  <a href="readiness.php"><b>Readiness check</b><small>The questions in the "How ready are you?" quiz.</small></a>
  <?php if ($can('settings.php')): ?><a href="settings.php"><b>Settings</b><small>Contact details, payment instructions, order alerts, homepage film and Kit page photos.</small></a><?php endif; ?>
  <a href="../index.html" target="_blank" rel="noopener"><b>View the website ↗</b><small>Opens the website in a new tab.</small></a>
</div>

<?php if (is_super($me)): ?>
<p class="menu-head">Team</p>
<div class="menu-list">
  <a href="users.php"><b>Logins</b><small>Who can use this admin page, and what each person can do (Super admin, Admin, Encoder).</small></a>
  <a href="activity.php"><b>Activity log</b><small>Who changed what, and when.</small></a>
</div>
<?php endif; ?>
<?php if ($sales): ?>

<p class="menu-head" id="downloads">Downloads & backup</p>
<div class="menu-list">
  <a href="reports.php?p=all&amp;download=xlsx"><b>All orders (Excel)</b><small>The all-time report: summary, every order, products sold, customers. For one month, use Reports.</small></a>
  <a href="stock.php?download=xlsx"><b>Stock list (Excel)</b><small>Every item on hand (per pouch colour), its value, and the history of stock changes.</small></a>
  <a href="customers-export.php"><b>Customer list (Excel)</b><small>Every customer: mobile, email, place, orders, total spent, follow-ups.</small></a>
  <?php if (is_super($me)): ?>
  <a href="sample-data.php"><b>Sample data</b><small>Add made-up orders to try out the Reports, and remove them again with one tap.</small></a>
  <form method="post" action="backup.php"><?= csrf_field() ?><button type="submit"><b>Full backup</b><small>A copy of everything (products, orders, customers, logins). Download one every month and keep it somewhere safe.</small></button></form>
  <?php endif; ?>
</div>
<?php endif; ?>

<p class="menu-head">Account</p>
<div class="menu-list">
  <a href="users.php#me"><b>My details and password</b><small>Logged in as <?= h($me['display_name'] ?: $me['username']) ?> (<?= h(role_label($me['role'])) ?>).</small></a>
  <form method="post" action="logout.php"><?= csrf_field() ?><button type="submit"><b>Log out</b><small>On a shared phone or computer, always log out when you finish.</small></button></form>
</div>
<?php page_bottom();
