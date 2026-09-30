<?php
// symptoms.php - Symptom checker: the patient picks symptoms, the page lists
// conditions from the health guide that share them and suggests matching doctors.
// It is guidance only, never a diagnosis.
require_once 'db.php';
require_once 'includes/components.php';

// Words removed so "High fever" and "fever" count as the same symptom
const SYMPTOM_FILLER = ['severe ', 'high ', 'dry ', 'increased ', 'frequent ', 'mild ', 'often ', 'sudden ', ' in extreme cases'];

// Symptoms that should never wait for an online booking
const URGENT_SYMPTOMS = ['difficulty breathing', 'shortness of breath', 'chest pain', 'fainting', 'confusion'];

// Different words for the same thing
const SYMPTOM_SYNONYMS = [
    'tiredness'            => 'fatigue',
    'muscle aches'         => 'muscle pain',
    'shortness of breath'  => 'difficulty breathing',
    'headaches'            => 'headache',
];

function normalise_symptom(string $s): string
{
    $s = strtolower(trim($s));
    $s = str_replace(SYMPTOM_FILLER, '', $s);
    $s = trim(preg_replace('/\s+/', ' ', $s));
    return SYMPTOM_SYNONYMS[$s] ?? $s;
}

// Turn the free text in the diseases table into clean symptom lists
$diseases = [];
try {
    $diseases = $pdo->query('SELECT * FROM diseases ORDER BY name')->fetchAll();
} catch (PDOException $e) {
    error_log('Symptom checker query failed: ' . $e->getMessage());
}

$all = [];
foreach ($diseases as &$d) {
    $d['symptom_set'] = [];
    foreach (preg_split('/[,;]+/', $d['symptoms']) as $raw) {
        // Skip long sentences and "no symptoms" notes; keep short symptom names
        $s = normalise_symptom($raw);
        if ($s === '' || str_word_count($s) > 5 || strpos($s, 'asymptomatic') !== false) {
            continue;
        }
        $d['symptom_set'][] = $s;
        $all[$s] = true;
    }
}
unset($d);
$allSymptoms = array_keys($all);
sort($allSymptoms);

// Symptoms the visitor ticked (only ones we know about are accepted)
$picked = array_values(array_intersect($allSymptoms, array_map('strval', (array) ($_GET['s'] ?? []))));
$urgent = array_values(array_intersect($picked, URGENT_SYMPTOMS));

