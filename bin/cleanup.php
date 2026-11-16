<?php

// Lists (and with --delete removes) stored media that no database record refers to.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/includes/config.php';

$options = getopt('', ['days:', 'delete', 'help']);
if (isset($options['help'])) {
    echo "Usage: php bin/cleanup.php [--days=30] [--delete]\n"
        . "Without --delete this only lists orphaned files older than the given number of days.\n";
    exit(0);
}
$days = isset($options['days']) ? filter_var($options['days'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) : 30;
if ($days === false) {
    fwrite(STDERR, "--days must be a whole number of days.\n");
    exit(1);
}

$orphans = (new StorageCleanup($conn))->orphans($days * 86400);
$bytes = 0;
foreach ($orphans as $path) {
    $bytes += filesize($path);
    echo $path . "\n";
    if (isset($options['delete']) && !unlink($path)) {
        fwrite(STDERR, "Could not delete $path\n");
    }
}
printf(
    "%d orphaned file(s), %.1f MB, %s.\n",
    count($orphans),
    $bytes / 1048576,
    isset($options['delete']) ? 'deleted' : 'not deleted (pass --delete to remove them)'
);
