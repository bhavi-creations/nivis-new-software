
<?php
// Include common sidebar only once.
// Remove this line if your parent layout already includes it.
$directInvoice = isset($directInvoice) && $directInvoice;
include_once 'header.php';
include 'sidebar.php';
if (!$directInvoice) {
    include __DIR__ . '/simple-quotation.php';
    include __DIR__ . '/footer.php';
    return;
}
?>

<section class="nivis-labs-creat-quotation" id="nivis-labs-creat-quotation" data-document-type="<?= $directInvoice ? 'invoice' : 'quotation' ?>">
  <div class="container-fluid p-0">

    <!-- Header -->
    <div class="nlcq-header">
      <div>
        <h1><?= $directInvoice ? 'Create Direct Invoice' : 'Create Quotation' ?></h1>
        <p>Select a customer, add products and save.</p>
      </div>
      <div class="nlcq-account">Admin <span>|</span> AP Business</div>
    </div>

    <!-- Quotation Information -->
    <div class="nlcq-card nlcq-info-card">
      <h2><i class="fa-solid fa-file-invoice" aria-hidden="true"></i> Document information</h2>

      <div class="row g-3">
        <div class="col-12 col-sm-6 col-xl-3">
          <label class="nlcq-label" for="nlcqQuoteNumber"><?= $directInvoice ? 'INVOICE NUMBER' : 'QUOTE NUMBER' ?></label>
          <input type="text" class="form-control" id="nlcqQuoteNumber"
                 value="Assigned when saved" readonly>
          <small><?= $directInvoice ? 'Automatically assigned on save.' : 'Next available number; confirmed on save. Product references appear below.' ?></small>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
          <label class="nlcq-label" for="nlcqCustomer">CUSTOMER *</label>
          <select class="form-select" id="nlcqCustomer" required>
            <option value="">Select customer</option>
          </select>
          <label for="nlcqCustomerSearch" class="nlcq-label mt-2">SEARCH CUSTOMER</label><input id="nlcqCustomerSearch" type="search" class="form-control" placeholder="Type a letter or name" autocomplete="off"><div id="nlcqCustomerMatches" class="list-group mt-1" style="max-height:220px;overflow-y:auto" aria-live="polite"></div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
          <label class="nlcq-label" for="nlcqIssueDate">ISSUE DATE</label>
          <input type="date" class="form-control" id="nlcqIssueDate"
                 value="2026-10-12">
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
          <label class="nlcq-label" for="nlcqValidUntil"><?= $directInvoice ? 'DUE DATE' : 'VALID UNTIL' ?></label>
          <input type="date" class="form-control" id="nlcqValidUntil"
                 value="2026-10-27">
        </div>
      </div>
    </div>

    <div class="nlcq-card"><label for="nlcqSupplyState" class="nlcq-label">PLACE OF SUPPLY</label><select id="nlcqSupplyState" class="form-select"><option value="37">Andhra Pradesh — Inside AP</option><option value="36">Telangana — Outside AP</option><option value="29">Karnataka — Outside AP</option><option value="33">Tamil Nadu — Outside AP</option><option value="27">Maharashtra — Outside AP</option><option value="32">Kerala — Outside AP</option><option value="21">Odisha — Outside AP</option><option value="07">Delhi — Outside AP</option></select><small id="nlcqTaxTreatment"></small></div>
    <div class="nlcq-card"><h2>Customer details</h2><p id="nlcqCustomerDetails" style="white-space:pre-line">Select a saved customer to see billing, shipping and contact details.</p></div>
    <!-- Line Items -->
    <div class="nlcq-card nlcq-items-card">
      <h2><i class="fa-solid fa-spa" aria-hidden="true"></i> Beauty product line items</h2>

      <div class="table-responsive">
        <table class="table nlcq-items-table mb-0">
          <thead>
            <tr>
              <th>S.No</th>
              <th>Product</th>
              <th>HSN</th>
              <th>Qty</th>
              <th>Price</th>
              <th>GST</th>
              <th>Disc.</th>
              <th>Total</th>
              <th class="nlcq-row-action-heading"></th>
            </tr>
          </thead>

          <tbody id="nlcqLineItems"></tbody>
        </table>
      </div>

      <button type="button" class="nlcq-add-line" id="nlcqAddLine">
        + Add line
      </button>
    </div>

    <div class="nlcq-card"><h2>Additional charges (optional)</h2><div class="row g-3">
      <div class="col-md-4"><label for="nlcqChargeDescription">Description</label><input id="nlcqChargeDescription" class="form-control" maxlength="180"></div>
      <div class="col-md-2"><label for="nlcqCharges">Amount</label><input id="nlcqCharges" class="form-control" type="number" value="0" min="0" step="0.01"></div>
      <div class="col-md-2"><label for="nlcqChargeHsn">HSN/SAC</label><input id="nlcqChargeHsn" class="form-control" maxlength="8"></div>
      <div class="col-md-4"><label for="nlcqChargeRate">GST rate</label><select id="nlcqChargeRate" class="form-select"><option value="">Select rate</option></select></div>
    </div></div>
    <div class="nlcq-card"><h2>Document terms</h2><div class="row g-3">
