
<?php
// tax.php
// Include the common sidebar only once.
include_once 'header.php';
include 'sidebar.php';
?>

<section class="tax-gst" id="tax-gst">
  <div class="container-fluid p-0">

    <!-- Page Header -->
    <div class="tax-gst-header">
      <div>
        <h1>Tax &amp; GST</h1>
        <p>Configure tax settings for beauty and skin-care product sales</p>
      </div>

      <div class="tax-gst-account">
        Admin <span>|</span> AP Business
      </div>
    </div>

    <!-- GST Configuration -->
    <div class="tax-gst-card tax-gst-config">
      <h2><i class="fa-solid fa-sliders" aria-hidden="true"></i> GST configuration</h2>

      <div class="row g-3 tax-gst-fields">

        <div class="col-12 col-md-4">
          <label for="taxSellerState">SELLER STATE</label>
          <select class="form-select" id="taxSellerState">
            <option value="37" selected>Andhra Pradesh</option>
            <option value="36">Telangana</option>
            <option value="29">Karnataka</option>
            <option value="33">Tamil Nadu</option>
            <option value="27">Maharashtra</option>
          </select>
        </div>
        <div class="col-12 col-md-4">
          <label for="taxDefaultMode">DEFAULT TAX MODE</label>
          <select class="form-select" id="taxDefaultMode">
            <option value="auto">Auto by place of supply</option>
            
          </select>
        </div>
        <div class="col-12 col-md-4">
          <label for="taxPriceMode">DEFAULT PRICE MODE</label>
          <select class="form-select" id="taxPriceMode">
            <option value="exclusive">Exclusive of GST</option>
            <option value="inclusive">Inclusive of GST</option>
          </select>
        </div>

      </div>
      <button type="button" class="btn btn-primary mt-3" id="taxSaveSettings">
        Save tax settings
      </button>
      <p class="mt-2 mb-0" id="taxSettingsMessage" role="status"></p>

      <!-- GST Mode Indicators -->
      <div class="row g-3 tax-gst-indicators">
        <div class="col-12 col-md-4">
          <div class="tax-gst-inside">
            Inside AP: CGST + SGST
          </div>
        </div>

        <div class="col-12 col-md-4">
          <div class="tax-gst-outside">
            Outside AP: IGST
          </div>
        </div>
      </div>

    </div>

    <div class="tax-gst-card">
      <h2 id="taxRateHeading">Add GST rate</h2><p>Use the applicable HSN rate and effective date. Inside AP splits GST equally into CGST and SGST; outside AP uses IGST for ordinary domestic sales by an AP supplier.</p>
      <form id="taxRateForm" class="row g-3">
        <input type="hidden" id="taxRateId">
        <div class="col-md-4"><label for="taxRateName">Rate name</label><input id="taxRateName" class="form-control" maxlength="100" required></div>
        <div class="col-md-2"><label for="taxRatePercent">GST %</label><input id="taxRatePercent" class="form-control" type="number" min="0" max="100" step="0.001" required></div>
        <div class="col-md-3"><label for="taxRateTreatment">Treatment</label><select id="taxRateTreatment" class="form-select"><option value="taxable">Taxable</option><option value="nil_rated">Nil rated</option><option value="exempt">Exempt</option><option value="zero_rated">Zero rated</option><option value="non_gst">Non GST</option></select></div>
        <div class="col-md-3"><label for="taxRateDate">Effective from</label><input id="taxRateDate" class="form-control" type="date" required></div>
        <div><button class="btn btn-primary" type="submit" id="taxRateSave">Save rate</button><button type="reset" class="btn btn-light ms-2">Cancel / New</button><span id="taxRateMessage" role="status"></span></div>
      </form>
    </div>
    <!-- GST Rates -->
    <div class="tax-gst-card tax-gst-rates">
      <h2><i class="fa-solid fa-table-list" aria-hidden="true"></i> Configured GST rates</h2>

      <div class="table-responsive">
        <table class="table tax-gst-table mb-0">
          <thead>
            <tr>
              <th>Name</th><th>Total GST</th>
              <th>Inside AP (CGST + SGST)</th>
              <th>Outside AP (IGST)</th>
              <th>Status</th><th>Actions</th>
            </tr>
          </thead>

          <tbody id="taxRatesTableBody"></tbody>
        </table>
      </div>
    </div>



  </div>
</section>

<?php include 'footer.php'; ?>
