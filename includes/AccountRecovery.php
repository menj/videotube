<?php
final class AccountRecovery {
    public function __construct(private PDO $db) {}
    public function issue(string $email, string $purpose): void {
        if (!in_array($purpose,['reset','verify'],true)) throw new InvalidArgumentException('Invalid purpose');
        $q=$this->db->prepare('SELECT * FROM users WHERE email=? AND suspended=0'); $q->execute([$email]); $user=$q->fetch(PDO::FETCH_ASSOC);
        if (!$user || ($purpose==='verify' && $user['emailVerifiedAt'])) return;
        $base=rtrim((string)getenv('VIDEOTUBE_BASE_URL'),'/');
        $test=getenv('VIDEOTUBE_ENV')==='test';
        if (!filter_var($base,FILTER_VALIDATE_URL) || (!str_starts_with($base,'https://') && !$test)) throw new RuntimeException('Configure VIDEOTUBE_BASE_URL with the public HTTPS URL.');
        $token=bin2hex(random_bytes(32)); $hash=hash('sha256',$token);
        $this->db->beginTransaction();
        try {
            $q=$this->db->prepare('SELECT id FROM users WHERE id=? FOR UPDATE');$q->execute([$user['id']]);
            $q=$this->db->prepare('DELETE FROM account_tokens WHERE userId=? AND purpose=?');$q->execute([$user['id'],$purpose]);
            $q=$this->db->prepare('INSERT INTO account_tokens(tokenHash,userId,purpose,email,passwordVersion,expiresAt) VALUES(?,?,?,?,?,?)');
            $q->execute([$hash,$user['id'],$purpose,$user['email'],hash('sha256',$user['password']),gmdate('Y-m-d H:i:s',time()+($purpose==='reset'?3600:86400))]);
            $this->db->commit();
        } catch (Throwable $e) { $this->db->rollBack(); throw $e; }
        $url=$base.'/account.php?purpose='.$purpose.'&token='.$token;
        $body="Open this link to ".($purpose==='reset'?'reset your password':'verify your email').":\n\n".$url."\n\nIf you did not request this, ignore this email.\n";
        try {
            if ($test && ($directory=getenv('VIDEOTUBE_TEST_MAIL_DIR'))) {
                $real=realpath($directory); $root=realpath(dirname(__DIR__));
                if (!$real || $real===$root || str_starts_with($real,$root.DIRECTORY_SEPARATOR)) throw new RuntimeException('Test outbox must be outside the document root.');
                $path=$real.'/'.bin2hex(random_bytes(16)).'.json';
                $mask=umask(0077);
                try { if (file_put_contents($path,json_encode(['to'=>$email,'purpose'=>$purpose,'url'=>$url],JSON_THROW_ON_ERROR))===false) throw new RuntimeException('Test mail failed'); }
                finally { umask($mask); }
            } else {
                $sender=getenv('VIDEOTUBE_MAIL_FROM');
                if (!is_string($sender) || !filter_var($sender,FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/',$sender)) throw new RuntimeException('Configure VIDEOTUBE_MAIL_FROM.');
                if (!mail($user['email'],'VideoTube account '.$purpose,$body,['From'=>$sender,'Content-Type'=>'text/plain; charset=UTF-8'])) throw new RuntimeException('Mail delivery failed');
            }
        } catch (Throwable $e) {
            $q=$this->db->prepare('DELETE FROM account_tokens WHERE tokenHash=?');$q->execute([$hash]);throw $e;
        }
    }
    public function consume(string $token, string $purpose, string $password=''): bool {
        if (!preg_match('/^[a-f0-9]{64}$/D',$token) || !in_array($purpose,['reset','verify'],true)) return false;
        if ($purpose==='reset' && (strlen($password)<12 || strlen($password)>72 || str_contains($password,"\0"))) return false;
        // Lock user before token, matching issuance lock order.
        $q=$this->db->prepare('SELECT userId FROM account_tokens WHERE tokenHash=?');$q->execute([hash('sha256',$token)]);$userId=$q->fetchColumn();
        if (!$userId) return false;
        $this->db->beginTransaction();
        try {
            $q=$this->db->prepare('SELECT * FROM users WHERE id=? AND suspended=0 FOR UPDATE');$q->execute([$userId]);$user=$q->fetch(PDO::FETCH_ASSOC);
            $q=$this->db->prepare('SELECT * FROM account_tokens WHERE tokenHash=? AND purpose=? AND expiresAt>UTC_TIMESTAMP() FOR UPDATE');$q->execute([hash('sha256',$token),$purpose]);$row=$q->fetch(PDO::FETCH_ASSOC);
            if (!$user || !$row || $row['email']!==$user['email'] || !hash_equals($row['passwordVersion'],hash('sha256',$user['password']))) { $this->db->rollBack(); return false; }
            if ($purpose==='reset') {
                $q=$this->db->prepare('UPDATE users SET password=? WHERE id=?');$q->execute([password_hash($password,PASSWORD_DEFAULT),$userId]);
                $q=$this->db->prepare('DELETE FROM account_tokens WHERE userId=?');$q->execute([$userId]);
            } else {
                $q=$this->db->prepare('UPDATE users SET emailVerifiedAt=UTC_TIMESTAMP() WHERE id=?');$q->execute([$userId]);
                $q=$this->db->prepare('DELETE FROM account_tokens WHERE tokenHash=?');$q->execute([hash('sha256',$token)]);
            }
            $this->db->commit();return true;
        } catch (Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack();throw $e; }
    }
}
