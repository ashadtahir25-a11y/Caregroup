<?php
// admin_inbox.php - Messages sent from the public Contact page.
require_once 'db.php';
require_once 'includes/booking.php';
checkRole('admin');

const INBOX_SUBJECTS = [
    'appointment' => 'Appointment or booking', 'doctor' => 'Joining as a doctor',
    'feedback' => 'Feedback or suggestion', 'complaint' => 'Complaint', 'other' => 'Something else',
];

// ---- Actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if (!csrf_check()) {
        flash('error', 'Your session expired. Try again.');
    } elseif ($action === 'status' && in_array($_POST['to'] ?? '', ['New', 'Read', 'Archived'], true)) {
        $pdo->prepare('UPDATE contact_messages SET status = ? WHERE id = ?')->execute([$_POST['to'], $id]);
        $labels = ['New' => 'Marked as unread.', 'Read' => 'Marked as read.', 'Archived' => 'Message archived.'];
        flash('success', $labels[$_POST['to']]);
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM contact_messages WHERE id = ?')->execute([$id]);
        flash('success', 'Message deleted.');
        redirect('admin_inbox.php');
    }
    // "Move to inbox" stays on the message; other changes go back to the list
    redirect(($_POST['to'] ?? '') === 'Read' ? 'admin_inbox.php?open=' . $id : 'admin_inbox.php');
}

// ---- One message ----
$open = null;
if (isset($_GET['open'])) {
    $stmt = $pdo->prepare('SELECT * FROM contact_messages WHERE id = ?');
    $stmt->execute([(int) $_GET['open']]);
    $open = $stmt->fetch() ?: null;
    if ($open && $open['status'] === 'New') {
        $pdo->prepare("UPDATE contact_messages SET status = 'Read' WHERE id = ?")->execute([$open['id']]);
        $open['status'] = 'Read';
    }
}

// ---- List ----
$tab = $_GET['tab'] ?? 'inbox';
$tabs = ['inbox' => 'Inbox', 'New' => 'Unread', 'Archived' => 'Archived', 'all' => 'All'];
if (!isset($tabs[$tab])) $tab = 'inbox';
$q = trim($_GET['q'] ?? '');

$sql = 'SELECT * FROM contact_messages WHERE 1 = 1';
$params = [];
if ($tab === 'inbox')        { $sql .= " AND status <> 'Archived'"; }
elseif ($tab !== 'all')      { $sql .= ' AND status = ?'; $params[] = $tab; }
if ($q !== '')               { $sql .= ' AND (name LIKE ? OR email LIKE ? OR message LIKE ?)'; array_push($params, "%$q%", "%$q%", "%$q%"); }
$stmt = $pdo->prepare($sql . ' ORDER BY created_at DESC LIMIT 200');
$stmt->execute($params);
$messages = $stmt->fetchAll();

$counts = array_column($pdo->query('SELECT status, COUNT(*) AS n FROM contact_messages GROUP BY status')->fetchAll(), 'n', 'status');

function inbox_button(int $id, string $action, string $label, string $class, array $extra = [], string $confirm = ''): string
{
    $html = '<form method="POST" action="admin_inbox.php"' . ($confirm ? ' data-confirm="' . h($confirm) . '"' : '') . '>' . csrf_field()
        . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="action" value="' . h($action) . '">';
    foreach ($extra as $k => $v) $html .= '<input type="hidden" name="' . h($k) . '" value="' . h($v) . '">';
    return $html . '<button type="submit" class="btn ' . $class . ' btn--xs">' . $label . '</button></form>';
}

$page_title  = 'Inbox | Admin | CARE Group';
$dash_title  = 'Inbox';
$dash_sub    = 'Messages from the Contact page.';
$dash_active = 'inbox';
include 'includes/dash_header.php';
echo '<link rel="stylesheet" href="' . h(url('assets/css/admin.css')) . '">';
?>

