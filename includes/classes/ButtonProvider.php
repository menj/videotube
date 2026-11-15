<?php
class ButtonProvider
{
    /**
     * Buttons carry no inline script: behaviour is attached by assets/JS through data-* attributes.
     *
     * @param array<string,scalar|null>|null $data rendered as data-* attributes; "action" names the handler
     */
    public static function createButton($text, $imageSrc, ?array $data, $class): string
    {
        $image = $imageSrc ? "<img src=\"" . h($imageSrc) . "\" alt=\"\">" : "";

        return "<button class=\"" . h($class) . "\"" . self::dataAttributes($data) . ">
                    $image
                    <span class=\"text\">" . h($text) . "</span>
                </button>";
    }

    public static function dataAttributes(?array $data): string
    {
        $html = "";
        foreach ($data ?? [] as $name => $value) {
            $html .= " data-" . h($name) . "=\"" . h($value) . "\"";
        }
        return $html;
    }

    public static function createHyperlinkButton($text, $imageSrc, $href, $class): string
    {
        $image = $imageSrc ? "<img src=\"" . h($imageSrc) . "\" alt=\"\">" : "";

        return "<a href=\"" . h($href) . "\">
                    <button class=\"" . h($class) . "\">
                        $image
                        <span class=\"text\">" . h($text) . "</span>
                    </button>
                </a>";
    }

    /** Avatar linking to a channel, for callers that already know the picture. */
    public static function profileLink(string $username, string $profilePic): string
    {
        return "<a href=\"profile.php?username=" . h(rawurlencode($username)) . "\">
                    <img src=\"" . h($profilePic) . "\" class=\"profilePicture\" alt=\"\">
                </a>";
    }

    public static function createProfileButton($conn, $username): string
    {
        $userObj = new User($conn, $username);
        return self::profileLink((string)$username, $userObj->getUserProfilePic());
    }

    public static function createEditButton($videoId): string
    {
        $button = self::createHyperlinkButton("EDIT VIDEO", null, "editVideo.php?videoId=" . (int)$videoId, "edit button");

        return "<div class=\"editVideoButtonContainer\">$button</div>";
    }

    public static function createSubscriberButton($conn, $userToObj, $userLoggedInObj): string
    {
        $userTo = $userToObj->getUsername();
        $isSubscribedTo = $userLoggedInObj->isSubscribedTo($userTo);
        $buttonText = ($isSubscribedTo ? "SUBSCRIBED" : "SUBSCRIBE") . " " . $userToObj->getSubscriberCount();
        $buttonClass = $isSubscribedTo ? "unsubscribe button" : "subscribe button";

        $button = self::createButton($buttonText, null, ["action" => "subscribe", "user-to" => $userTo], $buttonClass);

        return "<div class=\"subscribeButtonContainer\">$button</div>";
    }

    public static function createUserProfileNavigationButton($userLoggedInObj): string
    {
        if (User::isLoggedIn()) {
            return self::profileLink($userLoggedInObj->getUsername(), $userLoggedInObj->getUserProfilePic());
        }
        return "<a href=\"signIn.php\"><span class=\"signInLink\">SIGN IN</span></a>";
    }
}
