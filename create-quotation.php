
<?php
// Include common sidebar only once.
// Remove this line if your parent layout already includes it.
include_once 'header.php';
include 'sidebar.php';
?>

<section class="nivis-labs-creat-quotation" id="nivis-labs-creat-quotation">
  <div class="container-fluid p-0">

    <!-- Header -->
    <div class="nlcq-header">
      <div>
        <h1>Create Quotation</h1>
        <p>Build a beauty-care quote for sunscreen, moisturizers, serums and more</p>
      </div>
      <div class="nlcq-account">Admin <span>|</span> AP Business</div>
    </div>

    <!-- Quotation Information -->
    <div class="nlcq-card nlcq-info-card">
      <h2><i class="fa-solid fa-file-invoice" aria-hidden="true"></i> Quotation information</h2>

      <div class="row g-3">
        <div class="col-12 col-sm-6 col-xl-3">
          <label class="nlcq-label" for="nlcqQuoteNumber">QUOTE NUMBER</label>
          <input type="text" class="form-control" id="nlcqQuoteNumber"
                 value="Assigned when saved" readonly>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
          <label class="nlcq-label" for="nlcqCustomer">CUSTOMER *</label>
          <select class="form-select" id="nlcqCustomer" required>
            <option value="">Select customer</option>
          </select>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
          <label class="nlcq-label" for="nlcqIssueDate">ISSUE DATE</label>
          <input type="date" class="form-control" id="nlcqIssueDate"
                 value="2026-10-12">
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
          <label class="nlcq-label" for="nlcqValidUntil">VALID UNTIL</label>
          <input type="date" class="form-control" id="nlcqValidUntil"
                 value="2026-10-27">
        </div>
      </div>
    </div>

    <div class="nlcq-card"><label for="nlcqSupplyState" class="nlcq-label">PLACE OF SUPPLY</label><select id="nlcqSupplyState" class="form-select"><option value="37">Andhra Pradesh — Inside AP</option><option value="36">Telangana — Outside AP</option><option value="29">Karnataka — Outside AP</option><option value="33">Tamil Nadu — Outside AP</option><option value="27">Maharashtra — Outside AP</option><option value="32">Kerala — Outside AP</option><option value="21">Odisha — Outside AP</option><option value="07">Delhi — Outside AP</option></select><small id="nlcqTaxTreatment"></small></div>
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
            <div><span>Taxable:</span><span id="nlcqTaxable">Rs 0.00</span></div>
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
          Preview PDF
        </button>
        <button type="button" id="nlcqApprove" class="nlcq-btn nlcq-btn-outline">
          Mark approved
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
