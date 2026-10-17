<?php
// Direct access uses sessions for CSRF protection without account login.
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
    unset($_SESSION['user_id'], $_SESSION['login_attempts'], $_SESSION['login_time']);
    return null;
}
