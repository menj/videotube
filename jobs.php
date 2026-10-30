<?php
require __DIR__.'/includes/header.php';
$username=requireLogin();
$q=$conn->prepare('SELECT id,status,error,createdAt FROM upload_jobs WHERE username=? ORDER BY id DESC LIMIT 100');$q->execute([$username]);
?>
<div class="column"><h1>Your uploads</h1><p>Uploads are processed in the background. Refresh this page to check progress.</p>
<?php foreach ($q as $job): ?><p>Upload #<?= (int)$job['id'] ?> — <?= h($job['status']) ?> <?= h($job['error']) ?></p><?php endforeach; ?>
<a href="profile.php?username=<?= rawurlencode($username) ?>">Your videos</a></div>
<?php require __DIR__.'/includes/footer.php'; ?>
