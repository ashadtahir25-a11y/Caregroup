<?php
// login.php - Login for patients and doctors.
// Admins use the separate page at admin/login.php.
require_once 'db.php';

if (isLoggedIn()) {
    redirect(dashboard_for($_SESSION['role']));
}

const PUBLIC_MAX_ATTEMPTS = 8;    // failed tries allowed...
const PUBLIC_WINDOW_MIN   = 15;   // ...within this many minutes

// "Book appointment" buttons on the homepage send ?redirect=book&doc=ID
$bookDoctor = 0;
if (($_REQUEST['redirect'] ?? '') === 'book') {
    $bookDoctor = max(0, (int) ($_REQUEST['doc'] ?? 0));
}

$error    = '';
$username = '';
$locked   = login_locked_minutes($pdo, 'public', PUBLIC_MAX_ATTEMPTS, PUBLIC_WINDOW_MIN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!csrf_check()) {
        $error = 'Your session expired. Reload the page and log in again.';
    } elseif ($locked > 0) {
        $error = "Too many failed attempts. Try again in $locked minute" . ($locked > 1 ? 's' : '') . '.';
    } elseif ($username === '' || $password === '') {
        $error = 'Enter your username and password.';
    } else {
        try {
            $user = verify_credentials($pdo, $username, $password, ['patient', 'doctor']);
            if ($user) {
                login_clear_failures($pdo, 'public');
                login_user($pdo, $user);
                flash('success', 'Welcome back, ' . $user['username'] . '.');

                if ($user['role'] === 'patient' && $bookDoctor > 0) {
                    redirect('patient_dashboard.php?book_doc_id=' . $bookDoctor);
                }
                redirect(dashboard_for($user['role']));
            }
            login_record_failure($pdo, 'public', $username);
            $error = 'The username or password is incorrect.';
            $locked = login_locked_minutes($pdo, 'public', PUBLIC_MAX_ATTEMPTS, PUBLIC_WINDOW_MIN);
        } catch (PDOException $e) {
            error_log('Login failed: ' . $e->getMessage());
            $error = 'Login is unavailable right now. Try again in a minute.';
        }
    }
}

// Real numbers for the monitor panel
$netStats = ['doctors' => 0, 'cities' => 0, 'specialties' => 0];
try {
    $netStats['doctors']     = (int) $pdo->query('SELECT COUNT(*) FROM doctors')->fetchColumn();
    $netStats['cities']      = (int) $pdo->query('SELECT COUNT(*) FROM cities')->fetchColumn();
    $netStats['specialties'] = (int) $pdo->query('SELECT COUNT(DISTINCT specialty) FROM doctors')->fetchColumn();
} catch (PDOException $e) {
    error_log('Login stats failed: ' . $e->getMessage());
}

$page_title = 'Log in | CARE Group';
$body_class = 'page-login';
include 'includes/header.php';
?>

<section class="auth">
    <div class="auth__story enter">
        <h1>Your next appointment is a few taps away.</h1>
        <p class="auth__lede">Log in to book specialists, follow your visit status and keep your care history in one place.</p>

        <ul class="auth__points">
            <li><i class="fa-solid fa-user-injured" aria-hidden="true"></i><span><strong>Patients</strong> book, track and cancel appointments.</span></li>
            <li><i class="fa-solid fa-user-doctor" aria-hidden="true"></i><span><strong>Doctors</strong> confirm visits and set weekly clinic hours.</span></li>
        </ul>

        <div class="monitor glass" aria-hidden="true">
            <div class="monitor__head">
                <span>Clinic network</span>
                <span class="monitor__live">Live</span>
            </div>
            <div class="monitor__trace">
                <svg viewBox="0 0 800 90" preserveAspectRatio="none">
                    <path d="M0 50 H80 L92 50 L100 38 L108 62 L118 8 L130 84 L140 50 L160 50 L168 42 L178 50 H280 L292 50 L300 38 L308 62 L318 8 L330 84 L340 50 L360 50 L368 42 L378 50 H400
                             H480 L492 50 L500 38 L508 62 L518 8 L530 84 L540 50 L560 50 L568 42 L578 50 H680 L692 50 L700 38 L708 62 L718 8 L730 84 L740 50 L760 50 L768 42 L778 50 H800"/>
                </svg>
            </div>
            <div class="monitor__stats">
                <div class="monitor__stat"><b data-count="<?php echo $netStats['doctors']; ?>"><?php echo $netStats['doctors']; ?></b><span>Doctors</span></div>
                <div class="monitor__stat"><b data-count="<?php echo $netStats['specialties']; ?>"><?php echo $netStats['specialties']; ?></b><span>Specialties</span></div>
                <div class="monitor__stat"><b data-count="<?php echo $netStats['cities']; ?>"><?php echo $netStats['cities']; ?></b><span>Cities</span></div>
            </div>
        </div>
    </div>

    <div class="auth__card glass" data-tilt="4">
        <h2>Log in</h2>
        <p class="auth__sub">For patients and doctors.</p>

        <?php if ($error !== ''): ?>
            <div class="alert<?php echo $locked > 0 ? ' alert--lock' : ''; ?>" role="alert">
                <i class="fa-solid <?php echo $locked > 0 ? 'fa-lock' : 'fa-circle-exclamation'; ?>" aria-hidden="true"></i>
                <p><?php echo h($error); ?></p>
            </div>
        <?php endif; ?>

        <form class="auth__form" method="POST" action="login.php" data-loading novalidate>
            <?php echo csrf_field(); ?>
            <?php if ($bookDoctor > 0): ?>
                <input type="hidden" name="redirect" value="book">
                <input type="hidden" name="doc" value="<?php echo $bookDoctor; ?>">
            <?php endif; ?>

            <div class="field">
                <label class="field__label" for="username">Username</label>
                <div class="field__control">
                    <i class="field__icon fa-solid fa-user" aria-hidden="true"></i>
                    <input class="input" type="text" id="username" name="username" autocomplete="username"
                           value="<?php echo h($username); ?>" required autofocus>
                </div>
            </div>

            <div class="field">
                <label class="field__label" for="password">Password</label>
                <div class="field__control">
                    <i class="field__icon fa-solid fa-key" aria-hidden="true"></i>
                    <input class="input input--has-reveal" type="password" id="password" name="password"
                           autocomplete="current-password" required>
                    <button type="button" class="field__reveal" data-reveal-password aria-controls="password"
                            aria-pressed="false" aria-label="Show password"><i class="fa-solid fa-eye"></i></button>
                </div>
            </div>

            <button type="submit" class="btn btn--glow btn--block"<?php echo $locked > 0 ? ' disabled' : ''; ?>>
                Log in
            </button>
        </form>

        <p class="auth__switch">New patient? <a href="register.php">Create an account</a></p>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
