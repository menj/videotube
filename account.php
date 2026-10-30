<?php
require __DIR__.'/includes/config.php';
require __DIR__.'/includes/AccountRecovery.php';
header('Referrer-Policy: no-referrer');
$service=new AccountRecovery($conn);$message='';
$token=inputText('token',$_POST) ?: inputText('token',$_GET);
$purpose=inputText('purpose',$_POST) ?: inputText('purpose',$_GET);
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if ($token!=='') {
        $message=$service->consume($token,$purpose,inputText('password',$_POST)) ? 'Account updated. You can sign in.' : 'Invalid or expired link, or invalid password. Request a new link.';
    } else {
        $purpose=inputText('action',$_POST)==='verify'?'verify':'reset';
        $email=inputText('email',$_POST);
        if ($purpose==='verify') {
            $q=$conn->prepare('SELECT email FROM users WHERE username=?');$q->execute([requireLogin()]);$email=(string)$q->fetchColumn();
        }
        try { $service->issue($email,$purpose); }
        catch (Throwable $e) { error_log('Account mail: '.$e->getMessage()); }
        $message='If an eligible account matches, an email has been requested. Check your inbox. Contact the site administrator if it does not arrive.';
    }
}
?>
<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Account help — VideoTube</title><link rel="stylesheet" href="assets/CSS/fonts.css">
<main><h1>Account help</h1><p role="status"><?= h($message) ?></p>
<?php if ($token!==''): ?>
<form method="post"><input type="hidden" name="token" value="<?= h($token) ?>"><input type="hidden" name="purpose" value="<?= h($purpose) ?>">
<?php if ($purpose==='reset'): ?><label>New password (12–72 bytes)<input type="password" name="password" minlength="12" maxlength="72" autocomplete="new-password" required></label><?php endif; ?>
<button>Confirm <?= $purpose==='reset'?'password reset':'email verification' ?></button></form>
<?php else: ?>
<form method="post"><h2>Reset password</h2><label>Email <input type="email" name="email" required autocomplete="email"></label><button name="action" value="reset">Send reset link</button></form>
<?php if (!empty($_SESSION['userLoggedIn'])): ?><form method="post"><h2>Verify email</h2><p>Verify your current email before posting or uploading.</p><button name="action" value="verify">Send verification link</button></form><?php endif; ?>
<?php endif; ?><p><a href="signIn.php">Sign in</a> · <a href="index.php">Home</a></p></main></html>
