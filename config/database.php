<?php

function database_env(string $name, string $default): string
{
    static $config = null;
    if ($config === null) {
        $file = __DIR__ . '/database.local.php';
        $config = is_file($file) ? require $file : [];
        if (!is_array($config)) throw new RuntimeException('Database configuration must return an array.');
    }
    $value = getenv($name);
    return $value === false ? (string) ($config[$name] ?? $default) : $value;
}

function database_connection(bool $selectDatabase = true): PDO
{
    $host = database_env('NIVIS_DB_HOST', '127.0.0.1');
    $port = database_env('NIVIS_DB_PORT', '3306');
    $database = database_env('NIVIS_DB_NAME', 'nivis_labs_billing');
    $user = database_env('NIVIS_DB_USER', 'root');
    $password = database_env('NIVIS_DB_PASSWORD', '');
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $database) || !ctype_digit($port) ||
        (int) $port < 1 || (int) $port > 65535 || preg_match('/[;\r\n]/', $host)) {
        throw new RuntimeException('Invalid database host, port or database name.');
    }
    date_default_timezone_set('Asia/Kolkata');
    $databasePart = $selectDatabase ? ';dbname=' . $database : '';
    $dsn = 'mysql:host=' . $host . ';port=' . $port . $databasePart . ';charset=utf8mb4';

    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET time_zone = '+05:30'");
    return $pdo;
}
