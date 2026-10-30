<?php
require_once 'includes/config.php';
require_once 'includes/classes/Video.php';
require_once 'includes/classes/MediaRange.php';
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
    http_response_code(405); header('Allow: GET, HEAD'); exit;
}
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
try { $video = new Video($conn,$id ?: 0,null); } catch (OutOfBoundsException $e) { http_response_code(404); exit; }
if (!$video->canView($_SESSION['userLoggedIn'] ?? '')) { http_response_code(404); exit; }
$root = realpath(__DIR__ . '/uploads/videos');
$path = realpath(__DIR__ . '/' . $video->getVideoFilePath());
if (!$root || !$path || !str_starts_with($path, $root . DIRECTORY_SEPARATOR)
    || !is_file($path) || !is_readable($path)) { http_response_code(404); exit; }
$size = filesize($path);
header('Accept-Ranges: bytes');
header('Content-Type: video/mp4');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
try { $range = MediaRange::parse($_SERVER['HTTP_RANGE'] ?? null, $size); }
catch (RangeException $e) { http_response_code(416); header('Content-Range: bytes */' . $size); exit; }
[$start, $end] = $range ?? [0, $size - 1];
if ($range !== null) { http_response_code(206); header("Content-Range: bytes $start-$end/$size"); }
$remaining = $end - $start + 1;
header('Content-Length: ' . $remaining);
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
while (ob_get_level()) ob_end_clean();
if ($_SERVER['REQUEST_METHOD'] === 'HEAD') exit;
$handle = fopen($path, 'rb');
fseek($handle, $start);
while ($remaining > 0 && !feof($handle) && !connection_aborted()) {
    $chunk = fread($handle, min(65536, $remaining));
    if ($chunk === false || $chunk === '') break;
    echo $chunk;
    $remaining -= strlen($chunk);
}
fclose($handle);
