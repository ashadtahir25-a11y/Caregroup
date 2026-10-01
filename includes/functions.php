<?php
// includes/functions.php - Helpers shared by every page.

/* ------------------------------------------------------------------
 * Paths
 * Pages inside a sub-folder (e.g. admin/) define APP_DEPTH = 1 before
 * including db.php, so links and assets still point to the right place.
 * ------------------------------------------------------------------ */
function url(string $path = ''): string
{
    $prefix = defined('APP_DEPTH') ? str_repeat('../', (int) APP_DEPTH) : '';
    return $prefix . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit();
}

/* ------------------------------------------------------------------
 * Output escaping
 * ------------------------------------------------------------------ */
function h($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/* ------------------------------------------------------------------
 * CSRF protection
 * Every POST form prints csrf_field(); every POST handler calls csrf_check().
 * ------------------------------------------------------------------ */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function csrf_check(): bool
{
    $sent = $_POST['csrf_token'] ?? '';
    return is_string($sent) && $sent !== '' && hash_equals(csrf_token(), $sent);
}

/* ------------------------------------------------------------------
 * Flash messages (shown once, on the next page load, as a toast)
 * ------------------------------------------------------------------ */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_pull(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/* ------------------------------------------------------------------
 * Authentication
 * ------------------------------------------------------------------ */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id'], $_SESSION['role']);
}

function dashboard_for(string $role): string
{
    switch ($role) {
        case 'admin':  return 'admin_dashboard.php';
        case 'doctor': return 'doctor_dashboard.php';
        default:       return 'patient_dashboard.php';
    }
}

// Protects a page. Admin pages send visitors to the separate admin login.
function checkRole(string $role): void
{
    if (!isLoggedIn() || $_SESSION['role'] !== $role) {
        redirect($role === 'admin' ? 'admin/login.php' : 'login.php');
    }
}

// Starts a fresh session for a user who has just proved their password.
function login_user(PDO $pdo, array $user): void
{
    session_regenerate_id(true);   // stops session-fixation attacks

    $_SESSION['user_id']  = (int) $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role']     = $user['role'];
    $_SESSION['email']    = $user['email'];
    unset($_SESSION['doctor_id'], $_SESSION['patient_id']);

    if ($user['role'] === 'doctor') {
        $stmt = $pdo->prepare('SELECT id FROM doctors WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        if ($id = $stmt->fetchColumn()) {
            $_SESSION['doctor_id'] = (int) $id;
        }
    } elseif ($user['role'] === 'patient') {
        $stmt = $pdo->prepare('SELECT id FROM patients WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        if ($id = $stmt->fetchColumn()) {
            $_SESSION['patient_id'] = (int) $id;
        }
    }
}

/* ------------------------------------------------------------------
 * Brute-force protection (uses the login_attempts table)
 * If the table is missing, the page still works; the problem is logged.
 * ------------------------------------------------------------------ */
function client_ip(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
}

// Returns minutes left in the lock-out, or 0 if the visitor may try again.
function login_locked_minutes(PDO $pdo, string $scope, int $maxAttempts, int $windowMinutes): int
{
    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS fails, MIN(attempted_at) AS first_fail
               FROM login_attempts
              WHERE scope = ? AND ip = ? AND attempted_at > (NOW() - INTERVAL ? MINUTE)'
        );
        $stmt->execute([$scope, client_ip(), $windowMinutes]);
        $row = $stmt->fetch();
        if ($row && (int) $row['fails'] >= $maxAttempts) {
            $unlockAt = strtotime($row['first_fail']) + $windowMinutes * 60;
            return max(1, (int) ceil(($unlockAt - time()) / 60));
        }
    } catch (PDOException $e) {
        error_log('login_attempts check failed (run database/update_part1.sql): ' . $e->getMessage());
    }
    return 0;
}

function login_record_failure(PDO $pdo, string $scope, string $username): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO login_attempts (scope, ip, username) VALUES (?, ?, ?)');
        $stmt->execute([$scope, client_ip(), substr($username, 0, 50)]);
    } catch (PDOException $e) {
        error_log('login_attempts insert failed: ' . $e->getMessage());
    }
}

function login_clear_failures(PDO $pdo, string $scope): void
{
    try {
        $stmt = $pdo->prepare('DELETE FROM login_attempts WHERE scope = ? AND ip = ?');
        $stmt->execute([$scope, client_ip()]);
    } catch (PDOException $e) {
        error_log('login_attempts cleanup failed: ' . $e->getMessage());
    }
}

// Checks username + password against the users table for the allowed roles.
// Returns the user row on success, or null.
function verify_credentials(PDO $pdo, string $username, string $password, array $allowedRoles): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        return null;
    }
    if (!in_array($user['role'], $allowedRoles, true)) {
        return null;   // same message as a wrong password, so roles are not revealed
    }

    if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
        $upd = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
        $upd->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }
    return $user;
}

/* ------------------------------------------------------------------
 * Text length that counts Urdu/Unicode letters correctly,
 * even on servers where the mbstring extension is switched off.
 * ------------------------------------------------------------------ */
function text_length(string $text): int
{
    return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : (int) preg_match_all('/./us', $text);
}

// In-app notifications (Part 5)
require_once __DIR__ . '/notify.php';
