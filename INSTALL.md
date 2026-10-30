# Fresh installation (PHP 8.4+)

1. Upload the application to a PHP-capable host. Enable PDO MySQL, exec and shell_exec. Install FFmpeg and FFprobe, and obtain their absolute executable paths.
2. Create an **empty** MySQL/MariaDB database and an account with CREATE, SELECT, INSERT, UPDATE and DELETE privileges on it.
3. Set `VIDEOTUBE_INSTALL_TOKEN` to a cryptographically random secret (at least 32 characters), e.g. the output of `php -r 'echo bin2hex(random_bytes(32));'`. Keep it private. Use HTTPS for setup.
4. Temporarily allow the PHP service to write to `includes/`. Open `/install.php`, enter your token, database details, first account and media executable paths.
5. The installer creates all tables and categories, creates the first account, and saves private connection settings in `includes/local.php`. Its existence disables further installation. Remove the environment token and make `includes/` read-only again. Keep `uploads/videos/` and its `thumbnails/` directory writable by PHP.
6. Sign in. This project currently has no separate administrator role or dashboard.

Never delete local.php to rerun installation against an existing database. The installer refuses any database containing tables. MySQL schema changes are not transactional: if setup fails partway, inspect the server log and retry with a new empty database. Preserve the old database for recovery.

For manual setup, import `database/schema.sql` (not the legacy demo dump), configure VIDEOTUBE_DSN, VIDEOTUBE_DB_USER and VIDEOTUBE_DB_PASSWORD, and create your account via signUp.php. Set VIDEOTUBE_FFMPEG and VIDEOTUBE_FFPROBE to executable paths.

Existing installations: this installer is not an upgrade/migration tool. Back up your database and do not import the clean schema over it.

The installer checks executable paths, not codec support. Perform a small video upload after setup. The legacy upload processor still needs conversion/rollback improvements before relying on non-MP4 uploads in production.
