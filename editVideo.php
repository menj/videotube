<?php
require_once __DIR__ . '/includes/config.php';

$owner = requireLogin();
$id = filter_input(INPUT_GET, 'videoId', FILTER_VALIDATE_INT) ?: 0;
try {
    $video = new Video($conn, $id, null);
} catch (OutOfBoundsException $e) {
    http_response_code(404);
    exit('Video not found.');
}
if ($video->getVideoUploadedBy() !== $owner) {
    http_response_code(403);
    exit('Not your video.');
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && inputText('action', $_POST) === 'delete') {
    $conn->beginTransaction();
    ContentRemoval::video($conn, $id);
    $conn->commit();
    header('Location: profile.php?username=' . rawurlencode($owner));
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim(inputText('title', $_POST));
    $description = inputText('description', $_POST);
    $privacy = inputText('privacy', $_POST);
    $category = filter_var($_POST['category'] ?? null, FILTER_VALIDATE_INT);
    $thumbnail = filter_var($_POST['thumbnail'] ?? null, FILTER_VALIDATE_INT);
    $q = $conn->prepare('SELECT id FROM categories WHERE id=?');
    $q->execute([$category ?: 0]);
    if ($title === '' || strlen($title) > 70 || strlen($description) > 1000 || !in_array($privacy, ['0', '1'], true) || !$q->fetchColumn()) {
        http_response_code(422);
        $message = 'Check title, description, category and visibility.';
    } else {
        $conn->beginTransaction();
        $q = $conn->prepare('UPDATE videos SET title=?,description=?,privacy=?,category=? WHERE id=? AND uploadedBy=?');
        $q->execute([$title, $description, $privacy, $category, $id, $owner]);
        if ($thumbnail) {
            // Only switch when the chosen thumbnail belongs to this video.
            $q = $conn->prepare('SELECT id FROM thumbnails WHERE id=? AND videoId=?');
            $q->execute([$thumbnail, $id]);
            if ($q->fetchColumn()) {
                $conn->prepare('UPDATE thumbnails SET selected = (id = ?) WHERE videoId=?')->execute([$thumbnail, $id]);
            }
        }
        $conn->commit();
        header('Location: watch.php?id=' . $id);
        exit;
    }
}

$q = $conn->prepare('SELECT * FROM videos WHERE id=?');
$q->execute([$id]);
$row = $q->fetch(PDO::FETCH_ASSOC);
$q = $conn->prepare('SELECT id,selected FROM thumbnails WHERE videoId=? ORDER BY id');
$q->execute([$id]);
$thumbnails = $q->fetchAll(PDO::FETCH_ASSOC);
$categories = $conn->query('SELECT id,name FROM categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Edit video';
require_once __DIR__ . '/includes/header.php';
?>
<div class="column editVideo">
    <h1>Edit video</h1>
    <?php if ($message !== ''): ?><p class="alert alert-danger" role="alert"><?= h($message) ?></p><?php endif; ?>
    <form method="post">
        <div class="form-group">
            <label for="editTitle">Title</label>
            <input class="form-control" id="editTitle" name="title" maxlength="70" required value="<?= h($row['title']) ?>">
        </div>
        <div class="form-group">
            <label for="editDescription">Description</label>
            <textarea class="form-control" id="editDescription" name="description" maxlength="1000" rows="4"><?= h($row['description']) ?></textarea>
        </div>
        <div class="form-group">
            <label for="editPrivacy">Visibility</label>
            <select class="form-control" id="editPrivacy" name="privacy">
                <option value="0" <?= $row['privacy'] == 0 ? 'selected' : '' ?>>Private</option>
                <option value="1" <?= $row['privacy'] == 1 ? 'selected' : '' ?>>Public</option>
            </select>
        </div>
        <div class="form-group">
            <label for="editCategory">Category</label>
            <select class="form-control" id="editCategory" name="category">
                <?php foreach ($categories as $category): ?>
                <option value="<?= (int)$category['id'] ?>" <?= $category['id'] == $row['category'] ? 'selected' : '' ?>><?= h($category['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if (count($thumbnails) > 1): ?>
        <fieldset class="form-group thumbnailChoices">
            <legend>Thumbnail</legend>
            <?php foreach ($thumbnails as $thumbnail): ?>
            <label>
                <input type="radio" name="thumbnail" value="<?= (int)$thumbnail['id'] ?>" <?= $thumbnail['selected'] ? 'checked' : '' ?>>
                <img src="thumbnail.php?id=<?= $id ?>&amp;thumbnail=<?= (int)$thumbnail['id'] ?>" alt="Thumbnail option">
            </label>
            <?php endforeach; ?>
        </fieldset>
        <?php endif; ?>
        <button class="btn btn-primary">Save changes</button>
        <a class="btn btn-link" href="watch.php?id=<?= $id ?>">Cancel</a>
    </form>

    <form method="post" class="dangerZone" data-confirm="Delete this video with its comments and likes? This cannot be undone.">
        <h2>Delete video</h2>
        <p>Removes the video, its comments and its likes for everyone.</p>
        <button class="btn btn-outline-danger" name="action" value="delete">Delete this video</button>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
