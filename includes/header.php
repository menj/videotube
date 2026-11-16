<?php
require_once __DIR__ . '/config.php';

$usernameLoggedIn = isset($_SESSION["userLoggedIn"]) ? $_SESSION["userLoggedIn"] : "";
$userLoggedInObj = new User($conn, $usernameLoggedIn);
?>
<!DOCTYPE html>
<html lang="en">

<head>
<?php require __DIR__ . '/head.php'; ?>
</head>

<body data-viewer="<?= h($userLoggedInObj->viewerState()) ?>">
    <div id="pageContainer">
        <!-- main header -->
        <div id="mastHeadContainer">
            <!-- menu bar btn -->
            <button class="navShowHide" aria-label="Toggle navigation">
                <img src="./assets/images/icons/menu.png" alt="">
            </button>
            <!-- app logo  -->
            <a class="logoContainer" href="index.php">
                <img src="./assets/images/icons/VideoTubeLogo.png" title="VideoTube home" alt="VideoTube home">
            </a>
            <!--Search bar  -->
            <div class="searchBarContainer">
                <form action="search.php" method="get" role="search">
                    <input type="text" class="searchBar" name="term" placeholder="Search" aria-label="Search videos">
                    <button class="searchButton" aria-label="Search">
                        <img src="./assets/images/icons/search.png" alt="">
                    </button>
                </form>
            </div>
            <!-- right icons -->
            <div class="rightIcons">
                <a href="upload.php" aria-label="Upload a video">
                <img src="./assets/images/icons/upload.png" alt="">
                </a>
                <?php echo ButtonProvider::createUserProfileNavigationButton($userLoggedInObj); ?>
            </div>
        </div>

        <!-- side navigation bar -->
        <div id="sideNavContainer">
        <?php
           $navigationProvider = new NavigationMenuProvider($conn, $userLoggedInObj);
           echo  $navigationProvider->create();
        ?>
        </div>

        <!--main section area  -->
        <div id="mainSectionContainer">
            <div id="mainContentContainer">
<?php if ($userLoggedInObj->viewerState() === 'unverified') : ?>
                <p class="alert alert-warning" role="status">Verify your email address to upload, comment, like and subscribe. <a href="account.php">Send me the link again</a></p>
<?php endif; ?>
<?php if (!empty($pageNotice)) : ?>
                <p class="alert alert-info" role="status"><?= h($pageNotice) ?></p>
<?php endif; ?>
