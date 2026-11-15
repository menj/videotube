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
    <?php if (User::isLoggedIn()): ?>
    <nav aria-label="Account tools"><a href="jobs.php">Upload status</a> · <a href="account.php">Verify email / account help</a>
    <?php if ($userLoggedInObj->isAdmin()): ?> · <a href="admin.php">Administration</a><?php endif; ?></nav>
    <?php endif; ?>
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
