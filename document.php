<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/security.php';
require_once __DIR__ . '/config/billing.php';
function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function money($v): string
{
    return 'Rs ' . number_format((float)$v, 2);
}
try {
    $pdo = database_connection();
    if ($denied = nivis_access($pdo)) {
        header('Location: login.php');
        exit;
    }
    ensure_document_columns($pdo);
    $type = $_GET['type'] ?? 'invoice';
    if (!in_array($type, ['quotation', 'invoice'], true)) {
        http_response_code(400);
        exit('Invalid document type.');
    }
    $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
    $table = $type === 'quotation' ? 'quotations' : 'invoices';
    $q = $pdo->prepare("SELECT * FROM $table WHERE id=?");
    $q->execute([$id]);
    $doc = $q->fetch();
    if (!$doc) {
        http_response_code(404);
        exit('Document not found.');
    }
    $q = $pdo->prepare("SELECT * FROM {$type}_items WHERE {$type}_id=? ORDER BY line_no");
    $q->execute([$id]);
    $items = $q->fetchAll();
    $parties = json_decode($doc['party_snapshot'] ?? '', true) ?: [];
    $supplier = $parties['supplier'] ?? [];
    $buyer = $parties['buyer'] ?? [];
    if ($type === 'invoice') {
        $supplier = array_merge($supplier, ['company_name' => $doc['supplier_name'], 'gstin' => $doc['supplier_gstin'], 'address' => $doc['supplier_address']]);
        $buyer = array_merge($buyer, ['name' => $doc['buyer_name'], 'gstin' => $doc['buyer_gstin'], 'billing_address' => $doc['billing_address'], 'shipping_address' => $doc['shipping_address']]);
    }
    $details = json_decode($doc['document_details'] ?? '', true) ?: [];
    $treatments = $details['tax_treatments'] ?? [];
    $billOfSupply = $treatments && !array_diff($treatments, ['nil_rated', 'exempt', 'non_gst']);
    $number = $doc[$type === 'quotation' ? 'quote_number' : 'invoice_number'];
    $paid = 0;
    if ($type === 'invoice') {
        $q = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM invoice_payments WHERE invoice_id=?');
        $q->execute([$id]);
        $paid = $q->fetchColumn();
    }
} catch (Throwable $error) {
    error_log($error->getMessage());
    http_response_code(500);
    exit('Document unavailable. Check the server log.');
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= e($number) ?></title>
    <link rel="stylesheet" href="assets/css/document.css">
</head>

<body>
    <nav class="document-actions"><a href="<?= $type === 'quotation' ? 'quotations.php' : 'invoices.php' ?>">Back to <?= e($table) ?></a><button id="printDocument">Print</button><button id="downloadDocument">Download PDF</button><span id="pdfMessage" role="status"></span></nav>
    <main id="billingDocument" class="paper">
        <header class="document-header"><img src="assets/img/logo.png" alt="Nivis Labs" width="120">
            <div>
                <h1><?= $type === 'quotation' ? 'QUOTATION' : (!empty($supplier['gstin']) ? ($billOfSupply ? 'BILL OF SUPPLY' : 'TAX INVOICE') : 'INVOICE') ?></h1><strong><?= e($number) ?></strong>
                <p><?= e(strtoupper($doc['status'] === 'approved' ? 'accepted' : $doc['status'])) ?></p>
            </div>
        </header>
        <div class="parties">
            <!-- <section>
                <h2>From</h2><strong><?= e($supplier['company_name'] ?? 'Business details not captured') ?></strong>
                <p><?= nl2br(e($supplier['address'] ?? '')) ?></p>
                <p><?= e(implode(' | ', array_filter([$supplier['phone'] ?? '', $supplier['email'] ?? '']))) ?></p><?php if (!empty($supplier['gstin'])): ?><p>GSTIN: <?= e($supplier['gstin']) ?></p><?php endif; ?>
            </section> -->

            <section>
                <!-- Logo Image -->
                <?php if (!empty($supplier['logo_url'])): ?>
                    <img src="<?= e($supplier['logo_url']) ?>" alt="<?= e($supplier['company_name'] ?? 'Company Logo') ?>" class="supplier-logo" style="max-width: 150px; max-height: 80px; height: auto; margin-bottom: 10px; display: block;">
                <?php endif; ?>

                <!-- Company Details -->
                <h2><?= e($supplier['company_name'] ?? 'Business details not captured') ?></h2>

                <?php if (!empty($supplier['address'])): ?>
                    <p><?= nl2br(e($supplier['address'])) ?></p>
                <?php endif; ?>

                <?php
                $contact_info = array_filter([$supplier['phone'] ?? '', $supplier['email'] ?? '']);
                if (!empty($contact_info)):
                ?>
                    <p><?= e(implode(' | ', $contact_info)) ?></p>
                <?php endif; ?>

                <?php if (!empty($supplier['gstin'])): ?>
                    <p>GSTIN: <?= e($supplier['gstin']) ?></p>
                <?php endif; ?>
            </section>





            <section>
                <h2>Bill to</h2><strong><?= e($buyer['name'] ?? 'Customer details not captured on this legacy quotation') ?></strong>
                <p><?= nl2br(e($buyer['billing_address'] ?? '')) ?></p>
                <p><?= e(implode(' | ', array_filter([$buyer['mobile'] ?? '', $buyer['email'] ?? '']))) ?></p><?php if (!empty($buyer['gstin'])): ?><p>GSTIN: <?= e($buyer['gstin']) ?></p><?php endif; ?><h2>Ship to</h2>
                <p><?= nl2br(e($buyer['shipping_address'] ?? $buyer['billing_address'] ?? '')) ?></p>
            </section>
        </div>
        <div class="document-meta"><span>Date: <?= e($doc['issue_date']) ?></span><span><?= $type === 'quotation' ? 'Valid until' : 'Due date' ?>: <?= e($doc[$type === 'quotation' ? 'valid_until' : 'due_date'] ?? 'Not set') ?></span><span>Place of supply (state code): <?= e($doc['place_of_supply_state_code']) ?></span><span>Prices <?= e($doc['price_mode']) ?> of GST</span></div>
        <table>
            <thead>
                <tr>
                    <th># / Item / SKU</th>
                    <th>HSN/SAC</th>
                    <th>Qty / Unit</th>
                    <th>Price</th>
                    <th>Disc.</th>
                    <th>Taxable</th>
                    <th>GST</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $line): ?><tr>
                        <td><?= e($line['line_no'] . '. ' . $line['product_name']) ?><small><?= e($line['sku']) ?></small><small><?= e($details['item_descriptions'][$line['line_no']] ?? '') ?></small></td>
                        <td><?= e($line['hsn_code']) ?></td>
                        <td><?= e($line['quantity'] . ' ' . $line['unit']) ?></td>
                        <td><?= money($line['unit_price']) ?></td>
                        <td><?= e($line['discount_percent']) ?>%</td>
                        <td><?= money($line['taxable_amount']) ?></td>
                        <td><?= e($line['gst_rate']) ?>%<small><?= e(str_replace('_', ' ', $treatments[$line['line_no']] ?? '')) ?></small><small><?= money($line['cgst_amount'] + $line['sgst_amount'] + $line['igst_amount']) ?></small></td>
                        <td><?= money($line['line_total']) ?></td>
                    </tr><?php endforeach; ?>
            </tbody>
        </table>
        <section class="summary">
            <dl>
                <dt>Subtotal (including charges)</dt>
                <dd><?= money($doc['subtotal']) ?></dd>
                <dt>Discount</dt>
                <dd><?= money($doc['discount_total']) ?></dd>
                <dt>Additional charges (included above)</dt>
                <dd><?= money($doc['additional_charges']) ?></dd>
                <dt>Taxable value</dt>
                <dd><?= money($doc['taxable_total']) ?></dd>
                <?php foreach (($doc['tax_mode'] === 'cgst_sgst' ? ['cgst' => 'CGST', 'sgst' => 'SGST'] : ['igst' => 'IGST']) as $key => $label): ?><dt><?= $label ?></dt>
                    <dd><?= money($doc[$key . '_total']) ?></dd><?php endforeach; ?>
                <dt>Round off</dt>
                <dd><?= money($doc['round_off']) ?></dd>
                <dt class="grand">Grand total</dt>
                <dd class="grand"><?= money($doc['grand_total']) ?></dd>
                <?php if ($type === 'invoice'): ?><dt>Paid</dt>
                    <dd><?= money($paid) ?></dd>
                    <dt>Outstanding</dt>
                    <dd><?= money($doc['grand_total'] - $paid) ?></dd><?php endif; ?>
            </dl>
        </section>
        <p><strong>Amount in words:</strong> <span id="amountWords" data-amount="<?= e($doc['grand_total']) ?>"></span></p>
        <?php if ($doc['notes']): ?><section class="terms">
                <h2>Notes</h2>
                <p><?= nl2br(e($doc['notes'])) ?></p>
            </section><?php endif; ?>
        <?php foreach (['terms' => 'Terms and conditions', 'payment_terms' => 'Payment terms', 'delivery_timeline' => 'Delivery timeline', 'bank_details' => 'Bank details', 'payment_instructions' => 'Payment instructions'] as $key => $label): if (!empty($details[$key])): ?><section class="terms">
                    <h2><?= e($label) ?></h2>
                    <p><?= nl2br(e($details[$key])) ?></p>
                </section><?php endif;
                    endforeach; ?>
        <?php if (!empty($details['signatory'])): ?><section class="signature">
                <p>For <?= e($supplier['company_name'] ?? '') ?></p>
                <div></div><strong><?= e($details['signatory']) ?></strong>
                <p>Authorized signatory</p>
            </section><?php endif; ?>
        <?php if (!empty($doc['cancellation_reason'])): ?><p>Cancellation reason: <?= e($doc['cancellation_reason']) ?></p><?php endif; ?>
    </main>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="assets/js/document.js"></script>
</body>

</html>