<!-- side bar script -->
<script>
    const sidebar = document.getElementById("mainSidebar");
    const toggle = document.getElementById("sidebarToggle");
    const overlay = document.getElementById("sidebarOverlay");

    function setSidebar(open) {
        sidebar.classList.toggle("show", open);
        overlay.classList.toggle("show", open);
        toggle.setAttribute("aria-expanded", String(open));
        toggle.setAttribute("aria-label",
            open ? "Close sidebar" : "Open sidebar");

        toggle.innerHTML = open ?
            '<i class="fa-solid fa-xmark"></i>' :
            '<i class="fa-solid fa-bars"></i>';
    }

    toggle.addEventListener("click", () => {
        setSidebar(!sidebar.classList.contains("show"));
    });

    overlay.addEventListener("click", () => setSidebar(false));

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") setSidebar(false);
    });

    document.querySelectorAll(".side-bar .nav-link")
        .forEach(link => {
            link.addEventListener("click", () => {
                if (window.innerWidth < 992) {
                    setSidebar(false);
                }
            });
        });

    // Automatically highlight current PHP page
    const currentPage =
        window.location.pathname.split("/").pop().toLowerCase();

    document.querySelectorAll(".side-bar .nav-link")
        .forEach(link => {
            const targetPage = new URL(link.href, window.location.href)
                .pathname.split("/").pop().toLowerCase();

            link.classList.toggle("active", targetPage === currentPage);
        });
</script>



<!-- customer page  -->
<script>
    document.addEventListener("DOMContentLoaded", function() {
        if (window.NIVIS_DATABASE_UI) return;
        const tableBody = document.getElementById("nivisCustomerTableBody");
        const searchInput = document.getElementById("nivisCustomerSearch");
        const typeFilter = document.getElementById("nivisCustomerType");
        const filterButton = document.getElementById("nivisCustomerFilterBtn");
        const addButton = document.getElementById("nivisCustomerAddBtn");
        const form = document.getElementById("nivisCustomerForm");
        const modalElement = document.getElementById("nivisCustomerModal");
        const modalTitle = document.getElementById("nivisCustomerModalTitle");
        const saveButton = document.getElementById("nivisCustomerSaveBtn");
        const message = document.getElementById("nivisCustomerFormMessage");
        const emptyMessage = document.getElementById("nivisCustomerEmpty");

        if (!tableBody || !form || !window.bootstrap) return;

        const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
        let currentRow = null;
        let currentMode = "add";

        const field = name => form.elements.namedItem(name);

        function clean(value) {
            return String(value || "").trim();
        }

        function filterCustomers() {
            const query = searchInput.value.toLowerCase().trim();
            const selectedType = typeFilter.value;
            let count = 0;

            tableBody.querySelectorAll("tr").forEach(row => {
                const cells = row.cells;
                const haystack = [
                    cells[0].textContent,
                    cells[1].textContent,
                    cells[2].textContent
                ].join(" ").toLowerCase();

                const registered =
                    cells[2].textContent.trim().toLowerCase() !== "unregistered";

                const typeMatches = selectedType === "all" ||
                    (selectedType === "registered" && registered) ||
                    (selectedType === "unregistered" && !registered);

                const visible = haystack.includes(query) && typeMatches;
                row.hidden = !visible;
                if (visible) count++;
            });

            emptyMessage.classList.toggle("d-none", count > 0);
        }

        function readRow(row) {
            return {
                name: row.cells[0].textContent.trim(),
                mobile: row.cells[1].textContent.replace(/\s/g, ""),
                gstin: row.cells[2].textContent.trim(),
                state: row.cells[3].textContent.trim()
            };
        }

        function openCustomer(mode, row = null) {
            currentMode = mode;
            currentRow = row;
            form.reset();
            form.classList.remove("was-validated");
            message.textContent = "";

            const viewOnly = mode === "view";
            modalTitle.textContent = mode === "add" ?
                "Add customer" :
                mode === "edit" ? "Edit customer" : "Customer details";

            saveButton.classList.toggle("d-none", viewOnly);
            saveButton.textContent = mode === "edit" ?
                "Update customer" : "Save customer";

            if (row) {
                const data = readRow(row);
                field("customer_name").value = data.name;
                field("mobile").value = data.mobile;
                field("gstin").value =
                    data.gstin === "Unregistered" ? "" : data.gstin;
                field("customer_type").value =
                    data.gstin === "Unregistered" ?
                    "unregistered" : "registered";
                field("state").value = data.state;

                // Additional fields are stored in frontend memory
                // on the row during this browser session.
                const extra = row.customerExtra || {};
                field("email").value = extra.email || "";
                field("billing_address").value = extra.billing || "";
                field("shipping_address").value = extra.shipping || "";
            }

            Array.from(form.elements).forEach(element => {
                if (element.matches("input, textarea, select")) {
                    element.disabled = viewOnly;
                }
            });

            modal.show();
        }

        function makeCell(text) {
            const cell = document.createElement("td");
            cell.textContent = text;
            return cell;
        }

        function createActionButtons() {
            const cell = document.createElement("td");
            cell.className = "nivis_customer_actions";

            [
                ["view", "fa-eye", "View customer"],
                ["edit", "fa-pen-to-square", "Edit customer"],
                ["delete", "fa-trash-can", "Delete customer"]
            ].forEach(([action, icon, label]) => {
                const button = document.createElement("button");
                button.type = "button";
                button.className = "nivis_customer_icon_btn action-" + action;
                button.dataset.action = action;
                button.title = label;
                button.setAttribute("aria-label", label);
                button.innerHTML =
                    '<i class="fa-solid ' + icon + '" aria-hidden="true"></i>';
                cell.appendChild(button);
            });

            return cell;
        }

        addButton.addEventListener("click", () => {
            openCustomer("add");
        });

        tableBody.addEventListener("click", event => {
            const button = event.target.closest("[data-action]");
            if (!button) return;

            const row = button.closest("tr");
            if (button.dataset.action === "delete") {
                const customerName = row.cells[0].textContent.trim();
                if (window.confirm("Delete " + customerName + "?")) {
                    row.remove();
                    filterCustomers();
                }
                return;
            }
            openCustomer(button.dataset.action, row);
        });

        searchInput.addEventListener("input", filterCustomers);
        filterButton.addEventListener("click", filterCustomers);
        typeFilter.addEventListener("change", filterCustomers);

        field("customer_type").addEventListener("change", function() {
            const gst = field("gstin");
            gst.required = this.value === "registered";
            if (this.value === "unregistered") gst.value = "";
        });

        form.addEventListener("submit", function(event) {
            event.preventDefault();

            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            const customerType = field("customer_type").value;
            const gstin = clean(field("gstin").value).toUpperCase();

            const gstPattern =
                /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/;

            if (customerType === "registered" && !gstPattern.test(gstin)) {
                message.textContent = "Please enter a valid GSTIN format.";
                field("gstin").focus();
                return;
            }

            const customer = {
                name: clean(field("customer_name").value),
                mobile: clean(field("mobile").value),
                gstin: customerType === "registered" ? gstin : "Unregistered",
                state: field("state").value,
                email: clean(field("email").value),
                billing: clean(field("billing_address").value),
                shipping: clean(field("shipping_address").value)
            };

            if (currentMode === "edit" && currentRow) {
                currentRow.cells[0].textContent = customer.name;
                currentRow.cells[1].textContent = customer.mobile;
                currentRow.cells[2].textContent = customer.gstin;
                currentRow.cells[3].textContent = customer.state;
                currentRow.customerExtra = customer;
            } else {
                const row = document.createElement("tr");
                row.appendChild(makeCell(customer.name));
                row.appendChild(makeCell(customer.mobile));
                row.appendChild(makeCell(customer.gstin));
                row.appendChild(makeCell(customer.state));
                row.appendChild(createActionButtons());
                row.customerExtra = customer;
                tableBody.prepend(row);
            }

            filterCustomers();
            modal.hide();
            form.reset();
        });
    });
</script>






