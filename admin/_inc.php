<?php
/* =========================================================
   KIDAN 72 — Admin: shared code (login, security, page layout)
   Nothing to edit here.
   ========================================================= */

declare(strict_types=1);

require dirname(__DIR__) . '/api/lib.php';
require_once dirname(__DIR__) . '/api/shop.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
      || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_name('kidan72_admin');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
session_start();

const SESSION_HOURS = 8;

/* ---------- small helpers ---------- */
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function redirect(string $to): void { header('Location: ' . $to); exit; }

function flash(?string $msg = null, string $type = 'ok'): array {
    if ($msg !== null) { $_SESSION['flash'][] = [$type, $msg]; return []; }
    $all = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $all;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">'; }
function check_csrf(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('This form has expired. Please go back, refresh the page and try again.');
    }
}
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'; }

function lines_to_list(string $text): array {
    $out = [];
    foreach (preg_split('/\r\n|\r|\n/', $text) as $l) { $l = trim($l); if ($l !== '') $out[] = $l; }
    return $out;
}
function list_to_lines(?string $json): string { return implode("\n", json_list($json)); }

function money($v): string {
    return $v === null ? '—' : 'PHP ' . number_format((float)$v, (floor((float)$v) == (float)$v) ? 0 : 2);
}

/* "1,250.50" / "PHP 300" / "" -> 1250.5 / 300 / null */
function parse_money(string $raw): ?float {
    $raw = str_replace([',', 'PHP', 'php', '₱', ' '], '', trim($raw));
    if ($raw === '') return null;
    if (!is_numeric($raw) || (float)$raw < 0 || (float)$raw > 9999999) throw new RuntimeException('Amounts: numbers only, please.');
    return round((float)$raw, 2);
}

function plain_money($v): string {
    return $v === null ? '' : rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.');
}

function youtube_ok(string $url): bool {
    return $url === '' || (bool)preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/))[A-Za-z0-9_-]{11}~', $url);
}
function youtube_id(string $url): string {
    return preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/))([A-Za-z0-9_-]{11})~', $url, $m) ? $m[1] : '';
}

/* ---------- login ----------
   A login lasts while the browser is in use (up to 8 hours idle).
   "Keep me logged in on this device" adds a 30-day cookie with a
   one-time code; each use swaps it for a new code. Logging out, or
   changing / resetting the password, removes it. */
const KEEP_COOKIE = 'k72_keep';
const KEEP_DAYS = 30;

function keep_cookie_set(string $value, int $expires): void {
    global $https;
    setcookie(KEEP_COOKIE, $value, ['expires' => $expires, 'path' => '/', 'secure' => (bool)$https, 'httponly' => true, 'samesite' => 'Lax']);
}

function keep_login(int $adminId): void {
    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    db()->prepare('INSERT INTO admin_remember (selector, validator_hash, admin_id, expires_at, device) VALUES (?, ?, ?, NOW() + INTERVAL ' . KEEP_DAYS . ' DAY, ?)')
        ->execute([$selector, hash('sha256', $validator), $adminId, mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 160)]);
    keep_cookie_set("$selector:$validator", time() + KEEP_DAYS * 86400);
}

function forget_login(?int $allForAdmin = null): void {
    try {
        if ($allForAdmin) db()->prepare('DELETE FROM admin_remember WHERE admin_id = ?')->execute([$allForAdmin]);
        $c = (string)($_COOKIE[KEEP_COOKIE] ?? '');
        if ($c !== '' && str_contains($c, ':')) db()->prepare('DELETE FROM admin_remember WHERE selector = ?')->execute([explode(':', $c, 2)[0]]);
    } catch (Throwable $e) { /* table not there yet */ }
    if (isset($_COOKIE[KEEP_COOKIE])) keep_cookie_set('', time() - 3600);
}

