<?php
require_once __DIR__ . '/../includes/ajax.php';

// Next page of top-level comments, older than the last one already shown.
$before = filter_var($_POST['before'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$before) {
    http_response_code(400);
    exit;
}
echo CommentSection::renderPage(new CommentRepository($conn, $viewerObj), $videoId, $before);
