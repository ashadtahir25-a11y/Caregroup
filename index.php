<?php
// index.php - CARE Group homepage.
require_once 'db.php';
require_once 'includes/components.php';

$cities = $specialties = $featured = $diseases = $news = [];
$stats = ['doctors' => 0, 'specialties' => 0, 'cities' => 0, 'patients' => 0];

try {
    $cities = $pdo->query('SELECT id, name FROM cities ORDER BY name')->fetchAll();

    // Specialties with how many doctors practise each one
    $specialties = $pdo->query(
        "SELECT specialty, COUNT(*) AS total FROM doctors
          WHERE specialty <> '' GROUP BY specialty ORDER BY total DESC, specialty LIMIT 8"
    )->fetchAll();

    $featured = $pdo->query(
        'SELECT d.*, c.name AS city_name FROM doctors d JOIN cities c ON c.id = d.city_id
          ORDER BY d.experience_years DESC LIMIT 6'
    )->fetchAll();

    $diseases = $pdo->query('SELECT id, name, description, symptoms FROM diseases ORDER BY name LIMIT 4')->fetchAll();
    $news     = $pdo->query('SELECT id, title, summary, category, published_date FROM medical_news ORDER BY published_date DESC LIMIT 3')->fetchAll();

    // Real numbers only - no made-up totals
    $stats['doctors']     = (int) $pdo->query('SELECT COUNT(*) FROM doctors')->fetchColumn();
    $stats['specialties'] = (int) $pdo->query("SELECT COUNT(DISTINCT specialty) FROM doctors")->fetchColumn();
    $stats['cities']      = count($cities);
    $stats['patients']    = (int) $pdo->query('SELECT COUNT(*) FROM patients')->fetchColumn();
} catch (PDOException $e) {
    error_log('Homepage query failed: ' . $e->getMessage());
}

$hero = $featured[0] ?? null;   // doctor shown on the floating 3D card
$tip  = $diseases[0] ?? null;   // health tip on the small floating card

$page_title = 'CARE Group | Find a doctor and book online';
$body_class = 'page-home';
$nav_active = 'home';
include 'includes/header.php';
?>

