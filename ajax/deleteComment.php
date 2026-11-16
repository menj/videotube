<?php

require_once __DIR__ . '/../includes/ajax.php';

// Authors may delete their own comments; replies to it go with it.
$q = $conn->prepare('SELECT postedBy FROM comments WHERE id=?');
$q->execute([$commentId]);
if ($q->fetchColumn() !== $viewer) {
    $conn->rollBack();
    http_response_code(403);
    exit('You can only delete your own comments.');
}
ContentRemoval::comment($conn, $commentId);
$conn->commit();
