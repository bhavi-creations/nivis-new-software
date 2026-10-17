<?php

header('Content-Type: application/json; charset=utf-8');

function respond(array $payload, int $status = 200): void
{
    if ($status < 300 && isset($GLOBALS['request_cache_key']) && session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['requests'][$GLOBALS['request_cache_key']] = ['hash'=>$GLOBALS['request_hash'],'payload'=>$payload,'status'=>$status];
        if (count($_SESSION['requests']) > 100) array_shift($_SESSION['requests']);
    }
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function request_data(): array
{
    if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') === 0) return $_POST;
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return $_POST;
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        respond(['error' => 'Invalid JSON request body.'], 400);
    }
    return $data;
}

function required_text(array $data, string $key, int $maxLength): string
{
    $value = trim((string) ($data[$key] ?? ''));
    $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    if ($value === '' || $length > $maxLength) {
        respond(['error' => 'Enter a valid ' . str_replace('_', ' ', $key) . '.'], 422);
    }
    return $value;
}

function valid_status(array $data, string $key): string
{
    $value = strtolower(trim((string) ($data[$key] ?? 'active')));
    if (!in_array($value, ['active', 'inactive'], true)) {
        respond(['error' => 'Status must be active or inactive.'], 422);
    }
    return $value;
}

function state_code(string $state): string
{
    $codes = [
        'Andhra Pradesh' => '37', 'Telangana' => '36', 'Karnataka' => '29',
        'Tamil Nadu' => '33', 'Maharashtra' => '27', 'Kerala' => '32',
        'Odisha' => '21', 'Delhi' => '07',
    ];
    return $codes[$state] ?? '';
}