<!-- add edit script -->

<script>
    document.addEventListener("DOMContentLoaded", function() {
        if (window.NIVIS_DATABASE_UI) return;
        const form = document.getElementById("customerForm");
        const sameBilling = document.getElementById("sameBilling");
        const shippingFields = document.getElementById("shippingAddressFields");
        const customerType = document.getElementById("customerType");
        const gstin = document.getElementById("customerGSTIN");
        const state = document.getElementById("customerState");
        const message = document.getElementById("customerFormMessage");

        if (!form) return;

        // Display separate shipping address if needed.
        const shippingInputs = shippingFields.querySelectorAll(
            "input, select"
        );

        function updateShippingFields() {
            const separate = !sameBilling.checked;
            shippingFields.classList.toggle("d-none", !separate);

            shippingInputs.forEach(input => {
                input.disabled = !separate;
                input.required = separate;
            });
        }

        sameBilling.addEventListener("change", updateShippingFields);
        updateShippingFields();

        // GSTIN validation for registered business customers.
        function updateGSTIN() {
            const registered = customerType.value === "business";
            gstin.required = registered;
            gstin.disabled = !registered;

            if (!registered) {
                gstin.value = "";
                gstin.setCustomValidity("");
            }
        }

        customerType.addEventListener("change", updateGSTIN);
        updateGSTIN();

        // Format GSTIN automatically.
        gstin.addEventListener("input", function() {
            this.value = this.value.toUpperCase().replace(/\s/g, "");
            this.setCustomValidity("");
        });

        form.addEventListener("submit", function(event) {
            event.preventDefault();
            message.textContent = "";

            const gstinPattern =
                /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/;

            if (gstin.required) {
                if (!gstinPattern.test(gstin.value)) {
                    gstin.setCustomValidity("Enter a valid 15-character GSTIN.");
                } else {
                    const stateCode = {
                        "Andhra Pradesh": "37",
                        "Telangana": "36",
                        "Karnataka": "29",
                        "Tamil Nadu": "33",
                        "Maharashtra": "27",
                        "Kerala": "32",
                        "Odisha": "21",
                        "Delhi": "07"
                    };

                    if (state.value && gstin.value.slice(0, 2) !== stateCode[state.value]) {
                        gstin.setCustomValidity(
                            "GSTIN state code does not match the selected state."
                        );
                    } else {
                        gstin.setCustomValidity("");
                    }
                }
            }

            if (!form.reportValidity()) {
                return;
            }

            // Frontend-only demonstration.
            // Replace with validated backend submission and database persistence.
            message.style.color = "#078966";
            message.textContent =
                "Form validated successfully. Connect the backend to save this customer.";
        });
    });
</script>






<!-- category styling -->
<script>
    document.addEventListener("DOMContentLoaded", function() {
        if (window.NIVIS_DATABASE_UI) return;

        const form = document.getElementById("nivisCategoryForm");
        const tableBody = document.getElementById("nivisCategoryTableBody");
        const newButton = document.getElementById("nivisCategoryNewBtn");
        const message = document.getElementById("nivisCategoryMessage");

        const categoryId = document.getElementById("nivisCategoryId");
        const categoryName = document.getElementById("nivisCategoryName");
        const categoryCode = document.getElementById("nivisCategoryCode");
        const categoryDescription =
            document.getElementById("nivisCategoryDescription");
        const categoryStatus =
            document.getElementById("nivisCategoryStatus");

        if (!form || !tableBody) return;

        // Demo category data.
        const categories = [{
                id: 1,
                name: "Sun Care",
                code: "SUN",
                description: "Daily sunscreen and sun-protection products",
                products: 24,
                status: "Active"
            },
            {
                id: 2,
                name: "Moisturizers",
                code: "MOI",
                description: "Hydrating and barrier-care products",
                products: 38,
                status: "Active"
            },
            {
                id: 3,
                name: "Serums",
                code: "SER",
                description: "Targeted face serums and treatments",
                products: 21,
                status: "Active"
            },
            {
                id: 4,
                name: "Cleansers",
                code: "CLE",
                description: "Gentle daily skin cleansers",
                products: 17,
                status: "Active"
            }
        ];

        let selectedId = null;

        function showMessage(text, isError = false) {
            message.textContent = text;
            message.style.color = isError ? "#c0392b" : "#15845c";
        }

        function resetForm() {
            form.reset();
            selectedId = null;
            categoryId.value = "";
            categoryStatus.value = "Active";
            showMessage("");

            tableBody.querySelectorAll("tr").forEach(row => {
                row.classList.remove("selected");
            });

            categoryName.focus();
        }

        function renderCategories() {
            tableBody.innerHTML = "";

            categories.forEach(category => {
                const row = document.createElement("tr");
                row.dataset.id = category.id;

                const nameCell = document.createElement("td");
                nameCell.textContent = category.name;

                const productsCell = document.createElement("td");
                productsCell.textContent = category.products;

                const statusCell = document.createElement("td");
                statusCell.textContent = category.status;

                row.append(nameCell, productsCell, statusCell);
                tableBody.appendChild(row);
            });

            document.getElementById("nivisCategoryEmpty")
                .classList.toggle("d-none", categories.length > 0);
        }

        function selectCategory(id) {
            const category = categories.find(item => item.id === id);
            if (!category) return;

            selectedId = id;
            categoryId.value = category.id;
            categoryName.value = category.name;
            categoryCode.value = category.code;
            categoryDescription.value = category.description;
            categoryStatus.value = category.status;

            tableBody.querySelectorAll("tr").forEach(row => {
                row.classList.toggle(
                    "selected",
                    Number(row.dataset.id) === id
                );
            });

            showMessage("");
        }

        tableBody.addEventListener("click", function(event) {
            const row = event.target.closest("tr");
            if (!row) return;

            selectCategory(Number(row.dataset.id));
        });

        newButton.addEventListener("click", resetForm);

        categoryCode.addEventListener("input", function() {
            this.value = this.value.toUpperCase().replace(/\s+/g, "");
        });

        form.addEventListener("submit", function(event) {
            event.preventDefault();

            const name = categoryName.value.trim();
            const code = categoryCode.value.trim().toUpperCase();
            const description = categoryDescription.value.trim();
            const status = categoryStatus.value;

            if (!name) {
                showMessage("Category name is required.", true);
                categoryName.focus();
                return;
            }

            const duplicate = categories.some(category =>
                category.name.toLowerCase() === name.toLowerCase() &&
                category.id !== selectedId
            );

            if (duplicate) {
                showMessage("This category name already exists.", true);
                categoryName.focus();
                return;
            }

            if (selectedId !== null) {
                const category = categories.find(item =>
                    item.id === selectedId
                );

                if (category) {
                    category.name = name;
                    category.code = code;
                    category.description = description;
                    category.status = status;
                }

                showMessage("Category updated in this demo.");
            } else {
                const newCategory = {
                    id: categories.length ?
                        Math.max(...categories.map(c => c.id)) + 1 :
                        1,
                    name: name,
                    code: code,
                    description: description,
                    products: 0,
                    status: status
                };

                categories.push(newCategory);
                selectedId = newCategory.id;
                categoryId.value = newCategory.id;
                showMessage("Category added in this demo.");
            }

            renderCategories();

            if (selectedId !== null) {
                selectCategory(selectedId);
            }
        });

        renderCategories();

    });
</script>

