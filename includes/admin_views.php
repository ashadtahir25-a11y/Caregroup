<?php
// includes/admin_views.php - HTML for each admin section. Included by admin_dashboard.php.

/* ---------------- Small form helpers ---------------- */
function f_error(array $errors, string $name): string
{
    return isset($errors[$name]) ? '<p class="field__hint">' . h($errors[$name]) . '</p>' : '';
}

function f_input(array $errors, string $name, string $label, $value, string $type = 'text', string $extra = '', string $span = ''): string
{
    return '<div class="field' . ($span ? " $span" : '') . (isset($errors[$name]) ? ' field--error' : '') . '">'
        . '<label class="field__label" for="f-' . $name . '">' . h($label) . '</label>'
        . '<input class="input input--plain" type="' . $type . '" id="f-' . $name . '" name="' . $name . '" value="' . h($value) . '" ' . $extra . '>'
        . f_error($errors, $name) . '</div>';
}

function f_textarea(array $errors, string $name, string $label, $value, int $rows = 3, string $span = 'span-3', string $hint = ''): string
{
    return '<div class="field ' . $span . (isset($errors[$name]) ? ' field--error' : '') . '">'
        . '<label class="field__label" for="f-' . $name . '">' . h($label) . '</label>'
        . '<textarea class="input input--plain" id="f-' . $name . '" name="' . $name . '" rows="' . $rows . '">' . h($value) . '</textarea>'
        . ($hint && !isset($errors[$name]) ? '<p class="field__hint">' . h($hint) . '</p>' : '')
        . f_error($errors, $name) . '</div>';
}

function f_select(array $errors, string $name, string $label, array $options, $selected): string
{
    $html = '<div class="field' . (isset($errors[$name]) ? ' field--error' : '') . '">'
        . '<label class="field__label" for="f-' . $name . '">' . h($label) . '</label>'
        . '<select class="input input--plain" id="f-' . $name . '" name="' . $name . '">';
    foreach ($options as $value => $text) {
        $html .= '<option value="' . h($value) . '"' . ((string) $value === (string) $selected ? ' selected' : '') . '>' . h($text) . '</option>';
    }
    return $html . '</select>' . f_error($errors, $name) . '</div>';
}

// A tiny POST form with one button (used for delete and status changes)
function post_button(string $action, int $id, string $label, string $class, string $confirm = '', array $extra = []): string
{
    $html = '<form method="POST" action="admin_dashboard.php"' . ($confirm ? ' data-confirm="' . h($confirm) . '"' : '') . '>'
        . csrf_field()
        . '<input type="hidden" name="action" value="' . h($action) . '">'
        . '<input type="hidden" name="id" value="' . $id . '">';
    foreach ($extra as $k => $v) {
        $html .= '<input type="hidden" name="' . h($k) . '" value="' . h($v) . '">';
    }
    return $html . '<button type="submit" class="btn ' . $class . ' btn--xs">' . $label . '</button></form>';
}

function toolbar(string $view, string $q, string $placeholder, string $addLabel = ''): string
{
    $html = '<div class="toolbar"><form class="toolbar__search" method="GET" action="admin_dashboard.php" role="search">'
        . '<input type="hidden" name="view" value="' . h($view) . '">'
        . '<i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>'
        . '<input class="input input--plain" type="search" name="q" value="' . h($q) . '" placeholder="' . h($placeholder) . '" aria-label="' . h($placeholder) . '">'
        . '</form>';
    if ($addLabel) {
        $html .= '<a class="btn btn--glow btn--sm" href="admin_dashboard.php?view=' . h($view) . '&amp;add=1"><i class="fa-solid fa-plus" aria-hidden="true"></i> ' . h($addLabel) . '</a>';
    }
    return $html . '</div>';
}

$cityOptions = ['' => 'Choose a city'];
foreach ($cities as $c) {
    $cityOptions[$c['id']] = $c['name'];
}
?>

