
<?php
// Common sidebar - include only once.
// Remove this line if already included in parent layout.
include 'sidebar.php';
?>

<!-- Invoice Page Start -->
<section class="nivis-labs-invoice" id="nivis-labs-invoice">
  <div class="container-fluid p-0">

    <!-- Page Header -->
    <div class="nivis-labs-invoice-header">
      <div>
        <h1>Invoices</h1>
        <p>Beauty-care orders: sunscreen, moisturizers and skin-care essentials</p>
      </div>

      <div class="nivis-labs-invoice-account">
        Admin <span>|</span> AP Business
      </div>
    </div>

    <!-- New Invoice -->
    <div class="nivis-labs-invoice-toolbar">
      <a href="quotations.php"
         class="nivis-labs-invoice-add-btn">
        + Convert approved quotation
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
            <option value="all">All customers</option>
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
            <option value="Paid">Paid</option>
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

    <!-- Developer Notes -->
    <div class="nivis-labs-invoice-notes">
      <h5>SCREEN RULES / DEVELOPER NOTES</h5>

      <p>• Auto-number invoices independently of quotation numbers; lock issued invoices.</p>
      <p>• Invoice fields: date, due date, customer, supply state, items, tax, total.</p>
      <p>• Track payments/receipts; outstanding = invoice total less net payments.</p>

      <span class="nivis-labs-invoice-page">11</span>
    </div>

  </div>
</section>
<!-- Invoice Page End -->
