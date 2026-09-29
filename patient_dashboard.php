<?php
// patient_dashboard.php - Patient area: overview, booking and appointment history.
require_once 'db.php';
require_once 'includes/booking.php';
checkRole('patient');

// Make sure we know this patient's id
$patient_id = $_SESSION['patient_id'] ?? null;
if (!$patient_id) {
    $stmt = $pdo->prepare('SELECT id FROM patients WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $patient_id = $stmt->fetchColumn();
    if (!$patient_id) {
        flash('error', 'Your patient profile is missing. Contact the CARE Group admin.');
        redirect('logout.php');
    }
    $_SESSION['patient_id'] = (int) $patient_id;
}
$patient_id = (int) $patient_id;

// Old links used ?book_doc_id=ID without a view, so treat them as the booking view
$view = $_GET['view'] ?? (isset($_GET['book_doc_id']) ? 'book' : 'home');
if (!in_array($view, ['home', 'book', 'history'], true)) {
    $view = 'home';
}

/* =====================================================================
 * POST actions (always CSRF-checked, always redirect afterwards)
 * ===================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!csrf_check()) {
        flash('error', 'Your session expired. Try again.');
        redirect('patient_dashboard.php');
    }

    // ---- Book an appointment ----
    if ($action === 'book') {
        $doctor_id = (int) ($_POST['doctor_id'] ?? 0);
        $date      = trim($_POST['date'] ?? '');
        $slot      = trim($_POST['slot'] ?? '');
        $notes     = trim($_POST['notes'] ?? '');
        $back      = 'patient_dashboard.php?view=book&book_doc_id=' . $doctor_id . '&date=' . urlencode($date);

        $stmt = $pdo->prepare('SELECT available_days, available_slots FROM doctors WHERE id = ?');
        $stmt->execute([$doctor_id]);
        $doc = $stmt->fetch();

        if (!$doc) {
            flash('error', 'That doctor is no longer listed. Choose another doctor.');
            redirect('patient_dashboard.php?view=book');
        }
        $error = booking_date_error($date, csv_list($doc['available_days']));
        if ($error === '' && !in_array($slot, csv_list($doc['available_slots']), true)) {
            $error = 'Choose one of the time slots shown.';
        }
        if ($error === '' && strtotime($date . ' ' . $slot) <= time()) {
            $error = 'That time has already passed today. Choose a later slot.';
        }
        if ($error === '' && strlen($notes) > 500) {
            $error = 'Keep the note under 500 characters.';
        }
        if ($error === '' && in_array($slot, taken_slots($pdo, $doctor_id, $date), true)) {
            $error = 'Someone just booked ' . $slot . '. Choose another time.';
        }
        if ($error === '') {
            // The patient should not hold two visits at the same time
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND appointment_date = ? AND time_slot = ? AND status <> 'Cancelled'");
            $stmt->execute([$patient_id, $date, $slot]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'You already have another appointment at ' . $slot . ' on this day.';
            }
        }
        if ($error !== '') {
            flash('error', $error);
            redirect($back);
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO appointments (doctor_id, patient_id, appointment_date, time_slot, status, notes) VALUES (?, ?, ?, ?, 'Pending', ?)");
            $stmt->execute([$doctor_id, $patient_id, $date, $slot, $notes]);
            flash('success', 'Appointment requested for ' . friendly_date($date) . ' at ' . $slot . '. The doctor will confirm it.');
            redirect('patient_dashboard.php?view=history');
        } catch (PDOException $e) {
            // 23000 = the database's unique rule blocked a double booking made at the same second
            error_log('Booking failed: ' . $e->getMessage());
            flash('error', $e->getCode() === '23000' ? 'That time was just taken. Choose another slot.' : 'Booking failed. Try again in a minute.');
            redirect($back);
        }
    }

    // ---- Cancel own appointment ----
    if ($action === 'cancel') {
        $id = (int) ($_POST['appointment_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT status, appointment_date FROM appointments WHERE id = ? AND patient_id = ?');
        $stmt->execute([$id, $patient_id]);
        $app = $stmt->fetch();

        if (!$app || !can_change_status('patient', $app['status'], 'Cancelled') || $app['appointment_date'] < date('Y-m-d')) {
            flash('error', 'This appointment can no longer be cancelled.');
        } else {
            $pdo->prepare("UPDATE appointments SET status = 'Cancelled' WHERE id = ? AND patient_id = ?")->execute([$id, $patient_id]);
            flash('success', 'Appointment cancelled.');
        }
        redirect('patient_dashboard.php?view=' . (($_POST['return'] ?? '') === 'home' ? 'home' : 'history'));
    }

    redirect('patient_dashboard.php');
}

/* =====================================================================
 * Data for the page
 * ===================================================================== */
$appSql = 'SELECT a.*, d.name AS doctor_name, d.specialty, d.address AS clinic_address, d.phone AS clinic_phone, c.name AS city_name
             FROM appointments a
             JOIN doctors d ON d.id = a.doctor_id
             JOIN cities c ON c.id = d.city_id
            WHERE a.patient_id = ?';

$stmt = $pdo->prepare($appSql . " AND a.appointment_date >= CURDATE() AND a.status IN ('Pending','Confirmed') ORDER BY a.appointment_date, " . SLOT_ORDER_SQL);
$stmt->execute([$patient_id]);
$upcoming = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT status, COUNT(*) AS n FROM appointments WHERE patient_id = ? GROUP BY status');
$stmt->execute([$patient_id]);
$counts = array_column($stmt->fetchAll(), 'n', 'status');

// Renders one appointment row with an optional cancel button
function appointment_row(array $a, string $return): string
{
    $canCancel = in_array($a['status'], ['Pending', 'Confirmed'], true) && $a['appointment_date'] >= date('Y-m-d');
    ob_start(); ?>
    <article class="row glass" data-reveal>
        <div class="row__date">
            <b><?php echo date('j', strtotime($a['appointment_date'])); ?></b>
            <span><?php echo date('M', strtotime($a['appointment_date'])); ?></span>
        </div>
        <div class="row__main">
            <h3><?php echo h(doctor_name($a['doctor_name'])); ?> <small><?php echo h($a['specialty']); ?></small></h3>
            <p><i class="fa-regular fa-clock" aria-hidden="true"></i> <?php echo h(friendly_date($a['appointment_date'])); ?>, <?php echo h($a['time_slot']); ?>
               &nbsp; <i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?php echo h($a['clinic_address']); ?>, <?php echo h($a['city_name']); ?></p>
            <?php if ($a['notes'] !== null && $a['notes'] !== ''): ?><p class="row__note">Your note: <?php echo h($a['notes']); ?></p><?php endif; ?>
        </div>
        <div class="row__side">
            <?php echo status_badge($a['status']); ?>
            <?php if ($canCancel): ?>
                <form method="POST" action="patient_dashboard.php" data-confirm="Cancel this appointment? The slot will be released for other patients.">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="return" value="<?php echo h($return); ?>">
                    <input type="hidden" name="appointment_id" value="<?php echo (int) $a['id']; ?>">
                    <button type="submit" class="btn btn--ghost btn--xs">Cancel</button>
                </form>
            <?php endif; ?>
        </div>
    </article>
    <?php
    return ob_get_clean();
}

/* ---------- Booking view data ---------- */
$book_doctor = null;
$date = trim($_GET['date'] ?? '');
$date_error = '';
$slots = $taken = $passed = $next_dates = [];
$pick_doctors = [];

if ($view === 'book') {
    $book_id = (int) ($_GET['book_doc_id'] ?? 0);
    if ($book_id > 0) {
        $stmt = $pdo->prepare('SELECT d.*, c.name AS city_name FROM doctors d JOIN cities c ON c.id = d.city_id WHERE d.id = ?');
        $stmt->execute([$book_id]);
        $book_doctor = $stmt->fetch() ?: null;
    }

    if ($book_doctor) {
        $days  = csv_list($book_doctor['available_days']);
        $slots = sort_slots(csv_list($book_doctor['available_slots']));

        // Next 6 clinic dates as quick picks
        $d = new DateTime('today');
        for ($i = 0; $i <= BOOKING_DAYS_AHEAD && count($next_dates) < 6; $i++) {
            if (in_array($d->format('l'), $days, true)) {
                $next_dates[] = $d->format('Y-m-d');
            }
            $d->modify('+1 day');
        }

        if ($date !== '') {
            $date_error = booking_date_error($date, $days);
            if ($date_error === '') {
                $taken = taken_slots($pdo, (int) $book_doctor['id'], $date);
                // Slots earlier today can no longer be booked
                foreach ($slots as $s) {
                    if (strtotime($date . ' ' . $s) <= time()) {
                        $passed[] = $s;
                    }
                }
            }
        }
    } else {
        // Step 0: choose a doctor
        $f_spec = trim($_GET['specialty'] ?? '');
        $f_city = (int) ($_GET['city'] ?? 0);
        $sql = 'SELECT d.*, c.name AS city_name FROM doctors d JOIN cities c ON c.id = d.city_id WHERE 1 = 1';
        $params = [];
        if ($f_spec !== '') { $sql .= ' AND d.specialty = ?'; $params[] = $f_spec; }
        if ($f_city > 0)    { $sql .= ' AND d.city_id = ?';   $params[] = $f_city; }
        $stmt = $pdo->prepare($sql . ' ORDER BY d.experience_years DESC');
        $stmt->execute($params);
        $pick_doctors = $stmt->fetchAll();
        $all_specs  = $pdo->query("SELECT DISTINCT specialty FROM doctors ORDER BY specialty")->fetchAll(PDO::FETCH_COLUMN);
        $all_cities = $pdo->query('SELECT id, name FROM cities ORDER BY name')->fetchAll();
    }
}

/* ---------- History view data ---------- */
$tab = $_GET['tab'] ?? 'upcoming';
$history = [];
if ($view === 'history') {
    $where = [
        'upcoming'  => " AND a.appointment_date >= CURDATE() AND a.status IN ('Pending','Confirmed') ORDER BY a.appointment_date, " . SLOT_ORDER_SQL,
        'past'      => " AND (a.status = 'Completed' OR (a.appointment_date < CURDATE() AND a.status <> 'Cancelled')) ORDER BY a.appointment_date DESC",
        'cancelled' => " AND a.status = 'Cancelled' ORDER BY a.appointment_date DESC",
        'all'       => ' ORDER BY a.appointment_date DESC, ' . SLOT_ORDER_SQL,
    ];
    if (!isset($where[$tab])) {
        $tab = 'upcoming';
    }
    $stmt = $pdo->prepare($appSql . $where[$tab]);
    $stmt->execute([$patient_id]);
    $history = $stmt->fetchAll();
}

if (empty($_SESSION['display_name'])) {
    $stmt = $pdo->prepare('SELECT name FROM patients WHERE id = ?');
    $stmt->execute([$patient_id]);
    $_SESSION['display_name'] = $stmt->fetchColumn() ?: $_SESSION['username'];
}

$titles = ['home' => 'Overview', 'book' => 'Book an appointment', 'history' => 'My appointments'];
$page_title  = $titles[$view] . ' | CARE Group';
$dash_title  = $view === 'home' ? 'Hello, ' . explode(' ', $_SESSION['display_name'])[0] : $titles[$view];
$dash_active = $view;
include 'includes/dash_header.php';
?>

<?php if ($view === 'home'): ?>
    <!-- ================= OVERVIEW ================= -->
    <div class="kpis">
        <div class="kpi glass"><span>Upcoming</span><b data-count="<?php echo count($upcoming); ?>"><?php echo count($upcoming); ?></b></div>
        <div class="kpi glass"><span>Waiting for confirmation</span><b data-count="<?php echo (int) ($counts['Pending'] ?? 0); ?>"><?php echo (int) ($counts['Pending'] ?? 0); ?></b></div>
        <div class="kpi glass"><span>Completed visits</span><b data-count="<?php echo (int) ($counts['Completed'] ?? 0); ?>"><?php echo (int) ($counts['Completed'] ?? 0); ?></b></div>
    </div>

    <?php if ($upcoming): $next = $upcoming[0]; ?>
        <section class="next glass" data-tilt="3">
            <div class="next__pulse" aria-hidden="true">
                <svg viewBox="0 0 300 60" preserveAspectRatio="none"><path d="M0 34 H90 L100 34 L106 24 L112 44 L122 4 L132 56 L141 34 L158 34 L164 28 L171 34 H300"/></svg>
            </div>
            <p class="next__label">Your next visit</p>
            <h2><?php echo h(friendly_date($next['appointment_date'])); ?> at <?php echo h($next['time_slot']); ?></h2>
            <p class="next__who"><?php echo h(doctor_name($next['doctor_name'])); ?>, <?php echo h($next['specialty']); ?></p>
            <p class="next__where"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?php echo h($next['clinic_address']); ?>, <?php echo h($next['city_name']); ?>
               &nbsp; <i class="fa-solid fa-phone" aria-hidden="true"></i> <?php echo h($next['clinic_phone']); ?></p>
            <?php echo status_badge($next['status']); ?>
        </section>
    <?php endif; ?>

    <div class="panel-head">
        <h2>Upcoming appointments</h2>
        <a class="btn btn--glow btn--sm" href="patient_dashboard.php?view=book"><i class="fa-solid fa-plus" aria-hidden="true"></i> Book appointment</a>
    </div>
    <div class="rows">
        <?php foreach (array_slice($upcoming, 0, 5) as $a) { echo appointment_row($a, 'home'); } ?>
        <?php if (!$upcoming): ?>
            <div class="empty glass">
                <i class="fa-solid fa-calendar-plus" aria-hidden="true"></i>
                <p>You have no upcoming appointments.</p>
                <a class="btn btn--glow btn--sm" href="patient_dashboard.php?view=book">Book your first visit</a>
            </div>
        <?php endif; ?>
    </div>

<?php elseif ($view === 'book' && !$book_doctor): ?>
    <!-- ================= BOOK: choose a doctor ================= -->
    <form class="filters glass" method="GET" action="patient_dashboard.php">
        <input type="hidden" name="view" value="book">
        <label class="finder__field">
            <span>Specialty</span>
            <select name="specialty">
                <option value="">Any specialty</option>
                <?php foreach ($all_specs as $sp): ?>
                    <option value="<?php echo h($sp); ?>"<?php echo $sp === ($f_spec ?? '') ? ' selected' : ''; ?>><?php echo h($sp); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="finder__field">
            <span>City</span>
            <select name="city">
                <option value="">All cities</option>
                <?php foreach ($all_cities as $c): ?>
                    <option value="<?php echo (int) $c['id']; ?>"<?php echo (int) $c['id'] === ($f_city ?? 0) ? ' selected' : ''; ?>><?php echo h($c['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="btn btn--glow"><i class="fa-solid fa-filter" aria-hidden="true"></i> Filter</button>
    </form>

    <div class="pick-list">
        <?php foreach ($pick_doctors as $d): ?>
            <a class="pick glass" href="patient_dashboard.php?view=book&amp;book_doc_id=<?php echo (int) $d['id']; ?>" data-reveal>
                <span class="avatar" style="--hue: <?php echo avatar_hue((int) $d['id']); ?>" aria-hidden="true"><?php echo h(initials($d['name'])); ?></span>
                <div class="pick__main">
                    <b><?php echo h(doctor_name($d['name'])); ?></b>
                    <span><?php echo h($d['specialty']); ?>, <?php echo h($d['city_name']); ?>. <?php echo (int) $d['experience_years']; ?> yrs experience</span>
                </div>
                <span class="pick__fee">Rs <?php echo number_format((float) $d['consultation_fee']); ?></span>
                <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
            </a>
        <?php endforeach; ?>
        <?php if (!$pick_doctors): ?>
            <div class="empty glass"><i class="fa-solid fa-user-doctor" aria-hidden="true"></i><p>No doctor matches these filters.</p></div>
        <?php endif; ?>
    </div>

<?php elseif ($view === 'book'): ?>
    <!-- ================= BOOK: choose date and slot ================= -->
    <div class="book">
        <aside class="book__doc glass" data-tilt="4">
            <span class="avatar avatar--lg" style="--hue: <?php echo avatar_hue((int) $book_doctor['id']); ?>" aria-hidden="true" data-depth><?php echo h(initials($book_doctor['name'])); ?></span>
            <h2><?php echo h(doctor_name($book_doctor['name'])); ?></h2>
            <p class="doc-card__spec"><?php echo h($book_doctor['specialty']); ?></p>
            <dl class="book__facts">
                <div><dt>Fee</dt><dd>Rs <?php echo number_format((float) $book_doctor['consultation_fee']); ?></dd></div>
                <div><dt>Experience</dt><dd><?php echo (int) $book_doctor['experience_years']; ?> years</dd></div>
                <div><dt>Clinic</dt><dd><?php echo h($book_doctor['address']); ?>, <?php echo h($book_doctor['city_name']); ?></dd></div>
                <div><dt>Clinic days</dt><dd><?php echo h(implode(', ', csv_list($book_doctor['available_days'])) ?: 'Not set'); ?></dd></div>
            </dl>
            <a class="console__back" href="patient_dashboard.php?view=book"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Choose another doctor</a>
        </aside>

        <div class="book__steps">
            <section class="step glass">
                <h3><span class="steps__num steps__num--sm">1</span> Pick a date</h3>
                <?php if ($next_dates): ?>
                    <div class="date-chips">
                        <?php foreach ($next_dates as $nd): ?>
                            <a class="date-chip<?php echo $nd === $date ? ' is-on' : ''; ?>" href="patient_dashboard.php?view=book&amp;book_doc_id=<?php echo (int) $book_doctor['id']; ?>&amp;date=<?php echo $nd; ?>">
                                <span><?php echo date('D', strtotime($nd)); ?></span><b><?php echo date('j', strtotime($nd)); ?></b><span><?php echo date('M', strtotime($nd)); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="muted">This doctor has not set clinic days yet.</p>
                <?php endif; ?>
                <form class="date-form" method="GET" action="patient_dashboard.php">
                    <input type="hidden" name="view" value="book">
                    <input type="hidden" name="book_doc_id" value="<?php echo (int) $book_doctor['id']; ?>">
                    <label class="finder__field">
                        <span>Or choose another date</span>
                        <input type="date" name="date" value="<?php echo h($date); ?>" min="<?php echo date('Y-m-d'); ?>"
                               max="<?php echo date('Y-m-d', strtotime('+' . BOOKING_DAYS_AHEAD . ' days')); ?>" data-autosubmit>
                    </label>
                    <button type="submit" class="btn btn--ghost btn--sm">Show times</button>
                </form>
                <?php if ($date_error !== ''): ?>
                    <div class="alert" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><p><?php echo h($date_error); ?></p></div>
                <?php endif; ?>
            </section>

            <?php if ($date !== '' && $date_error === ''): ?>
                <section class="step glass enter">
                    <h3><span class="steps__num steps__num--sm">2</span> Choose a time on <?php echo h(date('l, j F', strtotime($date))); ?></h3>
                    <form method="POST" action="patient_dashboard.php" data-loading>
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="book">
                        <input type="hidden" name="doctor_id" value="<?php echo (int) $book_doctor['id']; ?>">
                        <input type="hidden" name="date" value="<?php echo h($date); ?>">

                        <div class="slots" role="radiogroup" aria-label="Time slots">
                            <?php $free = 0; foreach ($slots as $s):
                                $isTaken  = in_array($s, $taken, true);
                                $isPassed = in_array($s, $passed, true);
                                $off = $isTaken || $isPassed;
                                if (!$off) $free++; ?>
                                <label class="slot<?php echo $off ? ' is-taken' : ''; ?>">
                                    <input type="radio" name="slot" value="<?php echo h($s); ?>" required<?php echo $off ? ' disabled' : ''; ?>>
                                    <span><?php echo h($s); ?><?php if ($off): ?><small><?php echo $isTaken ? 'Booked' : 'Passed'; ?></small><?php endif; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($free === 0): ?>
                            <p class="muted">No free slots left on this day. Pick another date above.</p>
                        <?php else: ?>
                            <div class="field">
                                <label class="field__label" for="notes">Note for the doctor (optional)</label>
                                <textarea class="input input--plain" id="notes" name="notes" rows="3" maxlength="500" placeholder="Describe your symptoms or reason for the visit"></textarea>
                            </div>
                            <button type="submit" class="btn btn--glow"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i> Request appointment</button>
                        <?php endif; ?>
                    </form>
                </section>
            <?php endif; ?>
        </div>
    </div>

<?php else: ?>
    <!-- ================= HISTORY ================= -->
    <nav class="tabs" aria-label="Filter appointments">
        <?php foreach (['upcoming' => 'Upcoming', 'past' => 'Past', 'cancelled' => 'Cancelled', 'all' => 'All'] as $k => $label): ?>
            <a href="patient_dashboard.php?view=history&amp;tab=<?php echo $k; ?>"<?php echo $tab === $k ? ' aria-current="page"' : ''; ?>><?php echo $label; ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="rows">
        <?php foreach ($history as $a) { echo appointment_row($a, 'history'); } ?>
        <?php if (!$history): ?>
            <div class="empty glass"><i class="fa-regular fa-calendar" aria-hidden="true"></i><p>No appointments here.</p></div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php include 'includes/dash_footer.php'; ?>