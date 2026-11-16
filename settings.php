<?php
require_once __DIR__ . '/includes/config.php';

if (!User::isLoggedIn()) {
    header("Location: signIn.php");
    exit;
}
$username = requireLogin();
$alert = static fn(string $kind, string $text): string =>
    "<div class=\"alert alert-$kind\"><strong>" . ($kind === 'success' ? 'SUCCESS!' : 'ERROR!') . "</strong> " . h($text) . "</div>";

$detailsMessage = "";
$passwordMessage = "";
$pictureMessage = "";
$deleteMessage = "";
$account = new Account($conn);

if (isset($_POST["saveDetailsButton"])) {
    $firstName = FormSanitizer::sanitizingFormString(inputText('firstName', $_POST));
    $lastName = FormSanitizer::sanitizingFormString(inputText('lastName', $_POST));
    $email = FormSanitizer::sanitizingFormEmail(inputText('email', $_POST));
    $currentPassword = FormSanitizer::sanitizingFormPassword(inputText('currentPassword', $_POST));
    $previousEmail = $account->emailChange($email, $username);

    if ($account->updateDetails($firstName, $lastName, $email, $username, $currentPassword)) {
        $note = "";
        if ($previousEmail !== null) {
            // Best effort: the change is saved even when mail is not configured yet.
            $recovery = new AccountRecovery($conn);
            try {
                $recovery->notifyEmailChanged($previousEmail, $username);
            } catch (Throwable $e) {
                error_log('Account mail: ' . $e->getMessage());
            }
            try {
                $recovery->issue($email, 'verify');
            } catch (Throwable $e) {
                error_log('Account mail: ' . $e->getMessage());
            }
            $note = " A verification link was sent to your new address.";
        }
        $detailsMessage = $alert('success', "Details updated successfully!$note");
    } else {
        $detailsMessage = $alert('danger', $account->getFirstError() ?: "Something went wrong");
    }
}

if (isset($_POST["savePasswordButton"])) {
    $oldPassword = FormSanitizer::sanitizingFormPassword(inputText('oldPassword', $_POST));
    $newPassword = FormSanitizer::sanitizingFormPassword(inputText('newPassword', $_POST));
    $newPassword2 = FormSanitizer::sanitizingFormPassword(inputText('newPassword2', $_POST));

    if ($account->updatePassword($oldPassword, $newPassword, $newPassword2, $username)) {
        startAuthenticatedSession($conn, $username);
        $passwordMessage = $alert('success', "Password updated successfully!");
    } else {
        $passwordMessage = $alert('danger', $account->getFirstError() ?: "Something went wrong");
    }
}

if (isset($_POST["savePictureButton"]) || isset($_POST["removePictureButton"])) {
    try {
        $picture = isset($_POST["removePictureButton"]) ? AvatarStore::DEFAULT_PICTURE : AvatarStore::store($_FILES['picture'] ?? []);
        $conn->prepare('UPDATE users SET profilePic=? WHERE username=?')->execute([$picture, $username]);
        $pictureMessage = $alert('success', "Profile picture updated.");
    } catch (InvalidArgumentException $e) {
        $pictureMessage = $alert('danger', $e->getMessage());
    }
}

if (isset($_POST["deleteAccountButton"])) {
    if (!$account->passwordMatches(FormSanitizer::sanitizingFormPassword(inputText('deletePassword', $_POST)), $username)) {
        $deleteMessage = $alert('danger', Constants::$passwordIncorrect);
    } else {
        $conn->beginTransaction();
        try {
            ContentRemoval::account($conn, $username);
            $conn->commit();
        } catch (RuntimeException $e) {
            $conn->rollBack();
            $deleteMessage = $alert('danger', $e->getMessage());
        }
        if ($deleteMessage === "") {
            $_SESSION = [];
            session_regenerate_id(true);
            header("Location: signIn.php");
            exit;
        }
    }
}

$pageTitle = 'Settings';
require_once __DIR__ . '/includes/header.php';
$formProvider = new SettingsFormProvider();
?>
<div class="settingsContainer column">

    <div class="formSection">
        <div class="message"><?= $pictureMessage ?></div>
        <?= $formProvider->createPictureForm($userLoggedInObj->getUserProfilePic()) ?>
    </div>

    <div class="formSection">
        <div class="message"><?= $detailsMessage ?></div>
        <?= $formProvider->createUserDetailsForm(
            isset($_POST["firstName"]) ? inputText('firstName', $_POST) : $userLoggedInObj->getUserFirstName(),
            isset($_POST["lastName"]) ? inputText('lastName', $_POST) : $userLoggedInObj->getUserLastName(),
            isset($_POST["email"]) ? inputText('email', $_POST) : $userLoggedInObj->getUserEmail()
        ) ?>
    </div>

    <div class="formSection">
        <div class="message"><?= $passwordMessage ?></div>
        <?= $formProvider->createPasswordForm() ?>
    </div>

    <div class="formSection">
        <div class="message"><?= $deleteMessage ?></div>
        <?= $formProvider->createDeleteAccountForm() ?>
    </div>

</div>
<?php require_once("includes/footer.php"); ?>
