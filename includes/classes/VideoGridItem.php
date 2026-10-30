<?php
    class VideoGridItem {
        private $video, $largeMode;

        public function __construct($video, $largeMode) {
            $this->video = $video;
            $this->largeMode = $largeMode;
        }

        public function create() {
           $thumbnail = $this->createThumbnail();
           $details = $this->createDetails();
           $url = "watch.php?id=" . $this->video->getVideoId();

           return "<a href='$url'>
                       <div class = 'videoGridItem'>
                             $thumbnail
                             $details
                       </div>
                   </a>
           ";
        }

        private function createThumbnail() {
            $thumbnail = htmlspecialchars((string)$this->video->getVideoThumbnail(),ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8');
            $duration = htmlspecialchars((string)$this->video->getVideoDuration(),ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8');

            return "<div class='thumbnail'>
                        <img src='$thumbnail'>
                        <div class='duration'>
                           <span> $duration </span>
                        </div>
                    </div>
            ";
        }

        private function createDetails() {
           $title = htmlspecialchars((string)$this->video->getVideoTitle(),ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8');
           $username = htmlspecialchars((string)$this->video->getVideoUploadedBy(),ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8'); 
           $views = $this->video->getVideoViews(); 
           $description =  $this->createDescription();
           $timeStamp = $this->video->getVideoTimeStamp(); 

           return "
                  <div class='details'>
                     <h3 class='title'>$title</h3>
                     <span class='username'>$username</span>
                     <div class='stats'>
                        <span class = 'viewCount'>$views views- </span>
                        <span class = 'timeStamp'>$timeStamp</span>
                     </div>
                     $description
                  </div>
           
           ";
        }

        private function createDescription() {
            if(!$this->largeMode) {
                return "";
            }
            else {
                $description = htmlspecialchars((string)$this->video->getVideoDescription(),ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8');
                $description = (strlen($description) > 350) ? substr($description, 0, 347)."..." : $description;

                return "<span class='description'>$description</span>";
            }
        }
    }
?>