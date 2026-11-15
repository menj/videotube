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
if (!$video->canView($_SESSION['userLoggedIn'] ?? '')) {
    http_response_code(404);
    exit;
}
$path = storedFile($video->getVideoFilePath(), 'videos');
if ($path === null) {
    http_response_code(404);
    exit;
}
sendStoredFile($path, 'video/mp4', (int)$video->getVideoPrivacy() === 1);
