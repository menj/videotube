<?php
function rateLimit(PDO $db, string $scope, int $limit): void {
    $window=intdiv(time(),60);
    $key=hash('sha256',$scope . '|' . ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $window);
    $q=$db->prepare('INSERT INTO request_limits(id,hits,expiresAt) VALUES (?,1,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 MINUTE)) ON DUPLICATE KEY UPDATE hits=hits+1');
    $q->execute([$key]);
    $q=$db->prepare('SELECT hits FROM request_limits WHERE id=?'); $q->execute([$key]);
    if ((int)$q->fetchColumn()>$limit) { http_response_code(429); header('Retry-After: 60'); exit('Too many requests. Try again shortly.'); }
    $db->exec('DELETE FROM request_limits WHERE expiresAt < UTC_TIMESTAMP() LIMIT 100');
}
