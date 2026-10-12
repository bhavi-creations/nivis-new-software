
<?php
// Common sidebar
include_once 'header.php';
include 'sidebar.php';
?>

<section class="add-edit-customer">
  <div class="container-fluid p-0">

    <!-- Page Header -->
    <div class="add-edit-customer-header">
      <div>
        <h1>Add / Edit Customer</h1>
        <p>Manage beauty retailers and their billing and delivery details</p>
      </div>

      <div class="add-edit-customer-account">
        Admin <span>|</span> AP Business
      </div>
    </div>

    <!-- Customer Form -->
    <div class="add-edit-customer-card">

      <form id="customerForm" action="#" method="post">

        <!-- Basic Information -->
        <h3><i class="fa-solid fa-user-tag" aria-hidden="true"></i> Basic information</h3>

        <div class="row g-3">

          <div class="col-12 col-md-4">
            <label for="customerName" class="form-label">
              CUSTOMER NAME *
            </label>
            <input type="text" class="form-control"
                   id="customerName" name="customer_name"
                   placeholder="Enter customer name" required>
          </div>

          <div class="col-12 col-md-4">
            <label for="customerMobile" class="form-label">
              MOBILE NUMBER *
            </label>
            <input type="tel" class="form-control"
                   id="customerMobile" name="mobile"
                   placeholder="9876543210"
                   pattern="[0-9]{10}" maxlength="10" required>
          </div>

          <div class="col-12 col-md-4">
            <label for="customerEmail" class="form-label">
              EMAIL
            </label>
            <input type="email" class="form-control"
                   id="customerEmail" name="email"
                   placeholder="accounts@example.com">
          </div>

          <div class="col-12 col-md-4">
            <label for="customerType" class="form-label">
              CUSTOMER TYPE
            </label>
            <select class="form-select" id="customerType"
                    name="customer_type">
              <option value="business">Business</option>
              <option value="individual">Individual</option>
              <option value="unregistered">Unregistered</option>
            </select>
          </div>

          <div class="col-12 col-md-4">
            <label for="customerGSTIN" class="form-label">
              GSTIN
            </label>
            <input type="text" class="form-control text-uppercase"
                   id="customerGSTIN" name="gstin"
                   maxlength="15" placeholder="Enter GSTIN">
          </div>

          <div class="col-12 col-md-4">
            <label for="customerState" class="form-label">
              STATE *
            </label>
            <select class="form-select" id="customerState"
                    name="state" required>
              <option value="">Select State</option>
              <option value="Andhra Pradesh">Andhra Pradesh</option>
              <option value="Telangana">Telangana</option>
              <option value="Karnataka">Karnataka</option>
              <option value="Tamil Nadu">Tamil Nadu</option>
              <option value="Maharashtra">Maharashtra</option>
              <option value="Kerala">Kerala</option>
              <option value="Odisha">Odisha</option>
              <option value="Delhi">Delhi</option>
            </select>
          </div>

        </div>

        <!-- Billing Address -->
        <h3 class="add-edit-customer-subheading">
          <i class="fa-solid fa-location-dot" aria-hidden="true"></i> Billing address
        </h3>

        <div class="row g-3">

          <div class="col-12 col-md-8">
            <label for="billingAddress" class="form-label">
              ADDRESS LINE 1 *
            </label>
            <input type="text" class="form-control"
                   id="billingAddress" name="billing_address"
                   placeholder="Door no, street, locality" required>
          </div>

          <div class="col-12 col-md-4">
            <label for="customerPincode" class="form-label">
              PINCODE *
            </label>
            <input type="text" class="form-control"
                   id="customerPincode" name="pincode"
                   placeholder="533001"
                   inputmode="numeric"
                   pattern="[0-9]{6}" maxlength="6" required>
          </div>

          <div class="col-12 col-md-4">
            <label for="customerCity" class="form-label">
              CITY *
            </label>
            <input type="text" class="form-control"
                   id="customerCity" name="city"
                   placeholder="Kakinada" required>
          </div>

          <div class="col-12 col-md-4">
            <label for="customerCountry" class="form-label">
              COUNTRY
            </label>
            <input type="text" class="form-control"
                   id="customerCountry" name="country"
                   value="India" readonly>
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label">SHIPPING ADDRESS</label>

            <div class="add-edit-customer-shipping">
              <input type="checkbox" id="sameBilling"
                     name="same_as_billing" checked>
              <label for="sameBilling">Same as billing</label>
            </div>
          </div>

        </div>

        <!-- Separate Shipping Address -->
        <div id="shippingAddressFields" class="d-none mt-3">
          <h3><i class="fa-solid fa-truck-fast" aria-hidden="true"></i> Shipping address</h3>

          <div class="row g-3">
            <div class="col-12 col-md-8">
              <label for="shippingAddress" class="form-label">
                SHIPPING ADDRESS *
              </label>
              <input type="text" class="form-control"
                     id="shippingAddress" name="shipping_address"
                     placeholder="Door no, street, locality">
            </div>

            <div class="col-12 col-md-4">
              <label for="shippingPincode" class="form-label">
                SHIPPING PINCODE *
              </label>
              <input type="text" class="form-control"
                     id="shippingPincode" name="shipping_pincode"
                     pattern="[0-9]{6}" maxlength="6">
            </div>

            <div class="col-12 col-md-6">
              <label for="shippingCity" class="form-label">
                SHIPPING CITY *
              </label>
              <input type="text" class="form-control"
                     id="shippingCity" name="shipping_city">
            </div>

            <div class="col-12 col-md-6">
              <label for="shippingState" class="form-label">
                SHIPPING STATE *
              </label>
              <select class="form-select" id="shippingState"
                      name="shipping_state">
                <option value="">Select State</option>
                <option>Andhra Pradesh</option>
                <option>Telangana</option>
                <option>Karnataka</option>
                <option>Tamil Nadu</option>
                <option>Maharashtra</option>
                <option>Kerala</option>
                <option>Odisha</option>
                <option>Delhi</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Validation Message -->
        <div id="customerFormMessage" role="status"
             class="add-edit-customer-message"></div>

        <!-- Form Buttons -->
        <div class="add-edit-customer-actions">
          <a href="customers.php"
             class="add-edit-customer-cancel">
            Cancel
          </a>

          <button type="submit"
                  class="add-edit-customer-save">
            Save customer
          </button>
        </div>

      </form>
    </div>



  </div>
</section>

<?php include 'footer.php'; ?>
