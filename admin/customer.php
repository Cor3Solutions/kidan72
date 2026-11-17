<?php
/* KIDAN 72 — Admin: one customer's profile (details, orders, notes, follow-up) */
require __DIR__ . '/_inc.php';
$me = require_login();
$pdo = db();

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM customers WHERE id = ?');
$st->execute([$id]);
$c = $st->fetch();
if (!$c) { flash('That customer was not found.', 'error'); redirect('customers.php'); }
$self = 'customer.php?id=' . $id;

if (is_post()) {
    check_csrf();
    $action = $_POST['action'] ?? '';
    $t = fn($k, $max = 255) => mb_substr(trim((string)($_POST[$k] ?? '')), 0, $max);
    try {
        if ($action === 'details') {
            $name = $t('name', 120);
            if ($name === '') throw new RuntimeException('The name cannot be empty.');
            $mobile = $t('mobile', 40);
            $key = $mobile === '' ? null : mobile_key($mobile);
            if ($key !== null) {
                $chk = $pdo->prepare('SELECT id FROM customers WHERE mobile_key = ? AND id <> ?'); $chk->execute([$key, $id]);
                if ($other = $chk->fetchColumn()) throw new RuntimeException('Another customer already has that mobile number (customer #' . $other . ').');
            }
            $email = $t('email', 160);
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('That email address does not look right.');
            $type = in_array($_POST['type'] ?? '', CUSTOMER_TYPES, true) ? $_POST['type'] : $c['type'];
            $pdo->prepare('UPDATE customers SET name = ?, mobile = ?, mobile_key = ?, email = ?, type = ?, organization = ?, province = ?, city = ?, barangay = ?, street = ? WHERE id = ?')
                ->execute([$name, $mobile, $key, $email, $type, $t('organization', 160), $t('province', 80), $t('city', 80), $t('barangay', 120), $t('street'), $id]);
            log_action("Edited customer $name");
            flash('Customer details saved.');
        }
        if ($action === 'note') {
            // One form: a note for the timeline, a follow-up date, or both
            $note = trim((string)($_POST['note'] ?? ''));
            $date = (string)($_POST['follow_up_on'] ?? '');
            if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) throw new RuntimeException('Please pick a date.');
            if ($note === '' && $date === '') throw new RuntimeException('Type a note or pick a follow-up date.');
            $kind = isset(NOTE_KINDS[$_POST['kind'] ?? '']) ? $_POST['kind'] : 'note';
            if ($note !== '') {
                $pdo->prepare('INSERT INTO customer_notes (customer_id, admin_id, admin_name, kind, note) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$id, $me['id'], $me['display_name'] ?: $me['username'], $kind, mb_substr($note, 0, 5000)]);
                log_action("Added a " . NOTE_KINDS[$kind] . " note for {$c['name']}");
            }
            if ($date !== '') {
                $pdo->prepare('UPDATE customers SET follow_up_on = ?, follow_up_note = ? WHERE id = ?')->execute([$date, mb_substr($note, 0, 255), $id]);
                log_action("Set follow-up $date for {$c['name']}");
            }
            flash($date !== '' ? 'Saved. The follow-up shows on Home on ' . date('M j', strtotime($date)) . '.' : 'Note saved.');
        }
        if ($action === 'followup') {
            // "Done" / "Clear" on the follow-up
            $pdo->prepare("UPDATE customers SET follow_up_on = NULL, follow_up_note = '' WHERE id = ?")->execute([$id]);
            log_action('Cleared follow-up for ' . $c['name']);
            flash('Follow-up marked done.');
        }
        if ($action === 'delete_note') {
            $pdo->prepare('DELETE FROM customer_notes WHERE id = ? AND customer_id = ?')->execute([(int)($_POST['note_id'] ?? 0), $id]);
            flash('Note deleted.');
        }
        if ($action === 'delete') {
            $n = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE customer_id = ?'); $n->execute([$id]);
            if ((int)$n->fetchColumn() > 0) throw new RuntimeException('This customer has orders, so they cannot be deleted.');
            $pdo->prepare('DELETE FROM customers WHERE id = ?')->execute([$id]);
            log_action("Deleted customer {$c['name']}");
            flash('Customer deleted.');
            redirect('customers.php');
        }
    } catch (RuntimeException $e) {
        flash($e->getMessage(), 'error');
    }
    redirect($self);
}