<?php if ($open): ?>
    <article class="panel glass message enter">
        <div class="panel-head panel-head--flush">
            <div>
                <h2><?php echo h(INBOX_SUBJECTS[$open['subject']] ?? $open['subject']); ?></h2>
                <p class="muted small">From <b><?php echo h($open['name']); ?></b> &lt;<?php echo h($open['email']); ?>&gt;<?php echo $open['phone'] ? ' &middot; ' . h($open['phone']) : ''; ?> &middot; <?php echo h(date('j M Y, g:i A', strtotime($open['created_at']))); ?></p>
            </div>
            <a class="btn btn--ghost btn--sm" href="admin_inbox.php">Close</a>
        </div>
        <div class="message__body"><?php echo nl2br(h($open['message'])); ?></div>
        <div class="row__actions message__actions">
            <a class="btn btn--glow btn--xs" href="mailto:<?php echo h($open['email']); ?>?subject=<?php echo rawurlencode('Re: ' . (INBOX_SUBJECTS[$open['subject']] ?? 'Your message') . ' - CARE Group'); ?>"><i class="fa-solid fa-reply" aria-hidden="true"></i> Reply by email</a>
            <?php echo inbox_button((int) $open['id'], 'status', 'Mark as unread', 'btn--ghost', ['to' => 'New']); ?>
            <?php echo $open['status'] === 'Archived'
                ? inbox_button((int) $open['id'], 'status', 'Move to inbox', 'btn--ghost', ['to' => 'Read'])
                : inbox_button((int) $open['id'], 'status', 'Archive', 'btn--ghost', ['to' => 'Archived']); ?>
            <?php echo inbox_button((int) $open['id'], 'delete', 'Delete', 'btn--danger', [], 'Delete this message for good?'); ?>
        </div>
    </article>
<?php endif; ?>

<nav class="tabs" aria-label="Filter messages">
    <?php foreach ($tabs as $k => $label): ?>
        <a href="admin_inbox.php?tab=<?php echo $k; ?>"<?php echo $tab === $k ? ' aria-current="page"' : ''; ?>><?php echo $label; ?>
            <?php if ($k === 'New' && !empty($counts['New'])): ?> <span class="count"><?php echo (int) $counts['New']; ?></span><?php endif; ?></a>
    <?php endforeach; ?>
</nav>

<form class="toolbar" method="GET" action="admin_inbox.php" role="search">
    <input type="hidden" name="tab" value="<?php echo h($tab); ?>">
    <div class="toolbar__search"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <input class="input input--plain" type="search" name="q" value="<?php echo h($q); ?>" placeholder="Search name, email or message" aria-label="Search messages"></div>
</form>

<div class="table-wrap glass">
    <table class="table">
        <thead><tr><th>From</th><th>About</th><th>Message</th><th>Received</th><th class="table__actions">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($messages as $m): ?>
            <tr class="<?php echo $m['status'] === 'New' ? 'is-new' : ''; ?>">
                <td><b><?php echo h($m['name']); ?></b><br><span class="muted"><?php echo h($m['email']); ?></span></td>
                <td><?php echo h(INBOX_SUBJECTS[$m['subject']] ?? $m['subject']); ?><?php if ($m['status'] === 'Archived'): ?><br><span class="muted">Archived</span><?php endif; ?></td>
                <td class="table__note"><?php echo h(strlen($m['message']) > 90 ? substr($m['message'], 0, 90) . '...' : $m['message']); ?></td>
                <td><?php echo h(date('j M, g:i A', strtotime($m['created_at']))); ?></td>
                <td class="table__actions"><a class="btn <?php echo $m['status'] === 'New' ? 'btn--glow' : 'btn--ghost'; ?> btn--xs" href="admin_inbox.php?open=<?php echo (int) $m['id']; ?>&amp;tab=<?php echo h($tab); ?>">Open</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$messages): ?><tr><td colspan="5" class="table__empty"><?php echo $q !== '' ? 'No message matches that search.' : 'No messages here.'; ?></td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<?php include 'includes/dash_footer.php'; ?>
