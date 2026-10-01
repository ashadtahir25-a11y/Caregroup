<?php
// admin_reviews.php - Review moderation. The admin can hide a review (with a reason),
// show it again, or delete it. Hidden reviews do not count toward a doctor's rating.
require_once 'db.php';
require_once 'includes/booking.php';
checkRole('admin');

const HIDE_REASONS = [
    'Offensive or abusive language',
    'Shares personal or medical details of others',
    'Spam or advertising',
    'Not about the visit',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $back = 'admin_reviews.php?tab=' . urlencode($_POST['tab'] ?? 'all');

    $stmt = $pdo->prepare('SELECT r.*, p.user_id AS patient_user, d.name AS doctor_name FROM reviews r
                             JOIN patients p ON p.id = r.patient_id JOIN doctors d ON d.id = r.doctor_id WHERE r.id = ?');
    $stmt->execute([$id]);
    $rev = $stmt->fetch();

    if (!csrf_check()) {
        flash('error', 'Your session expired. Try again.');
    } elseif (!$rev) {
        flash('error', 'That review no longer exists.');
    } elseif ($action === 'hide') {
        $reason = $_POST['reason'] ?? '';
        if (!in_array($reason, HIDE_REASONS, true)) {
            flash('error', 'Choose why the review is being hidden.');
        } else {
            $pdo->prepare('UPDATE reviews SET is_hidden = 1, hidden_reason = ? WHERE id = ?')->execute([$reason, $id]);
            notify($pdo, (int) $rev['patient_user'], 'moderation', 'Your review was hidden',
                'Your review of ' . doctor_name($rev['doctor_name']) . ' was hidden: ' . strtolower($reason) . '.', 'patient_dashboard.php?view=history&tab=past');
            flash('success', 'Review hidden. It no longer counts toward the rating.');
        }
    } elseif ($action === 'show') {
        $pdo->prepare('UPDATE reviews SET is_hidden = 0, hidden_reason = NULL WHERE id = ?')->execute([$id]);
        flash('success', 'Review is visible again.');
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM reviews WHERE id = ?')->execute([$id]);
        flash('success', 'Review deleted. The patient can review that visit again.');
    }
    redirect($back);
}

$tab = $_GET['tab'] ?? 'all';
$tabs = ['all' => 'All', 'low' => '1 or 2 stars', 'hidden' => 'Hidden'];
if (!isset($tabs[$tab])) $tab = 'all';
$where = ['all' => '', 'low' => ' WHERE r.rating <= 2 AND r.is_hidden = 0', 'hidden' => ' WHERE r.is_hidden = 1'][$tab];

$reviews = $pdo->query('SELECT r.*, p.name AS patient_name, d.name AS doctor_name, d.id AS doc_id, a.appointment_date
                          FROM reviews r
                          JOIN patients p ON p.id = r.patient_id
                          JOIN doctors d ON d.id = r.doctor_id
                          JOIN appointments a ON a.id = r.appointment_id' . $where . '
                         ORDER BY r.created_at DESC LIMIT 200')->fetchAll();

$stats = $pdo->query('SELECT COUNT(*) AS total, SUM(is_hidden) AS hidden, AVG(CASE WHEN is_hidden = 0 THEN rating END) AS avg_rating FROM reviews')->fetch();

$page_title  = 'Reviews | Admin | CARE Group';
$dash_title  = 'Reviews';
$dash_sub    = 'Hide reviews that break the rules. Hidden reviews do not count toward ratings.';
$dash_active = 'reviews';
include 'includes/dash_header.php';
echo '<link rel="stylesheet" href="' . h(url('assets/css/admin.css')) . '">';
?>

<div class="kpis">
    <div class="kpi glass"><span>Reviews</span><b data-count="<?php echo (int) $stats['total']; ?>"><?php echo (int) $stats['total']; ?></b></div>
    <div class="kpi glass"><span>Average rating</span><b><?php echo $stats['avg_rating'] ? number_format((float) $stats['avg_rating'], 1) : '-'; ?></b><small><?php echo $stats['avg_rating'] ? stars((float) $stats['avg_rating']) : 'No visible reviews yet'; ?></small></div>
    <div class="kpi glass"><span>Hidden</span><b data-count="<?php echo (int) $stats['hidden']; ?>"><?php echo (int) $stats['hidden']; ?></b></div>
</div>

<nav class="tabs" aria-label="Filter reviews">
    <?php foreach ($tabs as $k => $label): ?>
        <a href="admin_reviews.php?tab=<?php echo $k; ?>"<?php echo $tab === $k ? ' aria-current="page"' : ''; ?>><?php echo $label; ?></a>
    <?php endforeach; ?>
</nav>

<div class="mod-list">
    <?php foreach ($reviews as $r): ?>
        <article class="mod glass<?php echo $r['is_hidden'] ? ' mod--hidden' : ''; ?>" data-reveal>
            <header class="mod__head">
                <?php echo stars((float) $r['rating']); ?>
                <b><?php echo h($r['patient_name']); ?></b>
                <span class="muted">on <a href="doctor.php?id=<?php echo (int) $r['doc_id']; ?>" target="_blank" rel="noopener"><?php echo h(doctor_name($r['doctor_name'])); ?></a>, visit <?php echo h(date('j M Y', strtotime($r['appointment_date']))); ?></span>
                <time><?php echo h(time_ago($r['created_at'])); ?></time>
            </header>
            <p class="mod__text"><?php echo trim((string) $r['comment']) !== '' ? nl2br(h($r['comment'])) : '<span class="muted">No comment, stars only.</span>'; ?></p>
            <?php if ($r['is_hidden']): ?>
                <p class="mod__reason"><i class="fa-solid fa-eye-slash" aria-hidden="true"></i> Hidden: <?php echo h($r['hidden_reason']); ?></p>
            <?php endif; ?>
            <div class="mod__actions">
                <?php if ($r['is_hidden']): ?>
                    <form method="POST" action="admin_reviews.php"><?php echo csrf_field(); ?>
                        <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>"><input type="hidden" name="tab" value="<?php echo h($tab); ?>">
                        <input type="hidden" name="action" value="show">
                        <button type="submit" class="btn btn--ghost btn--xs"><i class="fa-solid fa-eye" aria-hidden="true"></i> Show again</button></form>
                <?php else: ?>
                    <form class="mod__hide" method="POST" action="admin_reviews.php"><?php echo csrf_field(); ?>
                        <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>"><input type="hidden" name="tab" value="<?php echo h($tab); ?>">
                        <input type="hidden" name="action" value="hide">
                        <select class="input input--plain" name="reason" aria-label="Reason for hiding" required>
                            <option value="">Reason for hiding</option>
                            <?php foreach (HIDE_REASONS as $reason): ?><option><?php echo h($reason); ?></option><?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn--ghost btn--xs"><i class="fa-solid fa-eye-slash" aria-hidden="true"></i> Hide</button></form>
                <?php endif; ?>
                <form method="POST" action="admin_reviews.php" data-confirm="Delete this review for good? The patient will be able to review that visit again."><?php echo csrf_field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>"><input type="hidden" name="tab" value="<?php echo h($tab); ?>">
                    <input type="hidden" name="action" value="delete">
                    <button type="submit" class="btn btn--danger btn--xs">Delete</button></form>
            </div>
        </article>
    <?php endforeach; ?>
    <?php if (!$reviews): ?>
        <div class="empty glass"><i class="fa-regular fa-star" aria-hidden="true"></i><p>No reviews in this list.</p></div>
    <?php endif; ?>
</div>

<?php include 'includes/dash_footer.php'; ?>
