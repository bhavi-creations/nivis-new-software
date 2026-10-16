
<?php
// Common sidebar - include only once.
// Remove this line if already included in parent layout.
include_once 'header.php';
include 'sidebar.php';
?>

<!-- Invoice Page Start -->
<section class="nivis-labs-invoice" id="nivis-labs-invoice">
  <div class="container-fluid p-0">
    <?php if (isset($_GET['converted'])): ?>
      <p class="alert alert-success" role="status">Quotation converted successfully. Your invoice is listed below.</p>
    <?php endif; ?>


    <!-- Page Header -->
    <div class="nivis-labs-invoice-header">
      <div>
        <h1>Invoices</h1>
        <p>Add an invoice directly or convert a saved order / quotation. View and save it as an A4 PDF.</p>
      </div>

      <div class="nivis-labs-invoice-account">
        Admin <span>|</span> AP Business
      </div>
    </div>

    <!-- New Invoice -->
    <div class="nivis-labs-invoice-toolbar">
      <a href="create-invoice.php" class="nivis-labs-invoice-add-btn">+ Direct invoice</a>
      <a href="quotations.php"
         class="nivis-labs-invoice-add-btn">
        From quotation
      </a>
    </div>

    <!-- Invoice Filters -->
    <div class="nivis-labs-invoice-filters">
      <div class="row g-3">

        <div class="col-12 col-md-4">
          <label for="nivisInvoiceSearch">SEARCH INVOICE / PRODUCT</label>
          <input type="search"
                 class="form-control"
                 id="nivisInvoiceSearch"
                 placeholder="Invoice number or sunscreen">
        </div>

        <div class="col-12 col-md-4">
          <label for="nivisInvoiceCustomer">CUSTOMER</label>
          <select class="form-select"
                  id="nivisInvoiceCustomer">
            <option value="all" selected>All customers</option>
            <option value="Sri Lakshmi Traders">Sri Lakshmi Traders</option>
            <option value="Ravi Enterprises">Ravi Enterprises</option>
            <option value="Sree Agencies">Sree Agencies</option>
            <option value="Glow &amp; Co. Beauty Store">Glow &amp; Co. Beauty Store</option>
          </select>
        </div>

        <div class="col-12 col-md-4">
          <label for="nivisInvoiceStatus">PAYMENT STATUS</label>
          <select class="form-select"
                  id="nivisInvoiceStatus">
            <option value="all">All statuses</option>
            <option value="Draft">Draft</option><option value="Cancelled">Cancelled</option><option value="Paid">Paid</option>
            <option value="Part-paid">Part-paid</option>
            <option value="Unpaid">Unpaid</option>
          </select>
        </div>

      </div>
    </div>

    <!-- Invoice Table -->
    <div class="nivis-labs-invoice-table-card">
      <div class="table-responsive">
        <table class="table nivis-labs-invoice-table mb-0">
          <thead>
            <tr>
              <th>Invoice #</th>
              <th>Quotation #</th>
              <th>Customer</th>
              <th>Products</th>
              <th>Total</th>
              <th>Paid</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>

          <tbody id="nivisInvoiceTableBody">
            <!-- JavaScript renders invoice records -->
          </tbody>
        </table>
      </div>

      <div class="nivis-labs-invoice-empty d-none"
           id="nivisInvoiceEmpty">
        No invoices found.
      </div>
    </div>



  </div>
</section>
<!-- Invoice Page End -->

<?php include 'footer.php'; ?>
