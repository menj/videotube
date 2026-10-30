<?php
require __DIR__.'/includes/config.php';
require __DIR__.'/includes/Moderation.php';
$actor=requireAdmin($conn);$message='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try { (new Moderation($conn))->apply($actor,inputText('action',$_POST),(int)filter_var($_POST['id'] ?? null,FILTER_VALIDATE_INT));$message='Action completed.'; }
    catch (RuntimeException | InvalidArgumentException $e) { http_response_code(422);$message=$e->getMessage(); }
    requireAdmin($conn);
}
$section=inputText('section',$_GET);if (!in_array($section,['users','videos','comments','jobs','audit'],true)) $section='users';
$page=max(1,(int)filter_var($_GET['page'] ?? 1,FILTER_VALIDATE_INT));$offset=min($page-1,100000)*50;
$sql=match($section) {
 'users'=>'SELECT id,username,email,role,suspended,emailVerifiedAt FROM users',
 'videos'=>'SELECT id,title,uploadedBy,privacy FROM videos',
 'comments'=>'SELECT id,postedBy,videoId,body FROM comments',
 'jobs'=>'SELECT id,username,status,error,createdAt FROM upload_jobs',
 'audit'=>'SELECT id,actor,action,target,createdAt FROM moderation_log'
};
$rows=$conn->query($sql." ORDER BY id DESC LIMIT 50 OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Administration — VideoTube</title>
<link rel="stylesheet" href="assets/CSS/fonts.css"><style>body{max-width:1200px;margin:2rem auto;padding:1rem}table{border-collapse:collapse;width:100%}td,th{padding:.6rem;border:1px solid #ccc;overflow-wrap:anywhere}form{display:inline}button{margin:.25rem}nav a{margin-right:1rem}</style>
<h1>Administration</h1><nav><a href="index.php">Home</a><?php foreach (['users','videos','comments','jobs','audit'] as $tab): ?><a href="?section=<?= $tab ?>"><?= ucfirst($tab) ?></a><?php endforeach; ?></nav>
<p role="status"><?= h($message) ?></p><p>Content removal is permanent in the database. Stored media remains protected for backup recovery.</p>
<table><?php if ($rows): ?><thead><tr><?php foreach (array_keys($rows[0]) as $key): ?><th><?= h($key) ?></th><?php endforeach; ?><th>Actions</th></tr></thead><?php endif; ?><tbody>
<?php foreach ($rows as $row): ?><tr><?php foreach ($row as $value): ?><td><?= h($value) ?></td><?php endforeach; ?><td>
<?php $actions=match($section) { 'users'=>[$row['suspended']?'restore':'suspend',$row['role']==='admin'?'demote':'promote'], 'videos'=>['remove_video'], 'comments'=>['remove_comment'], default=>[] }; ?>
<?php foreach ($actions as $action): ?><form method="post" onsubmit="return confirm('Confirm this action?')"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button name="action" value="<?= $action ?>"><?= h(ucwords(str_replace('_',' ',$action))) ?></button></form><?php endforeach; ?>
</td></tr><?php endforeach; ?></tbody></table>
<p><?php if ($page>1): ?><a href="?section=<?= $section ?>&page=<?= $page-1 ?>">Previous</a><?php endif; ?> · <a href="?section=<?= $section ?>&page=<?= $page+1 ?>">Next</a></p></html>