/* Logs in from the "keep me logged in" cookie, if it is valid */
function login_from_cookie(): bool {
    $c = (string)($_COOKIE[KEEP_COOKIE] ?? '');
    if (!preg_match('/^([a-f0-9]{24}):([a-f0-9]{64})$/', $c, $m)) { if ($c !== '') keep_cookie_set('', time() - 3600); return false; }
    try {
        $pdo = db();
        $st = $pdo->prepare('SELECT admin_id, validator_hash FROM admin_remember WHERE selector = ? AND expires_at > NOW()');
        $st->execute([$m[1]]);
        $row = $st->fetch();
        $pdo->prepare('DELETE FROM admin_remember WHERE selector = ? OR expires_at < NOW()')->execute([$m[1]]);
        if (!$row || !hash_equals($row['validator_hash'], hash('sha256', $m[2]))) { keep_cookie_set('', time() - 3600); return false; }
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int)$row['admin_id'];
        $_SESSION['seen'] = time();
        keep_login((int)$row['admin_id']);           // a fresh one-time code for next time
        return true;
    } catch (Throwable $e) { return false; }
}

function current_admin(): ?array {
    if (empty($_SESSION['admin_id']) && !login_from_cookie()) return null;
    if (time() - (int)($_SESSION['seen'] ?? 0) > SESSION_HOURS * 3600) { session_unset(); return null; }
    $_SESSION['seen'] = time();
    static $me = null;
    if ($me === null) {
        $st = db()->prepare('SELECT id, username, display_name, role FROM admins WHERE id = ?');
        $st->execute([$_SESSION['admin_id']]);
        $me = $st->fetch() ?: null;
        if (!$me) session_unset();
        elseif (!isset(ADMIN_ROLES[$me['role']])) $me['role'] = 'encoder';   // unknown level: least access
    }
    return $me;
}

/* ---------- login levels ----------
   super   = Super admin: everything, including logins, settings and backups
   admin   = Admin: the day-to-day work (orders, customers, products, stock, reports)
   encoder = Encoder: only enters products, stock and training content.
             Never sees orders, customers, sales, prices or reports. */
const ADMIN_ROLES = [
    'super'   => ['Super admin', 'Everything, including logins, settings, backups and the activity log.'],
    'admin'   => ['Admin', 'Orders, customers, products, prices, stock and reports. Cannot manage logins, settings or backups.'],
    'encoder' => ['Encoder', 'Only enters products, stock and training content. Cannot see orders, customers, sales, prices or reports.'],
];
/* Pages an Encoder may open (everything else is closed to them) */
const ENCODER_PAGES = ['index.php', 'products.php', 'product.php', 'stock.php', 'training.php', 'readiness.php', 'more.php', 'users.php', 'logout.php'];
/* Pages only a Super admin may open */
const SUPER_PAGES = ['settings.php', 'backup.php', 'sample-data.php', 'activity.php'];

function role_label(string $role): string { return ADMIN_ROLES[$role][0] ?? 'Encoder'; }
function is_super(?array $me): bool { return ($me['role'] ?? '') === 'super'; }
function is_encoder(?array $me): bool { return ($me['role'] ?? 'encoder') === 'encoder'; }
/* Sales numbers, prices, orders and customers: Admin and Super admin only */
function sees_sales(?array $me): bool { return !is_encoder($me); }

function can_open(?array $me, string $page): bool {
    $page = basename(strtok($page, '?#'));
    $role = $me['role'] ?? 'encoder';
    if ($role === 'super') return true;
    if (in_array($page, SUPER_PAGES, true)) return false;
    if ($role === 'encoder') return in_array($page, ENCODER_PAGES, true);
    return true;
}

function require_login(): array {
    if (!kidan_config()) redirect('setup.php');
    $me = current_admin();
    if (!$me) redirect('login.php');
    $page = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if (!can_open($me, $page)) {
        flash('Your login (' . role_label($me['role']) . ') cannot open that page. Ask a Super admin if you need it.', 'error');
        redirect('index.php');
    }
    return $me;
}

