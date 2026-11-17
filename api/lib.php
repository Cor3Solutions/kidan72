<?php
/* =========================================================
   KIDAN 72 — Shared PHP code (database connection + catalog)
   Used by api/catalog.php, api/orders.php and the admin page.
   Nothing to edit here. Database passwords live in api/config.php.
   ========================================================= */

declare(strict_types=1);

define('KIDAN_ROOT', dirname(__DIR__));

// Philippine time for every date shown or compared (hosts often run on UTC)
date_default_timezone_set('Asia/Manila');
define('KIDAN_CONFIG_FILE', __DIR__ . '/config.php');

function kidan_config(): ?array {
    static $cfg = null;
    if ($cfg === null) {
        if (!is_file(KIDAN_CONFIG_FILE)) return null;
        $cfg = require KIDAN_CONFIG_FILE;
    }
    return is_array($cfg) ? $cfg : null;
}

function db_connect(array $c): PDO {
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $c['db_host'], (int)($c['db_port'] ?? 3306), $c['db_name']);
    return new PDO($dsn, $c['db_user'], $c['db_pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $cfg = kidan_config();
        if (!$cfg) throw new RuntimeException('The site is not set up yet (api/config.php is missing).');
        $pdo = db_connect($cfg);
        $pdo->exec("SET time_zone = '+08:00'");
        kidan_migrate($pdo);
    }
    return $pdo;
}

/* Runs any database upgrades in api/migrations.php that this database
   has not had yet (see settings.schema_version). Quick no-op otherwise. */
function kidan_migrate(PDO $pdo): void {
    try {
        $v = $pdo->query("SELECT v FROM settings WHERE k = 'schema_version'")->fetchColumn();
    } catch (PDOException $e) {
        return; // tables not created yet: admin/setup.php does that first
    }
    $current = $v === false ? 1 : (int)$v;
    $steps = require __DIR__ . '/migrations.php';
    if (max(array_keys($steps)) <= $current) return;

    if (!(int)$pdo->query("SELECT GET_LOCK('kidan72_migrate', 15)")->fetchColumn()) return;
    try {
        $v = $pdo->query("SELECT v FROM settings WHERE k = 'schema_version'")->fetchColumn();
        $current = $v === false ? 1 : (int)$v;
        ksort($steps);
        foreach ($steps as $n => $step) {
            if ($n <= $current) continue;
            $step($pdo);
            $pdo->prepare("INSERT INTO settings (k, v) VALUES ('schema_version', ?) ON DUPLICATE KEY UPDATE v = VALUES(v)")->execute([(string)$n]);
        }
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('kidan72_migrate')");
    }
}

/* 0917 123 4567 / +63 917-123-4567 / 639171234567 → "09171234567" */
function mobile_key(string $mobile): ?string {
    $d = preg_replace('/\D/', '', $mobile);
    if (strlen($d) === 12 && str_starts_with($d, '63')) $d = '0' . substr($d, 2);
    if (strlen($d) === 10 && str_starts_with($d, '9')) $d = '0' . $d;
    return strlen($d) >= 7 ? substr($d, 0, 20) : null;
}

/* Finds the customer with this mobile number (or creates one) and keeps
   their name and latest address up to date. Returns the customer id. */
function kidan_customer_for_order(PDO $pdo, string $name, string $mobile, array $addr): int {
    $key = mobile_key($mobile);
    $a = array_merge(['province' => '', 'city' => '', 'barangay' => '', 'street' => ''], array_map(fn($v) => (string)$v, $addr));
    if ($key !== null) {
        $st = $pdo->prepare('SELECT id FROM customers WHERE mobile_key = ?');
        $st->execute([$key]);
        if ($id = $st->fetchColumn()) {
            $set = 'name = ?, mobile = ?';
            $args = [$name, $mobile];
            if ($a['province'] !== '' || $a['street'] !== '') {   // a newer address replaces the old one
                $set .= ', province = ?, city = ?, barangay = ?, street = ?';
                array_push($args, $a['province'], $a['city'], $a['barangay'], $a['street']);
            }
            $pdo->prepare("UPDATE customers SET $set WHERE id = ?")->execute([...$args, $id]);
            return (int)$id;
        }
    }
    $pdo->prepare('INSERT INTO customers (name, mobile, mobile_key, province, city, barangay, street) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$name, $mobile, $key, $a['province'], $a['city'], $a['barangay'], $a['street']]);
    return (int)$pdo->lastInsertId();
}

/* Runs a .sql file (used by the setup page) */
function run_sql_file(PDO $pdo, string $file): void {
    $sql = (string)file_get_contents($file);
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);           // drop comment lines
    foreach (preg_split('/;\s*\n/', $sql) as $stmt) {
        if (trim($stmt) !== '') $pdo->exec($stmt);
    }
}

