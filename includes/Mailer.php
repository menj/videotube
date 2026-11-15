<?php
final class Mailer
{
    /**
     * Send a plain-text message. With VIDEOTUBE_ENV=test and VIDEOTUBE_TEST_MAIL_DIR set, the
     * message is written to that outbox instead (automated tests only).
     *
     * @param array<string,string> $meta extra fields recorded in the test outbox
     */
    public static function send(string $to, string $subject, string $body, array $meta = []): void
    {
        if (preg_match('/[\r\n]/', $to . $subject) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Invalid mail recipient or subject.');
        }
        $directory = getenv('VIDEOTUBE_TEST_MAIL_DIR');
        if (getenv('VIDEOTUBE_ENV') === 'test' && $directory) {
            $real = realpath($directory);
            $root = realpath(dirname(__DIR__));
            if (!$real || $real === $root || str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
                throw new RuntimeException('Test outbox must be outside the document root.');
            }
            $mask = umask(0077);
            try {
                $message = json_encode(['to' => $to, 'subject' => $subject, 'body' => $body] + $meta, JSON_THROW_ON_ERROR);
                if (file_put_contents($real . '/' . bin2hex(random_bytes(16)) . '.json', $message) === false) {
                    throw new RuntimeException('Test mail failed');
                }
            } finally {
                umask($mask);
            }
            return;
        }
        $sender = getenv('VIDEOTUBE_MAIL_FROM');
        if (!is_string($sender) || !filter_var($sender, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $sender)) {
            throw new RuntimeException('Configure VIDEOTUBE_MAIL_FROM.');
        }
        if (!mail($to, $subject, $body, ['From' => $sender, 'Content-Type' => 'text/plain; charset=UTF-8'])) {
            throw new RuntimeException('Mail delivery failed');
        }
    }
}
