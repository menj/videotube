<?php
require __DIR__ . '/../includes/Installer.php';
require __DIR__ . '/../includes/classes/Account.php';
require __DIR__ . '/../includes/classes/Constants.php';
$db = new PDO('mysql:host=127.0.0.1;dbname=videotube_test;charset=utf8mb4','root','test',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$account=['username'=>'owner','email'=>'owner@example.test','password'=>'a!StrongPassword123'];
Installer::install($db,$account,file_get_contents(__DIR__.'/../database/schema.sql'));
foreach (['users','videos','thumbnails','categories','likes','dislikes','comments','subscribers'] as $table) $db->query('SELECT * FROM '.$table);
if ((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn()!==1) throw new RuntimeException('Expected one first account');
if (!(new Account($db))->login('owner',$account['password'])) throw new RuntimeException('Installer password cannot sign in');
if ((new Account($db))->login('owner','wrong')) throw new RuntimeException('Wrong password accepted');
$db->exec("INSERT INTO videos(uploadedBy,title,description,category,privacy,filePath) VALUES('owner','Test','Description',16,1,'example.mp4')");
$db->exec("INSERT INTO likes(username,videoId) VALUES('owner',1)");
$db->exec("INSERT INTO comments(postedBy,videoId,body) VALUES('owner',1,'Hello')");
try { Installer::install($db,$account,file_get_contents(__DIR__.'/../database/schema.sql')); throw new LogicException('Reinstallation accepted'); }
catch (RuntimeException $e) { if (!str_contains($e->getMessage(),'empty database')) throw $e; }
echo "Installation, login, schema defaults, and overwrite protection passed.\n";