<?php if ($view === 'overview'): ?>
<!-- ======================= OVERVIEW ======================= -->
<div class="kpis kpis--4">
    <div class="kpi glass"><span>Doctors</span><b data-count="<?php echo $kpi['doctors']; ?>"><?php echo $kpi['doctors']; ?></b><small><?php echo count($cities); ?> cities</small></div>
    <div class="kpi glass"><span>Patients</span><b data-count="<?php echo $kpi['patients']; ?>"><?php echo $kpi['patients']; ?></b><small><?php echo $kpi['new_pat']; ?> joined this month</small></div>
    <div class="kpi glass"><span>Appointments</span><b data-count="<?php echo $kpi['total']; ?>"><?php echo $kpi['total']; ?></b><small><?php echo $kpi['today']; ?> today</small></div>
    <a class="kpi kpi--alert glass" href="admin_dashboard.php?view=appointments&amp;tab=Pending"><span>Waiting for a doctor</span><b data-count="<?php echo $kpi['pending']; ?>"><?php echo $kpi['pending']; ?></b><small>Review pending requests</small></a>
</div>

<div class="charts">
    <section class="chart-card chart-card--wide glass" data-reveal>
        <h2>Appointments by month</h2>
        <div class="bars" role="img" aria-label="Appointments in each of the last six months">
            <?php $i = 0; foreach ($months as $m): ?>
                <div class="bars__col">
                    <span class="bars__val"><?php echo $m['n']; ?></span>
                    <div class="bars__bar" style="--h: <?php echo round($m['n'] / $monthMax * 100); ?>%; --d: <?php echo $i++ * 90; ?>ms"></div>
                    <span class="bars__label"><?php echo h($m['label']); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="chart-card glass" data-reveal>
        <h2>By status</h2>
        <?php
        $colors = ['Pending' => 'var(--amber)', 'Confirmed' => 'var(--pulse-2)', 'Completed' => 'var(--pulse)', 'Cancelled' => 'var(--vital)'];
        $sum = array_sum($statusCounts); $start = 0; $segs = [];
        foreach ($colors as $st => $col) {
            $pct = $sum ? ($statusCounts[$st] ?? 0) / $sum * 100 : 0;
            $segs[] = "$col " . round($start, 2) . '% ' . round($start + $pct, 2) . '%';
            $start += $pct;
        }
        ?>
        <div class="donut donut--sm" style="--donut: conic-gradient(<?php echo $sum ? implode(', ', $segs) : 'var(--ink-700) 0 100%'; ?>)">
            <div class="donut__hole"><b><?php echo $sum; ?></b><span>total</span></div>
        </div>
        <ul class="legend">
            <?php foreach ($colors as $st => $col): ?>
                <li><i style="background: <?php echo $col; ?>"></i><?php echo $st; ?><b><?php echo (int) ($statusCounts[$st] ?? 0); ?></b></li>
            <?php endforeach; ?>
        </ul>
    </section>
</div>

<div class="charts">
    <section class="chart-card glass" data-reveal>
        <h2>Doctors by city</h2>
        <?php $cityMax = max(1, max(array_column($cities, 'doctors') ?: [0])); ?>
        <ul class="hbars">
            <?php foreach ($cities as $c): ?>
                <li><span><?php echo h($c['name']); ?></span><div class="hbars__track"><i style="--w: <?php echo round($c['doctors'] / $cityMax * 100); ?>%"></i></div><b><?php echo (int) $c['doctors']; ?></b></li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="chart-card chart-card--wide glass" data-reveal>
        <div class="panel-head panel-head--flush">
            <h2>Latest bookings</h2>
            <a class="link-more" href="admin_dashboard.php?view=appointments">All appointments</a>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Patient</th><th>Doctor</th><th>When</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($recent as $a): ?>
                    <tr>
                        <td><?php echo h($a['patient_name']); ?></td>
                        <td><?php echo h(doctor_name($a['doctor_name'])); ?></td>
                        <td><?php echo h(friendly_date($a['appointment_date'])); ?>, <?php echo h($a['time_slot']); ?></td>
                        <td><?php echo status_badge($a['status']); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$recent): ?><tr><td colspan="4" class="table__empty">No bookings yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php if ($specialties): ?>
    <div class="spec-strip">
        <?php foreach ($specialties as $sp): ?>
            <span class="chip"><i class="fa-solid <?php echo specialty_icon($sp['specialty']); ?>" aria-hidden="true"></i> <?php echo h($sp['specialty']); ?> <b><?php echo (int) $sp['n']; ?></b></span>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php elseif ($view === 'appointments'): ?>