/* Does this product have a photo the website can show? */
function product_has_photo(string $serial, array $uploadsForSerial): bool {
    return isset($uploadsForSerial[1]) || is_file(KIDAN_ROOT . '/' . default_photo($serial, 1));
}

/* The site's own address, e.g. https://kidan72.ph/ (works in a sub-folder too) */
function site_base_url(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = preg_replace('/[^A-Za-z0-9.:\-\[\]]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $dir = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/')));
    // scripts live in /, /api or /admin: the site root is the folder above api/admin
    $dir = preg_replace('~/(api|admin)$~', '', rtrim($dir, '/'));
    return ($https ? 'https' : 'http') . '://' . $host . $dir . '/';
}

/* =========================================================
   Email
   - With an email account set up in the admin page (Settings → Order
     alerts → Email sending), emails go out through that account (SMTP),
     e.g. the shop's Gmail. They arrive reliably and replies go to the shop.
   - Without it, the host's built-in mail is used. On Hostinger that is
     limited (100 a day) and often ends up in spam or is dropped.
   Every email is written to the email log (shown on the order page).
   ========================================================= */
function mail_last_error(?string $set = null): string {
    static $err = '';
    if ($set !== null) $err = $set;
    return $err;
}

function smtp_settings(): ?array {
    try { $host = setting('smtp_host'); } catch (Throwable $e) { return null; }
    if ($host === '') return null;
    return [
        'host' => $host, 'port' => (int)(setting('smtp_port') ?: 587), 'secure' => setting('smtp_secure') ?: 'tls',
        'user' => setting('smtp_user'), 'pass' => setting('smtp_pass'), 'name' => setting('mail_from_name') ?: 'KIDAN 72',
    ];
}

/* Sends an email (plain text + a simple formatted version).
   Returns true when the mail server accepted it; otherwise false and
   mail_last_error() says why in plain words. */
