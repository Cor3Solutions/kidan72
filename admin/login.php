<?php
/* KIDAN 72 — Admin login */
require __DIR__ . '/_inc.php';

if (!kidan_config()) redirect('setup.php');
if (current_admin()) redirect('index.php');

$error = '';
$username = '';

if (is_post()) {
    check_csrf();
    $pdo = db();
    $ip = client_ip();
    $pdo->exec('DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 1 DAY');
    $st = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > NOW() - INTERVAL 15 MINUTE');
    $st->execute([$ip]);

    $username = strtolower(trim((string)($_POST['username'] ?? '')));
    $password = (string)($_POST['password'] ?? '');

    if ((int)$st->fetchColumn() >= 8) {
        $error = 'Too many wrong attempts. Please wait 15 minutes and try again.';
    } else {
        $st = $pdo->prepare('SELECT id, password_hash FROM admins WHERE username = ?');
        $st->execute([$username]);
        $row = $st->fetch();
        if ($row && password_verify($password, $row['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$row['id'];
            $_SESSION['seen'] = time();
            unset($_SESSION['csrf']);
            if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
                $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $row['id']]);
            }
            $pdo->prepare('UPDATE admins SET last_login = NOW() WHERE id = ?')->execute([$row['id']]);
            $pdo->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
            if (!empty($_POST['keep'])) keep_login((int)$row['id']);
            redirect('index.php');
        }
        $pdo->prepare('INSERT INTO login_attempts (ip) VALUES (?)')->execute([$ip]);
        $error = 'Wrong username or password.';
    }
}

page_top('Log in');
?>
<div class="card login">
  <h1>Admin login</h1>
  <?php if ($error): ?><div class="flash error" role="alert"><?= h($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <div class="field"><label for="username">Username</label><input type="text" id="username" name="username" value="<?= h($username) ?>" required autocapitalize="none" autocomplete="username" autofocus></div>
    <div class="field"><label for="password">Password</label>
      <div class="pw"><input type="password" id="password" name="password" required autocomplete="current-password">
        <button type="button" class="btn ghost small" aria-controls="password" aria-pressed="false" onclick="var i=document.getElementById('password');var s=i.type==='password';i.type=s?'text':'password';this.textContent=s?'Hide':'Show';this.setAttribute('aria-pressed',s)">Show</button></div></div>
    <label class="check field"><input type="checkbox" name="keep" value="1"> Keep me logged in on this device for <?= KEEP_DAYS ?> days <span class="hint" style="display:inline">(not on shared computers)</span></label>
    <div class="actions"><button class="btn primary" type="submit" data-busy="Logging in…">Log in</button><a href="forgot.php">Forgot password?</a></div>
  </form>
</div>
<?php
page_bottom();