<!-- ======================= APPOINTMENTS ======================= -->
<nav class="tabs" aria-label="Filter by status">
    <?php foreach ($tabs as $k => $label): ?>
        <a href="admin_dashboard.php?<?php echo h(http_build_query(array_filter(['view' => 'appointments', 'tab' => $k, 'doctor' => $fDoc ?: null, 'from' => $fFrom ?: null, 'to' => $fTo ?: null, 'q' => $q ?: null]))); ?>"<?php echo $tab === $k ? ' aria-current="page"' : ''; ?>><?php echo $label; ?></a>
    <?php endforeach; ?>
</nav>

<form class="filters filters--admin glass" method="GET" action="admin_dashboard.php">
    <input type="hidden" name="view" value="appointments">
    <input type="hidden" name="tab" value="<?php echo h($tab); ?>">
    <label class="finder__field"><span>Patient name</span><input type="search" name="q" value="<?php echo h($q); ?>" placeholder="Search"></label>
    <label class="finder__field"><span>Doctor</span>
        <select name="doctor"><option value="">All doctors</option>
            <?php foreach ($doctorList as $d): ?><option value="<?php echo (int) $d['id']; ?>"<?php echo $fDoc === (int) $d['id'] ? ' selected' : ''; ?>><?php echo h(doctor_name($d['name'])); ?></option><?php endforeach; ?>
        </select>
    </label>
    <label class="finder__field"><span>From</span><input type="date" name="from" value="<?php echo h($fFrom); ?>"></label>
    <label class="finder__field"><span>To</span><input type="date" name="to" value="<?php echo h($fTo); ?>"></label>
    <button type="submit" class="btn btn--glow"><i class="fa-solid fa-filter" aria-hidden="true"></i> Filter</button>
</form>

<p class="results-count"><?php echo $totalRows; ?> appointment<?php echo $totalRows === 1 ? '' : 's'; ?><?php if ($fDoc || $fFrom || $fTo || $q !== ''): ?> &nbsp;<a href="admin_dashboard.php?view=appointments&amp;tab=<?php echo h($tab); ?>">Clear filters</a><?php endif; ?></p>

<div class="table-wrap glass">
    <table class="table">
        <thead><tr><th>Date</th><th>Patient</th><th>Doctor</th><th>Note</th><th>Status</th><th class="table__actions">Actions</th></tr></thead>
        <tbody>
        <?php $return = 'admin_dashboard.php?' . $filterQuery . '&page=' . $page; foreach ($appointments as $a): ?>
            <tr>
                <td><b><?php echo h(date('j M Y', strtotime($a['appointment_date']))); ?></b><br><span class="muted"><?php echo h($a['time_slot']); ?></span></td>
                <td><?php echo h($a['patient_name']); ?><br><span class="muted"><?php echo h($a['patient_phone']); ?></span></td>
                <td><?php echo h(doctor_name($a['doctor_name'])); ?><br><span class="muted"><?php echo h($a['specialty']); ?></span></td>
                <td class="table__note"><?php echo h($a['notes'] ?? ''); ?></td>
                <td><?php echo status_badge($a['status']); ?></td>
                <td class="table__actions">
                    <?php
                    if (can_change_status('admin', $a['status'], 'Confirmed')) echo post_button('appt_status', (int) $a['id'], 'Confirm', 'btn--glow', '', ['to' => 'Confirmed', 'return' => $return]);
                    if (can_change_status('admin', $a['status'], 'Completed') && $a['appointment_date'] <= date('Y-m-d')) echo post_button('appt_status', (int) $a['id'], 'Complete', 'btn--glow', '', ['to' => 'Completed', 'return' => $return]);
                    if (can_change_status('admin', $a['status'], 'Cancelled')) echo post_button('appt_status', (int) $a['id'], 'Cancel', 'btn--ghost', 'Cancel this appointment for ' . $a['patient_name'] . '?', ['to' => 'Cancelled', 'return' => $return]);
                    ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$appointments): ?><tr><td colspan="6" class="table__empty">No appointments match these filters.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($pages > 1): ?>
    <nav class="pager" aria-label="Pages">
        <?php for ($p = 1; $p <= $pages; $p++): ?>
            <a href="admin_dashboard.php?<?php echo h($filterQuery); ?>&amp;page=<?php echo $p; ?>"<?php echo $p === $page ? ' aria-current="page"' : ''; ?>><?php echo $p; ?></a>
        <?php endfor; ?>
    </nav>
