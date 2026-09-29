<?php
// doctor_dashboard.php - Doctor area: today's schedule, appointment requests and clinic profile.
require_once 'db.php';
require_once 'includes/booking.php';
checkRole('doctor');

$doctor_id = $_SESSION['doctor_id'] ?? null;
if (!$doctor_id) {
    $stmt = $pdo->prepare('SELECT id FROM doctors WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $doctor_id = $stmt->fetchColumn();
    if (!$doctor_id) {
        flash('error', 'Your doctor profile has not been set up yet. Contact the CARE Group admin.');
        redirect('logout.php');
    }
    $_SESSION['doctor_id'] = (int) $doctor_id;
}
$doctor_id = (int) $doctor_id;

$view = $_GET['view'] ?? 'home';
if (!in_array($view, ['home', 'appointments', 'clinic'], true)) {
    $view = 'home';
}

/* =====================================================================
 * POST actions
 * ===================================================================== */
$form_errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!csrf_check()) {
        flash('error', 'Your session expired. Try again.');
        redirect('doctor_dashboard.php');
    }

    // ---- Change an appointment's status ----
    if ($action === 'status') {
        $id = (int) ($_POST['appointment_id'] ?? 0);
        $to = $_POST['to'] ?? '';
        $return = $_POST['return'] ?? 'doctor_dashboard.php';
        if (!preg_match('/^doctor_dashboard\.php(\?[a-z_=&0-9]*)?$/', $return)) {
            $return = 'doctor_dashboard.php';   // only allow returning to this page
        }

        $stmt = $pdo->prepare('SELECT status, appointment_date FROM appointments WHERE id = ? AND doctor_id = ?');
        $stmt->execute([$id, $doctor_id]);
        $app = $stmt->fetch();

        if (!$app || !can_change_status('doctor', $app['status'], $to)) {
            flash('error', 'That change is not allowed for this appointment.');
        } elseif ($to === 'Completed' && $app['appointment_date'] > date('Y-m-d')) {
            flash('error', 'You can mark a visit as completed on or after its date.');
        } else {
            $pdo->prepare('UPDATE appointments SET status = ? WHERE id = ? AND doctor_id = ?')->execute([$to, $id, $doctor_id]);
            $done = ['Confirmed' => 'Appointment confirmed.', 'Completed' => 'Visit marked as completed.', 'Cancelled' => 'Appointment cancelled.'];
            flash('success', $done[$to]);
        }
        redirect($return);
    }

    // ---- Update clinic profile ----
    if ($action === 'profile') {
        $p = [
            'name'      => trim($_POST['name'] ?? ''),
            'specialty' => trim($_POST['specialty'] ?? ''),
            'city_id'   => (int) ($_POST['city_id'] ?? 0),
            'phone'     => preg_replace('/[\s\-()]/', '', $_POST['phone'] ?? ''),
            'email'     => trim($_POST['email'] ?? ''),
            'address'   => trim($_POST['address'] ?? ''),
            'bio'       => trim($_POST['bio'] ?? ''),
            'exp'       => (int) ($_POST['experience_years'] ?? -1),
            'fee'       => (float) ($_POST['consultation_fee'] ?? -1),
        ];
        $days  = array_values(array_intersect(WEEK_DAYS, (array) ($_POST['days'] ?? [])));
        $slots = sort_slots(array_values(array_intersect(SLOT_OPTIONS, (array) ($_POST['slots'] ?? []))));

        if (text_length($p['name']) < 3 || text_length($p['name']) > 100) $form_errors['name'] = 'Enter your full name.';
        if (text_length($p['specialty']) < 3 || text_length($p['specialty']) > 100) $form_errors['specialty'] = 'Enter your specialty, like Cardiologist.';
        if (!preg_match('/^\+?\d{10,13}$/', $p['phone'])) $form_errors['phone'] = 'Enter a valid phone number.';
        if (!filter_var($p['email'], FILTER_VALIDATE_EMAIL)) $form_errors['email'] = 'Enter a valid email address.';
        if (text_length($p['address']) < 5) $form_errors['address'] = 'Enter the clinic address.';
        if ($p['exp'] < 0 || $p['exp'] > 60) $form_errors['experience_years'] = 'Enter years between 0 and 60.';
        if ($p['fee'] < 0 || $p['fee'] > 100000) $form_errors['consultation_fee'] = 'Enter a fee between 0 and 100,000.';
        if (!$days) $form_errors['days'] = 'Choose at least one clinic day.';
        if (!$slots) $form_errors['slots'] = 'Choose at least one time slot.';

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM cities WHERE id = ?');
        $stmt->execute([$p['city_id']]);
        if (!$stmt->fetchColumn()) $form_errors['city_id'] = 'Choose a city.';

        if (!isset($form_errors['email'])) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?');
            $stmt->execute([$p['email'], $_SESSION['user_id']]);
            if ($stmt->fetchColumn()) $form_errors['email'] = 'Another account already uses this email.';
        }

        if (!$form_errors) {
            try {
                $pdo->beginTransaction();
                $pdo->prepare('UPDATE doctors SET name=?, specialty=?, city_id=?, phone=?, email=?, address=?, bio=?, experience_years=?, consultation_fee=?, available_days=?, available_slots=? WHERE id=?')
                    ->execute([$p['name'], $p['specialty'], $p['city_id'], $p['phone'], $p['email'], $p['address'], $p['bio'], $p['exp'], $p['fee'], implode(',', $days), implode(',', $slots), $doctor_id]);
                // Keep the login email in step with the profile email
                $pdo->prepare('UPDATE users SET email = ? WHERE id = ?')->execute([$p['email'], $_SESSION['user_id']]);
                $pdo->commit();

                $_SESSION['display_name'] = doctor_name($p['name']);
                $_SESSION['email'] = $p['email'];
                flash('success', 'Clinic profile saved.');
                redirect('doctor_dashboard.php?view=clinic');
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('Doctor profile update failed: ' . $e->getMessage());
                $form_errors['_'] = 'Saving failed. Try again in a minute.';
            }
        }
        $view = 'clinic';   // show the form again with the errors
    }
}

