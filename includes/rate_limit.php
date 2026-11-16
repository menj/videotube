<?php

/** Opaque counter id for a scope, a subject (address, account…) and a time window. */
function rateLimitKey(string $scope, string $subject, int $windowSeconds): string
{
    return hash('sha256', $scope . '|' . $subject . '|' . intdiv(time(), $windowSeconds));
}

function rateLimitCount(PDO $db, string $key): int
{
    $q = $db->prepare('SELECT hits FROM request_limits WHERE id=?');
    $q->execute([$key]);
    return (int)$q->fetchColumn();
}

/** Count one event against the key and return the new total. */
function rateLimitHit(PDO $db, string $key, int $windowSeconds): int
{
    $expires = gmdate('Y-m-d H:i:s', time() + 2 * $windowSeconds);
    $q = $db->prepare('INSERT INTO request_limits(id,hits,expiresAt) VALUES (?,1,?) ON DUPLICATE KEY UPDATE hits=hits+1');
    $q->execute([$key, $expires]);
    $db->exec('DELETE FROM request_limits WHERE expiresAt < UTC_TIMESTAMP() LIMIT 100');
    return rateLimitCount($db, $key);
}

/** Per-client, per-minute ceiling on POST requests to one script. */
function rateLimit(PDO $db, string $scope, int $limit): void
{
    if (rateLimitHit($db, rateLimitKey($scope, clientAddress(), 60), 60) > $limit) {
        http_response_code(429);
        header('Retry-After: 60');
        exit('Too many requests. Try again shortly.');
    }
}

const LOGIN_FAILURE_LIMIT = 10;
const LOGIN_FAILURE_WINDOW = 900;

/** Failed sign-ins are also counted per account, so one account cannot be guessed from many addresses. */
function loginFailureKey(string $username): string
{
    return rateLimitKey('login-failure', strtolower($username), LOGIN_FAILURE_WINDOW);
}