<?php endif; ?>

<?php elseif ($view === 'doctors'): ?>
<!-- ======================= DOCTORS ======================= -->
<?php if ($form): $isNew = !$edit; ?>
    <form class="panel glass enter" method="POST" action="admin_dashboard.php?view=doctors" data-loading novalidate>
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="doctor_save">
        <input type="hidden" name="id" value="<?php echo $edit; ?>">
        <div class="panel-head panel-head--flush">
            <h2><?php echo $isNew ? 'Add a doctor' : 'Edit ' . h(doctor_name($form['name'])); ?></h2>
            <a class="btn btn--ghost btn--sm" href="admin_dashboard.php?view=doctors">Close</a>
        </div>

        <h3 class="panel__title">Login</h3>
        <div class="form-grid form-grid--3">
            <?php if ($isNew): ?>
                <?php echo f_input($errors, 'username', 'Username', $form['username'], 'text', 'autocomplete="off"'); ?>
                <?php echo f_input($errors, 'password', 'Password', '', 'password', 'autocomplete="new-password"'); ?>
            <?php else: ?>
                <div class="field"><span class="field__label">Username</span><p class="static"><?php echo h($form['username']); ?></p></div>
                <?php echo f_input($errors, 'password', 'New password (leave empty to keep)', '', 'password', 'autocomplete="new-password"'); ?>
            <?php endif; ?>
            <?php echo f_input($errors, 'email', 'Email', $form['email'], 'email'); ?>
        </div>

        <h3 class="panel__title">Profile</h3>
        <div class="form-grid form-grid--3">
            <?php echo f_input($errors, 'name', 'Full name (without "Dr")', $form['name']); ?>
            <?php echo f_input($errors, 'specialty', 'Specialty', $form['specialty'], 'text', 'placeholder="Cardiologist"'); ?>
            <?php echo f_select($errors, 'city_id', 'City', $cityOptions, $form['city_id']); ?>
            <?php echo f_input($errors, 'phone', 'Clinic phone', $form['phone'], 'tel'); ?>
            <?php echo f_input($errors, 'experience_years', 'Years of experience', $form['experience_years'], 'number', 'min="0" max="60"'); ?>
            <?php echo f_input($errors, 'consultation_fee', 'Fee (Rs)', $form['consultation_fee'], 'number', 'min="0" step="50"'); ?>
            <?php echo f_input($errors, 'address', 'Clinic address', $form['address'], 'text', '', 'span-3'); ?>
            <?php echo f_textarea($errors, 'bio', 'Short bio (optional)', $form['bio'] ?? '', 2); ?>
        </div>

        <h3 class="panel__title">Clinic hours</h3>
        <fieldset class="toggles<?php echo isset($errors['days']) ? ' field--error' : ''; ?>">
            <legend class="field__label">Days</legend>
            <?php foreach (WEEK_DAYS as $d): ?>
                <label class="toggle"><input type="checkbox" name="days[]" value="<?php echo $d; ?>"<?php echo in_array($d, $form['days'], true) ? ' checked' : ''; ?>><span><?php echo substr($d, 0, 3); ?></span></label>
            <?php endforeach; ?>
            <?php echo f_error($errors, 'days'); ?>
        </fieldset>
        <fieldset class="toggles<?php echo isset($errors['slots']) ? ' field--error' : ''; ?>">
            <legend class="field__label">Time slots</legend>
            <?php foreach (SLOT_OPTIONS as $s): ?>
                <label class="toggle"><input type="checkbox" name="slots[]" value="<?php echo $s; ?>"<?php echo in_array($s, $form['slots'], true) ? ' checked' : ''; ?>><span><?php echo $s; ?></span></label>
            <?php endforeach; ?>
            <?php echo f_error($errors, 'slots'); ?>
        </fieldset>

        <div class="panel__foot"><button type="submit" class="btn btn--glow"><?php echo $isNew ? 'Add doctor' : 'Save changes'; ?></button></div>
    </form>
