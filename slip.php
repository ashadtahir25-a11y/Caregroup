<?php
// slip.php - Printable appointment slip with a QR code.
// For completed visits it also shows the doctor's prescription.
// Patients see their own slips, doctors see their patients' slips, the admin sees all.
require_once 'db.php';
require_once 'includes/booking.php';

if (!isLoggedIn()) {
    flash('error', 'Log in to view this appointment slip.');
    redirect('login.php');
}

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT a.*, p.name AS patient_name, p.phone AS patient_phone, p.email AS patient_email, p.user_id AS patient_user,
                              d.name AS doctor_name, d.specialty, d.address AS clinic_address, d.phone AS clinic_phone, d.user_id AS doctor_user,
                              c.name AS city_name
                         FROM appointments a
                         JOIN patients p ON p.id = a.patient_id
                         JOIN doctors d ON d.id = a.doctor_id
                         JOIN cities c ON c.id = d.city_id
                        WHERE a.id = ?');
$stmt->execute([$id]);
$a = $stmt->fetch();

$uid = (int) $_SESSION['user_id'];
$allowed = $a && ($_SESSION['role'] === 'admin'
    || ($_SESSION['role'] === 'patient' && (int) $a['patient_user'] === $uid)
    || ($_SESSION['role'] === 'doctor' && (int) $a['doctor_user'] === $uid));

if (!$allowed) {
    flash('error', 'That appointment slip is not available to your account.');
    redirect(dashboard_for($_SESSION['role']));
}

// Short check code printed on the slip and inside the QR code.
// Reception staff can compare it with the one shown when they scan.
$code = strtoupper(substr(hash('sha256', $a['id'] . '|' . $a['patient_id'] . '|' . $a['created_at']), 0, 8));
$sentCode = strtoupper(trim($_GET['code'] ?? ''));
$codeState = $sentCode === '' ? '' : (hash_equals($code, $sentCode) ? 'ok' : 'bad');

$stmt = $pdo->prepare('SELECT * FROM prescriptions WHERE appointment_id = ?');
$stmt->execute([$id]);
$rx = $stmt->fetch() ?: null;

// Absolute link for the QR code, e.g. http://localhost/php-project/slip.php?id=12&code=AB12CD34
$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$qrUrl   = $scheme . '://' . $_SERVER['HTTP_HOST'] . $baseDir . '/slip.php?id=' . $id . '&code=' . $code;

$ref = 'CG-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
$backUrl = dashboard_for($_SESSION['role']) . ($_SESSION['role'] === 'patient' ? '?view=history&tab=all' : ($_SESSION['role'] === 'doctor' ? '?view=appointments&tab=all' : '?view=appointments'));

$page_title  = ($rx ? 'Prescription ' : 'Appointment slip ') . $ref . ' | CARE Group';
$body_class  = 'page-slip';
$show_nav    = false;
$show_footer = false;
include 'includes/header.php';
?>

<div class="slip-tools">
    <a class="btn btn--ghost btn--sm" href="<?php echo h($backUrl); ?>"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back</a>
    <button type="button" class="btn btn--glow btn--sm" onclick="window.print()"><i class="fa-solid fa-print" aria-hidden="true"></i> Print or save as PDF</button>
</div>

<?php if ($codeState === 'ok'): ?>
    <p class="verify verify--ok"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Check code matches. This slip is genuine.</p>
<?php elseif ($codeState === 'bad'): ?>
    <p class="verify verify--bad"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> The check code does not match this appointment.</p>
<?php endif; ?>

