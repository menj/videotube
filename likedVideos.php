<?php
require_once("includes/header.php");

if (!User::isLoggedIn()) {
    header("Location: signIn.php");
    exit;
}

$likedVideosProvider = new LikedVideosProvider($conn, $userLoggedInObj);
$videos = $likedVideosProvider->getVideos();

$videoGrid = new VideoGrid($conn, $userLoggedInObj);
?>
<div class="largeVideoGridContainer">
    <?php
    if (sizeof($videos) > 0) {
        echo $videoGrid->createLarge($videos, "Videos that you have liked", false);
    } else {
        echo "No videos to show";
    }
    ?>
</div>
<?php require_once("includes/footer.php"); ?>
