<?php
require __DIR__.'/../includes/Migration.php';
$db=new PDO('mysql:host=127.0.0.1','root','test',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE DATABASE videotube_legacy CHARACTER SET utf8mb4');$db->exec('USE videotube_legacy');
// Model the original installer: missing comments/subscribers, old password length and missing defaults.
$db->exec("CREATE TABLE users(id INT AUTO_INCREMENT PRIMARY KEY,username VARCHAR(25),email VARCHAR(191),password VARCHAR(128),firstName VARCHAR(25),lastName VARCHAR(25),profilePic VARCHAR(255),singUpDate DATETIME NOT NULL) ENGINE=MyISAM");
$db->exec("CREATE TABLE videos(id INT AUTO_INCREMENT PRIMARY KEY,uploadedBy VARCHAR(50),title VARCHAR(70),description VARCHAR(1000),category INT,privacy INT,filePath VARCHAR(300),uploadDate DATETIME NOT NULL,views INT NOT NULL,duration VARCHAR(10) NOT NULL) ENGINE=MyISAM");
$db->exec("CREATE TABLE likes(id INT AUTO_INCREMENT PRIMARY KEY,username VARCHAR(50),videoId INT,commentId INT) ENGINE=MyISAM");
$db->exec("CREATE TABLE dislikes LIKE likes");
$db->exec("INSERT INTO users VALUES(1,'legacy','legacy@example.test','original-hash','Legacy','User','avatar.png','2020-01-01')");
$db->exec("INSERT INTO videos VALUES(1,'legacy','Original title','Original description',16,1,'original.mp4','2020-01-01',99,'01:02')");
$db->exec("INSERT INTO likes VALUES(1,'legacy',1,NULL)");
Migration::apply($db,'legacy',true);
if (in_array('role',$db->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN),true)) throw new RuntimeException('Dry run changed schema');
Migration::apply($db,'legacy');Migration::apply($db,'legacy');
if ($db->query('SELECT password FROM users WHERE id=1')->fetchColumn()!=='original-hash') throw new RuntimeException('Password overwritten');
if ($db->query('SELECT title FROM videos WHERE id=1')->fetchColumn()!=='Original title') throw new RuntimeException('Video lost');
if ((int)$db->query('SELECT commentId FROM likes WHERE id=1')->fetchColumn()!==0) throw new RuntimeException('Legacy default not migrated');
if ($db->query("SELECT role FROM users WHERE id=1")->fetchColumn()!=='admin') throw new RuntimeException('Administrator not promoted');
// Duplicate preflight must fail before changing the schema.
$db->exec('ALTER TABLE users DROP INDEX email_unique');
$db->exec("INSERT INTO users(username,email,password,firstName,lastName,profilePic) VALUES('second','legacy@example.test','hash','Other','User','avatar.png')");
try { Migration::apply($db,'legacy');throw new LogicException('Duplicate email accepted'); }
catch (RuntimeException $e) { if (!str_contains($e->getMessage(),'duplicate email')) throw $e; }
echo "Legacy migration, dry-run, repeatability, preservation and duplicate preflight passed.\n";
