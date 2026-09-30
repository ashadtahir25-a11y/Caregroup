<?php
// admin_dashboard.php - Admin console: overview charts, appointments and all records.
// Actions are handled here; the HTML for each section is in includes/admin_views.php
require_once 'db.php';
require_once 'includes/booking.php';
checkRole('admin');

$views = ['overview', 'appointments', 'doctors', 'patients', 'cities', 'diseases', 'news'];
$view  = $_GET['view'] ?? 'overview';
if (!in_array($view, $views, true)) {
    $view = 'overview';
}

/* ---------------------------------------------------------------------
 * Helpers for form handling
 * A failed form sends the admin back with the errors and typed values,
 * so refreshing the page never re-submits anything.
 * --------------------------------------------------------------------- */
function back_with_errors(string $url, array $errors, array $old): void
{
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_old'] = $old;
    flash('error', $errors['_'] ?? 'Fix the highlighted fields and try again.');
    redirect($url);
}

function email_in_use(PDO $pdo, string $email, int $exceptUserId = 0): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?');
    $stmt->execute([$email, $exceptUserId]);
    return (bool) $stmt->fetchColumn();
}

function valid_password(string $pw): bool
{
    return strlen($pw) >= 8 && preg_match('/[A-Za-z]/', $pw) && preg_match('/\d/', $pw);
}

