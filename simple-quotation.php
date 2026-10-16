<section class="nivis-labs-creat-quotation" id="simpleQuotation">
  <div class="container-fluid p-0">
    <div class="nlcq-header"><div><h1>New quotation</h1><p>Select a customer, add products and save.</p></div></div>
    <form id="simpleQuotationForm">
      <div class="nlcq-card">
        <label for="simpleQuoteCustomer" class="nlcq-label">CUSTOMER NAME</label>
        <select id="simpleQuoteCustomer" class="form-select" required><option value="">Select customer</option></select>
        <label for="simpleQuoteSearch" class="nlcq-label mt-3">SEARCH CUSTOMER</label>
        <input type="search" id="simpleQuoteSearch" class="form-control" placeholder="Type a letter or name" autocomplete="off">
        <div id="simpleQuoteMatches" class="list-group mt-1" style="max-height:200px;overflow:auto"></div>
      </div>
      <div class="nlcq-card">
        <label for="simpleQuoteRegion" class="nlcq-label">GST TYPE</label>
        <select id="simpleQuoteRegion" class="form-select">
          <option value="inside">Inside GST (CGST + SGST)</option>
          <option value="outside">Outside GST (IGST)</option>
        </select>
        <div id="simpleQuoteOutsideField" class="mt-3" hidden>
          <label for="simpleQuoteSupplyState" class="nlcq-label">OUTSIDE STATE</label>
          <select id="simpleQuoteSupplyState" class="form-select"><option value="">Select state</option></select>
        </div>
      </div>
      <div class="nlcq-card">
        <div class="table-responsive"><table class="table nlcq-items-table"><thead><tr><th>Product name</th><th>Quantity</th><th>Price</th><th>Tax</th><th>Total</th><th></th></tr></thead><tbody id="simpleQuoteLines"></tbody></table></div>
        <button type="button" class="nlcq-add-line" id="simpleQuoteAdd">+ Add product</button>
      </div>
      <div class="nlcq-card">
        <p id="simpleQuoteTaxMode" class="text-muted"></p>
        <div class="nlcq-totals"><div><span>Tax</span><strong id="simpleQuoteTax">Rs 0.00</strong></div><div class="nlcq-grand-total"><span>Total</span><strong id="simpleQuoteTotal">Rs 0.00</strong></div></div>
        <div class="nlcq-actions"><a href="quotations.php" class="nlcq-btn nlcq-btn-outline">Cancel</a><button type="submit" id="simpleQuoteSave" class="nlcq-btn nlcq-btn-primary" disabled>Save quotation</button></div>
        <p id="simpleQuoteMessage" role="status">Loading customers and products...</p>
      </div>
    </form>
  </div>
</section>
<script src="assets/js/simple-quotation.js"></script>
