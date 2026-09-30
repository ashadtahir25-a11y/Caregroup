<?php
// includes/components.php - Small reusable pieces for the public pages.

// "Nasir Ahmed" -> "NA" (used for the avatar circle, no internet image needed)
function initials(string $name): string
{
    $name  = preg_replace('/^(dr\.?\s+)/i', '', trim($name));
    $parts = preg_split('/\s+/', $name);
    $first = substr($parts[0] ?? '', 0, 1);
    $last  = count($parts) > 1 ? substr(end($parts), 0, 1) : '';
    return strtoupper($first . $last);
}

// Shows "Dr. Name" without doubling it when the name already starts with "Dr".
function doctor_name(string $name): string
{
    return 'Dr. ' . preg_replace('/^(dr\.?\s+)/i', '', trim($name));
}

// Font Awesome icon for a specialty (falls back to a stethoscope).
function specialty_icon(string $specialty): string
{
    $map = [
        'cardio' => 'fa-heart-pulse',  'neuro'  => 'fa-brain',       'derma'  => 'fa-hand-dots',
        'pediat' => 'fa-baby',         'ortho'  => 'fa-bone',        'dent'   => 'fa-tooth',
        'eye'    => 'fa-eye',          'ophth'  => 'fa-eye',         'ent'    => 'fa-ear-listen',
        'gyn'    => 'fa-person-pregnant', 'psych' => 'fa-head-side-heart', 'pulmo' => 'fa-lungs',
        'gastro' => 'fa-bacteria',     'uro'    => 'fa-droplet',     'onco'   => 'fa-ribbon',
        'general'=> 'fa-user-doctor',
    ];
    $s = strtolower($specialty);
    foreach ($map as $key => $icon) {
        if (strpos($s, $key) !== false) {
            return $icon;
        }
    }
    return 'fa-stethoscope';
}

// Splits "a, b; c" or multi-line text into a clean list.
function split_list(string $text): array
{
    $items = preg_split('/[,;\n]+/', $text);
    $items = array_filter(array_map('trim', $items), 'strlen');
    return array_values(array_map('ucfirst', $items));
}

// Colour hue per doctor so avatars look different but stay stable.
function avatar_hue(int $id): int
{
    return [168, 262, 200, 330, 40, 140][$id % 6];
}

// Average rating and review count for every doctor, loaded once per page.
function doctor_ratings(): array
{
    static $cache = null;
    global $pdo;
    if ($cache === null) {
        $cache = [];
        try {
            foreach ($pdo->query('SELECT doctor_id, AVG(rating) AS avg_rating, COUNT(*) AS total FROM reviews GROUP BY doctor_id') as $r) {
                $cache[(int) $r['doctor_id']] = ['avg' => round((float) $r['avg_rating'], 1), 'total' => (int) $r['total']];
            }
        } catch (PDOException $e) {
            error_log('Ratings unavailable (run database/update_part4.sql): ' . $e->getMessage());
        }
    }
    return $cache;
}

// Five stars, filled to the rating (e.g. 4.5 shows four and a half)
function stars(float $rating, string $extra = ''): string
{
    $pct = max(0, min(100, $rating / 5 * 100));
    return '<span class="stars ' . $extra . '" style="--fill: ' . $pct . '%" role="img" aria-label="' . h($rating) . ' out of 5 stars"></span>';
}

// Small "4.5 (12 reviews)" line, or a "new doctor" note when there are no reviews yet
function rating_line(int $doctorId): string
{
    $r = doctor_ratings()[$doctorId] ?? null;
    if (!$r) {
        return '<span class="rating rating--new">New doctor, no reviews yet</span>';
    }
    return '<span class="rating">' . stars($r['avg']) . ' <b>' . number_format($r['avg'], 1) . '</b> (' . $r['total'] . ' review' . ($r['total'] === 1 ? '' : 's') . ')</span>';
}

// One doctor card with 3D tilt.
function doctor_card(array $doc): string
{
    $days = split_list($doc['available_days'] ?? '');
    $short = array_map(function ($d) { return substr($d, 0, 3); }, $days);
    $bookUrl = (isLoggedIn() && $_SESSION['role'] === 'patient')
        ? 'patient_dashboard.php?book_doc_id=' . (int) $doc['id']
        : 'login.php?redirect=book&doc=' . (int) $doc['id'];

    ob_start(); ?>
    <article class="doc-card glass" data-tilt="7" data-reveal>
        <div class="doc-card__top" data-depth>
            <span class="avatar" style="--hue: <?php echo avatar_hue((int) $doc['id']); ?>" aria-hidden="true"><?php echo h(initials($doc['name'])); ?></span>
            <div>
                <h3 class="doc-card__name"><a href="<?php echo h(url('doctor.php?id=' . (int) $doc['id'])); ?>"><?php echo h(doctor_name($doc['name'])); ?></a></h3>
                <p class="doc-card__spec"><i class="fa-solid <?php echo specialty_icon($doc['specialty']); ?>" aria-hidden="true"></i> <?php echo h($doc['specialty']); ?></p>
            </div>
        </div>

        <?php echo rating_line((int) $doc['id']); ?>

        <dl class="doc-card__facts">
            <div><dt>Experience</dt><dd><?php echo (int) $doc['experience_years']; ?> yrs</dd></div>
            <div><dt>Fee</dt><dd>Rs <?php echo number_format((float) $doc['consultation_fee']); ?></dd></div>
            <div><dt>City</dt><dd><?php echo h($doc['city_name']); ?></dd></div>
        </dl>

        <p class="doc-card__addr"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?php echo h($doc['address']); ?></p>

        <?php if ($short): ?>
            <ul class="days" aria-label="Clinic days">
                <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d): ?>
                    <li class="<?php echo in_array($d, $short, true) ? 'is-on' : ''; ?>"><?php echo $d; ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <a class="btn btn--glow btn--block btn--sm" href="<?php echo h(url($bookUrl)); ?>">
            <i class="fa-solid fa-calendar-plus" aria-hidden="true"></i> Book appointment
        </a>
    </article>
    <?php
    return ob_get_clean();
}
