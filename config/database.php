<?php

function database_env(string $name, string $default): string
{
    $value = getenv($name);
    return $value === false ? $default : $value;
}

function database_connection(bool $selectDatabase = true): PDO
{
    $host = database_env('NIVIS_DB_HOST', '127.0.0.1');
    $port = database_env('NIVIS_DB_PORT', '3306');
    $database = database_env('NIVIS_DB_NAME', 'nivis_labs_billing');
    $user = database_env('NIVIS_DB_USER', 'root');
    $password = database_env('NIVIS_DB_PASSWORD', '');
    $databasePart = $selectDatabase ? ';dbname=' . $database : '';
    $dsn = 'mysql:host=' . $host . ';port=' . $port . $databasePart . ';charset=utf8mb4';

    return new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}
