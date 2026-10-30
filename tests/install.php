<?php
require __DIR__ . '/../includes/Installer.php';
require __DIR__ . '/../includes/classes/Account.php';
require __DIR__ . '/../includes/classes/Constants.php';
$db = new PDO('mysql:host=127.0.0.1;dbname=videotube_test;charset=utf8mb4','root','test',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$account=['username'=>'owner','email'=>'owner@example.test','password'=>'a!StrongPassword123'];
$schema = file_get_contents(__DIR__.'/../database/schema.sql');
foreach (['ddl','publish'] as $failure) {
    try {
        Installer::install($db, $account, $schema . ($failure === 'ddl' ? ';INVALID SQL' : ''), $failure === 'publish' ? function() { throw new RuntimeException('Simulated configuration failure'); } : null);
        throw new LogicException('Expected installation failure');
    } catch (PDOException | RuntimeException $e) {
        if ($db->query('SHOW TABLES')->fetchColumn() !== false) throw new LogicException('Failed install left tables behind');
    }
}
Installer::install($db,$account,file_get_contents(__DIR__.'/../database/schema.sql'));
foreach (['users','videos','thumbnails','categories','likes','dislikes','comments','subscribers','request_limits'] as $table) $db->query('SELECT * FROM '.$table);
if ((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn()!==1) throw new RuntimeException('Expected one first account');
if (!(new Account($db))->login('owner',$account['password'])) throw new RuntimeException('Installer password cannot sign in');
if ((new Account($db))->login('owner','wrong')) throw new RuntimeException('Wrong password accepted');
$db->exec("INSERT INTO videos(uploadedBy,title,description,category,privacy,filePath) VALUES('owner','Test','Description',16,1,'example.mp4')");
$db->exec("INSERT INTO likes(username,videoId) VALUES('owner',1)");
$db->exec("INSERT INTO comments(postedBy,videoId,body) VALUES('owner',1,'Hello')");
try { Installer::install($db,$account,file_get_contents(__DIR__.'/../database/schema.sql')); throw new LogicException('Reinstallation accepted'); }
catch (RuntimeException $e) { if (!str_contains($e->getMessage(),'empty database')) throw $e; }
require __DIR__.'/../includes/Migration.php';
$before=$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
Migration::apply($db,'owner',true);
Migration::apply($db,'owner');
Migration::apply($db,'owner');
if ($db->query('SELECT COUNT(*) FROM users')->fetchColumn()!==$before) throw new RuntimeException('Migration lost users');
echo "Installation, login, schema defaults, and overwrite protection passed.\n";
