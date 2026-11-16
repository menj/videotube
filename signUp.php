<?php
  require_once("includes/config.php");


  $account = new Account($conn);

if (isset($_POST["submitButton"])) {
    //firstName & lastName
    $firstName = FormSanitizer::sanitizingFormString(inputText('firstName', $_POST));
    $lastName = FormSanitizer::sanitizingFormString(inputText('lastName', $_POST));

    //Username
    $username = FormSanitizer::sanitizingFormUser(inputText('username', $_POST));

    //emails
    $email = FormSanitizer::sanitizingFormEmail(inputText('email', $_POST));
    $email2 = FormSanitizer::sanitizingFormEmail(inputText('email2', $_POST));

    //password
    $password = FormSanitizer::sanitizingFormPassword(inputText('password', $_POST));
    $password2 = FormSanitizer::sanitizingFormPassword(inputText('password2', $_POST));

    $wasSuccessful = $account->register($firstName, $lastName, $username, $email, $email2, $password, $password2);

    if ($wasSuccessful) {
        startAuthenticatedSession($conn, $username);
        try {
            (new AccountRecovery($conn))->issue($email, 'verify');
        } catch (Throwable $e) {
              error_log('Account mail: ' . $e->getMessage());
        }
        header("Location: account.php?sent=1");
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
<?php $pageTitle = 'Sign up';
require __DIR__ . '/includes/head.php'; ?>
</head>

<body>
    <div class="signInContainer">
        <div class="column">
            <div class="header">
                <img src="./assets/images/icons/VideoTubeLogo.png" title="VideoTube home" alt="site-logo">
                <h3>Sign Up</h3>
                <span>to continue to VideoTube</span>
            </div>
            <div class="loginForm">
                <form action="signUp.php" method="POST">
                   <!-- displaying error message if any -->
                   <?php
                     echo $account->getError(Constants::$firstNameCharacters);
                    ?>
                   <input type="text" name="firstName" placeholder="first name" autocomplete='off' value="<?php getInputValues('firstName') ?>" required>

                   
                   <!-- displaying error message if any -->
                   <?php
                     echo $account->getError(Constants::$lastNameCharacters);
                    ?>
                   <input type="text" name="lastName" placeholder="last name" autocomplete='off' value="<?php getInputValues('lastName') ?>" required>

                   <!-- displaying error message if any -->
                   <?php
                     echo $account->getError(Constants::$usernameCharacters);
                     echo $account->getError(Constants::$usernameTaken);
                    ?>
                   <input type="text" name="username" placeholder="User name" autocomplete='off' value="<?php getInputValues('username') ?>" required>

                   <!-- displaying error message if any -->
                   <?php
                     echo $account->getError(Constants::$emailsDoNotMatch);
                     echo $account->getError(Constants::$emailInvalid);
                     echo $account->getError(Constants::$emailTaken);
                    ?>
                   <input type="email" name="email" placeholder="email" autocomplete='off' value="<?php getInputValues('email') ?>" required>
                   <input type="email" name="email2" placeholder="confirm email" autocomplete='off' value="<?php getInputValues('email2') ?>" required>

                    <!-- displaying error message if any -->
                    <?php
                     echo $account->getError(Constants::$passwordDoNotMatch);
                     echo $account->getError(Constants::$passwordLength);
                    ?>
                   <input type="password" name="password" placeholder="password" autocomplete='off' required>
                   <input type="password" name="password2" placeholder="password" autocomplete='off' required>
                   
                   <input type="submit" class="btn btn-primary" name="submitButton" value="SUBMIT">

                </form>
            </div>
            <a href="signIn.php" class="loginMessage">Already have an account? Sign in here!</a>
        </div>
    </div>
</body>

</html>