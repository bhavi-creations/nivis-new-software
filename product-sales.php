<?php include_once __DIR__.'/header.php'; include __DIR__.'/sidebar.php'; ?>
<section class="nivis-produdcts"><div class="container-fluid p-0">
  <div class="nivis-produdcts-header"><div><h1>Product sales</h1><p id="stockSummary">Loading...</p></div><a href="products.php" class="btn btn-outline-primary">Back to products</a></div>
  <p>Sales are recorded when invoices are issued. Cancelled invoices are excluded.</p>
  <div class="table-responsive"><table class="table"><thead><tr><th>Customer</th><th>Quantity</th><th>Invoice date</th><th>Issued at</th><th>Invoice</th></tr></thead><tbody id="productSalesBody"></tbody></table></div>
  <p id="productSalesMessage" role="status"></p>
</div></section>
<script src="assets/js/product-sales.js"></script>
<?php include __DIR__.'/footer.php'; ?>
