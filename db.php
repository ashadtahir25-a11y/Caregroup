<?php
// db.php - Database connection, secure session, and shared helpers.
// Every page includes this file first.

// ---- Database settings (change these to match your XAMPP / server) ----
$host    = 'localhost';
$db      = 'care_db';
$user    = 'root';
$pass    = '';
$charset = 'utf8mb4';

// Hide raw PHP errors from visitors; they are written to the PHP error log instead.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Pakistan time for PHP dates (XAMPP often defaults to Europe/Berlin)
date_default_timezone_set('Asia/Karachi');

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->exec("SET time_zone = '+05:00'");   // MySQL CURDATE()/NOW() also use Pakistan time
} catch (PDOException $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    die('The database is not reachable. Start MySQL in XAMPP and import database/schema.sql, then reload this page.');
}

// ---- Secure session ----
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,                                   // JavaScript cannot read the cookie
        'samesite' => 'Lax',                                  // Blocks most cross-site request tricks
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_name('CARESESSID');
    session_start();
}

require_once __DIR__ . '/includes/functions.php';