<?php
// includes/header.php - Shared <head>, animated background, navigation and toasts.
// Before including, a page may set:
//   $page_title  (string)  text for the browser tab
//   $body_class  (string)  extra classes for <body>
//   $nav_active  (string)  'home' | 'doctors' | 'symptoms' | 'diseases' | 'research'
//   $show_nav    (bool)    false hides the public navigation (used by admin login)
$page_title = $page_title ?? 'CARE Group Medical Services';
$body_class = $body_class ?? '';
$nav_active = $nav_active ?? '';
$show_nav   = $show_nav ?? true;
$toasts     = flash_pull();

function nav_link(string $key, string $href, string $label, string $active): string
{
    $current = $key === $active ? ' aria-current="page"' : '';
    return '<a class="nav__link" href="' . h(url($href)) . '"' . $current . '>' . h($label) . '</a>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#060A16">
    <title><?php echo h($page_title); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&family=Unbounded:wght@500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="<?php echo h(url('assets/css/theme.css')); ?>">
    <link rel="stylesheet" href="<?php echo h(url('assets/css/pages.css')); ?>">
    <link rel="stylesheet" href="<?php echo h(url('assets/css/features.css')); ?>">
</head>
<body class="<?php echo h($body_class); ?> is-entering">

    <!-- Page transition: an ECG trace sweeps across the screen between pages -->
    <div class="transit" aria-hidden="true">
        <svg class="transit__ecg" viewBox="0 0 1200 200" preserveAspectRatio="none">
            <path d="M0 100 H430 L455 100 L472 70 L490 128 L512 18 L540 186 L562 100 L590 100 L606 84 L624 100 H1200"/>
        </svg>
    </div>

    <!-- Ambient background: glowing blobs that drift with the mouse (parallax) -->
    <div class="ambient" aria-hidden="true">
        <span class="ambient__blob ambient__blob--pulse"  data-parallax="40"></span>
        <span class="ambient__blob ambient__blob--plasma" data-parallax="-25"></span>
        <span class="ambient__blob ambient__blob--vital"  data-parallax="18"></span>
        <div class="ambient__grid" data-parallax="8"></div>
    </div>

<?php if ($show_nav): ?>
    <header class="nav" data-nav>
        <div class="nav__inner">
            <a class="brand" href="<?php echo h(url('index.php')); ?>" aria-label="CARE Group home">
                <svg class="brand__mark" viewBox="0 0 40 40" aria-hidden="true">
                    <rect x="1" y="1" width="38" height="38" rx="12"/>
                    <path d="M6 21 H13 L16 14 L20 28 L24 10 L27 21 H34"/>
                </svg>
                <span class="brand__text">CARE Group<small>Medical Services</small></span>
            </a>

            <button class="nav__toggle" type="button" aria-expanded="false" aria-controls="nav-menu" data-nav-toggle>
                <span></span><span></span><span class="sr-only">Menu</span>
            </button>

            <nav class="nav__menu" id="nav-menu" aria-label="Main">
                <?php
                echo nav_link('home', 'index.php', 'Home', $nav_active);
                echo nav_link('doctors', 'doctors.php', 'Find a doctor', $nav_active);
                echo nav_link('symptoms', 'symptoms.php', 'Symptom checker', $nav_active);
                echo nav_link('diseases', 'diseases.php', 'Health guide', $nav_active);
                echo nav_link('research', 'news.php', 'Research', $nav_active);
                ?>
                <div class="nav__actions">
                    <?php if (isLoggedIn()): ?>
                        <a class="btn btn--ghost btn--sm" href="<?php echo h(url(dashboard_for($_SESSION['role']))); ?>">
                            <i class="fa-solid fa-gauge-high" aria-hidden="true"></i> My dashboard
                        </a>
                        <a class="btn btn--glow btn--sm" href="<?php echo h(url('logout.php')); ?>">Log out</a>
                    <?php else: ?>
                        <a class="btn btn--ghost btn--sm" href="<?php echo h(url('login.php')); ?>">Log in</a>
                        <a class="btn btn--glow btn--sm" href="<?php echo h(url('register.php')); ?>">Create account</a>
                    <?php endif; ?>
                </div>
            </nav>
        </div>
    </header>
<?php endif; ?>

    <!-- Toast notifications (flash messages) -->
    <div class="toasts" role="status" aria-live="polite">
        <?php foreach ($toasts as $t): ?>
            <div class="toast toast--<?php echo h($t['type']); ?>" data-toast>
                <i class="fa-solid <?php echo $t['type'] === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check'; ?>" aria-hidden="true"></i>
                <p><?php echo h($t['message']); ?></p>
                <button type="button" class="toast__close" aria-label="Dismiss" data-toast-close>&times;</button>
            </div>
        <?php endforeach; ?>
    </div>

    <main class="page" id="main">
