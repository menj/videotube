<?php

require __DIR__ . '/includes/config.php';

$username = requireVerified($conn);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}
$data = new VideoUploadData(
    $_FILES['fileInput'] ?? [],
    inputText('titleInput', $_POST),
    inputText('descriptionInput', $_POST),
    filter_var($_POST['categoryInput'] ?? null, FILTER_VALIDATE_INT) ?: 0,
    inputText('privacyInput', $_POST),
    $username
);
try {
    (new UploadQueue($conn))->enqueue($data);
} catch (InvalidArgumentException $e) {
    // Explain the problem on the upload page instead of a bare error.
    http_response_code(422);
    $pageNotice = $e->getMessage();
    require __DIR__ . '/upload.php';
    exit;
}
header('Location: jobs.php');
