<?php
require_once __DIR__ . '/runtime.php';
ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
date_default_timezone_set('Asia/Kolkata');
// Original database defaults remain supported. Environment variables allow CI/hosting configuration.
$conn = new PDO(
    getenv('VIDEOTUBE_DSN') ?: 'mysql:host=localhost;dbname=VideoTube;charset=utf8mb4',
    getenv('VIDEOTUBE_DB_USER') ?: 'root',
    getenv('VIDEOTUBE_DB_PASSWORD') !== false ? getenv('VIDEOTUBE_DB_PASSWORD') : '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
