<?php
/* =========================================================
   KIDAN 72 — First-time setup (run once on the new host)
   1. Connects to the database and saves api/config.php
   2. Creates the tables and loads the starting content
   3. Creates the first admin login
   After step 3 this page locks itself.
   ========================================================= */

require __DIR__ . '/_inc.php';

function has_table(PDO $pdo, string $name): bool {
    $st = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $st->execute([$name]);
    return (bool)$st->fetchColumn();
}

$error = '';
$cfg = kidan_config();

/* ---------- Step 1: database login ---------- */
if (!$cfg) {
    $f = ['db_host' => 'localhost', 'db_port' => '3306', 'db_name' => '', 'db_user' => '', 'db_pass' => ''];
    if (is_post()) {
        check_csrf();
        foreach ($f as $k => $v) $f[$k] = trim((string)($_POST[$k] ?? $v));
        $try = ['db_host' => $f['db_host'], 'db_port' => (int)$f['db_port'], 'db_name' => $f['db_name'], 'db_user' => $f['db_user'], 'db_pass' => (string)($_POST['db_pass'] ?? '')];
        try {
            try {
                db_connect($try);
            } catch (PDOException $e) {
                // Unknown database: create it when the account is allowed to (XAMPP on a test computer).
                // On Hostinger the database is created in the hosting panel instead.
                if ((int)($e->errorInfo[1] ?? 0) !== 1049 || !preg_match('/^[A-Za-z0-9_]{1,64}$/', $try['db_name'])) throw $e;
                $server = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $try['db_host'], $try['db_port']), $try['db_user'], $try['db_pass'],
                                  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $server->exec('CREATE DATABASE `' . $try['db_name'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                db_connect($try);
            }
            $php = "<?php\n/* KIDAN 72 — Database login. Created by admin/setup.php.\n   Keep this file private: it is never uploaded to GitHub. */\n\nreturn " . var_export($try, true) . ";\n";
            if (@file_put_contents(KIDAN_CONFIG_FILE, $php) === false) {
                $error = 'Connected, but could not save api/config.php on the server. Check that the api folder can be written to.';
            } else {
                @chmod(KIDAN_CONFIG_FILE, 0600);
                redirect('setup.php');
            }
        } catch (Throwable $e) {
            $error = 'Could not connect to the database. Check the database name, user and password (they are case-sensitive). Server message: ' . $e->getMessage();
        }
    }
    page_top('Setup');
    ?>
    <div class="card login">
      <h1>Setup · Step 1 of 3</h1>
      <p>Enter the database details from your hosting panel (Hostinger: <b>Websites → Dashboard → Databases → Management</b>).</p>
      <?php if ($error): ?><div class="flash error"><?= h($error) ?></div><?php endif; ?>
      <form method="post">
        <?= csrf_field() ?>
        <div class="field"><label for="db_name">Database name</label><input type="text" id="db_name" name="db_name" value="<?= h($f['db_name']) ?>" required placeholder="u123456789_kidan72"></div>
        <div class="field"><label for="db_user">Database user</label><input type="text" id="db_user" name="db_user" value="<?= h($f['db_user']) ?>" required placeholder="u123456789_kidan"></div>
        <div class="field"><label for="db_pass">Database password</label><input type="password" id="db_pass" name="db_pass"><span class="hint">Leave empty only on a test computer (XAMPP).</span></div>
        <details class="field"><summary>Advanced (usually leave as is)</summary>
          <div class="row2" style="margin-top:10px">
            <div><label for="db_host">Host</label><input type="text" id="db_host" name="db_host" value="<?= h($f['db_host']) ?>"></div>
            <div><label for="db_port">Port</label><input type="text" id="db_port" name="db_port" value="<?= h($f['db_port']) ?>"></div>
          </div>
        </details>
        <button class="btn primary" type="submit">Connect</button>
      </form>
    </div>
    <?php
    page_bottom();
    exit;
}

/* ---------- Step 2: tables and starting content ---------- */
try {
    $pdo = db();
} catch (Throwable $e) {
    page_top('Setup');
    echo '<div class="card login"><h1>Setup</h1><div class="flash error">The saved database login no longer works: ' . h($e->getMessage()) .
         '</div><p>Delete <code>api/config.php</code> on the server and open this page again.</p></div>';
    page_bottom();
    exit;
}

if (!has_table($pdo, 'products')) {
    try {
        run_sql_file($pdo, KIDAN_ROOT . '/database/schema.sql');
        run_sql_file($pdo, KIDAN_ROOT . '/database/seed.sql');
        kidan_migrate($pdo);
        flash('Step 2 done: the tables were created and your products, bags, training and contact details were loaded.');
    } catch (Throwable $e) {
        page_top('Setup');
        echo '<div class="card login"><h1>Setup · Step 2 of 3</h1><div class="flash error">Could not create the tables: ' . h($e->getMessage()) . '</div></div>';
        page_bottom();
        exit;
    }
}

/* ---------- Step 3: first admin login ---------- */
$adminCount = (int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
if ($adminCount === 0) {
    $f = ['display_name' => '', 'username' => '', 'email' => ''];
    if (is_post()) {
        check_csrf();
        $f['display_name'] = trim((string)($_POST['display_name'] ?? ''));
        $f['username'] = strtolower(trim((string)($_POST['username'] ?? '')));
        $f['email'] = trim((string)($_POST['email'] ?? ''));
        $p1 = (string)($_POST['password'] ?? ''); $p2 = (string)($_POST['password2'] ?? '');
        if (!preg_match('/^[a-z0-9._-]{3,40}$/', $f['username'])) $error = 'Username: 3 to 40 letters or numbers, no spaces.';
        elseif (strlen($p1) < 10) $error = 'Please use a password of at least 10 characters.';
        elseif ($p1 !== $p2) $error = 'The two passwords are not the same.';
        elseif ($f['email'] !== '' && !filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $error = 'That email address does not look right.';
        else {
            $pdo->prepare("INSERT INTO admins (username, display_name, email, role, password_hash) VALUES (?, ?, ?, 'super', ?)")
                ->execute([$f['username'], $f['display_name'], $f['email'], password_hash($p1, PASSWORD_DEFAULT)]);
            flash('Setup complete. Log in with the username and password you just chose.');
            redirect('login.php');
        }
    }
    page_top('Setup');
    ?>
    <div class="card login">
      <h1>Setup · Step 3 of 3</h1>
      <p>Create your own admin login. You can add the client's login afterwards under <b>Users</b>.</p>
      <?php if ($error): ?><div class="flash error"><?= h($error) ?></div><?php endif; ?>
      <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <div class="field"><label for="display_name">Your name</label><input type="text" id="display_name" name="display_name" value="<?= h($f['display_name']) ?>"></div>
        <div class="field"><label for="username">Username</label><input type="text" id="username" name="username" value="<?= h($f['username']) ?>" required autocapitalize="none"></div>
        <div class="field"><label for="email">Your email <span class="hint">Optional. Lets you reset your password if you forget it.</span></label><input type="email" id="email" name="email" value="<?= h($f['email']) ?>"></div>
        <div class="field"><label for="password">Password <span class="hint">At least 10 characters.</span></label><input type="password" id="password" name="password" required autocomplete="new-password"></div>
        <div class="field"><label for="password2">Type the password again</label><input type="password" id="password2" name="password2" required autocomplete="new-password"></div>
        <button class="btn primary" type="submit">Create login</button>
      </form>
    </div>
    <?php
    page_bottom();
    exit;
}

/* ---------- Already set up ---------- */
page_top('Setup');
echo '<div class="card login"><h1>Setup is complete</h1><p>This page is locked because the site is already set up.</p><a class="btn primary" href="login.php">Go to login</a></div>';
page_bottom();
