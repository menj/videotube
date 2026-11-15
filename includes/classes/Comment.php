<?php
class Comment
{
    private $conn;
    private $sqlData;
    private $userLoggedInObj;
    private $videoId;

    /**
     * @param array|int|string $input a comment row (see CommentRepository for the fields used to
     *                                 render it) or the id of a comment to vote on
     */
    public function __construct($conn, $input, $userLoggedInObj, $videoId)
    {
        if (!is_array($input)) {
            $query = $conn->prepare("SELECT * FROM comments WHERE id = :id");
            $query->bindParam(":id", $input);
            $query->execute();

            $input = $query->fetch(PDO::FETCH_ASSOC) ?: [];
        }

        $this->sqlData = $input;
        $this->conn = $conn;
        $this->userLoggedInObj = $userLoggedInObj;
        $this->videoId = $videoId;
    }

    public function getId()
    {
        return $this->sqlData["id"];
    }

    public function getVideoId()
    {
        return $this->videoId;
    }

    /** Replies are one level deep: a reply to a reply joins the same thread. */
    public function getThreadId(): int
    {
        $parent = (int)($this->sqlData["responseTo"] ?? 0);
        return $parent > 0 ? $parent : (int)$this->sqlData["id"];
    }

    public function isReply(): bool
    {
        return (int)($this->sqlData["responseTo"] ?? 0) > 0;
    }

    /** Markup for one comment. Expects a row loaded through CommentRepository. */
    public function create(): string
    {
        $id = (int)$this->sqlData["id"];
        $videoId = (int)$this->getVideoId();
        $author = (string)$this->sqlData["postedBy"];
        $viewer = $this->userLoggedInObj->getUsername();
        $profileUrl = "profile.php?username=" . h(rawurlencode($author));
        $picture = h($this->sqlData["profilePic"] ?? "assets/images/profilePictures/default.png");
        $body = h($this->sqlData["body"]);
        $timespan = h($this->time_elapsed_string($this->sqlData["datePosted"]));

        $replies = "";
        if (!$this->isReply()) {
            $count = (int)($this->sqlData["replyCount"] ?? 0);
            $replies = $count > 0
                ? "<span class=\"repliesSection viewReplies\" data-action=\"getReplies\" data-comment-id=\"$id\" data-video-id=\"$videoId\">View all $count replies</span>"
                : "<div class=\"repliesSection\"></div>";
        }

        return "<div class=\"itemContainer\" data-comment-id=\"$id\">
                    <div class=\"comment\">
                        <a href=\"$profileUrl\"><img src=\"$picture\" class=\"profilePicture\" alt=\"\"></a>
                        <div class=\"mainContainer\">
                            <div class=\"commentHeader\">
                                <a href=\"$profileUrl\"><span class=\"username\">" . h($author) . "</span></a>
                                <span class=\"timestamp\">$timespan</span>
                            </div>
                            <div class=\"body\">$body</div>
                        </div>
                    </div>
                    " . $this->createControls($id, $videoId, $author === $viewer && $viewer !== "") . "
                    " . $this->createReplyForm($videoId) . "
                    $replies
                </div>";
    }

    private function createControls(int $id, int $videoId, bool $own): string
    {
        $score = (int)($this->sqlData["likeCount"] ?? 0) - (int)($this->sqlData["dislikeCount"] ?? 0);
        $target = ["comment-id" => $id, "video-id" => $videoId];
        $liked = !empty($this->sqlData["liked"]);
        $disliked = !empty($this->sqlData["disliked"]);

        $controls = ButtonProvider::createButton("REPLY", null, ["action" => "toggleReply"], "")
            . "<span class=\"likesCount\">" . ($score === 0 ? "" : $score) . "</span>"
            . ButtonProvider::createButton(
                "",
                "assets/images/icons/thumb-up" . ($liked ? "-active" : "") . ".png",
                ["action" => "likeComment"] + $target,
                "likeButton"
            )
            . ButtonProvider::createButton(
                "",
                "assets/images/icons/thumb-down" . ($disliked ? "-active" : "") . ".png",
                ["action" => "dislikeComment"] + $target,
                "disLikeButton"
            );
        if ($own) {
            $controls .= ButtonProvider::createButton("DELETE", null, ["action" => "deleteComment"] + $target, "deleteComment");
        }

        return "<div class=\"controls\">$controls</div>";
    }

    private function createReplyForm(int $videoId): string
    {
        $viewer = $this->userLoggedInObj;
        $data = ["action" => "postComment", "video-id" => $videoId, "reply-to" => $this->getThreadId()];

        return "<div class=\"commentForm hidden\">
                    " . ButtonProvider::profileLink($viewer->getUsername(), $viewer->getUserProfilePic()) . "
                    <textarea class=\"commentBodyClass\" placeholder=\"Add a public reply\" maxlength=\"10000\"></textarea>
                    " . ButtonProvider::createButton("Cancel", null, ["action" => "toggleReply"], "cancelComment") . "
                    " . ButtonProvider::createButton("Reply", null, $data, "postComment") . "
                </div>";
    }

    public function time_elapsed_string($datetime, $full = false)
    {
        $now = new DateTime();
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);

        $parts = [
            'y' => $diff->y, 'm' => $diff->m, 'w' => intdiv($diff->d, 7),
            'd' => $diff->d % 7, 'h' => $diff->h, 'i' => $diff->i, 's' => $diff->s,
        ];
        $names = ['y' => 'year', 'm' => 'month', 'w' => 'week', 'd' => 'day', 'h' => 'hour', 'i' => 'minute', 's' => 'second'];

        $string = [];
        foreach ($names as $key => $name) {
            if ($parts[$key]) {
                $string[] = $parts[$key] . ' ' . $name . ($parts[$key] > 1 ? 's' : '');
            }
        }

        if (!$full) {
            $string = array_slice($string, 0, 1);
        }
        return $string ? implode(', ', $string) . ' ago' : 'just now';
    }

    public function wasLiked()
    {
        return $this->hasVote("likes");
    }

    public function wasDisLiked()
    {
        return $this->hasVote("dislikes");
    }

    private function hasVote(string $table): bool
    {
        $query = $this->conn->prepare("SELECT 1 FROM $table WHERE username = ? AND commentId = ?");
        $query->execute([$this->userLoggedInObj->getUsername(), $this->getId()]);
        return $query->fetchColumn() !== false;
    }

    /** Toggle the viewer's like. Returns the change to the comment's score. */
    public function like()
    {
        return $this->vote("likes", "dislikes", 1);
    }

    /** Toggle the viewer's dislike. Returns the change to the comment's score. */
    public function disLike()
    {
        return $this->vote("dislikes", "likes", -1);
    }

    private function vote(string $table, string $opposite, int $direction)
    {
        $username = $this->userLoggedInObj->getUsername();
        if ($username === '') {
            http_response_code(403);
            return json_encode(['error' => 'Sign in to vote.']);
        }
        $commentId = $this->getId();

        if ($this->hasVote($table)) {
            $query = $this->conn->prepare("DELETE FROM $table WHERE username = ? AND commentId = ?");
            $query->execute([$username, $commentId]);
            return -$direction;
        }

        $query = $this->conn->prepare("DELETE FROM $opposite WHERE username = ? AND commentId = ?");
        $query->execute([$username, $commentId]);
        $removed = $query->rowCount();

        $query = $this->conn->prepare("INSERT INTO $table(username, commentId) VALUES(?, ?)");
        $query->execute([$username, $commentId]);

        return $direction * (1 + $removed);
    }
}
