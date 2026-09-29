<?php
// logout.php - Ends the session for any role.
require_once 'db.php';

$wasAdmin = ($_SESSION['role'] ?? '') === 'admin';

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

// Start a clean session only to carry the "logged out" message.
session_start();
session_regenerate_id(true);
flash('success', 'You have been logged out.');

redirect($wasAdmin ? 'admin/login.php' : 'login.php');
