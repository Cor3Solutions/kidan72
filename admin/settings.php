<?php
/* KIDAN 72 — Admin: contact details, brand film, Kit page slideshow */
require __DIR__ . '/_inc.php';
$me = require_login();
$pdo = db();

$keys = ['contact_messenger', 'contact_facebook', 'contact_viber', 'contact_email', 'brand_film', 'order_alert_email', 'payment_instructions'];

if (is_post()) {
    check_csrf();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'contact') {
            // Each section has its own Save button and sends only its own fields
            $v = [];
            foreach ($keys as $k) if (array_key_exists($k, $_POST)) $v[$k] = mb_substr(trim((string)$_POST[$k]), 0, $k === 'payment_instructions' ? 1000 : 255);
            // Accept a full Facebook/Messenger link and keep only the page ID or username
            if (isset($v['contact_messenger'])) {
                if (preg_match('~(?:id=)(\d+)~', $v['contact_messenger'], $mm)) $v['contact_messenger'] = $mm[1];
                elseif (preg_match('~(?:facebook\.com|m\.me|messenger\.com/t)/([A-Za-z0-9.]+)~', $v['contact_messenger'], $mm)) $v['contact_messenger'] = $mm[1];
            }
            if (($v['contact_email'] ?? '') !== '' && !filter_var($v['contact_email'], FILTER_VALIDATE_EMAIL)) throw new RuntimeException('That email address does not look right.');
            if (($v['order_alert_email'] ?? '') !== '' && !filter_var($v['order_alert_email'], FILTER_VALIDATE_EMAIL)) throw new RuntimeException('The alert email address does not look right.');
            if (($v['contact_facebook'] ?? '') !== '' && !preg_match('~^https://~', $v['contact_facebook'])) throw new RuntimeException('The Facebook page link must start with https://');
            if (isset($v['brand_film']) && !youtube_ok($v['brand_film'])) throw new RuntimeException('The homepage film must be a YouTube link.');
            $st = $pdo->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)');
            foreach ($v as $k => $val) $st->execute([$k, $val]);
            $what = ['contact_email' => 'Contact details', 'payment_instructions' => 'Payment details', 'order_alert_email' => 'Email alerts', 'brand_film' => 'Homepage film'];
            $saved = 'Settings';
            foreach ($what as $k => $label) if (isset($v[$k])) { $saved = $label; break; }
            log_action("Updated settings: $saved");
            flash("$saved saved.");
            redirect('settings.php#' . (['Contact details' => 'contact', 'Payment details' => 'payments', 'Email alerts' => 'alerts', 'Homepage film' => 'website'][$saved] ?? ''));
        }
        if ($action === 'test_mail') {
            $to = setting('order_alert_email');
            if ($to === '') $to = $me['email'] ?? '';
            if ($to === '') throw new RuntimeException('Fill in "Send alerts to" first and save.');
            $ok = send_mail($to, 'KIDAN 72 test email', "This is a test from the KIDAN 72 admin page.\n\nIf you can read this, emails to customers and order alerts will arrive too.\n\n" . site_base_url() . "admin/");
            if (!$ok) throw new RuntimeException('The test email was not sent: ' . mail_last_error());
            flash("Test email sent to $to" . (smtp_settings() ? '. It should arrive within a minute.' : '. If it is not in the inbox within a few minutes, check Spam, and set up "Email sending" below for reliable delivery.'));
        }
        if ($action === 'smtp') {
            $preset = (string)($_POST['smtp_preset'] ?? 'gmail');
            $presets = ['gmail' => ['smtp.gmail.com', 587, 'tls'], 'hostinger' => ['smtp.hostinger.com', 465, 'ssl']];
            $st = $pdo->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)');
            if ($preset === 'off') {
                foreach (['smtp_host', 'smtp_user', 'smtp_pass'] as $k) $st->execute([$k, '']);
                log_action('Turned off email sending through an email account');
                flash('Email sending through an account is off. The server\'s built-in mail is used (less reliable).');
            } else {
                [$host, $port, $secure] = $presets[$preset] ?? [trim((string)($_POST['smtp_host'] ?? '')), (int)($_POST['smtp_port'] ?? 587), (string)($_POST['smtp_secure'] ?? 'tls')];
                if (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $host)) throw new RuntimeException('Type the email server name, e.g. smtp.gmail.com.');
                if ($port < 1 || $port > 65535) throw new RuntimeException('The port must be a number like 587 or 465.');
                if (!in_array($secure, ['tls', 'ssl', 'none'], true)) $secure = 'tls';
                $user = trim((string)($_POST['smtp_user'] ?? ''));
                if (!filter_var($user, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Type the full email address of the account, e.g. kidan72gobag@gmail.com.');
                $pass = preg_replace('/\s+/', '', (string)($_POST['smtp_pass'] ?? ''));   // Gmail shows app passwords with spaces
                if ($pass === '' && setting('smtp_pass') === '') throw new RuntimeException('Type the password (for Gmail: the 16-letter App Password).');
                foreach (['smtp_host' => $host, 'smtp_port' => (string)$port, 'smtp_secure' => $secure, 'smtp_user' => $user,
                          'mail_from_name' => mb_substr(trim((string)($_POST['mail_from_name'] ?? '')) ?: 'KIDAN 72', 0, 60)] as $k => $v) $st->execute([$k, $v]);
                if ($pass !== '') $st->execute(['smtp_pass', $pass]);
                log_action("Set up email sending through $user");
                flash('Email account saved. Press "Send a test email" to check it.');
            }
        }
        if ($action === 'sms') {
            $nums = [];
            foreach (preg_split('/[,;\n]+/', (string)($_POST['sms_numbers'] ?? '')) as $n) {
                if (trim($n) === '') continue;
                $k = mobile_key($n);
                if ($k === null || !preg_match('/^09\d{9}$/', $k)) throw new RuntimeException('"' . trim($n) . '" does not look like a Philippine mobile number (09XX XXX XXXX).');
                $nums[$k] = $k;
            }
            if (count($nums) > 5) throw new RuntimeException('Up to 5 phone numbers.');
            $sender = trim((string)($_POST['sms_sender'] ?? ''));
            if ($sender !== '' && !preg_match('/^[A-Za-z0-9 ]{1,11}$/', $sender)) throw new RuntimeException('The sender name can have up to 11 letters or numbers.');
            $st = $pdo->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)');
            $st->execute(['sms_numbers', implode(', ', $nums)]);
            $st->execute(['sms_sender', $sender]);
            $key = trim((string)($_POST['sms_api_key'] ?? ''));
            if (!empty($_POST['sms_key_clear'])) $st->execute(['sms_api_key', '']);
            elseif ($key !== '') {
                if (!preg_match('/^[A-Za-z0-9]{16,64}$/', $key)) throw new RuntimeException('That API key does not look right. Copy it again from your Semaphore account (Account → API).');
                $st->execute(['sms_api_key', $key]);
            }
            log_action('Updated phone (SMS) alert settings');
            flash('Phone alert settings saved.' . (setting('sms_api_key') !== '' && $nums ? ' Press "Send a test text" to check it.' : ''));
        }
        if ($action === 'test_sms') {
            if (!sms_numbers()) throw new RuntimeException('Add the phone number first and save.');
            if (setting('sms_api_key') === '') throw new RuntimeException('Add your Semaphore API key first and save.');
            $r = send_sms(sms_numbers(), 'KIDAN 72 website: phone alerts are working. You will get a text for every new order and proof of payment.');
            if (!$r['ok']) throw new RuntimeException('The text could not be sent: ' . $r['error']);
            flash('Test text sent to ' . implode(', ', sms_numbers()) . '. It should arrive within a minute.');
        }
        if ($action === 'slides') {
            $st = $pdo->prepare('UPDATE kit_slides SET caption = ? WHERE id = ?');
            foreach ((array)($_POST['caption'] ?? []) as $id => $cap) $st->execute([mb_substr(trim((string)$cap), 0, 160), (int)$id]);
            log_action('Edited Kit slideshow captions');
            flash('Slideshow captions saved.');
        }
        if ($action === 'slide_photo') {
            $id = (int)($_POST['id'] ?? 0);
            $rel = save_uploaded_photo($_FILES['photo'] ?? [], 'kit', 'slide-' . $id);
            $pdo->prepare('UPDATE kit_slides SET src = ? WHERE id = ?')->execute([$rel . '?v=' . time(), $id]);
            log_action('Replaced a Kit slideshow photo');
            flash('Slideshow photo replaced.');
        }
        if ($action === 'slide_add') {
            $sort = (int)$pdo->query('SELECT COALESCE(MAX(sort), 0) + 10 FROM kit_slides')->fetchColumn();
            $pdo->prepare("INSERT INTO kit_slides (src, caption, sort) VALUES ('', ?, ?)")->execute([mb_substr(trim((string)($_POST['caption'] ?? '')), 0, 160), $sort]);
            $id = (int)$pdo->lastInsertId();
            try {
                $rel = save_uploaded_photo($_FILES['photo'] ?? [], 'kit', 'slide-' . $id);
                $pdo->prepare('UPDATE kit_slides SET src = ? WHERE id = ?')->execute([$rel . '?v=' . time(), $id]);
            } catch (Throwable $e) { $pdo->prepare('DELETE FROM kit_slides WHERE id = ?')->execute([$id]); throw $e; }
            log_action('Added a Kit slideshow photo');
            flash('Slide added.');
        }
        if ($action === 'slide_remove') {
            $pdo->prepare('DELETE FROM kit_slides WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
            log_action('Removed a Kit slideshow photo');
            flash('Slide removed from the slideshow.');
        }
        if ($action === 'slide_move') {
            $rows = $pdo->query('SELECT id FROM kit_slides ORDER BY sort, id')->fetchAll(PDO::FETCH_COLUMN);
            $i = array_search((int)($_POST['id'] ?? 0), array_map('intval', $rows), true);
            $j = $i === false ? false : $i + (($_POST['dir'] ?? '') === 'up' ? -1 : 1);
            if ($i !== false && isset($rows[$j])) { [$rows[$i], $rows[$j]] = [$rows[$j], $rows[$i]]; }
            $st = $pdo->prepare('UPDATE kit_slides SET sort = ? WHERE id = ?');
            foreach ($rows as $n => $rid) $st->execute([($n + 1) * 10, $rid]);
        }
    } catch (Throwable $e) {
        flash($e instanceof RuntimeException ? $e->getMessage() : 'Something went wrong: ' . $e->getMessage(), 'error');
    }
    $back = ['test_mail' => 'email', 'smtp' => 'email', 'sms' => 'alerts', 'test_sms' => 'alerts'][$action] ?? 'website';
    redirect('settings.php#' . $back);
}

