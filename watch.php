<?php 
   require_once('includes/header.php');
   require_once("includes/classes/Video.php");
   require_once("includes/classes/VideoPlayer.php");
   require_once("includes/classes/VideoInfo.php");
   require_once("includes/classes/Comment.php");
   require_once("includes/classes/CommentSection.php");



    $videoId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    try {
        if (!$videoId) throw new OutOfBoundsException('Video not found');
        $video = new Video($conn, $videoId, $userLoggedInObj);
    } catch (OutOfBoundsException $e) {
        http_response_code(404);
        echo 'Video not found';
        require 'includes/footer.php';
        exit;
    }
    $video->increamentViews();
        
 ?>

    



   <div class="watchLeftColumn">
       <?php 
            $videoPlayer = new VideoPlayer($video);
            echo $videoPlayer->create(true);

            $videoInfo = new VideoInfo($conn, $video, $userLoggedInObj);
            echo $videoInfo->create();
            
            $commentSection = new CommentSection($conn, $video, $userLoggedInObj);
            echo $commentSection->create();
      ?>
   </div>
 

   <div class="suggestions">
      <?php
        $videoGrid = new VideoGrid($conn, $userLoggedInObj);
        echo $videoGrid->create(null, null, false);
      ?>
   </div>


<?php require_once('includes/footer.php') ?>
 