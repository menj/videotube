<?php
// Class files live in includes/ and includes/classes/, one class per file named after the class.
spl_autoload_register(static function (string $class): void {
    if (!preg_match('/^[A-Za-z0-9_]+$/D', $class)) {
        return;
    }
    foreach ([__DIR__ . '/classes/', __DIR__ . '/'] as $directory) {
        $file = $directory . $class . '.php';
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});