/* ---------- customers (CRM) ---------- */
const CUSTOMER_TYPES = ['Individual', 'Family', 'Office / Company', 'Barangay / LGU', 'School', 'Organization / NGO', 'Reseller', 'Other'];
const NOTE_KINDS = ['note' => 'Note', 'call' => 'Call', 'message' => 'Message', 'visit' => 'Visit / meeting'];

function full_address(array $r): string {
    $b = trim((string)($r['barangay'] ?? ''));
    if ($b !== '' && !preg_match('/^(barangay|brgy|poblacion)\b/i', $b)) $b = 'Brgy. ' . $b;
    $parts = array_filter([trim((string)($r['street'] ?? '')), $b, trim((string)($r['city'] ?? '')), trim((string)($r['province'] ?? ''))], fn($s) => $s !== '');
    return $parts ? implode(', ', $parts) : trim((string)($r['address'] ?? ''));
}

/* Province / City / Barangay dropdowns + street box, filled by js/ph-address.js */
function address_fields(array $v, string $idPrefix = 'a'): string {
    $o = fn($k) => h($v[$k] ?? '');
    return '<div class="row3">'
        . '<div class="field"><label for="' . $idPrefix . '-province">Province</label><select id="' . $idPrefix . '-province" name="province" data-value="' . $o('province') . '"><option value="' . $o('province') . '">' . ($o('province') ?: 'Loading…') . '</option></select></div>'
        . '<div class="field"><label for="' . $idPrefix . '-city">City / Municipality</label><select id="' . $idPrefix . '-city" name="city" data-value="' . $o('city') . '"><option value="' . $o('city') . '">' . ($o('city') ?: '—') . '</option></select></div>'
        . '<div class="field"><label for="' . $idPrefix . '-barangay">Barangay</label><select id="' . $idPrefix . '-barangay" name="barangay" data-value="' . $o('barangay') . '"><option value="' . $o('barangay') . '">' . ($o('barangay') ?: '—') . '</option></select></div>'
        . '</div>'
        . '<div class="field"><label for="' . $idPrefix . '-street">House no., street, subdivision, landmark</label><input type="text" id="' . $idPrefix . '-street" name="street" value="' . $o('street') . '" maxlength="255"></div>'
        . '<script src="../js/ph-address.js"></script><script>'
        . '(function(){var p=document.getElementById("' . $idPrefix . '-province"),c=document.getElementById("' . $idPrefix . '-city"),b=document.getElementById("' . $idPrefix . '-barangay");'
        . 'PHAddress.attach({province:p,city:c,barangay:b,base:"../assets/data/ph/",value:{province:p.dataset.value,city:c.dataset.value,barangay:b.dataset.value}});})();</script>';
}

/* ---------- activity log ---------- */
function log_action(string $text): void {
    try {
        $me = current_admin();
        db()->prepare('INSERT INTO admin_log (admin_id, admin_name, action) VALUES (?, ?, ?)')
            ->execute([$me['id'] ?? null, $me ? ($me['display_name'] ?: $me['username']) : '', mb_substr($text, 0, 255)]);
    } catch (Throwable $e) { /* the log never blocks the real work */ }
}

/* ---------- photos ---------- */

/* Saves an uploaded photo as a JPEG (max 1600 px, phone photos turned
   the right way up). Returns the saved path relative to the site. */
