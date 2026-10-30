<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/User.php';
require_once __DIR__ . '/classes/Video.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
if (basename($_SERVER['SCRIPT_FILENAME']) !== 'getCommentReplies.php') requireLogin();
if (isset($_POST['videoId'])) {
    $id = filter_var($_POST['videoId'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    try { $v = new Video($conn,$id ?: 0,null); }
    catch (OutOfBoundsException $e) { http_response_code(404); exit; }
    if (!$v->canView($_SESSION['userLoggedIn'] ?? '')) { http_response_code(404); exit; }
    if (isset($_POST['commentId'])) {
        $q=$conn->prepare('SELECT id FROM comments WHERE id=? AND videoId=?');
        $q->execute([filter_var($_POST['commentId'],FILTER_VALIDATE_INT) ?: 0,$id]);
        if (!$q->fetchColumn()) { http_response_code(404); exit; }
    }
} elseif (basename($_SERVER['SCRIPT_FILENAME']) !== 'subscribe.php') { http_response_code(400); exit; }
