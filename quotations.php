
<?php
// Common sidebar - include only once.
include_once 'header.php';
include 'sidebar.php';
?>

<!-- Quotations Section Start -->
<section class="nivis-labs-quotation" id="nivis-labs-quotation">
  <div class="container-fluid p-0">
    <?php if (isset($_GET['saved'])): ?>
      <p class="alert alert-success" role="status">Quotation saved. You can view it or convert it to an invoice below.</p>
    <?php endif; ?>


    <!-- Header -->
    <div class="nivis-labs-quotation-header">
      <div>
        <h1>Quotations</h1>
        <p>Save a quotation, view its details or convert it to an invoice.</p>
      </div>

      <div class="nivis-labs-quotation-account">
        Admin <span>|</span> AP Business
      </div>
    </div>

    <!-- New Quotation Button -->
    <div class="nivis-labs-quotation-toolbar">
      <a href="create-quotation.php"
         class="nivis-labs-quotation-new-btn">
        + New quotation
      </a>
    </div>

    <div class="nivis-labs-quotation-filters">
      <div class="row g-3 align-items-end">
        <div class="col-12 col-md-5">
          <label for="nivisQuotationSearch">SEARCH QUOTATIONS / PRODUCTS</label>
          <input type="search" class="form-control" id="nivisQuotationSearch"
                 placeholder="Quote number, retailer or sunscreen">
        </div>
        <div class="col-12 col-md-3">
          <label for="nivisQuotationCustomer">CUSTOMER</label>
          <select class="form-select" id="nivisQuotationCustomer">
            <option value="all">All customers</option>
            <option value="Sri Lakshmi Traders">Sri Lakshmi Traders</option>
            <option value="Sree Agencies">Sree Agencies</option>
            <option value="Ravi Enterprises">Ravi Enterprises</option>
            <option value="Glow &amp; Co. Beauty Store">Glow &amp; Co. Beauty Store</option>
          </select>
        </div>
        <div class="col-12 col-md-4">
          <label for="nivisQuotationStatus">STATUS</label>
          <select class="form-select" id="nivisQuotationStatus">
            <option value="all">All statuses</option>
            <option value="Draft">Saved</option>
            <option value="Sent">Sent</option>
            <option value="Approved">Accepted</option><option value="Rejected">Rejected</option><option value="Expired">Expired</option>
            <option value="Converted">Converted</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Quotation Table -->
    <div class="nivis-labs-quotation-table-card">
      <h2 class="nivis-labs-quotation-table-title">Beauty product quotations</h2>

      <div class="table-responsive">
        <table class="table nivis-labs-quotation-table mb-0">

          <thead>
            <tr>
              <th>Quote #</th>
              <th>Customer</th>
              <th>Beauty products</th>
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
        No quotations match these filters.
      </div>

    </div>



  </div>
</section>
<!-- Quotations Section End -->

<!-- Convert Confirmation Modal -->
<div class="modal fade"
     id="nivisQuotationViewModal"
     tabindex="-1"
     aria-labelledby="nivisQuotationViewTitle"
     aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content nivis-labs-quotation-modal">
      <div class="modal-header">
        <h5 class="modal-title" id="nivisQuotationViewTitle">Quotation details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"
                aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <dl class="nivis-labs-quotation-view-details mb-0">
          <dt>Quote number</dt><dd id="nivisQuotationViewNumber"></dd>
          <dt>Beauty retailer</dt><dd id="nivisQuotationViewCustomer"></dd>
          <dt>Products</dt><dd id="nivisQuotationViewProducts"></dd>
          <dt>Quote date</dt><dd id="nivisQuotationViewDate"></dd>
          <dt>Quote total</dt><dd id="nivisQuotationViewTotal"></dd>
          <dt>Status</dt><dd id="nivisQuotationViewStatus"></dd>
        </dl>
      </div>
    </div>
  </div>
</div>

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


<?php include 'footer.php'; ?>
