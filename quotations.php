
<?php
// Common sidebar - include only once.
include 'header.php';
include 'sidebar.php';
?>

<link rel="stylesheet" href="assets/css/nivis-labs-quotation.css">

<!-- Quotations Section Start -->
<section class="nivis-labs-quotation" id="nivis-labs-quotation">
  <div class="container-fluid p-0">

    <!-- Header -->
    <div class="nivis-labs-quotation-header">
      <div>
        <h1>Quotations</h1>
        <p>Quote pipeline and conversion control</p>
      </div>

      <div class="nivis-labs-quotation-account">
        Admin <span>|</span> AP Business
      </div>
    </div>

    <!-- New Quotation Button -->
    <div class="nivis-labs-quotation-toolbar">
      <a href="add-edit-quotation.php"
         class="nivis-labs-quotation-new-btn">
        + New quotation
      </a>
    </div>

    <!-- Quotation Status Cards -->
    <div class="row g-3 nivis-labs-quotation-stats">

      <div class="col-6 col-lg-3">
        <div class="nivis-labs-quotation-stat-card">
          <h6>DRAFT</h6>
          <h2 id="quoteDraftCount">12</h2>
        </div>
      </div>

      <div class="col-6 col-lg-3">
        <div class="nivis-labs-quotation-stat-card">
          <h6>SENT</h6>
          <h2 id="quoteSentCount">25</h2>
        </div>
      </div>

      <div class="col-6 col-lg-3">
        <div class="nivis-labs-quotation-stat-card">
          <h6>APPROVED</h6>
          <h2 id="quoteApprovedCount">18</h2>
        </div>
      </div>

      <div class="col-6 col-lg-3">
        <div class="nivis-labs-quotation-stat-card">
          <h6>CONVERTED</h6>
          <h2 id="quoteConvertedCount">29</h2>
        </div>
      </div>

    </div>

    <!-- Quotation Table -->
    <div class="nivis-labs-quotation-table-card">

      <div class="table-responsive">
        <table class="table nivis-labs-quotation-table mb-0">

          <thead>
            <tr>
              <th>Quote #</th>
              <th>Customer</th>
              <th>Date</th>
              <th>Total</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>

          <tbody id="nivisQuotationTableBody">
            <!-- JS will render quotations -->
          </tbody>

        </table>
      </div>

      <div id="nivisQuotationEmpty"
           class="nivis-labs-quotation-empty d-none">
        No quotations found.
      </div>

    </div>

    <!-- Developer Notes -->
    <div class="nivis-labs-quotation-notes">
      <h5>SCREEN RULES / DEVELOPER NOTES</h5>

      <p>• Status: Draft &gt; Sent &gt; Approved / Rejected &gt; Converted (or Expired).</p>
      <p>• Convert action available for approved quotation; prevent duplicate conversion.</p>
      <p>• List actions: preview PDF, download, send/share, edit draft, duplicate, convert.</p>

      <span class="nivis-labs-quotation-page">09</span>
    </div>

  </div>
</section>
<!-- Quotations Section End -->

<!-- Convert Confirmation Modal -->
<div class="modal fade"
     id="nivisQuotationConvertModal"
     tabindex="-1"
     aria-labelledby="nivisQuotationConvertTitle"
     aria-hidden="true">

  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content nivis-labs-quotation-modal">

      <div class="modal-header">
        <h5 class="modal-title" id="nivisQuotationConvertTitle">
          Convert quotation
        </h5>
        <button type="button" class="btn-close"
                data-bs-dismiss="modal"
                aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <p id="nivisQuotationConvertText" class="mb-0"></p>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light"
                data-bs-dismiss="modal">
          Cancel
        </button>

        <button type="button"
                class="btn nivis-labs-quotation-confirm-btn"
                id="nivisQuotationConfirmConvert">
          Continue to invoice
        </button>
      </div>

    </div>
  </div>
</div>

<!-- Include Bootstrap JS only if not loaded globally -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/nivis-labs-quotation.js"></script>
