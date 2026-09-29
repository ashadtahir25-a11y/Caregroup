<?php
// register.php - Patient sign-up. Doctor accounts are created by the admin.
require_once 'db.php';

if (isLoggedIn()) {
    redirect(dashboard_for($_SESSION['role']));
}

$errors = [];          // field name => message
$form_error = '';      // message not tied to one field
$v = ['username' => '', 'name' => '', 'email' => '', 'phone' => '', 'address' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($v as $key => $_) {
        $v[$key] = trim($_POST[$key] ?? '');
    }
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';
    $phoneDigits = preg_replace('/[\s\-()]/', '', $v['phone']);

    if (!csrf_check()) {
        $form_error = 'Your session expired. Reload the page and submit the form again.';
    } else {
        // ---- Server-side validation (never trust the browser alone) ----
        if (!preg_match('/^[A-Za-z0-9_]{4,30}$/', $v['username'])) {
            $errors['username'] = 'Use 4 to 30 letters, numbers or underscores.';
        }
        if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            $errors['password'] = 'Use at least 8 characters with a letter and a number.';
        }
        if ($confirm !== $password) {
            $errors['password_confirm'] = 'The two passwords do not match.';
        }
        if (text_length($v['name']) < 3 || text_length($v['name']) > 100 || !preg_match("/^[\p{L} .'-]+$/u", $v['name'])) {
            $errors['name'] = 'Enter your full name using letters only.';
        }
        if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL) || strlen($v['email']) > 100) {
            $errors['email'] = 'Enter a valid email address, like name@gmail.com.';
        }
        if (!preg_match('/^(\+92|0092|0)3\d{9}$/', $phoneDigits)) {
            $errors['phone'] = 'Enter a Pakistani mobile number, like 0300-1234567.';
        }
        if (text_length($v['address']) < 5 || text_length($v['address']) > 255) {
            $errors['address'] = 'Enter your address (5 to 255 characters).';
        }

        // ---- Uniqueness checks ----
        if (!$errors) {
            try {
                $stmt = $pdo->prepare('SELECT username, email FROM users WHERE username = ? OR email = ?');
                $stmt->execute([$v['username'], $v['email']]);
                foreach ($stmt->fetchAll() as $row) {
                    if (strcasecmp($row['username'], $v['username']) === 0) {
                        $errors['username'] = 'This username is taken. Try another one.';
                    }
                    if (strcasecmp($row['email'], $v['email']) === 0) {
                        $errors['email'] = 'An account with this email already exists. Log in instead.';
                    }
                }
            } catch (PDOException $e) {
                error_log('Register lookup failed: ' . $e->getMessage());
                $form_error = 'Sign-up is unavailable right now. Try again in a minute.';
            }
        }

        // ---- Create the account (both rows or neither) ----
        if (!$errors && $form_error === '') {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("INSERT INTO users (username, password, role, email) VALUES (?, ?, 'patient', ?)");
                $stmt->execute([$v['username'], password_hash($password, PASSWORD_DEFAULT), $v['email']]);
                $userId = (int) $pdo->lastInsertId();

                $stmt = $pdo->prepare('INSERT INTO patients (user_id, name, address, phone, email) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$userId, $v['name'], $v['address'], $phoneDigits, $v['email']]);

                $pdo->commit();

                flash('success', 'Account created. Log in to book your first appointment.');
                redirect('login.php');   // Post/Redirect/Get: refreshing will not resubmit the form
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Register insert failed: ' . $e->getMessage());
                $form_error = $e->getCode() === '23000'
                    ? 'This username or email was just registered. Choose another and try again.'
                    : 'Sign-up is unavailable right now. Try again in a minute.';
            }
        }
    }
}

// Small helper to print one form field with its error state.
function field_attrs(array $errors, string $name): string
{
    return isset($errors[$name]) ? ' field--error' : '';
}
function field_hint(array $errors, string $name, string $default = ''): string
{
    $text = $errors[$name] ?? $default;
    return $text === '' ? '' : '<p class="field__hint" id="' . $name . '-hint">' . h($text) . '</p>';
}
function aria_hint(array $errors, string $name, bool $hasDefault = false): string
{
    $attr = ($hasDefault || isset($errors[$name])) ? ' aria-describedby="' . $name . '-hint"' : '';
    return $attr . (isset($errors[$name]) ? ' aria-invalid="true"' : '');
}

$page_title = 'Create account | CARE Group';
$body_class = 'page-register';
include 'includes/header.php';
?>