$s = [];
foreach ($keys as $k) $s[$k] = setting($k);
$smsNumbers = setting('sms_numbers');
$smsSender = setting('sms_sender');
$smsKeySet = setting('sms_api_key') !== '';
$smsLast = json_decode(setting('sms_last'), true) ?: [];
$smtp = smtp_settings();
$smtpPreset = !$smtp ? 'gmail' : ($smtp['host'] === 'smtp.gmail.com' ? 'gmail' : ($smtp['host'] === 'smtp.hostinger.com' ? 'hostinger' : 'other'));
$recentMail = $pdo->query('SELECT e.*, o.order_no FROM email_log e LEFT JOIN orders o ON o.id = e.order_id ORDER BY e.id DESC LIMIT 8')->fetchAll();
$mailFails = (int)$pdo->query('SELECT COUNT(*) FROM email_log WHERE ok = 0 AND created_at > NOW() - INTERVAL 7 DAY')->fetchColumn();
$slides = $pdo->query('SELECT * FROM kit_slides ORDER BY sort, id')->fetchAll();
$yt = youtube_id($s['brand_film']);

page_top('Settings', 'settings.php', $me);
?>
<a class="back" href="more.php">← More</a>
<div class="page-head"><h1>Settings</h1></div>
<nav class="tabs" aria-label="Jump to">
  <a href="#contact">Contact</a><a href="#payments">Payments</a><a href="#alerts">Order alerts</a><a href="#email">Email sending</a><a href="#website">Homepage & Kit photos</a>
