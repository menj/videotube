<?php
class NavigationMenuProvider {

    private $conn, $userLoggedInObj;

    public function __construct($conn, $userLoggedInObj) {
        $this->conn = $conn;
        $this->userLoggedInObj = $userLoggedInObj;
    }

    public function create() {
        $menuHtml = $this->createNavItem("Home", "assets/images/icons/home.png", "index.php");
        $menuHtml .= $this->createNavItem("Trending", "assets/images/icons/trending.png", "trending.php");
        $menuHtml .= $this->createNavItem("Subscriptions", "assets/images/icons/subscriptions.png", "subscriptions.php");
        $menuHtml .= $this->createNavItem("Liked Videos", "assets/images/icons/thumb-up.png", "likedVideos.php");
       
        if(User::isLoggedIn()) {
            $menuHtml .= $this->createNavItem("Your channel", "assets/images/icons/history.png", "profile.php?username=" . rawurlencode($this->userLoggedInObj->getUsername()));
            $menuHtml .= $this->createNavItem("Upload status", "assets/images/icons/upload.png", "jobs.php");
            $menuHtml .= $this->createNavItem("Settings", "assets/images/icons/settings.png", "settings.php");
            if (!$this->userLoggedInObj->isVerified()) {
                $menuHtml .= $this->createNavItem("Verify email", "assets/images/icons/settings.png", "account.php");
            }
            if ($this->userLoggedInObj->isAdmin()) {
                $menuHtml .= $this->createNavItem("Administration", "assets/images/icons/settings.png", "admin.php");
            }
            $menuHtml .= $this->createNavItem("Log Out", "assets/images/icons/logout.png", "logout.php");

            $menuHtml .= $this->createSubscriptionsSection();
        }

        return "<div class='navigationItems'>
                    $menuHtml
                </div>";
    }

    private function createNavItem($text, $icon, $link) {
        $text=htmlspecialchars($text,ENT_QUOTES,'UTF-8');
        $icon=htmlspecialchars($icon,ENT_QUOTES,'UTF-8');
        $link=htmlspecialchars($link,ENT_QUOTES,'UTF-8');
        return "<div class='navigationItem'>
                    <a href='$link'>
                        <img src='$icon' alt=''>
                        <span>$text</span>
                    </a>
                </div>";
    }

    private function createSubscriptionsSection() {
        $subscriptions = $this->userLoggedInObj->getSubscriptions();

        $html = "<span class='heading'>Subscriptions</span>";
        foreach($subscriptions as $sub) {
            $subUsername = $sub->getUsername();
            $html .= $this->createNavItem($subUsername, $sub->getUserProfilePic(), "profile.php?username=" . rawurlencode($subUsername));
        }
        return $html;
    }

}
?>