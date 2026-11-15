<?php
/**
 * Deletes content together with everything that hangs off it. Callers own the transaction.
 * Media files stay in storage; bin/cleanup.php removes files nothing refers to any more.
 */
final class ContentRemoval
{
    public static function video(PDO $db, int $videoId): bool
    {
        $q = $db->prepare('SELECT id FROM videos WHERE id=? FOR UPDATE');
        $q->execute([$videoId]);
        if (!$q->fetchColumn()) {
            return false;
        }
        foreach (['likes', 'dislikes'] as $table) {
            $db->prepare("DELETE FROM $table WHERE videoId=? OR commentId IN (SELECT id FROM comments WHERE videoId=?)")
                ->execute([$videoId, $videoId]);
        }
        $db->prepare('DELETE FROM comments WHERE videoId=?')->execute([$videoId]);
        $db->prepare('DELETE FROM thumbnails WHERE videoId=?')->execute([$videoId]);
        $db->prepare('DELETE FROM videos WHERE id=?')->execute([$videoId]);
        return true;
    }

    /** Removes a comment, its replies and their votes. */
    public static function comment(PDO $db, int $commentId): bool
    {
        $q = $db->prepare('SELECT id FROM comments WHERE id=? OR responseTo=? FOR UPDATE');
        $q->execute([$commentId, $commentId]);
        $ids = $q->fetchAll(PDO::FETCH_COLUMN);
        if (!$ids) {
            return false;
        }
        $marks = implode(',', array_fill(0, count($ids), '?'));
        foreach (['likes', 'dislikes'] as $table) {
            $db->prepare("DELETE FROM $table WHERE commentId IN ($marks)")->execute($ids);
        }
        $db->prepare("DELETE FROM comments WHERE id IN ($marks)")->execute($ids);
        return true;
    }

    /** Removes an account with its videos, comments, votes, subscriptions, tokens and queued uploads. */
    public static function account(PDO $db, string $username): bool
    {
        // Same lock order as Moderation: all administrators first, so the last one cannot disappear.
        $admins = $db->query("SELECT username,suspended FROM users WHERE role='admin' ORDER BY id FOR UPDATE")->fetchAll(PDO::FETCH_ASSOC);
        $active = array_filter($admins, static fn(array $admin): bool => !$admin['suspended']);
        if (count($active) === 1 && reset($active)['username'] === $username) {
            throw new RuntimeException('You are the only administrator. Promote another administrator before deleting this account.');
        }
        $q = $db->prepare('SELECT id FROM users WHERE username=? FOR UPDATE');
        $q->execute([$username]);
        $userId = $q->fetchColumn();
        if (!$userId) {
            return false;
        }
        $q = $db->prepare('SELECT id FROM videos WHERE uploadedBy=?');
        $q->execute([$username]);
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $videoId) {
            self::video($db, (int)$videoId);
        }
        $q = $db->prepare('SELECT id FROM comments WHERE postedBy=?');
        $q->execute([$username]);
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $commentId) {
            self::comment($db, (int)$commentId);
        }
        foreach (['likes', 'dislikes'] as $table) {
            $db->prepare("DELETE FROM $table WHERE username=?")->execute([$username]);
        }
        $db->prepare('DELETE FROM subscribers WHERE userTo=? OR userFrom=?')->execute([$username, $username]);
        $db->prepare('DELETE FROM account_tokens WHERE userId=?')->execute([$userId]);
        // The worker fails a job whose account is gone and deletes its source file.
        $db->prepare('DELETE FROM users WHERE id=?')->execute([$userId]);
        return true;
    }
}
