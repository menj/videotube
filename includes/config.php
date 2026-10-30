<?php
require_once __DIR__ . '/runtime.php';
require_once __DIR__ . '/security.php';
applicationSecurity();
date_default_timezone_set('UTC');
$local = is_file(__DIR__ . '/local.php') ? require __DIR__ . '/local.php' : [];
$dsn = getenv('VIDEOTUBE_DSN'); $user = getenv('VIDEOTUBE_DB_USER'); $password = getenv('VIDEOTUBE_DB_PASSWORD');
if ($dsn === false && empty($local['dsn'])) {
    if (PHP_SAPI !== 'cli') { header('Location: install.php'); exit; }
    throw new RuntimeException('Application is not installed.');
}
$conn = new PDO($dsn !== false ? $dsn : $local['dsn'], $user !== false ? $user : ($local['user'] ?? ''),
    $password !== false ? $password : ($local['password'] ?? ''),
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
if (PHP_SAPI !== 'cli' && !empty($_SESSION['userLoggedIn'])) {
    $q=$conn->prepare('SELECT id FROM users WHERE username=?'); $q->execute([$_SESSION['userLoggedIn']]);
    if (!$q->fetchColumn()) unset($_SESSION['userLoggedIn']);
}

if (PHP_SAPI !== 'cli' && $_SERVER['REQUEST_METHOD']==='POST') {
    require_once __DIR__ . '/rate_limit.php';
    $scope=basename($_SERVER['SCRIPT_FILENAME']);
    rateLimit($conn,$scope,in_array($scope,['signIn.php','signUp.php','processing.php'],true)?10:120);
}
