<?php
// includes/dash_header.php - Sidebar layout shared by every dashboard page.
// Before including, a page sets:
//   $page_title  (string) browser tab text
//   $dash_title  (string) big heading at the top of the content area
//   $dash_active (string) key of the highlighted menu item
//   $dash_sub    (string, optional) small line under the heading
require_once __DIR__ . '/components.php';

$role = $_SESSION['role'] ?? '';
$dash_sub = $dash_sub ?? '';

// Menu per role: [key, link, icon, label]
$menus = [
    'patient' => [
        ['home',    'patient_dashboard.php',              'fa-house-medical',   'Overview'],
        ['book',    'patient_dashboard.php?view=book',    'fa-calendar-plus',   'Book appointment'],
        ['history', 'patient_dashboard.php?view=history', 'fa-clock-rotate-left','My appointments'],
        ['account', 'account.php',                        'fa-user-gear',       'Account'],
    ],
    'doctor' => [
        ['home',         'doctor_dashboard.php',                   'fa-house-medical',  'Today'],
        ['appointments', 'doctor_dashboard.php?view=appointments', 'fa-calendar-check', 'Appointments'],
        ['clinic',       'doctor_dashboard.php?view=clinic',       'fa-stethoscope',    'Clinic profile'],
        ['account',      'account.php',                            'fa-user-gear',      'Account'],
    ],
    'admin' => [
        ['overview',     'admin_dashboard.php',                   'fa-chart-line',     'Overview'],
        ['appointments', 'admin_dashboard.php?view=appointments', 'fa-calendar-check', 'Appointments'],
        ['doctors',      'admin_dashboard.php?view=doctors',      'fa-user-doctor',    'Doctors'],
        ['patients',     'admin_dashboard.php?view=patients',     'fa-users',          'Patients'],
        ['cities',       'admin_dashboard.php?view=cities',       'fa-city',           'Cities'],
        ['diseases',     'admin_dashboard.php?view=diseases',     'fa-book-medical',   'Health guide'],
        ['news',         'admin_dashboard.php?view=news',         'fa-newspaper',      'Research'],
        ['account',      'account.php',                           'fa-user-gear',      'Account'],
    ],
];
$menu = $menus[$role] ?? [];
$roleLabel = ['patient' => 'Patient', 'doctor' => 'Doctor', 'admin' => 'Administrator'][$role] ?? '';
// Show the person's real name in the sidebar (looked up once, then kept in the session)
if (empty($_SESSION['display_name']) && isset($pdo)) {
    $table = ['patient' => 'patients', 'doctor' => 'doctors'][$role] ?? null;
    if ($table) {
        $stmt = $pdo->prepare("SELECT name FROM $table WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $name = $stmt->fetchColumn();
        $_SESSION['display_name'] = $name ? ($role === 'doctor' ? doctor_name($name) : $name) : $_SESSION['username'];
    } else {
        $_SESSION['display_name'] = 'Administrator';
    }
}
$displayName = $_SESSION['display_name'] ?? $_SESSION['username'] ?? '';

$body_class = trim(($body_class ?? '') . ' page-dash' . ($role === 'admin' ? ' theme-admin' : ''));
$show_nav = false;
include __DIR__ . '/header.php';
?>
<link rel="stylesheet" href="<?php echo h(url('assets/css/dashboard.css')); ?>">
<div class="dash">
    <aside class="side glass" aria-label="Dashboard menu">
        <a class="brand" href="<?php echo h(url('index.php')); ?>">
            <svg class="brand__mark" viewBox="0 0 40 40" aria-hidden="true">
                <rect x="1" y="1" width="38" height="38" rx="12"/>
                <path d="M6 21 H13 L16 14 L20 28 L24 10 L27 21 H34"/>
            </svg>
            <span class="brand__text">CARE Group<small><?php echo h($roleLabel); ?> portal</small></span>
        </a>

        <div class="side__user">
            <span class="avatar" style="--hue: <?php echo $role === 'admin' ? 262 : ($role === 'doctor' ? 200 : 168); ?>" aria-hidden="true"><?php echo h(initials($displayName)); ?></span>
            <div>
                <b><?php echo h($displayName); ?></b>
                <span><?php echo h($roleLabel); ?></span>
            </div>
        </div>

        <nav class="side__menu">
            <?php foreach ($menu as [$key, $href, $icon, $label]): ?>
                <a href="<?php echo h(url($href)); ?>"<?php echo $key === $dash_active ? ' aria-current="page"' : ''; ?>>
                    <i class="fa-solid <?php echo $icon; ?>" aria-hidden="true"></i><span><?php echo h($label); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="side__foot">
            <a href="<?php echo h(url('index.php')); ?>"><i class="fa-solid fa-globe" aria-hidden="true"></i><span>View website</span></a>
            <a href="<?php echo h(url('logout.php')); ?>"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i><span>Log out</span></a>
        </div>
    </aside>

    <section class="dash__main">
        <header class="dash__head enter">
            <div>
                <h1><?php echo h($dash_title ?? ''); ?></h1>
                <?php if ($dash_sub !== ''): ?><p><?php echo h($dash_sub); ?></p><?php endif; ?>
            </div>
            <p class="dash__date"><i class="fa-regular fa-calendar" aria-hidden="true"></i> <?php echo date('l, j F Y'); ?></p>
        </header>