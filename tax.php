
<?php
// tax.php
// Include the common sidebar only once.
include 'header.php';
include 'sidebar.php';
?>

<link rel="stylesheet" href="assets/css/tax-gst.css">

<section class="tax-gst" id="tax-gst">
  <div class="container-fluid p-0">

    <!-- Page Header -->
    <div class="tax-gst-header">
      <div>
        <h1>Tax &amp; GST</h1>
        <p>Configure GST rates and route intra/inter-state tax</p>
      </div>

      <div class="tax-gst-account">
        Admin <span>|</span> AP Business
      </div>
    </div>

    <!-- GST Configuration -->
    <div class="tax-gst-card tax-gst-config">
      <h2>GST configuration</h2>

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
      <h2>Available rates - illustration</h2>

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

          <tbody>
            <tr>
              <td>5%</td>
              <td>2.5% + 2.5%</td>
              <td>5%</td>
              <td>Enabled</td>
            </tr>

            <tr>
              <td>12%</td>
              <td>6% + 6%</td>
              <td>12%</td>
              <td>Enabled</td>
            </tr>

            <tr>
              <td>18%</td>
              <td>9% + 9%</td>
              <td>18%</td>
              <td>Enabled</td>
            </tr>

            <tr>
              <td>28%</td>
              <td>14% + 14%</td>
              <td>28%</td>
              <td>Enabled</td>
            </tr>
          </tbody>
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

<script src="assets/js/tax-gst.js"></script>
