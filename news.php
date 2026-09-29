<?php
// news.php - Medical research list, or one full article with ?id=
require_once 'db.php';
require_once 'includes/components.php';

$id       = (int) ($_GET['id'] ?? 0);
$category = $_GET['category'] ?? '';
$allowed  = ['News', 'Invention', 'Research'];
if (!in_array($category, $allowed, true)) {
    $category = '';
}

$article = null;
$articles = [];
try {
    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT * FROM medical_news WHERE id = ?');
        $stmt->execute([$id]);
        $article = $stmt->fetch() ?: null;
    }
    if (!$article) {
        if ($category !== '') {
            $stmt = $pdo->prepare('SELECT * FROM medical_news WHERE category = ? ORDER BY published_date DESC');
            $stmt->execute([$category]);
        } else {
            $stmt = $pdo->query('SELECT * FROM medical_news ORDER BY published_date DESC');
        }
        $articles = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log('News query failed: ' . $e->getMessage());
}

if ($id > 0 && !$article) {
    http_response_code(404);
}

$page_title = ($article ? $article['title'] . ' | ' : 'Medical research | ') . 'CARE Group';
$nav_active = 'research';
include 'includes/header.php';
?>

<?php if ($article): ?>
    <article class="article">
        <a class="console__back" href="news.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> All articles</a>
        <header class="enter">
            <span class="tag tag--<?php echo h(strtolower($article['category'])); ?>"><?php echo h($article['category']); ?></span>
            <h1><?php echo h($article['title']); ?></h1>
            <p class="article__meta">
                By <?php echo h($article['author']); ?>, <time datetime="<?php echo h($article['published_date']); ?>"><?php echo h(date('j F Y', strtotime($article['published_date']))); ?></time>
            </p>
            <p class="article__summary"><?php echo h($article['summary']); ?></p>
        </header>
        <div class="article__body glass" data-reveal>
            <?php foreach (preg_split('/\n\s*\n/', trim($article['content'])) as $para): ?>
                <p><?php echo nl2br(h($para)); ?></p>
            <?php endforeach; ?>
        </div>
    </article>

<?php else: ?>
    <section class="page-head">
        <h1 class="enter">Medical research</h1>
        <p class="page-head__lede">New treatments, inventions and health news, published by the CARE Group team.</p>
        <?php if ($id > 0): ?><p class="results-count">That article was not found. Here are the latest ones instead.</p><?php endif; ?>
    </section>

    <section class="section section--tight">
        <nav class="tabs" aria-label="Filter by category">
            <a href="news.php"<?php echo $category === '' ? ' aria-current="page"' : ''; ?>>All</a>
            <?php foreach ($allowed as $cat): ?>
                <a href="news.php?category=<?php echo $cat; ?>"<?php echo $category === $cat ? ' aria-current="page"' : ''; ?>><?php echo $cat; ?></a>
            <?php endforeach; ?>
        </nav>

        <div class="news-grid">
            <?php foreach ($articles as $n): ?>
                <a class="news-card glass" href="news.php?id=<?php echo (int) $n['id']; ?>" data-tilt="6" data-reveal>
                    <span class="tag tag--<?php echo h(strtolower($n['category'])); ?>" data-depth><?php echo h($n['category']); ?></span>
                    <h2><?php echo h($n['title']); ?></h2>
                    <p><?php echo h($n['summary']); ?></p>
                    <time datetime="<?php echo h($n['published_date']); ?>"><?php echo h(date('j M Y', strtotime($n['published_date']))); ?></time>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (!$articles): ?>
            <div class="empty glass">
                <i class="fa-solid fa-flask" aria-hidden="true"></i>
                <p>No articles in this category yet.</p>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>