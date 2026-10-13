<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/schema.php';
try {
    $pdo = database_connection();
    ensure_identity_columns($pdo, ['categories', 'products', 'customers', 'customer_addresses']);
    echo "Product, category and customer identity repair complete. Existing IDs preserved.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Identity repair failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}