</nav>

<form class="card" method="post" id="contact" data-warn>
  <?= csrf_field() ?><input type="hidden" name="action" value="contact">
  <h2>Contact details</h2>
  <p class="hint" style="margin-top:0">Shown on the website's Contact page and in emails to customers. Customers can also message you here about their order.</p>
  <div class="row2">
    <div class="field"><label for="m">Facebook Messenger <span class="hint">Page ID or username. Pasting the page link works too.</span></label><input type="text" id="m" name="contact_messenger" value="<?= h($s['contact_messenger']) ?>"></div>
    <div class="field"><label for="f">Facebook page link</label><input type="url" id="f" name="contact_facebook" value="<?= h($s['contact_facebook']) ?>" placeholder="https://www.facebook.com/…"></div>
    <div class="field"><label for="e">Email</label><input type="email" id="e" name="contact_email" value="<?= h($s['contact_email']) ?>"></div>
    <div class="field"><label for="v">Viber number <span class="hint">With +63, e.g. +639171234567.</span></label><input type="text" id="v" name="contact_viber" value="<?= h($s['contact_viber']) ?>" inputmode="tel"></div>
  </div>
  <button class="btn primary" type="submit" data-busy="Saving…">Save contact details</button>
</form>

<form class="card" method="post" id="payments" data-warn>
  <?= csrf_field() ?><input type="hidden" name="action" value="contact">
  <h2>Payments</h2>
  <div class="field"><label for="pi">How customers pay you <span class="hint">Customers see this once you confirm their order: in the email, on their tracking page and in the ready-made message.</span></label>
    <textarea id="pi" name="payment_instructions" style="min-height:90px" placeholder="GCash 0917 123 4567 (Juan D.)&#10;BPI 1234-5678-90 (KIDAN 72 Trading)"><?= h($s['payment_instructions']) ?></textarea></div>
  <button class="btn primary" type="submit" data-busy="Saving…">Save payment details</button>
