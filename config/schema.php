<?php
/** Restore imported identities before starting a write transaction. */
function ensure_identity_columns(PDO $pdo, array $tables): void
{
    if ($pdo->inTransaction()) {
        throw new RuntimeException('Identity migration must run outside a transaction.');
    }
    foreach ($tables as $table) {
        if (!in_array($table, ['products', 'categories', 'customers', 'customer_addresses'], true)) {
            throw new InvalidArgumentException('Unsupported identity table.');
        }
        $column = $pdo->query("SHOW COLUMNS FROM `$table` WHERE Field = 'id'")->fetch();
        if ($column && stripos($column['Extra'], 'auto_increment') !== false) continue;
        if (!$column || !preg_match('/^(tinyint|smallint|mediumint|int|bigint)(\([0-9]+\))?( unsigned)?$/i', $column['Type'])) {
            throw new RuntimeException("Unsupported identity definition in $table; manual schema review required.");
        }
        $primary = $pdo->query("SHOW INDEX FROM `$table` WHERE Key_name = 'PRIMARY'")->fetchAll();
        if (count($primary) !== 1 || $primary[0]['Column_name'] !== 'id') {
            throw new RuntimeException("Expected a single-column id primary key in $table; manual schema review required.");
        }
        // ALTER can renumber zero identities; never silently change existing IDs.
        if ($pdo->query("SELECT 1 FROM `$table` WHERE id <= 0 LIMIT 1")->fetchColumn() !== false) {
            throw new RuntimeException("Non-positive identities in $table; manual schema review required.");
        }
        $pdo->exec("ALTER TABLE `$table` MODIFY COLUMN `id` " . $column['Type'] . ' NOT NULL AUTO_INCREMENT');
    }
}

/** Retained for the existing customer migration command. */
function ensure_customer_identity_columns(PDO $pdo): void
{
    ensure_identity_columns($pdo, ['customers', 'customer_addresses']);
}
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