/* =====================================================================
 * Data
 * ===================================================================== */
$stmt = $pdo->prepare('SELECT d.*, c.name AS city_name FROM doctors d JOIN cities c ON c.id = d.city_id WHERE d.id = ?');
$stmt->execute([$doctor_id]);
$me = $stmt->fetch();

$appSql = 'SELECT a.*, p.name AS patient_name, p.phone AS patient_phone, p.email AS patient_email
             FROM appointments a JOIN patients p ON p.id = a.patient_id
            WHERE a.doctor_id = ?';

$stmt = $pdo->prepare('SELECT status, COUNT(*) AS n FROM appointments WHERE doctor_id = ? GROUP BY status');
$stmt->execute([$doctor_id]);
$counts = array_column($stmt->fetchAll(), 'n', 'status');
$total  = array_sum($counts);

// One appointment row with the actions this doctor is allowed to take
function doctor_row(array $a, string $return): string
{
    $actions = [];
    if (can_change_status('doctor', $a['status'], 'Confirmed')) $actions[] = ['Confirmed', 'Confirm', 'btn--glow', ''];
    if (can_change_status('doctor', $a['status'], 'Completed') && $a['appointment_date'] <= date('Y-m-d')) $actions[] = ['Completed', 'Mark completed', 'btn--glow', ''];
    if (can_change_status('doctor', $a['status'], 'Cancelled')) $actions[] = ['Cancelled', $a['status'] === 'Pending' ? 'Decline' : 'Cancel', 'btn--ghost', 'Cancel this appointment? The patient will see it as cancelled.'];

    ob_start(); ?>
    <article class="row glass" data-reveal>
        <div class="row__date">
            <b><?php echo h($a['time_slot'] ? substr($a['time_slot'], 0, 5) : ''); ?></b>
            <span><?php echo h(substr($a['time_slot'], -2)); ?></span>
        </div>
        <div class="row__main">
            <h3><?php echo h($a['patient_name']); ?></h3>
            <p><i class="fa-regular fa-calendar" aria-hidden="true"></i> <?php echo h(friendly_date($a['appointment_date'])); ?>
               &nbsp; <i class="fa-solid fa-phone" aria-hidden="true"></i> <?php echo h($a['patient_phone']); ?>
               &nbsp; <i class="fa-solid fa-envelope" aria-hidden="true"></i> <?php echo h($a['patient_email']); ?></p>
            <?php if ($a['notes'] !== null && $a['notes'] !== ''): ?><p class="row__note">Patient's note: <?php echo h($a['notes']); ?></p><?php endif; ?>
        </div>
        <div class="row__side">
            <?php echo status_badge($a['status']); ?>
            <?php if ($actions): ?>
                <div class="row__actions">
                    <?php foreach ($actions as [$to, $label, $cls, $confirm]): ?>
                        <form method="POST" action="doctor_dashboard.php"<?php echo $confirm ? ' data-confirm="' . h($confirm) . '"' : ''; ?>>
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="status">
                            <input type="hidden" name="appointment_id" value="<?php echo (int) $a['id']; ?>">
                            <input type="hidden" name="to" value="<?php echo $to; ?>">
                            <input type="hidden" name="return" value="<?php echo h($return); ?>">
                            <button type="submit" class="btn <?php echo $cls; ?> btn--xs"><?php echo $label; ?></button>
                        </form>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </article>
    <?php
    return ob_get_clean();
}

