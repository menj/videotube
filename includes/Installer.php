<?php
final class Installer {
    public static function install(PDO $db, array $account, string $schema): void {
        if ($db->query('SHOW TABLES')->fetchColumn() !== false) {
            throw new RuntimeException('Choose an empty database. Existing tables will never be overwritten.');
        }
        if (!preg_match('/^[A-Za-z0-9_]{5,25}$/D', $account['username'])
            || !filter_var($account['email'], FILTER_VALIDATE_EMAIL)
            || strlen($account['password']) < 12 || strlen($account['password']) > 72) {
            throw new InvalidArgumentException('Use a 5–25 character username (letters, digits, underscore), a valid email, and a 12–72 byte password.');
        }
        // MySQL DDL commits implicitly. A failed install must be retried with a fresh empty database.
        foreach (explode(';', $schema) as $sql) if (trim($sql) !== '') $db->exec($sql);
        $q = $db->prepare('INSERT INTO users(firstName,lastName,username,email,password,profilePic) VALUES (?,?,?,?,?,?)');
        $q->execute(['Site','Owner',$account['username'],$account['email'],password_hash($account['password'], PASSWORD_DEFAULT),'assets/images/profilePictures/default.png']);
    }
}
