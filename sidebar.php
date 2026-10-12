<?php include 'header.php' ; ?>

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
      <div class="brand-icon">N</div>
      <div>
        <div class="brand-name">NIVIS LABS</div>
        <div class="brand-subtitle">INTERNATIONAL</div>
      </div>
    </a>

    <!-- Sidebar Menu -->
    <nav class="sidebar-nav" aria-label="Main navigation">

      <a href="dashboard.php" class="nav-link">
        Dashboard
      </a>

      <a href="customers.php" class="nav-link active">
        Customers
      </a>

       <a href="add-edit-customer.php" class="nav-link">
        Add/Edit Customer
      </a>

      <a href="categories.php" class="nav-link">
        Categories
      </a>

      <a href="tax.php" class="nav-link">
        Tax &amp; GST
      </a>

      <a href="products.php" class="nav-link">
        Products
      </a>

      <a href="quotations.php" class="nav-link">
        Quotations
      </a>

      <a href="invoices.php" class="nav-link">
        Invoices
      </a>

      <a href="reports.php" class="nav-link">
        Reports
      </a>

      <a href="settings.php" class="nav-link">
        Settings
      </a>

    </nav>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
      UI/UX DESIGN SYSTEM
    </div>

  </aside>
  <!-- Sidebar End -->

  <!-- Page Content -->
  <!-- <main class="main-content">
    <h4>Customers</h4>
    <p class="text-secondary">Customer management page</p>
  </main> -->


  <?php include 'footer.php' ; ?>

