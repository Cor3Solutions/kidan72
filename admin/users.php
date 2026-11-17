<?php
/* KIDAN 72 — Admin: who can log in */
require __DIR__ . '/_inc.php';
$me = require_login();
$pdo = db();

$super = is_super($me);
$superCount = fn() => (int)$pdo->query("SELECT COUNT(*) FROM admins WHERE role = 'super'")->fetchColumn();

if (is_post()) {
    check_csrf();
    $action = $_POST['action'] ?? '';
    // Everyone can change their own details and password; only a Super admin manages the other logins
    if (!$super && !in_array($action, ['password', 'profile'], true)) { flash('Only a Super admin can manage logins.', 'error'); redirect('users.php'); }
    $p1 = (string)($_POST['password'] ?? ''); $p2 = (string)($_POST['password2'] ?? '');

    if ($action === 'add') {
        $u = strtolower(trim((string)($_POST['username'] ?? '')));
        $st = $pdo->prepare('SELECT COUNT(*) FROM admins WHERE username = ?'); $st->execute([$u]);
        if (!preg_match('/^[a-z0-9._-]{3,40}$/', $u)) flash('Username: 3 to 40 letters or numbers, no spaces.', 'error');
        elseif ($st->fetchColumn()) flash('That username is already taken.', 'error');
        elseif (strlen($p1) < 10) flash('Please use a password of at least 10 characters.', 'error');
        elseif ($p1 !== $p2) flash('The two passwords are not the same.', 'error');
        else {
            $email = trim((string)($_POST['email'] ?? ''));
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { flash('That email address does not look right.', 'error'); redirect('users.php'); }
            $role = (string)($_POST['role'] ?? '');
            if (!isset(ADMIN_ROLES[$role])) { flash('Please choose what this login can do.', 'error'); redirect('users.php'); }
            $pdo->prepare('INSERT INTO admins (username, display_name, email, role, password_hash) VALUES (?, ?, ?, ?, ?)')
                ->execute([$u, mb_substr(trim((string)($_POST['display_name'] ?? '')), 0, 80), $email, $role, password_hash($p1, PASSWORD_DEFAULT)]);
            log_action("Added login \"$u\" (" . role_label($role) . ')');
            flash("Login \"$u\" created as " . role_label($role) . '. Give them the username and password privately.');
        }
    }
    if ($action === 'password') {
        $st = $pdo->prepare('SELECT password_hash FROM admins WHERE id = ?'); $st->execute([$me['id']]);
        if (!password_verify((string)($_POST['current'] ?? ''), (string)$st->fetchColumn())) flash('Your current password is not correct.', 'error');
        elseif (strlen($p1) < 10) flash('Please use a password of at least 10 characters.', 'error');
        elseif ($p1 !== $p2) flash('The two new passwords are not the same.', 'error');
        else {
            $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')->execute([password_hash($p1, PASSWORD_DEFAULT), $me['id']]);
            forget_login((int)$me['id']);           // other devices must log in again with the new password
            log_action('Changed their own password');
            flash('Your password was changed.');
        }
    }
    if ($action === 'role') {
        $id = (int)($_POST['id'] ?? 0);
        $role = (string)($_POST['role'] ?? '');
        $st = $pdo->prepare('SELECT username, role FROM admins WHERE id = ?'); $st->execute([$id]);
        $a = $st->fetch();
        if (!$a || !isset(ADMIN_ROLES[$role])) flash('That login was not found.', 'error');
        elseif ($id === (int)$me['id']) flash('You cannot change your own level. Ask another Super admin.', 'error');
        elseif ($a['role'] === 'super' && $role !== 'super' && $superCount() <= 1) flash('There must always be at least one Super admin.', 'error');
        elseif ($a['role'] !== $role) {
            $pdo->prepare('UPDATE admins SET role = ? WHERE id = ?')->execute([$role, $id]);
            forget_login($id);                       // they log in again, with the new level
            log_action("Changed \"{$a['username']}\" from " . role_label($a['role']) . ' to ' . role_label($role));
            flash("\"{$a['username']}\" is now " . role_label($role) . '.');
        }
    }
    if ($action === 'remove') {
        $id = (int)($_POST['id'] ?? 0);
        $st = $pdo->prepare('SELECT role FROM admins WHERE id = ?'); $st->execute([$id]);
        if ($id === (int)$me['id']) flash('You cannot remove your own login.', 'error');
        elseif ($st->fetchColumn() === 'super' && $superCount() <= 1) flash('There must always be at least one Super admin.', 'error');
        else {
            $st = $pdo->prepare('SELECT username FROM admins WHERE id = ?'); $st->execute([$id]);
            $pdo->prepare('DELETE FROM admins WHERE id = ?')->execute([$id]);
            log_action('Removed admin login "' . $st->fetchColumn() . '"');
            flash('Login removed.');
        }
    }
    if ($action === 'reset_link') {
        // For someone who forgot their password: a one-time link to send them privately (Messenger, text)
        $id = (int)($_POST['id'] ?? 0);
        $st = $pdo->prepare('SELECT id, username FROM admins WHERE id = ?'); $st->execute([$id]);
        if (($a = $st->fetch()) && $id !== (int)$me['id']) {
            $token = bin2hex(random_bytes(32));
            $pdo->prepare('DELETE FROM password_resets WHERE admin_id = ? OR expires_at < NOW()')->execute([$id]);
            $pdo->prepare('INSERT INTO password_resets (token_hash, admin_id, expires_at) VALUES (?, ?, NOW() + INTERVAL 24 HOUR)')
                ->execute([hash('sha256', $token), $id]);
            log_action("Made a password reset link for \"{$a['username']}\"");
            $_SESSION['reset_link'] = ['user' => $a['username'], 'url' => site_base_url() . 'admin/reset.php?token=' . $token];
        }
    }
    if ($action === 'profile') {
        $email = trim((string)($_POST['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) flash('That email address does not look right.', 'error');
        else {
            $pdo->prepare('UPDATE admins SET display_name = ?, email = ? WHERE id = ?')
                ->execute([mb_substr(trim((string)($_POST['display_name'] ?? '')), 0, 80), $email, $me['id']]);
            log_action('Updated their name / email');
            flash('Your details were saved.');
        }
    }
    redirect('users.php');
}

$admins = $pdo->query("SELECT id, username, display_name, email, role, last_login, created_at FROM admins ORDER BY FIELD(role, 'super', 'admin', 'encoder'), id")->fetchAll();
$mine = array_values(array_filter($admins, fn($a) => (int)$a['id'] === (int)$me['id']))[0] ?? ['display_name' => '', 'email' => ''];
$roleOptions = function (string $current) {
    $o = '';
    foreach (ADMIN_ROLES as $k => [$label]) $o .= '<option value="' . $k . '"' . ($k === $current ? ' selected' : '') . '>' . h($label) . '</option>';
    return $o;
};
page_top($super ? 'Logins' : 'My account', 'users.php', $me);
?>
<a class="back" href="more.php">← More</a>
<?php if ($super): ?>
<div class="page-head">
  <h1>Logins</h1>
  <a class="btn primary" href="#add" onclick="document.getElementById('add').open=true">+ Add a login</a>
  <p class="lede">Give each person their own login, so the activity log shows who did what. Choose a level for each one:</p>
</div>
<div class="role-guide">
  <?php foreach (ADMIN_ROLES as $k => [$label, $what]): ?><div class="role-<?= $k ?>"><b><?= h($label) ?></b><span><?= h($what) ?></span></div><?php endforeach; ?>
</div>
<?php if (!empty($_SESSION['reset_link'])): $rl = $_SESSION['reset_link']; unset($_SESSION['reset_link']); ?>
  <div class="card" style="border-color:var(--orange)">
    <h3>Reset link for <?= h($rl['user']) ?></h3>
    <p class="hint" style="margin-top:0">Send this to them privately (Messenger or text). It works once, for 24 hours, and lets them choose a new password.</p>
    <input type="text" id="rl" value="<?= h($rl['url']) ?>" readonly onclick="this.select()">
    <div class="actions" style="margin-top:10px"><button class="btn" type="button" onclick="var i=document.getElementById('rl');i.select();navigator.clipboard&&navigator.clipboard.writeText(i.value);this.textContent='Copied'">Copy link</button></div>
  </div>
<?php endif; ?>
<div class="list">
  <?php foreach ($admins as $a): $isMe = (int)$a['id'] === (int)$me['id']; ?>
    <div class="row">
      <span class="row-main">
        <span class="row-title"><?= h($a['display_name'] ?: $a['username']) ?> <span class="role-chip role-<?= h($a['role']) ?>"><?= h(role_label($a['role'])) ?></span><?= $isMe ? ' <span class="badge">you</span>' : '' ?></span>
        <span class="row-sub">Username: <?= h($a['username']) ?> · <?= $a['email'] !== '' ? h($a['email']) : 'no email (cannot use "Forgot password?")' ?></span>
        <span class="row-sub">Last login: <?= $a['last_login'] ? h(when($a['last_login'])) : 'never' ?></span>
      </span>
      <?php if (!$isMe): ?><span class="row-side">
        <form method="post" class="role-form"><?= csrf_field() ?><input type="hidden" name="action" value="role"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
          <label class="sr-only" for="role-<?= (int)$a['id'] ?>">Level for <?= h($a['username']) ?></label>
          <select id="role-<?= (int)$a['id'] ?>" name="role" onchange="this.form.querySelector('button').hidden=false"><?= $roleOptions($a['role']) ?></select>
          <button class="btn small" type="submit" hidden>Save level</button></form>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="reset_link"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="btn ghost small" type="submit">Forgot password? Make a link</button></form>
        <form method="post" onsubmit="return confirm('Remove the login for <?= h($a['username']) ?>? They will not be able to log in any more.')"><?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="btn danger small" type="submit">Remove</button></form>
      </span><?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<details class="more" id="add" style="margin-top:16px">
  <summary>Add a login <small>for your client or a staff member</small></summary>
  <form class="inner" method="post" autocomplete="off">
    <?= csrf_field() ?><input type="hidden" name="action" value="add">
    <div class="field"><label for="dn">Their name</label><input type="text" id="dn" name="display_name"></div>
    <div class="field"><label for="un">Username</label><input type="text" id="un" name="username" required autocapitalize="none"></div>
    <fieldset class="field role-pick"><legend>What can they do?</legend>
      <?php foreach (ADMIN_ROLES as $k => [$label, $what]): ?>
        <label class="check"><input type="radio" name="role" value="<?= $k ?>" required<?= $k === 'encoder' ? ' checked' : '' ?>> <span><b><?= h($label) ?></b><small><?= h($what) ?></small></span></label>
      <?php endforeach; ?>
    </fieldset>
    <div class="field"><label for="em">Their email <span class="hint">Used only for "Forgot password?".</span></label><input type="email" id="em" name="email"></div>
    <div class="field"><label for="np">Password <span class="hint">At least 10 characters.</span></label><input type="password" id="np" name="password" required autocomplete="new-password"></div>
    <div class="field"><label for="np2">Type it again</label><input type="password" id="np2" name="password2" required autocomplete="new-password"></div>
    <button class="btn primary" type="submit">Add login</button>
  </form>
</details>

<?php else: ?>
<div class="page-head"><h1>My account</h1>
  <p class="lede">You are logged in as <b><?= h(role_label($me['role'])) ?></b>: <?= h(lcfirst(ADMIN_ROLES[$me['role']][1])) ?> To change your level, ask a Super admin.</p></div>
<?php endif; ?>

<h2 id="me">Your account</h2>
<div class="row2">
  <form class="card" method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="profile">
    <h3>Your details</h3>
    <div class="field"><label for="pn">Your name</label><input type="text" id="pn" name="display_name" value="<?= h($mine['display_name']) ?>"></div>
    <div class="field"><label for="pe">Your email <span class="hint">So you can reset your password if you forget it.</span></label><input type="email" id="pe" name="email" value="<?= h($mine['email']) ?>"></div>
    <button class="btn" type="submit">Save</button>
  </form>
  <form class="card" method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="password">
    <h3>Change your password</h3>
    <div class="field"><label for="cp">Current password</label><input type="password" id="cp" name="current" required autocomplete="current-password"></div>
    <div class="field"><label for="cp1">New password</label><input type="password" id="cp1" name="password" required autocomplete="new-password"></div>
    <div class="field"><label for="cp2">Type it again</label><input type="password" id="cp2" name="password2" required autocomplete="new-password"></div>
    <button class="btn" type="submit">Change password</button>
  </form>
</div>
<?php page_bottom();
