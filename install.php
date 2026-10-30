<?php
require __DIR__ . '/includes/runtime.php';
require __DIR__ . '/includes/Installer.php';
require __DIR__ . '/includes/classes/MediaCommand.php';
require __DIR__ . '/includes/security.php';
applicationSecurity();
$configPath = __DIR__ . '/includes/local.php';
if (is_file($configPath)) { http_response_code(403); exit('Installation is locked.'); }
$token = getenv('VIDEOTUBE_INSTALL_TOKEN');
if (!is_string($token) || strlen($token) < 32) {
    http_response_code(503);
    exit('Enable installation by setting VIDEOTUBE_INSTALL_TOKEN to a random secret of at least 32 characters in your hosting environment.');
}
csrfToken();
$error = ''; $done = false; $staged = null;
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['token'] ?? null) || !hash_equals($token,$_POST['token'])
        || !is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'],$_POST['csrf'])) {
        http_response_code(403); exit('Invalid installation token or session.');
    }
    $lock = fopen(__DIR__ . '/includes/install.lock','c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { http_response_code(409); exit('Installation is already running.'); }
    try {
        if (is_file($configPath)) throw new RuntimeException('Installation is locked.');
        foreach (['host','database','dbuser','dbpass','username','email','password','ffmpeg','ffprobe'] as $field) {
            if (!is_string($_POST[$field] ?? null)) throw new RuntimeException('All fields are required.');
        }
        if (!preg_match('/^[a-zA-Z0-9_.-]+$/D',$_POST['host']) || !preg_match('/^[a-zA-Z0-9_]+$/D',$_POST['database'])) {
            throw new RuntimeException('Invalid database host or name. Use a hostname/IP without a port.');
        }
        if (!is_writable(__DIR__ . '/includes')) throw new RuntimeException('The includes directory must be writable during setup.');
        foreach (['uploads/videos','uploads/videos/thumbnails'] as $directory) {
            if (!is_dir(__DIR__ . '/' . $directory) && !mkdir(__DIR__ . '/' . $directory,0750,true)) throw new RuntimeException('Cannot create upload directories.');
            if (!is_writable(__DIR__ . '/' . $directory)) throw new RuntimeException('Upload directories must be writable.');
        }
        if (!function_exists('proc_open')) throw new RuntimeException('Your host must enable proc_open for video processing.');
        foreach (['ffmpeg','ffprobe'] as $binary) {
            // Only local executable paths are accepted. No shell is involved.
            $path = realpath($_POST[$binary]);
            if (!$path || !is_file($path) || !is_executable($path)) throw new RuntimeException('Provide an executable absolute path for ' . $binary . '.');
            $_POST[$binary] = $path;
        }
        foreach (['ffmpeg','ffprobe'] as $binary) {
            $version = MediaCommand::run([$_POST[$binary], '-version'], 10);
            if (!str_contains(strtolower($version), $binary . ' version')) throw new RuntimeException('The configured ' . $binary . ' executable did not identify itself.');
        }
        $encoders = MediaCommand::run([$_POST['ffmpeg'], '-hide_banner', '-encoders'], 10);
        foreach (['libx264','aac','mjpeg'] as $codec) {
            if (!preg_match('/\b' . $codec . '\b/', $encoders)) throw new RuntimeException('FFmpeg is missing encoder: ' . $codec);
        }
        $config = ['dsn'=>'mysql:host=' . $_POST['host'] . ';dbname=' . $_POST['database'] . ';charset=utf8mb4',
            'user'=>$_POST['dbuser'],'password'=>$_POST['dbpass'],'ffmpeg'=>$_POST['ffmpeg'],'ffprobe'=>$_POST['ffprobe']];
        $db = new PDO($config['dsn'],$config['user'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $staged = Installer::stageConfig($configPath, $config);
        Installer::install($db,['username'=>$_POST['username'],'email'=>$_POST['email'],'password'=>$_POST['password']],file_get_contents(__DIR__ . '/database/schema.sql'), function() use ($staged, $configPath) {
            if (file_exists($configPath) || !rename($staged, $configPath)) throw new RuntimeException('Cannot publish configuration.');
        });
        session_regenerate_id(true); $done = true;
    } catch (PDOException $ex) {
        error_log('VideoTube installation: ' . $ex->getMessage());
        $error = 'Database setup failed. Check the server log and credentials. New tables are removed on failure; check the server log before retrying.';
    } catch (Throwable $ex) { $error = $ex->getMessage(); }
    finally { if ($staged !== null && is_file($staged)) unlink($staged); flock($lock, LOCK_UN); fclose($lock); }
}
?>
<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install VideoTube</title><style>body{font-size:19px;max-width:640px;margin:40px auto;padding:20px;background:#f5f6fa}label{display:block;margin:16px 0}input{display:block;box-sizing:border-box;width:100%;padding:10px}button{padding:12px 24px}p{line-height:1.5}</style>
<link rel="stylesheet" href="assets/CSS/fonts.css">
<h1>Install VideoTube</h1>
<?php if ($done): ?><p>Installation complete. Remove the installation token from your hosting environment.</p><a href="signIn.php">Sign in</a>
<?php else: ?>
<p>PHP 8.4+ and PDO MySQL are available. Create an empty database in your hosting control panel, then enter its details below.</p>
<p>This creates your administrator account.</p>
<?php if ($error): ?><p role="alert"><?= e($error) ?></p><?php endif; ?>
<form method="post">
<?php foreach (['token'=>'Installation token','host'=>'Database host','database'=>'Database name','dbuser'=>'Database username','dbpass'=>'Database password','username'=>'Your username','email'=>'Your email','password'=>'Your password (12–72 bytes)','ffmpeg'=>'Absolute path to FFmpeg','ffprobe'=>'Absolute path to FFprobe'] as $field=>$label): ?>
<label><?= e($label) ?><input name="<?= e($field) ?>" type="<?= in_array($field,['token','password','dbpass'],true)?'password':($field==='email'?'email':'text') ?>" <?= $field==='dbpass'?'':'required' ?> autocomplete="off"></label>
<?php endforeach; ?><button>Install VideoTube</button></form><?php endif; ?></html>
