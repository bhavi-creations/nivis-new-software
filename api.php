<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/schema.php';

header('Content-Type: application/json; charset=utf-8');

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function request_data(): array
{
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
    $pdo = database_connection();
    $resource = (string) ($_GET['resource'] ?? '');
    if (in_array($resource, ['products', 'categories'], true)) {
        ensure_billing_columns($pdo, $resource);
    }
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
    $id = filter_var($_GET['id'] ?? $data['id'] ?? null, FILTER_VALIDATE_INT);

    if ($resource === 'products' && $method === 'GET') {
        $rows = $pdo->query(
            "SELECT p.id, p.default_quantity AS quantity, p.gst_region, p.name, p.sku, p.category_id, c.name AS category,
                    p.hsn_code AS hsn, p.unit, p.selling_price AS price,
                    COALESCE(t.rate_percent, 0) AS gst,
                    p.default_discount_percent AS discount, p.status, p.description
             FROM products p
             JOIN categories c ON c.id = p.category_id
             LEFT JOIN tax_rates t ON t.id = p.tax_rate_id
             ORDER BY p.id"
        )->fetchAll();
        respond(['data' => $rows]);
    }

    if ($resource === 'products' && $method === 'POST') {
        $name = required_text($data, 'name', 180);
        $sku = trim((string) ($data['sku'] ?? ''));
        if ($sku === '') $sku = 'PRD-' . strtoupper(bin2hex(random_bytes(6)));
        if (strlen($sku) > 70) respond(['error' => 'Product code is too long.'], 422);
        $quantity = filter_var($data['quantity'] ?? 1, FILTER_VALIDATE_FLOAT);
        $region = (string) ($data['gst_region'] ?? 'inside_ap');
        if ($quantity === false || $quantity <= 0 || $quantity > 1000000 || !in_array($region, ['inside_ap', 'outside_ap'], true)) {
            respond(['error' => 'Enter a quantity above zero and choose a GST region.'], 422);
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
            "SELECT id FROM tax_rates WHERE rate_percent = ? AND is_enabled=1 AND effective_from <= CURRENT_DATE() AND (effective_to IS NULL OR effective_to >= CURRENT_DATE())
             ORDER BY is_enabled DESC, id LIMIT 1"
        );
        $rate->execute([$gst]);
        $taxRateId = $rate->fetchColumn();
        if ($taxRateId === false) {
            respond(['error' => 'Choose a GST rate configured in Tax & GST settings.'], 422);
        }
        $productId = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        if (isset($data['id']) && !$productId) {
            respond(['error' => 'Invalid product ID.'], 422);
        }
        if ($productId) {
            $statement = $pdo->prepare(
                'UPDATE products SET default_quantity=?, gst_region=?, name=?, sku=?, category_id=?, hsn_code=?, unit=?,
                 selling_price=?, default_discount_percent=?, tax_rate_id=?, description=?, status=?
                 WHERE id=?'
            );
            $statement->execute([
                $quantity, $region, $name, $sku, $categoryId, $hsn, $unit, $price, $discount,
                $taxRateId ?: null, trim((string) ($data['description'] ?? '')),
                $status, $productId,
            ]);
            if ($statement->rowCount() === 0 &&
                !(int) $pdo->query('SELECT COUNT(*) FROM products WHERE id=' . $productId)->fetchColumn()) {
                respond(['error' => 'Product not found.'], 404);
            }
            respond(['message' => 'Product saved.', 'id' => $productId]);
        }
        $statement = $pdo->prepare(
            'INSERT INTO products
             (default_quantity, gst_region, name, sku, category_id, hsn_code, unit, selling_price, default_discount_percent,
              tax_rate_id, description, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $quantity, $region, $name, $sku, $categoryId, $hsn, $unit, $price, $discount,
            $taxRateId ?: null, trim((string) ($data['description'] ?? '')), $status,
        ]);
        respond(['message' => 'Product saved.', 'id' => (int) $pdo->lastInsertId()], 201);
    }

    if ($resource === 'products' && $method === 'DELETE') {
        if (!$id) {
            respond(['error' => 'A valid product ID is required.'], 422);
        }
        $statement = $pdo->prepare('DELETE FROM products WHERE id = ?');
        $statement->execute([$id]);
        if ($statement->rowCount() === 0) {
            respond(['error' => 'Product not found.'], 404);
        }
        respond(['message' => 'Product deleted.']);
    }

    if ($resource === 'categories' && $method === 'GET') {
        $rows = $pdo->query(
            'SELECT c.id, c.name, c.code, c.description, c.status, c.product_count,
                    COUNT(p.id) AS linked_products, COALESCE(c.product_count, COUNT(p.id)) AS products
             FROM categories c LEFT JOIN products p ON p.category_id=c.id
             GROUP BY c.id ORDER BY c.id'
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
        $code = strtoupper(required_text($data, 'code', 30));
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
                    c.gst_registration, c.status,
                    MAX(CASE WHEN a.address_type='billing' THEN a.address_line1 END) AS billing_address,
                    MAX(CASE WHEN a.address_type='billing' THEN a.city END) AS city,
                    MAX(CASE WHEN a.address_type='billing' THEN a.state_name END) AS state,
                    MAX(CASE WHEN a.address_type='billing' THEN a.pincode END) AS pincode,
                    MAX(CASE WHEN a.address_type='shipping' THEN a.address_line1 END) AS shipping_address,
                    MAX(CASE WHEN a.address_type='shipping' THEN a.city END) AS shipping_city,
                    MAX(CASE WHEN a.address_type='shipping' THEN a.state_name END) AS shipping_state,
                    MAX(CASE WHEN a.address_type='shipping' THEN a.pincode END) AS shipping_pincode
             FROM customers c LEFT JOIN customer_addresses a ON a.customer_id=c.id
             GROUP BY c.id ORDER BY c.id"
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
                 gst_registration=?, gstin=?, status=? WHERE id=?'
            );
            $statement->execute([
                $name, $mobile, $email ?: null, $type, $registration, $gstin ?: null,
                $status, $customerId,
            ]);
            if ($statement->rowCount() === 0 &&
                !(int) $pdo->query('SELECT COUNT(*) FROM customers WHERE id=' . $customerId)->fetchColumn()) {
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
        $statement = $pdo->prepare('DELETE FROM customers WHERE id=?');
        $statement->execute([$id]);
        if ($statement->rowCount() === 0) {
            respond(['error' => 'Customer not found.'], 404);
        }
        respond(['message' => 'Customer deleted.']);
    }

    if ($resource === 'tax' && $method === 'GET') {
        $config = $pdo->query('SELECT * FROM gst_configuration WHERE id=1')->fetch();
        $rates = $pdo->query(
            'SELECT id, name, rate_percent, treatment, effective_from, effective_to, is_enabled
             FROM tax_rates ORDER BY rate_percent, id'
        )->fetchAll();
        respond(['configuration' => $config, 'rates' => $rates]);
    }

    if ($resource === 'tax_rates' && $method === 'POST') {
        $name = required_text($data, 'name', 100);
        $rate = filter_var($data['rate_percent'] ?? null, FILTER_VALIDATE_FLOAT);
        $effective = (string) ($data['effective_from'] ?? '');
        $date = DateTime::createFromFormat('!Y-m-d', $effective);
        $treatment = (string) ($data['treatment'] ?? 'taxable');
        if ($rate === false || $rate < 0 || $rate > 100 || !$date || $date->format('Y-m-d') !== $effective ||
            !in_array($treatment, ['taxable','zero_rated','nil_rated','exempt','non_gst'], true) || ($treatment !== 'taxable' && $rate != 0)) {
            respond(['error' => 'Enter a valid rate, treatment and effective date.'], 422);
        }
        $statement = $pdo->prepare('INSERT INTO tax_rates (name, rate_percent, treatment, effective_from, is_enabled) VALUES (?, ?, ?, ?, 1)');
        $statement->execute([$name, $rate, $treatment, $effective]);
        respond(['message' => 'GST rate saved.', 'id' => (int) $pdo->lastInsertId()], 201);
    }

    if ($resource === 'tax' && $method === 'POST') {
        $state = trim((string) ($data['seller_state_name'] ?? ''));
        $code = state_code($state);
        $taxMode = (string) ($data['tax_mode'] ?? 'auto');
        $priceMode = (string) ($data['price_mode'] ?? 'exclusive');
        if ($code === '' || !in_array($taxMode, ['auto', 'manual_review'], true) ||
            !in_array($priceMode, ['exclusive', 'inclusive'], true)) {
            respond(['error' => 'Choose valid tax configuration values.'], 422);
        }
        $statement = $pdo->prepare(
            'UPDATE gst_configuration SET seller_state_name=?, seller_state_code=?,
             tax_mode=?, price_mode=? WHERE id=1'
        );
        $statement->execute([$state, $code, $taxMode, $priceMode]);
        respond(['message' => 'Tax configuration saved.']);
    }

    if ($resource === 'quotations' && $method === 'GET') {
        $sql = "SELECT q.id, q.quote_number, q.customer_id, c.name AS customer,
                       q.issue_date, q.valid_until, q.notes, q.place_of_supply_state_code, q.status, q.grand_total,
                       i.id AS invoice_id,
                       GROUP_CONCAT(qi.product_name ORDER BY qi.line_no SEPARATOR ' | ') AS product_names
                FROM quotations q
                JOIN customers c ON c.id=q.customer_id
                LEFT JOIN invoices i ON i.quotation_id=q.id
                LEFT JOIN quotation_items qi ON qi.quotation_id=q.id";
        if ($id) {
            $statement = $pdo->prepare($sql . ' WHERE q.id=? GROUP BY q.id');
            $statement->execute([$id]);
            $rows = $statement->fetchAll();
            if (!$rows) {
                respond(['error' => 'Quotation not found.'], 404);
            }
            $itemStatement = $pdo->prepare(
                'SELECT product_id, product_name, sku, hsn_code AS hsn, unit,
                        quantity, unit_price, discount_percent, gst_rate,
                        taxable_amount, line_total
                 FROM quotation_items WHERE quotation_id=? ORDER BY line_no'
            );
            $itemStatement->execute([$id]);
            $rows[0]['items'] = $itemStatement->fetchAll();
            respond(['data' => $rows[0]]);
        }
        $rows = $pdo->query($sql . ' GROUP BY q.id ORDER BY q.id DESC')->fetchAll();
        foreach ($rows as &$row) {
            $row['products'] = $row['product_names'] === null
                ? [] : explode(' | ', $row['product_names']);
            unset($row['product_names']);
        }
        unset($row);
        respond(['data' => $rows]);
    }

    if ($resource === 'quotations' && $method === 'POST') {
        $quoteId = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        $customerId = filter_var($data['customer_id'] ?? null, FILTER_VALIDATE_INT);
        $issueDate = (string) ($data['issue_date'] ?? '');
        $validUntil = (string) ($data['valid_until'] ?? '');
        $notes = trim((string) ($data['notes'] ?? ''));
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

        $pdo->beginTransaction();
        $customerStatement = $pdo->prepare(
            "SELECT c.name, c.gstin, a.state_code
             FROM customers c LEFT JOIN customer_addresses a
               ON a.customer_id=c.id AND a.address_type='billing'
             WHERE c.id=? LIMIT 1"
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
        $sellerCode = $configuration['seller_state_code'] ?? '37';
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
        $productStatement = $pdo->prepare(
            "SELECT p.id, p.name, p.sku, p.hsn_code, p.unit, p.selling_price,
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
                'unit' => $product['unit'],
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
        foreach ($totals as $key => $value) {
            $totals[$key] = round($value, 2);
        }

        $fromStatus = null;
        if ($quoteId) {
            $existing = $pdo->prepare('SELECT quote_number, status FROM quotations WHERE id=? FOR UPDATE');
            $existing->execute([$quoteId]);
            $quote = $existing->fetch();
            if (!$quote || $quote['status'] !== 'draft') {
                $pdo->rollBack();
                respond(['error' => 'Only an existing draft quotation can be edited.'], 409);
            }
            $quoteNumber = $quote['quote_number'];
            $fromStatus = $quote['status'];
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
        $pdo->commit();
        respond(['message' => 'Quotation saved.', 'id' => $quoteId, 'quote_number' => $quoteNumber], 201);
    }

    if ($resource === 'quotations' && $method === 'DELETE') {
        if (!$id) {
            respond(['error' => 'A valid quotation ID is required.'], 422);
        }
        $statement = $pdo->prepare("DELETE FROM quotations WHERE id=? AND status='draft'");
        $statement->execute([$id]);
        if ($statement->rowCount() === 0) {
            respond(['error' => 'Only draft quotations can be deleted.'], 409);
        }
        respond(['message' => 'Quotation deleted.']);
    }

    if ($resource === 'invoices' && $method === 'GET') {
        $sql = "SELECT i.id, i.invoice_number, i.quotation_id, q.quote_number,
                       c.name AS customer, i.issue_date, i.status, i.grand_total,
                       i.supplier_name, i.supplier_gstin, i.supplier_address, i.buyer_name,
                       i.buyer_gstin, i.billing_address, i.shipping_address, i.place_of_supply_state_code,
                       i.tax_mode, i.taxable_total, i.cgst_total, i.sgst_total, i.igst_total,
                       (SELECT COALESCE(SUM(p.amount), 0) FROM invoice_payments p
                        WHERE p.invoice_id=i.id) AS paid_total,
                       GROUP_CONCAT(DISTINCT ii.product_name ORDER BY ii.line_no SEPARATOR ' | ') AS product_names
                FROM invoices i
                JOIN customers c ON c.id=i.customer_id
                LEFT JOIN quotations q ON q.id=i.quotation_id
                LEFT JOIN invoice_items ii ON ii.invoice_id=i.id";
        if ($id) {
            $statement = $pdo->prepare($sql . ' WHERE i.id=? GROUP BY i.id');
            $statement->execute([$id]);
            $invoice = $statement->fetch();
            if (!$invoice) {
                respond(['error' => 'Invoice not found.'], 404);
            }
            $itemStatement = $pdo->prepare(
                'SELECT product_name, hsn_code AS hsn, quantity, unit_price,
                        discount_percent, taxable_amount, gst_rate, cgst_amount,
                        sgst_amount, igst_amount, line_total
                 FROM invoice_items WHERE invoice_id=? ORDER BY line_no'
            );
            $itemStatement->execute([$id]);
            $invoice['items'] = $itemStatement->fetchAll();
            $paymentStatement = $pdo->prepare(
                'SELECT payment_date, amount, payment_method, transaction_reference
                 FROM invoice_payments WHERE invoice_id=? ORDER BY payment_date, id'
            );
            $paymentStatement->execute([$id]);
            $invoice['payments'] = $paymentStatement->fetchAll();
            $invoice['products'] = $invoice['product_names'] === null
                ? [] : explode(' | ', $invoice['product_names']);
            respond(['data' => $invoice]);
        }
        $rows = $pdo->query($sql . ' GROUP BY i.id ORDER BY i.id DESC')->fetchAll();
        foreach ($rows as &$row) {
            $row['products'] = $row['product_names'] === null
                ? [] : explode(' | ', $row['product_names']);
            unset($row['product_names']);
        }
        unset($row);
        respond(['data' => $rows]);
    }

    if ($resource === 'invoices' && $method === 'POST' &&
        ($data['action'] ?? '') === 'convert') {
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
             WHERE q.id=? FOR UPDATE"
        );
        $quoteStatement->execute([$quotationId]);
        $quote = $quoteStatement->fetch();
        if (!$quote || $quote['status'] !== 'approved') {
            $pdo->rollBack();
            respond(['error' => 'Only an approved quotation can be converted.'], 409);
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
        $pdo->prepare("UPDATE quotations SET status='converted' WHERE id=?")->execute([$quotationId]);
        $pdo->prepare(
            "INSERT INTO quotation_status_history (quotation_id, from_status, to_status, notes)
             VALUES (?, 'approved', 'converted', 'Converted to invoice')"
        )->execute([$quotationId]);
        $pdo->commit();
        respond(['message' => 'Quotation converted to a draft invoice.', 'id' => $invoiceId, 'invoice_number' => $invoiceNumber], 201);
    }

    if ($resource === 'invoices' && $method === 'POST' &&
        ($data['action'] ?? '') === 'issue') {
        $invoiceId = filter_var($data['invoice_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$invoiceId) {
            respond(['error' => 'A valid invoice ID is required.'], 422);
        }
        $statement = $pdo->prepare(
            "UPDATE invoices SET status='issued', issued_at=NOW()
             WHERE id=? AND status='draft'"
        );
        $statement->execute([$invoiceId]);
        if ($statement->rowCount() === 0) {
            respond(['error' => 'Only draft invoices can be issued.'], 409);
        }
        respond(['message' => 'Invoice issued.']);
    }

    if ($resource === 'invoices' && $method === 'POST' &&
        ($data['action'] ?? '') === 'payment') {
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
        $pdo->beginTransaction();
        $invoiceStatement = $pdo->prepare(
            "SELECT grand_total, status FROM invoices WHERE id=? FOR UPDATE"
        );
        $invoiceStatement->execute([$invoiceId]);
        $invoice = $invoiceStatement->fetch();
        if (!$invoice || $invoice['status'] !== 'issued') {
            $pdo->rollBack();
            respond(['error' => 'Payments can only be recorded against issued invoices.'], 409);
        }
        $paymentTotals = $pdo->prepare(
            'SELECT COALESCE(SUM(amount), 0) FROM invoice_payments WHERE invoice_id=?'
        );
        $paymentTotals->execute([$invoiceId]);
        $paid = (float) $paymentTotals->fetchColumn();
        if ($paid + $amount > (float) $invoice['grand_total'] + 0.005) {
            $pdo->rollBack();
            respond(['error' => 'Payment exceeds the invoice outstanding balance.'], 422);
        }
        $insert = $pdo->prepare(
            'INSERT INTO invoice_payments (invoice_id, payment_date, amount, payment_method, transaction_reference)
             VALUES (?, ?, ?, ?, ?)'
        );
        $insert->execute([
            $invoiceId, $paymentDate, $amount, $paymentMethod,
            trim((string) ($data['transaction_reference'] ?? '')) ?: null,
        ]);
        $pdo->commit();
        respond(['message' => 'Payment recorded.', 'id' => (int) $pdo->lastInsertId()], 201);
    }

    if ($resource === 'dashboard' && $method === 'GET') {
        $stats = $pdo->query(
            "SELECT
                (SELECT COUNT(*) FROM customers) AS customers,
                (SELECT COUNT(*) FROM products) AS products,
                (SELECT COUNT(*) FROM quotations) AS quotations,
                (SELECT COUNT(*) FROM quotations WHERE status IN ('draft','sent')) AS waiting,
                (SELECT COALESCE(SUM(grand_total),0) FROM invoices
                 WHERE status='issued' AND issue_date >= DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')
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
                FROM quotations q JOIN customers c ON c.id=q.customer_id
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
        $validDate = static function (string $value): bool {
            $date = DateTime::createFromFormat('!Y-m-d', $value);
            return $date !== false && $date->format('Y-m-d') === $value;
        };
        $types = ['sales', 'gst', 'quotation', 'payments', 'outstanding', 'customers', 'products'];
        if (!$validDate($from) || !$validDate($to) || $to < $from ||
            !in_array($type, $types, true)) {
            respond(['error' => 'Choose a valid report type and date range.'], 422);
        }

        $invoiceSummary = $pdo->prepare(
            "SELECT COALESCE(SUM(i.grand_total),0) AS sales,
                    COALESCE(SUM(b.outstanding),0) AS outstanding
             FROM invoices i JOIN v_invoice_balances b ON b.id=i.id
             WHERE i.status='issued' AND i.issue_date BETWEEN ? AND ?"
        );
        $invoiceSummary->execute([$from, $to]);
        $summary = $invoiceSummary->fetch();
        $paymentSummary = $pdo->prepare(
            'SELECT COALESCE(SUM(amount),0) FROM invoice_payments
             WHERE payment_date BETWEEN ? AND ?'
        );
        $paymentSummary->execute([$from, $to]);
        $summary['collected'] = $paymentSummary->fetchColumn();

        $queries = [
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
                 WHERE q.issue_date BETWEEN ? AND ? ORDER BY q.issue_date DESC, q.id DESC',
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
                        b.grand_total AS total, b.paid_amount, b.outstanding
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
                 GROUP BY c.id ORDER BY sales DESC, c.name",
                [$from, $to],
            ],
            'products' => [
                "SELECT p.name, p.sku, c.name AS category, p.hsn_code,
                        COALESCE(SUM(s.quantity),0) AS quantity_sold, COALESCE(SUM(s.line_total),0) AS sales
                 FROM products p JOIN categories c ON c.id=p.category_id
                 LEFT JOIN (SELECT ii.product_id, ii.quantity, ii.line_total FROM invoice_items ii
                    JOIN invoices i ON i.id=ii.invoice_id WHERE i.status='issued' AND i.issue_date BETWEEN ? AND ?) s ON s.product_id=p.id
                 GROUP BY p.id ORDER BY sales DESC, p.name",
                [$from, $to],
            ],
        ];
        $statement = $pdo->prepare($queries[$type][0]);
        $statement->execute($queries[$type][1]);
        respond([
            'summary' => $summary,
            'rows' => $statement->fetchAll(),
        ]);
    }

    respond(['error' => 'Unsupported API resource or method.'], 404);
} catch (PDOException $error) {
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
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Nivis API error: ' . $error->getMessage());
    respond(['error' => 'The request could not be completed.'], 500);
}
