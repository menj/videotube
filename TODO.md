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
- [ ] Verify the real production host: HTTPS, mail delivery, worker scheduling, resource limits and backup restoration (requires target-host access)
