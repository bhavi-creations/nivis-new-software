
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

      <input type="hidden" id="taxSellerState" value="37">
      <label for="taxPriceMode">PRICE MODE</label><select id="taxPriceMode" class="form-select mb-3"><option value="exclusive">Exclusive of GST</option><option value="inclusive">Inclusive of GST</option></select><p id="taxSupplierSummary"></p><a href="settings.php">Update registered business state in Settings</a>
      <div class="tax-gst-fields">
        <label for="taxDefaultMode">GST REGION</label>
        <select id="taxDefaultMode" class="form-select">
          <option value="inside_ap">Inside AP</option>
          <option value="outside_ap">Outside AP</option>
        </select>
        <p id="taxRegionDescription" class="mt-3 fw-bold" aria-live="polite"></p>
        <small>Default for new products and documents. A selected customer's place of supply determines document GST.</small>
      </div>
      <button type="button" class="btn btn-primary mt-3" id="taxSaveSettings">
        Save tax settings
      </button>
      <p class="mt-2 mb-0" id="taxSettingsMessage" role="status"></p>


    </div>

    <div class="tax-gst-card">
      <h2 id="taxRateHeading">Add GST rate</h2><p>Use the applicable HSN rate and effective date. Inside AP splits GST equally into CGST and SGST; outside AP uses IGST for ordinary domestic sales by an AP supplier.</p>
      <form id="taxRateForm" class="row g-3">
        <input type="hidden" id="taxRateId"><div class="col-md-6"><label for="taxRateHsn">HSN/SAC classifications (comma-separated, optional)</label><input id="taxRateHsn" class="form-control" placeholder="Configured classification codes"></div><div class="col-md-3"><label for="taxRateEnabled">Status</label><select id="taxRateEnabled" class="form-select"><option value="1">Active</option><option value="0">Inactive</option></select></div>
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
