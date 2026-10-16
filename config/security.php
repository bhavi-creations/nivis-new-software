<?php
// Local XAMPP remains usable until accounts are configured. Remote access requires a user.
function nivis_session(): void {
    if (PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) return;
    if (!@session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Strict', 'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'use_strict_mode' => true])) {
        throw new RuntimeException('PHP session storage is unavailable. Check session.save_path permissions.');
    }
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}
function nivis_access(PDO $pdo, bool $write = false): ?array {
    if (PHP_SAPI === 'cli') return null;
    nivis_session();
    $user = null;
    if (!empty($_SESSION['user_id'])) {
        $q = $pdo->prepare('SELECT id, role FROM users WHERE id=? AND is_active=1');
        $q->execute([$_SESSION['user_id']]); $user = $q->fetch() ?: null;
    }
    $local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true);
    if (!$user && (!$local || (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0)) {
        http_response_code(401); return ['error' => 'Sign in at login.php to continue.'];
    }
    if ($write && $user && !in_array($user['role'], ['admin','manager'], true)) {
        http_response_code(403); return ['error' => 'Your account has read-only access.'];
    }
    return null;
}
