<?php
/* KIDAN 72 — Log out */
require __DIR__ . '/_inc.php';
if (is_post()) {
    check_csrf();
    forget_login();
    $_SESSION = [];
    session_destroy();
}
redirect('login.php');