try {
    foreach (['errors.php', 'database.php', 'schema.php', 'product_images.php'] as $requiredFile) {
        if (!is_file(__DIR__ . '/config/' . $requiredFile)) {
            respond(['error' => 'Application file missing: config/' . $requiredFile . '. Upload the complete updated config folder.', 'code' => 'SETUP_MISSING_FILE'], 500);
        }
    }
    require_once __DIR__ . '/config/database.php';
    require_once __DIR__ . '/config/schema.php';
    require_once __DIR__ . '/config/product_images.php';
    $pdo = database_connection();
    require_once __DIR__.'/config/security.php';
    require_once __DIR__.'/config/billing.php';
    $write = !in_array($_SERVER['REQUEST_METHOD'], ['GET','HEAD'], true);
    if ($denied = nivis_access($pdo, $write)) respond($denied, http_response_code());
    if (PHP_SAPI !== 'cli' && $write && !hash_equals($_SESSION['csrf'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) respond(['error'=>'Security token expired. Refresh the page and retry.'],403);
    $resource = (string) ($_GET['resource'] ?? '');
    if (in_array($resource, ['products', 'categories'], true)) {
        ensure_billing_columns($pdo, $resource);
    }
    if (in_array($resource, ['customers', 'quotations', 'invoices', 'dashboard'], true)) ensure_billing_columns($pdo, 'customers');
    if (in_array($resource, ['products', 'tax', 'tax_rates'], true)) ensure_billing_columns($pdo, 'tax_rates');
    if ($resource === 'tax') ensure_billing_columns($pdo, 'gst_configuration');
    if ($resource === 'products') ensure_billing_columns($pdo, 'customers');
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'POST') {
        $identityTables = [
            'products' => ['products'],
            'categories' => ['categories'],
            'customers' => ['customers', 'customer_addresses'],
        ];
        if (isset($identityTables[$resource])) {
            ensure_identity_columns($pdo, $identityTables[$resource]);
        }
    }
    $data = request_data();
    if (in_array($resource,['quotations','invoices','settings','dashboard','reports'],true)) { ensure_document_columns($pdo); ensure_billing_columns($pdo,'tax_rates'); }
    if ($resource === 'security' && $method === 'GET') respond(['csrf'=>$_SESSION['csrf'] ?? '']);
    if (PHP_SAPI !== 'cli' && $write && !empty($_SERVER['HTTP_IDEMPOTENCY_KEY'])) {
        $key = $_SERVER['HTTP_IDEMPOTENCY_KEY'];
        if (!preg_match('/^[a-zA-Z0-9-]{16,80}$/',$key)) respond(['error'=>'Invalid request key.'],422);
        $request_cache_key = $resource.':'.$key;
        $request_hash = hash('sha256', $method.json_encode($_GET).json_encode($data));
        $cached = $_SESSION['requests'][$request_cache_key] ?? null;
        if ($cached) {
            if ($cached['hash'] !== $request_hash) respond(['error'=>'Request key already used for different values.'],409);
            respond($cached['payload'],$cached['status']);
        }
    }
    require_once __DIR__.'/config/inventory.php';
    billing_actions($pdo,$resource,$method,$data);
    $id = filter_var($_GET['id'] ?? $data['id'] ?? null, FILTER_VALIDATE_INT);

    if ($resource === 'settings' && $method === 'GET') {
        respond(['data' => $pdo->query('SELECT * FROM company_settings WHERE id=1')->fetch() ?: null]);
    }
    if ($resource === 'settings' && $method === 'POST') {
        $name = required_text($data, 'company_name', 180);
        $address = required_text($data, 'address', 5000);
        $state = required_text($data, 'state_name', 100);
        $code = state_code($state);
        $email = trim((string) ($data['email'] ?? ''));
        $gstin = strtoupper(trim((string) ($data['gstin'] ?? '')));
        $phone = trim((string) ($data['phone'] ?? ''));
        if ($code === '' || strlen($phone) > 20 || strlen($email) > 190 ||
            ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) ||
            ($gstin !== '' && (!preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $gstin) || substr($gstin, 0, 2) !== $code))) {
            respond(['error' => 'Check the company state, GSTIN, email and phone.'], 422);
        }
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO company_settings (id, company_name, address, state_name, state_code, gstin, email, phone)
            VALUES (1, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE
            company_name=VALUES(company_name), address=VALUES(address), state_name=VALUES(state_name),
            state_code=VALUES(state_code), gstin=VALUES(gstin), email=VALUES(email), phone=VALUES(phone)')
            ->execute([$name, $address, $state, $code, $gstin ?: null, $email ?: null, $phone ?: null]);
        $pdo->prepare('UPDATE gst_configuration SET seller_state_name=?, seller_state_code=? WHERE id=1')->execute([$state, $code]);
        $pdo->prepare('UPDATE company_settings SET document_defaults=? WHERE id=1')->execute([json_encode(billing_details($data))]);
        $pdo->commit();
        respond(['message' => 'Company settings saved.']);
    }

    if ($resource === 'product_sales' && $method === 'GET') {
        if (!$id) respond(['error'=>'A product ID is required.'],422);
        $pending=$pdo->prepare("SELECT i.id AS invoice_id,i.invoice_number,i.buyer_name AS customer,i.issue_date,ii.quantity,ii.unit FROM invoice_items ii JOIN invoices i ON i.id=ii.invoice_id WHERE ii.product_id=? AND i.status='draft' ORDER BY i.id DESC");
        $pending->execute([$id]);
        respond(['data'=>inventory_sales($pdo,(int)$id),'pending'=>$pending->fetchAll()]);
    }
    if ($resource === 'products' && $method === 'GET') {
        $rows = $pdo->query(
            "SELECT p.id, p.tax_rate_id, p.customer_id, p.image_path, p.default_quantity AS quantity, p.gst_region, p.name, p.sku, p.category_id, c.name AS category,
                    p.hsn_code AS hsn, p.unit, p.selling_price AS price,
                    COALESCE(t.rate_percent, 0) AS gst,
                    p.default_discount_percent AS discount, p.status, p.description
             FROM products p
             JOIN categories c ON c.id = p.category_id
             LEFT JOIN tax_rates t ON t.id = p.tax_rate_id
             ORDER BY p.id"
        )->fetchAll();
        foreach($rows as &$row) $row += inventory_balance($pdo,$row);
        unset($row);
        respond(['data' => $rows]);
    }

    if ($resource === 'products' && $method === 'POST') {
        $name = required_text($data, 'name', 180);
        $sku = trim((string) ($data['sku'] ?? ''));
        if ($sku === '') $sku = 'PRD-' . strtoupper(bin2hex(random_bytes(6)));
        if (strlen($sku) > 70) respond(['error' => 'Product code is too long.'], 422);
        $customerId = null;
        if (array_key_exists('customer_id', $data) && $data['customer_id'] !== '') {
            $customerId = filter_var($data['customer_id'], FILTER_VALIDATE_INT);
            $customerCheck = $pdo->prepare('SELECT id FROM customers WHERE id=? AND deleted_at IS NULL');
            $customerCheck->execute([$customerId]);
            if (!$customerId || !$customerCheck->fetchColumn()) respond(['error' => 'Choose an existing customer.'], 422);
        }
        $quantity = filter_var($data['quantity'] ?? 1, FILTER_VALIDATE_FLOAT);
        $region = (string) ($data['gst_region'] ?? 'inside_ap');
        if ($quantity === false || $quantity < 0 || $quantity > 1000000 || !in_array($region, ['inside_ap', 'outside_ap'], true)) {
            respond(['error' => 'Enter a quantity from 0 to 1000000 and choose a GST region.'], 422);
        }
        $categoryId = filter_var($data['category_id'] ?? null, FILTER_VALIDATE_INT);
        $hsn = required_text($data, 'hsn', 8);
        $unit = required_text($data, 'unit', 30);
        $price = filter_var($data['price'] ?? null, FILTER_VALIDATE_FLOAT);
        $gst = filter_var($data['gst'] ?? null, FILTER_VALIDATE_FLOAT);
        $discount = filter_var($data['discount'] ?? 0, FILTER_VALIDATE_FLOAT);
        $status = valid_status($data, 'status');
        if (!$categoryId || !preg_match('/^[0-9]{4,8}$/', $hsn) || $price === false ||
            $price < 0 || $gst === false || $gst < 0 || $gst > 100 ||
            $discount === false || $discount < 0 || $discount > 100) {
            respond(['error' => 'Check the product category, HSN, price, tax and discount values.'], 422);
        }
        $rate = $pdo->prepare(
            "SELECT id FROM tax_rates WHERE rate_percent = ? AND (? IS NULL OR id=?) AND deleted_at IS NULL AND is_enabled=1 AND effective_from <= CURRENT_DATE() AND (effective_to IS NULL OR effective_to >= CURRENT_DATE())
             ORDER BY is_enabled DESC, id LIMIT 1"
        );
        $selectedRate = !empty($data['tax_rate_id']) ? filter_var($data['tax_rate_id'], FILTER_VALIDATE_INT) : null;
        $rate->execute([$gst, $selectedRate, $selectedRate]);
        $taxRateId = $rate->fetchColumn();
        if ($taxRateId === false) {
            respond(['error' => 'Choose a GST rate configured in Tax & GST settings.'], 422);
        }
        $productId = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        if (isset($data['id']) && !$productId) {
            respond(['error' => 'Invalid product ID.'], 422);
        }
        $oldImage = null;
        $pdo->beginTransaction();
        if ($productId) {
            $existing = $pdo->prepare('SELECT * FROM products WHERE id=? FOR UPDATE');
            $existing->execute([$productId]);
            $record = $existing->fetch();
            if (!$record) respond(['error' => 'Product not found.'], 404);
            $balance=inventory_balance($pdo,$record);
            if (strtoupper($unit)!==strtoupper($record['unit']) && inventory_sales($pdo,(int)$productId,true)) { $pdo->rollBack();respond(['error'=>'A product on saved invoices must keep its stock unit.'],422); }
            if ($quantity < $balance['sold_quantity']) {$pdo->rollBack();respond(['error'=>'Total quantity cannot be less than the quantity already sold.'],422);}
            $oldImage = $record['image_path'];
        }
        $newProductImage = store_product_image();
        $imagePath = $newProductImage ?? $oldImage;
        if ($productId) {
            $statement = $pdo->prepare(
                'UPDATE products SET customer_id=IF(?, ?, customer_id), image_path=?, default_quantity=?, gst_region=?, name=?, sku=?, category_id=?, hsn_code=?, unit=?,
                 selling_price=?, default_discount_percent=?, tax_rate_id=?, description=?, status=?
                 WHERE id=?'
            );
            $statement->execute([
                array_key_exists('customer_id', $data) ? 1 : 0, $customerId, $imagePath, $quantity, $region, $name, $sku, $categoryId, $hsn, $unit, $price, $discount,
                $taxRateId ?: null, trim((string) ($data['description'] ?? '')),
                $status, $productId,
            ]);
            if ($statement->rowCount() === 0 &&
                !(int) $pdo->query('SELECT COUNT(*) FROM products WHERE id=' . $productId)->fetchColumn()) {
                respond(['error' => 'Product not found.'], 404);
            }
            if ($newProductImage) remove_product_image($oldImage);
            $newProductImage = null;
            $pdo->commit();
            respond(['message' => 'Product saved.', 'id' => $productId]);
        }
        $statement = $pdo->prepare(
            'INSERT INTO products
             (customer_id, image_path, default_quantity, gst_region, name, sku, category_id, hsn_code, unit, selling_price, default_discount_percent,
              tax_rate_id, description, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $customerId, $imagePath, $quantity, $region, $name, $sku, $categoryId, $hsn, $unit, $price, $discount,
            $taxRateId ?: null, trim((string) ($data['description'] ?? '')), $status,
        ]);
        $newProductImage = null;
        $newId=(int)$pdo->lastInsertId();$pdo->commit();
        respond(['message' => 'Product saved.', 'id' => $newId], 201);
    }

    if ($resource === 'products' && $method === 'DELETE') {
        if (!$id) {
            respond(['error' => 'A valid product ID is required.'], 422);
        }
        $pdo->beginTransaction();
        $imageQuery = $pdo->prepare('SELECT image_path FROM products WHERE id=? FOR UPDATE');
        $imageQuery->execute([$id]);
        $deletedImage = $imageQuery->fetchColumn();
        if (inventory_sales($pdo,(int)$id,true)) {$pdo->rollBack();respond(['error'=>'This product has saved invoices. Mark it inactive instead of deleting it.'],409);}
        $statement = $pdo->prepare('DELETE FROM products WHERE id = ?');
        $statement->execute([$id]);
        if ($statement->rowCount() === 0) {
            respond(['error' => 'Product not found.'], 404);
        }
        $pdo->commit();
        remove_product_image($deletedImage ?: null);
        respond(['message' => 'Product deleted.']);
    }

    if ($resource === 'categories' && $method === 'GET') {
        $rows = $pdo->query(
            'SELECT c.id, c.name, c.code, c.description, c.status, c.product_count,
                    COUNT(p.id) AS linked_products, COALESCE(c.product_count, COUNT(p.id)) AS products
             FROM categories c LEFT JOIN products p ON p.category_id=c.id
             GROUP BY c.id, c.name, c.code, c.description, c.status, c.product_count ORDER BY c.id'
        )->fetchAll();
        respond(['data' => $rows]);
    }

    if ($resource === 'categories' && $method === 'POST') {
        $categoryId = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        $productCount = $data['product_count'] ?? null;
        if ($productCount === '') $productCount = null;
        if ($productCount !== null) {
            $productCount = filter_var($productCount, FILTER_VALIDATE_INT);
            if ($productCount === false || $productCount < 0 || $productCount > 1000000) respond(['error' => 'Product count must be a whole number from 0 to 1000000.'], 422);
        }
        $name = required_text($data, 'name', 120);
        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        if ($code === '') $code = 'CAT-' . strtoupper(bin2hex(random_bytes(6)));
        if (strlen($code) > 30) respond(['error' => 'Category code is too long.'], 422);
        $description = trim((string) ($data['description'] ?? ''));
        $status = valid_status($data, 'status');
        if ($categoryId) {
            $statement = $pdo->prepare(
                'UPDATE categories SET name=?, code=?, description=?, status=?, product_count=? WHERE id=?'
            );
            $statement->execute([$name, $code, $description, $status, $productCount, $categoryId]);
            respond(['message' => 'Category saved.', 'id' => $categoryId]);
        }
        $statement = $pdo->prepare(
            'INSERT INTO categories (name, code, description, status, product_count) VALUES (?, ?, ?, ?, ?)'
        );
        $statement->execute([$name, $code, $description, $status, $productCount]);
        respond(['message' => 'Category saved.', 'id' => (int) $pdo->lastInsertId()], 201);
    }

    if ($resource === 'categories' && $method === 'DELETE') {
        if (!$id) {
            respond(['error' => 'A valid category ID is required.'], 422);
        }
        $statement = $pdo->prepare('DELETE FROM categories WHERE id=?');
        $statement->execute([$id]);
        if ($statement->rowCount() === 0) {
            respond(['error' => 'Category not found.'], 404);
        }
        respond(['message' => 'Category deleted.']);
    }

    if ($resource === 'customers' && $method === 'GET') {
        $rows = $pdo->query(
            "SELECT c.id, c.name, c.mobile, c.email, c.gstin, c.customer_type,
                    c.gst_registration, c.status, c.customer_category,
                    MAX(CASE WHEN a.address_type='billing' THEN a.address_line1 END) AS billing_address,
                    MAX(CASE WHEN a.address_type='billing' THEN a.city END) AS city,
                    MAX(CASE WHEN a.address_type='billing' THEN a.state_name END) AS state,
                    MAX(CASE WHEN a.address_type='billing' THEN a.pincode END) AS pincode,
                    MAX(CASE WHEN a.address_type='shipping' THEN a.address_line1 END) AS shipping_address,
                    MAX(CASE WHEN a.address_type='shipping' THEN a.city END) AS shipping_city,
                    MAX(CASE WHEN a.address_type='shipping' THEN a.state_name END) AS shipping_state,
                    MAX(CASE WHEN a.address_type='shipping' THEN a.pincode END) AS shipping_pincode
             FROM customers c LEFT JOIN customer_addresses a ON a.customer_id=c.id
             WHERE c.deleted_at IS NULL
             GROUP BY c.id, c.name, c.mobile, c.email, c.gstin, c.customer_type, c.gst_registration, c.status, c.customer_category ORDER BY c.id"
        )->fetchAll();
        respond(['data' => $rows]);
    }

    if ($resource === 'customers' && $method === 'POST') {
        $customerId = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        $name = required_text($data, 'name', 180);
        $mobile = required_text($data, 'mobile', 20);
        $type = (string) ($data['customer_type'] ?? 'unregistered');
        if ($type === 'registered') {
            $type = 'business';
        }
        if (!in_array($type, ['business', 'individual', 'unregistered'], true)) {
            respond(['error' => 'Choose a valid customer type.'], 422);
        }
        $category = (string) ($data['customer_category'] ?? 'CB1');
        if (!in_array($category, ['CB1', 'CB2'], true)) respond(['error' => 'Choose a valid customer category.'], 422);
        $registration = strtolower((string) ($data['gst_registration'] ?? 'unregistered'));
        if (!in_array($registration, ['registered', 'unregistered', 'composition', 'other'], true)) {
            $registration = 'unregistered';
        }
        $gstin = strtoupper(trim((string) ($data['gstin'] ?? '')));
        if ($registration === 'registered' &&
            !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $gstin)) {
            respond(['error' => 'Enter a valid GSTIN for a registered customer.'], 422);
        }
        if ($registration !== 'registered') {
            $gstin = '';
        }
        $email = trim((string) ($data['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            respond(['error' => 'Enter a valid email address.'], 422);
        }
        $state = trim((string) ($data['state'] ?? ''));
        $code = state_code($state);
        if ($code === '') {
            respond(['error' => 'Choose a supported state.'], 422);
        }
        if ($registration === 'registered' && substr($gstin, 0, 2) !== $code) {
            respond(['error' => 'The GSTIN state code must match the selected state.'], 422);
        }
        $status = valid_status($data, 'status');

        $pdo->beginTransaction();
        if ($customerId) {
            $statement = $pdo->prepare(
                'UPDATE customers SET name=?, mobile=?, email=?, customer_type=?,
                 gst_registration=?, gstin=?, status=? WHERE id=? AND deleted_at IS NULL'
            );
            $statement->execute([
                $name, $mobile, $email ?: null, $type, $registration, $gstin ?: null,
                $status, $customerId,
            ]);
            if ($statement->rowCount() === 0 &&
                !(int) $pdo->query('SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL AND id=' . $customerId)->fetchColumn()) {
                $pdo->rollBack();
                respond(['error' => 'Customer not found.'], 404);
            }
        } else {
            $statement = $pdo->prepare(
                'INSERT INTO customers (customer_code, name, mobile, email, customer_type, gst_registration, gstin, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $statement->execute([
                'CUS-' . strtoupper(bin2hex(random_bytes(8))),
                $name, $mobile, $email ?: null, $type, $registration, $gstin ?: null, $status,
            ]);
            $customerId = (int) $pdo->lastInsertId();
        }

        $pdo->prepare('UPDATE customers SET customer_category=? WHERE id=?')->execute([$category, $customerId]);
        if (!empty($data['same_as_billing'])) {
            foreach (['address' => 'billing_address', 'city' => 'city', 'state' => 'state', 'pincode' => 'pincode'] as $key => $source) $data['shipping_' . $key] = $data[$source] ?? '';
        }
        $pdo->prepare('DELETE FROM customer_addresses WHERE customer_id=?')->execute([$customerId]);
        $billing = trim((string) ($data['billing_address'] ?? ''));
        $shipping = trim((string) ($data['shipping_address'] ?? ''));
        if ($billing === '' && $shipping !== '') {
            $pdo->rollBack();
            respond(['error' => 'Enter a billing address before adding a shipping address.'], 422);
        }
        if ($billing !== '') {
            $address = $pdo->prepare(
                'INSERT INTO customer_addresses
                 (customer_id, address_type, address_line1, city, state_name, state_code, pincode, country, is_default)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $city = trim((string) ($data['city'] ?? ''));
            $pincode = trim((string) ($data['pincode'] ?? ''));
            if ($city === '' || !preg_match('/^[0-9]{6}$/', $pincode)) {
                $pdo->rollBack();
                respond(['error' => 'Billing address requires a city and six-digit pincode.'], 422);
            }
            $address->execute([$customerId, 'billing', $billing, $city, $state, $code, $pincode, 'India', 1]);
            if ($shipping !== '') {
                $shippingState = trim((string) ($data['shipping_state'] ?? $state));
                $shippingCode = state_code($shippingState);
                $shippingPin = trim((string) ($data['shipping_pincode'] ?? $pincode));
                if ($shippingCode === '' || !preg_match('/^[0-9]{6}$/', $shippingPin)) {
                    $pdo->rollBack();
                    respond(['error' => 'Shipping address requires a supported state and six-digit pincode.'], 422);
                }
                $address->execute([
                    $customerId, 'shipping', $shipping,
                    trim((string) ($data['shipping_city'] ?? $city)),
                    $shippingState, $shippingCode, $shippingPin, 'India', 1,
                ]);
            }
        }
        $pdo->commit();
        respond(['message' => 'Customer saved.', 'id' => $customerId], 201);
    }

    if ($resource === 'customers' && $method === 'DELETE') {
        if (!$id) {
            respond(['error' => 'A valid customer ID is required.'], 422);
        }
        $statement = $pdo->prepare("UPDATE customers SET deleted_at=CURRENT_TIMESTAMP, status='inactive' WHERE id=? AND deleted_at IS NULL");
        $statement->execute([$id]);
        if ($statement->rowCount() === 0) {
            respond(['error' => 'Customer not found.'], 404);
        }
        respond(['message' => 'Customer removed from the directory. Existing billing history is preserved.']);
    }

    if ($resource === 'tax' && $method === 'GET') {
        $config = $pdo->query('SELECT * FROM gst_configuration WHERE id=1')->fetch();
        $rates = $pdo->query(
            'SELECT id, name, rate_percent, treatment, effective_from, effective_to, is_enabled, (SELECT GROUP_CONCAT(hsn_code) FROM hsn_tax_mappings h WHERE h.tax_rate_id=tax_rates.id) AS hsn_codes
             FROM tax_rates WHERE deleted_at IS NULL ORDER BY rate_percent, id'
        )->fetchAll();
        respond(['configuration' => $config, 'rates' => $rates]);
    }

    if ($resource === 'tax_rates' && $method === 'DELETE') {
        if (!$id) respond(['error' => 'Choose a valid GST rate.'], 422);
        $statement = $pdo->prepare('UPDATE tax_rates SET deleted_at=CURRENT_TIMESTAMP, is_enabled=0 WHERE id=? AND deleted_at IS NULL');
        $statement->execute([$id]);
        if (!$statement->rowCount()) respond(['error' => 'GST rate not found.'], 404);
        respond(['message' => 'GST rate deleted.']);
    }
    if ($resource === 'tax_rates' && $method === 'POST') {
        if (($data['action'] ?? '') === 'toggle') {
            $q=$pdo->prepare('UPDATE tax_rates SET is_enabled=? WHERE id=? AND deleted_at IS NULL');
            $q->execute([!empty($data['is_enabled']) ? 1 : 0, $id]);
            respond(['message'=>'Tax rate status updated.']);
        }
        $name = required_text($data, 'name', 100);
        $rate = filter_var($data['rate_percent'] ?? null, FILTER_VALIDATE_FLOAT);
        $effective = (string) ($data['effective_from'] ?? '');
        $date = DateTime::createFromFormat('!Y-m-d', $effective);
        $treatment = (string) ($data['treatment'] ?? 'taxable');
        if ($rate === false || $rate < 0 || $rate > 100 || !$date || $date->format('Y-m-d') !== $effective ||
            !in_array($treatment, ['taxable','zero_rated','nil_rated','exempt','non_gst'], true) || ($treatment !== 'taxable' && $rate != 0)) {
            respond(['error' => 'Enter a valid rate, treatment and effective date.'], 422);
        }
        $hsnCodes = array_values(array_unique(array_filter(array_map('trim',explode(',',(string)($data['hsn_codes'] ?? ''))))));
        foreach ($hsnCodes as $hsnCode) if (!preg_match('/^[0-9]{4,8}$/',$hsnCode)) respond(['error'=>'HSN/SAC codes must contain 4 to 8 digits.'],422);
        $enabled = !array_key_exists('is_enabled',$data) || !empty($data['is_enabled']);
        $pdo->beginTransaction();
        if (!empty($data['id'])) {
            $rateId = filter_var($data['id'], FILTER_VALIDATE_INT);
            if (!$rateId) respond(['error' => 'Invalid rate ID.'], 422);
            $check=$pdo->prepare('SELECT id FROM tax_rates WHERE id=? AND deleted_at IS NULL FOR UPDATE');$check->execute([$rateId]);
            if (!$check->fetchColumn()) {$pdo->rollBack();respond(['error'=>'Tax rate not found.'],404);}
            $statement = $pdo->prepare('UPDATE tax_rates SET name=?, rate_percent=?, treatment=?, effective_from=? WHERE id=? AND deleted_at IS NULL');
            $statement->execute([$name, $rate, $treatment, $effective, $rateId]);
            $pdo->prepare('UPDATE tax_rates SET is_enabled=? WHERE id=? AND deleted_at IS NULL')->execute([(int)$enabled,$rateId]);
            $pdo->prepare('DELETE FROM hsn_tax_mappings WHERE tax_rate_id=?')->execute([$rateId]);
            foreach ($hsnCodes as $hsnCode) $pdo->prepare('INSERT INTO hsn_tax_mappings(hsn_code,tax_rate_id,effective_from) VALUES(?,?,?)')->execute([$hsnCode,$rateId,$effective]);
            $pdo->commit();
            respond(['message' => 'GST rate updated.', 'id' => $rateId]);
        }
        $statement = $pdo->prepare('INSERT INTO tax_rates (name, rate_percent, treatment, effective_from, is_enabled) VALUES (?, ?, ?, ?, 1)');
        $statement->execute([$name, $rate, $treatment, $effective]);
        $rateId=(int)$pdo->lastInsertId();
        $pdo->prepare('UPDATE tax_rates SET is_enabled=? WHERE id=?')->execute([(int)$enabled,$rateId]);
        foreach ($hsnCodes as $hsnCode) $pdo->prepare('INSERT INTO hsn_tax_mappings(hsn_code,tax_rate_id,effective_from) VALUES(?,?,?)')->execute([$hsnCode,$rateId,$effective]);
        $pdo->commit();
        respond(['message' => 'GST rate saved.', 'id' => $rateId], 201);
    }

    if ($resource === 'tax' && $method === 'POST') {
        $state = trim((string) ($data['seller_state_name'] ?? ''));
        $code = state_code($state);
        $region = (string) ($data['default_region'] ?? 'inside_ap');
        if (!in_array($region, ['inside_ap', 'outside_ap'], true)) respond(['error' => 'Choose Inside AP or Outside AP.'], 422);
        $taxMode = (string) ($data['tax_mode'] ?? 'auto');
        $priceMode = (string) ($data['price_mode'] ?? 'exclusive');
        if ($code === '' || !in_array($taxMode, ['auto', 'manual_review'], true) ||
            !in_array($priceMode, ['exclusive', 'inclusive'], true)) {
            respond(['error' => 'Choose valid tax configuration values.'], 422);
        }
        $statement = $pdo->prepare(
            'UPDATE gst_configuration SET seller_state_name=?, seller_state_code=?,
             tax_mode=?, price_mode=?, default_region=? WHERE id=1'
        );
        $statement->execute([$state, $code, $taxMode, $priceMode, $region]);
        respond(['message' => 'Tax configuration saved.']);
    }

    if ($resource === 'document_number' && $method === 'GET') {
        $date = (string) ($_GET['date'] ?? date('Y-m-d'));
        $parsed = DateTime::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) respond(['error' => 'Invalid date.'], 422);
        $year = (int) substr($date, 0, 4) - ((int) substr($date, 5, 2) < 4 ? 1 : 0);
        $fy = sprintf('%d-%02d', $year, ($year + 1) % 100);
        $statement = $pdo->prepare("SELECT last_number FROM document_sequences WHERE document_type='quotation' AND financial_year=?");
        $statement->execute([$fy]);
        respond(['number' => sprintf('QT-%s-%06d', $fy, (int) $statement->fetchColumn() + 1)]);
    }
    if ($resource === 'quotations' && $method === 'GET') {
        $sql = "SELECT q.id, q.quote_number, q.customer_id, COALESCE(MAX(JSON_UNQUOTE(JSON_EXTRACT(q.party_snapshot, '$.buyer.name'))), c.name) AS customer,
                       q.issue_date, q.valid_until, q.notes, q.place_of_supply_state_code, q.status, q.grand_total,
                       i.id AS invoice_id, MAX(q.source_type) AS source_type, MAX(q.order_number) AS order_number,
                       MAX(q.advance_amount) AS advance_amount, MAX(q.advance_date) AS advance_date, MAX(q.advance_method) AS advance_method,
                       GROUP_CONCAT(qi.product_name ORDER BY qi.line_no SEPARATOR ' | ') AS product_names
                FROM quotations q
                JOIN customers c ON c.id=q.customer_id
                LEFT JOIN invoices i ON i.quotation_id=q.id
                LEFT JOIN quotation_items qi ON qi.quotation_id=q.id";
        if ($id) {
            $statement = $pdo->prepare($sql . ' WHERE q.id=? AND q.deleted_at IS NULL GROUP BY q.id, q.quote_number, q.customer_id, c.name, q.issue_date, q.valid_until, q.notes, q.place_of_supply_state_code, q.status, q.grand_total, i.id');
            $statement->execute([$id]);
            $rows = $statement->fetchAll();
            if (!$rows) {
                respond(['error' => 'Quotation not found.'], 404);
            }
            $itemStatement = $pdo->prepare(
                'SELECT line_no, product_id, product_name, sku, hsn_code AS hsn, unit,
                        quantity, unit_price, discount_percent, gst_rate,
                        taxable_amount, line_total
                 FROM quotation_items WHERE quotation_id=? ORDER BY line_no'
            );
            $itemStatement->execute([$id]);
            $rows[0]['items'] = $itemStatement->fetchAll();
            $full=$pdo->prepare('SELECT * FROM quotations WHERE id=?'); $full->execute([$id]);
            $rows[0]=array_merge($rows[0],$full->fetch());
            foreach ($rows[0]['items'] as &$line) {
                $line['reference'] = $rows[0]['quote_number'] . '-P' . $line['product_id'] . '-L' . $line['line_no'];
            }
            unset($line);
            respond(['data' => $rows[0]]);
        }
        $rows = $pdo->query($sql . ' WHERE q.deleted_at IS NULL GROUP BY q.id, q.quote_number, q.customer_id, c.name, q.issue_date, q.valid_until, q.notes, q.place_of_supply_state_code, q.status, q.grand_total, i.id ORDER BY q.id DESC')->fetchAll();
        foreach ($rows as &$row) {
            $row['products'] = $row['product_names'] === null
                ? [] : explode(' | ', $row['product_names']);
            unset($row['product_names']);
        }
        unset($row);
        respond(['data' => $rows]);
    }

    $directInvoice = $resource === 'invoices' && $method === 'POST' && in_array(($data['action'] ?? ''), ['create','revise'], true);
    if (($resource === 'quotations' && $method === 'POST') || $directInvoice) {
        if ($directInvoice) { $data['valid_until'] = ($data['due_date'] ?? '') ?: ($data['issue_date'] ?? ''); $data['status'] = 'draft'; unset($data['id']); }
        $sourceType=(string)($data['source_type'] ?? 'quotation');
        if (!in_array($sourceType,['quotation','order'],true)) respond(['error'=>'Invalid order source.'],422);
        $quoteId = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        $customerId = filter_var($data['customer_id'] ?? null, FILTER_VALIDATE_INT);
        $issueDate = (string) ($data['issue_date'] ?? '');
        $validUntil = (string) ($data['valid_until'] ?? '');
        $notes = trim((string) ($data['notes'] ?? ''));
        $details=billing_details($data);
        if (strlen($notes)>5000) respond(['error'=>'Notes must be at most 5000 characters.'],422);
        if (($data['supply_type'] ?? 'domestic_goods') !== 'domestic_goods') respond(['error'=>'Special supplies require a reviewed tax workflow.'],422);
        $requestedStatus = strtolower((string) ($data['status'] ?? 'draft'));
        $items = $data['items'] ?? null;
        $isDate = static function (string $value): bool {
            $date = DateTime::createFromFormat('!Y-m-d', $value);
            return $date !== false && $date->format('Y-m-d') === $value;
        };
        if (!$customerId || !$isDate($issueDate) || !$isDate($validUntil) ||
            $validUntil < $issueDate || !is_array($items) || count($items) === 0 ||
            count($items) > 200 || !in_array($requestedStatus, ['draft', 'sent', 'approved'], true)) {
            respond(['error' => 'Provide a customer, valid dates, a supported status and at least one valid product line.'], 422);
        }

        if (in_array($requestedStatus,['sent','approved'],true) && $validUntil < date('Y-m-d')) respond(['error'=>'Cannot send or accept an expired quotation. Save a draft with valid dates.'],422);
        $pdo->beginTransaction();
        $customerStatement = $pdo->prepare(
            "SELECT c.name, c.gstin, a.state_code
             FROM customers c LEFT JOIN customer_addresses a
               ON a.customer_id=c.id AND a.address_type='billing'
             WHERE c.id=? AND c.deleted_at IS NULL LIMIT 1"
        );
        $customerStatement->execute([$customerId]);
        $customer = $customerStatement->fetch();
        if (!$customer) {
            $pdo->rollBack();
            respond(['error' => 'Customer not found.'], 404);
        }
        $configuration = $pdo->query(
            'SELECT seller_state_code, price_mode, tax_mode FROM gst_configuration WHERE id=1'
        )->fetch();
        $company=$pdo->query('SELECT * FROM company_settings WHERE id=1')->fetch();
        if (!$company && $directInvoice) { $pdo->rollBack(); respond(['error'=>'Configure your actual business details in Settings before creating a direct invoice.'],422); }
        if (($configuration['tax_mode'] ?? 'auto') === 'manual_review') { $pdo->rollBack(); respond(['error'=>'Tax configuration requires review before billing.'],422); }
        $sellerCode = $company['state_code'] ?? ($configuration['seller_state_code'] ?? '37');
        $parties=billing_parties($pdo,$customerId);
        if (array_key_exists('customer_gstin',$data)) {
            $buyerGstin=strtoupper(trim((string)$data['customer_gstin']));
            if ($buyerGstin!=='' && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/',$buyerGstin)) {$pdo->rollBack();respond(['error'=>'Enter a valid 15-character customer GST number or leave it blank.'],422);}
            $customer['gstin']=$buyerGstin ?: null;$parties['buyer']['gstin']=$buyerGstin ?: null;
        }
        $shippingCode=$pdo->prepare("SELECT state_code FROM customer_addresses WHERE customer_id=? AND address_type='shipping' ORDER BY is_default DESC,id DESC LIMIT 1");
        $shippingCode->execute([$customerId]);
        $customer['state_code']=$shippingCode->fetchColumn() ?: $customer['state_code'];
        $placeCode = (string) ($data['place_of_supply_state_code'] ?? ($customer['state_code'] ?: $sellerCode));
        if (!in_array($placeCode, ['37','36','29','33','27','32','21','07'], true)) {
            $pdo->rollBack();
            respond(['error' => 'Choose a valid place of supply.'], 422);
        }
        $sameState = $sellerCode === $placeCode;
        $taxMode = $sameState ? 'cgst_sgst' : 'igst';
        $priceMode = in_array($configuration['price_mode'] ?? '', ['exclusive', 'inclusive'], true)
            ? $configuration['price_mode'] : 'exclusive';
        $totals = [
            'subtotal' => 0.0, 'discount_total' => 0.0, 'taxable_total' => 0.0,
            'cgst_total' => 0.0, 'sgst_total' => 0.0, 'igst_total' => 0.0,
            'grand_total' => 0.0,
        ];
        $preparedItems = [];
        $taxTreatments=[]; $itemDescriptions=[];
        $productStatement = $pdo->prepare(
            "SELECT p.id, p.name, p.sku, p.hsn_code, p.unit, p.selling_price, p.description, t.treatment,
                    COALESCE(t.rate_percent, 0) AS gst_rate, t.is_enabled, t.effective_from, t.effective_to
             FROM products p LEFT JOIN tax_rates t ON t.id=p.tax_rate_id
             WHERE p.id=? AND p.status='active'"
        );
        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                $pdo->rollBack();
                respond(['error' => 'Invalid quotation line item.'], 422);
            }
            $productId = filter_var($item['product_id'] ?? null, FILTER_VALIDATE_INT);
            $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_FLOAT);
            $unitPrice = filter_var($item['unit_price'] ?? null, FILTER_VALIDATE_FLOAT);
            $discountPercent = filter_var($item['discount_percent'] ?? 0, FILTER_VALIDATE_FLOAT);
            if (!$productId || $quantity === false || $quantity <= 0 || $quantity > 1000000 ||
                $unitPrice === false || $unitPrice < 0 || $unitPrice > 1000000000 ||
                $discountPercent === false || $discountPercent < 0 || $discountPercent > 100) {
                $pdo->rollBack();
                respond(['error' => 'Check product, quantity, price and discount values.'], 422);
            }
            $productStatement->execute([$productId]);
            $product = $productStatement->fetch();
            if (!$product) {
                $pdo->rollBack();
                respond(['error' => 'A selected product is missing or inactive.'], 422);
            }
            if (!$product['is_enabled'] || $product['effective_from'] > $issueDate || ($product['effective_to'] !== null && $product['effective_to'] < $issueDate)) {
                $pdo->rollBack();
                respond(['error' => 'A product GST rate is disabled or outside its effective dates. Update the product tax rate.'], 422);
            }
            $lineUnit=strtoupper(trim((string)($item['unit'] ?? $product['unit'])));
            if (!in_array($lineUnit,array_merge([strtoupper($product['unit'])],['PCS','BOX','KG','GRAM','LITER','ML','METER','SET']),true)) {$pdo->rollBack();respond(['error'=>'Choose a valid unit for each product.'],422);}
            $quantity=round($quantity,3); $unitPrice=round($unitPrice,2); $discountPercent=round($discountPercent,2);
            if ($quantity<=0) {$pdo->rollBack();respond(['error'=>'Quantity must be at least 0.001.'],422);}
            $taxTreatments[$index+1]=$product['treatment'];
            $itemDescriptions[$index+1]=$product['description'];
            $base = round($quantity * $unitPrice, 2);
            $discountAmount = round($base * $discountPercent / 100, 2);
            $gstRate = (float) $product['gst_rate'];
            $discountedPrice = round($base - $discountAmount, 2);
            if ($priceMode === 'inclusive') {
                $taxable = round($discountedPrice / (1 + $gstRate / 100), 2);
                $tax = round($discountedPrice - $taxable, 2);
            } else {
                $taxable = $discountedPrice;
                $tax = round($taxable * $gstRate / 100, 2);
            }
            $cgst = $sameState ? round($tax / 2, 2) : 0.0;
            $sgst = $sameState ? round($tax - $cgst, 2) : 0.0;
            $igst = $sameState ? 0.0 : $tax;
            $lineTotal = $priceMode === 'inclusive'
                ? $discountedPrice : round($taxable + $tax, 2);
            $preparedItems[] = [
                'line_no' => $index + 1,
                'product_id' => $product['id'],
                'name' => $product['name'],
                'sku' => $product['sku'],
                'hsn' => $product['hsn_code'],
                'unit' => $lineUnit,
                'quantity' => $quantity,
                'price' => $unitPrice,
                'discount_percent' => $discountPercent,
                'discount_amount' => $discountAmount,
                'taxable' => $taxable,
                'gst_rate' => $gstRate,
                'cgst' => $cgst,
                'sgst' => $sgst,
                'igst' => $igst,
                'line_total' => $lineTotal,
            ];
            $totals['subtotal'] += $base;
            $totals['discount_total'] += $discountAmount;
            $totals['taxable_total'] += $taxable;
            $totals['cgst_total'] += $cgst;
            $totals['sgst_total'] += $sgst;
            $totals['igst_total'] += $igst;
            $totals['grand_total'] += $lineTotal;
        }
        $charge=filter_var($data['additional_charges'] ?? 0,FILTER_VALIDATE_FLOAT);
        if ($charge===false || $charge<0 || $charge>1000000000) { $pdo->rollBack(); respond(['error'=>'Enter valid additional charges.'],422); }
        $charge=round($charge,2);
        if ($charge>0) {
            $chargeName=required_text($data,'charge_description',180);
            $q=$pdo->prepare('SELECT * FROM tax_rates WHERE id=? AND is_enabled=1 AND deleted_at IS NULL AND effective_from<=? AND (effective_to IS NULL OR effective_to>=?)');
            $q->execute([$data['charge_tax_rate_id'] ?? null,$issueDate,$issueDate]);$rate=$q->fetch();
            $chargeHsn=required_text($data,'charge_hsn',8);
            if (!$rate || !preg_match('/^[0-9]{4,8}$/',$chargeHsn)) {$pdo->rollBack();respond(['error'=>'Choose a configured rate and HSN/SAC for additional charges.'],422);}
            $taxable=$priceMode==='inclusive' ? round($charge/(1+$rate['rate_percent']/100),2) : $charge;
            $tax=$priceMode==='inclusive' ? round($charge-$taxable,2) : round($taxable*$rate['rate_percent']/100,2);
            $cgst=$sameState ? round($tax/2,2) : 0; $sgst=$sameState ? round($tax-$cgst,2) : 0; $igst=$sameState ? 0 : $tax;
            $taxTreatments[count($preparedItems)+1]=$rate['treatment'];
            $preparedItems[]=['line_no'=>count($preparedItems)+1,'product_id'=>null,'name'=>$chargeName,'sku'=>'','hsn'=>$chargeHsn,'unit'=>'SERVICE','quantity'=>1,'price'=>$charge,'discount_percent'=>0,'discount_amount'=>0,'taxable'=>$taxable,'gst_rate'=>$rate['rate_percent'],'cgst'=>$cgst,'sgst'=>$sgst,'igst'=>$igst,'line_total'=>round($taxable+$tax,2)];
            $totals['subtotal']+=$charge;$totals['taxable_total']+=$taxable;$totals['cgst_total']+=$cgst;$totals['sgst_total']+=$sgst;$totals['igst_total']+=$igst;$totals['grand_total']+=round($taxable+$tax,2);
        }
        $details['tax_treatments']=$taxTreatments;
        $details['item_descriptions']=$itemDescriptions;
        $details['charge_description']=$data['charge_description'] ?? '';
        $details['charge_hsn']=$data['charge_hsn'] ?? '';
        $details['charge_tax_rate_id']=$data['charge_tax_rate_id'] ?? '';
        foreach ($totals as $key => $value) {
            $totals[$key] = round($value, 2);
        }

        if ($directInvoice && ($data['action'] ?? '') === 'revise') {
            $invoiceId=filter_var($data['invoice_id'] ?? null,FILTER_VALIDATE_INT);
            $q=$pdo->prepare('SELECT * FROM invoices WHERE id=? FOR UPDATE');$q->execute([$invoiceId]);$old=$q->fetch();
            if (!$old || $old['status']!=='draft') {$pdo->rollBack();respond(['error'=>'Only draft invoice lines can be edited.'],409);}
            if ($old['issue_date']!==$issueDate) {$pdo->rollBack();respond(['error'=>'The numbered invoice date cannot be changed.'],422);}
            $receipts=invoice_payment_details($pdo,(int)$invoiceId);
            if ($receipts['payments']) {$pdo->rollBack();respond(['error'=>'This invoice has recorded payments. Its product lines and customer cannot be changed; edit the source order/quotation separately.'],409);}
            $bp=$parties['buyer'];$sp=$parties['supplier'];
            $pdo->prepare('UPDATE invoices SET customer_id=?,due_date=?,supplier_name=?,supplier_gstin=?,supplier_address=?,supplier_state_code=?,buyer_name=?,buyer_gstin=?,billing_address=?,shipping_address=?,place_of_supply_state_code=?,tax_mode=?,price_mode=?,subtotal=?,discount_total=?,taxable_total=?,cgst_total=?,sgst_total=?,igst_total=?,grand_total=?,notes=? WHERE id=?')
                ->execute([$customerId,$validUntil,$sp['company_name'],$sp['gstin'],$sp['address'],$sellerCode,$bp['name'],$bp['gstin'],$bp['billing_address'] ?? '',$bp['shipping_address'],$placeCode,$taxMode,$priceMode,$totals['subtotal'],$totals['discount_total'],$totals['taxable_total'],$totals['cgst_total'],$totals['sgst_total'],$totals['igst_total'],$totals['grand_total'],$notes,$invoiceId]);
            $pdo->prepare('DELETE FROM invoice_items WHERE invoice_id=?')->execute([$invoiceId]);
            $q=$pdo->prepare('INSERT INTO invoice_items(invoice_id,line_no,product_id,product_name,sku,hsn_code,unit,quantity,unit_price,discount_percent,discount_amount,taxable_amount,gst_rate,cgst_amount,sgst_amount,igst_amount,line_total) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            foreach($preparedItems as $item) $q->execute(array_merge([$invoiceId],array_values($item)));
            $oldDetails=json_decode($old['document_details']??'{}',true)?:[];
            if(isset($oldDetails['order_number']))$details['order_number']=$oldDetails['order_number'];
            billing_store($pdo,'invoices',$invoiceId,$parties,$details,$charge);
            $pdo->commit();respond(['message'=>'Draft invoice updated.','id'=>$invoiceId]);
        }
        if ($directInvoice) {
            $invoiceDate = $issueDate;
            $financialYearStart = (int) substr($invoiceDate, 0, 4);
            if ((int) substr($invoiceDate, 5, 2) < 4) {
                $financialYearStart--;
            }
            $invoiceFy = sprintf('%d-%02d', $financialYearStart, ($financialYearStart + 1) % 100);
            $pdo->prepare(
                "INSERT INTO document_sequences(document_type, financial_year, last_number)
                 VALUES('invoice', ?, 0)
                 ON DUPLICATE KEY UPDATE last_number=last_number"
            )->execute([$invoiceFy]);
            $lock = $pdo->prepare(
                "SELECT last_number FROM document_sequences
                 WHERE document_type='invoice' AND financial_year=? FOR UPDATE"
            );
            $lock->execute([$invoiceFy]);
            $invoiceSequence = (int) $lock->fetchColumn() + 1;
            $pdo->prepare(
                "UPDATE document_sequences SET last_number=?
                 WHERE document_type='invoice' AND financial_year=?"
            )->execute([$invoiceSequence, $invoiceFy]);
            $invoiceNumber = sprintf('I%s%06d', str_replace('-', '', substr($invoiceFy, 2)), $invoiceSequence);
            $company = $pdo->query('SELECT * FROM company_settings WHERE id=1')->fetch();
            $configuration = $pdo->query('SELECT seller_state_name, seller_state_code FROM gst_configuration WHERE id=1')->fetch();
            $sellerStateName = $configuration['seller_state_name'] ?? 'Andhra Pradesh';
            $sellerStateCode = $configuration['seller_state_code'] ?? '37';
            $addresses = $pdo->prepare('SELECT * FROM customer_addresses WHERE customer_id=?');
            $addresses->execute([$customerId]);
            $buyerAddress = ''; $shippingAddress = '';
            foreach ($addresses->fetchAll() as $address) {
                $text = implode(', ', array_filter([$address['address_line1'], $address['city'], $address['state_name'], $address['pincode']]));
                if ($address['address_type'] === 'billing') $buyerAddress = $text;
                if ($address['address_type'] === 'shipping') $shippingAddress = $text;
            }
            $invoiceInsert = $pdo->prepare(
                'INSERT INTO invoices
                 (invoice_number, quotation_id, customer_id, issue_date, due_date, status,
                  supplier_name, supplier_gstin, supplier_address, supplier_state_code,
                  buyer_name, buyer_gstin, billing_address, shipping_address,
                  place_of_supply_state_code, tax_mode, price_mode, subtotal, discount_total,
                  taxable_total, cgst_total, sgst_total, igst_total, round_off, grand_total, notes)
                 VALUES (?, ?, ?, ?, ?, "draft", ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?,
                         ?, ?, ?, ?, ?, ?, 0, ?, ?)'
            );
            $invoiceInsert->execute([
                $invoiceNumber, null, $customerId, $issueDate, ($data['due_date'] ?? null) ?: null,
                $company['company_name'] ?? 'Nivis Beauty Care',
                $company['gstin'] ?? null,
                $company['address'] ?? 'Business address not configured',
                $sellerCode,
                $customer['name'], $customer['gstin'],
                $buyerAddress ?: 'Billing address not provided',
                $placeCode, $taxMode, $priceMode,
                $totals['subtotal'], $totals['discount_total'], $totals['taxable_total'],
                $totals['cgst_total'], $totals['sgst_total'], $totals['igst_total'],
                $totals['grand_total'], $notes,
            ]);
            $invoiceId = (int) $pdo->lastInsertId();
            $insertInvoiceItem = $pdo->prepare(
                'INSERT INTO invoice_items
                 (invoice_id, line_no, source_quotation_item_id, product_id, product_name, sku,
                  hsn_code, unit, quantity, unit_price, discount_percent, discount_amount,
                  taxable_amount, gst_rate, cgst_amount, sgst_amount, igst_amount, line_total)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($preparedItems as $item) {
                $insertInvoiceItem->execute([
                    $invoiceId, $item['line_no'], null, $item['product_id'],
                    $item['name'], $item['sku'], $item['hsn'], $item['unit'],
                    $item['quantity'], $item['price'], $item['discount_percent'],
                    $item['discount_amount'], $item['taxable'], $item['gst_rate'],
                    $item['cgst'], $item['sgst'], $item['igst'],
                    $item['line_total'],
                ]);
            }
            $pdo->prepare('UPDATE invoices SET shipping_address=? WHERE id=?')->execute([$shippingAddress ?: $buyerAddress, $invoiceId]);
            billing_store($pdo,'invoices',$invoiceId,$parties,$details,$charge);
            $pdo->commit();
            respond(['message' => 'Direct invoice created.', 'id' => $invoiceId, 'invoice_number' => $invoiceNumber], 201);
        }
        $fromStatus = null;
        if ($quoteId) {
            $existing = $pdo->prepare('SELECT * FROM quotations WHERE id=? AND deleted_at IS NULL FOR UPDATE');
            $existing->execute([$quoteId]);
            $quote = $existing->fetch();
            if (!$quote) {
                $pdo->rollBack();
                respond(['error' => 'Quotation or order not found.'], 404);
            }
            $quoteNumber = $quote['quote_number'];
            $sourceType = $quote['source_type'];
            $fromStatus = $quote['status'];
            if ($fromStatus === 'converted') $requestedStatus = 'converted';
            $update = $pdo->prepare(
                "UPDATE quotations SET customer_id=?, issue_date=?, valid_until=?, status=?,
                 supplier_state_code=?, place_of_supply_state_code=?, tax_mode=?, price_mode=?,
                 notes=?, subtotal=?, discount_total=?, taxable_total=?, cgst_total=?,
                 sgst_total=?, igst_total=?, grand_total=?,
                 approved_at=IF(?='approved', COALESCE(approved_at, NOW()), NULL) WHERE id=?"
            );
            $update->execute([
                $customerId, $issueDate, $validUntil, $requestedStatus,
                $sellerCode, $placeCode, $taxMode, $priceMode, $notes,
                $totals['subtotal'], $totals['discount_total'], $totals['taxable_total'],
                $totals['cgst_total'], $totals['sgst_total'], $totals['igst_total'],
                $totals['grand_total'], $requestedStatus, $quoteId,
            ]);
            $pdo->prepare('DELETE FROM quotation_items WHERE quotation_id=?')->execute([$quoteId]);
        } else {
            $fyStartYear = (int) substr($issueDate, 0, 4);
            if ((int) substr($issueDate, 5, 2) < 4) {
                $fyStartYear--;
            }
            $financialYear = sprintf('%d-%02d', $fyStartYear, ($fyStartYear + 1) % 100);
            $sequence = $pdo->prepare(
                "INSERT INTO document_sequences(document_type, financial_year, last_number)
                 VALUES('quotation', ?, 0)
                 ON DUPLICATE KEY UPDATE last_number=last_number"
            );
            $sequence->execute([$financialYear]);
            $lock = $pdo->prepare(
                "SELECT last_number FROM document_sequences
                 WHERE document_type='quotation' AND financial_year=? FOR UPDATE"
            );
            $lock->execute([$financialYear]);
            $next = (int) $lock->fetchColumn() + 1;
            $pdo->prepare(
                "UPDATE document_sequences SET last_number=?
                 WHERE document_type='quotation' AND financial_year=?"
            )->execute([$next, $financialYear]);
            $quoteNumber = sprintf('QT-%s-%06d', $financialYear, $next);
            $insert = $pdo->prepare(
                "INSERT INTO quotations
                 (quote_number, customer_id, issue_date, valid_until, status,
                  supplier_state_code, place_of_supply_state_code, tax_mode, price_mode,
                  notes, subtotal, discount_total, taxable_total, cgst_total, sgst_total,
                  igst_total, grand_total, approved_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                         IF(?='approved', NOW(), NULL))"
            );
            $insert->execute([
                $quoteNumber, $customerId, $issueDate, $validUntil, $requestedStatus,
                $sellerCode, $placeCode, $taxMode, $priceMode, $notes,
                $totals['subtotal'], $totals['discount_total'], $totals['taxable_total'],
                $totals['cgst_total'], $totals['sgst_total'], $totals['igst_total'],
                $totals['grand_total'], $requestedStatus,
            ]);
            $quoteId = (int) $pdo->lastInsertId();
        }
        $insertItem = $pdo->prepare(
            'INSERT INTO quotation_items
             (quotation_id, line_no, product_id, product_name, sku, hsn_code, unit,
              quantity, unit_price, discount_percent, discount_amount, taxable_amount,
              gst_rate, cgst_amount, sgst_amount, igst_amount, line_total)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($preparedItems as $item) {
            $insertItem->execute([
                $quoteId, $item['line_no'], $item['product_id'], $item['name'], $item['sku'],
                $item['hsn'], $item['unit'], $item['quantity'], $item['price'],
                $item['discount_percent'], $item['discount_amount'], $item['taxable'],
                $item['gst_rate'], $item['cgst'], $item['sgst'], $item['igst'],
                $item['line_total'],
            ]);
        }
        $history = $pdo->prepare(
            "INSERT INTO quotation_status_history
             (quotation_id, from_status, to_status, notes) VALUES (?, ?, ?, ?)"
        );
        $history->execute([$quoteId, $fromStatus, $requestedStatus, 'Saved from quotation screen']);
        $orderNumber=$sourceType==='order' ? 'ORD-'.substr($quoteNumber,3) : null;
        $advance = filter_var($data['advance_amount'] ?? ($quote['advance_amount'] ?? 0), FILTER_VALIDATE_FLOAT);
        $advanceDate = (string)($data['advance_date'] ?? ($quote['advance_date'] ?? date('Y-m-d')));
        $advanceMethod = (string)($data['advance_method'] ?? ($quote['advance_method'] ?? 'cash'));
        $parsedAdvanceDate = DateTime::createFromFormat('!Y-m-d',$advanceDate);
        if ($advance===false || $advance<0 || abs($advance-round($advance,2))>0.000001 || $advance>$totals['grand_total']+0.005 ||
            ($advance>0 && (!$parsedAdvanceDate || $parsedAdvanceDate->format('Y-m-d')!==$advanceDate || !in_array($advanceMethod,['cash','upi','bank_transfer','cheque','card','other'],true)))) {
            $pdo->rollBack();respond(['error'=>'Enter a valid advance amount (no more than the total), date and payment method.'],422);
        }
        if ($fromStatus==='converted' && ($advance!=(float)$quote['advance_amount'] || ($advance>0 && ($advanceDate!==$quote['advance_date'] || $advanceMethod!==$quote['advance_method'])))) {
            $pdo->rollBack();respond(['error'=>'The advance is already on the invoice. Record further payments from Payments.'],409);
        }
        $pdo->prepare('UPDATE quotations SET advance_amount=?,advance_date=?,advance_method=? WHERE id=?')->execute([$advance,$advance>0?$advanceDate:null,$advanceMethod,$quoteId]);
        $pdo->prepare('UPDATE quotations SET source_type=?,order_number=? WHERE id=?')->execute([$sourceType,$orderNumber,$quoteId]);
        if($orderNumber)$details['order_number']=$orderNumber;
        billing_store($pdo,'quotations',$quoteId,$parties,$details,$charge);
        $pdo->commit();
        respond(['message' => 'Quotation saved.', 'id' => $quoteId, 'quote_number' => $quoteNumber, 'order_number' => $orderNumber], 201);
    }

    if ($resource === 'quotations' && $method === 'DELETE') {
        if (!$id) {
            respond(['error' => 'A valid quotation ID is required.'], 422);
        }
        $statement = $pdo->prepare('UPDATE quotations SET deleted_at=NOW() WHERE id=? AND deleted_at IS NULL');
        $statement->execute([$id]);
        if ($statement->rowCount() === 0) {
            respond(['error' => 'Quotation or order not found or already deleted.'], 404);
        }
        respond(['message' => 'Quotation or order deleted. Linked invoices and payments are preserved.']);
    }

    if ($resource === 'invoices' && ($method === 'DELETE' || ($method === 'POST' && ($data['action'] ?? '') === 'edit'))) {
        $invoiceId = filter_var($method === 'DELETE' ? $id : ($data['invoice_id'] ?? null), FILTER_VALIDATE_INT);
        if (!$invoiceId) respond(['error' => 'Choose a valid invoice.'], 422);
        $pdo->beginTransaction();
        $check = $pdo->prepare('SELECT status, issue_date FROM invoices WHERE id=? FOR UPDATE');
        $check->execute([$invoiceId]);
        $invoice = $check->fetch();
        if (!$invoice || $invoice['status'] !== 'draft') {
            $pdo->rollBack();
            respond(['error' => 'Only draft invoices can be edited or deleted.'], 409);
        }
        if ($method === 'DELETE') {
            $pdo->prepare('DELETE FROM invoices WHERE id=?')->execute([$invoiceId]);
        } else {
            $due = trim((string) ($data['due_date'] ?? ''));
            $date = $due === '' ? null : DateTime::createFromFormat('!Y-m-d', $due);
            if ($due !== '' && (!$date || $date->format('Y-m-d') !== $due || $due < $invoice['issue_date'])) {
                $pdo->rollBack();
                respond(['error' => 'Due date must be on or after the invoice date.'], 422);
            }
            $pdo->prepare('UPDATE invoices SET due_date=?, notes=? WHERE id=?')->execute([$due ?: null, trim((string) ($data['notes'] ?? '')), $invoiceId]);
        }
        $pdo->commit();
        respond(['message' => $method === 'DELETE' ? 'Draft invoice deleted.' : 'Invoice details saved.']);
    }

    if ($resource === 'invoices' && $method === 'GET') {
        $sql = "SELECT i.id, i.customer_id, i.invoice_number, i.quotation_id, q.quote_number,
                       i.buyer_name AS customer, i.due_date, i.notes, i.issue_date, i.status, i.grand_total,
                       i.supplier_name, i.supplier_gstin, i.supplier_address, i.buyer_name,
                       i.buyer_gstin, i.billing_address, i.shipping_address, i.place_of_supply_state_code,
                       i.tax_mode, i.taxable_total, i.cgst_total, i.sgst_total, i.igst_total,
                       (SELECT b.net_paid FROM v_invoice_balances b WHERE b.id=i.id) AS paid_total,
                       GROUP_CONCAT(DISTINCT ii.product_name ORDER BY ii.line_no SEPARATOR ' | ') AS product_names
                FROM invoices i
                JOIN customers c ON c.id=i.customer_id
                LEFT JOIN quotations q ON q.id=i.quotation_id
                LEFT JOIN invoice_items ii ON ii.invoice_id=i.id";
        if ($id) {
            $statement = $pdo->prepare($sql . ' WHERE i.id=? GROUP BY i.id, i.invoice_number, i.quotation_id, q.quote_number, c.name, i.due_date, i.notes, i.issue_date, i.status, i.grand_total, i.supplier_name, i.supplier_gstin, i.supplier_address, i.buyer_name, i.buyer_gstin, i.billing_address, i.shipping_address, i.place_of_supply_state_code, i.tax_mode, i.taxable_total, i.cgst_total, i.sgst_total, i.igst_total');
            $statement->execute([$id]);
            $invoice = $statement->fetch();
            if (!$invoice) {
                respond(['error' => 'Invoice not found.'], 404);
            }
            $itemStatement = $pdo->prepare(
                'SELECT product_id, sku, unit, product_name, hsn_code AS hsn, quantity, unit_price,
                        discount_percent, taxable_amount, gst_rate, cgst_amount,
                        sgst_amount, igst_amount, line_total
                 FROM invoice_items WHERE invoice_id=? ORDER BY line_no'
            );
            $itemStatement->execute([$id]);
            $invoice['items'] = $itemStatement->fetchAll();
            $full=$pdo->prepare('SELECT * FROM invoices WHERE id=?'); $full->execute([$id]);
            $invoice=array_merge($invoice,$full->fetch());
            $paymentStatement = $pdo->prepare(
                'SELECT payment_date, amount, payment_method, transaction_reference, notes
                 FROM invoice_payments WHERE invoice_id=? ORDER BY payment_date, id'
            );
            $paymentStatement->execute([$id]);
            $invoice['payments'] = $paymentStatement->fetchAll();
            $paymentDetails=invoice_payment_details($pdo,(int)$id);
            $invoice['product_payments']=$paymentDetails['items'];
            $invoice['payments']=$paymentDetails['payments'];
            $invoice['paid_total']=$paymentDetails['paid_total'];
            $invoice['products'] = $invoice['product_names'] === null
                ? [] : explode(' | ', $invoice['product_names']);
            respond(['data' => $invoice]);
        }
        $rows = $pdo->query($sql . ' GROUP BY i.id, i.invoice_number, i.quotation_id, q.quote_number, c.name, i.due_date, i.notes, i.issue_date, i.status, i.grand_total, i.supplier_name, i.supplier_gstin, i.supplier_address, i.buyer_name, i.buyer_gstin, i.billing_address, i.shipping_address, i.place_of_supply_state_code, i.tax_mode, i.taxable_total, i.cgst_total, i.sgst_total, i.igst_total ORDER BY i.id DESC')->fetchAll();
        foreach ($rows as &$row) {
            $row['products'] = $row['product_names'] === null
                ? [] : explode(' | ', $row['product_names']);
            unset($row['product_names']);
        }
        unset($row);
        respond(['data' => $rows]);
    }

    if ($resource === 'invoices' && $method === 'POST' &&
        in_array(($data['action'] ?? ''), ['convert','convert_simple'], true)) {
        $quotationId = filter_var($data['quotation_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$quotationId) {
            respond(['error' => 'A valid quotation ID is required.'], 422);
        }
        $pdo->beginTransaction();
        $quoteStatement = $pdo->prepare(
            "SELECT q.*, c.name AS buyer_name, c.gstin AS buyer_gstin,
                    a.address_line1, a.city, a.state_name, a.state_code, a.pincode
             FROM quotations q
             JOIN customers c ON c.id=q.customer_id
             LEFT JOIN customer_addresses a
               ON a.customer_id=c.id AND a.address_type='billing'
             WHERE q.id=? AND q.deleted_at IS NULL FOR UPDATE"
        );
        $quoteStatement->execute([$quotationId]);
        $quote = $quoteStatement->fetch();
        $convertible = ($data['action'] ?? '') === 'convert_simple' ? ['draft','sent','approved'] : ['approved'];
        if (!$quote || !in_array($quote['status'], $convertible, true)) {
            $pdo->rollBack();
            respond(['error' => 'This quotation cannot be converted. Choose a saved quotation that has not been converted or rejected.'], 409);
        }
        $existingInvoice = $pdo->prepare('SELECT id FROM invoices WHERE quotation_id=?');
        $existingInvoice->execute([$quotationId]);
        if ($existingInvoice->fetchColumn()) {
            $pdo->rollBack();
            respond(['error' => 'This quotation has already been converted.'], 409);
        }
        $invoiceDate = date('Y-m-d');
        $financialYearStart = (int) substr($invoiceDate, 0, 4);
        if ((int) substr($invoiceDate, 5, 2) < 4) {
            $financialYearStart--;
        }
        $invoiceFy = sprintf('%d-%02d', $financialYearStart, ($financialYearStart + 1) % 100);
        $pdo->prepare(
            "INSERT INTO document_sequences(document_type, financial_year, last_number)
             VALUES('invoice', ?, 0)
             ON DUPLICATE KEY UPDATE last_number=last_number"
        )->execute([$invoiceFy]);
        $lock = $pdo->prepare(
            "SELECT last_number FROM document_sequences
             WHERE document_type='invoice' AND financial_year=? FOR UPDATE"
        );
        $lock->execute([$invoiceFy]);
        $invoiceSequence = (int) $lock->fetchColumn() + 1;
        $pdo->prepare(
            "UPDATE document_sequences SET last_number=?
             WHERE document_type='invoice' AND financial_year=?"
        )->execute([$invoiceSequence, $invoiceFy]);
        $invoiceNumber = sprintf('I%s%06d', str_replace('-', '', substr($invoiceFy, 2)), $invoiceSequence);
        $company = $pdo->query('SELECT * FROM company_settings WHERE id=1')->fetch();
        $configuration = $pdo->query('SELECT seller_state_name, seller_state_code FROM gst_configuration WHERE id=1')->fetch();
        $sellerStateName = $configuration['seller_state_name'] ?? 'Andhra Pradesh';
        $sellerStateCode = $configuration['seller_state_code'] ?? '37';
        $buyerAddress = trim(implode(', ', array_filter([
            $quote['address_line1'], $quote['city'], $quote['state_name'], $quote['pincode'],
        ])));
        $invoiceInsert = $pdo->prepare(
            'INSERT INTO invoices
             (invoice_number, quotation_id, customer_id, issue_date, due_date, status,
              supplier_name, supplier_gstin, supplier_address, supplier_state_code,
              buyer_name, buyer_gstin, billing_address, shipping_address,
              place_of_supply_state_code, tax_mode, price_mode, subtotal, discount_total,
              taxable_total, cgst_total, sgst_total, igst_total, round_off, grand_total, notes)
             VALUES (?, ?, ?, CURRENT_DATE(), NULL, "draft", ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?,
                     ?, ?, ?, ?, ?, ?, 0, ?, ?)'
        );
        $invoiceInsert->execute([
            $invoiceNumber, $quotationId, $quote['customer_id'],
            $company['company_name'] ?? 'Nivis Beauty Care',
            $company['gstin'] ?? null,
            $company['address'] ?? 'Business address not configured',
            $quote['supplier_state_code'],
            $quote['buyer_name'], $quote['buyer_gstin'],
            $buyerAddress ?: 'Billing address not provided',
            $quote['place_of_supply_state_code'], $quote['tax_mode'], $quote['price_mode'],
            $quote['subtotal'], $quote['discount_total'], $quote['taxable_total'],
            $quote['cgst_total'], $quote['sgst_total'], $quote['igst_total'],
            $quote['grand_total'], $quote['notes'],
        ]);
        $invoiceId = (int) $pdo->lastInsertId();
        $snapshot=json_decode($quote['party_snapshot'] ?? '',true);
        if ($snapshot) {
            $sp=$snapshot['supplier']; $bp=$snapshot['buyer'];
            $pdo->prepare('UPDATE invoices SET supplier_name=?,supplier_gstin=?,supplier_address=?,buyer_name=?,buyer_gstin=?,billing_address=? WHERE id=?')->execute([$sp['company_name'] ?? '',$sp['gstin'] ?? null,$sp['address'] ?? '',$bp['name'] ?? '',$bp['gstin'] ?? null,$bp['billing_address'] ?? '',$invoiceId]);
        }
        billing_store($pdo,'invoices',$invoiceId,$snapshot ?: billing_parties($pdo,(int)$quote['customer_id']),json_decode($quote['document_details'] ?? '',true) ?: [],(float)$quote['additional_charges']);
        $shipping = $pdo->prepare("SELECT CONCAT_WS(', ', address_line1, city, state_name, pincode) FROM customer_addresses WHERE customer_id=? AND address_type='shipping' LIMIT 1");
        $shipping->execute([$quote['customer_id']]);
        $pdo->prepare('UPDATE invoices SET shipping_address=? WHERE id=?')->execute([$snapshot['buyer']['shipping_address'] ?? ($shipping->fetchColumn() ?: $buyerAddress), $invoiceId]);
        $quoteItems = $pdo->prepare('SELECT * FROM quotation_items WHERE quotation_id=? ORDER BY line_no');
        $quoteItems->execute([$quotationId]);
        $insertInvoiceItem = $pdo->prepare(
            'INSERT INTO invoice_items
             (invoice_id, line_no, source_quotation_item_id, product_id, product_name, sku,
              hsn_code, unit, quantity, unit_price, discount_percent, discount_amount,
              taxable_amount, gst_rate, cgst_amount, sgst_amount, igst_amount, line_total)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($quoteItems->fetchAll() as $item) {
            $insertInvoiceItem->execute([
                $invoiceId, $item['line_no'], $item['id'], $item['product_id'],
                $item['product_name'], $item['sku'], $item['hsn_code'], $item['unit'],
                $item['quantity'], $item['unit_price'], $item['discount_percent'],
                $item['discount_amount'], $item['taxable_amount'], $item['gst_rate'],
                $item['cgst_amount'], $item['sgst_amount'], $item['igst_amount'],
                $item['line_total'],
            ]);
        }
        if ((float)$quote['advance_amount']>0) {
            $advanceDetails=invoice_payment_details($pdo,$invoiceId);
            $pdo->prepare('INSERT INTO invoice_payments(invoice_id,payment_date,amount,payment_method,notes) VALUES(?,?,?,?,?)')->execute([$invoiceId,$quote['advance_date'],$quote['advance_amount'],$quote['advance_method'],'Advance from '.$quote['quote_number']]);
            store_payment_allocation($pdo,(int)$pdo->lastInsertId(),$advanceDetails['items'],(float)$quote['advance_amount']);
        }
        $pdo->prepare("UPDATE quotations SET status='converted' WHERE id=?")->execute([$quotationId]);
        $pdo->prepare(
            "INSERT INTO quotation_status_history (quotation_id, from_status, to_status, notes)
             VALUES (?, ?, 'converted', 'Converted to invoice')"
        )->execute([$quotationId, $quote['status']]);
        $pdo->commit();
        respond(['message' => 'Quotation converted to a draft invoice.', 'id' => $invoiceId, 'invoice_number' => $invoiceNumber], 201);
    }

    if ($resource === 'invoices' && $method === 'POST' &&
        ($data['action'] ?? '') === 'issue') {
        $invoiceId = filter_var($data['invoice_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$invoiceId) {
            respond(['error' => 'A valid invoice ID is required.'], 422);
        }
        $pdo->beginTransaction();
        $issueCheck=$pdo->prepare('SELECT status,supplier_gstin,cgst_total,sgst_total,igst_total FROM invoices WHERE id=? FOR UPDATE');$issueCheck->execute([$invoiceId]);$issueRecord=$issueCheck->fetch();
        if ($issueRecord && !$issueRecord['supplier_gstin'] && ($issueRecord['cgst_total']+$issueRecord['sgst_total']+$issueRecord['igst_total'])>0) respond(['error'=>'A GST-charging invoice requires the actual supplier GSTIN. Correct company settings and recreate this draft before issuing.'],422);
        if (!$issueRecord || $issueRecord['status']!=='draft') {$pdo->rollBack();respond(['error'=>'Only draft invoices can be issued.'],409);}
        // Use a current read so the stock balance snapshot starts after acquiring product locks.
        $lines=$pdo->prepare('SELECT product_id,quantity,unit FROM invoice_items WHERE invoice_id=? AND product_id IS NOT NULL ORDER BY product_id FOR UPDATE');$lines->execute([$invoiceId]);
        $needed=[];$stock=[];
        foreach($lines->fetchAll() as $line){
            $pid=(int)$line['product_id'];
            if(!isset($stock[$pid])){$q=$pdo->prepare('SELECT * FROM products WHERE id=? FOR UPDATE');$q->execute([$pid]);$stock[$pid]=$q->fetch();}
            $p=$stock[$pid];
            if(!$p){$pdo->rollBack();respond(['error'=>'An invoice product no longer exists.'],422);}
            $qty=inventory_quantity((float)$line['quantity'],$line['unit'],$p['unit']);
            if($qty===null){$pdo->rollBack();respond(['error'=>'Use stock unit '.$p['unit'].' for '.$p['name'].'.'],422);}
            $needed[$pid]=($needed[$pid]??0)+$qty;
        }
        foreach($needed as $pid=>$qty){
            $balance=inventory_balance($pdo,$stock[$pid],false);
            if($balance['stock_error'] || $qty>$balance['available_quantity']+0.00001){$pdo->rollBack();respond(['error'=>$stock[$pid]['name'].': only '.$balance['available_quantity'].' '.$stock[$pid]['unit'].' available. '.($balance['stock_error']??'Reduce quantity or update total stock received.')],409);}
        }
        $statement = $pdo->prepare(
            "UPDATE invoices SET status='issued', issued_at=NOW()
             WHERE id=? AND status='draft'"
        );
        $statement->execute([$invoiceId]);
        if ($statement->rowCount() === 0) {
            respond(['error' => 'Only draft invoices can be issued.'], 409);
        }
        $pdo->commit();
        respond(['message' => 'Invoice issued.']);
    }

    if ($resource === 'invoices' && $method === 'POST' && ($data['action'] ?? '') === 'payment_delete') {
        $invoiceId=filter_var($data['invoice_id']??null,FILTER_VALIDATE_INT);
        $paymentId=filter_var($data['payment_id']??null,FILTER_VALIDATE_INT);
        if(!$invoiceId||!$paymentId)respond(['error'=>'Choose a valid invoice and payment.'],422);
        $pdo->beginTransaction();
        $lock=$pdo->prepare('SELECT id FROM invoices WHERE id=? FOR UPDATE');$lock->execute([$invoiceId]);
        $q=$pdo->prepare('SELECT * FROM invoice_payments WHERE id=? AND invoice_id=? FOR UPDATE');$q->execute([$paymentId,$invoiceId]);$receipt=$q->fetch();
        if(!$receipt){$pdo->rollBack();respond(['error'=>'Payment not found.'],404);}
        $q=$pdo->prepare('SELECT COUNT(*) FROM payment_refunds WHERE payment_id=?');$q->execute([$paymentId]);
        if($q->fetchColumn()>0){$pdo->rollBack();respond(['error'=>'This payment has refunds. Its refund history must be preserved.'],409);}
        $pdo->prepare("INSERT INTO audit_logs(entity_type,entity_id,action,before_data) VALUES('payment',?,'delete',?)")->execute([$paymentId,json_encode($receipt)]);
        $pdo->prepare('DELETE FROM invoice_payments WHERE id=?')->execute([$paymentId]);
        $pdo->commit();respond(['message'=>'Payment deleted. Invoice and product balances updated.']);
    }
    if ($resource === 'invoices' && $method === 'POST' &&
        in_array(($data['action'] ?? ''),['payment','payment_edit'],true)) {
        $invoiceId = filter_var($data['invoice_id'] ?? null, FILTER_VALIDATE_INT);
        $amount = filter_var($data['amount'] ?? null, FILTER_VALIDATE_FLOAT);
        $paymentDate = (string) ($data['payment_date'] ?? '');
        $paymentMethod = strtolower((string) ($data['payment_method'] ?? ''));
        $allowedMethods = ['cash', 'upi', 'bank_transfer', 'cheque', 'card', 'other'];
        if (!$invoiceId || $amount === false || $amount <= 0 ||
            !in_array($paymentMethod, $allowedMethods, true) ||
            !(DateTime::createFromFormat('!Y-m-d', $paymentDate) &&
              DateTime::createFromFormat('!Y-m-d', $paymentDate)->format('Y-m-d') === $paymentDate)) {
            respond(['error' => 'Enter a valid invoice, payment amount, date and method.'], 422);
        }
        if (abs($amount-round($amount,2))>0.000001) respond(['error'=>'Payment amounts support at most two decimal places.'],422);
        $pdo->beginTransaction();
        $invoiceStatement = $pdo->prepare(
            "SELECT grand_total, status FROM invoices WHERE id=? FOR UPDATE"
        );
        $invoiceStatement->execute([$invoiceId]);
        $invoice = $invoiceStatement->fetch();
        if (!$invoice || !in_array($invoice['status'],['draft','issued'],true)) {
            $pdo->rollBack();
            respond(['error' => 'Payments can only be recorded against draft or issued invoices.'], 409);
        }
        $editPaymentId=null;$previousReceipt=null;
        if(($data['action']??'')==='payment_edit'){
            $editPaymentId=filter_var($data['payment_id']??null,FILTER_VALIDATE_INT);
            $q=$pdo->prepare('SELECT * FROM invoice_payments WHERE id=? AND invoice_id=? FOR UPDATE');$q->execute([$editPaymentId,$invoiceId]);$previousReceipt=$q->fetch();
            if(!$previousReceipt){$pdo->rollBack();respond(['error'=>'Payment not found.'],404);}
            $q=$pdo->prepare('SELECT COUNT(*) FROM payment_refunds WHERE payment_id=?');$q->execute([$editPaymentId]);
            if($q->fetchColumn()>0){$pdo->rollBack();respond(['error'=>'Payments with refunds cannot be edited.'],409);}
        }
        $paymentTotals = $pdo->prepare(
            'SELECT COALESCE(SUM(amount), 0) FROM invoice_payments WHERE invoice_id=?'
        );
        $paymentTotals->execute([$invoiceId]);
        $paymentTotals->fetchColumn();
        $paymentDetails=invoice_payment_details($pdo,(int)$invoiceId,$editPaymentId);
        $paid=$paymentDetails['paid_total'];
        $itemId=isset($data['invoice_item_id']) && $data['invoice_item_id']!=='' ? filter_var($data['invoice_item_id'],FILTER_VALIDATE_INT) : null;
        if($itemId!==null){
            $matches=array_values(array_filter($paymentDetails['items'],fn($item)=>(int)$item['id']===$itemId));
            if(!$itemId || !$matches || $amount>$matches[0]['balance_due']+0.005){$pdo->rollBack();respond(['error'=>'Choose a product on this invoice and an amount within its remaining balance.'],422);}
        }
        if ($paid + $amount > (float) $invoice['grand_total'] + 0.005) {
            $pdo->rollBack();
            respond(['error' => 'Payment exceeds the invoice outstanding balance.'], 422);
        }
        if($editPaymentId!==null){
            $pdo->prepare('UPDATE invoice_payments SET payment_date=?,amount=?,payment_method=?,transaction_reference=?,notes=? WHERE id=?')->execute([$paymentDate,$amount,$paymentMethod,substr(trim((string)($data['transaction_reference']??'')),0,160),substr(trim((string)($data['notes']??$previousReceipt['notes']??'')),0,500),$editPaymentId]);
            $pdo->prepare('DELETE FROM invoice_payment_allocations WHERE payment_id=?')->execute([$editPaymentId]);
            $paymentId=(int)$editPaymentId;
            $pdo->prepare("INSERT INTO audit_logs(entity_type,entity_id,action,before_data,after_data) VALUES('payment',?,'edit',?,?)")->execute([$paymentId,json_encode($previousReceipt),json_encode($data)]);
        }else{
        $insert = $pdo->prepare(
            'INSERT INTO invoice_payments (invoice_id, payment_date, amount, payment_method, transaction_reference, notes)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([
            $invoiceId, $paymentDate, $amount, $paymentMethod,
            trim((string) ($data['transaction_reference'] ?? '')) ?: null, substr(trim((string)($data['notes'] ?? '')),0,500),
        ]);
        $paymentId=(int)$pdo->lastInsertId();
        }
        store_payment_allocation($pdo,$paymentId,$paymentDetails['items'],(float)$amount,$itemId);
        $pdo->commit();
        respond(['message' => 'Payment recorded.', 'id' => $paymentId], 201);
    }

    if ($resource === 'dashboard' && $method === 'GET') {
        $stats = $pdo->query(
            "SELECT
                (SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL) AS customers,
                (SELECT COUNT(*) FROM products) AS products,
                (SELECT COUNT(*) FROM quotations WHERE deleted_at IS NULL) AS quotations,
                (SELECT COUNT(*) FROM quotations WHERE deleted_at IS NULL AND status IN ('draft','sent')) AS waiting,
                (SELECT COALESCE(SUM(grand_total),0) FROM invoices
                 WHERE status IN ('draft','issued') AND issue_date >= DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')
                   AND issue_date < DATE_FORMAT(CURRENT_DATE() + INTERVAL 1 MONTH, '%Y-%m-01'))
                    AS invoice_value"
        )->fetch();
        $monthly = $pdo->query(
            "SELECT DATE_FORMAT(issue_date, '%Y-%m') AS month,
                    COALESCE(SUM(grand_total),0) AS total
             FROM invoices
             WHERE status='issued'
               AND issue_date >= DATE_FORMAT(CURRENT_DATE() - INTERVAL 7 MONTH, '%Y-%m-01')
             GROUP BY DATE_FORMAT(issue_date, '%Y-%m')
             ORDER BY month"
        )->fetchAll();
        $recent = $pdo->query(
            "SELECT document_number, customer, amount, status, document_date, document_type
             FROM (
                SELECT q.quote_number AS document_number, c.name AS customer,
                       q.grand_total AS amount, q.status, q.issue_date AS document_date,
                       'Quotation' AS document_type
                FROM quotations q JOIN customers c ON c.id=q.customer_id WHERE q.deleted_at IS NULL
                UNION ALL
                SELECT i.invoice_number, c.name, i.grand_total, i.status, i.issue_date,
                       'Invoice'
                FROM invoices i JOIN customers c ON c.id=i.customer_id
             ) recent_documents
             ORDER BY document_date DESC, document_number DESC LIMIT 8"
        )->fetchAll();
        respond(['stats' => $stats, 'monthly_sales' => $monthly, 'recent' => $recent]);
    }

    if ($resource === 'reports' && $method === 'GET') {
        $from = (string) ($_GET['from'] ?? '');
        $to = (string) ($_GET['to'] ?? '');
        $type = (string) ($_GET['type'] ?? 'sales');
        $clientId = filter_var($_GET['customer_id'] ?? 0, FILTER_VALIDATE_INT);
        if ($clientId === false || $clientId < 0) respond(['error' => 'Choose a valid client.'], 422);
        $validDate = static function (string $value): bool {
            $date = DateTime::createFromFormat('!Y-m-d', $value);
            return $date !== false && $date->format('Y-m-d') === $value;
        };
        $types = ['sales', 'invoice', 'gst', 'quotation', 'payments', 'outstanding', 'customers', 'products'];
        if (!$validDate($from) || !$validDate($to) || $to < $from ||
            !in_array($type, $types, true)) {
            respond(['error' => 'Choose a valid report type and date range.'], 422);
        }

        $invoiceSummary = $pdo->prepare(
            "SELECT COALESCE(SUM(i.grand_total),0) AS sales,
                    COALESCE(SUM(CASE WHEN i.status='issued' THEN b.outstanding ELSE 0 END),0) AS outstanding,
                    COALESCE(SUM(b.net_paid),0) AS collected
             FROM invoices i JOIN v_invoice_balances b ON b.id=i.id
             WHERE i.status IN ('draft','issued') AND i.issue_date BETWEEN ? AND ?
               AND (?=0 OR i.customer_id=?)"
        );
        $invoiceSummary->execute([$from, $to, $clientId, $clientId]);
        $summary = $invoiceSummary->fetch();

        $queries = [
            'invoice' => [
                "SELECT i.invoice_number AS document, i.issue_date AS date, c.name AS customer,
                        COALESCE(q.quote_number, 'Direct invoice') AS source,
                        (SELECT GROUP_CONCAT(CONCAT(ii.product_name, ' x ', ii.quantity, ' ', ii.unit) ORDER BY ii.line_no SEPARATOR '; ') FROM invoice_items ii WHERE ii.invoice_id=i.id) AS products,
                        i.grand_total AS total, b.paid_amount, b.refunded_amount, b.net_paid,
                        CASE WHEN i.status='cancelled' THEN 0 ELSE b.outstanding END AS balance_due,
                        (SELECT GROUP_CONCAT(CONCAT(p.payment_date, ': ', p.amount, ' (', p.payment_method, ')') ORDER BY p.payment_date,p.id SEPARATOR '; ') FROM invoice_payments p WHERE p.invoice_id=i.id) AS payment_history,
                        i.status
                 FROM invoices i JOIN customers c ON c.id=i.customer_id
                 LEFT JOIN quotations q ON q.id=i.quotation_id
                 JOIN v_invoice_balances b ON b.id=i.id
                 WHERE i.issue_date BETWEEN ? AND ? ORDER BY i.issue_date DESC, i.id DESC",
                [$from, $to],
            ],
            'sales' => [
                "SELECT i.invoice_number AS document, i.issue_date AS date, c.name AS customer,
                        i.grand_total AS total, i.status
                 FROM invoices i JOIN customers c ON c.id=i.customer_id
                 WHERE i.status='issued' AND i.issue_date BETWEEN ? AND ?
                 ORDER BY i.issue_date DESC, i.id DESC",
                [$from, $to],
            ],
            'gst' => [
                "SELECT i.invoice_number AS document, i.issue_date AS date, c.name AS customer,
                        i.taxable_total, i.cgst_total, i.sgst_total, i.igst_total, i.grand_total
                 FROM invoices i JOIN customers c ON c.id=i.customer_id
                 WHERE i.status='issued' AND i.issue_date BETWEEN ? AND ?
                 ORDER BY i.issue_date DESC, i.id DESC",
                [$from, $to],
            ],
            'quotation' => [
                'SELECT q.quote_number AS document, q.issue_date AS date, c.name AS customer,
                        q.status, q.grand_total AS total
                 FROM quotations q JOIN customers c ON c.id=q.customer_id
                 WHERE q.deleted_at IS NULL AND q.issue_date BETWEEN ? AND ? ORDER BY q.issue_date DESC, q.id DESC',
                [$from, $to],
            ],
            'payments' => [
                'SELECT i.invoice_number AS document, p.payment_date AS date, c.name AS customer,
                        p.payment_method, p.transaction_reference, p.amount
                 FROM invoice_payments p
                 JOIN invoices i ON i.id=p.invoice_id
                 JOIN customers c ON c.id=i.customer_id
                 WHERE p.payment_date BETWEEN ? AND ? ORDER BY p.payment_date DESC, p.id DESC',
                [$from, $to],
            ],
            'outstanding' => [
                "SELECT i.invoice_number AS document, i.issue_date AS date, c.name AS customer,
                        b.grand_total AS total, b.net_paid, b.outstanding
                 FROM v_invoice_balances b
                 JOIN invoices i ON i.id=b.id
                 JOIN customers c ON c.id=i.customer_id
                 WHERE i.status='issued' AND i.issue_date BETWEEN ? AND ?
                   AND b.outstanding > 0.005
                 ORDER BY i.issue_date DESC, i.id DESC",
                [$from, $to],
            ],
            'customers' => [
                "SELECT c.name, c.mobile, COUNT(i.id) AS invoices, COALESCE(SUM(i.grand_total),0) AS sales
                 FROM customers c LEFT JOIN invoices i ON i.customer_id=c.id AND i.status='issued' AND i.issue_date BETWEEN ? AND ?
                 GROUP BY c.id, c.name, c.mobile ORDER BY sales DESC, c.name",
                [$from, $to],
            ],
            'products' => [
                "SELECT p.name, p.sku, c.name AS category, p.hsn_code,
                        COALESCE(SUM(s.quantity),0) AS quantity_sold, COALESCE(SUM(s.line_total),0) AS sales
                 FROM products p JOIN categories c ON c.id=p.category_id
                 LEFT JOIN (SELECT ii.product_id, ii.quantity, ii.line_total FROM invoice_items ii
                    JOIN invoices i ON i.id=ii.invoice_id WHERE i.status='issued' AND i.issue_date BETWEEN ? AND ?) s ON s.product_id=p.id
                 GROUP BY p.id, p.name, p.sku, c.name, p.hsn_code ORDER BY sales DESC, p.name",
                [$from, $to],
            ],
        ];
        [$reportSql, $reportParams] = $queries[$type];
        if ($clientId) {
            if ($type === 'products') {
                $reportSql = str_replace('i.issue_date BETWEEN ? AND ?', 'i.issue_date BETWEEN ? AND ? AND i.customer_id=?', $reportSql);
            } elseif ($type === 'customers') {
                $reportSql = str_replace('GROUP BY c.id', 'WHERE c.id=? GROUP BY c.id', $reportSql);
            } else {
                $dateColumn = $type === 'quotation' ? 'q.issue_date' : ($type === 'payments' ? 'p.payment_date' : 'i.issue_date');
                $reportSql = str_replace($dateColumn . ' BETWEEN ? AND ?', $dateColumn . ' BETWEEN ? AND ? AND c.id=?', $reportSql);
            }
            $reportParams[] = $clientId;
        }
        $statement = $pdo->prepare($reportSql);
        $statement->execute($reportParams);
        respond([
            'summary' => $summary,
            'rows' => $statement->fetchAll(),
        ]);
    }

    respond(['error' => 'Unsupported API resource or method.'], 404);
} catch (PDOException $error) {
    if (!empty($newProductImage)) remove_product_image($newProductImage);
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Nivis database API error: ' . $error->getMessage());
    $mysqlError = (int) ($error->errorInfo[1] ?? 0);
    if (in_array($mysqlError, [1062, 1451, 1452], true)) {
        respond(['error' => $mysqlError === 1062 ? 'A record with the same unique value already exists. Check GSTIN or customer code.' : 'This record is linked to other data. Check the related records.', 'code' => 'DB_' . $mysqlError], 409);
    }
    // Return only a validated schema identifier, never raw SQL or submitted values.
    $field = '';
    if (preg_match("/(?:Field|Column|column) '([a-zA-Z0-9_]+)'/", $error->getMessage(), $match)) $field = $match[1];
    $fieldLabel = $field !== '' ? ' (' . $field . ')' : '';
    $messages = [
        1364 => 'The live database requires a field with no default' . $fieldLabel . '. Its schema differs from the application. Run database/migrate_identity_columns.php on the hosting server; check PHP logs if the repair fails.',
        1048 => 'A required database field cannot be empty' . $fieldLabel . '. Check the live table definition.',
        1265 => 'The live database does not accept this field value' . $fieldLabel . '. Check the allowed values in the table definition.',
        1406 => 'The value is too long for the live database field' . $fieldLabel . '. Check the field length.',
        1366 => 'The live database field has an incompatible type or character encoding' . $fieldLabel . '.',
        3819 => 'A database validation rule rejected this record. Check the customer field values and live table constraints.',
        4025 => 'A database validation rule rejected this record. Check the customer field values and live table constraints.',
        1045 => 'Database login failed. Check NIVIS_DB_USER and NIVIS_DB_PASSWORD in hosting settings or config/database.php.',
        1044 => 'Database access denied. Assign the database user to this database in your hosting panel.',
        1049 => 'Database not found. Check NIVIS_DB_NAME, including the hosting account prefix.',
        2002 => 'Cannot reach the database server. Check NIVIS_DB_HOST and NIVIS_DB_PORT.',
        2003 => 'Cannot reach the database server. Check the database host and port.',
        1146 => 'A required database table or view is missing. Import the application schema into the selected live database.',
        1054 => 'The live database schema is outdated. Run database/migrate_product_defaults.php on the server and verify all application tables were imported.',
        1142 => 'Database permission denied. Read/write access is required; schema repairs require ALTER permission. Ask your hosting administrator to run database/migrate_identity_columns.php and database/migrate_product_defaults.php.',
        1143 => 'Database column access denied. Check database user permissions in the hosting panel.',
        1055 => 'A database query is incompatible with the server grouping rules. Check the hosting PHP error log for the failing query.',
    ];
    respond(['error' => $messages[$mysqlError] ?? 'Database operation failed. Check the hosting PHP error log for the Nivis database API error entry.', 'code' => 'DB_' . $mysqlError], 500);
} catch (Throwable $error) {
    if (!empty($newProductImage)) remove_product_image($newProductImage);
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    $reference = substr(hash('sha256', uniqid('', true)), 0, 12);
    error_log('Nivis API error [' . $reference . '] resource=' . ($resource ?? 'startup') . ' method=' . ($_SERVER['REQUEST_METHOD'] ?? 'unknown') . ' ' . get_class($error) . ': ' . $error->getMessage() . ' at ' . $error->getFile() . ':' . $error->getLine());
    if ($error instanceof NivisConfigurationException) {
        respond(['error' => $error->getMessage(), 'code' => 'SETUP_ERROR'], 500);
    }
    respond(['error' => 'Save failed because of a server error. Check the PHP error log using reference ' . $reference . '.', 'code' => 'SERVER_ERROR'], 500);
}
