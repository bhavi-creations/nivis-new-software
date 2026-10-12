
<?php
// Common sidebar - include once
include 'header.php';
include 'sidebar.php';
?>

<section class="nivis_labs_category" id="nivis_labs_category">
  <div class="container-fluid p-0">

    <!-- Page Header -->
    <div class="nivis_labs_category_header">
      <div>
        <h1>Categories</h1>
        <p>Organize sunscreen, moisturizers, serums and cleanser ranges</p>
      </div>

      <div class="nivis_labs_category_account">
        Admin <span>|</span> AP Business
      </div>
    </div>

    <!-- Main Layout -->
    <div class="row g-3 nivis_labs_category_content">

      <!-- Left: Category Directory -->
      <div class="col-12 col-lg-7">
        <div class="nivis_labs_category_card">

          <div class="nivis_labs_category_card_header">
            <h2><i class="fa-solid fa-layer-group" aria-hidden="true"></i> Category directory</h2>

            <button type="button"
                    class="nivis_labs_category_new_btn"
                    id="nivisCategoryNewBtn">
              + New category
            </button>
          </div>

          <div class="table-responsive">
            <table class="table nivis_labs_category_table mb-0">
              <thead>
                <tr>
                  <th>Category</th>
                  <th>Products</th>
                  <th>Status</th>
                </tr>
              </thead>

              <tbody id="nivisCategoryTableBody">
                <tr data-id="1">
                  <td>Sun Care</td>
                  <td>24</td>
                  <td>Active</td>
                </tr>

                <tr data-id="2">
                  <td>Moisturizers</td>
                  <td>38</td>
                  <td>Active</td>
                </tr>

                <tr data-id="3">
                  <td>Serums</td>
                  <td>21</td>
                  <td>Active</td>
                </tr>

                <tr data-id="4">
                  <td>Cleansers</td>
                  <td>17</td>
                  <td>Active</td>
                </tr>
              </tbody>
            </table>
          </div>

          <p class="nivis_labs_category_empty d-none"
             id="nivisCategoryEmpty">
            No categories available.
          </p>

        </div>
      </div>

      <!-- Right: Add / Edit Category -->
      <div class="col-12 col-lg-5">
        <div class="nivis_labs_category_card nivis_labs_category_form_card">

          <h2><i class="fa-solid fa-folder-plus" aria-hidden="true"></i> Add / Edit category</h2>

          <form id="nivisCategoryForm" novalidate>

            <input type="hidden"
                   id="nivisCategoryId"
                   name="category_id">

            <div class="nivis_labs_category_field">
              <label for="nivisCategoryName">
                CATEGORY NAME *
              </label>

              <input type="text"
                     class="form-control"
                     id="nivisCategoryName"
                     name="category_name"
                     placeholder="Sun Care"
                     maxlength="100"
                     required>
            </div>

            <div class="nivis_labs_category_field">
              <label for="nivisCategoryCode">
                CATEGORY CODE
              </label>

              <input type="text"
                     class="form-control"
                     id="nivisCategoryCode"
                     name="category_code"
                     placeholder="SUN"
                     maxlength="20">
            </div>

            <div class="nivis_labs_category_field">
              <label for="nivisCategoryDescription">
                DESCRIPTION
              </label>

              <input type="text"
                     class="form-control"
                     id="nivisCategoryDescription"
                     name="description"
                     placeholder="Daily sunscreen and sun-protection products"
                     maxlength="255">
            </div>

            <div class="nivis_labs_category_field">
              <label for="nivisCategoryStatus">
                STATUS
              </label>

              <select class="form-select"
                      id="nivisCategoryStatus"
                      name="status">
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
              </select>
            </div>

            <div id="nivisCategoryMessage"
                 class="nivis_labs_category_message"
                 role="status"></div>

            <div class="nivis_labs_category_actions">
              <button type="submit"
                      class="nivis_labs_category_save_btn"
                      id="nivisCategorySaveBtn">
                Save category
              </button>
            </div>

          </form>

        </div>
      </div>

    </div>

    <!-- Developer Notes -->
    <div class="nivis_labs_category_notes">
      <h5>SCREEN RULES / DEVELOPER NOTES</h5>

      <p>• Category fields: ID, name, code, description, status, created date.</p>
      <p>• One product belongs to a category; products dropdown uses active categories.</p>
      <p>• Block hard deletion of categories already linked to products.</p>

      <span class="nivis_labs_category_page">05</span>
    </div>

  </div>
</section>
