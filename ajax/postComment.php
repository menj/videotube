<?php
require_once __DIR__ . '/../includes/ajax.php';

$commentText = inputText('commentText', $_POST);
if (trim($commentText) === '' || strlen($commentText) > 10000) {
    http_response_code(422);
    exit('Write a comment of up to 10,000 characters.');
}

// Replies are one level deep and must belong to a top-level comment on the same video.
$responseTo = $_POST['responseTo'] ?? 0;
$responseTo = ($responseTo === '' || $responseTo === null) ? 0 : filter_var($responseTo, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
if ($responseTo === false) {
    http_response_code(422);
    exit;
}
if ($responseTo > 0) {
    $parent = $conn->prepare('SELECT id FROM comments WHERE id=? AND videoId=? AND responseTo=0');
    $parent->execute([$responseTo, $videoId]);
    if (!$parent->fetchColumn()) {
        http_response_code(422);
        exit;
    }
}

$query = $conn->prepare('INSERT INTO comments(postedBy, videoId, responseTo, body) VALUES(?, ?, ?, ?)');
$query->execute([$viewer, $videoId, $responseTo, $commentText]);
$comment = (new CommentRepository($conn, $viewerObj))->find($videoId, (int)$conn->lastInsertId());
$conn->commit();

echo $comment->create();
