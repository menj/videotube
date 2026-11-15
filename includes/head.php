<?php
// Shared <head>. Every asset is served from this origin and nothing is inline, so the
// Content-Security-Policy sent by applicationSecurity() can stay strict.
?>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= h(csrfToken()) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php if (!empty($pageRefresh)): ?>
    <meta http-equiv="refresh" content="<?= (int)$pageRefresh ?>">
<?php endif; ?>
    <title><?= h(isset($pageTitle) ? $pageTitle . ' — VideoTube' : 'VideoTube') ?></title>
    <link href="assets/images/icons/favicon2.jpg" rel="icon">
    <link rel="stylesheet" href="assets/vendor/bootstrap-4.6.2.min.css">
    <link rel="stylesheet" href="assets/CSS/style.css">
    <link rel="stylesheet" href="assets/CSS/fonts.css">
    <script src="assets/vendor/jquery-3.7.1.min.js" defer></script>
    <script src="assets/vendor/bootstrap-4.6.2.bundle.min.js" defer></script>
    <script src="assets/JS/commonAction.js" defer></script>
    <script src="assets/JS/videoPlayerAction.js" defer></script>
    <script src="assets/JS/userAction.js" defer></script>
    <script src="assets/JS/commentAction.js" defer></script>
