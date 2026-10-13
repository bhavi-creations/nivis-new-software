<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Run this installer from the command line only.\n");
}

require_once __DIR__ . '/../config/database.php';

$files = [
    'database_users.sql',
    'customers.sql',
    'categories.sql',
    'tax_gst.sql',
    'products.sql',
    'quotations.sql',
    'invoices.sql',
    'payments.sql',
    'reports_dashboard.sql',
    'settings_audit.sql',
];

try {
    $pdo = database_connection();
    foreach ($files as $file) {
        $path = __DIR__ . DIRECTORY_SEPARATOR . $file;
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Cannot read schema file: ' . $file);
        }

        $contents = preg_replace('/^\s*--.*$/m', '', $contents);
        // Ignore export database names; install only into the configured database.
        $contents = preg_replace('/^\s*(?:CREATE DATABASE\b[^;]*|USE\s+[^;]+);/mi', '', $contents);
        $statements = preg_split('/;\s*(?:\r?\n|$)/', $contents);
        foreach ($statements as $statement) {
            if (trim($statement) !== '') {
                $pdo->exec($statement);
            }
        }
        echo 'Installed ' . $file . PHP_EOL;
    }

    $insertRate = $pdo->prepare(
        "INSERT INTO tax_rates (name, rate_percent, treatment, effective_from, is_enabled)
         SELECT ?, ?, ?, CURRENT_DATE(), 1
         WHERE NOT EXISTS (SELECT 1 FROM tax_rates WHERE rate_percent = ?)"
    );
    foreach ([0, 5, 12, 18, 28] as $rate) {
        $treatment = $rate === 0 ? 'zero_rated' : 'taxable';
        $insertRate->execute([
            'Illustrative ' . $rate . '% - verify applicability',
            $rate,
            $treatment,
            $rate,
        ]);
    }

    $taxRateId = (int) $pdo->query(
        "SELECT id FROM tax_rates WHERE rate_percent = 18.000 ORDER BY id LIMIT 1"
    )->fetchColumn();

    $pdo->beginTransaction();
    if ((int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn() === 0) {
        $insertCategory = $pdo->prepare(
            "INSERT INTO categories (name, code, description) VALUES (?, ?, ?)"
        );
        foreach ([
            ['Sun Care', 'SUN', 'Daily sunscreen and sun-protection products'],
            ['Moisturizers', 'MOI', 'Hydrating and barrier-care products'],
            ['Serums', 'SER', 'Targeted face serums and treatments'],
            ['Cleansers', 'CLE', 'Gentle daily skin cleansers'],
        ] as $category) {
            $insertCategory->execute($category);
        }
    }

    if ((int) $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn() === 0) {
        $insertCustomer = $pdo->prepare(
            "INSERT INTO customers (name, mobile, customer_type, gst_registration)
             VALUES (?, ?, 'unregistered', 'unregistered')"
        );
        $insertAddress = $pdo->prepare(
            "INSERT INTO customer_addresses
             (customer_id, address_type, address_line1, city, state_name, state_code, pincode)
             VALUES (?, 'billing', ?, ?, ?, ?, ?)"
        );
        foreach ([
            ['Sri Lakshmi Traders', '9876543210', 'Main Road', 'Kakinada', 'Andhra Pradesh', '37', '533001'],
            ['Sree Agencies', '9123456780', 'Market Street', 'Bengaluru', 'Karnataka', '29', '560001'],
            ['Ravi Enterprises', '9988776655', 'Lake View Road', 'Hyderabad', 'Telangana', '36', '500001'],
            ['Glow & Co. Beauty Store', '9844030010', 'Beach Road', 'Visakhapatnam', 'Andhra Pradesh', '37', '530001'],
        ] as $customer) {
            $insertCustomer->execute([$customer[0], $customer[1]]);
            $insertAddress->execute([
                (int) $pdo->lastInsertId(),
                $customer[2], $customer[3], $customer[4], $customer[5], $customer[6],
            ]);
        }
    }

    if ((int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() === 0) {
        $categoryIds = $pdo->query('SELECT code, id FROM categories')->fetchAll(PDO::FETCH_KEY_PAIR);
        $sampleCodes = ['SUN', 'MOI', 'SER', 'CLE'];
        if (count(array_intersect_key($categoryIds, array_flip($sampleCodes))) === count($sampleCodes)) {
            $insertProduct = $pdo->prepare(
                "INSERT INTO products
                    (name, sku, category_id, hsn_code, unit, selling_price, tax_rate_id, description)
                 VALUES (?, ?, ?, ?, 'PCS', ?, ?, ?)"
            );
            foreach ([
                ['Daily Shield SPF 50 Sunscreen', 'SUN-050', 'SUN', '330499', 799, 'Broad-spectrum daily sun protection.'],
                ['Hydra Cloud Moisturizer', 'MOI-010', 'MOI', '330499', 649, 'Lightweight daily hydration.'],
                ['Vitamin C Brightening Serum', 'SER-020', 'SER', '330499', 899, 'Daily brightening skin-care serum.'],
                ['Gentle Foaming Cleanser', 'CLE-015', 'CLE', '340130', 499, 'Gentle everyday face cleanser.'],
                ['Barrier Repair Moisturizer', 'MOI-025', 'MOI', '330499', 749, 'Rich moisturizer for skin barrier support.'],
            ] as $product) {
                $insertProduct->execute([
                    $product[0],
                    $product[1],
                    $categoryIds[$product[2]],
                    $product[3],
                    $product[4],
                    $taxRateId,
                    $product[5],
                ]);
            }
        }
    }

    $pdo->commit();
    echo 'Database schema and illustrative beauty product data are ready.' . PHP_EOL;
    echo 'Sample tax rates are illustrative only; verify tax applicability before use.' . PHP_EOL;
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Database setup failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
