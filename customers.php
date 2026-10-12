
<?php
// customers.php
// Common sidebar included only once.
include_once 'header.php';
include 'sidebar.php';
?>

<section class="nivis_customer" id="nivis_customer">
  <div class="container-fluid p-0">

    <!-- Page Header -->
    <div class="nivis_customer_header">
      <div class="nivis_customer_heading">
        <h1>Customers</h1>
        <p>Beauty retailers, billing identity and delivery details</p>
      </div>
      <div class="nivis_customer_account">
        Admin <span>|</span> AP Business
      </div>
    </div>

    <!-- Add Customer -->
    <div class="nivis_customer_toolbar">
      <button type="button" class="nivis_customer_add_btn"
              data-bs-toggle="modal" data-bs-target="#nivisCustomerModal"
              id="nivisCustomerAddBtn">
        + Add customer
      </button>
    </div>

    <!-- Search and Filter -->
    <div class="nivis_customer_filters">
      <div class="row g-3 align-items-end">

        <div class="col-12 col-md-5">
          <label for="nivisCustomerSearch">SEARCH</label>
          <input type="search" class="form-control"
                 id="nivisCustomerSearch"
                 placeholder="Search name / phone / GSTIN">
        </div>

        <div class="col-12 col-md-3">
          <label for="nivisCustomerType">CUSTOMER TYPE</label>
          <select class="form-select" id="nivisCustomerType">
            <option value="all">All customers</option>
            <option value="registered">GST Registered</option>
            <option value="unregistered">Unregistered</option>
          </select>
        </div>

        <div class="col-12 col-md-2">
          <button type="button" class="nivis_customer_filter_btn"
                  id="nivisCustomerFilterBtn">
            Filter
          </button>
        </div>

      </div>
    </div>

    <!-- Customer Table -->
    <div class="nivis_customer_table_card">
      <h2 class="nivis_customer_table_title">
        <i class="fa-solid fa-address-book" aria-hidden="true"></i>
        Beauty customer directory
      </h2>
      <div class="table-responsive">
        <table class="table nivis_customer_table mb-0">
          <thead>
            <tr>
              <th>Customer</th>
              <th>Mobile</th>
              <th>GSTIN</th>
              <th>State</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="nivisCustomerTableBody"></tbody>
        </table>
      </div>
      <p class="nivis_customer_empty d-none" id="nivisCustomerEmpty">
        No customers found.
      </p>
    </div>



  </div>
</section>

<!-- Add / Edit / View Customer Modal -->
<div class="modal fade" id="nivisCustomerModal" tabindex="-1"
     aria-labelledby="nivisCustomerModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content nivis_customer_modal">
      <div class="modal-header">
        <h5 class="modal-title" id="nivisCustomerModalTitle">Add customer</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"
                aria-label="Close"></button>
      </div>
      <form id="nivisCustomerForm">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Customer name *</label>
              <input type="text" class="form-control" name="customer_name" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Mobile number *</label>
              <input type="tel" class="form-control" name="mobile"
                     inputmode="numeric" pattern="[0-9]{10}"
                     maxlength="10" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Email address</label>
              <input type="email" class="form-control" name="email">
            </div>
            <div class="col-md-6">
              <label class="form-label">Customer type *</label>
              <select class="form-select" name="customer_type" required>
                <option value="registered">GST Registered</option>
                <option value="unregistered">Unregistered</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">GSTIN</label>
              <input type="text" class="form-control text-uppercase"
                     name="gstin" maxlength="15"
                     placeholder="Enter GSTIN">
              <div class="invalid-feedback">Enter a valid 15-character GSTIN.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">State *</label>
              <select class="form-select" name="state" required>
                <option value="">Select state</option>
                <option value="Andhra Pradesh">Andhra Pradesh</option>
                <option value="Karnataka">Karnataka</option>
                <option value="Telangana">Telangana</option>
                <option value="Tamil Nadu">Tamil Nadu</option>
                <option value="Maharashtra">Maharashtra</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Billing address</label>
              <textarea class="form-control" name="billing_address" rows="2"></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label">City</label>
              <input type="text" class="form-control" name="city">
            </div>
            <div class="col-md-6">
              <label class="form-label">Pincode</label>
              <input type="text" class="form-control" name="pincode"
                     inputmode="numeric" pattern="[0-9]{6}" maxlength="6">
            </div>
            <div class="col-12">
              <label class="form-label">Shipping address</label>
              <textarea class="form-control" name="shipping_address" rows="2"></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label">Shipping city</label>
              <input type="text" class="form-control" name="shipping_city">
            </div>
            <div class="col-md-6">
              <label class="form-label">Shipping pincode</label>
              <input type="text" class="form-control" name="shipping_pincode"
                     inputmode="numeric" pattern="[0-9]{6}" maxlength="6">
            </div>
          </div>
          <p class="nivis_customer_form_message mt-3 mb-0"
             id="nivisCustomerFormMessage" role="status"></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light"
                  data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"
                  id="nivisCustomerSaveBtn">Save customer</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Bootstrap JS: include once if not already in common layout -->


<?php include 'footer.php'; ?>
