<?php include 'header.php'; include 'sidebar.php'; ?>
<section class="tax-gst"><h1>Company settings</h1><p>Company details used for new invoices.</p>
<form id="companySettings" class="row g-3">
<div class="col-md-6"><label for="company_company_name" class="form-label">Company name</label><input class="form-control" id="company_company_name" name="company_name" maxlength="180" required ></div>
<div class="col-md-6"><label for="company_gstin" class="form-label">GSTIN (optional)</label><input class="form-control" id="company_gstin" name="gstin" maxlength="15"  ></div>
<div class="col-md-6"><label for="company_email" class="form-label">Email (optional)</label><input class="form-control" id="company_email" name="email" maxlength="190"  type="email"></div>
<div class="col-md-6"><label for="company_phone" class="form-label">Phone (optional)</label><input class="form-control" id="company_phone" name="phone" maxlength="20"  ></div>
<div class="col-12"><label for="company_address" class="form-label">Address</label><textarea id="company_address" name="address" class="form-control" maxlength="5000" required></textarea></div>
<div class="col-md-6"><label for="company_state" class="form-label">State</label><select id="company_state" name="state_name" class="form-select"><option>Andhra Pradesh</option><option>Telangana</option><option>Karnataka</option><option>Tamil Nadu</option><option>Maharashtra</option><option>Kerala</option><option>Odisha</option><option>Delhi</option></select></div>
<div class="col-12"><h2 class="h5">Document defaults</h2><p>Saved with each new document; existing documents keep their original details.</p></div>
<div class="col-md-6"><label for="company_terms">terms</label><textarea class="form-control" id="company_terms" name="terms" maxlength="5000"></textarea></div>
<div class="col-md-6"><label for="company_payment_terms">payment terms</label><textarea class="form-control" id="company_payment_terms" name="payment_terms" maxlength="5000"></textarea></div>
<div class="col-md-6"><label for="company_delivery_timeline">delivery timeline</label><textarea class="form-control" id="company_delivery_timeline" name="delivery_timeline" maxlength="5000"></textarea></div>
<div class="col-md-6"><label for="company_bank_details">bank details</label><textarea class="form-control" id="company_bank_details" name="bank_details" maxlength="5000"></textarea></div>
<div class="col-md-6"><label for="company_payment_instructions">payment instructions</label><textarea class="form-control" id="company_payment_instructions" name="payment_instructions" maxlength="5000"></textarea></div>
<div class="col-md-6"><label for="company_signatory">signatory</label><textarea class="form-control" id="company_signatory" name="signatory" maxlength="5000"></textarea></div>
<div><a href="tax.php">Manage GST rates and price mode</a></div>
<div><button class="btn btn-primary" type="submit" disabled>Save company settings</button></div><p id="companyMessage" role="status"></p></form></section>
<?php include 'footer.php'; ?>