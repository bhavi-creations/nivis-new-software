<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../config/business_profile.php';
$pdo=database_connection(); $profile=nivis_business_profile();
$pdo->beginTransaction();
try {
    $pdo->prepare('INSERT INTO company_settings (id,company_name,address,email,phone,state_name,state_code)
        VALUES (1,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE company_name=VALUES(company_name),address=VALUES(address),email=VALUES(email),phone=VALUES(phone)')
        ->execute(array_values($profile));
    $pdo->commit();
    echo "Business name, address, email and phone saved. Existing GSTIN, bank terms and historical invoices preserved.\n";
} catch(Throwable $error) {$pdo->rollBack();throw $error;}
