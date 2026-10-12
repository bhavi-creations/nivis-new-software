<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../config/database.php';
$p = database_connection();
$columns = $p->query('SHOW COLUMNS FROM products')->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('default_quantity', $columns, true)) $p->exec('ALTER TABLE products ADD default_quantity DECIMAL(12,3) NOT NULL DEFAULT 1');
if (!in_array('gst_region', $columns, true)) $p->exec("ALTER TABLE products ADD gst_region ENUM('inside_ap','outside_ap') NOT NULL DEFAULT 'inside_ap'");
$categoryColumns = $p->query('SHOW COLUMNS FROM categories')->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('product_count', $categoryColumns, true)) $p->exec('ALTER TABLE categories ADD product_count INT UNSIGNED NULL DEFAULT NULL');
echo "Product defaults migration complete.\n";
