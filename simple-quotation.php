<?php
require_once __DIR__.'/config/business_profile.php';
$quotationBusiness=nivis_business_profile();
$simpleMode = $simpleMode ?? 'quotation';
$simpleTitle = $simpleMode === 'order' ? 'New order' : ($simpleMode === 'invoice' ? 'New invoice' : 'New quotation');
?>
<section class="nivis-labs-creat-quotation" id="simpleQuotation">
  <div class="container-fluid p-0">
    <div class="nlcq-header"><div><h1><?= $simpleTitle ?></h1><p><?= $simpleMode === 'order' ? 'Save an order to automatically create its quotation.' : 'Select a customer, add products and save.' ?></p></div></div>
    <form id="simpleQuotationForm" data-mode="<?= $simpleMode ?>">
      <div class="nlcq-card">
        <h2>From</h2>
        <img src="assets/img/logo.png" alt="Nivis Labs logo" width="140" height="72" style="object-fit:contain;object-position:left center" class="mb-2">
        <p class="mb-1"><strong><?= htmlspecialchars($quotationBusiness['company_name'],ENT_QUOTES,'UTF-8') ?></strong></p>
        <p class="mb-1"><?= htmlspecialchars($quotationBusiness['address'],ENT_QUOTES,'UTF-8') ?></p>
        <p class="mb-1">Email: <?= htmlspecialchars($quotationBusiness['email'],ENT_QUOTES,'UTF-8') ?></p>
        <p class="mb-0">Contact: <?= htmlspecialchars($quotationBusiness['phone'],ENT_QUOTES,'UTF-8') ?></p>
      </div>
      <div class="nlcq-card">
        <label for="simpleQuoteCustomer" class="nlcq-label">CUSTOMER NAME</label>
        <select id="simpleQuoteCustomer" class="form-select" required><option value="">Select customer</option></select>
        <label for="simpleQuoteSearch" class="nlcq-label mt-3">SEARCH CUSTOMER</label>
        <input type="search" id="simpleQuoteSearch" class="form-control" placeholder="Type a letter or name" autocomplete="off">
        <div id="simpleQuoteMatches" class="list-group mt-1" style="max-height:200px;overflow:auto"></div>
        <label for="simpleQuoteGstin" class="nlcq-label mt-3">CUSTOMER GST NUMBER (OPTIONAL)</label>
        <input id="simpleQuoteGstin" class="form-control" maxlength="15" pattern="[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]" placeholder="GSTIN / leave empty if unregistered">
      </div>
      <div class="nlcq-card">
        <label for="simpleQuoteRegion" class="nlcq-label">GST TYPE</label>
        <select id="simpleQuoteRegion" class="form-select">
          <option value="inside">Inside GST (CGST + SGST)</option>
          <option value="outside">Outside GST (IGST)</option>
        </select>
        <div id="simpleQuoteOutsideField" class="mt-3" hidden>
          <label for="simpleQuoteSupplyState" class="nlcq-label">OUTSIDE STATE</label>
          <select id="simpleQuoteSupplyState" class="form-select"><option value="">Select state</option></select>
        </div>
      </div>
      <div class="nlcq-card">
        <div class="table-responsive"><table class="table nlcq-items-table"><thead><tr><th>Product name</th><th>Available stock</th><th>Quantity</th><th>Unit</th><th>Price / unit</th><th>Discount %</th><th>Tax</th><th>Total</th><th></th></tr></thead><tbody id="simpleQuoteLines"></tbody></table></div>
        <p class="text-muted">Example: Quantity 50 + Unit PCS, or Quantity 30 + Unit KG. Enter the price for one selected unit.</p>
        <button type="button" class="nlcq-add-line" id="simpleQuoteAdd">+ Add product</button>
      </div>
      <div class="nlcq-card">
        <p id="simpleQuoteTaxMode" class="text-muted"></p>
        <div class="nlcq-totals"><div><span>Tax</span><strong id="simpleQuoteTax">Rs 0.00</strong></div><div class="nlcq-grand-total"><span>Total</span><strong id="simpleQuoteTotal">Rs 0.00</strong></div></div>
        <div class="nlcq-actions"><a href="<?= $simpleMode === 'order' ? 'orders.php' : ($simpleMode === 'invoice' ? 'invoices.php' : 'quotations.php') ?>" class="nlcq-btn nlcq-btn-outline">Cancel</a><button type="submit" id="simpleQuoteSave" class="nlcq-btn nlcq-btn-primary" disabled>Save <?= $simpleMode ?></button></div>
        <p id="simpleQuoteMessage" role="status">Loading customers and products...</p>
      </div>
    </form>
  </div>
</section>
<script src="assets/js/simple-quotation.js?v=orders-3"></script>
