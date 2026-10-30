<?php
class VideoPlayer {
    private $video;
    public function __construct($video) { $this->video=$video; }
    public function create($autoPlay) {
        $id=(int)$this->video->getVideoId();
        return '<video class="videoPlayer" controls' . ($autoPlay?' autoplay':'') . '><source src="stream.php?id=' . $id . '" type="video/mp4">Your browser does not support video playback.</video>';
    }
}
