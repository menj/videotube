<?php
require __DIR__ . '/includes/config.php';

$actor = requireAdmin($conn);
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        (new Moderation($conn))->apply($actor, inputText('action', $_POST), (int)filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT));
        $message = 'Action completed.';
    } catch (RuntimeException | InvalidArgumentException $e) {
        http_response_code(422);
        $message = $e->getMessage();
    }
    requireAdmin($conn);
}

const ADMIN_PAGE_SIZE = 50;
$sections = [
    'users' => 'SELECT id,username,email,role,suspended,emailVerifiedAt FROM users',
    'videos' => 'SELECT id,title,uploadedBy,privacy FROM videos',
    'comments' => 'SELECT id,postedBy,videoId,body FROM comments',
    'jobs' => 'SELECT id,username,status,error,createdAt FROM upload_jobs',
    'audit' => 'SELECT id,actor,action,target,createdAt FROM moderation_log',
];
$section = inputText('section', $_GET);
if (!isset($sections[$section])) {
    $section = 'users';
}
$page = max(1, (int)filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT));
$offset = min($page - 1, 100000) * ADMIN_PAGE_SIZE;
// One extra row tells us whether a next page exists.
$rows = $conn->query($sections[$section] . ' ORDER BY id DESC LIMIT ' . (ADMIN_PAGE_SIZE + 1) . " OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);
$hasNext = count($rows) > ADMIN_PAGE_SIZE;
$rows = array_slice($rows, 0, ADMIN_PAGE_SIZE);

$actionsFor = static fn(array $row): array => match ($section) {
    'users' => [$row['suspended'] ? 'restore' : 'suspend', $row['role'] === 'admin' ? 'demote' : 'promote'],
    'videos' => ['remove_video'],
    'comments' => ['remove_comment'],
    default => [],
};
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="<?= h(csrfToken()) ?>">
<title>Administration — VideoTube</title>
<link rel="stylesheet" href="assets/CSS/fonts.css">
<link rel="stylesheet" href="assets/CSS/standalone.css">
<script src="assets/vendor/jquery-3.7.1.min.js" defer></script>
<script src="assets/JS/commonAction.js" defer></script>
</head>
<body class="standalone wide">
<h1>Administration</h1>
<nav>
    <a href="index.php">Home</a>
    <?php foreach (array_keys($sections) as $tab): ?>
    <a href="?section=<?= h($tab) ?>"<?= $tab === $section ? ' aria-current="page"' : '' ?>><?= h(ucfirst($tab)) ?></a>
    <?php endforeach; ?>
</nav>
<p role="status"><?= h($message) ?></p>
<p>Content removal is permanent in the database. Stored media remains protected until <code>bin/cleanup.php</code> purges it.</p>
<table>
<?php if ($rows): ?>
    <thead><tr>
        <?php foreach (array_keys($rows[0]) as $key): ?><th><?= h($key) ?></th><?php endforeach; ?>
        <th>Actions</th>
    </tr></thead>
<?php endif; ?>
    <tbody>
    <?php foreach ($rows as $row): ?>
    <tr>
        <?php foreach ($row as $value): ?><td><?= h($value) ?></td><?php endforeach; ?>
        <td>
        <?php foreach ($actionsFor($row) as $action): ?>
            <form method="post" data-confirm="Confirm this action?">
                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                <button name="action" value="<?= h($action) ?>"><?= h(ucwords(str_replace('_', ' ', $action))) ?></button>
            </form>
        <?php endforeach; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php if (!$rows): ?><p>Nothing to show.</p><?php endif; ?>
<p>
    <?php if ($page > 1): ?><a href="?section=<?= h($section) ?>&amp;page=<?= $page - 1 ?>">Previous</a><?php endif; ?>
    <?php if ($hasNext): ?><a href="?section=<?= h($section) ?>&amp;page=<?= $page + 1 ?>">Next</a><?php endif; ?>
</p>
</body>
</html>
