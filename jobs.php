<?php
require_once __DIR__ . '/includes/config.php';

$username = requireLogin();
$q = $conn->prepare('SELECT id,status,error,createdAt FROM upload_jobs WHERE username=? ORDER BY id DESC LIMIT 100');
$q->execute([$username]);
$jobs = $q->fetchAll(PDO::FETCH_ASSOC);

// Keep the page current while something is still being processed.
$waiting = array_filter($jobs, static fn(array $job): bool => in_array($job['status'], ['pending', 'processing'], true));
$pageTitle = 'Your uploads';
$pageRefresh = $waiting ? 10 : 0;
require __DIR__ . '/includes/header.php';
?>
<div class="column">
    <h1>Your uploads</h1>
    <p>Uploads are processed in the background.<?= $waiting ? ' This page refreshes while a job is waiting.' : '' ?></p>
    <?php if (!$jobs) :
        ?><p>You have not uploaded anything yet. <a href="upload.php">Upload a video</a></p><?php
    endif; ?>
    <?php foreach ($jobs as $job) : ?>
    <p>Upload #<?= (int)$job['id'] ?> — <?= h($job['status']) ?> <?= h($job['error']) ?></p>
    <?php endforeach; ?>
    <a href="profile.php?username=<?= rawurlencode($username) ?>">Your videos</a>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
