<?php

class VideoUploadData
{
    public ?int $jobId = null;
    /** Unguessable base name for the stored media files. */
    public ?string $mediaName = null;
    public $videoDataArray;
    public $title;
    public $description;
    public $categories;
    public $privacy;
    public $uploadedBy;
    //constructor
    public function __construct($videoDataArray, $title, $description, $categories, $privacy, $uploadedBy)
    {
        $this->videoDataArray = $videoDataArray;
        $this->title = $title;
        $this->description = $description;
        $this->categories = $categories;
        $this->privacy = $privacy;
        $this->uploadedBy = $uploadedBy;
    }
}
