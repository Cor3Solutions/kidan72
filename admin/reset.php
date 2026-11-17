<?php
/* KIDAN 72 — Admin: choose a new password from a reset link (emailed, or made by another admin) */
require __DIR__ . '/_inc.php';
if (!kidan_config()) redirect('setup.php');

$pdo = db();
$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$row = null;
if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $st = $pdo->prepare('SELECT r.admin_id, a.username FROM password_resets r JOIN admins a ON a.id = r.admin_id
                         WHERE r.token_hash = ? AND r.used = 0 AND r.expires_at > NOW()');
    $st->execute([hash('sha256', $token)]);
    $row = $st->fetch() ?: null;
}

$error = '';
if ($row && is_post()) {
    check_csrf();
    $p1 = (string)($_POST['password'] ?? ''); $p2 = (string)($_POST['password2'] ?? '');
    if (strlen($p1) < 10) $error = 'Please use a password of at least 10 characters.';
    elseif ($p1 !== $p2) $error = 'The two passwords are not the same.';
    else {
        $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')->execute([password_hash($p1, PASSWORD_DEFAULT), $row['admin_id']]);
        $pdo->prepare('UPDATE password_resets SET used = 1 WHERE admin_id = ?')->execute([$row['admin_id']]);
        try { $pdo->prepare('DELETE FROM admin_remember WHERE admin_id = ?')->execute([$row['admin_id']]); } catch (Throwable $e) {}
        $pdo->prepare('INSERT INTO admin_log (admin_id, admin_name, action) VALUES (?, ?, ?)')
            ->execute([$row['admin_id'], $row['username'], 'Reset their password with a reset link']);
        flash('Your password was changed. Log in with the new password.');
        redirect('login.php');
    }
}

page_top('Choose a new password');
?>
<div class="card login">
  <h1>New password</h1>
  <?php if (!$row): ?>
    <div class="flash error" role="alert">This link has expired or was already used.</div>
    <a class="btn" href="forgot.php">Send a new link</a>
  <?php else: ?>
    <p>Choose a new password for <b><?= h($row['username']) ?></b>.</p>
    <?php if ($error): ?><div class="flash error" role="alert"><?= h($error) ?></div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="token" value="<?= h($token) ?>">
      <div class="field"><label for="password">New password <span class="hint">At least 10 characters.</span></label><input type="password" id="password" name="password" required autocomplete="new-password" autofocus></div>
      <div class="field"><label for="password2">Type it again</label><input type="password" id="password2" name="password2" required autocomplete="new-password"></div>
      <button class="btn primary" type="submit">Save new password</button>
    </form>
  <?php endif; ?>
</div>
<?php page_bottom();
