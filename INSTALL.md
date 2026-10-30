# Installation and deployment (PHP 8.4+)

1. Use PHP 8.4 or newer with PDO MySQL and Fileinfo. Enable `proc_open`. Install FFmpeg/FFprobe with H.264 (`libx264`), AAC and JPEG support. CI tests PHP 8.4/8.5 and MySQL 8.4.
2. Create an **empty** MySQL database. During installation its account needs CREATE, DROP, SELECT, INSERT, UPDATE and DELETE privileges. DROP is needed to clean up a failed installation. After installation revoke CREATE and DROP.
3. Configure HTTPS and the web server restrictions below **before** opening the installer. Set `VIDEOTUBE_INSTALL_TOKEN` to a private random secret of at least 32 characters, generated with `php -r 'echo bin2hex(random_bytes(32));'`.
4. Temporarily allow PHP to write to `includes/`. Open `/install.php` and enter the token, database details, first account, and absolute FFmpeg/FFprobe paths. Passwords must be 12–72 bytes.
5. The installer creates the schema and first account, then atomically publishes private `includes/local.php`. It refuses nonempty databases and concurrent installation. Failed attempts remove only tables they created. Check server logs if cleanup itself fails, for example because DROP permission was missing.
6. Remove the installation token. Make `includes/` read-only to PHP again; retain read access to `local.php`. Keep `uploads/videos/` and `uploads/videos/thumbnails/` writable. Never commit local.php or backups. Do not remove local.php to reinstall.
7. Sign in, upload a short test video, check playback/seek/thumbnail, edit its visibility, and confirm a logged-out browser cannot access a private video.

## Web server

Apache 2.4: enable mod_rewrite and allow the supplied `.htaccess` rules (`AllowOverride FileInfo Options AuthConfig`). The root rules deny direct requests to includes, database, tests, bin, uploads and hidden files. PHP endpoints serve authorized media. **Verify these rules work**: `/database/schema.sql`, `/includes/local.php`, `/uploads/videos/example.mp4` and `/.git/config` must return 403/404.

Nginx: apply `deploy/nginx.conf.example` inside the server block before generic PHP handling. Configure PHP-FPM and TLS for your host. PHP's development server is for testing only and does not enforce `.htaccess`.

The application rejects plain HTTP except loopback. Behind a TLS-terminating reverse proxy, set `VIDEOTUBE_HTTPS=1` only in a backend environment that is inaccessible directly and whose proxy enforces HTTPS. Client-supplied forwarded headers are deliberately not trusted.

Set PHP `upload_max_filesize=50M`, `post_max_size=52M`, `max_execution_time=180` and matching web-server/FPM timeouts. The application accepts files up to 50,000,000 bytes and videos up to four hours; conversion has a 120-second deadline and is intended for small videos. Large/slow conversions fail cleanly. A worker queue is needed for a large video service. Restrict network and filesystem access for the PHP/FFmpeg service account, keep FFmpeg patched, and set disk/CPU/process quotas.

## Operations

Use a dedicated database account, protected PHP session storage and server-side error logs. Back up the database, local.php and uploaded media together, encrypt backups, and test restoration. Monitor HTTP errors, disk space, failed uploads and database availability. Do not expose logs or backups through the document root.

This CMS has ordinary user accounts and owner-only video editing; it does not yet provide administrator moderation, account recovery/email verification or a distributed upload queue. Those are separate product features, not capabilities supplied by this installer. Decide whether they are required before opening registration to an untrusted public audience.

## Existing data

The installer is a fresh-install tool, not a legacy migration. Never import the clean schema over an existing database. Existing deployments need a backed-up, reviewed schema migration (including request_limits and unique vote indexes) before running this version. Legacy SHA-512 passwords are upgraded to modern password hashes after a successful login.

## Tests

`composer test` runs the PHP regression suite. CI also runs `tests/install.php` against a disposable MySQL database, including injected SQL/configuration-publish failures and reinstall rejection. `tests/browser_flow.py` starts a disposable PHP server, installs a separate database, and exercises real upload/transcoding, access checks, CSRF, editing, comments, voting and streaming ranges. Never run integration tests against production data.
