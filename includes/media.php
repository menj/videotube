<?php
/** Settings written by the installer; empty before installation. */
function localSettings(): array
{
    static $local = null;
    return $local ??= (is_file(__DIR__ . '/local.php') ? require __DIR__ . '/local.php' : []);
}

function mediaBinary(string $name): string
{
    $value = getenv('VIDEOTUBE_' . strtoupper($name));
    return $value !== false && $value !== '' ? $value : (localSettings()[strtolower($name)] ?? $name);
}

/**
 * Directory that holds uploaded media. Point VIDEOTUBE_STORAGE (or "storage" in local.php)
 * at a path outside the web root so files are reachable only through the PHP endpoints.
 */
function storageRoot(): string
{
    $configured = getenv('VIDEOTUBE_STORAGE');
    if ($configured === false || $configured === '') {
        $configured = (string)(localSettings()['storage'] ?? '');
    }
    return $configured !== '' ? rtrim($configured, '/') : dirname(__DIR__) . '/uploads';
}

/** Create (if needed) and return a writable directory inside the storage root. */
function storageDirectory(string $area): string
{
    $directory = storageRoot() . '/' . $area;
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Cannot create storage directory.');
    }
    return $directory;
}

/**
 * Resolve a stored path such as "uploads/videos/name.mp4" to a readable file inside the
 * given storage area, or null when it is missing or escapes that area.
 */
function storedFile(?string $relative, string $area): ?string
{
    if (!is_string($relative) || $relative === '') {
        return null;
    }
    $root = realpath(storageRoot() . '/' . $area);
    $path = realpath(storageRoot() . '/' . preg_replace('~^uploads/~', '', $relative));
    if (!$root || !$path || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path) || !is_readable($path)) {
        return null;
    }
    return $path;
}

/**
 * Send a stored file with validators and byte-range support, then stop.
 *
 * Shared media may be kept by the browser for an hour; anything else is revalidated on every
 * use. When the web server can serve protected files itself, PHP only authorises the request:
 * set VIDEOTUBE_ACCEL_REDIRECT to an internal Nginx location (for example "/protected-media/")
 * or VIDEOTUBE_XSENDFILE=1 for Apache mod_xsendfile.
 */
function sendStoredFile(string $path, string $contentType, bool $shared): never
{
    $size = filesize($path);
    $modified = filemtime($path);
    $etag = '"' . dechex($modified) . '-' . dechex($size) . '"';

    header('Content-Type: ' . $contentType);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: ' . ($shared ? 'private, max-age=3600' : 'private, no-cache'));
    header('ETag: ' . $etag);
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modified) . ' GMT');
    header('Accept-Ranges: bytes');
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    while (ob_get_level()) {
        ob_end_clean();
    }
    if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
        http_response_code(304);
        exit;
    }

    $accel = (string)getenv('VIDEOTUBE_ACCEL_REDIRECT');
    $root = realpath(storageRoot());
    if ($accel !== '' && $root && str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
        $relative = implode('/', array_map('rawurlencode', explode('/', substr($path, strlen($root) + 1))));
        header('X-Accel-Redirect: ' . rtrim($accel, '/') . '/' . $relative);
        exit;
    }
    if (getenv('VIDEOTUBE_XSENDFILE') === '1') {
        header('X-Sendfile: ' . $path);
        exit;
    }

    // A range is honoured only for the version the client already holds part of.
    $rangeHeader = $_SERVER['HTTP_RANGE'] ?? null;
    $ifRange = trim((string)($_SERVER['HTTP_IF_RANGE'] ?? ''));
    if ($ifRange !== '' && $ifRange !== $etag) {
        $rangeHeader = null;
    }
    try {
        $range = MediaRange::parse($rangeHeader, $size);
    } catch (RangeException $e) {
        http_response_code(416);
        header('Content-Range: bytes */' . $size);
        exit;
    }
    [$start, $end] = $range ?? [0, $size - 1];
    if ($range !== null) {
        http_response_code(206);
        header("Content-Range: bytes $start-$end/$size");
    }
    $remaining = $end - $start + 1;
    header('Content-Length: ' . $remaining);
    if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
        exit;
    }
    $handle = fopen($path, 'rb');
    fseek($handle, $start);
    while ($remaining > 0 && !feof($handle) && !connection_aborted()) {
        $chunk = fread($handle, min(65536, $remaining));
        if ($chunk === false || $chunk === '') {
            break;
        }
        echo $chunk;
        $remaining -= strlen($chunk);
    }
    fclose($handle);
    exit;
}

/** Send a file bundled with the application (not from media storage) and stop. */
function sendBundledFile(string $path, string $contentType): never
{
    header('Content-Type: ' . $contentType);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=3600');
    header('Content-Length: ' . filesize($path));
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    while (ob_get_level()) {
        ob_end_clean();
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') {
        readfile($path);
    }
    exit;
}
