
<?php
// Common sidebar - include once only
include_once 'header.php';
include 'sidebar.php';
?>

<section class="add-edit-product" id="add-edit-product">
  <div class="container-fluid p-0">

    <!-- Page Header -->
    <div class="add-edit-product-header">
      <div>
        <h1>Add / Edit Product</h1>
        <p>Enter a product and its available quantity</p>
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
          <div class="col-12">
            <label for="editProductImage" class="form-label">PRODUCT PHOTO</label>
            <input type="file" id="editProductImage" class="form-control" accept="image/*">
            <small>All image formats, maximum 50 MB. Preview depends on browser support. Select a new photo to replace the current one.</small>
            <div class="mt-2"><img id="editProductImagePreview" class="product-photo-preview" alt="Product photo preview" hidden></div>
          </div>
          <div class="col-md-4"><label for="editProductSerial" class="form-label">S.NO</label><input id="editProductSerial" class="form-control" value="Assigned when saved" readonly></div>
          <div class="col-md-4"><label for="editProductQuantity" class="form-label">QUANTITY *</label><input id="editProductQuantity" class="form-control" type="number" min="0" max="1000000" step="0.001" value="1" required><small>Enter 0 when not available.</small></div>

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
          <!-- Category -->
          <div class="mt-3">
            <label for="editProductCategory" class="form-label">
              CATEGORY *
            </label>
            <select class="form-select"
                    id="editProductCategory"
                    name="category_id"
                    required>
              <option value="">Select category</option>
            </select>
            <div class="mt-2"><a href="categories.php" class="btn btn-outline-primary btn-sm">+ Add a category first</a></div>
          </div>

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
              COST *
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

          <div class="col-md-4"><label for="editProductRegion" class="form-label">GST REGION</label><select id="editProductRegion" class="form-select"><option value="inside_ap">Inside AP (CGST + SGST)</option><option value="outside_ap">Outside AP (IGST)</option></select><small>Default for product preview. Documents use their place of supply.</small></div>
          <div class="col-md-4"><label for="editProductTotal" class="form-label">TOTAL</label><input id="editProductTotal" class="form-control" readonly><small id="editProductTaxSplit"></small></div>
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



  </div>
</section>

<?php include 'footer.php'; ?>
