

  <!-- Mobile Sidebar Button -->
  <button class="side-bar-toggle" id="sidebarToggle"
          type="button" aria-label="Open sidebar"
          aria-controls="mainSidebar" aria-expanded="false">
    <i class="fa-solid fa-bars"></i>
  </button>

  <!-- Sidebar Overlay -->
  <div class="side-bar-overlay" id="sidebarOverlay"></div>

  <!-- Sidebar Start -->
  <aside class="side-bar" id="mainSidebar">

    <!-- Logo Section -->
    <a href="dashboard.php" class="sidebar-brand">
      <!-- <div class="brand-icon">N</div> -->
      <!-- <div class="brand-icon"> -->
        <img src="./assets/img/logo.png" alt="logo" style="width: 50px; height:50px;">
      <!-- </div> -->
      <div>
        <div class="brand-name">NIVIS LABS</div>
        <div class="brand-subtitle">INTERNATIONAL</div>
      </div>
    </a>

    <!-- Sidebar Menu -->
    <nav class="sidebar-nav" aria-label="Main navigation">

      <a href="dashboard.php" class="nav-link">
        <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
        Dashboard
      </a>

      <a href="customers.php" class="nav-link active">
        <i class="fa-solid fa-users" aria-hidden="true"></i>
        Customers
      </a>

       <a href="add-edit-customer.php" class="nav-link">
        <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
        Add/Edit Customer
      </a>

      <a href="categories.php" class="nav-link">
        <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
        Categories
      </a>

      <a href="tax.php" class="nav-link">
        <i class="fa-solid fa-percent" aria-hidden="true"></i>
        Tax &amp; GST
      </a>

      <a href="products.php" class="nav-link">
        <i class="fa-solid fa-box" aria-hidden="true"></i>
        Products
      </a>

      <a href="orders.php" class="nav-link">
        <i class="fa-solid fa-cart-shopping" aria-hidden="true"></i>
        Orders
      </a>

      <a href="quotations.php" class="nav-link">
        <i class="fa-solid fa-file-lines" aria-hidden="true"></i>
        Quotations
      </a>

      <a href="invoices.php" class="nav-link">
        <i class="fa-solid fa-file-invoice" aria-hidden="true"></i>
        Invoices
      </a>

      <a href="payments.php" class="nav-link"><i class="fa-solid fa-money-bill" aria-hidden="true"></i>Payments</a>
      <a href="reports.php" class="nav-link">
        <i class="fa-solid fa-chart-column" aria-hidden="true"></i>
        Reports
      </a>

      <a href="settings.php" class="nav-link">
        <i class="fa-solid fa-gear" aria-hidden="true"></i>
        Settings
      </a>

    </nav>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
      NIVIS LABS ? BILLING
      <?php if (!empty($_SESSION['user_id'])): ?>
      <form method="post" action="logout.php" class="mt-2">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit" class="btn btn-sm btn-outline-secondary">Sign out</button>
      </form>
      <?php endif; ?>
    </div>

  </aside>
  <!-- Sidebar End -->

  <!-- Page Content -->
  <!-- <main class="main-content">
    <h4>Customers</h4>
    <p class="text-secondary">Customer management page</p>
  </main> -->


  

