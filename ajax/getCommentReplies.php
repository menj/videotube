<?php

require_once __DIR__ . '/../includes/ajax.php';

foreach ((new CommentRepository($conn, $viewerObj))->replies($videoId, $commentId) as $reply) {
    echo $reply->create();
}
