<?php
/* KIDAN 72 — Admin: old "download orders" link → the all-time report (Excel) */
require __DIR__ . '/_inc.php';
require_login();
redirect('reports.php?p=all&download=xlsx');