</form>

<section class="card" id="alerts">
  <h2>Order alerts</h2>
  <p class="hint" style="margin-top:0">How you hear about new website orders and proofs of payment.</p>

  <form method="post" data-warn>
    <?= csrf_field() ?><input type="hidden" name="action" value="contact">
    <h3>By email</h3>
    <div class="field"><label for="oa">Send alerts to <span class="hint">Your inbox for new orders and proofs of payment. Leave empty to stop email alerts.</span></label><input type="email" id="oa" name="order_alert_email" value="<?= h($s['order_alert_email']) ?>"></div>
    <button class="btn primary" type="submit" data-busy="Saving…">Save</button>
  </form>
</section>

<section class="card" id="email">
  <h2>Email sending</h2>
  <p class="hint" style="margin-top:0">The account that sends emails to customers (order received, confirmed, paid, delivered) and your alerts.</p>
  <p class="<?= $smtp ? 'flash ok' : 'next-step' ?>" style="margin:0 0 14px">
    <?php if ($smtp): ?><b>On.</b> Emails are sent from <?= h($smtp['user']) ?>.
    <?php else: ?><b>Not set up yet.</b> Emails go through the web host's built-in mail, which often lands in spam or is not delivered. Set up the shop's email account below.<?php endif; ?>
    <?php if ($mailFails): ?><br><b><?= $mailFails ?> email<?= $mailFails === 1 ? '' : 's' ?> failed in the last 7 days</b> (see the list below).<?php endif; ?></p>

  <form method="post" autocomplete="off" data-warn>
    <?= csrf_field() ?><input type="hidden" name="action" value="smtp">
    <fieldset class="choice" id="smtp-preset">
      <legend>Email account</legend>
      <?php foreach (['gmail' => 'Gmail', 'hostinger' => 'Hostinger email', 'other' => 'Other', 'off' => 'Turn off'] as $k => $label): if ($k === 'off' && !$smtp) continue; ?>
        <label><input type="radio" name="smtp_preset" value="<?= $k ?>"<?= $smtpPreset === $k ? ' checked' : '' ?>><span><?= $label ?></span></label>
      <?php endforeach; ?>
    </fieldset>
    <div class="smtp-fields">
      <div class="smtp-other row3">
        <div class="field"><label for="sh">Server</label><input type="text" id="sh" name="smtp_host" value="<?= h($smtp['host'] ?? '') ?>" placeholder="smtp.example.com"></div>
        <div class="field"><label for="sp">Port</label><input type="text" id="sp" name="smtp_port" value="<?= h((string)($smtp['port'] ?? 587)) ?>" inputmode="numeric"></div>
        <div class="field"><label for="se">Security</label><select id="se" name="smtp_secure"><?php foreach (['tls' => 'STARTTLS (port 587)', 'ssl' => 'SSL (port 465)', 'none' => 'None'] as $k => $label): ?><option value="<?= $k ?>"<?= ($smtp['secure'] ?? 'tls') === $k ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="row2">
        <div class="field"><label for="su">Email address</label><input type="email" id="su" name="smtp_user" value="<?= h($smtp['user'] ?? setting('contact_email')) ?>" autocomplete="off"></div>
        <div class="field"><label for="spw">Password <span class="hint smtp-gmail-hint">For Gmail: an App Password, not the normal password.</span></label><input type="password" id="spw" name="smtp_pass" value="" placeholder="<?= $smtp && $smtp['pass'] !== '' ? 'Saved. Leave empty to keep it.' : '' ?>" autocomplete="new-password"></div>
        <div class="field"><label for="sfn">Sender name <span class="hint">What customers see as the sender.</span></label><input type="text" id="sfn" name="mail_from_name" value="<?= h($smtp['name'] ?? 'KIDAN 72') ?>" maxlength="60"></div>
      </div>
      <details class="how smtp-gmail-hint">
        <summary>How to get a Gmail App Password (2 minutes)</summary>
        <ol>
          <li>Log in to the shop's Gmail (e.g. kidan72gobag@gmail.com) on a computer or phone.</li>
          <li>Open <a href="https://myaccount.google.com/security" target="_blank" rel="noopener">myaccount.google.com/security</a> and turn on <b>2-Step Verification</b> if it is off.</li>
          <li>Open <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener">myaccount.google.com/apppasswords</a>, type a name like "KIDAN 72 website" and press <b>Create</b>.</li>
          <li>Copy the 16-letter password Google shows, paste it in Password above, and press Save. Then press "Send a test email".</li>
        </ol>
      </details>
    </div>
    <div class="actions"><button class="btn primary" type="submit" data-busy="Saving…">Save</button>
      <button class="btn ghost" type="submit" form="test-mail" data-busy="Sending…">Send a test email</button></div>
  </form>
  <form id="test-mail" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="test_mail"></form>

  <?php if ($recentMail): ?>
  <details class="more" style="margin:18px 0 0">
    <summary>Recent emails <small><?= $mailFails ? $mailFails . ' failed' : 'last 8' ?></small></summary>
    <div class="inner"><ul class="history">
      <?php foreach ($recentMail as $m): ?>
        <li><span><?= $m['ok'] ? '<span class="status delivered">Sent</span>' : '<span class="status cancelled">Failed</span>' ?> <?= h($m['subject']) ?> <span class="by">to <?= h($m['to_addr']) ?></span><?= !$m['ok'] && $m['error'] !== '' ? '<br><span class="by">' . h($m['error']) . '</span>' : '' ?></span><span class="by"><?= h(when($m['created_at'])) ?></span></li>
      <?php endforeach; ?>
    </ul></div>
  </details>
  <?php endif; ?>
