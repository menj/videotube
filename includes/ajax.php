<?php

// Shared entry checks for the POST endpoints in /ajax. Endpoints receive validated values in
// $viewer, $viewerObj, $videoId, $video and $commentId instead of reading raw request input.
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$endpoint = basename($_SERVER['SCRIPT_FILENAME'], '.php');
$readOnly = in_array($endpoint, ['getCommentReplies', 'getComments'], true);
$needsVideo = $endpoint !== 'subscribe';
$needsComment = in_array($endpoint, ['likeComment', 'disLikeComment', 'getCommentReplies', 'deleteComment'], true);

$viewer = $readOnly ? (string)($_SESSION['userLoggedIn'] ?? '') : requireVerified($conn);
$viewerObj = new User($conn, $viewer);
$videoId = 0;
$commentId = 0;
$video = null;

if ($needsVideo) {
    if (!isset($_POST['videoId'])) {
        http_response_code(400);
        exit;
    }
    $videoId = filter_var($_POST['videoId'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
    try {
        $video = new Video($conn, $videoId, $viewerObj);
    } catch (OutOfBoundsException $e) {
        http_response_code(404);
        exit;
    }
    if (!$video->canView($viewer)) {
        http_response_code(404);
        exit;
    }
}

if ($needsComment) {
    if (!isset($_POST['commentId'])) {
        http_response_code(400);
        exit;
    }
    $commentId = filter_var($_POST['commentId'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
    $q = $conn->prepare('SELECT id FROM comments WHERE id=? AND videoId=?');
    $q->execute([$commentId, $videoId]);
    if (!$q->fetchColumn()) {
        http_response_code(404);
        exit;
    }
}

// Serialize account mutations across sessions; the endpoint commits after completing its writes.
if (!$readOnly) {
    $conn->beginTransaction();
    $q = $conn->prepare('SELECT id FROM users WHERE username=? AND suspended=0 AND emailVerifiedAt IS NOT NULL FOR UPDATE');
    $q->execute([$viewer]);
    if (!$q->fetchColumn()) {
        $conn->rollBack();
        http_response_code(403);
        exit('Verified active account required.');
    }
}
