
<?php
// Common sidebar - include once only
include 'sidebar.php';
?>

<section class="add-edit-product" id="add-edit-product">
  <div class="container-fluid p-0">

    <!-- Page Header -->
    <div class="add-edit-product-header">
      <div>
        <h1>Add / Edit Product</h1>
        <p>Product master holds reusable defaults for quotations</p>
      </div>

      <div class="add-edit-product-account">
        Admin <span>|</span> AP Business
      </div>
    </div>

    <!-- Product Form -->
    <div class="add-edit-product-card">

      <h2>Product details</h2>

      <form id="addEditProductForm" method="post">

        <input type="hidden" name="product_id" id="editProductId">

        <div class="row g-4">

          <!-- Product Name -->
          <div class="col-12 col-md-6 col-xl-4">
            <label for="editProductName" class="form-label">
              PRODUCT NAME *
            </label>
            <input type="text"
                   class="form-control"
                   id="editProductName"
                   name="product_name"
                   placeholder="Daily Shield SPF 50 Sunscreen"
                   maxlength="150"
                   required>
          </div>

          <!-- SKU -->
          <div class="col-12 col-md-6 col-xl-4">
            <label for="editProductSKU" class="form-label">
              SKU / PRODUCT CODE
            </label>
            <input type="text"
                   class="form-control"
                   id="editProductSKU"
                   name="sku"
                   placeholder="SUN-050"
                   maxlength="50">
          </div>

          <!-- Category -->
          <div class="col-12 col-md-6 col-xl-4">
            <label for="editProductCategory" class="form-label">
              CATEGORY *
            </label>
            <select class="form-select"
                    id="editProductCategory"
                    name="category_id"
                    required>
              <option value="">Select category</option>
            </select>
          </div>

          <!-- HSN -->
          <div class="col-12 col-md-6 col-xl-4">
            <label for="editProductHSN" class="form-label">
              HSN CODE *
            </label>
            <input type="text"
                   class="form-control"
                   id="editProductHSN"
                   name="hsn_code"
                   placeholder="330499"
                   inputmode="numeric"
                   pattern="[0-9]{4,8}"
                   maxlength="8"
                   required>
          </div>

          <!-- Unit -->
          <div class="col-12 col-md-6 col-xl-4">
            <label for="editProductUnit" class="form-label">
              UNIT *
            </label>
            <select class="form-select"
                    id="editProductUnit"
                    name="unit"
                    required>
              <option value="PCS">PCS</option>
              <option value="BOX">BOX</option>
              <option value="KG">KG</option>
              <option value="METER">METER</option>
              <option value="SET">SET</option>
              <option value="LITER">LITER</option>
            </select>
          </div>

          <!-- Selling Price -->
          <div class="col-12 col-md-6 col-xl-4">
            <label for="editProductPrice" class="form-label">
              SELLING PRICE *
            </label>
            <input type="number"
                   class="form-control"
                   id="editProductPrice"
                   name="selling_price"
                   placeholder="799.00"
                   min="0"
                   step="0.01"
                   required>
          </div>

          <!-- GST -->
          <div class="col-12 col-md-6 col-xl-4">
            <label for="editProductGST" class="form-label">
              GST RATE *
            </label>
            <select class="form-select"
                    id="editProductGST"
                    name="gst_rate"
                    required>
              <option value="0">0%</option>
              <option value="5">5%</option>
              <option value="12">12%</option>
              <option value="18" selected>18%</option>
              <option value="28">28%</option>
            </select>
          </div>

          <!-- Discount -->
          <div class="col-12 col-md-6 col-xl-4">
            <label for="editProductDiscount" class="form-label">
              DEFAULT DISCOUNT
            </label>
            <div class="add-edit-product-discount">
              <input type="number"
                     class="form-control"
                     id="editProductDiscount"
                     name="default_discount"
                     min="0"
                     max="100"
                     step="0.01"
                     value="0">
              <span>%</span>
            </div>
          </div>

          <!-- Status -->
          <div class="col-12 col-md-6 col-xl-4">
            <label for="editProductStatus" class="form-label">
              STATUS
            </label>
            <select class="form-select"
                    id="editProductStatus"
                    name="status">
              <option value="Active">Active</option>
              <option value="Inactive">Inactive</option>
            </select>
          </div>

          <!-- Description -->
          <div class="col-12">
            <label for="editProductDescription" class="form-label">
              PRODUCT DESCRIPTION
            </label>
            <input type="text"
                   class="form-control"
                   id="editProductDescription"
                   name="description"
                   placeholder="Daily skin-care product details"
                   maxlength="500">
          </div>

        </div>

        <div id="addEditProductMessage"
             class="add-edit-product-message"
             role="status"></div>

        <!-- Form Actions -->
        <div class="add-edit-product-actions">
          <a href="products.php"
             class="add-edit-product-cancel">
            Cancel
          </a>

          <button type="submit"
                  class="add-edit-product-save"
                  id="addEditProductSave">
            Save product
          </button>
        </div>

      </form>
    </div>

    <!-- Developer Notes -->
    <div class="add-edit-product-notes">
      <h5>SCREEN RULES / DEVELOPER NOTES</h5>

      <p>• Quotation line additionally needs S.No, quantity, price, GST, discount and total.</p>
      <p>• HSN and GST should be validated against current applicable classifications.</p>
      <p>• Retain item snapshot on documents if product master is edited later.</p>

      <span class="add-edit-product-page">08</span>
    </div>

  </div>
</section>
