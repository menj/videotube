<?php

require_once('includes/header.php');

$videoId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
try {
    if (!$videoId) {
        throw new OutOfBoundsException('Video not found');
    }
    $video = new Video($conn, $videoId, $userLoggedInObj);
    if (!$video->canView($userLoggedInObj->getUsername())) {
        // Private videos look exactly like missing ones to everyone but their uploader.
        throw new OutOfBoundsException('Video not found');
    }
} catch (OutOfBoundsException $e) {
    http_response_code(404);
    echo 'Video not found';
    require 'includes/footer.php';
    exit;
}
$video->recordView($userLoggedInObj->getUsername());
?>
<div class="watchLeftColumn">
    <?php
    echo (new VideoPlayer($video))->create(true);
    echo (new VideoInfo($conn, $video, $userLoggedInObj))->create();
    echo (new CommentSection($conn, $video, $userLoggedInObj))->create();
    ?>
</div>

<div class="suggestions">
    <?php
    echo (new VideoGrid($conn, $userLoggedInObj))->create(null, null, false);
    ?>
</div>
<?php require_once('includes/footer.php'); ?>
