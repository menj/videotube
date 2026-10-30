<?php
function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function inputText(string $key, array $source): string { return is_string($source[$key] ?? null) ? $source[$key] : ''; }
function secureTransport(): bool { return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || getenv('VIDEOTUBE_HTTPS') === '1'; }
function localRequest(): bool { return PHP_SAPI === 'cli-server' && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'],true); }
function csrfToken(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function requireLogin(): string {
    $user = $_SESSION['userLoggedIn'] ?? '';
    if (!is_string($user) || $user === '') { http_response_code(403); exit('Sign in to continue.'); }
    return $user;
}
function startAuthenticatedSession(PDO $db, string $username): void {
    $q=$db->prepare('SELECT username,password,sessionVersion FROM users WHERE username=? AND suspended=0');
    $q->execute([$username]); $account=$q->fetch(PDO::FETCH_ASSOC);
    if (!$account) throw new RuntimeException('Account no longer exists.');
    session_regenerate_id(true);
    $_SESSION['userLoggedIn']=$account['username'];
    $_SESSION['authVersion']=hash('sha256',$account['password'].':'.$account['sessionVersion']);
    $_SESSION['csrf']=bin2hex(random_bytes(32));
}
function applicationSecurity(): void {
    ini_set('display_errors','0');
    set_exception_handler(function(Throwable $error) {
        error_log((string)$error);
        if (PHP_SAPI==='cli') exit(1);
        http_response_code(500); echo 'The request could not be completed. Check the server log.';
    });
    if (PHP_SAPI === 'cli') return;
    if (!secureTransport() && !localRequest()) { http_response_code(400); exit('HTTPS is required.'); }
    header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    if (secureTransport()) header('Strict-Transport-Security: max-age=31536000');
    if (session_status() !== PHP_SESSION_ACTIVE) session_start(['use_strict_mode'=>true,'cookie_httponly'=>true,'cookie_secure'=>secureTransport(),'cookie_samesite'=>'Lax']);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');
        if (!is_string($token) || !hash_equals(csrfToken(),$token)) { http_response_code(403); exit('Invalid session token. Reload and try again.'); }
    }
    // Include the token in every server-rendered POST form, including legacy form providers.
    ob_start(function($html) {
        return preg_replace_callback('~<form\b[^>]*>~i', function($match) {
            return preg_match('~method\s*=\s*["\']?post\b~i',$match[0])
                ? $match[0] . '<input type="hidden" name="csrf" value="' . h(csrfToken()) . '">' : $match[0];
        }, $html);
    });
}

function requireVerified(PDO $db): string {
    $username=requireLogin();
    $q=$db->prepare('SELECT emailVerifiedAt FROM users WHERE username=? AND suspended=0'); $q->execute([$username]);
    if (!$q->fetchColumn()) { http_response_code(403); exit('Verify your email at account.php before posting or uploading.'); }
    return $username;
}
function requireAdmin(PDO $db): string {
    $username=requireLogin();
    $q=$db->prepare("SELECT id FROM users WHERE username=? AND role='admin' AND suspended=0"); $q->execute([$username]);
    if (!$q->fetchColumn()) { http_response_code(403); exit('Administrator access required.'); }
    return $username;
}
