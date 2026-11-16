<?php

class VideoInfo
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
        return $this->getVideoPrimaryInfo() . $this->getVideoSecondaryInfo();
    }

    private function getVideoPrimaryInfo(): string
    {
        $title = h($this->video->getVideoTitle());
        $views = (int)$this->video->getVideoViews();
        $controls = (new VideoInfoControls($this->video, $this->userLoggedInObj))->create();

        return "<div class=\"videoInfo\">
                    <h1>$title</h1>
                    <div class=\"bottomSection\">
                        <span class=\"viewCount\">$views views</span>
                        $controls
                    </div>
                </div>";
    }

    private function getVideoSecondaryInfo(): string
    {
        // Raw values are used for lookups and comparisons; escaping happens only where they are printed.
        $uploadedBy = (string)$this->video->getVideoUploadedBy();
        $uploader = new User($this->conn, $uploadedBy);
        $profileButton = ButtonProvider::profileLink($uploadedBy, $uploader->getUserProfilePic());

        if ($uploadedBy === $this->userLoggedInObj->getUsername()) {
            $actionButton = ButtonProvider::createEditButton($this->video->getVideoId());
        } else {
            $actionButton = ButtonProvider::createSubscriberButton($this->conn, $uploader, $this->userLoggedInObj);
        }

        return "<div class=\"secondaryInfo\">
                    <div class=\"topRow\">
                        $profileButton
                        <div class=\"uploadInfo\">
                            <span class=\"owner\">
                                <a href=\"profile.php?username=" . h(rawurlencode($uploadedBy)) . "\">" . h($uploadedBy) . "</a>
                            </span>
                            <span class=\"date\">published on " . h($this->video->getVideoUploadDate()) . "</span>
                        </div>
                        $actionButton
                    </div>
                    <div class=\"descriptionContainer\">" . h($this->video->getVideoDescription()) . "</div>
                </div>";
    }
}