/* =====================================================================
 * POST actions
 * ===================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        flash('error', 'Your session expired. Try again.');
        redirect('admin_dashboard.php?view=' . $view);
    }
    $action = $_POST['action'] ?? '';
    $id     = (int) ($_POST['id'] ?? 0);

    try {
        switch ($action) {

        /* ---------------- Appointments ---------------- */
        case 'appt_status':
            $to = $_POST['to'] ?? '';
            $return = $_POST['return'] ?? 'admin_dashboard.php?view=appointments';
            if (!preg_match('/^admin_dashboard\.php(\?[A-Za-z0-9_=&%\-]*)?$/', $return)) {
                $return = 'admin_dashboard.php?view=appointments';
            }
            $stmt = $pdo->prepare('SELECT status, appointment_date FROM appointments WHERE id = ?');
            $stmt->execute([$id]);
            $app = $stmt->fetch();
            if (!$app || !can_change_status('admin', $app['status'], $to)) {
                flash('error', 'That change is not allowed for this appointment.');
            } elseif ($to === 'Completed' && $app['appointment_date'] > date('Y-m-d')) {
                flash('error', 'A visit can be marked completed on or after its date.');
            } else {
                $pdo->prepare('UPDATE appointments SET status = ? WHERE id = ?')->execute([$to, $id]);
                flash('success', 'Appointment marked as ' . strtolower($to) . '.');
            }
            redirect($return);

        /* ---------------- Doctors ---------------- */
        case 'doctor_save':
            $isNew = $id === 0;
            $back  = 'admin_dashboard.php?view=doctors' . ($isNew ? '&add=1' : '&edit=' . $id);
            $d = [
                'username'         => trim($_POST['username'] ?? ''),
                'password'         => $_POST['password'] ?? '',
                'name'             => trim(preg_replace('/^dr\.?\s+/i', '', trim($_POST['name'] ?? ''))),
                'specialty'        => trim($_POST['specialty'] ?? ''),
                'city_id'          => (int) ($_POST['city_id'] ?? 0),
                'phone'            => preg_replace('/[\s\-()]/', '', $_POST['phone'] ?? ''),
                'email'            => trim($_POST['email'] ?? ''),
                'address'          => trim($_POST['address'] ?? ''),
                'bio'              => trim($_POST['bio'] ?? ''),
                'experience_years' => trim($_POST['experience_years'] ?? ''),
                'consultation_fee' => trim($_POST['consultation_fee'] ?? ''),
                'days'             => array_values(array_intersect(WEEK_DAYS, (array) ($_POST['days'] ?? []))),
                'slots'            => sort_slots(array_values(array_intersect(SLOT_OPTIONS, (array) ($_POST['slots'] ?? [])))),
            ];
            $userId = 0;
            if (!$isNew) {
                $stmt = $pdo->prepare('SELECT user_id FROM doctors WHERE id = ?');
                $stmt->execute([$id]);
                $userId = (int) $stmt->fetchColumn();
                if (!$userId) {
                    flash('error', 'That doctor no longer exists.');
                    redirect('admin_dashboard.php?view=doctors');
                }
            }

            $e = [];
            if ($isNew && !preg_match('/^[A-Za-z0-9_]{4,30}$/', $d['username'])) $e['username'] = 'Use 4 to 30 letters, numbers or underscores.';
            if ($isNew && !isset($e['username'])) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
                $stmt->execute([$d['username']]);
                if ($stmt->fetchColumn()) $e['username'] = 'This username is taken.';
            }
            if (($isNew || $d['password'] !== '') && !valid_password($d['password'])) $e['password'] = 'Use at least 8 characters with a letter and a number.';
            if (text_length($d['name']) < 3 || text_length($d['name']) > 100) $e['name'] = 'Enter the doctor\'s full name (without "Dr").';
            if (text_length($d['specialty']) < 3) $e['specialty'] = 'Enter a specialty, like Cardiologist.';
            if (!preg_match('/^\+?\d{10,13}$/', $d['phone'])) $e['phone'] = 'Enter a valid phone number.';
            if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $e['email'] = 'Enter a valid email address.';
            elseif (email_in_use($pdo, $d['email'], $userId)) $e['email'] = 'Another account already uses this email.';
            if (text_length($d['address']) < 5) $e['address'] = 'Enter the clinic address.';
            if (!ctype_digit($d['experience_years']) || (int) $d['experience_years'] > 60) $e['experience_years'] = 'Enter years between 0 and 60.';
            if (!is_numeric($d['consultation_fee']) || $d['consultation_fee'] < 0) $e['consultation_fee'] = 'Enter a fee in rupees.';
            if (!$d['days']) $e['days'] = 'Choose at least one clinic day.';
            if (!$d['slots']) $e['slots'] = 'Choose at least one time slot.';
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM cities WHERE id = ?');
            $stmt->execute([$d['city_id']]);
            if (!$stmt->fetchColumn()) $e['city_id'] = 'Choose a city.';

            if ($e) {
                unset($d['password']);
                back_with_errors($back, $e, $d);
            }

            $pdo->beginTransaction();
            $cols = [$d['name'], $d['specialty'], $d['city_id'], $d['address'], $d['phone'], $d['email'], $d['bio'],
                     (int) $d['experience_years'], (float) $d['consultation_fee'], implode(',', $d['days']), implode(',', $d['slots'])];
            if ($isNew) {
                $pdo->prepare("INSERT INTO users (username, password, role, email) VALUES (?, ?, 'doctor', ?)")
                    ->execute([$d['username'], password_hash($d['password'], PASSWORD_DEFAULT), $d['email']]);
                $newUser = (int) $pdo->lastInsertId();
                $pdo->prepare('INSERT INTO doctors (name, specialty, city_id, address, phone, email, bio, experience_years, consultation_fee, available_days, available_slots, user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute(array_merge($cols, [$newUser]));
            } else {
                $pdo->prepare('UPDATE doctors SET name=?, specialty=?, city_id=?, address=?, phone=?, email=?, bio=?, experience_years=?, consultation_fee=?, available_days=?, available_slots=? WHERE id=?')
                    ->execute(array_merge($cols, [$id]));
                $pdo->prepare('UPDATE users SET email = ? WHERE id = ?')->execute([$d['email'], $userId]);
                if ($d['password'] !== '') {
                    $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($d['password'], PASSWORD_DEFAULT), $userId]);
                }
            }
            $pdo->commit();
            flash('success', doctor_name($d['name']) . ($isNew ? ' was added. They can log in with username "' . $d['username'] . '".' : ' was updated.'));
            redirect('admin_dashboard.php?view=doctors');

        case 'doctor_delete':
            $stmt = $pdo->prepare('SELECT user_id, name FROM doctors WHERE id = ?');
            $stmt->execute([$id]);
            if ($doc = $stmt->fetch()) {
                // Deleting the login also deletes the profile and its appointments (ON DELETE CASCADE)
                $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$doc['user_id']]);
                flash('success', doctor_name($doc['name']) . ' and their appointments were deleted.');
            }
            redirect('admin_dashboard.php?view=doctors');

        /* ---------------- Patients ---------------- */
        case 'patient_save':
            $back = 'admin_dashboard.php?view=patients&edit=' . $id;
            $stmt = $pdo->prepare('SELECT user_id FROM patients WHERE id = ?');
            $stmt->execute([$id]);
            $userId = (int) $stmt->fetchColumn();
            if (!$userId) {
                flash('error', 'That patient no longer exists.');
                redirect('admin_dashboard.php?view=patients');
            }
            $p = [
                'name'     => trim($_POST['name'] ?? ''),
                'email'    => trim($_POST['email'] ?? ''),
                'phone'    => preg_replace('/[\s\-()]/', '', $_POST['phone'] ?? ''),
                'address'  => trim($_POST['address'] ?? ''),
                'password' => $_POST['password'] ?? '',
            ];
            $e = [];
            if (text_length($p['name']) < 3 || text_length($p['name']) > 100) $e['name'] = 'Enter the full name.';
            if (!filter_var($p['email'], FILTER_VALIDATE_EMAIL)) $e['email'] = 'Enter a valid email address.';
            elseif (email_in_use($pdo, $p['email'], $userId)) $e['email'] = 'Another account already uses this email.';
            if (!preg_match('/^\+?\d{10,13}$/', $p['phone'])) $e['phone'] = 'Enter a valid phone number.';
            if (text_length($p['address']) < 5) $e['address'] = 'Enter the address.';
            if ($p['password'] !== '' && !valid_password($p['password'])) $e['password'] = 'Use at least 8 characters with a letter and a number.';
            if ($e) {
                unset($p['password']);
                back_with_errors($back, $e, $p);
            }
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE patients SET name = ?, email = ?, phone = ?, address = ? WHERE id = ?')->execute([$p['name'], $p['email'], $p['phone'], $p['address'], $id]);
            $pdo->prepare('UPDATE users SET email = ? WHERE id = ?')->execute([$p['email'], $userId]);
            if ($p['password'] !== '') {
                $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($p['password'], PASSWORD_DEFAULT), $userId]);
            }
            $pdo->commit();
            flash('success', $p['name'] . ' was updated.');
            redirect('admin_dashboard.php?view=patients');

        case 'patient_delete':
            $stmt = $pdo->prepare('SELECT user_id, name FROM patients WHERE id = ?');
            $stmt->execute([$id]);
            if ($pat = $stmt->fetch()) {
                $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$pat['user_id']]);
                flash('success', $pat['name'] . ' and their appointments were deleted.');
            }
            redirect('admin_dashboard.php?view=patients');

        /* ---------------- Cities ---------------- */
        case 'city_save':
            $name = trim(preg_replace('/\s+/', ' ', $_POST['name'] ?? ''));
            if (text_length($name) < 2 || text_length($name) > 100 || !preg_match("/^[\p{L} .'-]+$/u", $name)) {
                back_with_errors('admin_dashboard.php?view=cities' . ($id ? '&edit=' . $id : ''), ['name' => 'Enter a city name using letters only.'], ['name' => $name]);
            }
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM cities WHERE name = ? AND id <> ?');
            $stmt->execute([$name, $id]);
            if ($stmt->fetchColumn()) {
                back_with_errors('admin_dashboard.php?view=cities' . ($id ? '&edit=' . $id : ''), ['name' => $name . ' is already listed.'], ['name' => $name]);
            }
            if ($id) {
                $pdo->prepare('UPDATE cities SET name = ? WHERE id = ?')->execute([$name, $id]);
                flash('success', 'City renamed to ' . $name . '.');
            } else {
                $pdo->prepare('INSERT INTO cities (name) VALUES (?)')->execute([$name]);
                flash('success', $name . ' was added.');
            }
            redirect('admin_dashboard.php?view=cities');

        case 'city_delete':
            $stmt = $pdo->prepare('SELECT c.name, COUNT(d.id) AS doctors FROM cities c LEFT JOIN doctors d ON d.city_id = c.id WHERE c.id = ? GROUP BY c.id');
            $stmt->execute([$id]);
            $city = $stmt->fetch();
            if ($city && $city['doctors'] > 0) {
                flash('error', $city['name'] . ' still has ' . $city['doctors'] . ' doctor(s). Move or delete them first.');
            } elseif ($city) {
                $pdo->prepare('DELETE FROM cities WHERE id = ?')->execute([$id]);
                flash('success', $city['name'] . ' was deleted.');
            }
            redirect('admin_dashboard.php?view=cities');

        /* ---------------- Health guide ---------------- */
        case 'disease_save':
            $x = [];
            foreach (['name', 'description', 'symptoms', 'preventions', 'cures'] as $k) {
                $x[$k] = trim($_POST[$k] ?? '');
            }
            $e = [];
            if (text_length($x['name']) < 3 || text_length($x['name']) > 150) $e['name'] = 'Enter the condition name.';
            foreach (['description' => 'a short description', 'symptoms' => 'the symptoms', 'preventions' => 'prevention tips', 'cures' => 'the treatment'] as $k => $label) {
                if (text_length($x[$k]) < 5) $e[$k] = 'Enter ' . $label . '.';
            }
            if ($e) {
                back_with_errors('admin_dashboard.php?view=diseases' . ($id ? '&edit=' . $id : '&add=1'), $e, $x);
            }
            if ($id) {
                $pdo->prepare('UPDATE diseases SET name=?, description=?, symptoms=?, preventions=?, cures=? WHERE id=?')->execute(array_merge(array_values($x), [$id]));
            } else {
                $pdo->prepare('INSERT INTO diseases (name, description, symptoms, preventions, cures) VALUES (?,?,?,?,?)')->execute(array_values($x));
            }
            flash('success', $x['name'] . ($id ? ' was updated.' : ' was added to the health guide.'));
            redirect('admin_dashboard.php?view=diseases');

        case 'disease_delete':
            $pdo->prepare('DELETE FROM diseases WHERE id = ?')->execute([$id]);
            flash('success', 'Condition deleted from the health guide.');
            redirect('admin_dashboard.php?view=diseases');

        /* ---------------- Research articles ---------------- */
        case 'news_save':
            $x = [];
            foreach (['title', 'summary', 'content', 'category', 'published_date', 'author'] as $k) {
                $x[$k] = trim($_POST[$k] ?? '');
            }
            $e = [];
            if (text_length($x['title']) < 5 || text_length($x['title']) > 255) $e['title'] = 'Enter a title (5 to 255 characters).';
            if (text_length($x['summary']) < 10) $e['summary'] = 'Enter a one or two sentence summary.';
            if (text_length($x['content']) < 20) $e['content'] = 'Enter the article text.';
            if (!in_array($x['category'], ['News', 'Invention', 'Research'], true)) $e['category'] = 'Choose a category.';
            $dt = DateTime::createFromFormat('!Y-m-d', $x['published_date']);
            if (!$dt || $dt->format('Y-m-d') !== $x['published_date']) $e['published_date'] = 'Choose a date.';
            if ($x['author'] === '') $x['author'] = 'CARE Group Editor';
            if ($e) {
                back_with_errors('admin_dashboard.php?view=news' . ($id ? '&edit=' . $id : '&add=1'), $e, $x);
            }
            if ($id) {
                $pdo->prepare('UPDATE medical_news SET title=?, summary=?, content=?, category=?, published_date=?, author=? WHERE id=?')->execute(array_merge(array_values($x), [$id]));
            } else {
                $pdo->prepare('INSERT INTO medical_news (title, summary, content, category, published_date, author) VALUES (?,?,?,?,?,?)')->execute(array_values($x));
            }
            flash('success', $id ? 'Article updated.' : 'Article published.');
            redirect('admin_dashboard.php?view=news');

        case 'news_delete':
            $pdo->prepare('DELETE FROM medical_news WHERE id = ?')->execute([$id]);
            flash('success', 'Article deleted.');
            redirect('admin_dashboard.php?view=news');
        }
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Admin action "' . $action . '" failed: ' . $ex->getMessage());
        flash('error', 'That change could not be saved. Try again in a minute.');
    }
    redirect('admin_dashboard.php?view=' . $view);
}

