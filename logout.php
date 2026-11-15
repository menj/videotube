<?php
require __DIR__ . '/includes/config.php';

// Signing out changes state, so it happens by POST (with the session token), never by following a link.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Log out — VideoTube</title>
<link rel="stylesheet" href="assets/CSS/fonts.css">
<link rel="stylesheet" href="assets/CSS/standalone.css">
</head>
<body class="standalone narrow">
<h1>Log out</h1>
<form method="post"><button>Log out of VideoTube</button></form>
<p><a href="index.php">Stay signed in</a></p>
</body>
</html>
    <?php
    exit;
}

$_SESSION = [];
$params = session_get_cookie_params();
setcookie(session_name(), '', [
    'expires' => time() - 3600,
    'path' => $params['path'],
    'domain' => $params['domain'],
    'secure' => $params['secure'],
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_destroy();
header('Location: index.php');
