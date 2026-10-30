<?php
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
chdir(dirname(__DIR__));
require 'includes/runtime.php';
foreach (glob('includes/classes/*.php') as $classFile) require_once $classFile;
$count = 0;
function check($condition, $message) {
    global $count;
    $count++;
    if (!$condition) throw new RuntimeException($message);
}
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec("CREATE TABLE users(id INTEGER PRIMARY KEY, username TEXT, firstName TEXT, lastName TEXT, email TEXT, profilePic TEXT, singUpDate TEXT);
INSERT INTO users VALUES(1,'owner','Test','User','test@example.test','avatar.jpg','2020-01-01 00:00:00');
CREATE TABLE videos(id INTEGER PRIMARY KEY, uploadedBy TEXT, title TEXT, description TEXT, category INTEGER, privacy INTEGER, filePath TEXT, uploadDate TEXT, views INTEGER, duration TEXT);
INSERT INTO videos VALUES(1,'owner','Example','Description',16,1,'video.mp4','2020-01-01 00:00:00',0,'00:10');
CREATE TABLE thumbnails(id INTEGER PRIMARY KEY,videoid INTEGER,filepath TEXT,selected INTEGER);
CREATE TABLE subscribers(id INTEGER PRIMARY KEY,userTo TEXT,userFrom TEXT);
INSERT INTO subscribers VALUES(1,'owner','viewer');
CREATE TABLE likes(id INTEGER PRIMARY KEY,username TEXT,commentId INTEGER,videoId INTEGER);
CREATE TABLE dislikes(id INTEGER PRIMARY KEY,username TEXT,commentId INTEGER,videoId INTEGER);
CREATE TABLE comments(id INTEGER PRIMARY KEY,postedBy TEXT,body TEXT,videoId INTEGER,responseTo INTEGER,datePosted TEXT);");
check(PHP_VERSION_ID >= 80400, 'Runtime minimum');
$_SESSION = [];
$guest = new User($db, '');
check($guest->getUsername() === '', 'Guest username');
check($guest->getName() === ' ', 'Guest name');
check($guest->getUserFirstName() === '' && $guest->getUserLastName() === '', 'Guest names');
check($guest->getUserEmail() === '' && $guest->getSignUpDate() === '', 'Guest data defaults');
check($guest->getUserProfilePic() !== '', 'Guest avatar');
$owner = new User($db,'owner');
check($owner->getSignUpDate() === '2020-01-01 00:00:00', 'Schema signup-date spelling');
$viewer = new User($db,'viewer');
// A user object for a missing account deliberately resolves to the guest defaults.
check(!$viewer->isSubscribedTo('owner'), 'Missing account has no subscriptions');
$db->exec("INSERT INTO users VALUES(2,'viewer','View','User','','','2020-01-02')");
$viewer = new User($db,'viewer');
check($viewer->isSubscribedTo('owner'), 'Subscription parameter spelling');
$video = new Video($db,1,$owner);
check($video->getVideoThumbnail() === 'assets/images/icons/VideoTubeLogo.png', 'Missing thumbnail fallback');
check($video->getVideoDisLikes() == 0, 'Lowercase dislikes table');
try { new Video($db,999,$owner); check(false,'Missing video exception'); }
catch (OutOfBoundsException $e) { check(true,'Missing video exception'); }
$comment = new Comment($db,['id'=>1,'body'=>'Example','postedBy'=>'owner','datePosted'=>'2020-01-01'], $owner,1);
check($comment->time_elapsed_string((new DateTimeImmutable('-8 days'))->format('Y-m-d H:i:s')) === '1 week ago', 'Week time without dynamic DateInterval property');
check($comment->time_elapsed_string((new DateTimeImmutable())->format('Y-m-d H:i:s')) === 'just now', 'Current timestamp');
check(str_contains($comment->time_elapsed_string('2020-01-01',true),'years'), 'Full elapsed time');
$grid = new VideoGrid($db,$guest);
check(!str_contains($grid->create([],null,false),'videoGridItem'), 'Empty list does not execute random query');
$_GET = [];
check(str_contains($grid->createGridHeader('Results',true),'search.php?term='), 'Filter links without a query string');
$_GET = ['term'=>['unexpected']];
check(str_contains($grid->createGridHeader('Results',true),'search.php?term='), 'Malformed filter term');
$profile = new ProfileGenerator($db,$guest,'missing');
check($profile->create() === 'User does not exist', 'Missing profile short circuit');
$data = new ProfileData($db,'owner');
$videos = $data->getUsersVideos();
check(count($videos) === 1 && $videos[0] instanceof Video, 'Profile video objects');
check($videos[0]->wasLiked() === false, 'Profile passes User object');
$db->exec("INSERT INTO thumbnails VALUES(1,1,'thumb.jpg',1)");
check(str_contains((new SelectThumbnail($db,$video))->create(),'thumbnail.php?id=1&thumbnail=1'), 'Actual thumbnail column names');
foreach (['FormString','FormUser','FormEmail','FormPassword'] as $name) {
    $method = 'sanitizing' . $name;
    check(is_string(FormSanitizer::$method('Example')), 'Form sanitizer ' . $name);
}
check(FormSanitizer::sanitizingFormString('  Mohd Elfie  ') === 'Mohd Elfie', 'Preserve spaces and casing in names');
check(FormSanitizer::sanitizingFormEmail('  Mixed.Case@example.test ') === 'Mixed.Case@example.test', 'Preserve email local-part casing');
check(FormSanitizer::sanitizingFormUser('bad name') === 'bad name', 'Do not silently rewrite invalid usernames');
foreach (['bytes=0-9'=>[0,9], 'bytes=90-'=>[90,99], 'bytes=-10'=>[90,99], 'bytes=0-999'=>[0,99]] as $header=>$expected) {
    check(MediaRange::parse($header,100)===$expected,'Byte range '.$header);
}
foreach (['bytes=100-','bytes=-0','bytes=9-2','bytes=0-1,4-5'] as $header) {
    try { MediaRange::parse($header,100); check(false,'Invalid range accepted'); }
    catch (RangeException $e) { check(true,'Invalid range rejected'); }
}
check(count((new SubscriptionsProvider($db,$viewer))->getVideos())===1,'Subscription feed uses subscribed public videos');
$db->exec("UPDATE videos SET privacy=0 WHERE id=1");
check((new SubscriptionsProvider($db,$viewer))->getVideos()===[],'Private video excluded from subscriptions');
$db->exec("INSERT INTO likes VALUES(99,'viewer',0,1)");
check((new LikedVideosProvider($db,$viewer))->getVideos()===[],'Private liked video hidden from other users');
$db->exec("DELETE FROM likes WHERE id=99; UPDATE videos SET privacy=1 WHERE id=1");
$guestVideo = new Video($db,1,$guest);
$guestComment = new Comment($db,['id'=>1],$guest,1);
foreach ([$guestVideo, $guestComment] as $subject) {
    foreach (['like','disLike'] as $method) {
        $result = json_decode($subject->$method(),true);
        check(isset($result['error']) && http_response_code() === 403, 'Guest votes rejected');
    }
}
check((int)$db->query('SELECT COUNT(*) FROM likes')->fetchColumn() === 0, 'Guest likes do not write');
check((int)$db->query('SELECT COUNT(*) FROM dislikes')->fetchColumn() === 0, 'Guest dislikes do not write');
echo "$count PHP compatibility checks passed on " . PHP_VERSION . "\n";
