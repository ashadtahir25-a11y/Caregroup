<?php
// admin/index.php - Opening /admin/ goes straight to the admin sign-in.
define('APP_DEPTH', 1);
require_once __DIR__ . '/../db.php';
redirect(isLoggedIn() && $_SESSION['role'] === 'admin' ? 'admin_dashboard.php' : 'admin/login.php');
