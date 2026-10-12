
<?php
// tax.php
// Include the common sidebar only once.
include 'header.php';
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
            <option value="intra">Intra-state (CGST + SGST)</option>
            <option value="inter">Inter-state (IGST)</option>
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

    <!-- GST Rates -->
    <div class="tax-gst-card tax-gst-rates">
      <h2><i class="fa-solid fa-table-list" aria-hidden="true"></i> Available rates - illustration</h2>

      <div class="table-responsive">
        <table class="table tax-gst-table mb-0">
          <thead>
            <tr>
              <th>Total GST</th>
              <th>Inside AP (CGST + SGST)</th>
              <th>Outside AP (IGST)</th>
              <th>Status</th>
            </tr>
          </thead>

          <tbody id="taxRatesTableBody"></tbody>
        </table>
      </div>
    </div>

    <!-- Developer Notes -->
    <div class="tax-gst-notes">
      <h5>SCREEN RULES / DEVELOPER NOTES</h5>

      <p>• Use legally applicable GST rate for each HSN; do not infer tax solely from state.</p>
      <p>• Tax split depends on place of supply, transaction type and GST rules.</p>
      <p>• Support exempt, nil-rated and other applicable treatments where required.</p>

      <span class="tax-gst-page">06</span>
    </div>

  </div>
</section>