<?php endif; ?>

<?php echo toolbar('doctors', $q, 'Search by name, specialty or username', 'Add doctor'); ?>
<div class="table-wrap glass">
    <table class="table">
        <thead><tr><th>Doctor</th><th>City</th><th>Username</th><th>Fee</th><th>Bookings</th><th class="table__actions">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($doctors as $d): ?>
            <tr>
                <td>
                    <div class="who">
                        <span class="avatar avatar--sm" style="--hue: <?php echo avatar_hue((int) $d['id']); ?>" aria-hidden="true"><?php echo h(initials($d['name'])); ?></span>
                        <div><b><?php echo h(doctor_name($d['name'])); ?></b><br><span class="muted"><?php echo h($d['specialty']); ?>, <?php echo (int) $d['experience_years']; ?> yrs</span></div>
                    </div>
                </td>
                <td><?php echo h($d['city_name']); ?></td>
                <td><code><?php echo h($d['username']); ?></code></td>
                <td>Rs <?php echo number_format((float) $d['consultation_fee']); ?></td>
                <td><?php echo (int) $d['appt_count']; ?></td>
                <td class="table__actions">
                    <a class="btn btn--ghost btn--xs" href="admin_dashboard.php?view=doctors&amp;edit=<?php echo (int) $d['id']; ?>">Edit</a>
                    <?php echo post_button('doctor_delete', (int) $d['id'], 'Delete', 'btn--danger',
                        'Delete ' . doctor_name($d['name']) . '? Their login and ' . (int) $d['appt_count'] . ' appointment(s) will be removed for good.'); ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$doctors): ?><tr><td colspan="6" class="table__empty"><?php echo $q !== '' ? 'No doctor matches that search.' : 'No doctors yet. Add the first one.'; ?></td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php elseif ($view === 'patients'): ?>
<!-- ======================= PATIENTS ======================= -->
<?php if ($form): ?>
    <form class="panel glass enter" method="POST" action="admin_dashboard.php?view=patients" data-loading novalidate>
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="patient_save">
        <input type="hidden" name="id" value="<?php echo $edit; ?>">
        <div class="panel-head panel-head--flush">
            <h2>Edit <?php echo h($form['name']); ?></h2>
            <a class="btn btn--ghost btn--sm" href="admin_dashboard.php?view=patients">Close</a>
        </div>
        <div class="form-grid form-grid--3">
            <?php echo f_input($errors, 'name', 'Full name', $form['name']); ?>
            <?php echo f_input($errors, 'email', 'Email', $form['email'], 'email'); ?>
            <?php echo f_input($errors, 'phone', 'Mobile number', $form['phone'], 'tel'); ?>
            <?php echo f_input($errors, 'address', 'Address', $form['address'], 'text', '', 'span-2'); ?>
            <?php echo f_input($errors, 'password', 'New password (leave empty to keep)', '', 'password', 'autocomplete="new-password"'); ?>
        </div>
        <div class="panel__foot"><button type="submit" class="btn btn--glow">Save changes</button></div>
    </form>
<?php endif; ?>

<?php echo toolbar('patients', $q, 'Search by name, email or phone'); ?>
<div class="table-wrap glass">
    <table class="table">
        <thead><tr><th>Patient</th><th>Contact</th><th>Username</th><th>Joined</th><th>Bookings</th><th class="table__actions">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($patients as $p): ?>
            <tr>
                <td>
                    <div class="who">
                        <span class="avatar avatar--sm" style="--hue: <?php echo avatar_hue((int) $p['id'] + 3); ?>" aria-hidden="true"><?php echo h(initials($p['name'])); ?></span>
                        <div><b><?php echo h($p['name']); ?></b><br><span class="muted"><?php echo h($p['address']); ?></span></div>
                    </div>
                </td>
                <td><?php echo h($p['email']); ?><br><span class="muted"><?php echo h($p['phone']); ?></span></td>
                <td><code><?php echo h($p['username']); ?></code></td>
                <td><?php echo h(date('j M Y', strtotime($p['registered_date']))); ?></td>
                <td><?php echo (int) $p['appt_count']; ?></td>
                <td class="table__actions">
                    <a class="btn btn--ghost btn--xs" href="admin_dashboard.php?view=patients&amp;edit=<?php echo (int) $p['id']; ?>">Edit</a>
                    <?php echo post_button('patient_delete', (int) $p['id'], 'Delete', 'btn--danger',
                        'Delete ' . $p['name'] . '? Their account and ' . (int) $p['appt_count'] . ' appointment(s) will be removed for good.'); ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$patients): ?><tr><td colspan="6" class="table__empty"><?php echo $q !== '' ? 'No patient matches that search.' : 'No patients have signed up yet.'; ?></td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php elseif ($view === 'cities'): ?>
