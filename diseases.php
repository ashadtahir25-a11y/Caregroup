<?php
// diseases.php - Health guide: common conditions, symptoms, prevention and treatment.
require_once 'db.php';
require_once 'includes/components.php';

$q = trim($_GET['q'] ?? '');
$diseases = [];
try {
    if ($q !== '') {
        $stmt = $pdo->prepare('SELECT * FROM diseases WHERE name LIKE ? OR symptoms LIKE ? ORDER BY name');
        $stmt->execute(['%' . $q . '%', '%' . $q . '%']);
    } else {
        $stmt = $pdo->query('SELECT * FROM diseases ORDER BY name');
    }
    $diseases = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Diseases query failed: ' . $e->getMessage());
}

$page_title = 'Health guide | CARE Group';
$nav_active = 'diseases';
include 'includes/header.php';
?>

<section class="page-head">
    <h1 class="enter">Health guide</h1>
    <p class="page-head__lede">Common conditions in Pakistan: what to look out for, how to prevent them and how they are treated. This guide does not replace a doctor's advice.</p>
</section>

<section class="section section--tight">
    <form class="filters filters--compact glass" method="GET" action="diseases.php" role="search" aria-label="Search conditions">
        <label class="finder__field finder__field--grow">
            <span>Search by condition or symptom</span>
            <input type="search" name="q" value="<?php echo h($q); ?>" placeholder="e.g. fever, cough, dengue">
        </label>
        <button type="submit" class="btn btn--glow"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Search</button>
    </form>

    <?php if ($q !== ''): ?>
        <p class="results-count"><?php echo count($diseases); ?> result<?php echo count($diseases) === 1 ? '' : 's'; ?> for "<?php echo h($q); ?>" &nbsp;<a href="diseases.php">Clear</a></p>
    <?php endif; ?>

    <div class="conditions">
        <?php foreach ($diseases as $i => $d): ?>
            <details class="condition glass" id="disease-<?php echo (int) $d['id']; ?>" data-reveal<?php echo $i === 0 ? ' open' : ''; ?>>
                <summary>
                    <span class="condition__title"><?php echo h($d['name']); ?></span>
                    <span class="condition__desc"><?php echo h($d['description']); ?></span>
                    <i class="fa-solid fa-plus condition__icon" aria-hidden="true"></i>
                </summary>
                <div class="condition__body">
                    <div>
                        <h3><i class="fa-solid fa-temperature-half" aria-hidden="true"></i> Symptoms</h3>
                        <ul><?php foreach (split_list($d['symptoms']) as $s): ?><li><?php echo h($s); ?></li><?php endforeach; ?></ul>
                    </div>
                    <div>
                        <h3><i class="fa-solid fa-shield-heart" aria-hidden="true"></i> Prevention</h3>
                        <ul><?php foreach (split_list($d['preventions']) as $s): ?><li><?php echo h($s); ?></li><?php endforeach; ?></ul>
                    </div>
                    <div>
                        <h3><i class="fa-solid fa-kit-medical" aria-hidden="true"></i> Treatment</h3>
                        <ul><?php foreach (split_list($d['cures']) as $s): ?><li><?php echo h($s); ?></li><?php endforeach; ?></ul>
                    </div>
                </div>
            </details>
        <?php endforeach; ?>
    </div>

    <?php if (!$diseases): ?>
        <div class="empty glass">
            <i class="fa-solid fa-book-medical" aria-hidden="true"></i>
            <p><?php echo $q !== '' ? 'Nothing matches that search. Try a simpler word, like "fever".' : 'The health guide is empty.'; ?></p>
        </div>
    <?php endif; ?>
</section>

<?php include 'includes/footer.php'; ?>