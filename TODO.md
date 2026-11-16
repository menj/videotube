# Release checklist

- [x] PHP 8.4+ runtime, installer rollback and private configuration
- [x] Administrator roles and moderation dashboard
- [x] Video/comment removal, user suspension and moderation audit log
- [x] Password recovery and email verification with single-use expiring tokens
- [x] Legacy database migration with dry run and data checks
- [x] Database-backed background upload processing and worker scheduling examples
- [x] Supplied Sabon Next LT, Special Elite and Amiri WOFF2 fonts
- [x] Pass final feature, migration and web-server staging tests
- Merge status: [release PR #2](https://github.com/menj/videotube/pull/2)
- [x] Review follow-up: reply threading, UTC clock, storage outside the web root, CSP, indexes, cached media, bounded worker, self-service deletion, profile pictures, paging, PSR-12
- [ ] Replace username string keys with numeric user ids and add foreign keys (needs a data migration and an orphan-row policy)
- [ ] Move the remaining string-built HTML (grids, profile, forms) to templates that escape at output
- [ ] Add static analysis (PHPStan) to CI
- [ ] Confirm the licence for the bundled Sabon Next LT fonts allows redistribution in this repository
- [ ] Verify the real production host: HTTPS, mail delivery, worker scheduling, resource limits and backup restoration (requires target-host access)
