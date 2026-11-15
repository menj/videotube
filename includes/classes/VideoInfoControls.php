<?php
class VideoInfoControls
{
    private $video;
    private $userLoggedInObj;

    public function __construct($video, $userLoggedInObj)
    {
        $this->video = $video;
        $this->userLoggedInObj = $userLoggedInObj;
    }

    public function create(): string
    {
        $videoId = (int)$this->video->getVideoId();
        $like = ButtonProvider::createButton(
            $this->video->getVideoLikes(),
            "assets/images/icons/thumb-up" . ($this->video->wasLiked() ? "-active" : "") . ".png",
            ["action" => "likeVideo", "video-id" => $videoId],
            "likeButton"
        );
        $dislike = ButtonProvider::createButton(
            $this->video->getVideoDisLikes(),
            "assets/images/icons/thumb-down" . ($this->video->wasDisLiked() ? "-active" : "") . ".png",
            ["action" => "dislikeVideo", "video-id" => $videoId],
            "disLikeButton"
        );

        return "<div class=\"controls\">
                    $like
                    $dislike
                </div>";
    }
}
