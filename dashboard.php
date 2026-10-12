<?php
include 'header.php';?>

<?php

// dashboard.php
// Include your existing sidebar file in the main layout.
include 'sidebar.php';
?>

<!-- Dashboard Section Start -->
<section class="nivis_dashboard" id="nivis_dashboard">
  <div class="container-fluid">

    <!-- Dashboard Heading -->
    <div class="nivis_dashboard_header">
      <div>
        <h1>Dashboard</h1>
        <p>Beauty business overview, customer activity and quick actions</p>
      </div>

      <div class="nivis_dashboard_account">
        Admin <span>|</span> AP Business
      </div>
    </div>

    <!-- Dashboard KPI Cards -->
    <div class="row g-3 nivis_dashboard_stats">

      <div class="col-12 col-sm-6 col-xl-3">
        <div class="nivis_dashboard_stat_card">
          <h6>Total Customers</h6>
          <h2 id="nivisTotalCustomers">0</h2>
          <p>Active beauty retailers</p>
        </div>
      </div>

      <div class="col-12 col-sm-6 col-xl-3">
        <div class="nivis_dashboard_stat_card">
          <h6>Total Products</h6>
          <h2 id="nivisTotalProducts">0</h2>
          <p>Active skin-care products</p>
        </div>
      </div>

      <div class="col-12 col-sm-6 col-xl-3">
        <div class="nivis_dashboard_stat_card">
          <h6>Quotations</h6>
          <h2 id="nivisTotalQuotations">0</h2>
          <p id="nivisWaitingQuotes">0 waiting</p>
        </div>
      </div>

      <div class="col-12 col-sm-6 col-xl-3">
        <div class="nivis_dashboard_stat_card">
          <h6>Invoice Value</h6>
          <h2 id="nivisInvoiceValue">Rs 0</h2>
          <p>This month</p>
        </div>
      </div>

    </div>

    <!-- Sales Chart & Quick Actions -->
    <div class="row g-3 nivis_dashboard_middle">

      <div class="col-12 col-lg-8">
        <div class="nivis_dashboard_panel nivis_dashboard_sales">

          <h3><i class="fa-solid fa-chart-column" aria-hidden="true"></i> Sales overview</h3>
          <p class="nivis_dashboard_subtitle">
            Monthly invoice totals
          </p>

          <div class="nivis_dashboard_chart">
            <canvas id="nivisSalesChart"></canvas>
          </div>

        </div>
      </div>

      <div class="col-12 col-lg-4">
        <div class="nivis_dashboard_panel nivis_dashboard_actions">

          <h3><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> Quick actions</h3>

          <div class="nivis_dashboard_action_list">
            <a href="create-quotation.php"
               class="nivis_dashboard_action_primary">
              + Create quotation
            </a>

            <a href="invoices.php"
               class="nivis_dashboard_action_btn">
              + View invoices
            </a>

            <a href="customers.php?action=create"
               class="nivis_dashboard_action_btn">
              + Add customer
            </a>

            <a href="products.php?action=create"
               class="nivis_dashboard_action_btn">
              + Add product
            </a>
          </div>

        </div>
      </div>

    </div>

    <!-- Recent Activity -->
    <div class="nivis_dashboard_panel nivis_dashboard_recent">

      <h3><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Recent activity</h3>

      <div class="table-responsive">
        <table class="table nivis_dashboard_table mb-0">
          <thead>
            <tr>
              <th>Document</th>
              <th>Customer</th>
              <th>Amount</th>
              <th>Status</th>
            </tr>
          </thead>

          <tbody id="nivisDashboardRecent"></tbody>
        </table>
      </div>

    </div>

    <!-- Developer Notes -->
    <div class="nivis_dashboard_notes">
      <h5>SCREEN RULES / DEVELOPER NOTES</h5>

      <p>• KPI cards respond to date filter; count pending quotations and unpaid invoices.</p>
      <p>• Charts from approved invoices, excluding cancelled documents.</p>
      <p>• Show recent activity, quick create, search, notifications and account menu.</p>
    </div>

  </div>
</section>
<!-- Dashboard Section End -->

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
  const canvas = document.getElementById("nivisSalesChart");
  const currency = value => "Rs " + Number(value).toLocaleString("en-IN");
  fetch("api.php?resource=dashboard")
    .then(async response => {
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || "Could not load dashboard data.");
      return result;
    })
    .then(data => {
      document.getElementById("nivisTotalCustomers").textContent = data.stats.customers;
      document.getElementById("nivisTotalProducts").textContent = data.stats.products;
      document.getElementById("nivisTotalQuotations").textContent = data.stats.quotations;
      document.getElementById("nivisWaitingQuotes").textContent = data.stats.waiting + " waiting";
      document.getElementById("nivisInvoiceValue").textContent = currency(data.stats.invoice_value);

      const recent = document.getElementById("nivisDashboardRecent");
      recent.replaceChildren();
      data.recent.forEach(item => {
        const row = document.createElement("tr");
        [item.document_number, item.customer, currency(item.amount), item.status]
          .forEach(value => {
            const cell = document.createElement("td");
            cell.textContent = value;
            row.appendChild(cell);
          });
        recent.appendChild(row);
      });
      if (!data.recent.length) {
        const row = document.createElement("tr");
        const cell = document.createElement("td");
        cell.colSpan = 4;
        cell.textContent = "No recent quotations or invoices.";
        row.appendChild(cell);
        recent.appendChild(row);
      }

      if (!canvas || typeof Chart === "undefined") return;
      const monthly = data.monthly_sales;
      new Chart(canvas, {
        type: "bar",
        data: {
          labels: monthly.map(item => item.month),
          datasets: [{
            data: monthly.map(item => Number(item.total)),
            backgroundColor: monthly.map((item, index) =>
              index === monthly.length - 1 ? "#345CF6" : "#AEC2FF"
            ),
            borderRadius: 5,
            borderSkipped: false,
            maxBarThickness: 36
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: context => currency(context.parsed.y)
              }
            }
          },
          scales: {
            x: {
              grid: { display: false },
              border: { display: false },
              ticks: { color: "#8292AE", font: { size: 10 } }
            },
            y: { display: false, beginAtZero: true, grid: { display: false } }
          }
        }
      });
    })
    .catch(error => {
      const recent = document.getElementById("nivisDashboardRecent");
      const row = document.createElement("tr");
      const cell = document.createElement("td");
      cell.colSpan = 4;
      cell.textContent = error.message;
      row.appendChild(cell);
      recent.replaceChildren(row);
    });
});
</script>

<?php include 'footer.php' ; ?>