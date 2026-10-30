<?php
require_once 'includes/config.php';
require_once 'includes/classes/Video.php';
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET','HEAD'], true)) { http_response_code(405); header('Allow: GET, HEAD'); exit; }
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
try { $video = new Video($conn,$id ?: 0,null); } catch (OutOfBoundsException $e) { http_response_code(404); exit; }
if (!$video->canView($_SESSION['userLoggedIn'] ?? '')) { http_response_code(404); exit; }
$thumbId = filter_input(INPUT_GET, 'thumbnail', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($thumbId && ($video->getVideoUploadedBy() === ($_SESSION['userLoggedIn'] ?? ''))) {
    $query = $conn->prepare('SELECT filePath FROM thumbnails WHERE videoId=? AND id=?');
    $query->execute([$id, $thumbId]);
} else {
    $query = $conn->prepare('SELECT filePath FROM thumbnails WHERE videoId=? AND selected=1 LIMIT 1');
    $query->execute([$id]);
}
$relative = $query->fetchColumn();
$root = realpath(__DIR__ . '/uploads/videos/thumbnails');
$path = is_string($relative) ? realpath(__DIR__ . '/' . $relative) : false;
if (!$root || !$path || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) { http_response_code(404); exit; }
header('Content-Type: image/jpeg'); header('X-Content-Type-Options: nosniff'); header('Cache-Control: private, no-store');
header('Content-Length: ' . filesize($path));
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
while (ob_get_level()) ob_end_clean();
if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') readfile($path);
