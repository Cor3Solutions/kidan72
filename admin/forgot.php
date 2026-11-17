<?php
/* KIDAN 72 — Admin: "Forgot password?" — emails a reset link */
require __DIR__ . '/_inc.php';
if (!kidan_config()) redirect('setup.php');
if (current_admin()) redirect('index.php');

$sent = false;
$error = '';
if (is_post()) {
    check_csrf();
    $pdo = db();
    $ip = client_ip();
    $st = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > NOW() - INTERVAL 15 MINUTE');
    $st->execute([$ip]);
    if ((int)$st->fetchColumn() >= 8) {
        $error = 'Too many attempts. Please wait 15 minutes and try again.';
    } else {
        // Counts as an attempt, so this page cannot be used to flood someone's inbox
        $pdo->prepare('INSERT INTO login_attempts (ip) VALUES (?)')->execute([$ip]);
        $who = strtolower(trim((string)($_POST['who'] ?? '')));
        $st = $pdo->prepare("SELECT id, username, display_name, email FROM admins WHERE (username = ? OR LOWER(email) = ?) AND email <> '' LIMIT 1");
        $st->execute([$who, $who]);
        if ($a = $st->fetch()) {
            $token = bin2hex(random_bytes(32));
            $pdo->prepare('DELETE FROM password_resets WHERE admin_id = ? OR expires_at < NOW()')->execute([$a['id']]);
            $pdo->prepare('INSERT INTO password_resets (token_hash, admin_id, expires_at) VALUES (?, ?, NOW() + INTERVAL 1 HOUR)')
                ->execute([hash('sha256', $token), $a['id']]);
            $link = site_base_url() . 'admin/reset.php?token=' . $token;
            send_mail($a['email'], 'Reset your KIDAN 72 admin password',
                "Hi " . ($a['display_name'] ?: $a['username']) . ",\n\nSomeone (hopefully you) asked to reset the password for the KIDAN 72 admin login \"{$a['username']}\".\n\n"
                . "Open this link within 1 hour to choose a new password:\n$link\n\nIf you did not ask for this, ignore this email; your password stays the same.\n");
        }
        // Same answer whether or not the login exists, so nobody can test usernames here
        $sent = true;
    }
}

page_top('Forgot password');
?>
<div class="card login">
  <h1>Forgot password</h1>
  <?php if ($sent): ?>
    <div class="flash ok" role="status">If that login has an email address saved, a reset link is on its way. It works for 1 hour. Check the Spam folder too.</div>
    <p class="hint">No email? Ask another admin to open <b>Users</b>, remove your login and add it again with a new password.</p>
    <a class="btn" href="login.php">Back to login</a>
  <?php else: ?>
    <p>Type your username or the email address saved on your login. We'll email you a link to choose a new password.</p>
    <?php if ($error): ?><div class="flash error" role="alert"><?= h($error) ?></div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="field"><label for="who">Username or email</label><input type="text" id="who" name="who" required autocapitalize="none" autofocus></div>
      <div class="actions"><button class="btn primary" type="submit">Send reset link</button><a href="login.php">Back to login</a></div>
    </form>
  <?php endif; ?>
</div>
<?php page_bottom();
