
<?php
// Include sidebar only once.
// Remove if already loaded by your common layout.
include_once 'header.php';
include 'sidebar.php';
?>

<section class="nivis-labs-reports" id="nivis-labs-reports">
  <div class="container-fluid p-0">
    <?php include __DIR__ . "/billing-guide.php"; ?>

    <!-- Page Header -->
    <div class="nlr-header">
      <div>
        <h1>Reports</h1>
        <p>Review and export existing records by date. Quotations show estimates; invoices show bills. Reports do not create new bills.</p>
      </div>

      <div class="nlr-account">
        Admin <span>|</span> AP Business
      </div>
    </div>

    <p class="text-muted">Summary totals below cover issued invoices and payments in the selected period.</p>
    <!-- Report Filters -->
    <div class="nlr-filter-card">
      <form id="nlrFilterForm" class="row g-3 align-items-end">

        <div class="col-12 col-md-6 col-xl-4">
          <label for="nlrFromDate">FROM DATE</label>
          <input type="date"
                 class="form-control"
                 id="nlrFromDate"
                 value="2026-10-01"
                 required>
        </div>

        <div class="col-12 col-md-6 col-xl-4">
          <label for="nlrToDate">TO DATE</label>
          <input type="date"
                 class="form-control"
                 id="nlrToDate"
                 value="2026-10-31"
                 required>
        </div>

        <div class="col-12 col-md-8 col-xl-2">
          <label for="nlrReportType">REPORT TYPE</label>
          <select class="form-select" id="nlrReportType">
            <option value="invoice">Invoices</option>
            <option value="sales">Issued invoice sales</option>
            <option value="gst">GST Summary</option>
            <option value="quotation">Quotations</option>
            <option value="payments">Payments</option>
            <option value="outstanding">Outstanding</option>
            <option value="customers">Customers</option>
            <option value="products">Products</option>
          </select>
        </div>

        <div class="col-12 col-md-4 col-xl-2">
          <button type="submit" class="nlr-apply-btn">
            Apply
          </button>
        </div>

      </form>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 nlr-summary-row">

      <div class="col-12 col-sm-6 col-lg-4">
        <div class="nlr-summary-card">
          <h6>INVOICED SALES</h6>
          <h2 id="nlrInvoicedSales">Rs 0</h2>
        </div>
      </div>

      <div class="col-12 col-sm-6 col-lg-4">
        <div class="nlr-summary-card">
          <h6>COLLECTED</h6>
          <h2 id="nlrCollected">Rs 0</h2>
        </div>
      </div>

      <div class="col-12 col-sm-12 col-lg-4">
        <div class="nlr-summary-card">
          <h6>BALANCE DUE (ISSUED INVOICES)</h6>
          <h2 id="nlrOutstanding">Rs 0</h2>
        </div>
      </div>

    </div>

    <!-- Report Preview -->
    <div class="nlr-report-card">

      <div class="nlr-report-heading">
        <h2 id="nlrReportTitle">Invoices</h2>

        <button type="button" class="nlr-export-btn" id="nlrPrint">Print / Save PDF</button>
        <button type="button"
                class="nlr-export-btn"
                id="nlrExportCSV">
          Export CSV
        </button>
      </div>

      <div class="table-responsive">
        <table class="table nlr-report-table mb-0">
          <thead id="nlrReportTableHead"></thead>
          <tbody id="nlrReportTableBody"></tbody>
        </table>
      </div>

      <div id="nlrEmptyMessage" class="nlr-empty d-none">
        No report data found for the selected period.
      </div>
    </div>



  </div>
</section>

<script src="assets/js/nivis-labs-reports.js"></script>

<?php include 'footer.php'; ?>
