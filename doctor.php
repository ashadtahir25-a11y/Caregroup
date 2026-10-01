<?php
// doctor.php - Public profile of one doctor: details, clinic hours and patient reviews.
require_once 'db.php';
require_once 'includes/booking.php';

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT d.*, c.name AS city_name FROM doctors d JOIN cities c ON c.id = d.city_id WHERE d.id = ?');
$stmt->execute([$id]);
$doc = $stmt->fetch();

if (!$doc) {
    flash('error', 'That doctor could not be found.');
    redirect('doctors.php');
}

// Reviews (patient names shortened to "Sana M." for privacy)
$stmt = $pdo->prepare('SELECT r.rating, r.comment, r.created_at, p.name AS patient_name
                         FROM reviews r JOIN patients p ON p.id = r.patient_id
                        WHERE r.doctor_id = ? AND r.is_hidden = 0 ORDER BY r.created_at DESC LIMIT 20');
$stmt->execute([$id]);
$reviews = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT rating, COUNT(*) AS n FROM reviews WHERE doctor_id = ? AND is_hidden = 0 GROUP BY rating');
$stmt->execute([$id]);
$dist = array_column($stmt->fetchAll(), 'n', 'rating');
$totalReviews = array_sum($dist);
$avg = $totalReviews ? array_sum(array_map(function ($r, $n) { return $r * $n; }, array_keys($dist), $dist)) / $totalReviews : 0;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND status = 'Completed'");
$stmt->execute([$id]);
$visits = (int) $stmt->fetchColumn();

function short_name(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    return $parts[0] . (count($parts) > 1 ? ' ' . strtoupper(substr(end($parts), 0, 1)) . '.' : '');
}

$bookUrl = (isLoggedIn() && $_SESSION['role'] === 'patient')
    ? 'patient_dashboard.php?view=book&book_doc_id=' . $id
    : 'login.php?redirect=book&doc=' . $id;
$days  = csv_list($doc['available_days']);
$slots = sort_slots(csv_list($doc['available_slots']));

$page_title = doctor_name($doc['name']) . ', ' . $doc['specialty'] . ' | CARE Group';
$nav_active = 'doctors';
include 'includes/header.php';
?>

<section class="profile">
    <a class="console__back" href="doctors.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> All doctors</a>

    <div class="profile__grid">
        <aside class="profile__card glass enter" data-tilt="5">
            <span class="avatar avatar--xl" style="--hue: <?php echo avatar_hue($id); ?>" aria-hidden="true" data-depth><?php echo h(initials($doc['name'])); ?></span>
            <h1><?php echo h(doctor_name($doc['name'])); ?></h1>
            <p class="doc-card__spec"><i class="fa-solid <?php echo specialty_icon($doc['specialty']); ?>" aria-hidden="true"></i> <?php echo h($doc['specialty']); ?></p>
            <?php echo rating_line($id); ?>

            <dl class="doc-card__facts profile__facts">
                <div><dt>Experience</dt><dd><?php echo (int) $doc['experience_years']; ?> yrs</dd></div>
                <div><dt>Fee</dt><dd>Rs <?php echo number_format((float) $doc['consultation_fee']); ?></dd></div>
                <div><dt>Visits</dt><dd><?php echo $visits; ?></dd></div>
            </dl>
            <a class="btn btn--glow btn--block" href="<?php echo h($bookUrl); ?>"><i class="fa-solid fa-calendar-plus" aria-hidden="true"></i> Book appointment</a>
        </aside>

        <div class="profile__main">
            <section class="glass profile__block" data-reveal>
                <h2>About</h2>
                <p><?php echo $doc['bio'] !== null && trim($doc['bio']) !== '' ? nl2br(h($doc['bio'])) : h(doctor_name($doc['name']) . ' is a ' . $doc['specialty'] . ' practising in ' . $doc['city_name'] . '.'); ?></p>
                <p class="muted"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?php echo h($doc['address']); ?>, <?php echo h($doc['city_name']); ?>
                   &nbsp; <i class="fa-solid fa-phone" aria-hidden="true"></i> <?php echo h($doc['phone']); ?></p>
            </section>

            <section class="glass profile__block" data-reveal>
                <h2>Clinic hours</h2>
                <ul class="days days--lg" aria-label="Clinic days">
                    <?php foreach (WEEK_DAYS as $d): ?>
                        <li class="<?php echo in_array($d, $days, true) ? 'is-on' : ''; ?>"><?php echo substr($d, 0, 3); ?></li>
                    <?php endforeach; ?>
                </ul>
                <p class="profile__slots"><?php foreach ($slots as $s): ?><span class="chip"><?php echo h($s); ?></span><?php endforeach; ?></p>
            </section>

            <section class="glass profile__block" data-reveal>
                <h2>Patient reviews</h2>
                <?php if ($totalReviews): ?>
                    <div class="review-sum">
                        <div class="review-sum__big">
                            <b><?php echo number_format($avg, 1); ?></b>
                            <?php echo stars($avg, 'stars--lg'); ?>
                            <span><?php echo $totalReviews; ?> review<?php echo $totalReviews === 1 ? '' : 's'; ?></span>
                        </div>
                        <ul class="hist">
                            <?php for ($r = 5; $r >= 1; $r--): $n = (int) ($dist[$r] ?? 0); ?>
                                <li><span><?php echo $r; ?> <i class="fa-solid fa-star" aria-hidden="true"></i></span>
                                    <div class="hist__track"><i style="--w: <?php echo round($n / $totalReviews * 100); ?>%"></i></div><b><?php echo $n; ?></b></li>
                            <?php endfor; ?>
                        </ul>
                    </div>
                    <div class="reviews">
                        <?php foreach ($reviews as $rv): ?>
                            <article class="review">
                                <header>
                                    <b><?php echo h(short_name($rv['patient_name'])); ?></b>
                                    <?php echo stars((float) $rv['rating']); ?>
                                    <time datetime="<?php echo h(substr($rv['created_at'], 0, 10)); ?>"><?php echo h(date('j M Y', strtotime($rv['created_at']))); ?></time>
                                </header>
                                <?php if (trim((string) $rv['comment']) !== ''): ?><p><?php echo nl2br(h($rv['comment'])); ?></p><?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="muted">No reviews yet. Patients can rate a doctor after a completed visit.</p>
                <?php endif; ?>
            </section>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