<!-- tax gst stylings -->
<script>
    document.addEventListener("DOMContentLoaded", function() {
        if (window.NIVIS_DATABASE_UI) return;

        const sellerState = document.getElementById("taxSellerState");
        const taxMode = document.getElementById("taxDefaultMode");
        const priceMode = document.getElementById("taxPriceMode");

        const insideBadge = document.querySelector(".tax-gst-inside");
        const outsideBadge = document.querySelector(".tax-gst-outside");

        if (!sellerState || !taxMode || !priceMode) return;

        function updateTaxIndicators() {

            const stateName =
                sellerState.options[sellerState.selectedIndex].text;

            insideBadge.textContent =
                "Inside " + stateName + ": CGST + SGST";

            outsideBadge.textContent =
                "Outside " + stateName + ": IGST";

            // This is only a visual example.
            // Real tax selection depends on the place of supply,
            // supplier registration, supply type and applicable law.

            if (taxMode.value === "intra") {
                insideBadge.style.opacity = "1";
                outsideBadge.style.opacity = "0.5";
            } else if (taxMode.value === "inter") {
                insideBadge.style.opacity = "0.5";
                outsideBadge.style.opacity = "1";
            } else {
                insideBadge.style.opacity = "1";
                outsideBadge.style.opacity = "1";
            }
        }

        sellerState.addEventListener("change", updateTaxIndicators);
        taxMode.addEventListener("change", updateTaxIndicators);

        priceMode.addEventListener("change", function() {
            // Connect this preference to backend settings later.
            console.log("Price mode:", priceMode.value);
        });

        updateTaxIndicators();
    });
</script>

<!-- products script  -->
<script>
    document.addEventListener("DOMContentLoaded", function() {
        if (window.NIVIS_DATABASE_UI) return;

        const products = [{
                id: 1,
                name: "Daily Shield SPF 50 Sunscreen",
                sku: "SUN-050",
                category: "Sun Care",
                hsn: "330499",
                unit: "Pcs",
                price: 799,
                gst: 18,
                status: "Active",
                description: "Broad-spectrum daily sun protection."
            },
            {
                id: 2,
                name: "Hydra Cloud Moisturizer",
                sku: "MOI-010",
                category: "Moisturizers",
                hsn: "330499",
                unit: "Pcs",
                price: 649,
                gst: 18,
                status: "Active",
                description: "Lightweight daily hydration."
            },
            {
                id: 3,
                name: "Vitamin C Brightening Serum",
                sku: "SER-020",
                category: "Serums",
                hsn: "330499",
                unit: "Pcs",
                price: 899,
                gst: 18,
                status: "Active",
                description: "Daily brightening skin-care serum."
            },
            {
                id: 4,
                name: "Gentle Foaming Cleanser",
                sku: "CLE-015",
                category: "Cleansers",
                hsn: "340130",
                unit: "Pcs",
                price: 499,
                gst: 18,
                status: "Active",
                description: "Gentle everyday face cleanser."
            },
            {
                id: 5,
                name: "Barrier Repair Moisturizer",
                sku: "MOI-025",
                category: "Moisturizers",
                hsn: "330499",
                unit: "Pcs",
                price: 749,
                gst: 18,
                status: "Active",
                description: "Rich moisturizer for skin barrier support."
            }
        ];

        const tableBody = document.getElementById("nivisProductTableBody");
        const search = document.getElementById("nivisProductSearch");
        const categoryFilter =
            document.getElementById("nivisProductCategoryFilter");
        const gstFilter = document.getElementById("nivisProductGSTFilter");
        const addButton = document.getElementById("nivisProductAddBtn");
        const form = document.getElementById("nivisProductForm");
        const message = document.getElementById("nivisProductMessage");
        const modalElement = document.getElementById("nivisProductModal");
        const modalTitle = document.getElementById("nivisProductModalTitle");

        if (!tableBody || !form || !window.bootstrap) return;

        const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
        let editingId = null;

        const get = id => document.getElementById(id);
        const saveButton = form.querySelector('button[type="submit"]');

        function formatPrice(amount) {
            return "Rs " + Number(amount).toLocaleString("en-IN", {
                maximumFractionDigits: 2
            });
        }

        function renderProducts() {
            const query = search.value.toLowerCase().trim();
            const selectedCategory = categoryFilter.value;
            const selectedGST = gstFilter.value;

            const filtered = products.filter(product => {
                const matchesSearch = (
                    product.name + " " + product.sku + " " + product.hsn
                ).toLowerCase().includes(query);

                const matchesCategory =
                    selectedCategory === "all" ||
                    product.category === selectedCategory;

                const matchesGST =
                    selectedGST === "all" ||
                    String(product.gst) === selectedGST;

                return matchesSearch && matchesCategory && matchesGST;
            });

            tableBody.replaceChildren();

            filtered.forEach(product => {
                const row = document.createElement("tr");

                const values = [
                    product.name + " / " + product.sku,
                    product.category,
                    product.hsn,
                    formatPrice(product.price),
                    product.gst + "%"
                ];

                values.forEach(value => {
                    const td = document.createElement("td");
                    td.textContent = value;
                    row.appendChild(td);
                });

                const actions = document.createElement("td");
                actions.className = "nivis-produdcts-actions";
                [
                    ["view", "fa-eye", "View product"],
                    ["edit", "fa-pen-to-square", "Edit product"],
                    ["delete", "fa-trash-can", "Delete product"]
                ].forEach(([action, icon, label]) => {
                    const button = document.createElement("button");
                    button.type = "button";
                    button.className =
                        "nivis-produdcts-icon-action action-" + action;
                    button.dataset.action = action;
                    button.dataset.id = product.id;
                    button.title = label;
                    button.setAttribute("aria-label", label + ": " + product.name);
                    button.innerHTML =
                        '<i class="fa-solid ' + icon + '" aria-hidden="true"></i>';
                    actions.appendChild(button);
                });
                row.appendChild(actions);
                tableBody.appendChild(row);
            });

            get("nivisProductEmpty").classList.toggle(
                "d-none", filtered.length > 0
            );
        }

        function openAddForm() {
            editingId = null;
            form.reset();
            message.textContent = "";
            form.querySelectorAll("input, select, textarea").forEach(field => {
                field.disabled = false;
            });
            saveButton.classList.remove("d-none");
            modalTitle.textContent = "Add product";
            get("productGST").value = "18";
            get("productStatus").value = "Active";
        }

        function openEditForm(id) {
            const product = products.find(item => item.id === id);
            if (!product) return;

            editingId = id;
            form.reset();
            message.textContent = "";
            form.querySelectorAll("input, select, textarea").forEach(field => {
                field.disabled = false;
            });
            saveButton.classList.remove("d-none");
            form.querySelectorAll("input, select, textarea").forEach(field => {
                field.disabled = false;
            });

            modalTitle.textContent = "Edit product";
            get("productName").value = product.name;
            get("productSKU").value = product.sku;
            get("productCategory").value = product.category;
            get("productHSN").value = product.hsn;
            get("productUnit").value = product.unit;
            get("productPrice").value = product.price;
            get("productGST").value = product.gst;
            get("productStatus").value = product.status;
            get("productDescription").value = product.description;

            modal.show();
        }

        function openViewForm(id) {
            openEditForm(id);
            modalTitle.textContent = "Product details";
            form.querySelectorAll("input, select, textarea").forEach(field => {
                field.disabled = true;
            });
            saveButton.classList.add("d-none");
        }

        addButton.addEventListener("click", openAddForm);

        tableBody.addEventListener("click", function(event) {
            const button = event.target.closest("[data-action][data-id]");
            if (!button) return;

            const id = Number(button.dataset.id);
            if (button.dataset.action === "view") {
                openViewForm(id);
            } else if (button.dataset.action === "edit") {
                openEditForm(id);
            } else if (button.dataset.action === "delete") {
                const product = products.find(item => item.id === id);
                if (product && window.confirm("Delete " + product.name + "?")) {
                    products.splice(products.indexOf(product), 1);
                    renderProducts();
                }
            }
        });

        [search, categoryFilter, gstFilter].forEach(control => {
            control.addEventListener(
                control === search ? "input" : "change",
                renderProducts
            );
        });

        form.addEventListener("submit", function(event) {
            event.preventDefault();

            if (!form.reportValidity()) return;

            const product = {
                id: editingId || Math.max(0, ...products.map(p => p.id)) + 1,
                name: get("productName").value.trim(),
                sku: get("productSKU").value.trim().toUpperCase(),
                category: get("productCategory").value,
                hsn: get("productHSN").value.trim(),
                unit: get("productUnit").value,
                price: Number(get("productPrice").value),
                gst: Number(get("productGST").value),
                status: get("productStatus").value,
                description: get("productDescription").value.trim()
            };

            const duplicate = products.some(item =>
                item.sku.toLowerCase() === product.sku.toLowerCase() &&
                item.id !== editingId
            );

            if (duplicate) {
                message.style.color = "#bb3030";
                message.textContent = "This product SKU already exists.";
                return;
            }

            if (editingId) {
                const index = products.findIndex(item => item.id === editingId);
                if (index !== -1) products[index] = product;
            } else {
                products.push(product);
            }

            renderProducts();
            modal.hide();
        });

        renderProducts();
    });
