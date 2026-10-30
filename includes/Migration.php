<?php
final class Migration {
    public static function apply(PDO $db, string $admin, bool $dryRun=false): array {
        $lock='videotube-migrate-'.substr(hash('sha256',(string)$db->query('SELECT DATABASE()')->fetchColumn()),0,40);
        $q=$db->prepare('SELECT GET_LOCK(?,0)');$q->execute([$lock]);if ((int)$q->fetchColumn()!==1) throw new RuntimeException('Migration already running.');
        try {
            $tables=$db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            foreach (['users'=>['id','username','email','password','firstName','lastName','profilePic','singUpDate'],'videos'=>['id','uploadedBy','title','description','category','privacy','filePath','uploadDate','views','duration']] as $table=>$required) {
                if (!in_array($table,$tables,true)) throw new RuntimeException('Unsupported database: missing '.$table);
                $columns=$db->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($required as $column) if (!in_array($column,$columns,true)) throw new RuntimeException('Unsupported database: missing '.$table.'.'.$column);
            }
            $q=$db->prepare('SELECT id FROM users WHERE BINARY username=BINARY ?');$q->execute([$admin]);$adminId=$q->fetchColumn();if (!$adminId) throw new RuntimeException('Choose an existing administrator username with exact casing.');
            foreach (['username','email'] as $column) {
                if ($db->query("SELECT `$column` FROM users GROUP BY `$column` HAVING COUNT(*)>1 LIMIT 1")->fetchColumn()!==false) throw new RuntimeException('Resolve duplicate '.$column.' values before migrating.');
            }
            if ($db->query('SELECT id FROM users WHERE CHAR_LENGTH(email)>191 LIMIT 1')->fetchColumn()!==false) throw new RuntimeException('Email longer than 191 characters; resolve before migrating.');
            foreach (['likes','dislikes'] as $table) if (in_array($table,$tables,true)) {
                if ($db->query("SELECT id FROM `$table` WHERE username IS NULL LIMIT 1")->fetchColumn()!==false) throw new RuntimeException('Resolve null vote usernames.');
                if ($db->query("SELECT username FROM `$table` GROUP BY username,COALESCE(videoId,0),COALESCE(commentId,0) HAVING COUNT(*)>1 LIMIT 1")->fetchColumn()!==false) throw new RuntimeException('Resolve duplicate '.$table.' votes before migrating; no records are discarded automatically.');
            }
            if (in_array('subscribers',$tables,true) && $db->query('SELECT userTo FROM subscribers GROUP BY userTo,userFrom HAVING COUNT(*)>1 LIMIT 1')->fetchColumn()!==false) throw new RuntimeException('Resolve duplicate subscriptions.');
            $plan=[];
            $schema=file_get_contents(__DIR__.'/../database/schema.sql');
            foreach (explode(';',$schema) as $sql) if (preg_match('/^\s*CREATE TABLE\s+`?([a-zA-Z0-9_]+)`?/i',$sql,$m) && !in_array($m[1],$tables,true)) $plan[]=$sql;
            if (!in_array('categories',$tables,true)) foreach (explode(';',$schema) as $sql) if (preg_match('/^\s*INSERT INTO `categories`/i',$sql)) $plan[]=$sql;
            $columns=$db->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
            foreach (['role'=>"VARCHAR(16) NOT NULL DEFAULT 'user'",'suspended'=>'TINYINT NOT NULL DEFAULT 0','sessionVersion'=>'INT NOT NULL DEFAULT 0','emailVerifiedAt'=>'DATETIME NULL'] as $name=>$definition) if (!in_array($name,$columns,true)) $plan[]="ALTER TABLE users ADD `$name` $definition";
            $columns=$db->query('SHOW COLUMNS FROM videos')->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('uploadJobId',$columns,true)) $plan[]='ALTER TABLE videos ADD uploadJobId BIGINT NULL UNIQUE';
            $plan[]='ALTER TABLE users MODIFY password VARCHAR(255) NOT NULL, MODIFY email VARCHAR(191) NOT NULL, ALTER singUpDate SET DEFAULT CURRENT_TIMESTAMP';
            $plan[]="ALTER TABLE videos ALTER uploadDate SET DEFAULT CURRENT_TIMESTAMP, ALTER views SET DEFAULT 0, ALTER duration SET DEFAULT '00:00'";
            foreach (['likes','dislikes'] as $table) if (in_array($table,$tables,true)) {
                $plan[]="UPDATE `$table` SET videoId=COALESCE(videoId,0),commentId=COALESCE(commentId,0)";
                $plan[]="ALTER TABLE `$table` MODIFY videoId INT NOT NULL DEFAULT 0, MODIFY commentId INT NOT NULL DEFAULT 0";
            }
            foreach (['users'=>['username_unique'=>['username'],'email_unique'=>['email']],'likes'=>['vote_unique'=>['username','videoId','commentId']],'dislikes'=>['vote_unique'=>['username','videoId','commentId']],'subscribers'=>['subscription'=>['userTo','userFrom']]] as $table=>$indexes) if (in_array($table,$tables,true)) {
                $existing=[];
                foreach ($db->query("SHOW INDEX FROM `$table`") as $index) if (!$index['Non_unique']) $existing[$index['Key_name']][(int)$index['Seq_in_index']]=$index['Column_name'];
                foreach ($indexes as $name=>$columns) {
                    $found=false;foreach ($existing as $cols) { ksort($cols);if (array_values($cols)===$columns) $found=true; }
                    if (!$found) $plan[]="ALTER TABLE `$table` ADD UNIQUE KEY `$name` (".implode(',',$columns).')';
                }
            }
            foreach ($tables as $table) if (in_array($table,['users','videos','likes','dislikes','comments','subscribers','thumbnails','categories','request_limits'],true)) $plan[]="ALTER TABLE `$table` ENGINE=InnoDB";
            if ($dryRun) return $plan;
            // DDL commits implicitly. Every step is repeatable; never drop user tables or rows.
            foreach ($plan as $sql) $db->exec($sql);
            $db->beginTransaction();
            try {
                $q=$db->prepare("UPDATE users SET role='admin',suspended=0,emailVerifiedAt=COALESCE(emailVerifiedAt,UTC_TIMESTAMP()) WHERE id=?");$q->execute([$adminId]);
                $db->exec('INSERT IGNORE INTO schema_migrations(version) VALUES(1)');$db->commit();
            } catch (Throwable $e) { $db->rollBack();throw $e; }
            return $plan;
        } finally { $q=$db->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]); }
    }
}
