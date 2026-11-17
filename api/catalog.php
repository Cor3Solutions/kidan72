<?php
/* KIDAN 72 — Sends all website content from the database.
   Returns the same shape as js/data.js (see api/lib.php). */

require __DIR__ . '/lib.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') json_out(['error' => 'Method not allowed'], 405);

try {
    json_out(build_catalog());
} catch (Throwable $e) {
    error_log('KIDAN 72 catalog: ' . $e->getMessage());
    json_out(['error' => 'The shop could not load right now.'], 500);
}
