<?php
   
    class VideoUploadData {
        public ?int $jobId = null;
        public $videoDataArray,$title,$description,$categories,$privacy,$uploadedBy;
        //constructor
        public function __construct($videoDataArray,$title,$description,$categories,$privacy,$uploadedBy){
            $this->videoDataArray = $videoDataArray;
            $this->title = $title;
            $this->description = $description;
            $this->categories = $categories;
            $this->privacy = $privacy;
            $this->uploadedBy = $uploadedBy;
        }
    }

?>