</script>



<!-- add edit products -->

<script>
    document.addEventListener("DOMContentLoaded", function() {
        if (window.NIVIS_DATABASE_UI) return;
        const form = document.getElementById("addEditProductForm");
        const message = document.getElementById("addEditProductMessage");
        const saveBtn = document.getElementById("addEditProductSave");

        if (!form) return;

        const field = id => document.getElementById(id);

        const name = field("editProductName");
        const sku = field("editProductSKU");
        const category = field("editProductCategory");
        const hsn = field("editProductHSN");
        const price = field("editProductPrice");
        const gst = field("editProductGST");
        const discount = field("editProductDiscount");

        sku.addEventListener("input", function() {
            this.value = this.value.toUpperCase().replace(/\s+/g, "");
        });

        hsn.addEventListener("input", function() {
            this.value = this.value.replace(/[^0-9]/g, "");
        });

        function showMessage(text, error = false) {
            message.textContent = text;
            message.style.color = error ? "#c0392b" : "#078966";
        }

        // Call this after fetching a product from PHP/MySQL.
        window.populateNivisProductForm = function(product) {
            field("editProductId").value = product.id || "";
            name.value = product.name || "";
            sku.value = product.sku || "";
            category.value = product.category_id || "";
            hsn.value = product.hsn || "";
            field("editProductUnit").value = product.unit || "PCS";
            price.value = product.price ?? "";
            gst.value = product.gst ?? "18";
            discount.value = product.discount ?? 0;
            field("editProductStatus").value = product.status || "Active";
            field("editProductDescription").value = product.description || "";

            saveBtn.textContent = "Update product";
        };

        form.addEventListener("submit", function(event) {
            event.preventDefault();
            showMessage("");

            if (!form.reportValidity()) return;

            const productPrice = Number(price.value);
            const discountValue = Number(discount.value);

            if (!Number.isFinite(productPrice) || productPrice < 0) {
                showMessage("Enter a valid selling price.", true);
                price.focus();
                return;
            }

            if (
                !Number.isFinite(discountValue) ||
                discountValue < 0 ||
                discountValue > 100
            ) {
                showMessage("Discount must be between 0 and 100%.", true);
                discount.focus();
                return;
            }

            if (!/^[0-9]{4,8}$/.test(hsn.value)) {
                showMessage("Enter a valid 4 to 8 digit HSN.", true);
                hsn.focus();
                return;
            }

            if (!category.value || !name.value.trim()) {
                showMessage("Complete all required product fields.", true);
                return;
            }

            // UI-only validation.
            // Replace with a POST request to the backend API.
            showMessage(
                "Product validated. Connect PHP/MySQL to save this record."
            );
        });
    });
</script>

<!-- quotations stylings  -->