function send_mail(string $to, string $subject, string $body, string $replyTo = '', int $orderId = 0): bool {
    mail_last_error('');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { mail_last_error('That email address does not look right.'); return false; }
    $smtp = smtp_settings();
    $host = preg_replace('/^www\./', '', strtolower((string)parse_url(site_base_url(), PHP_URL_HOST)));
    $from = $smtp ? $smtp['user'] : 'no-reply@' . ($host ?: 'localhost');
    $name = $smtp['name'] ?? 'KIDAN 72';
    if (!filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        try { $replyTo = setting('contact_email'); } catch (Throwable $e) { $replyTo = ''; }
        if (!filter_var($replyTo, FILTER_VALIDATE_EMAIL)) $replyTo = $from;
    }
    $boundary = 'k72-' . bin2hex(random_bytes(8));
    $encSubject = function_exists('mb_encode_mimeheader') ? mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n") : $subject;
    $encName = function_exists('mb_encode_mimeheader') ? mb_encode_mimeheader($name, 'UTF-8', 'B', "\r\n") : $name;
    $headers = [
        'From: ' . $encName . ' <' . $from . '>',
        'Reply-To: ' . $replyTo,
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'X-Mailer: KIDAN72',
    ];
    $text = str_replace(["\r\n", "\r"], "\n", $body);
    $mime = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
          . chunk_split(base64_encode($text)) . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
          . chunk_split(base64_encode(mail_html($subject, $text))) . "--$boundary--\r\n";

    if ($smtp) {
        $ok = smtp_send($smtp, $from, $to, array_merge(['To: ' . $to, 'Subject: ' . $encSubject, 'Date: ' . date('r'),
                        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . ($host ?: 'kidan72') . '>'], $headers), $mime);
    } else {
        $ok = @mail($to, $encSubject, $mime, implode("\r\n", $headers));
        if (!$ok) mail_last_error('This server could not send the email. Set up "Email sending" in Settings → Order alerts.');
    }
    try {
        db()->prepare('INSERT INTO email_log (order_id, to_addr, subject, ok, error, via) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$orderId ?: null, mb_substr($to, 0, 160), mb_substr($subject, 0, 200), $ok ? 1 : 0, mb_substr(mail_last_error(), 0, 255), $smtp ? 'smtp' : 'server']);
    } catch (Throwable $e) { /* log table not there yet */ }
    if (!$ok) error_log('KIDAN 72 email to ' . $to . ': ' . mail_last_error());
    return $ok;
}

/* A clean, simple HTML version of a plain-text email */
function mail_html(string $title, string $text): string {
    $esc = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $esc = preg_replace('~(https?://[^\s<]+)~', '<a href="$1" style="color:#B9481A">$1</a>', $esc);
    $paras = array_map(fn($p) => '<p style="margin:0 0 14px">' . nl2br($p) . '</p>', preg_split("/\n{2,}/", trim($esc)));
    return '<!doctype html><html><body style="margin:0;background:#F3EEE5;font-family:Arial,Helvetica,sans-serif;color:#17140F">'
         . '<div style="max-width:560px;margin:0 auto;padding:24px 16px">'
         . '<div style="background:#17140F;color:#fff;padding:14px 20px;font-weight:bold;letter-spacing:1px">KIDAN 72</div>'
         . '<div style="background:#FFFDF9;border:1px solid #E2D8C9;border-top:0;padding:22px 20px;font-size:15px;line-height:1.55">'
         . '<h1 style="font-size:19px;margin:0 0 16px">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>' . implode('', $paras) . '</div>'
         . '<p style="font-size:12px;color:#7D766D;text-align:center;margin:14px 0 0">KIDAN 72 · Always Ready.</p></div></body></html>';
}

/* Minimal SMTP client: SSL (port 465) or STARTTLS (port 587), AUTH LOGIN */
function smtp_send(array $s, string $from, string $to, array $headers, string $body): bool {
    $fail = function (string $msg) use (&$fp) { mail_last_error($msg); if (is_resource($fp)) { @fwrite($fp, "QUIT\r\n"); @fclose($fp); } return false; };
    $target = ($s['secure'] === 'ssl' ? 'ssl://' : 'tcp://') . $s['host'] . ':' . $s['port'];
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true, 'peer_name' => $s['host']]]);
    $fp = @stream_socket_client($target, $errno, $errstr, 12, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) return $fail("Could not reach the email server {$s['host']}:{$s['port']} ($errstr). Check the server name and port.");
    stream_set_timeout($fp, 15);
    $read = function () use ($fp): array {
        $all = ''; $code = 0;
        while (($line = fgets($fp, 1024)) !== false) {
            $all .= $line; $code = (int)substr($line, 0, 3);
            if (strlen($line) < 4 || $line[3] !== '-') break;
        }
        return [$code, trim($all)];
    };
    $cmd = function (string $c, array $okCodes) use ($fp, $read): array {
        fwrite($fp, $c . "\r\n");
        [$code, $msg] = $read();
        return [in_array($code, $okCodes, true), $code, $msg];
    };
    [$code, $msg] = $read();
    if ($code !== 220) return $fail("The email server did not answer properly: $msg");
    $me = preg_replace('/[^a-z0-9.-]/i', '', (string)parse_url(site_base_url(), PHP_URL_HOST)) ?: 'localhost';
    [$ok, , $msg] = $cmd("EHLO $me", [250]);
    if (!$ok) return $fail("The email server refused the greeting: $msg");
    if ($s['secure'] === 'tls') {
        [$ok, , $msg] = $cmd('STARTTLS', [220]);
        if (!$ok) return $fail("The email server does not offer a secure connection on this port: $msg");
        if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT') ? STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT : 0))) {
            return $fail('Could not start a secure connection with the email server.');
        }
        [$ok, , $msg] = $cmd("EHLO $me", [250]);
        if (!$ok) return $fail("The email server refused the greeting: $msg");
    }
    if ($s['user'] !== '') {
        [$ok, , $msg] = $cmd('AUTH LOGIN', [334]);
        if (!$ok) return $fail("The email server does not accept this login method: $msg");
        $cmd(base64_encode($s['user']), [334]);
        [$ok, $code, $msg] = $cmd(base64_encode($s['pass']), [235]);
        if (!$ok) {
            $gmail = stripos($s['host'], 'gmail') !== false;
            return $fail('The email account refused the username or password.' . ($gmail
                ? ' For Gmail, use an App Password (16 letters), not the normal Gmail password: Google Account → Security → 2-Step Verification → App passwords.'
                : '') . " ($code)");
        }
    }
    [$ok, , $msg] = $cmd('MAIL FROM:<' . $from . '>', [250]);
    if (!$ok) return $fail("The email server refused the sender address: $msg");
    [$ok, , $msg] = $cmd('RCPT TO:<' . $to . '>', [250, 251]);
    if (!$ok) return $fail("The email server refused the address $to: $msg");
    [$ok, , $msg] = $cmd('DATA', [354]);
    if (!$ok) return $fail("The email server refused the message: $msg");
    $data = implode("\r\n", $headers) . "\r\n\r\n" . $body;
    $data = preg_replace('/^\./m', '..', str_replace(["\r\n", "\n"], ["\n", "\r\n"], $data));
    [$ok, , $msg] = $cmd($data . "\r\n.", [250]);
    if (!$ok) return $fail("The email server did not accept the message: $msg");
    @fwrite($fp, "QUIT\r\n"); @fclose($fp);
    return true;
}

