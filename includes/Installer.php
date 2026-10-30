<?php
final class Installer {
    public static function install(PDO $db, array $account, string $schema, ?callable $publish = null): void {
        $lock = 'videotube-install-' . substr(hash('sha256', (string)$db->query('SELECT DATABASE()')->fetchColumn()), 0, 40);
        $q = $db->prepare('SELECT GET_LOCK(?, 0)'); $q->execute([$lock]);
        if ((int)$q->fetchColumn() !== 1) throw new RuntimeException('Installation is already running.');
        $created = [];
        try {
            if ($db->query('SHOW TABLES')->fetchColumn() !== false) throw new RuntimeException('Choose an empty database. Existing tables will never be overwritten.');
            foreach (['username','email','password'] as $field) if (!is_string($account[$field] ?? null)) throw new InvalidArgumentException('Invalid account details.');
            if (!preg_match('/^[A-Za-z0-9_]{5,25}$/D', $account['username'])
                || strlen($account['email']) > 191 || !filter_var($account['email'], FILTER_VALIDATE_EMAIL)
                || strlen($account['password']) < 12 || strlen($account['password']) > 72 || str_contains($account['password'], "\0")) {
                throw new InvalidArgumentException('Use a 5–25 character username (letters, digits, underscore), a valid email, and a 12–72 byte password.');
            }
            foreach (explode(';', $schema) as $sql) {
                if (trim($sql) === '') continue;
                $db->exec($sql);
                if (preg_match('/^\s*CREATE TABLE\s+`?([a-zA-Z0-9_]+)`?/i', $sql, $m)) $created[] = $m[1];
            }
            $q = $db->prepare("INSERT INTO users(firstName,lastName,username,email,password,profilePic,role,emailVerifiedAt) VALUES (?,?,?,?,?,?,'admin',UTC_TIMESTAMP())");
            $q->execute(['Site','Owner',$account['username'],$account['email'],password_hash($account['password'], PASSWORD_DEFAULT),'assets/images/profilePictures/default.png']);
            if ($publish !== null) $publish();
        } catch (Throwable $error) {
            // MySQL DDL is not transactional: undo only tables this attempt created.
            foreach (array_reverse($created) as $table) {
                try { $db->exec('DROP TABLE `' . $table . '`'); }
                catch (Throwable $cleanup) { error_log('Installer rollback failed: ' . $cleanup->getMessage()); }
            }
            throw $error;
        } finally {
            $q = $db->prepare('SELECT RELEASE_LOCK(?)'); $q->execute([$lock]);
        }
    }

    public static function stageConfig(string $destination, array $config): string {
        $mask = umask(0077);
        try {
            $temp = tempnam(dirname($destination), '.install-');
            if ($temp === false) throw new RuntimeException('Cannot stage configuration.');
            $content = '<?php return ' . var_export($config, true) . ';';
            if (file_put_contents($temp, $content, LOCK_EX) !== strlen($content)) {
                unlink($temp); throw new RuntimeException('Configuration write failed.');
            }
            return $temp;
        } finally { umask($mask); }
    }
}
