<?php
// admin/login.php - Separate, restricted login for the administrator.
// This page is not linked from the public website.
define('APP_DEPTH', 1);          // this file lives one folder deep
require_once __DIR__ . '/../db.php';

if (isLoggedIn() && $_SESSION['role'] === 'admin') {
    redirect('admin_dashboard.php');
}

const ADMIN_MAX_ATTEMPTS = 5;    // stricter than the public login
const ADMIN_WINDOW_MIN   = 15;

$error    = '';
$username = '';
$locked   = login_locked_minutes($pdo, 'admin', ADMIN_MAX_ATTEMPTS, ADMIN_WINDOW_MIN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!csrf_check()) {
        $error = 'Your session expired. Reload the page and sign in again.';
    } elseif ($locked > 0) {
        $error = "Sign-in is locked after too many failed attempts. Try again in $locked minute" . ($locked > 1 ? 's' : '') . '.';
    } elseif ($username === '' || $password === '') {
        $error = 'Enter the admin username and password.';
    } else {
        try {
            $user = verify_credentials($pdo, $username, $password, ['admin']);
            if ($user) {
                login_clear_failures($pdo, 'admin');
                login_user($pdo, $user);
                flash('success', 'Signed in to the admin console.');
                redirect('admin_dashboard.php');
            }
            login_record_failure($pdo, 'admin', $username);
            $locked = login_locked_minutes($pdo, 'admin', ADMIN_MAX_ATTEMPTS, ADMIN_WINDOW_MIN);
            $left = max(0, ADMIN_MAX_ATTEMPTS - admin_fail_count($pdo));
            $error = $locked > 0
                ? "Sign-in is locked after too many failed attempts. Try again in $locked minute" . ($locked > 1 ? 's' : '') . '.'
                : 'Access denied. ' . $left . ' attempt' . ($left === 1 ? '' : 's') . ' left before sign-in is locked.';
        } catch (PDOException $e) {
            error_log('Admin login failed: ' . $e->getMessage());
            $error = 'Sign-in is unavailable right now. Try again in a minute.';
        }
    }
}

// Failed admin attempts from this IP inside the current window.
function admin_fail_count(PDO $pdo): int
{
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM login_attempts WHERE scope = 'admin' AND ip = ? AND attempted_at > (NOW() - INTERVAL ? MINUTE)"
        );
        $stmt->execute([client_ip(), ADMIN_WINDOW_MIN]);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

$page_title  = 'Admin console | CARE Group';
$body_class  = 'theme-admin';
$show_nav    = false;
$show_footer = false;
include __DIR__ . '/../includes/header.php';
?>

<section class="console">
    <div class="console__wrap enter">
        <a class="console__back" href="<?php echo h(url('index.php')); ?>">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to website
        </a>

        <div class="console__card glass" data-tilt="5">
            <div class="console__badge" data-depth aria-hidden="true"><i class="fa-solid fa-shield-halved"></i></div>
            <h1>Admin console</h1>
            <p class="auth__sub">Restricted to CARE Group administrators. Every failed attempt is recorded.</p>

            <?php if ($error !== ''): ?>
                <div class="alert<?php echo $locked > 0 ? ' alert--lock' : ''; ?>" role="alert">
                    <i class="fa-solid <?php echo $locked > 0 ? 'fa-lock' : 'fa-circle-exclamation'; ?>" aria-hidden="true"></i>
                    <p><?php echo h($error); ?></p>
                </div>
            <?php endif; ?>

            <form class="auth__form" method="POST" action="login.php" data-loading novalidate>
                <?php echo csrf_field(); ?>

                <div class="field">
                    <label class="field__label" for="username">Admin username</label>
                    <div class="field__control">
                        <i class="field__icon fa-solid fa-user-gear" aria-hidden="true"></i>
                        <input class="input" type="text" id="username" name="username" autocomplete="username"
                               value="<?php echo h($username); ?>" required autofocus>
                    </div>
                </div>

                <div class="field">
                    <label class="field__label" for="password">Password</label>
                    <div class="field__control">
                        <i class="field__icon fa-solid fa-fingerprint" aria-hidden="true"></i>
                        <input class="input input--has-reveal" type="password" id="password" name="password"
                               autocomplete="current-password" required>
                        <button type="button" class="field__reveal" data-reveal-password aria-controls="password"
                                aria-pressed="false" aria-label="Show password"><i class="fa-solid fa-eye"></i></button>
                    </div>
                </div>

                <button type="submit" class="btn btn--glow btn--block"<?php echo $locked > 0 ? ' disabled' : ''; ?>>
                    <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> Sign in
                </button>
            </form>

            <p class="console__status">
                <i class="fa-solid fa-circle-nodes" aria-hidden="true"></i>
                Locks for <?php echo ADMIN_WINDOW_MIN; ?> minutes after <?php echo ADMIN_MAX_ATTEMPTS; ?> failed attempts.
            </p>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
