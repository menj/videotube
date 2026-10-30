<?php
require_once("includes/header.php");
require_once("includes/classes/ProfileGenerator.php");
require_once("includes/classes/ProfileData.php");
if(isset($_GET["username"])) {
    $profileUsername = inputText('username',$_GET);
}
else {
    echo "Channel not found";
    exit();
}
$profileGenerator = new ProfileGenerator($conn, $userLoggedInObj, $profileUsername);
echo $profileGenerator->create();
?>