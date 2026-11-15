<?php
require_once("includes/header.php");
require_once("includes/classes/Account.php");
require_once("includes/classes/FormSanitizer.php");
require_once("includes/classes/SettingsFormProvider.php");
require_once("includes/classes/Constants.php");

if(!User::isLoggedIn()) {
    header("Location: signIn.php");
    exit;
}

$detailsMessage = "";
$passwordMessage = "";
$formProvider = new SettingsFormProvider();

if(isset($_POST["saveDetailsButton"])) {
    $account = new Account($conn);

    $firstName = FormSanitizer::sanitizingFormString(inputText('firstName',$_POST));
    $lastName = FormSanitizer::sanitizingFormString(inputText('lastName',$_POST));
    $email = FormSanitizer::sanitizingFormEmail(inputText('email',$_POST));

    $username = $userLoggedInObj->getUsername();
    $previousEmail = $account->emailChange($email, $username);
    $currentPassword = FormSanitizer::sanitizingFormPassword(inputText('currentPassword',$_POST));

    if($account->updateDetails($firstName, $lastName, $email, $username, $currentPassword)) {
        $note = "";
        if ($previousEmail !== null) {
            // Best effort: the change is saved even when mail is not configured yet.
            $recovery = new AccountRecovery($conn);
            try { $recovery->notifyEmailChanged($previousEmail, $username); }
            catch (Throwable $e) { error_log('Account mail: ' . $e->getMessage()); }
            try { $recovery->issue($email, 'verify'); }
            catch (Throwable $e) { error_log('Account mail: ' . $e->getMessage()); }
            $note = " A verification link was sent to your new address.";
        }
        $detailsMessage = "<div class='alert alert-success'>
                                <strong>SUCCESS!</strong> Details updated successfully!$note
                            </div>";
    }
    else {
        $errorMessage = $account->getFirstError();

        if($errorMessage == "") $errorMessage = "Something went wrong";

        $detailsMessage = "<div class='alert alert-danger'>
                                <strong>ERROR!</strong> $errorMessage
                            </div>";
    }
}

if(isset($_POST["savePasswordButton"])) {
    $account = new Account($conn);

    $oldPassword = FormSanitizer::sanitizingFormPassword(inputText('oldPassword',$_POST));
    $newPassword = FormSanitizer::sanitizingFormPassword(inputText('newPassword',$_POST));
    $newPassword2 = FormSanitizer::sanitizingFormPassword(inputText('newPassword2',$_POST));

    if($account->updatePassword($oldPassword, $newPassword, $newPassword2, $userLoggedInObj->getusername())) {
        startAuthenticatedSession($conn, $userLoggedInObj->getUsername());
        $passwordMessage = "<div class='alert alert-success'>
                                <strong>SUCCESS!</strong> Password updated successfully!
                            </div>";
    }
    else {
        $errorMessage = $account->getFirstError();

        if($errorMessage == "") $errorMessage = "Something went wrong";

        $passwordMessage = "<div class='alert alert-danger'>
                                <strong>ERROR!</strong> $errorMessage
                            </div>";
    }
}
?>
<div class="settingsContainer column">

    <div class="formSection">
        <div class="message">
            <?php echo $detailsMessage; ?>
        </div>
        <?php
            echo $formProvider->createUserDetailsForm(
                isset($_POST["firstName"]) ? inputText('firstName',$_POST) : $userLoggedInObj->getUserFirstName(),
                isset($_POST["lastName"]) ? inputText('lastName',$_POST) : $userLoggedInObj->getUserLastName(),
                isset($_POST["email"]) ? inputText('email',$_POST) : $userLoggedInObj->getUserEmail()
            );
        ?>
    </div>

    <div class="formSection">
        <div class="message">
            <?php echo $passwordMessage; ?>
        </div>
        <?php
            echo $formProvider->createPasswordForm();
        ?>
    </div>

</div>
<?php require_once("includes/footer.php"); ?>
