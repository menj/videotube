<?php
require_once __DIR__ . '/../includes/ajax.php';

// Toggles the viewer's subscription to a channel and answers with its new subscriber count.
if (!isset($_POST['userTo'])) {
    http_response_code(400);
    exit('Missing subscription target.');
}
$userTo = inputText('userTo', $_POST);
if ($userTo === $viewer) {
    http_response_code(422);
    exit('You cannot subscribe to yourself.');
}
$target = $conn->prepare('SELECT username FROM users WHERE username=?');
$target->execute([$userTo]);
$userTo = $target->fetchColumn();
if ($userTo === false) {
    http_response_code(404);
    exit;
}

$query = $conn->prepare('DELETE FROM subscribers WHERE userTo=? AND userFrom=?');
$query->execute([$userTo, $viewer]);
if ($query->rowCount() === 0) {
    $conn->prepare('INSERT INTO subscribers(userTo, userFrom) VALUES(?, ?)')->execute([$userTo, $viewer]);
}

$query = $conn->prepare('SELECT COUNT(*) FROM subscribers WHERE userTo=?');
$query->execute([$userTo]);
echo (int)$query->fetchColumn();
$conn->commit();
