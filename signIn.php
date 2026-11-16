<?php
  require_once("includes/config.php");

  $account = new Account($conn);

if (isset($_POST["submitButton"])) {
    $username = FormSanitizer::sanitizingFormUser(inputText('username', $_POST));
    $password = FormSanitizer::sanitizingFormPassword(inputText('password', $_POST));

    $failureKey = loginFailureKey($username);
    $throttled = rateLimitCount($conn, $failureKey) >= LOGIN_FAILURE_LIMIT;
    if ($throttled) {
        http_response_code(429);
        header('Retry-After: ' . LOGIN_FAILURE_WINDOW);
    }
    $wasSuccessful = !$throttled && $account->login($username, $password);
    if (!$throttled && !$wasSuccessful) {
        rateLimitHit($conn, $failureKey, LOGIN_FAILURE_WINDOW);
    }

    if ($wasSuccessful) {
        startAuthenticatedSession($conn, $username);
        header("Location: index.php");
        exit;
    }
}

function getInputValues($name)
{
    if (isset($_POST[$name])) {
        echo h(inputText($name, $_POST));
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
<?php $pageTitle = 'Sign in';
require __DIR__ . '/includes/head.php'; ?>
</head>

   <body>
<div class="signInContainer">
             <div class="column">
                <div class="header">
                <img src="./assets/images/icons/VideoTubeLogo.png" title="VideoTube home" alt="site-logo">
                 <h3>Sign In</h3>
                 <span>to continue to VideoTube</span>
                </div>
                <div class="loginForm">
                <form action="signIn.php" method="POST">
                   <!-- displaying error message if any -->
                   <?php
                     echo $account->getError(Constants::$UserLoginFailed);
                    if (!empty($throttled)) {
                        echo "<span class='errorMessage'>" . h(Constants::$loginThrottled) . "</span>";
                    }
                    ?>
                   <input type="text" name="username" placeholder="User name" autocomplete="username"  value="<?php getInputValues('username') ?>" required>                   
             
                 
                   <input type="password" name="password" placeholder="Password" autocomplete="current-password"  required>
                   <input type="submit" class="btn btn-primary" name="submitButton" value="SUBMIT">
                </form>
                </div>
                <a href="signUp.php" class="loginMessage">Need an account? Sign up here!</a>
                <a href="account.php" class="loginMessage">Forgot your password?</a>
             </div>
         </div>
   </body>

   </html>
