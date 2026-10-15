
<?php
// Common sidebar - include only once.
// If already included in your main layout, remove this line.
include_once 'header.php';
include 'sidebar.php';
?>

<section class="nivis-produdcts" id="nivis-produdcts">
  <div class="container-fluid p-0">

    <!-- Page Header -->
    <div class="nivis-produdcts-header">
      <div>
        <h1>Products</h1>
        <p>Manage product costs, quantities and availability</p>
      </div>

      <div class="nivis-produdcts-account">
        Admin <span>|</span> AP Business
      </div>
    </div>

    <!-- Add Product Button -->
    <div class="nivis-produdcts-toolbar">
      <a href="categories.php" class="btn btn-outline-primary me-2">+ Add category</a>
      <button type="button"
              class="nivis-produdcts-add-btn"
              id="nivisProductAddBtn">
        + Add product
      </button>
    </div>

    <!-- Product Filters -->
    <div class="nivis-produdcts-filters">
      <div class="row g-3">
        <div class="col-12 col-md-5">
          <label for="nivisProductSearch">
            SEARCH PRODUCT
          </label>

          <input type="search"
                 class="form-control"
                 id="nivisProductSearch"
                 placeholder="Name / SKU / HSN">
        </div>

        <div class="col-12 col-md-4">
          <label for="nivisProductCategoryFilter">
            CATEGORY
          </label>

          <select class="form-select"
                  id="nivisProductCategoryFilter">
            <option value="all">All categories</option>
          </select>
        </div>

        <div class="col-12 col-md-3">
          <label for="nivisProductGSTFilter">
            GST RATE
          </label>

          <select class="form-select"
                  id="nivisProductGSTFilter">
            <option value="all">All GST rates</option>
            <option value="0">0%</option>
            <option value="5">5%</option>
            <option value="12">12%</option>
            <option value="18">18%</option>
            <option value="28">28%</option>
          </select>
        </div>

      </div>
    </div>

    <!-- Product Directory -->
    <div class="nivis-produdcts-table-card">
      <h2 class="nivis-produdcts-table-title">
        <i class="fa-solid fa-spa" aria-hidden="true"></i>
        Product catalogue
      </h2>
      <div class="table-responsive">
        <table class="table nivis-produdcts-table mb-0">
          <thead>
            <tr>
              <th>S.No</th><th>Photo</th>
              <th>Product name</th>
              <th>Category</th>
              <th>Cost</th>
              <th>Quantity</th>
              <th>Availability</th>
              <th>Actions</th>
            </tr>
          </thead>

          <tbody id="nivisProductTableBody">
          </tbody>
        </table>
      </div>

      <div class="nivis-produdcts-empty d-none"
           id="nivisProductEmpty">
        No products found.
      </div>
    </div>



  </div>
</section>

<!-- Add / Edit Product Modal -->
<div class="modal fade" id="nivisProductModal"
     tabindex="-1" aria-hidden="true"
     aria-labelledby="nivisProductModalTitle">

  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content nivis-produdcts-modal">

      <div class="modal-header">
        <h5 class="modal-title" id="nivisProductModalTitle">
          Add product
        </h5>

        <button type="button" class="btn-close"
                data-bs-dismiss="modal"
                aria-label="Close"></button>
      </div>

      <form id="nivisProductForm">
        <div class="px-3 pt-3"><img id="productImagePreview" class="product-photo-preview" alt="Product photo" hidden></div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6"><label for="productSerial">S.NO</label><input id="productSerial" class="form-control" readonly></div>
            <div class="col-md-6"><label for="productQuantity">QUANTITY</label><input id="productQuantity" class="form-control" readonly></div>
            <div class="col-md-6"><label for="productRegion">GST REGION</label><input id="productRegion" class="form-control" readonly></div>
            <div class="col-md-6"><label for="productTotal">TOTAL</label><input id="productTotal" class="form-control" readonly><small id="productTaxSplit"></small></div>



            <div class="col-12 col-md-6">
              <label class="form-label" for="productName">
                PRODUCT NAME *
              </label>
              <input type="text" class="form-control"
                     id="productName" required>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label" for="productSKU">
                PRODUCT SKU *
              </label>
              <input type="text" class="form-control"
                     id="productSKU" required>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label" for="productCategory">
                CATEGORY *
              </label>
              <select class="form-select"
                      id="productCategory" required>
                <option value="">Select category</option>
              </select>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label" for="productHSN">
                HSN CODE *
              </label>
              <input type="text" class="form-control"
                     id="productHSN" maxlength="8"
                     pattern="[0-9]{4,8}" required>
            </div>

            <div class="col-12 col-md-4">
              <label class="form-label" for="productUnit">
                UNIT *
              </label>
              <select class="form-select"
                      id="productUnit" required>
                <option value="PCS">Pcs</option>
                <option value="BOX">Box</option>
                <option value="KG">Kg</option>
                <option value="METER">Meter</option>
                <option value="SET">Set</option>
                <option value="LITER">Liter</option>
              </select>
            </div>

            <div class="col-12 col-md-4">
              <label class="form-label" for="productPrice">
                COST *
              </label>
              <input type="number" class="form-control"
                     id="productPrice" min="0"
                     step="0.01" required>
            </div>

            <div class="col-12 col-md-4">
              <label class="form-label" for="productGST">
                GST RATE *
              </label>
              <select class="form-select"
                      id="productGST" required>
              </select>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label" for="productStatus">
                STATUS
              </label>
              <select class="form-select" id="productStatus">
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
              </select>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label" for="productDiscount">
                DEFAULT DISCOUNT
              </label>
              <input type="number" class="form-control"
                     id="productDiscount" min="0" max="100"
                     step="0.01" value="0">
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label" for="productDescription">
                DESCRIPTION
              </label>
              <input type="text" class="form-control"
                     id="productDescription">
            </div>

          </div>

          <div id="nivisProductMessage"
               class="nivis-produdcts-message mt-3"
               role="status"></div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light"
                  data-bs-dismiss="modal">
            Cancel
          </button>

          <button type="submit"
                  class="btn nivis-produdcts-save-btn">
            Save product
          </button>
        </div>
      </form>

    </div>
  </div>
</div>

<!-- Include Bootstrap JS only if not included globally -->


<?php include 'footer.php'; ?>
