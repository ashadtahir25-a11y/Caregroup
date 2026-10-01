<?php
// reschedule.php - A patient moves an upcoming appointment to another date or time.
// Rules: own appointment only, Pending or Confirmed, not yet started, at most 2 moves.
// After a move the appointment goes back to Pending so the doctor confirms the new time.
require_once 'db.php';
require_once 'includes/booking.php';
checkRole('patient');

const MAX_RESCHEDULES = 2;

$patient_id = (int) ($_SESSION['patient_id'] ?? 0);
$id = (int) ($_REQUEST['id'] ?? 0);

// Load the appointment with its doctor's clinic hours
$stmt = $pdo->prepare('SELECT a.*, d.name AS doctor_name, d.specialty, d.available_days, d.available_slots, d.address AS clinic_address, c.name AS city_name
                         FROM appointments a
                         JOIN doctors d ON d.id = a.doctor_id
                         JOIN cities c ON c.id = d.city_id
                        WHERE a.id = ? AND a.patient_id = ?');
$stmt->execute([$id, $patient_id]);
$app = $stmt->fetch();

// Why this appointment cannot be moved, or '' if it can
function reschedule_block(?array $app): string
{
    if (!$app) return 'That appointment was not found.';
    if (!in_array($app['status'], ['Pending', 'Confirmed'], true)) return 'Only upcoming appointments can be moved.';
    if (strtotime($app['appointment_date'] . ' ' . $app['time_slot']) <= time()) return 'This appointment time has already passed.';
    if ((int) $app['reschedule_count'] >= MAX_RESCHEDULES) return 'This appointment has already been moved ' . MAX_RESCHEDULES . ' times. Cancel it and book a new one instead.';
    return '';
}

$block = reschedule_block($app ?: null);
if ($block !== '') {
    flash('error', $block);
    redirect('patient_dashboard.php?view=history');
}

$days  = csv_list($app['available_days']);
$slots = sort_slots(csv_list($app['available_slots']));

/* ---------------- Save the new time ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = trim($_POST['date'] ?? '');
    $slot = trim($_POST['slot'] ?? '');
    $back = 'reschedule.php?id=' . $id . '&date=' . urlencode($date);

    $error = '';
    if (!csrf_check()) {
        $error = 'Your session expired. Try again.';
    } elseif ($date === $app['appointment_date'] && $slot === $app['time_slot']) {
        $error = 'That is the same time you already have. Choose a different date or slot.';
    } else {
        $error = booking_date_error($date, $days);
        if ($error === '' && !in_array($slot, $slots, true)) {
            $error = 'Choose one of the time slots shown.';
        }
        if ($error === '' && strtotime($date . ' ' . $slot) <= time()) {
            $error = 'That time has already passed. Choose a later slot.';
        }
        if ($error === '') {
            // Taken by someone else? (this appointment itself does not count)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND time_slot = ? AND status <> 'Cancelled' AND id <> ?");
            $stmt->execute([$app['doctor_id'], $date, $slot, $id]);
            if ($stmt->fetchColumn()) {
                $error = $slot . ' is already booked. Choose another time.';
            }
        }
        if ($error === '') {
            // The patient should not hold two visits at the same moment
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND appointment_date = ? AND time_slot = ? AND status <> 'Cancelled' AND id <> ?");
            $stmt->execute([$patient_id, $date, $slot, $id]);
            if ($stmt->fetchColumn()) {
                $error = 'You already have another appointment at ' . $slot . ' on this day.';
            }
        }
    }

    if ($error !== '') {
        flash('error', $error);
        redirect($back);
    }

    try {
        // The WHERE repeats the rules, so two quick clicks cannot move it twice
        $stmt = $pdo->prepare("UPDATE appointments
                                  SET appointment_date = ?, time_slot = ?, status = 'Pending', reschedule_count = reschedule_count + 1
                                WHERE id = ? AND patient_id = ? AND status IN ('Pending', 'Confirmed') AND reschedule_count < ?");
        $stmt->execute([$date, $slot, $id, $patient_id, MAX_RESCHEDULES]);
        if ($stmt->rowCount() === 0) {
            flash('error', 'This appointment can no longer be moved.');
            redirect('patient_dashboard.php?view=history');
        }
        notify_appointment($pdo, $id, 'rescheduled');
        flash('success', 'Appointment moved to ' . friendly_date($date) . ' at ' . $slot . '. The doctor will confirm the new time.');
        redirect('patient_dashboard.php?view=history');
    } catch (PDOException $e) {
        error_log('Reschedule failed: ' . $e->getMessage());
        flash('error', $e->getCode() === '23000' ? 'That time was just taken. Choose another slot.' : 'The appointment could not be moved. Try again.');
        redirect($back);
    }
}

/* ---------------- Page data ---------------- */
$date = trim($_GET['date'] ?? '');
$date_error = '';
$taken = $passed = [];
if ($date !== '') {
    $date_error = booking_date_error($date, $days);
    if ($date_error === '') {
        $stmt = $pdo->prepare("SELECT time_slot FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND status <> 'Cancelled' AND id <> ?");
        $stmt->execute([$app['doctor_id'], $date, $id]);
        $taken = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($slots as $s) {
            if (strtotime($date . ' ' . $s) <= time()) $passed[] = $s;
        }
    }
}

$next_dates = [];
$d = new DateTime('today');
for ($i = 0; $i <= BOOKING_DAYS_AHEAD && count($next_dates) < 6; $i++) {
    if (in_array($d->format('l'), $days, true)) $next_dates[] = $d->format('Y-m-d');
    $d->modify('+1 day');
}
$movesLeft = MAX_RESCHEDULES - (int) $app['reschedule_count'];

$page_title  = 'Move appointment | CARE Group';
$dash_title  = 'Move appointment';
$dash_sub    = 'You can move this appointment ' . $movesLeft . ' more time' . ($movesLeft === 1 ? '' : 's') . '.';
$dash_active = 'history';
include 'includes/dash_header.php';
?>

<div class="book">
    <aside class="book__doc glass">
        <p class="next__label">Current booking</p>
        <h2><?php echo h(doctor_name($app['doctor_name'])); ?></h2>
        <p class="doc-card__spec"><?php echo h($app['specialty']); ?></p>
        <dl class="book__facts">
            <div><dt>Date</dt><dd><?php echo h(date('l, j F Y', strtotime($app['appointment_date']))); ?></dd></div>
            <div><dt>Time</dt><dd><?php echo h($app['time_slot']); ?></dd></div>
            <div><dt>Status</dt><dd><?php echo status_badge($app['status']); ?></dd></div>
            <div><dt>Clinic</dt><dd><?php echo h($app['clinic_address']); ?>, <?php echo h($app['city_name']); ?></dd></div>
        </dl>
        <p class="muted small">After you move it, the doctor needs to confirm the new time again.</p>
        <a class="console__back" href="patient_dashboard.php?view=history"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Keep current time</a>
    </aside>

    <div class="book__steps">
        <section class="step glass">
            <h3><span class="steps__num steps__num--sm">1</span> Pick a new date</h3>
            <div class="date-chips">
                <?php foreach ($next_dates as $nd): ?>
                    <a class="date-chip<?php echo $nd === $date ? ' is-on' : ''; ?>" href="reschedule.php?id=<?php echo $id; ?>&amp;date=<?php echo $nd; ?>">
                        <span><?php echo date('D', strtotime($nd)); ?></span><b><?php echo date('j', strtotime($nd)); ?></b><span><?php echo date('M', strtotime($nd)); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
            <form class="date-form" method="GET" action="reschedule.php">
                <input type="hidden" name="id" value="<?php echo $id; ?>">
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
                <form method="POST" action="reschedule.php" data-loading>
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                    <input type="hidden" name="date" value="<?php echo h($date); ?>">
                    <div class="slots" role="radiogroup" aria-label="Time slots">
                        <?php $free = 0; foreach ($slots as $s):
                            $isCurrent = $date === $app['appointment_date'] && $s === $app['time_slot'];
                            $off = in_array($s, $taken, true) || in_array($s, $passed, true) || $isCurrent;
                            if (!$off) $free++; ?>
                            <label class="slot<?php echo $off ? ' is-taken' : ''; ?>">
                                <input type="radio" name="slot" value="<?php echo h($s); ?>" required<?php echo $off ? ' disabled' : ''; ?>>
                                <span><?php echo h($s); ?><?php if ($off): ?><small><?php echo $isCurrent ? 'Your time' : (in_array($s, $taken, true) ? 'Booked' : 'Passed'); ?></small><?php endif; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($free === 0): ?>
                        <p class="muted">No free slots on this day. Pick another date above.</p>
                    <?php else: ?>
                        <button type="submit" class="btn btn--glow"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i> Move appointment</button>
                    <?php endif; ?>
                </form>
            </section>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/dash_footer.php'; ?>
