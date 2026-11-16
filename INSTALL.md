# Install VideoTube — PHP 8.4 and newer

## Requirements

PHP 8.4+ with PDO MySQL, Fileinfo and `proc_open`; MySQL 8.4 is exercised in CI. FFmpeg/FFprobe must support libx264, AAC and JPEG. The installer checks the executable versions and encoders. Use HTTPS and a dedicated service/database account.

## Fresh installation

1. Configure Apache or Nginx restrictions below, PHP and TLS. Create an empty database with a temporary account allowed CREATE, DROP, SELECT, INSERT, UPDATE and DELETE. DROP allows cleanup on failed setup.
2. Set `VIDEOTUBE_INSTALL_TOKEN` to a private random secret of at least 32 characters (`php -r 'echo bin2hex(random_bytes(32));'`). Temporarily allow PHP to write to `includes/`.
3. Open `/install.php`, supply database details, FFmpeg/FFprobe absolute paths and the first administrator account. Optionally name a media storage directory outside the web root (see Media storage); it must exist and be writable by PHP. The first administrator is trusted as the bootstrap owner; ordinary signups must verify their email.
4. Setup refuses existing tables, locks concurrent attempts, and removes newly created tables on failure. Configuration is staged privately and renamed to `includes/local.php` only after database setup succeeds. Inspect logs if rollback lacks permissions or otherwise fails.
5. Remove the installation token. Make `includes/` read-only to PHP, retaining read access to local.php. Revoke CREATE and DROP database privileges. Keep `uploads/` writable. Never commit local.php or backups.
6. Configure email and the worker below. Sign in; administration is at `/admin.php`. The dashboard manages users, roles, suspension, videos, comments and audit history. Removing a video/comment deletes its database records. Protected media files remain for backup recovery until `bin/cleanup.php` purges them; they are no longer reachable through application endpoints. Keep at least one active administrator.

## Media storage

Uploaded videos, thumbnails, profile pictures and queued sources live under one storage directory: `uploads/` inside the application by default. Prefer a directory outside the web root, so the files are reachable only through `stream.php`, `thumbnail.php` and `avatar.php` even if a web-server rule is missing. Give it in the installer, or set `VIDEOTUBE_STORAGE=/absolute/path` (the environment variable wins over `storage` in local.php). To move an existing installation, stop the worker, move the contents of `uploads/` to the new directory, set the variable for both PHP and the worker, and start again; stored paths do not change. Stored files have unguessable names.

Public media may be cached by browsers for an hour and is revalidated with ETag/Last-Modified; private media is revalidated on every use. PHP can hand the transfer to the web server after authorising it: for Nginx set `VIDEOTUBE_ACCEL_REDIRECT=/protected-media/` and enable the internal location in `deploy/nginx.conf.example`; for Apache with mod_xsendfile set `VIDEOTUBE_XSENDFILE=1` and allow the storage path in `XSendFilePath`.

`php bin/cleanup.php` lists stored files that no video, thumbnail, account or waiting job refers to and that are older than 30 days (`--days=N` changes the age). Nothing is deleted without `--delete`. Run it during maintenance after you no longer need removed media for recovery.

## Email verification and password recovery

Set `VIDEOTUBE_BASE_URL=https://your-host/path` (no trailing slash required) and `VIDEOTUBE_MAIL_FROM=noreply@your-host`. Configure PHP's mail transport to a working local MTA/SMTP relay; configure SPF/DKIM at the mail provider. No browser-supplied host is used in email links.

`/account.php` provides verification and password reset. A verification link is sent automatically at sign-up; `/account.php` can send it again. Verification is required before uploading, voting, commenting or subscribing. Changing the email address requires the current password, clears verification, sends a new verification link to the new address and a notice to the old one. Reset links expire after one hour, verification links after 24 hours; both require a confirmation POST and become unusable after consumption. Tokens are stored only as hashes. Password changes/resets invalidate existing sessions; suspending a user invalidates sessions even if the user is later restored.

The `VIDEOTUBE_ENV=test` and `VIDEOTUBE_TEST_MAIL_DIR` outbox transport exists solely for automated tests and writes outside the document root. Never set these on production. Confirm actual email delivery before opening registration. Mail failures are recorded in server logs; visitors receive the same response for known/unknown addresses.

## Background uploads

Web requests validate and queue uploads; `/jobs.php` shows progress. Run `php bin/worker.php --once` to process one job or `php bin/worker.php` to process up to 100 jobs and exit. Schedule the latter every minute using cron, or adapt the supplied systemd service/timer examples. Use the same configuration, filesystem and service user as PHP. Do not use PHP's development server in production.

Jobs are database-backed. A database advisory lock permits one worker per database; a restarted worker resumes an interrupted processing job. A unique job reference on videos prevents publication twice after a crash. Sources are deleted after success/failure. Failed jobs show the reason (unsupported file, no video stream, too long, conversion deadline, output too large); users can upload again. A job interrupted three times, for example by a worker that is killed, is abandoned so it cannot block the queue. Each account may have three pending jobs. Three thumbnail candidates are generated; the uploader chooses one on the edit page. Keep uploads on persistent shared storage at a stable absolute path across releases; include both database and files in backups.