</section>

<section class="card" id="sms-card">
  <form method="post" id="sms" autocomplete="off" data-warn>
    <?= csrf_field() ?><input type="hidden" name="action" value="sms">
    <h2>Text message alerts</h2>
    <p class="hint" style="margin-top:0">Through <a href="https://semaphore.co" target="_blank" rel="noopener">Semaphore</a>, a Philippine SMS service: about PHP 0.50 per text, from your Semaphore credits.</p>
    <p class="<?= $smsKeySet && $smsNumbers !== '' ? 'flash ok' : 'next-step' ?>" style="margin:0 0 14px">
      <?php if ($smsKeySet && $smsNumbers !== ''): ?><b>On.</b> Texts go to <?= h($smsNumbers) ?>.
      <?php elseif ($smsNumbers !== ''): ?><b>Waiting for the Semaphore API key.</b> Texts will go to <?= h($smsNumbers) ?>.
      <?php else: ?><b>Off.</b> Add a number and your Semaphore API key to turn it on.<?php endif; ?>
      <?php if ($smsLast): ?><br>Last text: <?= h(when($smsLast['at'])) ?>, <?= !empty($smsLast['ok']) ? 'sent' : 'not sent (' . h($smsLast['error']) . ')' ?><?php endif; ?></p>
    <div class="row2">
      <div class="field"><label for="sn">Send texts to <span class="hint">e.g. 0995 376 9308. Separate several numbers with commas.</span></label><input type="text" id="sn" name="sms_numbers" value="<?= h($smsNumbers) ?>" inputmode="tel"></div>
      <div class="field"><label for="sk">Semaphore API key <span class="hint"><?= $smsKeySet ? 'Saved. Leave empty to keep it.' : 'In Semaphore: Account → API.' ?></span></label><input type="password" id="sk" name="sms_api_key" value="" placeholder="<?= $smsKeySet ? '••••••••••••' : '' ?>" autocomplete="new-password"></div>
      <div class="field"><label for="ss">Sender name <span class="hint">Optional: your approved Semaphore sender name.</span></label><input type="text" id="ss" name="sms_sender" value="<?= h($smsSender) ?>" maxlength="11"></div>
    </div>
    <?php if ($smsKeySet): ?><label class="check field"><input type="checkbox" name="sms_key_clear" value="1"> Remove the saved API key (stops text alerts)</label><?php endif; ?>
    <div class="actions"><button class="btn primary" type="submit" data-busy="Saving…">Save</button>
      <button class="btn ghost" type="submit" form="test-sms" data-busy="Sending…">Send a test text</button></div>
  </form>
  <form id="test-sms" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="test_sms"></form>