$results = [];
if ($picked) {
    foreach ($diseases as $d) {
        $hits = array_values(array_intersect($picked, $d['symptom_set']));
        if (!$hits) continue;
        $results[] = $d + [
            'hits'  => $hits,
            'score' => count($hits) / count($picked),                     // how much of what you feel it explains
            'cover' => count($hits) / max(1, count($d['symptom_set'])),   // how much of the condition you have
        ];
    }
    usort($results, function ($a, $b) {
        return [$b['score'], $b['cover']] <=> [$a['score'], $a['cover']];
    });
    $results = array_slice($results, 0, 3);

    // Doctors for each suggested specialty
    foreach ($results as &$r) {
        $r['doctors'] = [];
        if (!empty($r['specialty'])) {
            $stmt = $pdo->prepare('SELECT d.*, c.name AS city_name FROM doctors d JOIN cities c ON c.id = d.city_id
                                    WHERE d.specialty LIKE ? ORDER BY d.experience_years DESC LIMIT 3');
            $stmt->execute(['%' . $r['specialty'] . '%']);
            $r['doctors'] = $stmt->fetchAll();
        }
    }
    unset($r);
}

$page_title = 'Symptom checker | CARE Group';
$nav_active = 'symptoms';
include 'includes/header.php';
?>

<section class="page-head">
    <h1 class="enter">Symptom checker</h1>
    <p class="page-head__lede">Tick what you are feeling. We will show conditions from our health guide with the same symptoms, and the specialists who treat them.</p>
</section>

<section class="section section--tight">
    <p class="disclaimer"><i class="fa-solid fa-circle-info" aria-hidden="true"></i>
        This is general guidance, not a diagnosis. Only a doctor can tell you what is wrong. In an emergency, call 1122.</p>

    <form class="checker glass" method="GET" action="symptoms.php">
        <fieldset>
            <legend>What are you feeling? <span class="muted" data-picked-count><?php echo count($picked); ?> selected</span></legend>
            <div class="sym-grid">
                <?php foreach ($allSymptoms as $s): ?>
                    <label class="sym">
                        <input type="checkbox" name="s[]" value="<?php echo h($s); ?>"<?php echo in_array($s, $picked, true) ? ' checked' : ''; ?>>
                        <span><?php echo h(ucfirst($s)); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <div class="checker__foot">
            <?php if ($picked): ?><a class="btn btn--ghost" href="symptoms.php">Clear</a><?php endif; ?>
            <button type="submit" class="btn btn--glow"><i class="fa-solid fa-wave-square" aria-hidden="true"></i> Check symptoms</button>
        </div>
    </form>

    <?php if ($urgent): ?>
        <div class="urgent glass" role="alert">
            <i class="fa-solid fa-truck-medical" aria-hidden="true"></i>
            <div>
                <b>Get help now for <?php echo h(implode(' and ', $urgent)); ?>.</b>
                <p>Do not wait for an appointment. Call 1122 or go to the nearest emergency department.</p>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($picked): ?>
        <div class="panel-head"><h2>Possible matches</h2></div>
        <?php if (!$results): ?>
            <div class="empty glass"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <p>None of the conditions in our health guide match these symptoms. Book a general check-up to be safe.</p>
                <a class="btn btn--glow btn--sm" href="doctors.php">Find a doctor</a></div>
        <?php endif; ?>

        <div class="matches">
            <?php foreach ($results as $i => $r): $pct = (int) round($r['score'] * 100); ?>
                <article class="match glass" data-reveal style="transition-delay: <?php echo $i * 120; ?>ms">
                    <div class="match__ring" style="--p: <?php echo $pct; ?>" aria-label="<?php echo $pct; ?> percent of your symptoms">
                        <b><?php echo $pct; ?>%</b><span>match</span>
                    </div>
                    <div class="match__body">
                        <h3><?php echo h($r['name']); ?></h3>
                        <p class="muted"><?php echo h($r['description']); ?></p>
                        <p class="match__hits">
                            <?php foreach ($r['symptom_set'] as $s): ?>
                                <span class="chip<?php echo in_array($s, $r['hits'], true) ? ' chip--hit' : ''; ?>"><?php echo h(ucfirst($s)); ?></span>
                            <?php endforeach; ?>
                        </p>
                        <div class="match__foot">
                            <a class="link-more" href="diseases.php#disease-<?php echo (int) $r['id']; ?>">Prevention and treatment</a>
                            <?php if (!empty($r['specialty'])): ?>
                                <span class="muted">See a <b><?php echo h($r['specialty']); ?></b></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($r['doctors'])): ?>
                            <div class="match__docs">
                                <?php foreach ($r['doctors'] as $doc): ?>
                                    <a class="mini-doc" href="doctor.php?id=<?php echo (int) $doc['id']; ?>">
                                        <span class="avatar avatar--sm" style="--hue: <?php echo avatar_hue((int) $doc['id']); ?>" aria-hidden="true"><?php echo h(initials($doc['name'])); ?></span>
                                        <span><b><?php echo h(doctor_name($doc['name'])); ?></b><br><small><?php echo h($doc['city_name']); ?>, Rs <?php echo number_format((float) $doc['consultation_fee']); ?></small></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif (!empty($r['specialty'])): ?>
                            <p class="muted small">No <?php echo h($r['specialty']); ?> is listed yet. <a href="doctors.php">Browse all doctors</a>.</p>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<script>
/* Live "3 selected" counter while ticking symptoms */
document.querySelectorAll('.sym input').forEach(function (box) {
    box.addEventListener('change', function () {
        var n = document.querySelectorAll('.sym input:checked').length;
        document.querySelector('[data-picked-count]').textContent = n + ' selected';
    });
});
</script>

<?php include 'includes/footer.php'; ?>