<section class="auth auth--wide">
    <div class="auth__story enter">
        <h1>Create your patient account.</h1>
        <p class="auth__lede">One account lets you find doctors by city and specialty, request a time that suits you and see when it is confirmed.</p>
        <ul class="auth__points">
            <li><i class="fa-solid fa-magnifying-glass-location" aria-hidden="true"></i><span>Search specialists in your city.</span></li>
            <li><i class="fa-solid fa-calendar-check" aria-hidden="true"></i><span>Pick a day and time slot the doctor is available.</span></li>
            <li><i class="fa-solid fa-bell" aria-hidden="true"></i><span>Follow each request from pending to confirmed.</span></li>
        </ul>
    </div>

    <div class="auth__card glass" data-tilt="3">
        <h2>Sign up</h2>
        <p class="auth__sub">All fields are required.</p>

        <?php if ($form_error !== '' || $errors): ?>
            <div class="alert" role="alert">
                <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                <p><?php echo h($form_error !== '' ? $form_error : 'Fix the highlighted fields and submit again.'); ?></p>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php" data-loading novalidate>
            <?php echo csrf_field(); ?>
            <div class="form-grid">

                <div class="field span-2<?php echo field_attrs($errors, 'name'); ?>">
                    <label class="field__label" for="name">Full name</label>
                    <div class="field__control">
                        <i class="field__icon fa-solid fa-id-card" aria-hidden="true"></i>
                        <input class="input" type="text" id="name" name="name" autocomplete="name" maxlength="100"
                               value="<?php echo h($v['name']); ?>" placeholder="Muhammad Jameel" required<?php echo aria_hint($errors, 'name'); ?>>
                    </div>
                    <?php echo field_hint($errors, 'name'); ?>
                </div>

                <div class="field<?php echo field_attrs($errors, 'username'); ?>">
                    <label class="field__label" for="username">Username</label>
                    <div class="field__control">
                        <i class="field__icon fa-solid fa-at" aria-hidden="true"></i>
                        <input class="input" type="text" id="username" name="username" autocomplete="username" maxlength="30"
                               value="<?php echo h($v['username']); ?>" placeholder="jameel99" required<?php echo aria_hint($errors, 'username'); ?>>
                    </div>
                    <?php echo field_hint($errors, 'username'); ?>
                </div>

                <div class="field<?php echo field_attrs($errors, 'email'); ?>">
                    <label class="field__label" for="email">Email</label>
                    <div class="field__control">
                        <i class="field__icon fa-solid fa-envelope" aria-hidden="true"></i>
                        <input class="input" type="email" id="email" name="email" autocomplete="email" maxlength="100"
                               value="<?php echo h($v['email']); ?>" placeholder="jameel@gmail.com" required<?php echo aria_hint($errors, 'email'); ?>>
                    </div>
                    <?php echo field_hint($errors, 'email'); ?>
                </div>

                <div class="field<?php echo field_attrs($errors, 'password'); ?>">
                    <label class="field__label" for="password">Password</label>
                    <div class="field__control">
                        <i class="field__icon fa-solid fa-key" aria-hidden="true"></i>
                        <input class="input input--has-reveal" type="password" id="password" name="password"
                               autocomplete="new-password" required<?php echo aria_hint($errors, 'password', true); ?>>
                        <button type="button" class="field__reveal" data-reveal-password aria-controls="password"
                                aria-pressed="false" aria-label="Show password"><i class="fa-solid fa-eye"></i></button>
                    </div>
                    <div class="strength" data-strength-for="password" data-strength-label="password-hint" data-level="0" aria-hidden="true">
                        <span></span><span></span><span></span><span></span>
                    </div>
                    <?php echo field_hint($errors, 'password', 'Use at least 8 characters with a letter and a number.'); ?>
                </div>

                <div class="field<?php echo field_attrs($errors, 'password_confirm'); ?>">
                    <label class="field__label" for="password_confirm">Confirm password</label>
                    <div class="field__control">
                        <i class="field__icon fa-solid fa-lock" aria-hidden="true"></i>
                        <input class="input" type="password" id="password_confirm" name="password_confirm"
                               autocomplete="new-password" required<?php echo aria_hint($errors, 'password_confirm'); ?>>
                    </div>
                    <?php echo field_hint($errors, 'password_confirm'); ?>
                </div>

                <div class="field span-2<?php echo field_attrs($errors, 'phone'); ?>">
                    <label class="field__label" for="phone">Mobile number</label>
                    <div class="field__control">
                        <i class="field__icon fa-solid fa-phone" aria-hidden="true"></i>
                        <input class="input" type="tel" id="phone" name="phone" autocomplete="tel" maxlength="16"
                               value="<?php echo h($v['phone']); ?>" placeholder="0300-1234567" required<?php echo aria_hint($errors, 'phone'); ?>>
                    </div>
                    <?php echo field_hint($errors, 'phone'); ?>
                </div>

                <div class="field span-2<?php echo field_attrs($errors, 'address'); ?>">
                    <label class="field__label" for="address">Home address</label>
                    <div class="field__control field__control--area">
                        <i class="field__icon fa-solid fa-location-dot" aria-hidden="true"></i>
                        <textarea class="input" id="address" name="address" rows="2" maxlength="255" autocomplete="street-address"
                                  placeholder="House 12, Block 4, Gulshan-e-Iqbal, Karachi" required<?php echo aria_hint($errors, 'address'); ?>><?php echo h($v['address']); ?></textarea>
                    </div>
                    <?php echo field_hint($errors, 'address'); ?>
                </div>

                <div class="span-2">
                    <button type="submit" class="btn btn--glow btn--block">Create account</button>
                </div>
            </div>
        </form>

        <p class="auth__switch">Already registered? <a href="login.php">Log in</a></p>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