/* =====================================================================
 * Data for the current view
 * ===================================================================== */
$errors = $_SESSION['form_errors'] ?? [];
$old    = $_SESSION['form_old'] ?? null;
unset($_SESSION['form_errors'], $_SESSION['form_old']);

$cities = $pdo->query('SELECT c.id, c.name, COUNT(d.id) AS doctors FROM cities c LEFT JOIN doctors d ON d.city_id = c.id GROUP BY c.id ORDER BY c.name')->fetchAll();
$edit   = (int) ($_GET['edit'] ?? 0);
$adding = isset($_GET['add']);
$q      = trim($_GET['q'] ?? '');

switch ($view) {
    case 'overview':
        $kpi = [
            'doctors'  => (int) $pdo->query('SELECT COUNT(*) FROM doctors')->fetchColumn(),
            'patients' => (int) $pdo->query('SELECT COUNT(*) FROM patients')->fetchColumn(),
            'total'    => (int) $pdo->query('SELECT COUNT(*) FROM appointments')->fetchColumn(),
            'pending'  => (int) $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'Pending'")->fetchColumn(),
            'today'    => (int) $pdo->query("SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE() AND status <> 'Cancelled'")->fetchColumn(),
            'new_pat'  => (int) $pdo->query("SELECT COUNT(*) FROM patients WHERE registered_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetchColumn(),
        ];

        // Appointments per month: previous 5 months + this month
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = (new DateTime('first day of this month'))->modify("-$i month");
            $months[$m->format('Y-m')] = ['label' => $m->format('M'), 'n' => 0];
        }
        $rows = $pdo->query("SELECT DATE_FORMAT(appointment_date, '%Y-%m') AS ym, COUNT(*) AS n FROM appointments
                              WHERE appointment_date >= DATE_FORMAT(CURDATE() - INTERVAL 5 MONTH, '%Y-%m-01')
                                AND appointment_date < DATE_FORMAT(CURDATE() + INTERVAL 1 MONTH, '%Y-%m-01')
                              GROUP BY ym")->fetchAll();
        foreach ($rows as $r) {
            if (isset($months[$r['ym']])) $months[$r['ym']]['n'] = (int) $r['n'];
        }
        $monthMax = max(1, max(array_column($months, 'n')));

        $statusCounts = array_column($pdo->query('SELECT status, COUNT(*) AS n FROM appointments GROUP BY status')->fetchAll(), 'n', 'status');
        $specialties  = $pdo->query('SELECT specialty, COUNT(*) AS n FROM doctors GROUP BY specialty ORDER BY n DESC LIMIT 6')->fetchAll();

        $recent = $pdo->query('SELECT a.*, p.name AS patient_name, d.name AS doctor_name FROM appointments a
                                 JOIN patients p ON p.id = a.patient_id JOIN doctors d ON d.id = a.doctor_id
                                ORDER BY a.created_at DESC LIMIT 6')->fetchAll();
        break;

    case 'appointments':
        $tab   = $_GET['tab'] ?? 'all';
        $tabs  = ['all' => 'All', 'Pending' => 'Pending', 'Confirmed' => 'Confirmed', 'Completed' => 'Completed', 'Cancelled' => 'Cancelled'];
        if (!isset($tabs[$tab])) $tab = 'all';
        $fDoc  = (int) ($_GET['doctor'] ?? 0);
        $fFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : '';
        $fTo   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : '';

        $where = ' WHERE 1 = 1';
        $params = [];
        if ($tab !== 'all') { $where .= ' AND a.status = ?'; $params[] = $tab; }
        if ($fDoc)          { $where .= ' AND a.doctor_id = ?'; $params[] = $fDoc; }
        if ($fFrom)         { $where .= ' AND a.appointment_date >= ?'; $params[] = $fFrom; }
        if ($fTo)           { $where .= ' AND a.appointment_date <= ?'; $params[] = $fTo; }
        if ($q !== '')      { $where .= ' AND p.name LIKE ?'; $params[] = '%' . $q . '%'; }

        $from = ' FROM appointments a JOIN patients p ON p.id = a.patient_id JOIN doctors d ON d.id = a.doctor_id';
        $stmt = $pdo->prepare('SELECT COUNT(*)' . $from . $where);
        $stmt->execute($params);
        $totalRows = (int) $stmt->fetchColumn();

        $perPage = 15;
        $pages   = max(1, (int) ceil($totalRows / $perPage));
        $page    = min($pages, max(1, (int) ($_GET['page'] ?? 1)));
        $offset  = ($page - 1) * $perPage;

        $stmt = $pdo->prepare('SELECT a.*, p.name AS patient_name, p.phone AS patient_phone, d.name AS doctor_name, d.specialty'
            . $from . $where . ' ORDER BY a.appointment_date DESC, ' . SLOT_ORDER_SQL . " LIMIT $perPage OFFSET $offset");
        $stmt->execute($params);
        $appointments = $stmt->fetchAll();
        $doctorList = $pdo->query('SELECT id, name FROM doctors ORDER BY name')->fetchAll();

        // Current filters, used to build links that keep them
        $filterQuery = http_build_query(array_filter(['view' => 'appointments', 'tab' => $tab, 'doctor' => $fDoc ?: null, 'from' => $fFrom ?: null, 'to' => $fTo ?: null, 'q' => $q ?: null]));
        break;

    case 'doctors':
        $sql = 'SELECT d.*, c.name AS city_name, u.username,
                       (SELECT COUNT(*) FROM appointments a WHERE a.doctor_id = d.id) AS appt_count
                  FROM doctors d JOIN cities c ON c.id = d.city_id JOIN users u ON u.id = d.user_id';
        $params = [];
        if ($q !== '') { $sql .= ' WHERE d.name LIKE ? OR d.specialty LIKE ? OR u.username LIKE ?'; $params = array_fill(0, 3, '%' . $q . '%'); }
        $stmt = $pdo->prepare($sql . ' ORDER BY d.name');
        $stmt->execute($params);
        $doctors = $stmt->fetchAll();

        $form = null;
        if ($edit) {
            $stmt = $pdo->prepare('SELECT d.*, u.username FROM doctors d JOIN users u ON u.id = d.user_id WHERE d.id = ?');
            $stmt->execute([$edit]);
            if ($row = $stmt->fetch()) {
                $row['days']  = csv_list($row['available_days']);
                $row['slots'] = csv_list($row['available_slots']);
                $form = $old ? array_merge($row, $old) : $row;
            }
        } elseif ($adding) {
            $form = $old ?: ['username' => '', 'name' => '', 'specialty' => '', 'city_id' => 0, 'phone' => '', 'email' => '', 'address' => '',
                             'bio' => '', 'experience_years' => '', 'consultation_fee' => '',
                             'days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'], 'slots' => ['09:00 AM', '11:00 AM', '02:00 PM', '04:00 PM']];
        }
        break;

    case 'patients':
        $sql = 'SELECT p.*, u.username, (SELECT COUNT(*) FROM appointments a WHERE a.patient_id = p.id) AS appt_count
                  FROM patients p JOIN users u ON u.id = p.user_id';
        $params = [];
        if ($q !== '') { $sql .= ' WHERE p.name LIKE ? OR p.email LIKE ? OR p.phone LIKE ?'; $params = array_fill(0, 3, '%' . $q . '%'); }
        $stmt = $pdo->prepare($sql . ' ORDER BY p.registered_date DESC');
        $stmt->execute($params);
        $patients = $stmt->fetchAll();

        $form = null;
        if ($edit) {
            $stmt = $pdo->prepare('SELECT * FROM patients WHERE id = ?');
            $stmt->execute([$edit]);
            if ($row = $stmt->fetch()) {
                $form = $old ? array_merge($row, $old) : $row;
            }
        }
        break;

    case 'diseases':
        $diseases = $pdo->query('SELECT * FROM diseases ORDER BY name')->fetchAll();
        $form = null;
        if ($edit) {
            $stmt = $pdo->prepare('SELECT * FROM diseases WHERE id = ?');
            $stmt->execute([$edit]);
            if ($row = $stmt->fetch()) $form = $old ? array_merge($row, $old) : $row;
        } elseif ($adding) {
            $form = $old ?: ['name' => '', 'description' => '', 'symptoms' => '', 'preventions' => '', 'cures' => ''];
        }
        break;

    case 'news':
        $articles = $pdo->query('SELECT * FROM medical_news ORDER BY published_date DESC')->fetchAll();
        $form = null;
        if ($edit) {
            $stmt = $pdo->prepare('SELECT * FROM medical_news WHERE id = ?');
            $stmt->execute([$edit]);
            if ($row = $stmt->fetch()) $form = $old ? array_merge($row, $old) : $row;
        } elseif ($adding) {
            $form = $old ?: ['title' => '', 'summary' => '', 'content' => '', 'category' => 'News', 'published_date' => date('Y-m-d'), 'author' => 'CARE Group Editor'];
        }
        break;

    case 'cities':
        $cityForm = null;
        if ($edit) {
            foreach ($cities as $c) {
                if ((int) $c['id'] === $edit) $cityForm = $c;
            }
            if ($cityForm && $old) $cityForm['name'] = $old['name'];
        }
        break;
}

$titles = [
    'overview' => 'Overview', 'appointments' => 'Appointments', 'doctors' => 'Doctors',
    'patients' => 'Patients', 'cities' => 'Cities', 'diseases' => 'Health guide', 'news' => 'Research articles',
];
$page_title  = $titles[$view] . ' | Admin | CARE Group';
$dash_title  = $titles[$view];
$dash_sub    = $view === 'overview' ? 'Everything happening across CARE Group today.' : '';
$dash_active = $view;
include 'includes/dash_header.php';
echo '<link rel="stylesheet" href="' . h(url('assets/css/admin.css')) . '">';
include 'includes/admin_views.php';
include 'includes/dash_footer.php';