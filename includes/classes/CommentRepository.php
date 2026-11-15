<?php
/**
 * Loads comments together with everything needed to draw them (author picture, vote totals,
 * reply count, the viewer's own vote) in one query per page instead of several per comment.
 */
final class CommentRepository
{
    public const PAGE_SIZE = 20;
    private const REPLY_LIMIT = 500;

    private const SELECT = "SELECT c.*, u.profilePic,
            (SELECT COUNT(*) FROM likes l WHERE l.commentId = c.id) AS likeCount,
            (SELECT COUNT(*) FROM dislikes d WHERE d.commentId = c.id) AS dislikeCount,
            (SELECT COUNT(*) FROM comments r WHERE r.responseTo = c.id) AS replyCount,
            EXISTS(SELECT 1 FROM likes l WHERE l.commentId = c.id AND l.username = ?) AS liked,
            EXISTS(SELECT 1 FROM dislikes d WHERE d.commentId = c.id AND d.username = ?) AS disliked
        FROM comments c LEFT JOIN users u ON u.username = c.postedBy";

    public function __construct(private PDO $db, private User $viewer)
    {
    }

    /**
     * Newest top-level comments of a video, one page at a time.
     *
     * @param int $beforeId return comments older than this id; 0 starts at the newest
     * @return array{0: Comment[], 1: int} the page, and the id to continue from (0 when nothing is left)
     */
    public function page(int $videoId, int $beforeId = 0): array
    {
        $limit = self::PAGE_SIZE + 1;
        $sql = self::SELECT . " WHERE c.videoId = ? AND c.responseTo = 0"
            . ($beforeId > 0 ? " AND c.id < ?" : "") . " ORDER BY c.id DESC LIMIT $limit";
        $parameters = [$this->viewer->getUsername(), $this->viewer->getUsername(), $videoId];
        if ($beforeId > 0) {
            $parameters[] = $beforeId;
        }
        $comments = $this->fetch($sql, $parameters, $videoId);
        $next = 0;
        if (count($comments) > self::PAGE_SIZE) {
            array_pop($comments);
            $next = (int)end($comments)->getId();
        }
        return [$comments, $next];
    }

    /** @return Comment[] replies to one comment, oldest first */
    public function replies(int $videoId, int $commentId): array
    {
        $sql = self::SELECT . " WHERE c.videoId = ? AND c.responseTo = ? ORDER BY c.id ASC LIMIT " . self::REPLY_LIMIT;
        return $this->fetch($sql, [$this->viewer->getUsername(), $this->viewer->getUsername(), $videoId, $commentId], $videoId);
    }

    public function find(int $videoId, int $commentId): ?Comment
    {
        $sql = self::SELECT . " WHERE c.videoId = ? AND c.id = ?";
        return $this->fetch($sql, [$this->viewer->getUsername(), $this->viewer->getUsername(), $videoId, $commentId], $videoId)[0] ?? null;
    }

    /** @return Comment[] */
    private function fetch(string $sql, array $parameters, int $videoId): array
    {
        $query = $this->db->prepare($sql);
        $query->execute($parameters);
        $comments = [];
        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $comments[] = new Comment($this->db, $row, $this->viewer, $videoId);
        }
        return $comments;
    }
}