if ($view === 'home') {
    $stmt = $pdo->prepare($appSql . " AND a.appointment_date = CURDATE() AND a.status IN ('Pending','Confirmed','Completed') ORDER BY " . SLOT_ORDER_SQL);
    $stmt->execute([$doctor_id]);
    $today = $stmt->fetchAll();

    $stmt = $pdo->prepare($appSql . " AND a.status = 'Pending' AND a.appointment_date >= CURDATE() ORDER BY a.appointment_date, " . SLOT_ORDER_SQL . ' LIMIT 8');
    $stmt->execute([$doctor_id]);
    $pending = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND status IN ('Pending','Confirmed') AND appointment_date BETWEEN CURDATE() AND CURDATE() + INTERVAL 6 DAY");
    $stmt->execute([$doctor_id]);
    $week = (int) $stmt->fetchColumn();

    // Donut chart segments (conic-gradient percentages)
    $donut = [];
    $colors = ['Pending' => 'var(--amber)', 'Confirmed' => 'var(--pulse-2)', 'Completed' => 'var(--pulse)', 'Cancelled' => 'var(--vital)'];
    $start = 0;
    foreach ($colors as $st => $col) {
        $pct = $total ? ($counts[$st] ?? 0) / $total * 100 : 0;
        $donut[] = "$col " . round($start, 2) . '% ' . round($start + $pct, 2) . '%';
        $start += $pct;
    }
}

$tab = $_GET['tab'] ?? 'pending';
$list = [];
if ($view === 'appointments') {
    $where = [
        'pending'   => " AND a.status = 'Pending' ORDER BY a.appointment_date, " . SLOT_ORDER_SQL,
        'upcoming'  => " AND a.status = 'Confirmed' AND a.appointment_date >= CURDATE() ORDER BY a.appointment_date, " . SLOT_ORDER_SQL,
        'completed' => " AND a.status = 'Completed' ORDER BY a.appointment_date DESC",
        'cancelled' => " AND a.status = 'Cancelled' ORDER BY a.appointment_date DESC",
        'all'       => ' ORDER BY a.appointment_date DESC, ' . SLOT_ORDER_SQL,
    ];
    if (!isset($where[$tab])) $tab = 'pending';
    $stmt = $pdo->prepare($appSql . $where[$tab]);
    $stmt->execute([$doctor_id]);
    $list = $stmt->fetchAll();
}

if ($view === 'clinic') {
    $cities = $pdo->query('SELECT id, name FROM cities ORDER BY name')->fetchAll();
    // After a failed save, show what the doctor typed; otherwise the saved values
    $f = $form_errors ? [
        'name' => $p['name'], 'specialty' => $p['specialty'], 'city_id' => $p['city_id'], 'phone' => $_POST['phone'] ?? '',
        'email' => $p['email'], 'address' => $p['address'], 'bio' => $p['bio'],
        'experience_years' => $_POST['experience_years'] ?? '', 'consultation_fee' => $_POST['consultation_fee'] ?? '',
        'days' => $days, 'slots' => $slots,
    ] : [
        'name' => $me['name'], 'specialty' => $me['specialty'], 'city_id' => (int) $me['city_id'], 'phone' => $me['phone'],
        'email' => $me['email'], 'address' => $me['address'], 'bio' => $me['bio'],
        'experience_years' => $me['experience_years'], 'consultation_fee' => (float) $me['consultation_fee'],
        'days' => csv_list($me['available_days']), 'slots' => csv_list($me['available_slots']),
    ];
}

function err(array $errors, string $key): string
{
    return isset($errors[$key]) ? '<p class="field__hint">' . h($errors[$key]) . '</p>' : '';
}

