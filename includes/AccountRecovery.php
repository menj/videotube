<?php
final class AccountRecovery
{
    public function __construct(private PDO $db)
    {
    }

    /** Email a single-use reset or verification link. Unknown addresses are ignored silently. */
    public function issue(string $email, string $purpose): void
    {
        if (!in_array($purpose, ['reset', 'verify'], true)) {
            throw new InvalidArgumentException('Invalid purpose');
        }
        $q = $this->db->prepare('SELECT * FROM users WHERE email=? AND suspended=0');
        $q->execute([$email]);
        $user = $q->fetch(PDO::FETCH_ASSOC);
        if (!$user || ($purpose === 'verify' && $user['emailVerifiedAt'])) {
            return;
        }
        $base = rtrim((string)getenv('VIDEOTUBE_BASE_URL'), '/');
        $test = getenv('VIDEOTUBE_ENV') === 'test';
        if (!filter_var($base, FILTER_VALIDATE_URL) || (!str_starts_with($base, 'https://') && !$test)) {
            throw new RuntimeException('Configure VIDEOTUBE_BASE_URL with the public HTTPS URL.');
        }
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $this->db->beginTransaction();
        try {
            $q = $this->db->prepare('SELECT id FROM users WHERE id=? FOR UPDATE');
            $q->execute([$user['id']]);
            $q = $this->db->prepare('DELETE FROM account_tokens WHERE userId=? AND purpose=?');
            $q->execute([$user['id'], $purpose]);
            $q = $this->db->prepare('INSERT INTO account_tokens(tokenHash,userId,purpose,email,passwordVersion,expiresAt) VALUES(?,?,?,?,?,?)');
            $lifetime = $purpose === 'reset' ? 3600 : 86400;
            $q->execute([$hash, $user['id'], $purpose, $user['email'], hash('sha256', $user['password']), gmdate('Y-m-d H:i:s', time() + $lifetime)]);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        $url = $base . '/account.php?purpose=' . $purpose . '&token=' . $token;
        $action = $purpose === 'reset' ? 'reset your password' : 'verify your email';
        $body = "Open this link to $action:\n\n$url\n\nIf you did not request this, ignore this email.\n";
        try {
            Mailer::send($user['email'], 'VideoTube account ' . $purpose, $body, ['purpose' => $purpose, 'url' => $url]);
        } catch (Throwable $e) {
            $q = $this->db->prepare('DELETE FROM account_tokens WHERE tokenHash=?');
            $q->execute([$hash]);
            throw $e;
        }
    }

    /** Tell the previous address that the account email was changed. */
    public function notifyEmailChanged(string $oldEmail, string $username): void
    {
        $body = "The email address of the VideoTube account \"$username\" was changed.\n\n"
            . "If this was you, no action is needed. If it was not, reset your password now and contact the site administrator.\n";
        Mailer::send($oldEmail, 'VideoTube account email changed', $body, ['purpose' => 'email-changed']);
    }

    public function consume(string $token, string $purpose, string $password = ''): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token) || !in_array($purpose, ['reset', 'verify'], true)) {
            return false;
        }
        if ($purpose === 'reset' && (strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0"))) {
            return false;
        }
        // Lock user before token, matching issuance lock order.
        $tokenHash = hash('sha256', $token);
        $q = $this->db->prepare('SELECT userId FROM account_tokens WHERE tokenHash=?');
        $q->execute([$tokenHash]);
        $userId = $q->fetchColumn();
        if (!$userId) {
            return false;
        }
        $this->db->beginTransaction();
        try {
            $q = $this->db->prepare('SELECT * FROM users WHERE id=? AND suspended=0 FOR UPDATE');
            $q->execute([$userId]);
            $user = $q->fetch(PDO::FETCH_ASSOC);
            $q = $this->db->prepare('SELECT * FROM account_tokens WHERE tokenHash=? AND purpose=? AND expiresAt>UTC_TIMESTAMP() FOR UPDATE');
            $q->execute([$tokenHash, $purpose]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            if (!$user || !$row || $row['email'] !== $user['email'] || !hash_equals($row['passwordVersion'], hash('sha256', $user['password']))) {
                $this->db->rollBack();
                return false;
            }
            if ($purpose === 'reset') {
                $q = $this->db->prepare('UPDATE users SET password=? WHERE id=?');
                $q->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
                $q = $this->db->prepare('DELETE FROM account_tokens WHERE userId=?');
                $q->execute([$userId]);
            } else {
                $q = $this->db->prepare('UPDATE users SET emailVerifiedAt=UTC_TIMESTAMP() WHERE id=?');
                $q->execute([$userId]);
                $q = $this->db->prepare('DELETE FROM account_tokens WHERE tokenHash=?');
                $q->execute([$tokenHash]);
            }
            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
