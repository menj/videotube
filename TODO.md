# Release checklist

- [x] PHP 8.4+ runtime, installer rollback and private configuration
- [x] Administrator roles and moderation dashboard
- [x] Video/comment removal, user suspension and moderation audit log
- [x] Password recovery and email verification with single-use expiring tokens
- [x] Legacy database migration with dry run and data checks
- [x] Database-backed background upload processing and worker scheduling examples
- [x] Supplied Sabon Next LT, Special Elite and Amiri WOFF2 fonts
- [ ] Pass final feature, migration and web-server staging tests
- [ ] Merge the tested PR
- [ ] Verify the real production host: HTTPS, mail delivery, worker scheduling, resource limits and backup restoration (requires target-host access)
