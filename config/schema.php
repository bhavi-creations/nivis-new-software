<?php
require_once __DIR__ . '/errors.php';
/** Restore imported identities before starting a write transaction. */
function ensure_identity_columns(PDO $pdo, array $tables): void
{
    if ($pdo->inTransaction()) {
        throw new NivisConfigurationException('Identity migration must run outside a transaction.');
    }
    foreach ($tables as $table) {
        if (!in_array($table, ['products', 'categories', 'customers', 'customer_addresses'], true)) {
            throw new InvalidArgumentException('Unsupported identity table.');
        }
        $column = $pdo->query("SHOW COLUMNS FROM `$table` WHERE Field = 'id'")->fetch();
        if ($column && stripos($column['Extra'], 'auto_increment') !== false) continue;
        if (!$column || !preg_match('/^(tinyint|smallint|mediumint|int|bigint)(\([0-9]+\))?( unsigned)?$/i', $column['Type'])) {
            throw new NivisConfigurationException("Unsupported identity definition in $table; manual schema review required.");
        }
        $primary = $pdo->query("SHOW INDEX FROM `$table` WHERE Key_name = 'PRIMARY'")->fetchAll();
        // ALTER can renumber zero identities; never silently change existing IDs.
        if ($pdo->query("SELECT 1 FROM `$table` WHERE id IS NULL OR id <= 0 LIMIT 1")->fetchColumn() !== false) {
            throw new NivisConfigurationException("Non-positive identities in $table; manual schema review required.");
        }
        if ($pdo->query("SELECT id FROM `$table` GROUP BY id HAVING COUNT(*) > 1 LIMIT 1")->fetchColumn() !== false) {
            throw new NivisConfigurationException("Duplicate IDs in $table. Existing data was preserved; resolve duplicate IDs before adding records.");
        }
        $keyClause = '';
        if (!$primary) {
            $keyClause = ', ADD PRIMARY KEY (`id`)';
        } elseif (count($primary) !== 1 || $primary[0]['Column_name'] !== 'id') {
            // Preserve an existing business/composite primary key.
            $indexes = [];
            foreach ($pdo->query("SHOW INDEX FROM `$table`")->fetchAll() as $index) {
                $indexes[$index['Key_name']][] = $index;
            }
            $uniqueId = false;
            foreach ($indexes as $parts) {
                if (count($parts) === 1 && (int) $parts[0]['Non_unique'] === 0 && $parts[0]['Column_name'] === 'id') $uniqueId = true;
            }
            if (!$uniqueId) {
                $keyName = 'nivis_identity_id';
                while (isset($indexes[$keyName])) $keyName .= '_x';
                $keyClause = ', ADD UNIQUE KEY `' . $keyName . '` (`id`)';
            }
        }
        // Add the index and identity together, preserving existing IDs and references.
        $pdo->exec("ALTER TABLE `$table` MODIFY COLUMN `id` " . $column['Type'] . ' NOT NULL AUTO_INCREMENT' . $keyClause);
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
            'image_path' => 'VARCHAR(255) NULL DEFAULT NULL',
            'default_quantity' => 'DECIMAL(12,3) NOT NULL DEFAULT 1',
            'gst_region' => "ENUM('inside_ap','outside_ap') NOT NULL DEFAULT 'inside_ap'",
        ],
        'customers' => ['deleted_at' => 'DATETIME NULL DEFAULT NULL', 'customer_category' => "ENUM('CB1','CB2') NOT NULL DEFAULT 'CB1'"],
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
