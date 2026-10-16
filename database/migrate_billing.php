<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/billing.php';
$pdo=database_connection();
ensure_billing_columns($pdo);
ensure_document_columns($pdo);
echo "Billing migration complete. Existing records preserved.\n";
