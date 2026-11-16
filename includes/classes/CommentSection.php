<?php

class CommentSection
{
    private $conn;
    private $video;
    private $userLoggedInObj;

    public function __construct($conn, $video, $userLoggedInObj)
    {
        $this->video = $video;
        $this->conn = $conn;
        $this->userLoggedInObj = $userLoggedInObj;
    }

    public function create(): string
    {
        $viewer = $this->userLoggedInObj;
        $videoId = (int)$this->video->getVideoId();
        $numOfComments = (int)$this->video->getNumOfComments();

        $profileButton = ButtonProvider::profileLink($viewer->getUsername(), $viewer->getUserProfilePic());
        $commentButton = ButtonProvider::createButton(
            "COMMENT",
            null,
            ["action" => "postComment", "video-id" => $videoId, "reply-to" => ""],
            "postComment"
        );
        $commentItems = self::renderPage(new CommentRepository($this->conn, $viewer), $videoId);

        return "<div class=\"commentSection\">
                    <div class=\"header\">
                        <span class=\"commentCount\">$numOfComments comments</span>
                        <div class=\"commentForm\">
                            $profileButton
                            <textarea class=\"commentBodyClass\" placeholder=\"Add a public comment\" maxlength=\"10000\"></textarea>
                            $commentButton
                        </div>
                    </div>
                    <div class=\"comments\">
                        $commentItems
                    </div>
                </div>";
    }

    /** One page of top-level comments, followed by a "show more" button while older ones remain. */
    public static function renderPage(CommentRepository $repository, int $videoId, int $beforeId = 0): string
    {
        [$comments, $next] = $repository->page($videoId, $beforeId);
        $html = "";
        foreach ($comments as $comment) {
            $html .= $comment->create();
        }
        if ($next > 0) {
            $html .= ButtonProvider::createButton(
                "Show more comments",
                null,
                ["action" => "loadComments", "video-id" => $videoId, "before" => $next],
                "loadMoreComments btn btn-light"
            );
        }
        return $html;
    }
}
