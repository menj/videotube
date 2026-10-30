<?php
require_once __DIR__ . '/includes/header.php';
requireLogin();
$id = filter_input(INPUT_GET, 'videoId', FILTER_VALIDATE_INT) ?: 0;
try { $video = new Video($conn, $id, $userLoggedInObj); }
catch (OutOfBoundsException $e) { http_response_code(404); exit('Video not found.'); }
if ($video->getVideoUploadedBy() !== requireLogin()) { http_response_code(403); exit('Not your video.'); }
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim(inputText('title', $_POST)); $description = inputText('description', $_POST);
    $privacy = inputText('privacy', $_POST); $category = filter_var($_POST['category'] ?? null, FILTER_VALIDATE_INT);
    $q = $conn->prepare('SELECT id FROM categories WHERE id=?'); $q->execute([$category ?: 0]);
    if ($title === '' || strlen($title)>70 || strlen($description)>1000 || !in_array($privacy,['0','1'],true) || !$q->fetchColumn()) {
        http_response_code(422); $message = 'Check title, description, category and visibility.';
    } else {
        $q = $conn->prepare('UPDATE videos SET title=?,description=?,privacy=?,category=? WHERE id=? AND uploadedBy=?');
        $q->execute([$title,$description,$privacy,$category,$id,requireLogin()]);
        header('Location: watch.php?id=' . $id); exit;
    }
}
$q = $conn->prepare('SELECT * FROM videos WHERE id=?'); $q->execute([$id]); $row = $q->fetch(PDO::FETCH_ASSOC);
?>
<div class="column"><h1>Edit video</h1><p role="alert"><?= h($message) ?></p>
<form method="post">
<label>Title <input name="title" maxlength="70" required value="<?= h($row['title']) ?>"></label>
<label>Description <textarea name="description" maxlength="1000"><?= h($row['description']) ?></textarea></label>
<label>Visibility <select name="privacy"><option value="0" <?= $row['privacy']==0?'selected':'' ?>>Private</option><option value="1" <?= $row['privacy']==1?'selected':'' ?>>Public</option></select></label>
<label>Category <select name="category"><?php foreach ($conn->query('SELECT * FROM categories') as $category): ?>
<option value="<?= (int)$category['id'] ?>" <?= $category['id']==$row['category']?'selected':'' ?>><?= h($category['name']) ?></option>
<?php endforeach; ?></select></label><button>Save changes</button></form></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