<div class="col-md-6"><label for="nlcq_terms">terms</label><textarea class="form-control" id="nlcq_terms" maxlength="5000"></textarea></div>
<div class="col-md-6"><label for="nlcq_payment_terms">payment terms</label><textarea class="form-control" id="nlcq_payment_terms" maxlength="5000"></textarea></div>
<div class="col-md-6"><label for="nlcq_delivery_timeline">delivery timeline</label><textarea class="form-control" id="nlcq_delivery_timeline" maxlength="5000"></textarea></div>
<div class="col-md-6"><label for="nlcq_bank_details">bank details</label><textarea class="form-control" id="nlcq_bank_details" maxlength="5000"></textarea></div>
<div class="col-md-6"><label for="nlcq_payment_instructions">payment instructions</label><textarea class="form-control" id="nlcq_payment_instructions" maxlength="5000"></textarea></div>
<div class="col-md-6"><label for="nlcq_signatory">signatory</label><textarea class="form-control" id="nlcq_signatory" maxlength="5000"></textarea></div>
    </div></div>
    <!-- Notes / Terms & Totals -->
    <div class="nlcq-card nlcq-summary-card">
      <div class="row g-3">
        <div class="col-12 col-lg-7">
          <h2><i class="fa-regular fa-note-sticky" aria-hidden="true"></i> Notes / terms</h2>
          <textarea id="nlcqNotes" class="nlcq-notes-input"
                    rows="2" placeholder="Enter delivery and payment terms"></textarea>
        </div>

        <div class="col-12 col-lg-5">
          <div class="nlcq-totals">
            <div><span>Subtotal:</span><span id="nlcqSubtotal">Rs 0.00</span></div><div><span>Discount:</span><span id="nlcqDiscount">Rs 0.00</span></div><div><span>Tax split:</span><span id="nlcqTaxSplit">Rs 0.00</span></div><div><span>Taxable:</span><span id="nlcqTaxable">Rs 0.00</span></div>
            <div><span>GST:</span><span id="nlcqTax">Rs 0.00</span></div>
            <div class="nlcq-grand-total">
              <strong>GRAND TOTAL:</strong>
              <strong id="nlcqGrandTotal">Rs 0.00</strong>
            </div>
          </div>
        </div>
      </div>

      <div class="nlcq-actions">
        <button type="button" id="nlcqSaveDraft" class="nlcq-btn nlcq-btn-outline">
          Save draft
        </button>
        <button type="button" id="nlcqPreview" class="nlcq-btn nlcq-btn-outline">
          Save & preview
        </button>
        <button type="button" id="nlcqSent" class="nlcq-btn nlcq-btn-outline">Mark sent</button>
        <button type="button" id="nlcqApprove" class="nlcq-btn nlcq-btn-outline">
          Mark accepted
        </button>
        <button type="button" id="nlcqConvert" class="nlcq-btn nlcq-btn-primary">
          Convert to invoice
        </button>
      </div>

      <div class="nlcq-feedback" id="nlcqFeedback" role="status"></div>
    </div>



  </div>
</section>

<?php include 'footer.php'; ?>
