<?php
// Keep the guard parseable on older PHP versions to show a useful upgrade message.
if (PHP_VERSION_ID < 80400) {
    if (PHP_SAPI !== 'cli') http_response_code(503);
    exit("VideoTube requires PHP 8.4 or newer.\n");
}
foreach (['pdo', 'pdo_mysql', 'fileinfo'] as $extension) {
    if (!extension_loaded($extension)) {
        if (PHP_SAPI !== 'cli') http_response_code(503);
        exit("VideoTube requires the PHP " . $extension . " extension.\n");
    }
}
