<?php
// account.php - Account settings for every role: personal details (patients) and password change.
require_once 'db.php';

if (!isLoggedIn()) {
    redirect('login.php');
}
$role    = $_SESSION['role'];
$user_id = (int) $_SESSION['user_id'];

$errors = ['details' => [], 'password' => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!csrf_check()) {
        flash('error', 'Your session expired. Try again.');
        redirect('account.php');
    }

    // ---- Patient personal details ----
    if ($action === 'details' && $role === 'patient') {
        $name    = trim($_POST['name'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $phone   = preg_replace('/[\s\-()]/', '', $_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $e = &$errors['details'];

        if (text_length($name) < 3 || text_length($name) > 100 || !preg_match("/^[\p{L} .'-]+$/u", $name)) $e['name'] = 'Enter your full name using letters only.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) $e['email'] = 'Enter a valid email address.';
        if (!preg_match('/^(\+92|0092|0)3\d{9}$/', $phone)) $e['phone'] = 'Enter a Pakistani mobile number, like 0300-1234567.';
        if (text_length($address) < 5 || text_length($address) > 255) $e['address'] = 'Enter your address (5 to 255 characters).';
        if (!isset($e['email'])) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?');
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetchColumn()) $e['email'] = 'Another account already uses this email.';
        }

        if (!$e) {
            try {
                $pdo->beginTransaction();
                $pdo->prepare('UPDATE patients SET name = ?, email = ?, phone = ?, address = ? WHERE user_id = ?')->execute([$name, $email, $phone, $address, $user_id]);
                $pdo->prepare('UPDATE users SET email = ? WHERE id = ?')->execute([$email, $user_id]);
                $pdo->commit();
                $_SESSION['display_name'] = $name;
                $_SESSION['email'] = $email;
                flash('success', 'Your details are saved.');
                redirect('account.php');
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('Account details failed: ' . $ex->getMessage());
                $e['_'] = 'Saving failed. Try again in a minute.';
            }
        }
        unset($e);
    }

    // ---- Password change (all roles) ----
    if ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $e = &$errors['password'];

        $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->execute([$user_id]);
        $hash = (string) $stmt->fetchColumn();

        if (!password_verify($current, $hash)) $e['current_password'] = 'Your current password is incorrect.';
        if (strlen($new) < 8 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) $e['new_password'] = 'Use at least 8 characters with a letter and a number.';
        elseif ($new === $current) $e['new_password'] = 'Choose a password different from the current one.';
        if ($confirm !== $new) $e['confirm_password'] = 'The two passwords do not match.';

        if (!$e) {
            $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $user_id]);
            session_regenerate_id(true);
            flash('success', 'Password changed. Use the new one next time you log in.');
            redirect('account.php');
        }
        unset($e);
    }
}

// Current values for the details form
$me = null;
if ($role === 'patient') {
    $stmt = $pdo->prepare('SELECT name, email, phone, address FROM patients WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $me = $stmt->fetch();
    if ($errors['details']) {   // keep what the user typed
        $me = ['name' => $_POST['name'] ?? '', 'email' => $_POST['email'] ?? '', 'phone' => $_POST['phone'] ?? '', 'address' => $_POST['address'] ?? ''];
    }
}

function account_field(array $errors, string $name, string $label, string $value, string $type = 'text', string $auto = ''): string
{
    $err = $errors[$name] ?? '';
    return '<div class="field' . ($err ? ' field--error' : '') . '">'
        . '<label class="field__label" for="' . $name . '">' . h($label) . '</label>'
        . '<input class="input input--plain" type="' . $type . '" id="' . $name . '" name="' . $name . '" value="' . h($value) . '"'
        . ($auto ? ' autocomplete="' . $auto . '"' : '') . ' required>'
        . ($err ? '<p class="field__hint">' . h($err) . '</p>' : '')
        . '</div>';
}

$page_title  = 'Account | CARE Group';
$dash_title  = 'Account';
$dash_sub    = 'Signed in as ' . $_SESSION['username'];
$dash_active = 'account';
include 'includes/dash_header.php';
?>

<div class="dash-grid dash-grid--even">
    <?php if ($role === 'patient' && $me): ?>
        <form class="panel glass" method="POST" action="account.php" data-loading novalidate>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="details">
            <h2 class="panel__title">Personal details</h2>
            <?php if (!empty($errors['details']['_'])): ?>
                <div class="alert" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><p><?php echo h($errors['details']['_']); ?></p></div>
            <?php endif; ?>
            <div class="form-stack">
                <?php
                echo account_field($errors['details'], 'name', 'Full name', $me['name'], 'text', 'name');
                echo account_field($errors['details'], 'email', 'Email', $me['email'], 'email', 'email');
                echo account_field($errors['details'], 'phone', 'Mobile number', $me['phone'], 'tel', 'tel');
                echo account_field($errors['details'], 'address', 'Home address', $me['address'], 'text', 'street-address');
                ?>
            </div>
            <div class="panel__foot"><button type="submit" class="btn btn--glow">Save details</button></div>
        </form>
    <?php elseif ($role === 'doctor'): ?>
        <section class="panel glass">
            <h2 class="panel__title">Clinic details</h2>
            <p class="muted">Your name, specialty, fee, contact details and clinic hours are edited on the clinic profile page.</p>
            <a class="btn btn--ghost btn--sm" href="doctor_dashboard.php?view=clinic">Open clinic profile</a>
        </section>
    <?php endif; ?>

    <form class="panel glass" method="POST" action="account.php" data-loading novalidate>
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="password">
        <h2 class="panel__title">Change password</h2>
        <div class="form-stack">
            <?php
            echo account_field($errors['password'], 'current_password', 'Current password', '', 'password', 'current-password');
            echo account_field($errors['password'], 'new_password', 'New password', '', 'password', 'new-password');
            echo account_field($errors['password'], 'confirm_password', 'Confirm new password', '', 'password', 'new-password');
            ?>
        </div>
        <p class="field__hint">Use at least 8 characters with a letter and a number.</p>
        <div class="panel__foot"><button type="submit" class="btn btn--glow">Change password</button></div>
    </form>
</div>

<?php include 'includes/dash_footer.php'; ?>