$titles = ['home' => 'Today', 'appointments' => 'Appointments', 'clinic' => 'Clinic profile'];
$page_title  = $titles[$view] . ' | CARE Group';
$dash_title  = $view === 'home' ? 'Good ' . (date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening')) . ', ' . doctor_name($me['name']) : $titles[$view];
$dash_sub    = $view === 'home' ? $me['specialty'] . ', ' . $me['city_name'] : '';
$dash_active = $view;
include 'includes/dash_header.php';
?>

<?php if ($view === 'home'): ?>
    <div class="kpis kpis--4">
        <div class="kpi glass"><span>Today</span><b data-count="<?php echo count($today); ?>"><?php echo count($today); ?></b></div>
        <div class="kpi glass"><span>Requests to review</span><b data-count="<?php echo (int) ($counts['Pending'] ?? 0); ?>"><?php echo (int) ($counts['Pending'] ?? 0); ?></b></div>
        <div class="kpi glass"><span>Next 7 days</span><b data-count="<?php echo $week; ?>"><?php echo $week; ?></b></div>
        <div class="kpi glass"><span>Completed visits</span><b data-count="<?php echo (int) ($counts['Completed'] ?? 0); ?>"><?php echo (int) ($counts['Completed'] ?? 0); ?></b></div>
    </div>

    <div class="dash-grid">
        <div>
            <div class="panel-head"><h2>Today's schedule</h2></div>
            <div class="rows">
                <?php foreach ($today as $a) { echo doctor_row($a, 'doctor_dashboard.php'); } ?>
                <?php if (!$today): ?><div class="empty glass"><i class="fa-solid fa-mug-hot" aria-hidden="true"></i><p>No patients booked for today.</p></div><?php endif; ?>
            </div>

            <div class="panel-head">
                <h2>New requests</h2>
                <a class="link-more" href="doctor_dashboard.php?view=appointments&amp;tab=pending">See all</a>
            </div>
            <div class="rows">
                <?php foreach ($pending as $a) { echo doctor_row($a, 'doctor_dashboard.php'); } ?>
                <?php if (!$pending): ?><div class="empty glass"><i class="fa-solid fa-inbox" aria-hidden="true"></i><p>No requests waiting for you.</p></div><?php endif; ?>
            </div>
        </div>

        <aside class="chart-card glass" data-reveal>
            <h2>All appointments</h2>
            <div class="donut" style="--donut: conic-gradient(<?php echo $total ? implode(', ', $donut) : 'var(--ink-700) 0 100%'; ?>)">
                <div class="donut__hole"><b data-count="<?php echo $total; ?>"><?php echo $total; ?></b><span>total</span></div>
            </div>
            <ul class="legend">
                <?php foreach ($colors as $st => $col): ?>
                    <li><i style="background: <?php echo $col; ?>"></i><?php echo $st; ?><b><?php echo (int) ($counts[$st] ?? 0); ?></b></li>
                <?php endforeach; ?>
            </ul>
        </aside>
    </div>

<?php elseif ($view === 'appointments'): ?>
    <nav class="tabs" aria-label="Filter appointments">
        <?php foreach (['pending' => 'Requests', 'upcoming' => 'Confirmed', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'all' => 'All'] as $k => $label): ?>
            <a href="doctor_dashboard.php?view=appointments&amp;tab=<?php echo $k; ?>"<?php echo $tab === $k ? ' aria-current="page"' : ''; ?>>
                <?php echo $label; ?><?php if ($k === 'pending' && !empty($counts['Pending'])): ?> <span class="count"><?php echo (int) $counts['Pending']; ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="rows">
        <?php foreach ($list as $a) { echo doctor_row($a, 'doctor_dashboard.php?view=appointments&tab=' . $tab); } ?>
        <?php if (!$list): ?><div class="empty glass"><i class="fa-regular fa-calendar" aria-hidden="true"></i><p>Nothing in this list.</p></div><?php endif; ?>
    </div>

<?php else: ?>
    <form class="panel glass" method="POST" action="doctor_dashboard.php?view=clinic" data-loading novalidate>
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="profile">
        <?php if ($form_errors): ?>
            <div class="alert" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                <p><?php echo h($form_errors['_'] ?? 'Fix the highlighted fields and save again.'); ?></p></div>
        <?php endif; ?>

        <h2 class="panel__title">About you</h2>
        <div class="form-grid form-grid--3">
            <div class="field<?php echo isset($form_errors['name']) ? ' field--error' : ''; ?>">
                <label class="field__label" for="name">Full name</label>
                <input class="input input--plain" id="name" name="name" value="<?php echo h($f['name']); ?>" required><?php echo err($form_errors, 'name'); ?>
            </div>
            <div class="field<?php echo isset($form_errors['specialty']) ? ' field--error' : ''; ?>">
                <label class="field__label" for="specialty">Specialty</label>
                <input class="input input--plain" id="specialty" name="specialty" value="<?php echo h($f['specialty']); ?>" required><?php echo err($form_errors, 'specialty'); ?>
            </div>
            <div class="field<?php echo isset($form_errors['experience_years']) ? ' field--error' : ''; ?>">
                <label class="field__label" for="experience_years">Years of experience</label>
                <input class="input input--plain" type="number" min="0" max="60" id="experience_years" name="experience_years" value="<?php echo h($f['experience_years']); ?>" required><?php echo err($form_errors, 'experience_years'); ?>
            </div>
            <div class="field span-3">
                <label class="field__label" for="bio">Short bio (shown to patients)</label>
                <textarea class="input input--plain" id="bio" name="bio" rows="3" maxlength="1000"><?php echo h($f['bio']); ?></textarea>
            </div>
        </div>

        <h2 class="panel__title">Clinic and contact</h2>
        <div class="form-grid form-grid--3">
            <div class="field<?php echo isset($form_errors['city_id']) ? ' field--error' : ''; ?>">
                <label class="field__label" for="city_id">City</label>
                <select class="input input--plain" id="city_id" name="city_id">
                    <?php foreach ($cities as $c): ?>
                        <option value="<?php echo (int) $c['id']; ?>"<?php echo (int) $c['id'] === (int) $f['city_id'] ? ' selected' : ''; ?>><?php echo h($c['name']); ?></option>
                    <?php endforeach; ?>
                </select><?php echo err($form_errors, 'city_id'); ?>
            </div>
            <div class="field span-2<?php echo isset($form_errors['address']) ? ' field--error' : ''; ?>">
                <label class="field__label" for="address">Clinic address</label>
                <input class="input input--plain" id="address" name="address" value="<?php echo h($f['address']); ?>" required><?php echo err($form_errors, 'address'); ?>
            </div>
            <div class="field<?php echo isset($form_errors['phone']) ? ' field--error' : ''; ?>">
                <label class="field__label" for="phone">Clinic phone</label>
                <input class="input input--plain" type="tel" id="phone" name="phone" value="<?php echo h($f['phone']); ?>" required><?php echo err($form_errors, 'phone'); ?>
            </div>
            <div class="field<?php echo isset($form_errors['email']) ? ' field--error' : ''; ?>">
                <label class="field__label" for="email">Email (also your login email)</label>
                <input class="input input--plain" type="email" id="email" name="email" value="<?php echo h($f['email']); ?>" required><?php echo err($form_errors, 'email'); ?>
            </div>
            <div class="field<?php echo isset($form_errors['consultation_fee']) ? ' field--error' : ''; ?>">
                <label class="field__label" for="consultation_fee">Consultation fee (Rs)</label>
                <input class="input input--plain" type="number" min="0" step="50" id="consultation_fee" name="consultation_fee" value="<?php echo h($f['consultation_fee']); ?>" required><?php echo err($form_errors, 'consultation_fee'); ?>
            </div>
        </div>

        <h2 class="panel__title">Weekly clinic hours</h2>
        <fieldset class="toggles<?php echo isset($form_errors['days']) ? ' field--error' : ''; ?>">
            <legend class="field__label">Clinic days</legend>
            <?php foreach (WEEK_DAYS as $d): ?>
                <label class="toggle"><input type="checkbox" name="days[]" value="<?php echo $d; ?>"<?php echo in_array($d, $f['days'], true) ? ' checked' : ''; ?>><span><?php echo substr($d, 0, 3); ?></span></label>
            <?php endforeach; ?>
            <?php echo err($form_errors, 'days'); ?>
        </fieldset>
        <fieldset class="toggles<?php echo isset($form_errors['slots']) ? ' field--error' : ''; ?>">
            <legend class="field__label">Time slots</legend>
            <?php foreach (SLOT_OPTIONS as $s): ?>
                <label class="toggle"><input type="checkbox" name="slots[]" value="<?php echo $s; ?>"<?php echo in_array($s, $f['slots'], true) ? ' checked' : ''; ?>><span><?php echo $s; ?></span></label>
            <?php endforeach; ?>
            <?php echo err($form_errors, 'slots'); ?>
        </fieldset>

        <div class="panel__foot">
            <button type="submit" class="btn btn--glow"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save profile</button>
        </div>
    </form>
<?php endif; ?>

<?php include 'includes/dash_footer.php'; ?>