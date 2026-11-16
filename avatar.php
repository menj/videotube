<?php

// Profile pictures are public; the request needs no session lock.
const VIDEOTUBE_SESSION_READONLY = true;
require_once __DIR__ . '/includes/config.php';

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    exit;
}
$name = inputText('f', $_GET);
$path = preg_match('/^[a-f0-9]{32}$/D', $name) ? storedFile('avatars/' . $name . '.jpg', 'avatars') : null;
if ($path === null) {
    sendBundledFile(__DIR__ . '/' . AvatarStore::DEFAULT_PICTURE, 'image/png');
}
sendStoredFile($path, 'image/jpeg', true);