<!-- ======================= CITIES ======================= -->
<form class="panel glass inline-form" method="POST" action="admin_dashboard.php?view=cities" novalidate>
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="city_save">
    <input type="hidden" name="id" value="<?php echo $cityForm ? (int) $cityForm['id'] : 0; ?>">
    <?php echo f_input($errors, 'name', $cityForm ? 'Rename ' . $cityForm['name'] : 'Add a city', $cityForm['name'] ?? ($old['name'] ?? ''), 'text', 'placeholder="e.g. Faisalabad" required'); ?>
    <button type="submit" class="btn btn--glow"><?php echo $cityForm ? 'Save name' : 'Add city'; ?></button>
    <?php if ($cityForm): ?><a class="btn btn--ghost" href="admin_dashboard.php?view=cities">Cancel</a><?php endif; ?>
</form>

<div class="city-grid">
    <?php foreach ($cities as $c): ?>
        <article class="city glass" data-tilt="8" data-reveal>
            <i class="fa-solid fa-city city__icon" aria-hidden="true" data-depth></i>
            <h3><?php echo h($c['name']); ?></h3>
            <p class="muted"><?php echo (int) $c['doctors']; ?> doctor<?php echo $c['doctors'] == 1 ? '' : 's'; ?></p>
            <div class="city__actions">
                <a class="btn btn--ghost btn--xs" href="admin_dashboard.php?view=cities&amp;edit=<?php echo (int) $c['id']; ?>">Rename</a>
                <?php if ((int) $c['doctors'] === 0): ?>
                    <?php echo post_button('city_delete', (int) $c['id'], 'Delete', 'btn--danger', 'Delete ' . $c['name'] . '?'); ?>
                <?php else: ?>
                    <span class="lock" title="Move or delete its doctors first"><i class="fa-solid fa-lock" aria-hidden="true"></i> In use</span>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</div>

<?php elseif ($view === 'diseases'): ?>
<!-- ======================= HEALTH GUIDE ======================= -->
<?php if ($form): ?>
    <form class="panel glass enter" method="POST" action="admin_dashboard.php?view=diseases" data-loading novalidate>
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="disease_save">
        <input type="hidden" name="id" value="<?php echo $edit; ?>">
        <div class="panel-head panel-head--flush">
            <h2><?php echo $edit ? 'Edit ' . h($form['name']) : 'Add a condition'; ?></h2>
            <a class="btn btn--ghost btn--sm" href="admin_dashboard.php?view=diseases">Close</a>
        </div>
        <div class="form-grid form-grid--3">
            <?php echo f_input($errors, 'name', 'Condition name', $form['name'], 'text', '', 'span-2'); ?>
            <?php echo f_input($errors, 'specialty', 'Specialist to see (for the symptom checker)', $form['specialty'] ?? '', 'text', 'placeholder="e.g. Cardiologist"'); ?>
            <?php echo f_textarea($errors, 'description', 'Short description', $form['description'], 2); ?>
            <?php echo f_textarea($errors, 'symptoms', 'Symptoms', $form['symptoms'], 3, '', 'Separate items with commas.'); ?>
            <?php echo f_textarea($errors, 'preventions', 'Prevention', $form['preventions'], 3, '', 'Separate items with commas.'); ?>
            <?php echo f_textarea($errors, 'cures', 'Treatment', $form['cures'], 3, '', 'Separate items with commas.'); ?>
        </div>
        <div class="panel__foot"><button type="submit" class="btn btn--glow"><?php echo $edit ? 'Save changes' : 'Add to health guide'; ?></button></div>
    </form>