$st = $pdo->prepare('SELECT * FROM orders WHERE customer_id = ? ORDER BY id DESC');
$st->execute([$id]);
$orders = $st->fetchAll();
$st = $pdo->prepare('SELECT * FROM customer_notes WHERE customer_id = ? ORDER BY created_at DESC, id DESC');
$st->execute([$id]);
$notes = $st->fetchAll();

$counted = array_filter($orders, fn($o) => $o['status'] !== 'cancelled');
$spent = array_sum(array_map(fn($o) => (float)$o['total'], $counted));
$first = $orders ? end($orders)['created_at'] : null;
$last = $orders ? $orders[0]['created_at'] : null;

// What they usually buy
$st = $pdo->prepare("SELECT oi.name, SUM(oi.qty) AS qty FROM order_items oi JOIN orders o ON o.id = oi.order_id
                     WHERE o.customer_id = ? AND o.status <> 'cancelled' GROUP BY oi.serial, oi.name ORDER BY qty DESC LIMIT 6");
$st->execute([$id]);
$bought = $st->fetchAll();

// One timeline: notes and orders, newest first
$timeline = [];
foreach ($notes as $n) $timeline[] = ['t' => $n['created_at'], 'kind' => $n['kind'], 'note' => $n];
foreach ($orders as $o) $timeline[] = ['t' => $o['created_at'], 'kind' => 'order', 'order' => $o];
usort($timeline, fn($a, $b) => strcmp($b['t'], $a['t']));

$tel = preg_replace('/[^\d+]/', '', $c['mobile']);
$due = $c['follow_up_on'] && $c['follow_up_on'] <= date('Y-m-d');

page_top($c['name'], 'customers.php', $me);
?>
<a class="back" href="customers.php">← Customers</a>
<div class="profile-head">
  <h1><?= h($c['name']) ?></h1>
  <div class="quick">
    <span class="badge"><?= h($c['type']) ?></span>
    <?php if ($c['organization'] !== ''): ?><span class="badge"><?= h($c['organization']) ?></span><?php endif; ?>
    <?php if (count($orders) >= 2): ?><span class="badge repeat">Repeat customer</span><?php endif; ?>
    <span class="hint" style="margin:0">Since <?= h(date('M j, Y', strtotime($first ?: $c['created_at']))) ?><?= $c['source'] === 'admin' ? ' · added by hand' : '' ?></span>
  </div>
  <div class="contact-btns" style="margin-top:4px">
    <?php if ($tel !== ''): ?>
      <a class="btn ghost small" href="tel:<?= h($tel) ?>">Call</a>
      <a class="btn ghost small" href="sms:<?= h($tel) ?>">Text</a>
    <?php endif; ?>
    <?php if ($c['email'] !== ''): ?><a class="btn ghost small" href="mailto:<?= h($c['email']) ?>">Email</a><?php endif; ?>
    <a class="btn primary small" href="order-new.php?customer=<?= (int)$c['id'] ?>">+ New order</a>
  </div>
</div>

<?php if ($c['follow_up_on']): ?>
<form class="due-banner" method="post">
  <?= csrf_field() ?><input type="hidden" name="action" value="followup">
  <p><b><?= $due ? 'Follow up ' . ($c['follow_up_on'] === date('Y-m-d') ? 'today' : 'now (was due ' . h(date('M j', strtotime($c['follow_up_on']))) . ')') : 'Follow up on ' . h(date('M j, Y', strtotime($c['follow_up_on']))) ?></b><?= $c['follow_up_note'] !== '' ? '<br>' . h($c['follow_up_note']) : '' ?></p>
  <button class="btn small" type="submit"><?= $due ? 'Done' : 'Cancel follow-up' ?></button>
</form>
<?php endif; ?>

<div class="stats">
  <div><b><?= count($orders) ?></b><span>Orders</span></div>
  <div><b><?= h(money($spent)) ?></b><span>Total spent</span></div>
  <div><b><?= $counted ? h(money($spent / count($counted))) : '—' ?></b><span>Average order</span></div>
  <div><b><?= $last ? h(when($last, false)) : '—' ?></b><span>Last order</span></div>
</div>

<form class="card" method="post" data-warn>
  <?= csrf_field() ?><input type="hidden" name="action" value="note">
  <h3>Add a note</h3>
  <fieldset class="choice">
    <legend class="hint" style="margin-top:0">What happened</legend>
    <?php $first_kind = true; foreach (NOTE_KINDS as $k => $label): ?>
      <label><input type="radio" name="kind" value="<?= $k ?>"<?= $first_kind ? ' checked' : '' ?>><span><?= h($label) ?></span></label>
    <?php $first_kind = false; endforeach; ?>
  </fieldset>
  <div class="field"><label for="note">Note</label><textarea id="note" name="note" style="min-height:80px" placeholder="e.g. Called. Wants 2 more KIDAN Pouches for her kids after payday."></textarea></div>
  <div class="field"><label for="nf-date">Remind me to follow up on <span class="hint">Optional. It shows on Home on that day.</span></label><input type="date" id="nf-date" name="follow_up_on" min="<?= date('Y-m-d') ?>" style="max-width:220px"></div>
  <button class="btn primary" type="submit" data-busy="Saving…">Save</button>
</form>

<h2>Timeline</h2>
<?php if (!$timeline): ?>
  <div class="list"><div class="empty"><p>No orders or notes yet.</p></div></div>
<?php else: ?>
<ul class="timeline">
  <?php foreach ($timeline as $e): ?>
    <?php if ($e['kind'] === 'order'): $o = $e['order']; ?>
      <li class="order">
        <div class="meta"><?= h(date('M j, Y · g:i A', strtotime($o['created_at']))) ?> · Order</div>
        <p><a href="order.php?id=<?= (int)$o['id'] ?>"><b><?= h($o['order_no']) ?></b></a> · <?= (int)$o['item_count'] ?> <?= (int)$o['item_count'] === 1 ? 'item' : 'items' ?> · <?= h(money($o['total'])) ?> <?= status_chip($o['status']) ?></p>
      </li>
    <?php else: $nn = $e['note']; ?>
      <li class="<?= h($nn['kind']) ?>">
        <div class="meta"><?= h(date('M j, Y · g:i A', strtotime($nn['created_at']))) ?> · <?= h(NOTE_KINDS[$nn['kind']] ?? 'Note') ?> · <?= h($nn['admin_name']) ?>
          <form method="post" class="inline" onsubmit="return confirm('Delete this note?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_note"><input type="hidden" name="note_id" value="<?= (int)$nn['id'] ?>"><button class="linklike" type="submit" style="font-size:13px">Delete</button></form></div>
        <p><?= h($nn['note']) ?></p>
      </li>
    <?php endif; ?>
  <?php endforeach; ?>
</ul>
<?php endif; ?>

<?php if ($bought): ?>
<h2>Usually buys</h2>
<div class="card"><ul style="margin:0;padding-left:18px"><?php foreach ($bought as $b): ?><li><?= h($b['name']) ?> <span class="muted">× <?= (int)$b['qty'] ?></span></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<details class="more" style="margin-top:24px">
  <summary>Edit details <small>name, mobile, email, address</small></summary>
  <form class="inner" method="post" data-warn>
    <?= csrf_field() ?><input type="hidden" name="action" value="details">
    <div class="row2">
      <div class="field"><label for="c-name">Name</label><input type="text" id="c-name" name="name" value="<?= h($c['name']) ?>" required maxlength="120"></div>
      <div class="field"><label for="c-mobile">Mobile</label><input type="tel" id="c-mobile" name="mobile" value="<?= h($c['mobile']) ?>" maxlength="40"></div>
      <div class="field"><label for="c-email">Email</label><input type="email" id="c-email" name="email" value="<?= h($c['email']) ?>" maxlength="160"></div>
      <div class="field"><label for="c-type">Type</label><select id="c-type" name="type"><?php foreach (CUSTOMER_TYPES as $ty): ?><option<?= $c['type'] === $ty ? ' selected' : '' ?>><?= h($ty) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="field"><label for="c-org">Office / school / barangay name</label><input type="text" id="c-org" name="organization" value="<?= h($c['organization']) ?>" maxlength="160"></div>
    <?= address_fields($c, 'c') ?>
    <p class="hint">A new website order from this mobile number updates the address by itself.</p>
    <button class="btn primary" type="submit" data-busy="Saving…">Save details</button>
  </form>
</details>

<?php if (!$orders): ?>
<form method="post" class="danger-zone" onsubmit="return confirm('Delete <?= h($c['name']) ?>? This cannot be undone.')">
  <?= csrf_field() ?><input type="hidden" name="action" value="delete">
  <button class="btn danger small" type="submit">Delete this customer</button>
</form>
<?php endif; ?>
<?php page_bottom();