<article class="paper enter">
    <header class="paper__head">
        <div class="paper__brand">
            <svg viewBox="0 0 40 40" aria-hidden="true"><rect x="1" y="1" width="38" height="38" rx="12"/><path d="M6 21 H13 L16 14 L20 28 L24 10 L27 21 H34"/></svg>
            <div><b>CARE Group</b><span>Medical Services</span></div>
        </div>
        <div class="paper__title">
            <h1><?php echo $rx ? 'Prescription' : 'Appointment slip'; ?></h1>
            <p><?php echo h($ref); ?> &middot; <?php echo status_badge($a['status']); ?></p>
        </div>
    </header>

    <div class="paper__grid">
        <section>
            <h2>Patient</h2>
            <p><b><?php echo h($a['patient_name']); ?></b><br><?php echo h($a['patient_phone']); ?><br><?php echo h($a['patient_email']); ?></p>
        </section>
        <section>
            <h2>Doctor</h2>
            <p><b><?php echo h(doctor_name($a['doctor_name'])); ?></b><br><?php echo h($a['specialty']); ?><br><?php echo h($a['clinic_address']); ?>, <?php echo h($a['city_name']); ?><br><?php echo h($a['clinic_phone']); ?></p>
        </section>
        <section class="paper__when">
            <h2>Visit</h2>
            <p class="paper__date"><?php echo h(date('l, j F Y', strtotime($a['appointment_date']))); ?></p>
            <p class="paper__time"><?php echo h($a['time_slot']); ?></p>
        </section>
        <section class="paper__qr">
            <div id="qr" data-qr="<?php echo h($qrUrl); ?>" aria-label="QR code for this appointment"></div>
            <p>Check code <b><?php echo h($code); ?></b></p>
        </section>
    </div>

    <?php if ($a['notes'] !== null && trim($a['notes']) !== ''): ?>
        <section class="paper__block">
            <h2>Reason for visit</h2>
            <p><?php echo nl2br(h($a['notes'])); ?></p>
        </section>
    <?php endif; ?>

    <?php if ($rx): ?>
        <section class="paper__block paper__rx">
            <h2><span class="rx-mark">&#8478;</span> Diagnosis</h2>
            <p><?php echo nl2br(h($rx['diagnosis'])); ?></p>

            <h2>Medicines</h2>
            <ol class="meds">
                <?php foreach (preg_split('/\r?\n/', trim($rx['medicines'])) as $line): if (trim($line) === '') continue; ?>
                    <li><?php echo h(trim($line)); ?></li>
                <?php endforeach; ?>
            </ol>

            <?php if (trim((string) $rx['advice']) !== ''): ?>
                <h2>Advice</h2>
                <p><?php echo nl2br(h($rx['advice'])); ?></p>
            <?php endif; ?>

            <?php if ($rx['follow_up']): ?>
                <p class="follow"><i class="fa-regular fa-calendar" aria-hidden="true"></i> Follow-up visit: <b><?php echo h(date('l, j F Y', strtotime($rx['follow_up']))); ?></b></p>
            <?php endif; ?>

            <div class="sign">
                <span><?php echo h(doctor_name($a['doctor_name'])); ?></span>
                <small>Written <?php echo h(date('j M Y', strtotime($rx['updated_at'] ?? $rx['created_at']))); ?></small>
            </div>
        </section>
    <?php elseif ($a['status'] !== 'Cancelled'): ?>
        <section class="paper__block paper__note">
            <p><b>Please arrive 10 minutes early</b> and show this slip at reception. Bring any earlier reports or medicines you take.</p>
        </section>
    <?php else: ?>
        <section class="paper__block paper__note paper__note--bad">
            <p><b>This appointment was cancelled.</b> Book a new time from your dashboard.</p>
        </section>
    <?php endif; ?>

    <footer class="paper__foot">Printed <?php echo date('j M Y, g:i A'); ?> &middot; CARE Group Medical Services &middot; Emergency: 1122</footer>
</article>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
/* Draw the QR code. If the QR library cannot load (no internet), the check code is still printed. */
(function () {
    var box = document.getElementById('qr');
    if (window.QRCode && box) {
        new QRCode(box, { text: box.dataset.qr, width: 132, height: 132, colorDark: '#0A1022', colorLight: '#ffffff', correctLevel: QRCode.CorrectLevel.M });
    } else if (box) {
        box.classList.add('qr--offline');
        box.textContent = 'QR code needs internet';
    }
})();
</script>

<?php include 'includes/footer.php'; ?>
