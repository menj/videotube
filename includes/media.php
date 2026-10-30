<?php
function mediaBinary(string $name): string {
    $local = is_file(__DIR__ . '/local.php') ? require __DIR__ . '/local.php' : [];
    $key = 'VIDEOTUBE_' . strtoupper($name);
    $value = getenv($key);
    return $value !== false && $value !== '' ? $value : ($local[strtolower($name)] ?? $name);
}
