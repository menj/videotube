<?php
require_once __DIR__ . '/../includes/ajax.php';

echo (new Comment($conn, $commentId, $viewerObj, $videoId))->like();
$conn->commit();
