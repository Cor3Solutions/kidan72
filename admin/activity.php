<?php
/* KIDAN 72 — Admin: activity log (who changed what) */
require __DIR__ . '/_inc.php';
$me = require_login();

$rows = db()->query('SELECT admin_name, action, created_at FROM admin_log ORDER BY id DESC LIMIT 300')->fetchAll();
page_top('Activity log', 'activity.php', $me);
?>
<a class="back" href="more.php">← More</a>
<div class="page-head"><h1>Activity log</h1><p class="lede">The last 300 changes made in the admin, newest first.</p></div>
<?php if (!$rows): ?>
  <div class="list"><div class="empty"><p>Nothing yet. Changes you save are listed here.</p></div></div>
<?php else: ?>
<div class="list">
  <?php foreach ($rows as $r): ?>
    <div class="row"><span class="row-main"><span><?= h($r['action']) ?></span><span class="row-sub"><?= h($r['admin_name'] ?: 'Website') ?></span></span><span class="row-side"><span class="row-sub"><?= h(when($r['created_at'])) ?></span></span></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php page_bottom();
