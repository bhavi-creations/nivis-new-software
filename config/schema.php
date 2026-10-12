<?php
/** Add only the optional columns introduced by the billing update. */
function ensure_billing_columns(PDO $pdo, ?string $resource = null): void
{
    $tables = [
        'products' => [
            'default_quantity' => 'DECIMAL(12,3) NOT NULL DEFAULT 1',
            'gst_region' => "ENUM('inside_ap','outside_ap') NOT NULL DEFAULT 'inside_ap'",
        ],
        'categories' => ['product_count' => 'INT UNSIGNED NULL DEFAULT NULL'],
    ];
    foreach ($tables as $table => $definitions) {
        if ($resource !== null && $resource !== $table) continue;
        $existing = $pdo->query('SHOW COLUMNS FROM ' . $table)->fetchAll(PDO::FETCH_COLUMN);
        foreach ($definitions as $column => $definition) {
            if (in_array($column, $existing, true)) continue;
            try {
                $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
            } catch (PDOException $error) {
                // A concurrent request may have just added the same column.
                if ((int) ($error->errorInfo[1] ?? 0) !== 1060) throw $error;
            }
        }
    }
}
