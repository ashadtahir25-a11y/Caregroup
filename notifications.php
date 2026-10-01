<?php
// notifications.php - Every notification for the signed-in user.
//   notifications.php?open=ID  marks one as read and goes to its page
//   POST action=read_all       marks everything as read
require_once 'db.php';

if (!isLoggedIn()) {
    redirect('login.php');
}
$uid = (int) $_SESSION['user_id'];

// Open one notification
if (isset($_GET['open'])) {
    $stmt = $pdo->prepare('SELECT link FROM notifications WHERE id = ? AND user_id = ?');
    $stmt->execute([(int) $_GET['open'], $uid]);
    $link = $stmt->fetchColumn();
    if ($link !== false) {
        $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')->execute([(int) $_GET['open'], $uid]);
    }
    $link = $link ? safe_internal_link($link) : '';
    redirect($link !== '' ? $link : 'notifications.php');
}

// Mark all as read
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (csrf_check() && ($_POST['action'] ?? '') === 'read_all') {
        $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0')->execute([$uid]);
        flash('success', 'All notifications marked as read.');
    }
    $back = safe_internal_link($_POST['return'] ?? '');
    redirect($back !== '' ? $back : 'notifications.php');
}

$filter = ($_GET['filter'] ?? '') === 'unread' ? 'unread' : 'all';
$stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ?' . ($filter === 'unread' ? ' AND is_read = 0' : '') . ' ORDER BY created_at DESC, id DESC LIMIT 100');
$stmt->execute([$uid]);
$items = $stmt->fetchAll();

$page_title  = 'Notifications | CARE Group';
$dash_title  = 'Notifications';
$dash_active = 'notifications';
include 'includes/dash_header.php';
?>

<div class="toolbar">
    <nav class="tabs tabs--flush" aria-label="Filter notifications">
        <a href="notifications.php"<?php echo $filter === 'all' ? ' aria-current="page"' : ''; ?>>All</a>
        <a href="notifications.php?filter=unread"<?php echo $filter === 'unread' ? ' aria-current="page"' : ''; ?>>Unread</a>
    </nav>
    <form method="POST" action="notifications.php">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="read_all">
        <button type="submit" class="btn btn--ghost btn--sm"><i class="fa-solid fa-check-double" aria-hidden="true"></i> Mark all as read</button>
    </form>
</div>

<div class="note-list">
    <?php foreach ($items as $n): ?>
        <a class="note glass<?php echo $n['is_read'] ? '' : ' note--unread'; ?>" href="notifications.php?open=<?php echo (int) $n['id']; ?>" data-reveal>
            <span class="note__icon"><i class="fa-solid <?php echo notification_icon($n['type']); ?>" aria-hidden="true"></i></span>
            <span class="note__text">
                <b><?php echo h($n['title']); ?></b>
                <?php if ($n['body'] !== ''): ?><span><?php echo h($n['body']); ?></span><?php endif; ?>
            </span>
            <time datetime="<?php echo h($n['created_at']); ?>"><?php echo h(time_ago($n['created_at'])); ?></time>
        </a>
    <?php endforeach; ?>
    <?php if (!$items): ?>
        <div class="empty glass"><i class="fa-regular fa-bell" aria-hidden="true"></i>
            <p><?php echo $filter === 'unread' ? 'You are all caught up.' : 'No notifications yet. Updates about your appointments will appear here.'; ?></p></div>
    <?php endif; ?>
</div>

<?php include 'includes/dash_footer.php'; ?>