<!-- ================= HERO ================= -->
<section class="hero">
    <div class="hero__copy enter">
        <h1 class="hero__title">Find the right doctor in your city. Book in minutes.</h1>
        <p class="hero__lede">Search verified specialists across Pakistan, see the days they hold clinic and request a time slot online.</p>

        <form class="finder glass" action="doctors.php" method="GET" role="search" aria-label="Find a doctor">
            <label class="finder__field">
                <span>Specialty</span>
                <select name="specialty">
                    <option value="">Any specialty</option>
                    <?php foreach ($specialties as $sp): ?>
                        <option value="<?php echo h($sp['specialty']); ?>"><?php echo h($sp['specialty']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="finder__field">
                <span>City</span>
                <select name="city">
                    <option value="">All cities</option>
                    <?php foreach ($cities as $c): ?>
                        <option value="<?php echo (int) $c['id']; ?>"><?php echo h($c['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="btn btn--glow"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Search doctors</button>
        </form>

        <?php if ($specialties): ?>
            <p class="hero__quick">
                Popular:
                <?php foreach (array_slice($specialties, 0, 4) as $sp): ?>
                    <a class="chip" href="doctors.php?specialty=<?php echo urlencode($sp['specialty']); ?>"><?php echo h($sp['specialty']); ?></a>
                <?php endforeach; ?>
            </p>
        <?php endif; ?>
    </div>

    <!-- 3D floating scene: layers move at different speeds with the mouse -->
    <div class="scene" aria-hidden="true">
        <div class="scene__card glass" data-tilt="10">
            <?php if ($hero): ?>
                <div class="scene__doc" data-depth style="--depth: 50px">
                    <span class="avatar avatar--lg" style="--hue: <?php echo avatar_hue((int) $hero['id']); ?>"><?php echo h(initials($hero['name'])); ?></span>
                    <div>
                        <b><?php echo h(doctor_name($hero['name'])); ?></b>
                        <span><?php echo h($hero['specialty']); ?>, <?php echo h($hero['city_name']); ?></span>
                    </div>
                </div>
            <?php else: ?>
                <div class="scene__doc" data-depth style="--depth: 50px">
                    <span class="avatar avatar--lg" style="--hue: 168">CG</span>
                    <div><b>CARE Group</b><span>Specialists joining soon</span></div>
                </div>
            <?php endif; ?>

            <svg class="scene__ecg" viewBox="0 0 400 80" preserveAspectRatio="none" data-depth style="--depth: 30px">
                <path d="M0 45 H120 L132 45 L140 32 L148 58 L160 6 L174 76 L186 45 L206 45 L214 36 L224 45 H400"/>
            </svg>

            <p class="scene__label" data-depth style="--depth: 24px">Choose a time</p>
            <div class="scene__slots" data-depth style="--depth: 40px">
                <span>09:00 AM</span><span class="is-picked">11:00 AM</span><span>02:00 PM</span><span>04:00 PM</span>
            </div>
        </div>

        <div class="scene__float scene__float--status glass" data-parallax="-30">
            <i class="fa-solid fa-circle-check"></i>
            <div><b>Appointment confirmed</b><span>You will see it on your dashboard</span></div>
        </div>

        <?php if ($tip): ?>
            <div class="scene__float scene__float--tip glass" data-parallax="22">
                <i class="fa-solid fa-shield-virus"></i>
                <div><b><?php echo h($tip['name']); ?></b><span>Read prevention tips</span></div>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ================= LIVE NUMBERS ================= -->
<section class="stats" aria-label="CARE Group in numbers">
    <div class="stats__item"><b data-count="<?php echo $stats['doctors']; ?>"><?php echo $stats['doctors']; ?></b><span>Registered doctors</span></div>
    <div class="stats__item"><b data-count="<?php echo $stats['specialties']; ?>"><?php echo $stats['specialties']; ?></b><span>Specialties</span></div>
    <div class="stats__item"><b data-count="<?php echo $stats['cities']; ?>"><?php echo $stats['cities']; ?></b><span>Cities</span></div>
    <div class="stats__item"><b data-count="<?php echo $stats['patients']; ?>"><?php echo $stats['patients']; ?></b><span>Patients signed up</span></div>
</section>

<!-- ================= SPECIALTIES ================= -->
<?php if ($specialties): ?>
<section class="section" id="specialties">
    <header class="section__head">
        <h2>Browse by specialty</h2>
        <a class="link-more" href="doctors.php">All doctors</a>
    </header>
    <div class="spec-grid">
        <?php foreach ($specialties as $i => $sp): ?>
            <a class="spec glass" href="doctors.php?specialty=<?php echo urlencode($sp['specialty']); ?>" data-tilt="12" data-reveal style="transition-delay: <?php echo $i * 60; ?>ms">
                <i class="fa-solid <?php echo specialty_icon($sp['specialty']); ?>" data-depth aria-hidden="true"></i>
                <b><?php echo h($sp['specialty']); ?></b>
                <span><?php echo (int) $sp['total']; ?> doctor<?php echo $sp['total'] == 1 ? '' : 's'; ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- ================= FEATURED DOCTORS ================= -->
<section class="section" id="search-section">
    <header class="section__head">
        <h2>Most experienced doctors</h2>
        <a class="link-more" href="doctors.php">See all doctors</a>
    </header>
    <?php if ($featured): ?>
        <div class="doc-grid">
            <?php foreach ($featured as $doc) { echo doctor_card($doc); } ?>
        </div>
    <?php else: ?>
        <div class="empty glass">
            <i class="fa-solid fa-user-doctor" aria-hidden="true"></i>
            <p>No doctors are listed yet. The admin can add them from the admin console.</p>
        </div>
    <?php endif; ?>
</section>

<!-- ================= HOW BOOKING WORKS (a real sequence, so it is numbered) ================= -->
<section class="section">
    <header class="section__head"><h2>How booking works</h2></header>
    <ol class="steps">
        <li class="steps__item glass" data-reveal>
            <span class="steps__num">1</span>
            <h3>Search</h3>
            <p>Pick a specialty and city to see doctors near you, with fees and clinic days.</p>
        </li>
        <li class="steps__item glass" data-reveal style="transition-delay: 120ms">
            <span class="steps__num">2</span>
            <h3>Request a slot</h3>
            <p>Choose a date the doctor is in clinic and one of their time slots.</p>
        </li>
        <li class="steps__item glass" data-reveal style="transition-delay: 240ms">
            <span class="steps__num">3</span>
            <h3>Get confirmed</h3>
            <p>The doctor confirms your request. Track its status from your dashboard.</p>
        </li>
    </ol>
</section>

<!-- ================= HEALTH GUIDE + RESEARCH ================= -->
<section class="section split" id="handbook">
    <div>
        <header class="section__head">
            <h2>Health guide</h2>
            <a class="link-more" href="diseases.php">All conditions</a>
        </header>
        <div class="guide">
            <?php foreach ($diseases as $d): ?>
                <a class="guide__item glass" href="diseases.php#disease-<?php echo (int) $d['id']; ?>" data-reveal>
                    <b><?php echo h($d['name']); ?></b>
                    <span><?php echo h($d['description']); ?></span>
                </a>
            <?php endforeach; ?>
            <?php if (!$diseases): ?><p class="muted">Health articles will appear here.</p><?php endif; ?>
        </div>
    </div>

    <div id="news-section">
        <header class="section__head">
            <h2>Medical research</h2>
            <a class="link-more" href="news.php">All articles</a>
        </header>
        <div class="news-list">
            <?php foreach ($news as $n): ?>
                <a class="news-item glass" href="news.php?id=<?php echo (int) $n['id']; ?>" data-reveal>
                    <span class="tag tag--<?php echo h(strtolower($n['category'])); ?>"><?php echo h($n['category']); ?></span>
                    <b><?php echo h($n['title']); ?></b>
                    <time datetime="<?php echo h($n['published_date']); ?>"><?php echo h(date('j M Y', strtotime($n['published_date']))); ?></time>
                </a>
            <?php endforeach; ?>
            <?php if (!$news): ?><p class="muted">Research articles will appear here.</p><?php endif; ?>
        </div>
    </div>
</section>

<!-- ================= CALL TO ACTION ================= -->
<?php if (!isLoggedIn()): ?>
<section class="cta glass" data-reveal>
    <div>
        <h2>Ready to book your first visit?</h2>
        <p>Create a free patient account. It takes about a minute.</p>
    </div>
    <a class="btn btn--glow" href="register.php">Create account</a>
</section>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>