<?php endif; ?>

<div class="toolbar"><span class="results-count"><?php echo count($diseases); ?> conditions in the public health guide</span>
    <a class="btn btn--glow btn--sm" href="admin_dashboard.php?view=diseases&amp;add=1"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add condition</a></div>
<div class="table-wrap glass">
    <table class="table">
        <thead><tr><th>Condition</th><th>Specialist</th><th>Description</th><th class="table__actions">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($diseases as $d): ?>
            <tr>
                <td><b><?php echo h($d['name']); ?></b></td>
                <td><?php echo h($d['specialty'] ?? '') ?: '<span class="muted">Not set</span>'; ?></td>
                <td class="muted"><?php echo h($d['description']); ?></td>
                <td class="table__actions">
                    <a class="btn btn--ghost btn--xs" href="admin_dashboard.php?view=diseases&amp;edit=<?php echo (int) $d['id']; ?>">Edit</a>
                    <?php echo post_button('disease_delete', (int) $d['id'], 'Delete', 'btn--danger', 'Delete ' . $d['name'] . ' from the health guide?'); ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$diseases): ?><tr><td colspan="4" class="table__empty">The health guide is empty.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php elseif ($view === 'news'): ?>
<!-- ======================= RESEARCH ARTICLES ======================= -->
<?php if ($form): ?>
    <form class="panel glass enter" method="POST" action="admin_dashboard.php?view=news" data-loading novalidate>
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="news_save">
        <input type="hidden" name="id" value="<?php echo $edit; ?>">
        <div class="panel-head panel-head--flush">
            <h2><?php echo $edit ? 'Edit article' : 'Write an article'; ?></h2>
            <a class="btn btn--ghost btn--sm" href="admin_dashboard.php?view=news">Close</a>
        </div>
        <div class="form-grid form-grid--3">
            <?php echo f_input($errors, 'title', 'Title', $form['title'], 'text', '', 'span-3'); ?>
            <?php echo f_select($errors, 'category', 'Category', ['News' => 'News', 'Invention' => 'Invention', 'Research' => 'Research'], $form['category']); ?>
            <?php echo f_input($errors, 'published_date', 'Published on', $form['published_date'], 'date'); ?>
            <?php echo f_input($errors, 'author', 'Author', $form['author']); ?>
            <?php echo f_textarea($errors, 'summary', 'Summary (shown on cards)', $form['summary'], 2); ?>
            <?php echo f_textarea($errors, 'content', 'Full article', $form['content'], 8, 'span-3', 'Leave an empty line between paragraphs.'); ?>
        </div>
        <div class="panel__foot"><button type="submit" class="btn btn--glow"><?php echo $edit ? 'Save changes' : 'Publish'; ?></button></div>
    </form>
<?php endif; ?>

<div class="toolbar"><span class="results-count"><?php echo count($articles); ?> published articles</span>
    <a class="btn btn--glow btn--sm" href="admin_dashboard.php?view=news&amp;add=1"><i class="fa-solid fa-plus" aria-hidden="true"></i> Write article</a></div>
<div class="table-wrap glass">
    <table class="table">
        <thead><tr><th>Title</th><th>Category</th><th>Published</th><th class="table__actions">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($articles as $n): ?>
            <tr>
                <td><b><?php echo h($n['title']); ?></b><br><span class="muted"><?php echo h($n['author']); ?></span></td>
                <td><span class="tag tag--<?php echo h(strtolower($n['category'])); ?>"><?php echo h($n['category']); ?></span></td>
                <td><?php echo h(date('j M Y', strtotime($n['published_date']))); ?></td>
                <td class="table__actions">
                    <a class="btn btn--ghost btn--xs" href="news.php?id=<?php echo (int) $n['id']; ?>" target="_blank" rel="noopener">View</a>
                    <a class="btn btn--ghost btn--xs" href="admin_dashboard.php?view=news&amp;edit=<?php echo (int) $n['id']; ?>">Edit</a>
                    <?php echo post_button('news_delete', (int) $n['id'], 'Delete', 'btn--danger', 'Delete "' . $n['title'] . '"?'); ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$articles): ?><tr><td colspan="4" class="table__empty">No articles yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
