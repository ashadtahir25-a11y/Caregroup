<?php
// doctors.php - Search and filter all doctors.
require_once 'db.php';
require_once 'includes/components.php';

$q         = trim($_GET['q'] ?? '');
$specialty = trim($_GET['specialty'] ?? '');
$city      = (int) ($_GET['city'] ?? 0);
$sort      = $_GET['sort'] ?? 'experience';

$sortSql = [
    'experience' => 'd.experience_years DESC',
    'fee_low'    => 'd.consultation_fee ASC',
    'fee_high'   => 'd.consultation_fee DESC',
    'name'       => 'd.name ASC',
];
if (!isset($sortSql[$sort])) {
    $sort = 'experience';
}

$doctors = $cities = $specialties = [];
try {
    $cities      = $pdo->query('SELECT id, name FROM cities ORDER BY name')->fetchAll();
    $specialties = $pdo->query("SELECT DISTINCT specialty FROM doctors WHERE specialty <> '' ORDER BY specialty")->fetchAll(PDO::FETCH_COLUMN);

    $sql = 'SELECT d.*, c.name AS city_name FROM doctors d JOIN cities c ON c.id = d.city_id WHERE 1 = 1';
    $params = [];
    if ($q !== '') {
        $sql .= ' AND (d.name LIKE ? OR d.specialty LIKE ? OR d.address LIKE ?)';
        $like = '%' . $q . '%';
        array_push($params, $like, $like, $like);
    }
    if ($specialty !== '') {
        $sql .= ' AND d.specialty = ?';
        $params[] = $specialty;
    }
    if ($city > 0) {
        $sql .= ' AND d.city_id = ?';
        $params[] = $city;
    }
    $sql .= ' ORDER BY ' . $sortSql[$sort];   // value comes from the whitelist above, never from the user directly

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $doctors = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Doctor search failed: ' . $e->getMessage());
}

$filtered = $q !== '' || $specialty !== '' || $city > 0;

$page_title = 'Find a doctor | CARE Group';
$nav_active = 'doctors';
include 'includes/header.php';
?>

<section class="page-head">
    <h1 class="enter">Find a doctor</h1>
    <p class="page-head__lede">Filter by specialty and city, then book a time slot that suits you.</p>
</section>

<section class="section section--tight">
    <form class="filters glass" method="GET" action="doctors.php" role="search" aria-label="Filter doctors">
        <label class="finder__field finder__field--grow">
            <span>Search</span>
            <input type="search" name="q" value="<?php echo h($q); ?>" placeholder="Doctor name or area">
        </label>
        <label class="finder__field">
            <span>Specialty</span>
            <select name="specialty">
                <option value="">Any specialty</option>
                <?php foreach ($specialties as $sp): ?>
                    <option value="<?php echo h($sp); ?>"<?php echo $sp === $specialty ? ' selected' : ''; ?>><?php echo h($sp); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="finder__field">
            <span>City</span>
            <select name="city">
                <option value="">All cities</option>
                <?php foreach ($cities as $c): ?>
                    <option value="<?php echo (int) $c['id']; ?>"<?php echo (int) $c['id'] === $city ? ' selected' : ''; ?>><?php echo h($c['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="finder__field">
            <span>Sort by</span>
            <select name="sort">
                <option value="experience"<?php echo $sort === 'experience' ? ' selected' : ''; ?>>Most experienced</option>
                <option value="fee_low"<?php echo $sort === 'fee_low' ? ' selected' : ''; ?>>Lowest fee</option>
                <option value="fee_high"<?php echo $sort === 'fee_high' ? ' selected' : ''; ?>>Highest fee</option>
                <option value="name"<?php echo $sort === 'name' ? ' selected' : ''; ?>>Name (A to Z)</option>
            </select>
        </label>
        <button type="submit" class="btn btn--glow"><i class="fa-solid fa-sliders" aria-hidden="true"></i> Apply</button>
    </form>

    <p class="results-count">
        <?php echo count($doctors); ?> doctor<?php echo count($doctors) === 1 ? '' : 's'; ?> found
        <?php if ($filtered): ?> &nbsp;<a href="doctors.php">Clear filters</a><?php endif; ?>
    </p>

    <?php if ($doctors): ?>
        <div class="doc-grid">
            <?php foreach ($doctors as $doc) { echo doctor_card($doc); } ?>
        </div>
    <?php else: ?>
        <div class="empty glass">
            <i class="fa-solid fa-user-doctor" aria-hidden="true"></i>
            <p><?php echo $filtered ? 'No doctor matches these filters. Try another city or specialty.' : 'No doctors are listed yet.'; ?></p>
            <?php if ($filtered): ?><a class="btn btn--ghost btn--sm" href="doctors.php">Show all doctors</a><?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<?php include 'includes/footer.php'; ?>