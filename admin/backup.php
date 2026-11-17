<?php
/* KIDAN 72 — Admin: download a full copy of the database (.sql)
   To restore it: phpMyAdmin → choose the database → Import → this file. */
require __DIR__ . '/_inc.php';
$me = require_login();
if (!is_post()) redirect('index.php');
check_csrf();

$pdo = db();
@set_time_limit(120);
log_action('Downloaded a database backup');

$name = 'kidan72-backup-' . date('Y-m-d-His') . '.sql';
header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $name . '"');

echo "-- KIDAN 72 database backup\n-- Made " . date('F j, Y g:i A') . ' by ' . ($me['display_name'] ?: $me['username']) . "\n";
echo "-- Restore: phpMyAdmin > the database > Import > this file.\n\n";
echo "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";

$tables = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE' ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $t) {
    $create = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM)[1];
    echo "DROP TABLE IF EXISTS `$t`;\n$create;\n\n";
    $st = $pdo->query("SELECT * FROM `$t`");
    $batch = [];
    while ($row = $st->fetch(PDO::FETCH_NUM)) {
        $batch[] = '(' . implode(', ', array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), $row)) . ')';
        if (count($batch) === 100) { echo "INSERT INTO `$t` VALUES\n" . implode(",\n", $batch) . ";\n"; $batch = []; }
    }
    if ($batch) echo "INSERT INTO `$t` VALUES\n" . implode(",\n", $batch) . ";\n";
    echo "\n";
}
echo "SET FOREIGN_KEY_CHECKS = 1;\n";
