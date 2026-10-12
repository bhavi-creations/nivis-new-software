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
        <p>Live business overview and shortcuts</p>
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
          <h2 id="nivisTotalCustomers">1,248</h2>
          <p>+ 32 this month</p>
        </div>
      </div>

      <div class="col-12 col-sm-6 col-xl-3">
        <div class="nivis_dashboard_stat_card">
          <h6>Total Products</h6>
          <h2 id="nivisTotalProducts">386</h2>
          <p>12 categories</p>
        </div>
      </div>

      <div class="col-12 col-sm-6 col-xl-3">
        <div class="nivis_dashboard_stat_card">
          <h6>Quotations</h6>
          <h2 id="nivisTotalQuotations">84</h2>
          <p>11 waiting</p>
        </div>
      </div>

      <div class="col-12 col-sm-6 col-xl-3">
        <div class="nivis_dashboard_stat_card">
          <h6>Invoice Value</h6>
          <h2 id="nivisInvoiceValue">Rs 8,42,500</h2>
          <p>This month</p>
        </div>
      </div>

    </div>

    <!-- Sales Chart & Quick Actions -->
    <div class="row g-3 nivis_dashboard_middle">

      <div class="col-12 col-lg-8">
        <div class="nivis_dashboard_panel nivis_dashboard_sales">

          <h3>Sales overview</h3>
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

          <h3>Quick actions</h3>

          <div class="nivis_dashboard_action_list">
            <a href="quotations.php?action=create"
               class="nivis_dashboard_action_primary">
              + Create quotation
            </a>

            <a href="invoices.php?action=create"
               class="nivis_dashboard_action_btn">
              + New invoice
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

      <h3>Recent activity</h3>

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

          <tbody>
            <tr>
              <td>QT-0025</td>
              <td>Sri Lakshmi Traders</td>
              <td>Rs 22,000</td>
              <td>Sent</td>
            </tr>

            <tr>
              <td>INV-0031</td>
              <td>Sree Agencies</td>
              <td>Rs 14,868</td>
              <td>Paid</td>
            </tr>
          </tbody>
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

  if (!canvas || typeof Chart === "undefined") return;

  new Chart(canvas, {
    type: "bar",

    data: {
      labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug"],

      datasets: [{
        data: [35, 52, 42, 70, 61, 86, 75, 103],
        backgroundColor: [
          "#AEC2FF", "#AEC2FF", "#AEC2FF", "#AEC2FF",
          "#AEC2FF", "#AEC2FF", "#AEC2FF", "#345CF6"
        ],
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
            label: function(context) {
              return "Demo value: " + context.parsed.y;
            }
          }
        }
      },

      scales: {
        x: {
          grid: { display: false },
          border: { display: false },
          ticks: {
            color: "#8292AE",
            font: { size: 10 }
          }
        },

        y: {
          display: false,
          beginAtZero: true,
          grid: { display: false }
        }
      }
    }
  });
});
</script>

<?php include 'footer.php' ; ?>