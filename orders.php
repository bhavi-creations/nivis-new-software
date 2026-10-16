<?php include_once __DIR__.'/header.php'; include __DIR__.'/sidebar.php'; ?>
<section class="nivis-labs-quotation"><div class="container-fluid p-0">
  <div class="nivis-labs-quotation-header"><div><h1>Orders</h1><p>Every saved order appears in Quotations. Convert it to an invoice when needed.</p></div></div>
  <?php if(isset($_GET['saved'])): ?><p class="alert alert-success" role="status">Order saved. Its quotation is ready.</p><?php endif; ?>
  <div class="nivis-labs-quotation-toolbar"><a href="create-order.php" class="nivis-labs-quotation-new-btn">+ New order</a></div>
  <div class="nivis-labs-quotation-filters"><label for="orderSearch">SEARCH ORDER / CUSTOMER / PRODUCT</label><input type="search" id="orderSearch" class="form-control" placeholder="Search orders"></div>
  <div class="nivis-labs-quotation-table-card"><div class="table-responsive"><table class="table nivis-labs-quotation-table"><thead><tr><th>Order #</th><th>Quotation #</th><th>Customer</th><th>Products</th><th>Total</th><th>Status</th><th>Actions</th></tr></thead><tbody id="orderTable"></tbody></table></div><p id="orderMessage" role="status">Loading orders...</p></div>
</div></section>
<script src="assets/js/orders.js"></script>
<?php include __DIR__.'/footer.php'; ?>
