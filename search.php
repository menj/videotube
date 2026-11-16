<?php
require_once("includes/header.php");

$term = trim(inputText('term', $_GET));
if ($term === "") {
    echo "You must enter a search term";
    require_once("includes/footer.php");
    exit();
}
$orderBy = inputText('orderBy', $_GET) === "uploadDate" ? "uploadDate" : "views";
$page = Pager::current();

$searchResultProvider = new SearchResultProvider($conn, $userLoggedInObj);
$total = $searchResultProvider->countVideos($term);
$videos = $searchResultProvider->getVideos($term, $orderBy, $page);
$videoGrid = new VideoGrid($conn, $userLoggedInObj);
?>
<div class="largeVideoGridContainer">
    <?php
    if (count($videos) > 0) {
        echo $videoGrid->createLarge($videos, $total . " videos found", true);
        echo Pager::links('search.php', ['term' => $term, 'orderBy' => $orderBy], $page, Pager::offset($page) + count($videos) < $total);
    } else {
        echo "No result found";
    }
    ?>
</div>
<?php require_once("includes/footer.php"); ?>
