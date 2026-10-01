<?php
// contact.php - Public contact form. Messages go to the admin Inbox.
// Spam protection: a hidden "website" field bots fill in, and at most 3 messages per hour from one IP.
require_once 'db.php';

const CONTACT_SUBJECTS = [
    'appointment' => 'Appointment or booking',
    'doctor'      => 'Joining as a doctor',
    'feedback'    => 'Feedback or suggestion',
    'complaint'   => 'Complaint',
    'other'       => 'Something else',
];
const CONTACT_LIMIT_PER_HOUR = 3;

$errors = [];
$v = ['name' => '', 'email' => '', 'phone' => '', 'subject' => '', 'message' => ''];

// Logged-in patients get their name and email filled in
if (isLoggedIn() && $_SESSION['role'] === 'patient') {
    $stmt = $pdo->prepare('SELECT name, email, phone FROM patients WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    if ($me = $stmt->fetch()) {
        $v = array_merge($v, $me);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($v as $k => $_) {
        $v[$k] = trim($_POST[$k] ?? '');
    }
    $v['phone'] = preg_replace('/[\s\-()]/', '', $v['phone']);

    if (!csrf_check()) {
        $errors['_'] = 'Your session expired. Reload the page and send again.';
    } elseif (trim($_POST['website'] ?? '') !== '') {
        // Honeypot filled in: almost certainly a bot. Pretend it worked and save nothing.
        flash('success', 'Thank you. Your message has been sent.');
        redirect('contact.php');
    } else {
        if (text_length($v['name']) < 3 || text_length($v['name']) > 100) $errors['name'] = 'Enter your name.';
        if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL) || strlen($v['email']) > 100) $errors['email'] = 'Enter a valid email address so we can reply.';
        if ($v['phone'] !== '' && !preg_match('/^\+?\d{10,13}$/', $v['phone'])) $errors['phone'] = 'Enter a valid phone number, or leave it empty.';
        if (!isset(CONTACT_SUBJECTS[$v['subject']])) $errors['subject'] = 'Choose what your message is about.';
        if (text_length($v['message']) < 15) $errors['message'] = 'Write a little more so we can help (at least 15 characters).';
        if (text_length($v['message']) > 2000) $errors['message'] = 'Keep your message under 2,000 characters.';

        if (!$errors) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM contact_messages WHERE ip = ? AND created_at > NOW() - INTERVAL 1 HOUR');
            $stmt->execute([client_ip()]);
            if ($stmt->fetchColumn() >= CONTACT_LIMIT_PER_HOUR) {
                $errors['_'] = 'You have sent several messages in the last hour. Please wait a while before sending another.';
            }
        }

        if (!$errors) {
            try {
                $stmt = $pdo->prepare('INSERT INTO contact_messages (name, email, phone, subject, message, ip) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$v['name'], $v['email'], $v['phone'] ?: null, $v['subject'], $v['message'], client_ip()]);
                notify_admins($pdo, 'message', 'New message: ' . CONTACT_SUBJECTS[$v['subject']], 'From ' . $v['name'] . ' (' . $v['email'] . ')', 'admin_inbox.php?open=' . (int) $pdo->lastInsertId());
                flash('success', 'Thank you, ' . explode(' ', $v['name'])[0] . '. We usually reply within one working day.');
                redirect('contact.php');
            } catch (PDOException $e) {
                error_log('Contact message failed: ' . $e->getMessage());
                $errors['_'] = 'Your message could not be sent. Try again in a minute.';
            }
        }
    }
}

function contact_error(array $errors, string $k): string
{
    return isset($errors[$k]) ? '<p class="field__hint">' . h($errors[$k]) . '</p>' : '';
}

$page_title = 'Contact us | CARE Group';
$nav_active = 'contact';
include 'includes/header.php';
?>

<section class="auth auth--wide">
    <div class="auth__story enter">
        <h1>We are here to help.</h1>
        <p class="auth__lede">Questions about a booking, feedback about a doctor, or a clinic that wants to join? Send us a message.</p>
        <ul class="auth__points">
            <li><i class="fa-solid fa-phone" aria-hidden="true"></i><span><strong>Helpline</strong> 021-111-CARE (2273), 9 AM to 9 PM</span></li>
            <li><i class="fa-solid fa-envelope" aria-hidden="true"></i><span><strong>Email</strong> support@caregroup.com</span></li>
            <li><i class="fa-solid fa-truck-medical" aria-hidden="true"></i><span><strong>Emergency</strong> Do not use this form. Call 1122.</span></li>
        </ul>
    </div>

    <div class="auth__card glass" data-tilt="3">
        <h2>Send a message</h2>
        <p class="auth__sub">Fields marked optional can be left empty.</p>

        <?php if ($errors): ?>
            <div class="alert" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                <p><?php echo h($errors['_'] ?? 'Fix the highlighted fields and send again.'); ?></p></div>
        <?php endif; ?>

        <form method="POST" action="contact.php" data-loading novalidate>
            <?php echo csrf_field(); ?>
            <!-- Honeypot: hidden from people, bots fill it in -->
            <div class="hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

            <div class="form-grid">
                <div class="field<?php echo isset($errors['name']) ? ' field--error' : ''; ?>">
                    <label class="field__label" for="name">Your name</label>
                    <div class="field__control"><i class="field__icon fa-solid fa-user" aria-hidden="true"></i>
                        <input class="input" id="name" name="name" value="<?php echo h($v['name']); ?>" autocomplete="name" required></div>
                    <?php echo contact_error($errors, 'name'); ?>
                </div>
                <div class="field<?php echo isset($errors['email']) ? ' field--error' : ''; ?>">
                    <label class="field__label" for="email">Email</label>
                    <div class="field__control"><i class="field__icon fa-solid fa-envelope" aria-hidden="true"></i>
                        <input class="input" type="email" id="email" name="email" value="<?php echo h($v['email']); ?>" autocomplete="email" required></div>
                    <?php echo contact_error($errors, 'email'); ?>
                </div>
                <div class="field<?php echo isset($errors['phone']) ? ' field--error' : ''; ?>">
                    <label class="field__label" for="phone">Phone (optional)</label>
                    <div class="field__control"><i class="field__icon fa-solid fa-phone" aria-hidden="true"></i>
                        <input class="input" type="tel" id="phone" name="phone" value="<?php echo h($v['phone']); ?>" autocomplete="tel"></div>
                    <?php echo contact_error($errors, 'phone'); ?>
                </div>
                <div class="field<?php echo isset($errors['subject']) ? ' field--error' : ''; ?>">
                    <label class="field__label" for="subject">About</label>
                    <select class="input input--plain" id="subject" name="subject" required>
                        <option value="">Choose a topic</option>
                        <?php foreach (CONTACT_SUBJECTS as $k => $label): ?>
                            <option value="<?php echo $k; ?>"<?php echo $v['subject'] === $k ? ' selected' : ''; ?>><?php echo h($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php echo contact_error($errors, 'subject'); ?>
                </div>
                <div class="field span-2<?php echo isset($errors['message']) ? ' field--error' : ''; ?>">
                    <label class="field__label" for="message">Message</label>
                    <textarea class="input input--plain" id="message" name="message" rows="5" maxlength="2000" required><?php echo h($v['message']); ?></textarea>
                    <?php echo contact_error($errors, 'message'); ?>
                </div>
                <div class="span-2">
                    <button type="submit" class="btn btn--glow btn--block"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Send message</button>
                </div>
            </div>
        </form>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
