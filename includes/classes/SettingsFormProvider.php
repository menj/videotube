<?php

class SettingsFormProvider
{
    public function createUserDetailsForm($firstName, $lastName, $email)
    {
        $firstNameInput = $this->createFirstNameInput($firstName);
        $lastNameInput = $this->createLastNameInput($lastName);
        $emailInput = $this->createEmailInput($email);
        $saveButton = $this->createSaveUserDetailsButton();

        return "<form action='settings.php' method='POST' enctype='multipart/form-data'>
                    <span class='title'>User details</span>
                    $firstNameInput
                    $lastNameInput
                    $emailInput
                    <div class='form-group'>
                        <input class='form-control' type='password' placeholder='Current password (only needed to change your email)' name='currentPassword' autocomplete='current-password'>
                    </div>
                    $saveButton
                </form>";
    }

    public function createPictureForm($currentPicture)
    {
        $picture = h($currentPicture);
        return "<form action='settings.php' method='POST' enctype='multipart/form-data'>
                    <span class='title'>Profile picture</span>
                    <div class='form-group pictureRow'>
                        <img src='$picture' class='profilePicture' alt='Your current profile picture'>
                        <input type='file' class='form-control-file' name='picture' accept='image/jpeg,image/png,image/webp' aria-label='New profile picture'>
                    </div>
                    <p class='text-muted'>JPEG, PNG or WebP up to 2 MB. It is cropped to a square.</p>
                    <button type='submit' class='btn btn-primary' name='savePictureButton'>Upload picture</button>
                    <button type='submit' class='btn btn-link' name='removePictureButton'>Use the default picture</button>
                </form>";
    }

    public function createDeleteAccountForm()
    {
        return "<form action='settings.php' method='POST' data-confirm='Delete your account with all of your videos and comments? This cannot be undone.'>
                    <span class='title'>Delete account</span>
                    <p>Removes your channel, videos, comments, likes and subscriptions permanently.</p>
                    <div class='form-group'>
                        <input class='form-control' type='password' placeholder='Current password' name='deletePassword' autocomplete='current-password' required>
                    </div>
                    <button type='submit' class='btn btn-outline-danger' name='deleteAccountButton'>Delete my account</button>
                </form>";
    }

    public function createPasswordForm()
    {
        $oldPasswordInput = $this->createPasswordInput("oldPassword", "Old password");
        $newPassword1Input = $this->createPasswordInput("newPassword", "New password");
        $newPassword2Input = $this->createPasswordInput("newPassword2", "Confirm new password");

        $saveButton = $this->createSavePasswordButton();

        return "<form action='settings.php' method='POST' enctype='multipart/form-data'>
                    <span class='title'>Update password</span>
                    $oldPasswordInput
                    $newPassword1Input
                    $newPassword2Input
                    $saveButton
                </form>";
    }

    private function createFirstNameInput($value)
    {
        $value = htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if ($value == null) {
            $value = "";
        }
        return "<div class='form-group'>
                    <input class='form-control' type='text' placeholder='First name' name='firstName' value='$value' required>
                </div>";
    }

    private function createLastNameInput($value)
    {
        $value = htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if ($value == null) {
            $value = "";
        }
        return "<div class='form-group'>
                    <input class='form-control' type='text' placeholder='Last name' name='lastName' value='$value' required>
                </div>";
    }

    private function createEmailInput($value)
    {
        $value = htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if ($value == null) {
            $value = "";
        }
        return "<div class='form-group'>
                    <input class='form-control' type='email' placeholder='Email' name='email' value='$value' required>
                </div>";
    }

    private function createSaveUserDetailsButton()
    {
        return "<button type='submit' class='btn btn-primary' name='saveDetailsButton'>Save</button>";
    }

    private function createSavePasswordButton()
    {
        return "<button type='submit' class='btn btn-primary' name='savePasswordButton'>Save</button>";
    }

    private function createPasswordInput($name, $placeholder)
    {

        return "<div class='form-group'>
                    <input class='form-control' type='password' placeholder='$placeholder' name='$name' required>
                </div>";
    }
}