Limits: 50,000,000-byte input, four-hour duration, 100,000,000-byte output cap and maximum 1280×720 dimensions. The conversion deadline is three times the video's length plus two minutes, capped by `VIDEOTUBE_MAX_TRANSCODE_SECONDS` (default 3600); conversions that exceed it fail cleanly. Profile pictures are limited to 2 MB and re-encoded to 256×256 JPEG. Scale these limits and the worker architecture only after measuring host capacity. Set PHP `upload_max_filesize=50M`, `post_max_size=52M`; matching web-server request limits are required. Keep FFmpeg patched and isolate its process with OS disk/CPU/memory/network limits. Use `bin/cleanup.php` to review and purge orphaned files; never remove files referenced by videos or active jobs by hand.

## Upgrading an installation

After deploying a new release, stop the worker and run `php bin/migrate.php --admin=YOUR_ADMIN_USERNAME --dry-run`, then `--backup-confirmed`, with ALTER and INDEX privileges. Schema version 2 adds lookup indexes and the upload attempt counter that the worker needs. The steps are repeatable; a database that is already current reports only harmless re-checks.

The application now runs every database session in UTC. If your MySQL server was not on UTC, timestamps stored before the upgrade keep their old local clock time, so "5 hours ago" style labels on older comments are off by that offset. New rows are correct.

## Existing databases

The CLI migration preserves user/video records and password hashes; it creates missing tables and columns, sets required defaults/indexes and converts legacy core tables to InnoDB. It does not import phpLD data.

1. Stop web traffic and all workers. Back up and test restoration of the database, local.php and uploads together. Migration uses DDL, which MySQL does not roll back atomically.
2. Configure the existing connection via local.php or `VIDEOTUBE_DSN`, `VIDEOTUBE_DB_USER`, `VIDEOTUBE_DB_PASSWORD`. Temporarily grant ALTER, CREATE and INDEX plus normal data privileges.
3. Run `php bin/migrate.php --admin=EXACT_EXISTING_USERNAME --dry-run`. This prints the plan without changing data and rejects unsupported schemas, duplicate accounts/votes/subscriptions and overlong email addresses. Resolve reported data issues explicitly; no duplicates are silently deleted.
4. Run `php bin/migrate.php --admin=EXACT_EXISTING_USERNAME --backup-confirmed`. The chosen user becomes the active, verified administrator. If a DDL step fails, inspect the log, repair the prerequisite and rerun; completed additive steps are detected. Do not blindly restore only one component of a live system.
5. Revoke migration privileges, restart workers, run staging checks, then reopen traffic. Existing users must sign in again and verify email. Old SHA-512 hashes upgrade after successful login. Existing local MP4 files/thumbnails remain protected by the media endpoints; re-upload unsupported legacy media to transcode it.

## Web servers and operations

Apache 2.4: enable mod_rewrite and the root `.htaccess` with `AllowOverride FileInfo Options AuthConfig`. Nginx: include `deploy/nginx.conf.example` before generic PHP handling, adapting path prefixes if hosted below a subdirectory. The shipped example assumes the domain root. Verify `/includes/local.php`, `/database/schema.sql`, `/bin/worker.php`, `/uploads/videos/example.mp4`, `/composer.json` and `/.git/config` return 403/404. CI exercises these restrictions against Apache and Nginx.

Plain HTTP is rejected except loopback requests to PHP's development server. Behind a TLS-terminating proxy, set `VIDEOTUBE_HTTPS=1` only in an inaccessible backend whose proxy enforces HTTPS. Client forwarded headers are ignored unless the request comes from an address listed in `VIDEOTUBE_TRUSTED_PROXIES` (comma-separated proxy addresses); then the right-most untrusted `X-Forwarded-For` address is used for rate limiting. Without it, every visitor behind your proxy shares one rate limit. 

Security behaviour worth knowing: every page sends a Content-Security-Policy that allows only same-origin scripts, styles, images, media and fonts, so jQuery and Bootstrap are served from `assets/vendor/` and pages contain no inline script. POST requests are limited per client address (10 per minute for sign-in, sign-up, uploads and account help; 120 elsewhere). Ten failed sign-ins for one account within 15 minutes lock sign-in for that account until the window passes; a password reset is unaffected. A request body over `post_max_size` is answered with 413.

Users can delete their own comments, videos and account (password required). The only active administrator cannot delete their account. Use protected PHP session storage, server-side logs, encrypted backups and tested restores. Monitor queue backlog, failed mail/uploads, error rates, disk space and database health.

CI runs a PSR-12 style check (`composer lint`; `composer format` fixes), PHP 8.4/8.5 regressions, rejects PHP 8.3, exercises fresh installation/rollback, a representative legacy migration, account recovery/verification, authorization/moderation and queued FFmpeg processing. These are disposable staging tests. Production DNS, certificates, email deliverability, cron/service operation, capacity and backup restoration still must be checked on the actual target host.