function json_out($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* A product's choice, e.g. {"label":"Colour","values":["Black","Brown"],"photos":[1,2]}
   (photos: which photo shows each value). Null when there is none. */
function product_choice(?string $json): ?array {
    $c = json_decode((string)$json, true);
    if (!is_array($c) || empty($c['values']) || !is_array($c['values'])) return null;
    $values = array_values(array_filter(array_map(fn($v) => trim((string)$v), $c['values']), fn($v) => $v !== ''));
    if (!$values) return null;
    $out = ['label' => trim((string)($c['label'] ?? '')) ?: 'Choice', 'values' => $values];
    if (!empty($c['photos']) && is_array($c['photos'])) $out['photos'] = array_map('intval', $c['photos']);
    return $out;
}

function json_list(?string $text): array {
    if ($text === null || $text === '') return [];
    $v = json_decode($text, true);
    return is_array($v) ? $v : [];
}

function setting(string $key, string $default = ''): string {
    $st = db()->prepare('SELECT v FROM settings WHERE k = ?');
    $st->execute([$key]);
    $v = $st->fetchColumn();
    return $v === false ? $default : (string)$v;
}

function price_value($v): ?float {
    return ($v === null || $v === '') ? null : (float)$v;
}

/* The default photo path the site has always used */
function default_photo(string $serial, int $n): string {
    return 'assets/img/products/' . rawurlencode($serial) . ($n > 1 ? '-' . $n : '') . '.jpg';
}

/* Builds the catalog in exactly the same shape as js/data.js */
function build_catalog(): array {
    $pdo = db();

    $categories = [];
    foreach ($pdo->query('SELECT code, name FROM categories ORDER BY sort, code') as $r) {
        $categories[$r['code']] = $r['name'];
    }

    $products = $pdo->query('SELECT * FROM products ORDER BY sort, serial')->fetchAll();
    $kindOf = [];
    foreach ($products as $p) $kindOf[$p['serial']] = $p['kind'];

    $children = [];
    foreach ($pdo->query('SELECT bag_serial, child_serial FROM bag_contents ORDER BY sort, child_serial') as $r) {
        $children[$r['bag_serial']][] = $r['child_serial'];
    }

    $uploads = [];
    foreach ($pdo->query('SELECT serial, position, file, UNIX_TIMESTAMP(updated_at) AS v FROM product_photos') as $r) {
        $uploads[$r['serial']][(int)$r['position']] = $r['file'] . '?v=' . $r['v'];
    }

    $prices = []; $bags = []; $items = []; $notSold = []; $photoFiles = []; $soldOut = [];
    foreach ($products as $p) {
        $s = $p['serial'];
        $prices[$s] = price_value($p['price']);
        if (!$p['sold_separately']) $notSold[] = $s;
        if (!empty($p['sold_out'])) $soldOut[] = $s;
        $photos = max(1, (int)$p['photos']);

        if (!empty($uploads[$s])) {
            $list = [];
            for ($n = 1; $n <= $photos; $n++) $list[] = $uploads[$s][$n] ?? default_photo($s, $n);
            $photoFiles[$s] = $list;
        }

        if ($p['kind'] === 'bag') {
            $b = ['serial' => $s, 'icon' => $p['icon'], 'name' => $p['name'], 'label' => $p['label']];
            if ($photos > 1) $b['photos'] = $photos;
            if ($p['photo_hidden']) $b['photo'] = false;
            $b['short'] = $p['short_text'];
            $b['summary'] = $p['summary'];
            $inc = []; $con = [];
            foreach ($children[$s] ?? [] as $c) {
                if (($kindOf[$c] ?? '') === 'bag') $inc[] = $c; else $con[] = $c;
            }
            if ($inc) $b['includes'] = $inc;
            if ($con) $b['contents'] = $con;
            $b['specs'] = json_list($p['specs']);
            if ($ch = product_choice($p['choice'] ?? null)) $b['choice'] = $ch;
            $bags[] = $b;
        } else {
            $it = [
                'serial' => $s, 'cat' => $p['cat'], 'icon' => $p['icon'], 'name' => $p['name'],
                'short' => $p['short_text'], 'why' => $p['why'], 'steps' => json_list($p['steps']),
                'spec' => $p['spec'], 'maintain' => $p['maintain'],
            ];
            $parts = json_list($p['parts']);
            if ($parts) $it['parts'] = $parts;
            $it['q'] = $p['search_q'];
            if ($photos > 1) $it['photos'] = $photos;
            if ($p['photo_hidden']) $it['photo'] = false;
            $it['video'] = $p['video'];
            if ($ch = product_choice($p['choice'] ?? null)) $it['choice'] = $ch;
            $items[] = $it;
        }
    }

    $moduleItems = [];
    foreach ($pdo->query('SELECT module_code, serial FROM module_items ORDER BY sort, serial') as $r) {
        $moduleItems[$r['module_code']][] = $r['serial'];
    }
    $modules = [];
    foreach ($pdo->query('SELECT * FROM modules ORDER BY sort, code') as $m) {
        $modules[] = [
            'code' => $m['code'], 'icon' => $m['icon'], 'name' => $m['name'], 'short' => $m['short_text'],
            'lessons' => json_list($m['lessons']), 'items' => $moduleItems[$m['code']] ?? [],
            'q' => $m['search_q'], 'video' => $m['video'],
        ];
    }

    $readiness = [];
    foreach ($pdo->query('SELECT code, text, module_code FROM readiness ORDER BY sort, code') as $r) {
        $readiness[] = ['code' => $r['code'], 'text' => $r['text'], 'module' => $r['module_code']];
    }

    $kitSlides = [];
    foreach ($pdo->query('SELECT src, fallback, caption FROM kit_slides ORDER BY sort, id') as $r) {
        $sl = ['src' => $r['src']];
        if ($r['fallback'] !== '') $sl['fallback'] = $r['fallback'];
        $sl['caption'] = $r['caption'];
        $kitSlides[] = $sl;
    }

    // Stock: sold out by itself at 0, "Only 3 left" when low (see api/stock.php)
    require_once __DIR__ . '/stock.php';
    try { $stock = stock_public($pdo); } catch (Throwable $e) { $stock = ['soldOut' => [], 'choiceOut' => [], 'left' => []]; }

    return [
        'prices'            => (object)$prices,
        'contact'           => [
            'messenger' => setting('contact_messenger'),
            'facebook'  => setting('contact_facebook'),
            'viber'     => setting('contact_viber'),
            'email'     => setting('contact_email'),
        ],
        'brandFilm'         => setting('brand_film'),
        'kitSlides'         => $kitSlides,
        'categories'        => (object)$categories,
        'bags'              => $bags,
        'notSoldSeparately' => $notSold,
        'items'             => $items,
        'modules'           => $modules,
        'readiness'         => $readiness,
        'photoFiles'        => (object)$photoFiles,
        'soldOut'           => array_values(array_unique(array_merge($soldOut, $stock['soldOut']))),
        'choiceOut'         => (object)$stock['choiceOut'],   // e.g. {"K72-PCH-001": ["Brown"]}: that colour is out of stock
        'stockLeft'         => (object)$stock['left'],        // e.g. {"K72-MED-001": 3}: only 3 left
    ];
}

function client_ip(): string {
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}
