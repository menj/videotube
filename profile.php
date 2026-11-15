<?php
require_once("includes/header.php");
if(isset($_GET["username"])) {
    $profileUsername = inputText('username',$_GET);
}
else {
    http_response_code(404);
    echo "Channel not found";
    require_once("includes/footer.php");
    exit();
}
$profileGenerator = new ProfileGenerator($conn, $userLoggedInObj, $profileUsername);
echo $profileGenerator->create();
require_once("includes/footer.php");
?>