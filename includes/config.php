<?php
require_once __DIR__ . '/runtime.php';
ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
date_default_timezone_set('Asia/Kolkata');
// Original database defaults remain supported. Environment variables allow CI/hosting configuration.
$local = is_file(__DIR__ . '/local.php') ? require __DIR__ . '/local.php' : [];
$dsn = getenv('VIDEOTUBE_DSN');
$user = getenv('VIDEOTUBE_DB_USER');
$password = getenv('VIDEOTUBE_DB_PASSWORD');
$conn = new PDO(
    $dsn !== false ? $dsn : ($local['dsn'] ?? 'mysql:host=localhost;dbname=VideoTube;charset=utf8mb4'),
    $user !== false ? $user : ($local['user'] ?? 'root'),
    $password !== false ? $password : ($local['password'] ?? ''),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