<script>
    document.addEventListener("DOMContentLoaded", function() {
        if (window.NIVIS_DATABASE_UI) return;

        const quotations = [{
            id: 26,
            number: "QT-0026",
                customer: "Sri Lakshmi Traders",
            products: ["Daily Shield SPF 50 Sunscreen", "Hydra Cloud Moisturizer"],
            date: "12-Oct-2026",
            total: 22000,
                status: "Approved",
                invoiceId: null
            },
            {
                id: 24,
                number: "QT-0024",
                customer: "Sree Agencies",
                products: ["Vitamin C Brightening Serum", "Daily Shield SPF 50 Sunscreen"],
                date: "10-Oct-2026",
                total: 14868,
                status: "Sent",
                invoiceId: null
            },
            {
                id: 23,
                number: "QT-0023",
                customer: "Glow & Co. Beauty Store",
                products: ["Barrier Repair Moisturizer"],
                date: "08-Oct-2026",
                total: 9440,
                status: "Draft",
                invoiceId: null
            },
            {
                id: 22,
                number: "QT-0022",
                customer: "Ravi Enterprises",
                products: ["Gentle Foaming Cleanser", "Hydra Cloud Moisturizer"],
                date: "03-Oct-2026",
                total: 18000,
                status: "Converted",
                invoiceId: 31
            }
        ];

        const tableBody =
            document.getElementById("nivisQuotationTableBody");

        const emptyMessage =
            document.getElementById("nivisQuotationEmpty");
        const searchInput =
            document.getElementById("nivisQuotationSearch");
        const customerFilter =
            document.getElementById("nivisQuotationCustomer");
        const statusFilter =
            document.getElementById("nivisQuotationStatus");

        const convertModalElement =
            document.getElementById("nivisQuotationConvertModal");
        const viewModalElement =
            document.getElementById("nivisQuotationViewModal");

        const convertText =
            document.getElementById("nivisQuotationConvertText");

        const confirmButton =
            document.getElementById("nivisQuotationConfirmConvert");

        if (!tableBody) return;

        let selectedQuotation = null;
        const linkedQuoteId =
            new URLSearchParams(window.location.search).get("quote_id");
        if (linkedQuoteId) {
            const linkedQuote = quotations.find(
                quote => String(quote.id) === linkedQuoteId
            );
            if (linkedQuote) searchInput.value = linkedQuote.number;
        }

        const modal = window.bootstrap ?
            bootstrap.Modal.getOrCreateInstance(convertModalElement) :
            null;
        const quotationViewModal = window.bootstrap ?
            bootstrap.Modal.getOrCreateInstance(viewModalElement) :
            null;

        function formatCurrency(amount) {
            return "Rs " + Number(amount).toLocaleString("en-IN", {
                maximumFractionDigits: 2
            });
        }

        function renderCounts() {
            document.getElementById("quoteDraftCount").textContent =
                quotations.filter(quote => quote.status === "Draft").length;
            document.getElementById("quoteSentCount").textContent =
                quotations.filter(quote => quote.status === "Sent").length;
            document.getElementById("quoteApprovedCount").textContent =
                quotations.filter(quote => quote.status === "Approved").length;
            document.getElementById("quoteConvertedCount").textContent =
                quotations.filter(quote => quote.status === "Converted").length;
        }

        function makeCell(value) {
            const cell = document.createElement("td");
            cell.textContent = value;
            return cell;
        }

        function renderTable() {
            const query = searchInput.value.toLowerCase().trim();
            const customer = customerFilter.value;
            const status = statusFilter.value;
            const filtered = quotations.filter(quotation => {
                const searchable = [
                    quotation.number,
                    quotation.customer,
                    ...(quotation.products || [])
                ].join(" ").toLowerCase();
                return searchable.includes(query) &&
                    (customer === "all" || quotation.customer === customer) &&
                    (status === "all" || quotation.status === status);
            });

            tableBody.replaceChildren();

            filtered.forEach(quotation => {
                const row = document.createElement("tr");

                row.appendChild(makeCell(quotation.number));
                row.appendChild(makeCell(quotation.customer));
                row.appendChild(makeCell((quotation.products || []).join(", ")));
                row.appendChild(makeCell(quotation.date));
                row.appendChild(makeCell(formatCurrency(quotation.total)));
                const statusCell = makeCell("");
                const statusBadge = document.createElement("span");
                statusBadge.className =
                    "nivis-labs-quotation-status status-" +
                    quotation.status.toLowerCase();
                statusBadge.textContent = quotation.status;
                statusCell.appendChild(statusBadge);
                row.appendChild(statusCell);

                const actionCell = document.createElement("td");
                actionCell.className = "nivis-labs-quotation-actions";

                [
                    ["view", "fa-eye", "View quotation"],
                    ["edit", "fa-pen-to-square", "Edit quotation"],
                    ["delete", "fa-trash-can", "Delete quotation"]
                ].forEach(([action, icon, label]) => {
                    const button = document.createElement("button");
                    button.type = "button";
                    button.className =
                        "nivis-labs-quotation-icon-action action-" + action;
                    button.dataset.action = action;
                    button.dataset.id = quotation.id;
                    button.setAttribute("aria-label", label + " " + quotation.number);
                    button.title = label;
                    button.innerHTML =
                        '<i class="fa-solid ' + icon + '" aria-hidden="true"></i>';
                    actionCell.appendChild(button);
                });

                if (quotation.status === "Approved" &&
                    quotation.invoiceId === null) {
                    const convertButton = document.createElement("button");
                    convertButton.type = "button";
                    convertButton.className =
                        "nivis-labs-quotation-action nivis-labs-quotation-convert";
                    convertButton.textContent = "Convert";
                    convertButton.dataset.action = "convert";
                    convertButton.dataset.id = quotation.id;
                    actionCell.appendChild(convertButton);
                } else if (quotation.status === "Converted" &&
                    quotation.invoiceId !== null) {
                    const invoiceButton = document.createElement("button");
                    invoiceButton.type = "button";
                    invoiceButton.className =
                        "nivis-labs-quotation-action nivis-labs-quotation-convert";
                    invoiceButton.textContent = "Invoice";
                    invoiceButton.dataset.action = "invoice";
                    invoiceButton.dataset.id = quotation.id;
                    actionCell.appendChild(invoiceButton);
                }

                row.appendChild(actionCell);
                tableBody.appendChild(row);
            });

            emptyMessage.classList.toggle(
                "d-none", filtered.length > 0
            );
        }

        tableBody.addEventListener("click", function(event) {
            const button = event.target.closest("[data-action]");
            if (!button) return;

            const id = Number(button.dataset.id);
            const quotation = quotations.find(q => q.id === id);
            if (!quotation) return;

            switch (button.dataset.action) {

                case "delete":
                    if (window.confirm("Delete " + quotation.number + "?")) {
                        quotations.splice(quotations.indexOf(quotation), 1);
                        renderCounts();
                        renderTable();
                    }
                    break;

                case "convert":
                    if (quotation.status !== "Approved" ||
                        quotation.invoiceId !== null) {
                        return;
                    }

                    selectedQuotation = quotation;

                    if (modal) {
                        convertText.textContent =
                            "Convert " + quotation.number +
                            " into an invoice for " +
                            quotation.customer + "?";
                        modal.show();
                    }
                    break;

                case "view":
                    if (quotationViewModal) {
                        document.getElementById("nivisQuotationViewNumber")
                            .textContent = quotation.number;
                        document.getElementById("nivisQuotationViewCustomer")
                            .textContent = quotation.customer;
                        document.getElementById("nivisQuotationViewProducts")
                            .textContent = (quotation.products || []).join(", ");
                        document.getElementById("nivisQuotationViewDate")
                            .textContent = quotation.date;
                        document.getElementById("nivisQuotationViewTotal")
                            .textContent = formatCurrency(quotation.total);
                        document.getElementById("nivisQuotationViewStatus")
                            .textContent = quotation.status;
                        quotationViewModal.show();
                    }
                    break;

                case "edit":
                    window.location.href =
                        "create-quotation.php?id=" +
                        encodeURIComponent(id);
                    break;

                case "invoice":
                    if (quotation.invoiceId !== null) {
                        window.location.href =
                            "add-edit-invoice.php?id=" +
                            encodeURIComponent(quotation.invoiceId);
                    }
                    break;
            }
        });

        [searchInput, customerFilter, statusFilter].forEach(control => {
            control.addEventListener(
                control === searchInput ? "input" : "change",
                renderTable
            );
        });

        document.querySelectorAll("[data-quote-status]").forEach(card => {
            card.addEventListener("click", function() {
                const selected = statusFilter.value === card.dataset.quoteStatus;
                statusFilter.value = selected ? "all" : card.dataset.quoteStatus;
                document.querySelectorAll("[data-quote-status]").forEach(item => {
                    item.setAttribute(
                        "aria-pressed",
                        String(!selected && item === card)
                    );
                });
                renderTable();
            });
        });

        confirmButton.addEventListener("click", function() {
            if (!selectedQuotation ||
                selectedQuotation.status !== "Approved" ||
                selectedQuotation.invoiceId !== null) {
                return;
            }

            // Backend must fetch the approved quotation,
            // verify authorization and conversion status,
            // and create an invoice in one DB transaction.
            // Do not mark the quotation converted here.
            const id = encodeURIComponent(selectedQuotation.id);

            window.location.href =
                "add-edit-invoice.php?quotation_id=" + id;
        });

        renderCounts();
        renderTable();

    });
</script>

<!-- creat new quotations  -->

