<?php include_once __DIR__.'/header.php'; include __DIR__.'/sidebar.php'; ?>
<section class="nivis-produdcts"><div class="container-fluid p-0">
  <div class="nivis-produdcts-header"><div><h1>Product sales</h1><p id="stockSummary">Loading...</p></div><a href="products.php" class="btn btn-outline-primary">Back to products</a></div>
  <p>Catalogue availability includes both issued and draft invoice quantities. Issuing a draft does not deduct it twice. Cancelled and deleted invoices release their quantities.</p>
  <div class="table-responsive"><table class="table"><thead><tr><th>Customer</th><th>Quantity</th><th>Invoice date</th><th>Issued at</th><th>Invoice</th></tr></thead><tbody id="productSalesBody"></tbody></table></div>
  <p id="productSalesMessage" role="status"></p>
  <h2>Draft invoices — included in catalogue quantities</h2>
  <div class="table-responsive"><table class="table"><thead><tr><th>Customer</th><th>Quantity</th><th>Date</th><th>Action</th></tr></thead><tbody id="productPendingBody"></tbody></table></div>
</div></section>
<script src="assets/js/product-sales.js?v=3-invoiced"></script>
<?php include __DIR__.'/footer.php'; ?>