function save_uploaded_photo(array $file, string $folder, string $basename): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $msg = [UPLOAD_ERR_INI_SIZE => 'The photo is too large for the server.', UPLOAD_ERR_FORM_SIZE => 'The photo is too large.',
                UPLOAD_ERR_PARTIAL => 'The upload was interrupted. Please try again.', UPLOAD_ERR_NO_FILE => 'Please choose a photo first.'];
        throw new RuntimeException($msg[$file['error'] ?? UPLOAD_ERR_NO_FILE] ?? 'The photo could not be uploaded.');
    }
    if (!function_exists('imagecreatetruecolor')) throw new RuntimeException('Photo uploads need the PHP "GD" module, which is switched off on this server.');
    if ($file['size'] > 20 * 1024 * 1024) throw new RuntimeException('The photo is larger than 20 MB.');
    $info = @getimagesize($file['tmp_name']);
    $type = $info[2] ?? 0;
    if (!in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        throw new RuntimeException('Please upload a JPG, PNG or WEBP photo. (iPhone HEIC photos: change the camera setting to "Most Compatible", or send the photo to yourself first.)');
    }
    $img = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($file['tmp_name']),
        IMAGETYPE_PNG  => @imagecreatefrompng($file['tmp_name']),
        IMAGETYPE_WEBP => @imagecreatefromwebp($file['tmp_name']),
    };
    if (!$img) throw new RuntimeException('That photo could not be read. Please try another file.');

    if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $o = (int)(@exif_read_data($file['tmp_name'])['Orientation'] ?? 1);
        $angle = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        if ($angle) { $r = imagerotate($img, $angle, 0); if ($r) { imagedestroy($img); $img = $r; } }
    }

    $w = imagesx($img); $hgt = imagesy($img); $max = 1600;
    $scale = min(1, $max / max($w, $hgt));
    $nw = (int)round($w * $scale); $nh = (int)round($hgt * $scale);
    $out = imagecreatetruecolor($nw, $nh);
    imagefill($out, 0, 0, imagecolorallocate($out, 255, 253, 248)); // transparent PNGs get the site's card colour
    imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $hgt);
    imagedestroy($img);

    $dir = KIDAN_ROOT . '/uploads/' . $folder;
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) throw new RuntimeException('The uploads folder could not be created on the server.');
    $guard = KIDAN_ROOT . '/uploads/.htaccess';
    if (!is_file($guard)) @file_put_contents($guard, "# Photos only: never run scripts from this folder\n<FilesMatch \"\\.(php|phtml|phar|pl|py|cgi|sh)$\">\n  Require all denied\n</FilesMatch>\nOptions -Indexes\n");

    $rel = 'uploads/' . $folder . '/' . $basename . '.jpg';
    if (!imagejpeg($out, KIDAN_ROOT . '/' . $rel, 85)) throw new RuntimeException('The photo could not be saved on the server.');
    imagedestroy($out);
    return $rel;
}

/* Where photo n of a product is shown from right now */
function current_photo(string $serial, int $n, array $uploads): string {
    if (isset($uploads[$n])) return '../' . $uploads[$n]['file'] . '?v=' . strtotime((string)$uploads[$n]['updated_at']);
    return '../' . default_photo($serial, $n);
}

/* Icon names from js/data.js (for the icon dropdown) */
function icon_names(): array {
    $src = @file_get_contents(KIDAN_ROOT . '/js/data.js') ?: '';
    $pos = strpos($src, 'KIDAN.icons');
    if ($pos === false) return [];
    preg_match_all('/^\s{2}([a-z0-9_]+)\s*:/mi', substr($src, $pos), $m);
    return $m[1];
}