<script>
    document.addEventListener("DOMContentLoaded", function() {
        if (window.NIVIS_DATABASE_UI) return;
        const products = [{
                id: 1,
                name: "Daily Shield SPF 50 Sunscreen",
                hsn: "330499",
                price: 799,
                gst: 18,
                discount: 0
            },
            {
                id: 2,
                name: "Hydra Cloud Moisturizer",
                hsn: "330499",
                price: 649,
                gst: 18,
                discount: 5
            },
            {
                id: 3,
                name: "Vitamin C Brightening Serum",
                hsn: "330499",
                price: 899,
                gst: 18,
                discount: 0
            },
            {
                id: 4,
                name: "Gentle Foaming Cleanser",
                hsn: "340130",
                price: 499,
                gst: 18,
                discount: 0
            },
            {
                id: 5,
                name: "Barrier Repair Moisturizer",
                hsn: "330499",
                price: 749,
                gst: 18,
                discount: 0
            }
        ];

        const customerStates = {
            "1": "Andhra Pradesh",
            "2": "Karnataka",
            "3": "Telangana",
            "4": "Andhra Pradesh"
        };

        const sellerState = "Andhra Pradesh";

        const tbody = document.getElementById("nlcqLineItems");
        const addButton = document.getElementById("nlcqAddLine");
        const customerField = document.getElementById("nlcqCustomer");
        const feedback = document.getElementById("nlcqFeedback");

        if (!tbody || !addButton || !customerField) return;

        let quotationStatus = "Draft";
        let rows = [{
                productId: 1,
                qty: 2,
                price: 799,
                gst: 18,
                discount: 0
            },
            {
                productId: 2,
                qty: 3,
                price: 649,
                gst: 18,
                discount: 5
            }
        ];

        function money(amount) {
            return "Rs " + amount.toLocaleString("en-IN", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function createInput(type, value, className, min, max) {
            const input = document.createElement("input");
            input.type = type;
            input.value = value;
            input.className = className;

            if (min !== undefined) input.min = min;
            if (max !== undefined) input.max = max;
            if (type === "number") input.step = "0.01";

            return input;
        }

        function renderRows() {
            tbody.replaceChildren();

            rows.forEach((row, index) => {
                const tr = document.createElement("tr");
                tr.dataset.index = index;

                const serial = document.createElement("td");
                serial.textContent = String(index + 1).padStart(2, "0");
                tr.appendChild(serial);

                const productCell = document.createElement("td");
                const productSelect = document.createElement("select");
                productSelect.className = "nlcq-cell-select";
                productSelect.dataset.field = "productId";

                const placeholder = new Option("Select product", "");
                productSelect.add(placeholder);

                products.forEach(product => {
                    productSelect.add(
                        new Option(product.name, product.id)
                    );
                });

                productSelect.value = row.productId || "";
                productCell.appendChild(productSelect);
                tr.appendChild(productCell);

                const hsn = document.createElement("td");
                hsn.textContent =
                    products.find(p => p.id === row.productId)?.hsn || "-";
                tr.appendChild(hsn);

                ["qty", "price", "gst", "discount"].forEach(field => {
                    const td = document.createElement("td");
                    const input = createInput(
                        "number",
                        row[field],
                        "nlcq-cell-input",
                        0,
                        field === "discount" ? 100 : undefined
                    );
                    input.dataset.field = field;
                    td.appendChild(input);
                    tr.appendChild(td);
                });

                const totalCell = document.createElement("td");
                totalCell.className = "nlcq-row-total";
                totalCell.dataset.total = index;
                tr.appendChild(totalCell);

                const removeCell = document.createElement("td");
                const removeButton = document.createElement("button");
                removeButton.type = "button";
                removeButton.className = "nlcq-row-remove";
                removeButton.textContent = "×";
                removeButton.title = "Remove line";
                removeButton.dataset.remove = index;
                removeCell.appendChild(removeButton);
                tr.appendChild(removeCell);

                tbody.appendChild(tr);
            });

            calculateTotals();
        }

        function calculateTotals() {
            let subtotal = 0;
            let discountAmount = 0;
            let taxableTotal = 0;
            let taxTotal = 0;

            rows.forEach((row, index) => {
                const qty = Math.max(0, Number(row.qty) || 0);
                const price = Math.max(0, Number(row.price) || 0);
                const gst = Math.max(0, Number(row.gst) || 0);
                const discount = Math.min(
                    100, Math.max(0, Number(row.discount) || 0)
                );

                const base = qty * price;
                const lineDiscount = base * discount / 100;
                const taxable = base - lineDiscount;
                const tax = taxable * gst / 100;
                const total = taxable + tax;

                subtotal += base;
                discountAmount += lineDiscount;
                taxableTotal += taxable;
                taxTotal += tax;

                const totalCell = tbody.querySelector(
                    '[data-total="' + index + '"]'
                );

                if (totalCell) {
                    totalCell.textContent =
                        total.toLocaleString("en-IN", {
                            maximumFractionDigits: 2
                        });
                }
            });

            document.getElementById("nlcqTaxable").textContent =
                money(taxableTotal);

            document.getElementById("nlcqTax").textContent =
                money(taxTotal);

            document.getElementById("nlcqGrandTotal").textContent =
                money(taxableTotal + taxTotal);

            const customerState = customerStates[customerField.value];
            const intraState = customerState === sellerState;

            // For UI demo only.
            // Real CGST/SGST/IGST determination uses place-of-supply
            // rules, supplier registration and transaction type.
            const taxMode = intraState ? "CGST + SGST" : "IGST";

            return {
                subtotal,
                discountAmount,
                taxableTotal,
                taxTotal,
                grandTotal: taxableTotal + taxTotal,
                taxMode
            };
        }

        tbody.addEventListener("input", function(event) {
            const input = event.target.closest("[data-field]");
            if (!input || input.tagName === "SELECT") return;

            const index = Number(input.closest("tr").dataset.index);
            const field = input.dataset.field;

            if (!["qty", "price", "gst", "discount"].includes(field)) return;

            rows[index][field] = input.value;
            calculateTotals();
        });

        tbody.addEventListener("change", function(event) {
            const select = event.target.closest('select[data-field="productId"]');
            if (!select) return;

            const index = Number(select.closest("tr").dataset.index);
            const product = products.find(p => p.id === Number(select.value));

            if (product) {
                rows[index] = {
                    productId: product.id,
                    qty: rows[index].qty || 1,
                    price: product.price,
                    gst: product.gst,
                    discount: product.discount
                };
            }

            renderRows();
        });

        tbody.addEventListener("click", function(event) {
            const button = event.target.closest("[data-remove]");
            if (!button) return;

            rows.splice(Number(button.dataset.remove), 1);
            renderRows();
        });

        addButton.addEventListener("click", function() {
            rows.push({
                productId: "",
                qty: 1,
                price: 0,
                gst: 0,
                discount: 0
            });
            renderRows();
        });

        customerField.addEventListener("change", calculateTotals);

        function buildQuotation() {
            return {
                quoteNumber: document.getElementById("nlcqQuoteNumber").value,
                customerId: customerField.value,
                issueDate: document.getElementById("nlcqIssueDate").value,
                validUntil: document.getElementById("nlcqValidUntil").value,
                notes: document.getElementById("nlcqNotes").value,
                status: quotationStatus,
                items: rows.map(row => ({
                    ...row
                })),
                totals: calculateTotals()
            };
        }

        function validateQuotation() {
            if (!customerField.value) {
                feedback.textContent = "Please select a customer.";
                return false;
            }

            if (!rows.length || rows.some(row =>
                    !row.productId ||
                    !(Number(row.qty) > 0) ||
                    Number(row.price) < 0 ||
                    Number(row.discount) < 0 ||
                    Number(row.discount) > 100
                )) {
                feedback.textContent =
                    "Please complete all line items with valid quantities and prices.";
                return false;
            }

            const issueDate = document.getElementById("nlcqIssueDate").value;
            const validUntil = document.getElementById("nlcqValidUntil").value;

            if (!issueDate || !validUntil || validUntil < issueDate) {
                feedback.textContent =
                    "Valid until date must not be earlier than issue date.";
                return false;
            }

            feedback.textContent = "";
            return true;
        }

        document.getElementById("nlcqSaveDraft").addEventListener("click", function() {
            if (!validateQuotation()) return;
            quotationStatus = "Draft";
            feedback.textContent =
                "Draft validated in frontend. Backend saving is not connected.";
        });

        document.getElementById("nlcqApprove").addEventListener("click", function() {
            if (!validateQuotation()) return;
            feedback.textContent =
                "Approval requires authorized backend confirmation.";
        });

        document.getElementById("nlcqPreview").addEventListener("click", function() {
            if (!validateQuotation()) return;

            // For a production PDF, call a server-side PDF renderer.
            // This demo uses the browser print dialog.
            window.print();
        });

        document.getElementById("nlcqConvert").addEventListener("click", function() {
            if (!validateQuotation()) return;

            if (quotationStatus !== "Approved") {
                feedback.textContent =
                    "Only approved, saved quotations can be converted to invoices.";
                return;
            }

            // A production backend must verify that the quotation
            // is approved and not already converted, then create
            // the invoice once in a database transaction.
            feedback.textContent =
                "Connect the approved quotation backend to enable invoice conversion.";
        });

        renderRows();
    });
</script>

<!-- invoice  script -->
<script>
    document.addEventListener("DOMContentLoaded", function() {
        if (window.NIVIS_DATABASE_UI) return;

        const invoices = [{
                id: 31,
                invoiceNo: "INV-0031",
                quotationNo: "QT-0021",
                customer: "Sree Agencies",
            products: ["Daily Shield SPF 50 Sunscreen", "Vitamin C Brightening Serum"],
            total: 14868,
                paid: 14868
            },
            {
                id: 30,
                invoiceNo: "INV-0030",
                quotationNo: "QT-0020",
                customer: "Sri Lakshmi Traders",
                products: ["Hydra Cloud Moisturizer", "Gentle Foaming Cleanser"],
                total: 22000,
                paid: 10000
            },
            {
                id: 29,
                invoiceNo: "INV-0029",
                quotationNo: "-",
                customer: "Ravi Enterprises",
                products: ["Barrier Repair Moisturizer"],
                total: 18000,
                paid: 0
            }
        ];

        const searchInput =
            document.getElementById("nivisInvoiceSearch");

        const customerFilter =
            document.getElementById("nivisInvoiceCustomer");

        const statusFilter =
            document.getElementById("nivisInvoiceStatus");

        const tableBody =
            document.getElementById("nivisInvoiceTableBody");

        const emptyMessage =
            document.getElementById("nivisInvoiceEmpty");

        if (!tableBody) return;

        function formatCurrency(value) {
            return "Rs " + Number(value).toLocaleString("en-IN", {
                maximumFractionDigits: 2
            });
        }

        function getPaymentStatus(invoice) {
            const outstanding = Math.max(
                0,
                invoice.total - invoice.paid
            );

            if (outstanding <= 0.005) {
                return "Paid";
            }

            if (invoice.paid > 0) {
                return "Part-paid";
            }

            return "Unpaid";
        }

        function makeCell(value) {
            const cell = document.createElement("td");
            cell.textContent = value;
            return cell;
        }

        function renderInvoices() {
            const query = searchInput.value.toLowerCase().trim();
            const customer = customerFilter.value;
            const status = statusFilter.value;

            const filtered = invoices.filter(invoice => {
                const matchesSearch =
                    (invoice.invoiceNo + " " + invoice.products.join(" "))
                    .toLowerCase().includes(query);

                const matchesCustomer =
                    customer === "all" || invoice.customer === customer;

                const matchesStatus =
                    status === "all" ||
                    getPaymentStatus(invoice) === status;

                return matchesSearch &&
                    matchesCustomer &&
                    matchesStatus;
            });

            tableBody.replaceChildren();

            filtered.forEach(invoice => {
                const row = document.createElement("tr");

                [
                    invoice.invoiceNo,
                    invoice.quotationNo,
                    invoice.customer,
                    invoice.products.join(", "),
                    formatCurrency(invoice.total),
                    formatCurrency(invoice.paid),
                    getPaymentStatus(invoice)
                ].forEach(value => {
                    row.appendChild(makeCell(value));
                });

                const statusCell = row.cells[6];
                const statusBadge = document.createElement("span");
                statusBadge.className = "nivis-labs-invoice-status status-" +
                    getPaymentStatus(invoice).toLowerCase().replace("-", "");
                statusBadge.textContent = getPaymentStatus(invoice);
                statusCell.replaceChildren(statusBadge);

                const actionCell = document.createElement("td");
                actionCell.className = "nivis-labs-invoice-actions";
                [
                    ["view", "fa-eye", "View invoice"],
                    ["edit", "fa-pen-to-square", "Edit invoice"],
                    ["delete", "fa-trash-can", "Delete invoice"]
                ].forEach(([action, icon, label]) => {
                    const button = document.createElement("button");
                    button.type = "button";
                    button.className =
                        "nivis-labs-invoice-icon-action action-" + action;
                    button.dataset.action = action;
                    button.dataset.id = invoice.id;
                    button.setAttribute("aria-label", label + " " + invoice.invoiceNo);
                    button.title = label;
                    button.innerHTML =
                        '<i class="fa-solid ' + icon + '" aria-hidden="true"></i>';
                    actionCell.appendChild(button);
                });
                row.appendChild(actionCell);

                tableBody.appendChild(row);
            });

            emptyMessage.classList.toggle(
                "d-none",
                filtered.length > 0
            );
        }

        tableBody.addEventListener("click", function(event) {
            const button = event.target.closest("[data-action]");
            if (!button) return;

            const id = Number(button.dataset.id);
            const invoice = invoices.find(item => item.id === id);
            if (!invoice) return;

            if (button.dataset.action === "delete") {
                if (window.confirm("Delete " + invoice.invoiceNo + "?")) {
                    invoices.splice(invoices.indexOf(invoice), 1);
                    renderInvoices();
                }
                return;
            }

            const suffix = button.dataset.action === "edit" ? "&action=edit" : "";
            window.location.href =
                "add-edit-invoice.php?id=" + encodeURIComponent(id) + suffix;
        });

        searchInput.addEventListener("input", renderInvoices);
        customerFilter.addEventListener("change", renderInvoices);
        statusFilter.addEventListener("change", renderInvoices);

        renderInvoices();

    });
</script>

<!-- view invoice -->
<script>
    document.addEventListener("DOMContentLoaded", function() {
        if (window.NIVIS_DATABASE_UI) return;

        // Demo invoice data - replace with PHP/MySQL API response.
        const invoice = {
            id: 32,
            number: "INV-0032",
            date: "12-Oct-2026",
            quotationId: 26,
            quotationNo: "QT-0026",
            customer: "Sri Lakshmi Traders",
            customerState: "Andhra Pradesh",
            sellerState: "Andhra Pradesh",
            placeOfSupply: "Andhra Pradesh",
            paid: 0,

            items: [{
                    product: "Daily Shield SPF 50 Sunscreen",
                    hsn: "330499",
                    qty: 2,
                    price: 799,
                    gst: 18,
                    discount: 0
                },
                {
                    product: "Hydra Cloud Moisturizer",
                    hsn: "330499",
                    qty: 3,
                    price: 649,
                    gst: 18,
                    discount: 5
                }
            ]
        };

        const $ = id => document.getElementById(id);
        if (!$("nlidpInvoiceNumber")) return;

        const formatMoney = amount =>
            "Rs " + Number(amount).toLocaleString("en-IN", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

        let invoiceTotal = 0;
        let paidAmount = invoice.paid;

        function makeCell(value) {
            const td = document.createElement("td");
            td.textContent = value;
            return td;
        }

        function calculateInvoice() {
            const tbody = $("nlidpInvoiceItems");
            tbody.replaceChildren();

            let taxableTotal = 0;
            let gstTotal = 0;

            invoice.items.forEach(item => {
                const base = item.qty * item.price;
                const discount = base * item.discount / 100;
                const taxable = base - discount;
                const gstAmount = taxable * item.gst / 100;

                taxableTotal += taxable;
                gstTotal += gstAmount;

                const row = document.createElement("tr");

                [
                    item.product,
                    item.hsn,
                    item.qty,
                    item.price.toFixed(2),
                    item.discount + "%",
                    taxable.toLocaleString("en-IN", {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }),
                    item.gst + "%"
                ].forEach(value => {
                    row.appendChild(makeCell(value));
                });

                tbody.appendChild(row);
            });

            const intraState =
                invoice.sellerState === invoice.placeOfSupply;

            $("nlidpTaxable").textContent = formatMoney(taxableTotal);

            if (intraState) {
                $("nlidpCgstRow").classList.remove("d-none");
                $("nlidpSgstRow").classList.remove("d-none");
                $("nlidpIgstRow").classList.add("d-none");

                // Example assumes all line items have the same GST rate.
                const gstRate = invoice.items[0]?.gst || 0;

                $("nlidpCgstLabel").textContent =
                    "CGST " + (gstRate / 2) + "%:";

                $("nlidpSgstLabel").textContent =
                    "SGST " + (gstRate / 2) + "%:";

                $("nlidpCgst").textContent = formatMoney(gstTotal / 2);
                $("nlidpSgst").textContent = formatMoney(gstTotal / 2);
            } else {
                $("nlidpCgstRow").classList.add("d-none");
                $("nlidpSgstRow").classList.add("d-none");
                $("nlidpIgstRow").classList.remove("d-none");

                const gstRate = invoice.items[0]?.gst || 0;

                $("nlidpIgstLabel").textContent =
                    "IGST " + gstRate + "%:";

                $("nlidpIgst").textContent = formatMoney(gstTotal);
            }

            invoiceTotal = Math.round(
                (taxableTotal + gstTotal) * 100
            ) / 100;

            $("nlidpGrandTotal").textContent = formatMoney(invoiceTotal);
            $("nlidpOutstanding").textContent =
                formatMoney(Math.max(0, invoiceTotal - paidAmount));
        }

        // Invoice metadata
        $("nlidpInvoiceNumber").textContent = invoice.number;
        $("nlidpInvoiceDate").textContent = invoice.date;
        $("nlidpQuotationLink").textContent =
            "Linked " + invoice.quotationNo;

        $("nlidpQuotationLink").href =
            "quotations.php?quote_id=" +
            encodeURIComponent(invoice.quotationId);

        $("nlidpSupplyState").textContent =
            "Place of supply: " + invoice.placeOfSupply;

        calculateInvoice();

        // PDF Download
        $("nlidpDownloadPDF").addEventListener("click", function() {
            const element = $("nlidpPrintableInvoice");

            if (!window.html2pdf) {
                $("nlidpFeedback").textContent =
                    "PDF library unavailable. Please try again.";
                return;
            }

            html2pdf().set({
                margin: 8,
                filename: invoice.number + ".pdf",
                image: {
                    type: "jpeg",
                    quality: 0.98
                },
                html2canvas: {
                    scale: 2,
                    useCORS: true,
                    backgroundColor: "#ffffff"
                },
                jsPDF: {
                    unit: "mm",
                    format: "a4",
                    orientation: "portrait"
                }
            }).from(element).save();
        });

        // Payment Modal
        const paymentModal = $("nlidpPaymentModal");
        const paymentForm = $("nlidpPaymentForm");
        const paymentMessage = $("nlidpPaymentMessage");

        $("nlidpRecordPayment").addEventListener("click", function() {
            paymentForm.reset();
            paymentMessage.textContent = "";

            $("nlidpPaymentDate").value =
                new Date().toLocaleDateString("en-CA");

            $("nlidpOutstanding").textContent =
                formatMoney(Math.max(0, invoiceTotal - paidAmount));
        });

        paymentForm.addEventListener("submit", function(event) {
            event.preventDefault();
            paymentMessage.textContent = "";

            if (!paymentForm.reportValidity()) return;

            const amount = Number($("nlidpPaymentAmount").value);
            const outstanding = Math.max(0, invoiceTotal - paidAmount);

            if (!Number.isFinite(amount) ||
                amount <= 0 || amount > outstanding + 0.005) {
                paymentMessage.textContent =
                    "Enter a valid amount within the outstanding balance.";
                paymentMessage.style.color = "#bd3030";
                return;
            }

            // Frontend demo update only.
            // Production: call backend, record payment transaction,
            // then refresh paid/outstanding values from DB.
            paidAmount = Math.round((paidAmount + amount) * 100) / 100;

            $("nlidpOutstanding").textContent =
                formatMoney(Math.max(0, invoiceTotal - paidAmount));

            const modal = bootstrap.Modal.getInstance(paymentModal);
            if (modal) modal.hide();

            $("nlidpFeedback").textContent =
                "Demo payment recorded for this session. Connect PHP/MySQL for permanent records.";
        });

        // Send invoice - placeholder for actual delivery workflow.
        $("nlidpSendInvoice").addEventListener("click", function() {
            $("nlidpFeedback").textContent =
                "Connect email or sharing service to send this invoice.";
        });
    });
</script>

<!-- reports script -->
<script>
    document.addEventListener("DOMContentLoaded", function() {
        if (window.NIVIS_DATABASE_UI) return;

        // Demo records only - replace with backend API.
        const invoices = [{
                date: "2026-10-03",
                invoice: "INV-0029",
                customer: "Ravi Enterprises",
                total: 18000,
                paid: 0
            },
            {
                date: "2026-10-10",
                invoice: "INV-0030",
                customer: "Sri Lakshmi Traders",
                total: 22000,
                paid: 10000
            },
            {
                date: "2026-10-12",
                invoice: "INV-0031",
                customer: "Sree Agencies",
                total: 14868,
                paid: 14868
            }
        ];

        const reports = [{
                name: "Sales report",
                metric: "Invoice turnover",
                breakdown: "Day / month",
                available: "PDF / CSV"
            },
            {
                name: "GST summary",
                metric: "CGST / SGST / IGST",
                breakdown: "Rate / state",
                available: "PDF / CSV"
            },
            {
                name: "Quotation conversion",
                metric: "Converted quote count",
                breakdown: "Customer / status",
                available: "PDF / CSV"
            },
            {
                name: "Payments",
                metric: "Payment collections",
                breakdown: "Date / method",
                available: "PDF / CSV"
            },
            {
                name: "Outstanding",
                metric: "Pending receivables",
                breakdown: "Customer / aging",
                available: "PDF / CSV"
            },
            {
                name: "Customers",
                metric: "Customer-wise sales",
                breakdown: "Customer / region",
                available: "PDF / CSV"
            },
            {
                name: "Products",
                metric: "Product-wise sales",
                breakdown: "Category / HSN",
                available: "PDF / CSV"
            }
        ];

        const form = document.getElementById("nlrFilterForm");
        const fromDate = document.getElementById("nlrFromDate");
        const toDate = document.getElementById("nlrToDate");
        const reportType = document.getElementById("nlrReportType");
        const tableBody = document.getElementById("nlrReportTableBody");
        const exportButton = document.getElementById("nlrExportCSV");

        if (!form || !tableBody) return;

        const money = value =>
            "Rs " + Number(value).toLocaleString("en-IN", {
                maximumFractionDigits: 2
            });

        let filteredInvoices = [];

        function applyFilters() {
            if (!fromDate.value || !toDate.value ||
                fromDate.value > toDate.value) {
                toDate.setCustomValidity(
                    "To date must be on or after from date."
                );
                toDate.reportValidity();
                return;
            }

            toDate.setCustomValidity("");

            filteredInvoices = invoices.filter(invoice =>
                invoice.date >= fromDate.value &&
                invoice.date <= toDate.value
            );

            const invoiced = filteredInvoices.reduce(
                (sum, row) => sum + row.total, 0
            );

            const collected = filteredInvoices.reduce(
                (sum, row) => sum + row.paid, 0
            );

            const outstanding = invoiced - collected;

            document.getElementById("nlrInvoicedSales").textContent =
                money(invoiced);

            document.getElementById("nlrCollected").textContent =
                money(collected);

            document.getElementById("nlrOutstanding").textContent =
                money(outstanding);

            renderReportPreview();
        }

        function renderReportPreview() {
            tableBody.replaceChildren();

            // The table lists report definitions as in the screenshot.
            // Selected report appears first.
            const selected = reportType.value;

            const typeMap = {
                sales: "Sales report",
                gst: "GST summary",
                quotation: "Quotation conversion",
                payments: "Payments",
                outstanding: "Outstanding",
                customers: "Customers",
                products: "Products"
            };

            const selectedName = typeMap[selected];

            const ordered = [
                ...reports.filter(item => item.name === selectedName),
                ...reports.filter(item => item.name !== selectedName)
            ];

            ordered.forEach(report => {
                const tr = document.createElement("tr");

                [
                    report.name,
                    report.metric,
                    report.breakdown,
                    report.available
                ].forEach(value => {
                    const td = document.createElement("td");
                    td.textContent = value;
                    tr.appendChild(td);
                });

                tableBody.appendChild(tr);
            });
        }

        function csvEscape(value) {
            let text = String(value ?? "");

            // Reduce spreadsheet formula injection when opened in Excel.
            if (/^[=+\-@\t\r]/.test(text)) {
                text = "'" + text;
            }

            return '"' + text.replace(/"/g, '""') + '"';
        }

        function exportCSV() {
            const headers = [
                "Invoice Number",
                "Date",
                "Customer",
                "Invoice Total",
                "Collected",
                "Outstanding"
            ];

            const rows = filteredInvoices.map(invoice => [
                invoice.invoice,
                invoice.date,
                invoice.customer,
                invoice.total.toFixed(2),
                invoice.paid.toFixed(2),
                (invoice.total - invoice.paid).toFixed(2)
            ]);

            const csv = [headers, ...rows]
                .map(row => row.map(csvEscape).join(","))
                .join("\r\n");

            const blob = new Blob(
                ["\uFEFF", csv], {
                    type: "text/csv;charset=utf-8;"
                }
            );

            const url = URL.createObjectURL(blob);
            const link = document.createElement("a");

            link.href = url;
            link.download =
                "nivis-report-" + reportType.value + "-" +
                fromDate.value + "-to-" + toDate.value + ".csv";

            document.body.appendChild(link);
            link.click();
            link.remove();

            URL.revokeObjectURL(url);
        }

        form.addEventListener("submit", function(event) {
            event.preventDefault();
            applyFilters();
        });

        [fromDate, toDate].forEach(input => {
            input.addEventListener("change", () => {
                toDate.setCustomValidity("");
            });
        });

        exportButton.addEventListener("click", exportCSV);

        applyFilters();

    });
</script>



<script src="assets/js/database-ui.js"></script>

</body>

</html>