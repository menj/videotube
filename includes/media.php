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
