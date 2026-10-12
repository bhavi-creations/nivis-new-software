
<?php
// Include common sidebar only once.
// Remove this if your main layout already includes it.
include 'sidebar.php';
?>

<link rel="stylesheet" href="assets/css/nivis-labs-invoice-detail-preview.css">

<section class="nivis-labs-invoice-detail-preview" id="nivisInvoicePreview">
  <div class="container-fluid p-0">

    <!-- Page Header -->
    <div class="nlidp-header">
      <div>
        <h1>Invoice Detail / Preview</h1>
        <p>Quotation-linked invoice with payment records</p>
      </div>

      <div class="nlidp-account">
        Admin <span>|</span> AP Business
      </div>
    </div>

    <!-- Invoice Main Card -->
    <div class="nlidp-invoice-card" id="nlidpPrintableInvoice">

      <!-- Invoice Heading -->
      <div class="nlidp-invoice-heading">
        <h2>TAX INVOICE</h2>

        <div class="nlidp-invoice-meta">
          <strong id="nlidpInvoiceNumber">INV-0032</strong>
          <span>|</span>
          <strong id="nlidpInvoiceDate">12-Oct-2026</strong>
        </div>
      </div>

      <!-- Seller & Buyer Details -->
      <div class="row g-3 nlidp-parties">

        <div class="col-12 col-md-6">
          <h4>FROM <span>|</span> YOUR BUSINESS</h4>
          <p id="nlidpSellerName">Nivis Labs International</p>
          <p id="nlidpSellerDetails">AP-based registered seller</p>

          <div class="nlidp-linked-quote">
            <a href="view-quotation.php?id=26" id="nlidpQuotationLink">
              Linked QT-0026
            </a>
          </div>
        </div>

        <div class="col-12 col-md-6">
          <h4>BILL TO</h4>
          <p id="nlidpCustomerName">
            Sri Lakshmi Traders, Kakinada, AP
          </p>

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
                <th>Product</th>
                <th>HSN</th>
                <th>Qty</th>
                <th>Rate</th>
                <th>Discount</th>
                <th>Taxable</th>
                <th>GST</th>
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
          <span id="nlidpCgstLabel">CGST 9%:</span>
          <span id="nlidpCgst">Rs 0.00</span>
        </div>

        <div id="nlidpSgstRow">
          <span id="nlidpSgstLabel">SGST 9%:</span>
          <span id="nlidpSgst">Rs 0.00</span>
        </div>

        <div id="nlidpIgstRow" class="d-none">
          <span id="nlidpIgstLabel">IGST 18%:</span>
          <span id="nlidpIgst">Rs 0.00</span>
        </div>

        <div class="nlidp-grand-total">
          <strong>TOTAL:</strong>
          <strong id="nlidpGrandTotal">Rs 0.00</strong>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="nlidp-actions">
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

    <!-- Developer Notes -->
    <div class="nlidp-notes">
      <h5>SCREEN RULES / DEVELOPER NOTES</h5>
      <p>• Invoice must carry legally required particulars, seller/buyer GSTIN as relevant.</p>
      <p>• Cancellation, credit notes, numbering and e-invoicing need legal configuration.</p>
      <p>• Amounts above illustrate intra-state 18% and two-decimal tax rounding.</p>
      <span>12</span>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- html2pdf for client-side demo PDF export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script src="assets/js/nivis-labs-invoice-detail-preview.js"></script>