/* ---------- small display helpers ---------- */
const STATUS_LABELS = ['new' => 'New', 'confirmed' => 'Confirmed', 'paid' => 'Paid', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'];

function status_chip(string $s): string {
    return '<span class="status ' . h($s) . '">' . h(STATUS_LABELS[$s] ?? ucfirst($s)) . '</span>';
}

/* "Today 9:04 AM", "Yesterday 3:10 PM", "Oct 12", "Oct 12, 2025" */
function when(?string $ts, bool $time = true): string {
    if (!$ts) return '';
    $t = strtotime($ts);
    $d = date('Y-m-d', $t);
    $clock = $time ? ' ' . date('g:i A', $t) : '';
    if ($d === date('Y-m-d')) return 'Today' . $clock;
    if ($d === date('Y-m-d', strtotime('-1 day'))) return 'Yesterday' . $clock;
    return date(date('Y', $t) === date('Y') ? 'M j' : 'M j, Y', $t);
}

/* One order as a tappable row (dashboard, orders list, customer page) */
function order_row(array $o, bool $withCustomer = true): string {
    $place = implode(', ', array_filter([$o['city'] ?? '', $o['province'] ?? '']));
    $proof = !empty($o['proofs']) && in_array($o['status'], ['new', 'confirmed'], true);
    $items = (int)($o['item_count'] ?? 0);
    return '<a class="row" href="order.php?id=' . (int)$o['id'] . '">'
         . '<span class="row-main"><span class="row-title">' . ($withCustomer ? h($o['customer']) : h($o['order_no'])) . '</span>'
         . '<span class="row-sub">' . ($withCustomer ? h($o['order_no']) . ' · ' : '') . $items . ' item' . ($items === 1 ? '' : 's') . ($place !== '' ? ' · ' . h($place) : '') . '</span></span>'
         . '<span class="row-side"><span class="row-money">' . h(money($o['total'])) . '</span>'
         . '<span class="row-tags">' . ($proof ? '<span class="badge due">Proof sent</span>' : '') . status_chip($o['status']) . '</span>'
         . '<span class="row-sub">' . h(when($o['created_at'])) . '</span></span></a>';
}

function icon(string $name): string {
    $p = [
        'home'      => '<path d="M3 11.5 12 4l9 7.5"/><path d="M5.5 10v10h13V10"/><path d="M10 20v-5.5h4V20"/>',
        'orders'    => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h3"/>',
        'customers' => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><circle cx="17" cy="9" r="2.6"/><path d="M16 14.2c2.8.2 5 2.6 5 5.8"/>',
        'products'  => '<path d="M3.5 7.5 12 3l8.5 4.5v9L12 21l-8.5-4.5z"/><path d="M3.5 7.5 12 12l8.5-4.5M12 12v9"/>',
        'stock'     => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V3h6v1M9 10h6M9 14h6M9 18h3"/>',
        'more'      => '<circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/>',
        'site'      => '<path d="M14 4h6v6"/><path d="M20 4 11 13"/><path d="M18 14v6H4V6h6"/>',
    ][$name] ?? '';
    return '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true">' . $p . '</svg>';
}

/* ---------- page layout ----------
   Phone: a bar at the bottom with the 4 main pages + More.
   Computer: the same pages in a menu on the left, with the More pages listed too. */
function page_top(string $title, string $active = '', ?array $me = null): void {
    $main = ['index.php' => ['Home', 'home'], 'orders.php' => ['Orders', 'orders'], 'customers.php' => ['Customers', 'customers'], 'products.php' => ['Products', 'products']];
    $more = ['Business' => ['reports.php' => 'Reports', 'stock.php' => 'Stock', 'more.php' => 'Downloads & backup'],
             'Website' => ['training.php' => 'Training modules', 'readiness.php' => 'Readiness check', 'settings.php' => 'Settings'],
             'Team' => ['users.php' => 'Logins', 'activity.php' => 'Activity log']];
    if ($me) {
        // show only the pages this login can open
        $main = array_filter($main, fn($f) => can_open($me, $f), ARRAY_FILTER_USE_KEY);
        if (is_encoder($me)) $main['stock.php'] = ['Stock', 'stock'];
        foreach ($more as $g => $links) {
            $links = array_filter($links, fn($f) => can_open($me, $f) && !(is_encoder($me) && $f === 'stock.php'), ARRAY_FILTER_USE_KEY);
            if (is_encoder($me) && $g === 'Business') unset($links['more.php']);
            if (!is_super($me) && isset($links['users.php'])) $links['users.php'] = 'My account';
            unset($more[$g]);
            if ($links) $more[!is_super($me) && $g === 'Team' ? 'Account' : $g] = $links;
        }
    }
    $inMore = $active !== '' && !isset($main[$active]);
    $badges = [];
    if ($me && sees_sales($me)) {
        try {
            $pdo = db();
            $badges['orders.php'] = (int)$pdo->query("SELECT COUNT(*) FROM orders o WHERE o.status = 'new'
                OR (o.status = 'confirmed' AND EXISTS (SELECT 1 FROM payment_proofs p WHERE p.order_id = o.id))")->fetchColumn();
            $badges['customers.php'] = (int)$pdo->query('SELECT COUNT(*) FROM customers WHERE follow_up_on IS NOT NULL AND follow_up_on <= CURDATE()')->fetchColumn();
        } catch (Throwable $e) {}
    }
    $badge = fn($f) => !empty($badges[$f]) ? ' <b class="pill">' . $badges[$f] . '</b>' : '';
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#17140F">
<title><?= h($title) ?> · KIDAN 72 Admin</title>
<link rel="icon" href="../assets/img/favicon.png">
<link rel="manifest" href="manifest.webmanifest">
<link rel="apple-touch-icon" href="apple-touch-icon.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="KIDAN 72">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700&family=Barlow:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap">
<link rel="stylesheet" href="admin.css?v=20261104a">
</head>
<body class="<?= $me ? 'has-nav' : 'no-nav' ?>">
<?php if ($me): ?>
<aside class="side" aria-label="Admin menu">
  <a class="brand" href="index.php"><img src="../assets/img/kidan72-logo-header.png" alt="KIDAN 72"><span>Admin</span></a>
  <nav class="side-nav">
    <?php foreach ($main as $f => [$label, $ic]): ?>
      <a href="<?= $f ?>"<?= $active === $f ? ' aria-current="page"' : '' ?>><?= icon($ic) ?><span><?= h($label) ?></span><?= $badge($f) ?></a>
    <?php endforeach; ?>
    <?php foreach ($more as $group => $links): ?>
      <p class="side-group"><?= h($group) ?></p>
      <?php foreach ($links as $f => $label): ?><a class="sub" href="<?= $f ?>"<?= $active === $f ? ' aria-current="page"' : '' ?>><span><?= h($label) ?></span></a><?php endforeach; ?>
    <?php endforeach; ?>
  </nav>
  <div class="side-foot">
    <a href="../index.html" target="_blank" rel="noopener"><?= icon('site') ?><span>View website</span></a>
    <form method="post" action="logout.php"><?= csrf_field() ?><button type="submit" class="side-logout">Log out <span><?= h($me['display_name'] ?: $me['username']) ?> · <?= h(role_label($me['role'])) ?></span></button></form>
  </div>
</aside>
<header class="bar">
  <a class="brand" href="index.php"><img src="../assets/img/kidan72-logo-header.png" alt="KIDAN 72 Admin"></a>
  <span class="bar-title"><?= h($title) ?></span>
</header>
<nav class="tabbar" aria-label="Main">
  <?php foreach ($main as $f => [$label, $ic]): ?>
    <a href="<?= $f ?>"<?= $active === $f ? ' aria-current="page"' : '' ?>><?= icon($ic) ?><span><?= h($label) ?></span><?= $badge($f) ?></a>
  <?php endforeach; ?>
  <a href="more.php"<?= $inMore ? ' aria-current="page"' : '' ?>><?= icon('more') ?><span>More</span></a>
</nav>
<?php else: ?>
<header class="bar solo"><span class="brand"><img src="../assets/img/kidan72-logo-header.png" alt="KIDAN 72"><span>Admin</span></span></header>
<?php endif; ?>
<main class="wrap">
<?php foreach (flash() as [$type, $msg]): ?>
  <div class="flash <?= h($type) ?>" role="status"><?= h($msg) ?></div>
<?php endforeach;
}

function page_bottom(): void {
    ?></main>
<script>
/* Ask before leaving a page with unsaved changes; buttons that say what they do while working */
(function () {
  var dirty = false;
  document.querySelectorAll('form[data-warn]').forEach(function (f) {
    f.addEventListener('input', function () { dirty = true; f.classList.add('is-dirty'); });
    f.addEventListener('submit', function () { dirty = false; });
  });
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
  document.querySelectorAll('form').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      var b = e.submitter;
      if (b && b.dataset.busy && !e.defaultPrevented) { setTimeout(function () { b.disabled = true; b.textContent = b.dataset.busy; }, 0); }
    });
  });
})();
</script>
</body>
</html>
<?php
}