</section>

<section id="website">
<form class="card" method="post" data-warn>
  <?= csrf_field() ?><input type="hidden" name="action" value="contact">
  <h2>Homepage film</h2>
  <div class="field"><label for="b">YouTube link <span class="hint">Plays on the homepage and from "Watch the film".</span></label><input type="url" id="b" name="brand_film" value="<?= h($s['brand_film']) ?>">
    <?php if ($yt): ?><div class="yt"><img src="https://i.ytimg.com/vi/<?= h($yt) ?>/mqdefault.jpg" alt=""><a href="<?= h($s['brand_film']) ?>" target="_blank" rel="noopener">Check the video ↗</a></div><?php endif; ?></div>
  <button class="btn primary" type="submit" data-busy="Saving…">Save film</button>
</form>

<div class="card">
<h2>Kit page photos</h2>
<p class="hint" style="margin-top:0">The big photos at the top of the Kit page, in this order. Change a caption, then press "Save captions" at the bottom.</p>
<form id="captions" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="slides"></form>
<div class="photos">
  <?php foreach ($slides as $n => $sl): $src = $sl['src'] !== '' ? $sl['src'] : $sl['fallback']; ?>
    <div class="photo-card">
      <div class="pic"><img src="../<?= h($src) ?>" alt="" onerror="<?= $sl['fallback'] ? "if(!this.dataset.f){this.dataset.f=1;this.src='../" . h($sl['fallback']) . "'}else{this.outerHTML='<span>No photo yet</span>'}" : "this.outerHTML='<span>No photo yet</span>'" ?>"></div>
      <label for="cap<?= (int)$sl['id'] ?>">Photo <?= $n + 1 ?> caption</label>
      <input type="text" id="cap<?= (int)$sl['id'] ?>" name="caption[<?= (int)$sl['id'] ?>]" value="<?= h($sl['caption']) ?>" form="captions" style="margin-bottom:8px">
      <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?><input type="hidden" name="action" value="slide_photo"><input type="hidden" name="id" value="<?= (int)$sl['id'] ?>">
        <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required aria-label="New photo for photo <?= $n + 1 ?>">
        <button class="btn small" type="submit" data-busy="Uploading…">Replace photo</button>
      </form>
      <div class="actions" style="margin-top:8px">
        <?php foreach (['up' => '← Earlier', 'down' => 'Later →'] as $dir => $label): ?>
          <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="slide_move"><input type="hidden" name="id" value="<?= (int)$sl['id'] ?>"><input type="hidden" name="dir" value="<?= $dir ?>"><button class="btn ghost small" type="submit"><?= $label ?></button></form>
        <?php endforeach; ?>
        <form method="post" class="inline" onsubmit="return confirm('Remove this photo from the Kit page?')"><?= csrf_field() ?><input type="hidden" name="action" value="slide_remove"><input type="hidden" name="id" value="<?= (int)$sl['id'] ?>"><button class="btn danger small" type="submit">Remove</button></form>
      </div>
    </div>
  <?php endforeach; ?>
  <form class="photo-card" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?><input type="hidden" name="action" value="slide_add">
    <div class="pic"><span>Add a photo</span></div>
    <label for="newcap">Caption</label><input type="text" id="newcap" name="caption" style="margin-bottom:8px">
    <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required aria-label="Photo to add">
    <button class="btn small" type="submit" data-busy="Uploading…">Add photo</button>
  </form>
</div>
<div class="actions" style="margin-top:14px"><button class="btn primary" type="submit" form="captions" data-busy="Saving…">Save captions</button></div>
</div>
</section>
<script>
(function () {
  var box = document.getElementById('smtp-preset'); if (!box) return;
  function show() {
    var v = (box.querySelector('input:checked') || {}).value || 'gmail';
    document.querySelector('.smtp-fields').hidden = v === 'off';
    document.querySelector('.smtp-other').hidden = v !== 'other';
    document.querySelectorAll('.smtp-gmail-hint').forEach(function (e) { e.hidden = v !== 'gmail'; });
  }
  box.addEventListener('change', show); show();
})();
</script>
<?php page_bottom();
