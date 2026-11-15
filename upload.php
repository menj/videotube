<?php
require_once __DIR__ . '/includes/config.php';

if (!User::isLoggedIn()) {
    header('Location: signIn.php');
    exit;
}
$pageTitle = 'Upload';
require_once __DIR__ . '/includes/header.php';
?>
<div class="column">
<?php if ($userLoggedInObj->isVerified()): ?>
    <p>Videos up to 50 MB. Conversion runs in the background and can take a few minutes.</p>
    <?= (new VideoDetailsFormProvider($conn))->createUploadForm() ?>
<?php else: ?>
    <p>Uploading is available once your email address is verified.</p>
<?php endif; ?>
</div>

<div class="modal fade" id="modalLoader" tabindex="-1" role="dialog" aria-labelledby="modalLoaderText" aria-hidden="true" data-backdrop="static" data-keyboard="false">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-body">
         <span id="modalLoaderText">Uploading. Please keep this page open…</span>
         <img src="./assets/images/icons/loading-spinner.gif" alt="">
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
