<?php
// Media requests only read the session, so they must not hold its lock while the file is sent.
const VIDEOTUBE_SESSION_READONLY = true;
require_once __DIR__ . '/includes/config.php';

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    exit;
}
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
try {
    $video = new Video($conn, $id ?: 0, null);
} catch (OutOfBoundsException $e) {
    http_response_code(404);
    exit;
}
$viewer = $_SESSION['userLoggedIn'] ?? '';
if (!$video->canView($viewer)) {
    http_response_code(404);
    exit;
}

// The owner may preview any candidate; everyone else gets the selected one.
$thumbId = filter_input(INPUT_GET, 'thumbnail', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($thumbId && $video->getVideoUploadedBy() === $viewer) {
    $query = $conn->prepare('SELECT filePath FROM thumbnails WHERE videoId=? AND id=?');
    $query->execute([$id, $thumbId]);
} else {
    $query = $conn->prepare('SELECT filePath FROM thumbnails WHERE videoId=? AND selected=1 LIMIT 1');
    $query->execute([$id]);
}
$relative = $query->fetchColumn();
$path = storedFile(is_string($relative) ? $relative : null, 'videos/thumbnails');
if ($path === null) {
    // Videos without a thumbnail show the site logo, so listings never need to check first.
    sendBundledFile(__DIR__ . '/assets/images/icons/VideoTubeLogo.png', 'image/png');
}
sendStoredFile($path, 'image/jpeg', (int)$video->getVideoPrivacy() === 1);
