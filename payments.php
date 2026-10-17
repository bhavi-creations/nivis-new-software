<?php include_once 'header.php'; include 'sidebar.php'; ?>
<section class="nivis-labs-reports"><div class="container-fluid p-0">
  <div class="nlr-header"><div><h1>Payments</h1><p>Select a customer and invoice to see purchased products, paid amounts and balances.</p></div></div>
  <div class="nlr-filter-card row g-3">
    <div class="col-md-6"><label for="payCustomer">Customer</label><select id="payCustomer" class="form-select"><option value="">Select customer</option></select><input id="paySearch" type="search" class="form-control mt-2" placeholder="Search customer"><div id="payMatches" class="list-group"></div></div>
    <div class="col-md-6"><label for="payInvoice">Invoice</label><select id="payInvoice" class="form-select"><option value="">Select invoice</option></select><a id="payView" class="btn btn-outline-primary mt-2" hidden>View invoice / PDF</a></div>
  </div>
  <p id="payMessage" role="status"></p><h2 id="paySummary" class="h5"></h2>
  <div id="payAdvances" class="nlr-report-card" hidden><h2 class="h5">Advances awaiting invoice conversion</h2><p>These amounts are saved on orders/quotations. They become invoice payments on conversion.</p><div class="table-responsive"><table class="table"><thead><tr><th>Order / Quotation</th><th>Date</th><th>Advance</th><th>Method</th><th>View</th></tr></thead><tbody id="payAdvanceRows"></tbody></table></div></div>
  <div class="nlr-report-card table-responsive"><table class="table"><thead><tr><th>Product</th><th>Quantity</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th></tr></thead><tbody id="payProducts"></tbody></table></div>
  <form id="payForm" class="nlr-filter-card row g-3" hidden>
    <div class="col-md-4"><label for="payProduct">Payment for</label><select id="payProduct" class="form-select"></select></div>
    <div class="col-md-3"><label for="payDate">Payment date</label><input id="payDate" type="date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
    <div class="col-md-3"><label for="payAmount">Amount (Rs)</label><input id="payAmount" type="number" class="form-control" min="0.01" step="0.01" required></div>
    <div class="col-md-2"><label for="payMethod">Method</label><select id="payMethod" class="form-select"><option value="cash">Cash</option><option value="upi">UPI</option><option value="bank_transfer">Bank transfer</option><option value="cheque">Cheque</option><option value="card">Card</option><option value="other">Other</option></select></div>
    <div class="col-md-8"><label for="payReference">Transaction reference / notes</label><input id="payReference" class="form-control" maxlength="160"></div>
    <div class="col-md-4 d-flex align-items-end gap-2"><button class="btn btn-primary" id="paySave" type="submit">Save payment</button><button id="payCancelEdit" type="button" class="btn btn-outline-secondary" hidden>Cancel edit</button></div>
    <p>Choose a product to pay for that product only. “All unpaid products” applies the amount in invoice line order. Advances and earlier invoice-level receipts also follow line order.</p>
  </form>
  <div class="nlr-report-card table-responsive"><h2 class="h5">Payment history</h2><table class="table"><thead><tr><th>Customer</th><th>Date</th><th>Amount</th><th>Refunded</th><th>Method</th><th>Products credited</th><th>Reference / notes</th><th>Actions</th></tr></thead><tbody id="payHistory"></tbody></table></div>
  <dialog id="payReceipt" class="rounded border p-4"><h2 class="h5">Payment details</h2><div id="payReceiptDetails" style="white-space:pre-wrap"></div><form method="dialog"><button class="btn btn-secondary mt-3">Close</button></form></dialog>
</div></section>
<script src="assets/js/payments.js?v=2-actions"></script>
<?php include 'footer.php'; ?>
