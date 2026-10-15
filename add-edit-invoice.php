
<?php
// Include common sidebar only once.
// Remove this if your main layout already includes it.
include_once 'header.php';
include 'sidebar.php';
?>

<section class="nivis-labs-invoice-detail-preview" id="nivisInvoicePreview">
  <div class="container-fluid p-0">

    <!-- Page Header -->
    <div class="nlidp-header">
      <div>
        <h1>Invoice Detail / Preview</h1>
        <p>Review invoice details, due dates and payments</p>
      </div>

      <div class="nlidp-account">
        Admin <span>|</span> AP Business
      </div>
    </div>

    <!-- Invoice Main Card -->
    <div class="nlidp-invoice-card" id="nlidpPrintableInvoice">

      <!-- Invoice Heading -->
      <div class="nlidp-invoice-heading">
        <h2>TAX INVOICE <small id="nlidpDocumentStatus"></small></h2>

        <div class="nlidp-invoice-meta">
          <strong id="nlidpInvoiceNumber">—</strong>
          <span>|</span>
          <strong id="nlidpInvoiceDate">—</strong>
        </div>
      </div>

      <!-- Seller & Buyer Details -->
      <div class="row g-3 nlidp-parties">

        <div class="col-12 col-md-6">
          <h4><i class="fa-solid fa-store" aria-hidden="true"></i> FROM <span>|</span> YOUR BEAUTY BUSINESS</h4>
          <p id="nlidpSellerName">—</p>
          <p id="nlidpSellerDetails">—</p>

          <div class="nlidp-linked-quote">
            <a href="quotations.php?quote_id=26" id="nlidpQuotationLink">
              —
            </a>
          </div>
        </div>

        <div class="col-12 col-md-6">
          <h4><i class="fa-solid fa-user" aria-hidden="true"></i> BILL TO</h4>
          <p id="nlidpCustomerName">—</p>
          

          <div class="nlidp-supply-state" id="nlidpSupplyState">
            Place of supply: Andhra Pradesh
          </div>
        </div>

      </div>

      <!-- Invoice Items -->
      <div class="nlidp-items">
        <div class="table-responsive">
          <table class="table nlidp-table mb-0">
            <thead>
              <tr>
                <th>S.No</th><th>Product</th>
                <th>HSN</th>
                <th>Qty</th>
                <th>Rate</th>
                <th>Discount</th>
                <th>Taxable</th>
                <th>GST</th><th>Total</th>
              </tr>
            </thead>

            <tbody id="nlidpInvoiceItems">
              <!-- JavaScript renders invoice items -->
            </tbody>
          </table>
        </div>
      </div>

      <!-- Invoice Totals -->
      <div class="nlidp-totals">
        <div>
          <span>Taxable value:</span>
          <span id="nlidpTaxable">Rs 0.00</span>
        </div>

        <div id="nlidpCgstRow">
          <span id="nlidpCgstLabel">CGST:</span>
          <span id="nlidpCgst">Rs 0.00</span>
        </div>

        <div id="nlidpSgstRow">
          <span id="nlidpSgstLabel">SGST:</span>
          <span id="nlidpSgst">Rs 0.00</span>
        </div>

        <div id="nlidpIgstRow" class="d-none">
          <span id="nlidpIgstLabel">IGST:</span>
          <span id="nlidpIgst">Rs 0.00</span>
        </div>

        <div class="nlidp-grand-total">
          <strong>TOTAL:</strong>
          <strong id="nlidpGrandTotal">Rs 0.00</strong>
        </div>
      </div>

      <div class="nlidp-totals"><div>Paid: <span id="nlidpPaid">Rs 0.00</span></div><div>Outstanding: <span id="nlidpBalance">Rs 0.00</span></div></div>
      <p>Due date: <strong id="invoiceSavedDue"></strong></p><p id="invoiceSavedNotes"></p>
      <form id="invoiceDraftEditor" hidden data-html2canvas-ignore="true" class="row g-3 my-3">
        <h3>Edit draft details</h3>
        <p>Update the due date and notes here. Product lines come from the approved quotation. Issued invoices are read-only.</p>
        <div class="col-md-4"><label for="invoiceDueDate">Due date</label><input type="date" id="invoiceDueDate" class="form-control"></div>
        <div class="col-md-8"><label for="invoiceNotes">Notes</label><textarea id="invoiceNotes" class="form-control" maxlength="5000"></textarea></div>
        <div><button class="btn btn-primary" type="submit">Save invoice details</button></div>
      </form>
      <!-- Action Buttons -->
      <div class="nlidp-actions" data-html2canvas-ignore="true">
        <button type="button"
                class="nlidp-btn nlidp-btn-primary"
                id="nlidpDownloadPDF">
          Download PDF
        </button>

        <button type="button"
                class="nlidp-btn nlidp-btn-outline"
                id="nlidpRecordPayment"
                data-bs-toggle="modal"
                data-bs-target="#nlidpPaymentModal">
          Record payment
        </button>

        <button type="button"
                class="nlidp-btn nlidp-btn-outline"
                id="nlidpSendInvoice">
          Send invoice
        </button>
      </div>

      <div class="nlidp-feedback" id="nlidpFeedback" role="status"></div>
    </div>



  </div>
</section>

<!-- Record Payment Modal -->
<div class="modal fade" id="nlidpPaymentModal"
     tabindex="-1" aria-hidden="true"
     aria-labelledby="nlidpPaymentTitle">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content nlidp-payment-modal">

      <div class="modal-header">
        <h5 class="modal-title" id="nlidpPaymentTitle">
          Record payment
        </h5>
        <button type="button" class="btn-close"
                data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <form id="nlidpPaymentForm">
        <div class="modal-body">

          <div class="mb-3">
            <label class="form-label" for="nlidpPaymentAmount">
              PAYMENT AMOUNT *
            </label>
            <input type="number" class="form-control"
                   id="nlidpPaymentAmount" min="0.01"
                   step="0.01" required>
          </div>

          <div class="mb-3">
            <label class="form-label" for="nlidpPaymentDate">
              PAYMENT DATE *
            </label>
            <input type="date" class="form-control"
                   id="nlidpPaymentDate" required>
          </div>

          <div class="mb-3">
            <label class="form-label" for="nlidpPaymentMethod">
              PAYMENT METHOD *
            </label>
            <select class="form-select" id="nlidpPaymentMethod" required>
              <option value="">Select payment method</option>
              <option value="Cash">Cash</option>
              <option value="UPI">UPI</option>
              <option value="Bank Transfer">Bank Transfer</option>
              <option value="Cheque">Cheque</option>
              <option value="Card">Card</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label" for="nlidpPaymentReference">
              TRANSACTION REFERENCE
            </label>
            <input type="text" class="form-control"
                   id="nlidpPaymentReference">
          </div>

          <div class="nlidp-payment-balance">
            Outstanding: <strong id="nlidpOutstanding">Rs 0.00</strong>
          </div>

          <div id="nlidpPaymentMessage" role="status"
               class="nlidp-payment-message"></div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light"
                  data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn nlidp-save-payment">
            Save payment
          </button>
        </div>
      </form>

    </div>
  </div>
</div>

<!-- Bootstrap Bundle - load once in your project -->


<!-- html2pdf for client-side demo PDF export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<?php include 'footer.php'; ?>
