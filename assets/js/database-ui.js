function nivisListControls(table, render) {
    const toolbar=document.createElement("div"); toolbar.className="row g-3 mb-3";
    const dates=[];
    for(const title of ["From date","To date"]) {const wrap=document.createElement("div");wrap.className="col-md-3";const label=document.createElement("label");const input=document.createElement("input");input.type="date";input.className="form-control";input.id=table.id+title.replaceAll(" ","");label.htmlFor=input.id;label.textContent=title;wrap.append(label,input);toolbar.append(wrap);dates.push(input);input.addEventListener("change",()=>{page=1;render();});}
    table.closest(".table-responsive").before(toolbar);
    const pager=document.createElement("div");pager.className="d-flex gap-3 align-items-center my-3";const prev=document.createElement("button"),next=document.createElement("button"),label=document.createElement("span");label.setAttribute("aria-live","polite");prev.textContent="Previous";next.textContent="Next";for(const b of [prev,next]){b.type="button";b.className="btn btn-outline-primary btn-sm";}pager.append(prev,label,next);table.closest(".table-responsive").after(pager);
    let page=1, signature="";prev.onclick=()=>{page--;render();};next.onclick=()=>{page++;render();};
    return records=>{records=records.filter(r=>(!dates[0].value||r.issue_date>=dates[0].value)&&(!dates[1].value||r.issue_date<=dates[1].value));const key=records.map(r=>r.id).join(",");if(key!==signature){page=1;signature=key;}const pages=Math.max(1,Math.ceil(records.length/10));page=Math.min(page,pages);prev.disabled=page<=1;next.disabled=page>=pages;label.textContent=records.length+" records · Page "+page+" of "+pages;return records.slice((page-1)*10,page*10);};
}
function nivisCustomerSearch(select, input, matches) {
    input.addEventListener("input", () => {
        matches.replaceChildren();
        const query = input.value.trim().toLocaleLowerCase();
        if (!query) return;
        const options = Array.from(select.options).filter(option => option.value && option.text.toLocaleLowerCase().includes(query));
        for (const option of options) {
            const button = document.createElement("button");
            button.type = "button";
            button.className = "list-group-item list-group-item-action";
            button.textContent = option.text;
            button.addEventListener("click", () => {
                select.value = option.value;
                select.dispatchEvent(new Event("change", { bubbles: true }));
                input.value = "";
                matches.replaceChildren();
            });
            matches.append(button);
        }
        if (!options.length) matches.textContent = "No matching customers.";
    });
}
window.NIVIS_DATABASE_UI = true;

async function nivisApi(resource, method = "GET", data = null, id = null) {
    const url = new URL("api.php", window.location.href);
    url.searchParams.set("resource", resource);
    if (id !== null) url.searchParams.set("id", id);

    const headers = data && !(data instanceof FormData) ? { "Content-Type": "application/json" } : {};
    if (method !== "GET") {
        headers["X-CSRF-Token"] = document.querySelector('meta[name="csrf-token"]')?.content || "";
        const fingerprint = resource + method + id + JSON.stringify(data);
        nivisApi.keys ||= new Map();
        if (!nivisApi.keys.has(fingerprint)) nivisApi.keys.set(fingerprint, crypto.randomUUID());
        if (!(data instanceof FormData)) headers["Idempotency-Key"] = nivisApi.keys.get(fingerprint);
    }
    const response = await fetch(url, {
        method,
        headers,
        body: data instanceof FormData ? data : data ? JSON.stringify(data) : undefined
    });
    let result;
    try { result = await response.json(); }
    catch { throw new Error("The server returned an invalid response (HTTP " + response.status + "). Check the PHP error log and upload size settings."); }
    if (!response.ok) {
        throw new Error((result.error || "The database request failed.") + (result.code ? " [" + result.code + "]" : ""));
    }
    if (method !== "GET") nivisApi.keys.delete(resource + method + id + JSON.stringify(data));
    return result;
}

function nivisMessage(element, text, isError = false) {
    if (!element) return;
    element.textContent = text;
    element.style.color = isError ? "#c0392b" : "#078966";
}

function nivisActionButton(action, label, icon) {
    const button = document.createElement("button");
    button.type = "button";
    button.className = "nivis_customer_icon_btn action-" + action;
    button.dataset.action = action;
    button.title = label;
    button.setAttribute("aria-label", label);
    button.innerHTML = '<i class="fa-solid ' + icon + '" aria-hidden="true"></i>';
    const caption = document.createElement("span");
    caption.textContent = label.split(" ")[0];
    button.appendChild(caption);
    return button;
}

document.addEventListener("DOMContentLoaded", () => {
    const productTable = document.getElementById("nivisProductTableBody");
    if (productTable) {
        const products = [];
        const categories = [];
        const search = document.getElementById("nivisProductSearch");
        const categoryFilter = document.getElementById("nivisProductCategoryFilter");
        const gstFilter = document.getElementById("nivisProductGSTFilter");
        const empty = document.getElementById("nivisProductEmpty");
        const form = document.getElementById("nivisProductForm");
        const modal = bootstrap.Modal.getOrCreateInstance(
            document.getElementById("nivisProductModal")
        );
        const message = document.getElementById("nivisProductMessage");
        const modalTitle = document.getElementById("nivisProductModalTitle");
        const saveButton = form.querySelector('button[type="submit"]');
        let editingId = null;
        let productPriceMode = "exclusive";

        function setCategoryOptions() {
            const options = categories.map(category =>
                new Option(category.name, category.id)
            );
            const modalSelect = document.getElementById("productCategory");
            const originalFilter = categoryFilter.value;
            modalSelect.replaceChildren(new Option("Select category", ""), ...options);
            categoryFilter.replaceChildren(new Option("All categories", "all"));
            categories.forEach(category =>
                categoryFilter.add(new Option(category.name, category.name))
            );
            categoryFilter.value = categories.some(
                item => item.name === originalFilter
            ) ? originalFilter : "all";
        }

        function renderProducts() {
            const query = search.value.toLowerCase().trim();
            const category = categoryFilter.value;
            const gst = gstFilter.value;
            const filtered = products.filter(product =>
                (product.name + " " + product.sku + " " + product.hsn)
                    .toLowerCase().includes(query) &&
                (category === "all" || product.category === category) &&
                (gst === "all" || Number(product.gst) === Number(gst))
            );

            productTable.replaceChildren();
            filtered.forEach(product => {
                const row = document.createElement("tr");
                [
                    product.id,
                    product.name,
                    product.category,
                    "Rs " + Number(product.price).toLocaleString("en-IN", {minimumFractionDigits: 2}),
                    Number(product.quantity),
                    Number(product.quantity) > 0 && product.status === "active" ? "Available" : "Not available"
                ].forEach(value => {
                    const cell = document.createElement("td");
                    cell.textContent = value;
                    row.appendChild(cell);
                });
                const actions = document.createElement("td");
                const photoCell = document.createElement("td");
                if (product.image_path) {
                    const photo = document.createElement("img");
                    photo.src = product.image_path; photo.alt = product.name;
                    photo.className = "product-photo-thumb"; photo.loading = "lazy";
                    photoCell.appendChild(photo);
                } else { photoCell.textContent = "No photo"; }
                row.insertBefore(photoCell, row.children[1]);
                actions.className = "nivis-produdcts-actions";
                [["view", "View product", "fa-eye"],
                    ["edit", "Edit product", "fa-pen-to-square"],
                    ["delete", "Delete product", "fa-trash-can"]]
                    .forEach(([action, label, icon]) => {
                        const button = nivisActionButton(action, label, icon);
                        button.className = "nivis-produdcts-icon-action action-" + action;
                        button.dataset.id = product.id;
                        button.setAttribute("aria-label", label + ": " + product.name);
                        actions.appendChild(button);
                    });
                row.appendChild(actions);
                productTable.appendChild(row);
            });
            empty.classList.toggle("d-none", filtered.length > 0);
        }

        function populateProductForm(product = null, readOnly = false) {
            form.reset();
            const photo = document.getElementById("productImagePreview");
            photo.hidden = !product?.image_path;
            if (product?.image_path) photo.src = product.image_path;
            else photo.removeAttribute("src");
            editingId = product ? Number(product.id) : null;
            document.getElementById("productName").value = product?.name || "";
            document.getElementById("productSKU").value = product?.sku || "";
            document.getElementById("productCategory").value =
                product?.category_id || "";
            document.getElementById("productHSN").value = product?.hsn || "";
            document.getElementById("productUnit").value = product?.unit || "PCS";
            document.getElementById("productPrice").value = product?.price ?? "";
            document.getElementById("productGST").value = String(Number(product?.gst ?? 18));
            document.getElementById("productStatus").value =
                product?.status === "inactive" ? "Inactive" : "Active";
            document.getElementById("productDiscount").value =
                product?.discount ?? 0;
            document.getElementById("productDescription").value =
                product?.description || "";
            document.getElementById("productSerial").value = product?.id || "";
            document.getElementById("productQuantity").value = product?.quantity ?? 1;
            document.getElementById("productRegion").value = product?.gst_region === "outside_ap" ? "Outside AP (IGST)" : "Inside AP (CGST + SGST)";
            const amounts = nivisAmounts(Number(product?.quantity ?? 1), Number(product?.price || 0), Number(product?.discount || 0), Number(product?.gst || 0), productPriceMode);
            document.getElementById("productTotal").value = nivisMoney(amounts.total);
            document.getElementById("productTaxSplit").textContent = product?.gst_region === "outside_ap" ? "IGST " + nivisMoney(amounts.tax) : "CGST " + nivisMoney(amounts.cgst) + " + SGST " + nivisMoney(amounts.sgst);
            modalTitle.textContent = readOnly ? "Product details" :
                product ? "Edit product" : "Add product";
            form.querySelectorAll("input, select, textarea").forEach(field => {
                field.disabled = readOnly;
            });
            saveButton.classList.toggle("d-none", readOnly);
            saveButton.textContent = product ? "Update product" : "Save product";
            nivisMessage(message, "");
            modal.show();
        }

        async function refresh() {
            const [productResult, categoryResult, taxResult] = await Promise.all([
                nivisApi("products"),
                nivisApi("categories"),
                nivisApi("tax")
            ]);
            productPriceMode = taxResult.configuration?.price_mode || "exclusive";
            products.splice(0, products.length, ...productResult.data);
            categories.splice(0, categories.length, ...categoryResult.data);
            setCategoryOptions();
            const gstSelect = document.getElementById("productGST");
            const gstFilter = document.getElementById("nivisProductGSTFilter");
            const selectedGst = gstSelect.value;
            gstSelect.replaceChildren(...taxResult.rates.map(rate =>
                new Option(Number(rate.rate_percent) + "%", Number(rate.rate_percent))
            ));
            gstFilter.replaceChildren(new Option("All GST rates", "all"));
            taxResult.rates.forEach(rate =>
                gstFilter.add(new Option(
                    rate.name + " (" + Number(rate.rate_percent) + "%)", rate.id
                ))
            );
            gstSelect.value = taxResult.rates.some(rate =>
                String(rate.rate_percent) === selectedGst
            ) ? selectedGst : "18";
            renderProducts();
        }

        document.getElementById("nivisProductAddBtn").addEventListener(
            "click", () => { window.location.href = "add-edit-product.php"; }
        );
        [search, categoryFilter, gstFilter].forEach(control =>
            control.addEventListener(control === search ? "input" : "change", renderProducts)
        );
        productTable.addEventListener("click", async event => {
            const button = event.target.closest("[data-action][data-id]");
            if (!button) return;
            const product = products.find(item => Number(item.id) === Number(button.dataset.id));
            if (!product) return;
            if (button.dataset.action === "delete") {
                if (!window.confirm("Delete " + product.name + "? This change is permanent.")) return;
                try {
                    await nivisApi("products", "DELETE", null, product.id);
                    await refresh();
                } catch (error) {
                    window.alert(error.message);
                }
                return;
            }
            if (button.dataset.action === "edit") { window.location.href = "add-edit-product.php?id=" + product.id; return; }
            populateProductForm(product, true);
        });
        form.addEventListener("submit", async event => {
            event.preventDefault();
            if (!form.reportValidity()) return;
            const product = {
                id: editingId,
                name: document.getElementById("productName").value.trim(),
                sku: document.getElementById("productSKU").value.trim().toUpperCase(),
                category_id: document.getElementById("productCategory").value,
                hsn: document.getElementById("productHSN").value.trim(),
                unit: document.getElementById("productUnit").value.toUpperCase(),
                price: Number(document.getElementById("productPrice").value),
                gst: Number(document.getElementById("productGST").value),
                discount: Number(document.getElementById("productDiscount").value || 0),
                status: document.getElementById("productStatus").value.toLowerCase(),
                description: document.getElementById("productDescription").value.trim()
            };
            try {
                await nivisApi("products", "POST", product);
                await refresh();
                modal.hide();
            } catch (error) {
                nivisMessage(message, error.message, true);
            }
        });
        refresh().then(() => {
            if (new URLSearchParams(window.location.search).get("action") === "create") {
                window.location.href = "add-edit-product.php";
            }
        }).catch(error => {
            empty.textContent = error.message;
            empty.classList.remove("d-none");
            nivisMessage(message, error.message, true);
        });
    }

    const productForm = document.getElementById("addEditProductForm");
    if (productForm) {
        const message = document.getElementById("addEditProductMessage");
        const idField = document.getElementById("editProductId");
        const categorySelect = document.getElementById("editProductCategory");
        const gstSelect = document.getElementById("editProductGST");
        const customerSelect = document.getElementById("editProductCustomer");
        nivisCustomerSearch(customerSelect, document.getElementById("editProductCustomerSearch"), document.getElementById("editProductCustomerMatches"));
        const imageInput = document.getElementById("editProductImage");
        const imagePreview = document.getElementById("editProductImagePreview");
        let savedImage = "";
        let previewUrl = null;
        imageInput.addEventListener("change", () => {
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            const file = imageInput.files[0];
            if (file && file.size > 50 * 1024 * 1024) {
                imageInput.value = "";
                nivisMessage(message,"Choose an image up to 50 MB.",true);
            } else { nivisMessage(message,""); }
            previewUrl = imageInput.files[0] ? URL.createObjectURL(imageInput.files[0]) : null;
            imagePreview.hidden = !(previewUrl || savedImage);
            if (previewUrl || savedImage) imagePreview.src = previewUrl || savedImage;
            else imagePreview.removeAttribute("src");
        });
        let rates = [];
        let priceMode = "exclusive";
        function updateProductTotal() {
            const value = id => Number(document.getElementById(id).value) || 0;
            const amounts = nivisAmounts(value("editProductQuantity"), value("editProductPrice"), value("editProductDiscount"), Number(rates.find(r => String(r.id) === gstSelect.value)?.rate_percent || 0), priceMode);
            document.getElementById("editProductTotal").value = nivisMoney(amounts.total);
            document.getElementById("editProductTaxSplit").textContent = document.getElementById("editProductRegion").value === "inside_ap"
                ? "CGST " + nivisMoney(amounts.cgst) + " + SGST " + nivisMoney(amounts.sgst)
                : "IGST " + nivisMoney(amounts.tax);
        }
        productForm.addEventListener("input", updateProductTotal);
        productForm.addEventListener("change", updateProductTotal);

        document.getElementById("addEditProductSave").disabled = true;
        async function loadProductForm() {
            const [categoryResult, taxResult, customerResult] = await Promise.all([
                nivisApi("categories"),
                nivisApi("tax"), nivisApi("customers")
            ]);
            categorySelect.replaceChildren(new Option("Select category", ""));
            categoryResult.data.forEach(category =>
                categorySelect.add(new Option(category.name, category.id))
            );
            customerResult.data.forEach(customer => customerSelect.add(new Option(customer.name + " - " + customer.mobile, customer.id)));
            customerSelect.addEventListener("change", () => {
                const customer = customerResult.data.find(c => String(c.id) === customerSelect.value);
                if (customer) document.getElementById("editProductRegion").value = (customer.shipping_state || customer.state) === "Andhra Pradesh" ? "inside_ap" : "outside_ap";
                updateProductTotal();
            });
            document.getElementById("editProductRegion").value = taxResult.configuration?.default_region || "inside_ap";
            priceMode = taxResult.configuration?.price_mode || "exclusive";
            const today = new Date().toLocaleDateString("en-CA");
            rates = taxResult.rates.filter(rate => Number(rate.is_enabled) && rate.effective_from <= today && (!rate.effective_to || rate.effective_to >= today));
            gstSelect.replaceChildren(new Option("Select GST rate", ""));
            rates.forEach(rate =>
                gstSelect.add(new Option(
                    rate.name + " (" + Number(rate.rate_percent) + "%)", rate.id
                ))
            );
            const id = new URLSearchParams(window.location.search).get("id");
            if (!id) { categorySelect.value = new URLSearchParams(location.search).get("category_id") || ""; updateProductTotal(); return; }
            const response = await nivisApi("products");
            const product = response.data.find(item => String(item.id) === id);
            if (!product) throw new Error("Product not found.");
            savedImage = product.image_path || "";
            imagePreview.hidden = !savedImage;
            if (savedImage) imagePreview.src = savedImage;
            idField.value = product.id;
            if (product.customer_id && !Array.from(customerSelect.options).some(o => o.value === String(product.customer_id))) customerSelect.add(new Option("Previously selected customer #" + product.customer_id, product.customer_id));
            customerSelect.value = product.customer_id || "";
            document.getElementById("editProductSerial").value = product.id;
            document.getElementById("editProductQuantity").value = product.quantity;
            document.getElementById("editProductRegion").value = product.gst_region;
            document.getElementById("editProductName").value = product.name;
            document.getElementById("editProductSKU").value = product.sku;
            categorySelect.value = product.category_id;
            document.getElementById("editProductHSN").value = product.hsn;
            document.getElementById("editProductUnit").value = product.unit;
            document.getElementById("editProductPrice").value = product.price;
            gstSelect.value = String(product.tax_rate_id);
            document.getElementById("editProductDiscount").value = product.discount;
            document.getElementById("editProductStatus").value = product.status === "inactive" ? "Inactive" : "Active";
            document.getElementById("editProductDescription").value = product.description || "";
            updateProductTotal();
        }

        productForm.addEventListener("submit", async event => {
            event.preventDefault();
            if (!productForm.reportValidity()) return;
            const data = {
                id: idField.value || null,
                customer_id: customerSelect.value,
                quantity: Number(document.getElementById("editProductQuantity").value),
                gst_region: document.getElementById("editProductRegion").value,
                name: document.getElementById("editProductName").value.trim(),
                sku: document.getElementById("editProductSKU").value.trim().toUpperCase(),
                category_id: categorySelect.value,
                hsn: document.getElementById("editProductHSN").value.trim(),
                unit: document.getElementById("editProductUnit").value,
                price: Number(document.getElementById("editProductPrice").value),
                gst: Number(rates.find(r => String(r.id) === gstSelect.value)?.rate_percent || 0),
                tax_rate_id: gstSelect.value,
                discount: Number(document.getElementById("editProductDiscount").value || 0),
                status: document.getElementById("editProductStatus").value.toLowerCase(),
                description: document.getElementById("editProductDescription").value.trim()
            };
            try {
                const payload = new FormData();
                Object.entries(data).forEach(([key,value]) => { if (value !== null) payload.append(key,value); });
                if (imageInput.files[0]) payload.append("image",imageInput.files[0]);
                document.getElementById("addEditProductSave").disabled = true;
                await nivisApi("products", "POST", payload);
                window.location.href = "products.php";
            } catch (error) {
                document.getElementById("addEditProductSave").disabled = false;
                nivisMessage(message, error.message, true);
            }
        });
        loadProductForm().then(() => { document.getElementById("addEditProductSave").disabled = false; }).catch(error => nivisMessage(message, error.message, true));
    }

    const categoryForm = document.getElementById("nivisCategoryForm");
    if (categoryForm) {
        const rows = document.getElementById("nivisCategoryTableBody");
        const categories = [];
        const idField = document.getElementById("nivisCategoryId");
        const message = document.getElementById("nivisCategoryMessage");
        const empty = document.getElementById("nivisCategoryEmpty");
        const field = id => document.getElementById(id);

        function renderCategories() {
            rows.replaceChildren();
            categories.forEach(category => {
                const row = document.createElement("tr");
                row.dataset.id = category.id;
                [category.name, category.products, category.status].forEach(value => {
                    const cell = document.createElement("td");
                    cell.textContent = value;
                    row.appendChild(cell);
                });
                const actions = document.createElement("td");
                [["edit", "Edit category", "fa-pen-to-square"],
                    ["delete", "Delete category", "fa-trash-can"]]
                    .forEach(([action, label, icon]) => {
                        const button = nivisActionButton(action, label, icon);
                        button.dataset.id = category.id;
                        actions.appendChild(button);
                    });
                row.appendChild(actions);
                rows.appendChild(row);
            });
            empty.classList.toggle("d-none", categories.length > 0);
        }

        async function refreshCategories() {
            const result = await nivisApi("categories");
            categories.splice(0, categories.length, ...result.data);
            renderCategories();
        }

        function resetCategory() {
            categoryForm.reset();
            field("nivisCategoryAddProduct").hidden = true;
            field("nivisCategoryLinkedProducts").textContent = "";
            idField.value = "";
            field("nivisCategoryStatus").value = "Active";
            nivisMessage(message, "");
        }

        document.getElementById("nivisCategoryNewBtn").addEventListener("click", resetCategory);
        rows.addEventListener("click", async event => {
            const button = event.target.closest("[data-action][data-id]");
            if (!button) return;
            const category = categories.find(item => Number(item.id) === Number(button.dataset.id));
            if (!category) return;
            if (button.dataset.action === "edit") {
                idField.value = category.id;
                field("nivisCategoryProducts").value = category.product_count ?? "";
                field("nivisCategoryLinkedProducts").textContent = "Linked products: " + category.linked_products;
                field("nivisCategoryAddProduct").href = "add-edit-product.php?category_id=" + category.id;
                field("nivisCategoryAddProduct").hidden = false;
                field("nivisCategoryName").value = category.name;
                field("nivisCategoryCode").value = category.code;
                field("nivisCategoryDescription").value = category.description || "";
                field("nivisCategoryStatus").value =
                    category.status === "inactive" ? "Inactive" : "Active";
                return;
            }
            if (!window.confirm("Delete " + category.name + "?")) return;
            try {
                await nivisApi("categories", "DELETE", null, category.id);
                await refreshCategories();
                resetCategory();
            } catch (error) {
                nivisMessage(message, error.message, true);
            }
        });
        categoryForm.addEventListener("submit", async event => {
            event.preventDefault();
            if (!categoryForm.reportValidity()) return;
            try {
                await nivisApi("categories", "POST", {
                    id: idField.value || null,
                    name: field("nivisCategoryName").value.trim(),
                    code: field("nivisCategoryCode").value.trim().toUpperCase(),
                    product_count: field("nivisCategoryProducts").value === "" ? null : Number(field("nivisCategoryProducts").value),
                    description: field("nivisCategoryDescription").value.trim(),
                    status: field("nivisCategoryStatus").value.toLowerCase()
                });
                await refreshCategories();
                resetCategory();
                nivisMessage(message, "Category saved.");
            } catch (error) {
                nivisMessage(message, error.message, true);
            }
        });
        refreshCategories().catch(error => nivisMessage(message, error.message, true));
    }

    const customerTable = document.getElementById("nivisCustomerTableBody");
    if (customerTable) {
        const customers = [];
        const form = document.getElementById("nivisCustomerForm");
        const modal = bootstrap.Modal.getOrCreateInstance(
            document.getElementById("nivisCustomerModal")
        );
        const message = document.getElementById("nivisCustomerFormMessage");
        const saveButton = document.getElementById("nivisCustomerSaveBtn");
        const search = document.getElementById("nivisCustomerSearch");
        const typeFilter = document.getElementById("nivisCustomerType");
        const empty = document.getElementById("nivisCustomerEmpty");
        let selectedCustomer = null;
        let mode = "add";
        const field = name => form.elements.namedItem(name);

        function renderCustomers() {
            const query = search.value.toLowerCase().trim();
            const type = typeFilter.value;
            const filtered = customers.filter(customer => {
                const registered = customer.gst_registration === "registered";
                return (customer.name + " " + customer.mobile + " " +
                    (customer.gstin || "")).toLowerCase().includes(query) &&
                    (type === "all" ||
                        (type === "registered" && registered) ||
                        (type === "unregistered" && !registered));
            });
            customerTable.replaceChildren();
            filtered.forEach(customer => {
                const row = document.createElement("tr");
                row.dataset.id = customer.id;
                [
                    customer.name + " ? " + (customer.customer_category === "CB2" ? "CB2 / Bulk order" : "CB1 / Regular"),
                    customer.mobile,
                    customer.gstin || "Unregistered",
                    customer.state || "-"
                ].forEach(value => {
                    const cell = document.createElement("td");
                    cell.textContent = value;
                    row.appendChild(cell);
                });
                const actions = document.createElement("td");
                actions.className = "nivis_customer_actions";
                [["view", "View customer", "fa-eye"],
                    ["edit", "Edit customer", "fa-pen-to-square"],
                    ["delete", "Delete customer", "fa-trash-can"]]
                    .forEach(([action, label, icon]) => {
                        const button = nivisActionButton(action, label, icon);
                        button.dataset.id = customer.id;
                        actions.appendChild(button);
                    });
                row.appendChild(actions);
                customerTable.appendChild(row);
            });
            empty.classList.toggle("d-none", filtered.length > 0);
        }

        async function refreshCustomers() {
            const result = await nivisApi("customers");
            customers.splice(0, customers.length, ...result.data);
            renderCustomers();
        }

        function openCustomer(customer = null, readOnly = false) {
            form.reset();
            selectedCustomer = customer;
            mode = readOnly ? "view" : customer ? "edit" : "add";
            document.getElementById("nivisCustomerModalTitle").textContent =
                readOnly ? "Customer details" : customer ? "Edit customer" : "Add customer";
            if (customer) {
                field("customer_name").value = customer.name;
                field("mobile").value = customer.mobile;
                field("email").value = customer.email || "";
                field("customer_type").value =
                    customer.gst_registration === "registered" ? "registered" : "unregistered";
                field("gstin").value = customer.gstin || "";
                field("state").value = customer.state || "";
                field("billing_address").value = customer.billing_address || "";
                field("city").value = customer.city || "";
                field("pincode").value = customer.pincode || "";
                field("shipping_address").value = customer.shipping_address || "";
                field("shipping_city").value = customer.shipping_city || "";
                field("shipping_pincode").value = customer.shipping_pincode || "";
            }
            form.querySelectorAll("input, select, textarea").forEach(input => {
                input.disabled = readOnly;
            });
            saveButton.classList.toggle("d-none", readOnly);
            saveButton.textContent = customer ? "Update customer" : "Save customer";
            nivisMessage(message, "");
            modal.show();
        }

        document.getElementById("nivisCustomerAddBtn").addEventListener(
            "click", () => { window.location.href = "add-edit-customer.php"; }
        );
        [search, typeFilter].forEach(control =>
            control.addEventListener(control === search ? "input" : "change", renderCustomers)
        );
        document.getElementById("nivisCustomerFilterBtn")
            .addEventListener("click", renderCustomers);
        customerTable.addEventListener("click", async event => {
            const button = event.target.closest("[data-action][data-id]");
            if (!button) return;
            const customer = customers.find(item => Number(item.id) === Number(button.dataset.id));
            if (!customer) return;
            if (button.dataset.action === "delete") {
                if (!window.confirm("Remove " + customer.name + " from the customer list? Existing invoices and quotations will be preserved.")) return;
                try {
                    await nivisApi("customers", "DELETE", null, customer.id);
                    await refreshCustomers();
                } catch (error) {
                    window.alert(error.message);
                }
                return;
            }
            if (button.dataset.action === "edit") { window.location.href = "add-edit-customer.php?id=" + customer.id; return; }
            openCustomer(customer, true);
        });
        form.addEventListener("submit", async event => {
            event.preventDefault();
            if (!form.reportValidity()) return;
            const registered = field("customer_type").value === "registered";
            try {
                await nivisApi("customers", "POST", {
                    id: selectedCustomer?.id || null,
                    name: field("customer_name").value.trim(),
                    mobile: field("mobile").value.trim(),
                    email: field("email").value.trim(),
                    customer_type: registered ? "business" : "unregistered",
                    gst_registration: registered ? "registered" : "unregistered",
                    gstin: field("gstin").value.trim().toUpperCase(),
                    state: field("state").value,
                    billing_address: field("billing_address").value.trim(),
                    city: field("city").value.trim(),
                    pincode: field("pincode").value.trim(),
                    shipping_address: field("shipping_address").value.trim(),
                    shipping_city: field("shipping_city").value.trim(),
                    shipping_pincode: field("shipping_pincode").value.trim()
                });
                modal.hide();
                await refreshCustomers();
            } catch (error) {
                nivisMessage(message, error.message, true);
            }
        });
        refreshCustomers().then(() => {
            if (new URLSearchParams(window.location.search).get("action") === "create") {
                window.location.href = "add-edit-customer.php";
            }
        }).catch(error => {
            empty.textContent = error.message;
            empty.classList.remove("d-none");
        });
    }

    const customerForm = document.getElementById("customerForm");
    if (customerForm) {
        const message = document.getElementById("customerFormMessage");
        const customerType = document.getElementById("customerType");
        const gstin = document.getElementById("customerGSTIN");
        const id = new URLSearchParams(window.location.search).get("id");
        const get = name => customerForm.elements.namedItem(name);
        const sameBilling = document.getElementById("sameBilling");
        const shippingFields = document.getElementById("shippingAddressFields");
        const shippingInputs = shippingFields.querySelectorAll("input, select");

        const addressPairs = [['billing_address','shipping_address'], ['city','shipping_city'], ['pincode','shipping_pincode'], ['state','shipping_state']];
        function updateShippingFields() {
            shippingFields.classList.remove("d-none");
            if (sameBilling.checked) addressPairs.forEach(([source, target]) => get(target).value = get(source).value);
            shippingInputs.forEach(input => {
                input.disabled = sameBilling.checked;
                input.required = !sameBilling.checked;
            });
        }
        sameBilling.addEventListener("change", updateShippingFields);
        addressPairs.forEach(([source]) => get(source).addEventListener("input", updateShippingFields));
        get("region").addEventListener("change", () => {
            if (get("region").value === "inside_ap") get("state").value = "Andhra Pradesh";
            else if (get("state").value === "Andhra Pradesh") get("state").value = "";
            updateShippingFields();
        });
        get("state").addEventListener("change", () => {
            get("region").value = get("state").value === "Andhra Pradesh" ? "inside_ap" : "outside_ap";
            updateShippingFields();
        });
        get("state").value = "Andhra Pradesh";
        updateShippingFields();

        function updateCustomerType() {
            const isBusiness = customerType.value === "business";
            gstin.required = isBusiness;
            gstin.disabled = !isBusiness;
            if (!isBusiness) gstin.value = "";
        }
        customerType.addEventListener("change", updateCustomerType);
        gstin.addEventListener("input", () => {
            gstin.value = gstin.value.toUpperCase().replace(/\s/g, "");
            gstin.setCustomValidity("");
        });
        updateCustomerType();

        async function loadCustomer() {
            if (!id) return;
            const result = await nivisApi("customers");
            const customer = result.data.find(item => String(item.id) === id);
            if (!customer) throw new Error("Customer not found.");
            get("customer_name").value = customer.name;
            get("mobile").value = customer.mobile;
            get("email").value = customer.email || "";
            customerType.value = customer.customer_type;
            get("customer_category").value = customer.customer_category || "CB1";
            get("region").value = customer.state === "Andhra Pradesh" ? "inside_ap" : "outside_ap";
            gstin.value = customer.gstin || "";
            updateCustomerType();
            get("state").value = customer.state || "";
            get("billing_address").value = customer.billing_address || "";
            get("city").value = customer.city || "";
            get("pincode").value = customer.pincode || "";
            if (customer.shipping_address) {
                sameBilling.checked = addressPairs.every(([source, target]) => (customer[source] || "") === (customer[target] || ""));
                get("shipping_address").value = customer.shipping_address;
                get("shipping_city").value = customer.shipping_city || "";
                get("shipping_pincode").value = customer.shipping_pincode || "";
                get("shipping_state").value = customer.shipping_state || "";
            }
            updateShippingFields();
        }

        customerForm.addEventListener("submit", async event => {
            event.preventDefault();
            if (!customerForm.reportValidity()) return;
            const registration = customerType.value === "business" && gstin.value.trim()
                ? "registered" : "unregistered";
            try {
                await nivisApi("customers", "POST", {
                    id: id || null,
                    name: get("customer_name").value.trim(),
                    mobile: get("mobile").value.trim(),
                    email: get("email").value.trim(),
                    customer_type: customerType.value,
                    customer_category: get("customer_category").value,
                    same_as_billing: sameBilling.checked,
                    gst_registration: registration,
                    gstin: gstin.value.trim().toUpperCase(),
                    state: get("state").value,
                    billing_address: get("billing_address").value.trim(),
                    city: get("city").value.trim(),
                    pincode: get("pincode").value.trim(),
                    shipping_address: get("shipping_address").value.trim(),
                    shipping_city: get("shipping_city").value.trim(),
                    shipping_pincode: get("shipping_pincode").value.trim(),
                    shipping_state: get("shipping_state").value
                });
                window.location.href = "customers.php";
            } catch (error) {
                nivisMessage(message, error.message, true);
            }
        });
        loadCustomer().catch(error => nivisMessage(message, error.message, true));
    }

    const quotationTable = document.getElementById("nivisQuotationTableBody");
    if (quotationTable) {
        const quotations = [];
        const search = document.getElementById("nivisQuotationSearch");
        const customerFilter = document.getElementById("nivisQuotationCustomer");
        const statusFilter = document.getElementById("nivisQuotationStatus");
        const empty = document.getElementById("nivisQuotationEmpty");
        let selectedQuote = null;

        function formatMoney(value) {
            return "Rs " + Number(value).toLocaleString("en-IN", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        const quotePage=nivisListControls(quotationTable,renderQuotations);
        function renderQuotations() {
            const query = search.value.toLowerCase().trim();
            const customer = customerFilter.value;
            const status = statusFilter.value.toLowerCase();
            const filtered = quotations.filter(quote =>
                (quote.quote_number + " " + quote.customer + " " +
                    quote.products.join(" ")).toLowerCase().includes(query) &&
                (customer === "all" || quote.customer === customer) &&
                (status === "all" || quote.status === status)
            );
            quotationTable.replaceChildren();
            const visibleQuotes=quotePage(filtered);
            visibleQuotes.forEach(quote => {
                const row = document.createElement("tr");
                [
                    quote.quote_number,
                    quote.customer,
                    quote.products.join(", "),
                    quote.issue_date,
                    formatMoney(quote.grand_total)
                ].forEach(value => {
                    const cell = document.createElement("td");
                    cell.textContent = value;
                    row.appendChild(cell);
                });
                const statusCell = document.createElement("td");
                const badge = document.createElement("span");
                badge.className = "nivis-labs-quotation-status status-" + quote.status;
                badge.textContent = ["draft","sent","approved"].includes(quote.status) ? "Saved" : quote.status.charAt(0).toUpperCase() + quote.status.slice(1);
                statusCell.appendChild(badge);
                row.appendChild(statusCell);

                const actions = document.createElement("td");
                actions.className = "nivis-labs-quotation-actions";
                const view=nivisActionButton("view","View","fa-eye");
                view.className="btn btn-outline-primary btn-sm";view.dataset.id=quote.id;actions.append(view);
                if (["draft","sent","approved"].includes(quote.status) && !quote.invoice_id) {
                    const convert = document.createElement("button");
                    convert.type = "button";
                    convert.className = "nivis-labs-quotation-action nivis-labs-quotation-convert";
                    convert.textContent = "Convert to Invoice";
                    convert.dataset.action = "convert";
                    convert.dataset.id = quote.id;
                    actions.appendChild(convert);
                }
                if (quote.status === "converted" && quote.invoice_id) {
                    const invoiceLink = document.createElement("a");
                    invoiceLink.className = "nivis-labs-quotation-action";
                    invoiceLink.href = "add-edit-invoice.php?id=" + encodeURIComponent(quote.invoice_id);
                    invoiceLink.textContent = "Invoice";
                    actions.appendChild(invoiceLink);
                }
                row.appendChild(actions);
                quotationTable.appendChild(row);
            });
            empty.classList.toggle("d-none", visibleQuotes.length > 0);
            ["draft", "sent", "approved", "converted"].forEach(status => {
                const node = document.getElementById("quote" +
                    status.charAt(0).toUpperCase() + status.slice(1) + "Count");
                if (node) node.textContent = quotations.filter(q => q.status === status).length;
            });
        }

        async function refreshQuotations() {
            const [quoteResult, customerResult] = await Promise.all([
                nivisApi("quotations"),
                nivisApi("customers")
            ]);
            quotations.splice(0, quotations.length, ...quoteResult.data);
            const selected = customerFilter.value;
            customerFilter.replaceChildren(new Option("All customers", "all"));
            customerResult.data.forEach(customer =>
                customerFilter.add(new Option(customer.name, customer.name))
            );
            customerFilter.value = customerResult.data.some(item => item.name === selected)
                ? selected : "all";
            renderQuotations();
        }

        quotationTable.addEventListener("click", async event => {
            const button = event.target.closest("[data-action][data-id]");
            if (!button) return;
            const quote = quotations.find(item => Number(item.id) === Number(button.dataset.id));
            if (!quote) return;
            if(button.dataset.action==="copy") {location.href="create-quotation.php?copy="+quote.id;return;}
            if(button.dataset.action==="view") {location.href="document.php?type=quotation&id="+quote.id;return;}
            if (button.dataset.action === "edit") {
                window.location.href = "create-quotation.php?id=" + encodeURIComponent(quote.id);
                return;
            }
            if (button.dataset.action === "delete") {
                if (!window.confirm("Delete draft " + quote.quote_number + "?")) return;
                try {
                    await nivisApi("quotations", "DELETE", null, quote.id);
                    await refreshQuotations();
                } catch (error) {
                    window.alert(error.message);
                }
                return;
            }
            if (button.dataset.action === "convert") {
                if(button.disabled)return;button.disabled=true;button.textContent="Converting...";
                try {
                    await nivisApi("invoices","POST",{action:"convert_simple",quotation_id:quote.id});
                    location.href="invoices.php?converted=1";
                } catch(error) {window.alert(error.message);button.disabled=false;button.textContent="Convert to Invoice";}
                return;
            }
            document.getElementById("nivisQuotationViewNumber").textContent = quote.quote_number;
            document.getElementById("nivisQuotationViewCustomer").textContent = quote.customer;
            try {
                const detail = (await nivisApi("quotations", "GET", null, quote.id)).data;
                document.getElementById("nivisQuotationViewProducts").textContent = detail.items.map(item => item.product_name + " (" + item.reference + ")").join(", ");
            } catch (error) { window.alert(error.message); return; }
            document.getElementById("nivisQuotationViewDate").textContent = quote.issue_date;
            document.getElementById("nivisQuotationViewTotal").textContent = formatMoney(quote.grand_total);
            document.getElementById("nivisQuotationViewStatus").textContent = quote.status;
            bootstrap.Modal.getOrCreateInstance(
                document.getElementById("nivisQuotationViewModal")
            ).show();
        });
        document.getElementById("nivisQuotationConfirmConvert")
            .addEventListener("click", async () => {
                if (!selectedQuote) return;
                try {
                    const result = await nivisApi("invoices", "POST", {
                        action: "convert",
                        quotation_id: selectedQuote.id
                    });
                    window.location.href = "add-edit-invoice.php?id=" + encodeURIComponent(result.id);
                } catch (error) {
                    window.alert(error.message);
                }
            });
        [search, customerFilter, statusFilter].forEach(control =>
            control.addEventListener(control === search ? "input" : "change", renderQuotations)
        );
        document.querySelectorAll("[data-quote-status]").forEach(card => {
            card.addEventListener("click", () => {
                statusFilter.value = statusFilter.value === card.dataset.quoteStatus
                    ? "all" : card.dataset.quoteStatus.toLowerCase();
                renderQuotations();
            });
        });
        const linkedQuote = new URLSearchParams(window.location.search).get("quote_id");
        if (linkedQuote) search.value = linkedQuote;
        refreshQuotations().catch(error => window.alert(error.message));
    }

    const quoteLines = document.getElementById("nlcqLineItems");
    if (quoteLines) {
        const directInvoice = document.getElementById("nivis-labs-creat-quotation").dataset.documentType === "invoice";
        const productSelect = document.getElementById("nlcqCustomer");
        const feedback = document.getElementById("nlcqFeedback");
        const rows = [];
        let products = [];
        let customers = [];
        let chargeRates = [];
        const copyId = new URLSearchParams(location.search).get("copy");
        const invoiceEditId = directInvoice ? new URLSearchParams(location.search).get("id") : null;
        let quoteConfiguration = {price_mode:"exclusive", seller_state_code:"37"};
        let quoteId = new URLSearchParams(window.location.search).get("id");
        const formData = () => ({
            action: directInvoice ? (invoiceEditId ? "revise" : "create") : undefined,
            invoice_id: invoiceEditId,
            ...Object.fromEntries(["terms","payment_terms","delivery_timeline","bank_details","payment_instructions","signatory"].map(k=>[k,document.getElementById("nlcq_"+k).value])),
            additional_charges: Number(document.getElementById("nlcqCharges").value),
            charge_description: document.getElementById("nlcqChargeDescription").value,
            charge_hsn: document.getElementById("nlcqChargeHsn").value,
            charge_tax_rate_id: document.getElementById("nlcqChargeRate").value,
            due_date: document.getElementById("nlcqValidUntil").value,
            customer_id: productSelect.value,
            place_of_supply_state_code: document.getElementById("nlcqSupplyState").value,
            issue_date: document.getElementById("nlcqIssueDate").value,
            valid_until: document.getElementById("nlcqValidUntil").value,
            notes: document.getElementById("nlcqNotes").value,
            items: rows.map(row => ({
                product_id: row.product_id,
                quantity: Number(row.quantity),
                unit_price: Number(row.unit_price),
                discount_percent: Number(row.discount_percent)
            }))
        });

        function quoteMoney(amount) {
            return "Rs " + Number(amount).toLocaleString("en-IN", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function calculateQuote() {
            const sameState = document.getElementById("nlcqSupplyState").value === quoteConfiguration.seller_state_code;
            document.getElementById("nlcqTaxTreatment").textContent = (sameState ? "CGST + SGST" : "IGST") + " | Prices " + quoteConfiguration.price_mode + " of GST";
            let taxable = 0;
            let tax = 0, subtotal = 0, discount = 0, cgst = 0, sgst = 0;
            rows.forEach((row, index) => {
                const gst = Number(products.find(p => Number(p.id) === Number(row.product_id))?.gst || 0);
                const amounts = nivisAmounts(Number(row.quantity) || 0, Number(row.unit_price) || 0, Number(row.discount_percent) || 0, gst, quoteConfiguration.price_mode);
                taxable += amounts.taxable;
                tax += amounts.tax; subtotal += amounts.base; discount += amounts.discount; cgst += amounts.cgst; sgst += amounts.sgst;
                const net = amounts.taxable, lineTax = amounts.tax;
                const totalCell = quoteLines.querySelector('[data-total="' + index + '"]');
                if (totalCell) totalCell.textContent = quoteMoney(net + lineTax);
            });
            const charge = nivisAmounts(1, Number(document.getElementById("nlcqCharges").value)||0, 0, Number(chargeRates.find(r=>String(r.id)===document.getElementById("nlcqChargeRate").value)?.rate_percent)||0, quoteConfiguration.price_mode);
            subtotal += charge.base; taxable += charge.taxable; tax += charge.tax; cgst += charge.cgst; sgst += charge.sgst;
            document.getElementById("nlcqSubtotal").textContent = quoteMoney(subtotal);
            document.getElementById("nlcqDiscount").textContent = quoteMoney(discount);
            document.getElementById("nlcqTaxSplit").textContent = sameState ? "CGST " + quoteMoney(cgst) + " + SGST " + quoteMoney(sgst) : "IGST " + quoteMoney(tax);
            document.getElementById("nlcqTaxable").textContent = quoteMoney(taxable);
            document.getElementById("nlcqTax").textContent = quoteMoney(tax);
            document.getElementById("nlcqGrandTotal").textContent = quoteMoney(taxable + tax);
        }

        function renderQuoteLines() {
            quoteLines.replaceChildren();
            rows.forEach((row, index) => {
                const tr = document.createElement("tr");
                const serial = document.createElement("td");
                serial.textContent = String(index + 1);
                tr.appendChild(serial);
                const selectCell = document.createElement("td");
                const select = document.createElement("select");
                select.className = "nlcq-cell-select";
                select.dataset.index = index;
                select.add(new Option("Select product", ""));
                products.forEach(product => select.add(new Option(product.name, product.id)));
                select.value = row.product_id || "";
                selectCell.appendChild(select);
                const category = document.createElement("small");
                const info=products.find(p => Number(p.id) === Number(row.product_id));
                category.textContent = info ? info.category + " | SKU: " + info.sku + " | Unit: " + info.unit + " | Available: " + info.quantity : "";
                selectCell.appendChild(category);
                if (!directInvoice && row.product_id) {
                    const reference = document.createElement("small");
                    reference.style.display = "block";
                    reference.textContent = document.getElementById("nlcqQuoteNumber").value + "-P" + row.product_id + "-L" + (index + 1);
                    selectCell.appendChild(reference);
                }
                tr.appendChild(selectCell);
                const product = products.find(item => Number(item.id) === Number(row.product_id));
                const hsn = document.createElement("td");
                hsn.textContent = product?.hsn || "-";
                tr.appendChild(hsn);
                [["quantity", "number", "0.001"], ["unit_price", "number", "0.01"]]
                    .forEach(([key, type, step]) => {
                    const cell = document.createElement("td");
                    const input = document.createElement("input");
                    input.type = type;
                    input.min = key === "discount_percent" ? "0" : "0.001";
                    input.max = key === "discount_percent" ? "100" : undefined;
                    input.step = step;
                    input.value = row[key] ?? 0;
                    input.dataset.index = index;
                    input.dataset.field = key;
                    input.className = "nlcq-cell-input";
                    cell.appendChild(input);
                    tr.appendChild(cell);
                });
                const taxCell = document.createElement("td");
                taxCell.textContent = (Number(product?.gst) || 0) + "%";
                tr.appendChild(taxCell);
                const discountCell = document.createElement("td");
                const discountInput = document.createElement("input");
                discountInput.type = "number";
                discountInput.min = "0";
                discountInput.max = "100";
                discountInput.step = "0.01";
                discountInput.value = row.discount_percent ?? 0;
                discountInput.dataset.index = index;
                discountInput.dataset.field = "discount_percent";
                discountInput.className = "nlcq-cell-input";
                discountCell.appendChild(discountInput);
                tr.appendChild(discountCell);
                const totalCell = document.createElement("td");
                totalCell.dataset.total = index;
                totalCell.className = "nlcq-row-total";
                tr.appendChild(totalCell);
                const action = document.createElement("td");
                const remove = document.createElement("button");
                remove.type = "button";
                remove.textContent = "×";
                remove.title = "Remove line";
                remove.dataset.remove = index;
                action.appendChild(remove);
                tr.appendChild(action);
                quoteLines.appendChild(tr);
            });
            calculateQuote();
        }

        let savingDocument = false;
        async function saveQuotation(status) {
            if (savingDocument) return null;
            if (!productSelect.value || !rows.length ||
                rows.some(row => !row.product_id || Number(row.quantity) <= 0 ||
                    Number(row.unit_price) < 0 ||
                    Number(row.discount_percent) < 0 ||
                    Number(row.discount_percent) > 100)) {
                nivisMessage(feedback, "Select a customer and complete each product line.", true);
                return null;
            }
            const payload = formData();
            if (payload.valid_until < payload.issue_date) {
                nivisMessage(feedback, "Valid until must not be earlier than the issue date.", true);
                return null;
            }
            payload.status = status;
            payload.id = quoteId;
            savingDocument = true;
            try {
                const result = await nivisApi(directInvoice ? "invoices" : "quotations", "POST", payload);
                if (directInvoice) { window.location.href = "add-edit-invoice.php?id=" + result.id; return null; }
                quoteId = result.id;
                document.getElementById("nlcqQuoteNumber").value = result.quote_number;
                renderQuoteLines();
                nivisMessage(feedback, "Quotation saved to the database.");
                return result;
            } catch (error) {
                nivisMessage(feedback, error.message, true);
                return null;
            } finally { savingDocument = false; }
        }

        async function refreshQuoteNumber() {
            if (directInvoice || quoteId) return;
            const date = document.getElementById("nlcqIssueDate").value;
            if (!date) return;
            const response = await fetch("api.php?resource=document_number&date=" + encodeURIComponent(date));
            const result = await response.json();
            if (!response.ok) throw new Error(result.error);
            if (date !== document.getElementById("nlcqIssueDate").value || quoteId) return;
            document.getElementById("nlcqQuoteNumber").value = result.number;
            renderQuoteLines();
        }
        document.getElementById("nlcqIssueDate").addEventListener("change", () => refreshQuoteNumber().catch(e => nivisMessage(feedback, e.message, true)));
        if (directInvoice) {
            document.getElementById("nlcqApprove").hidden = true;
            document.getElementById("nlcqSent").hidden = true;
            document.getElementById("nlcqConvert").hidden = true;
            document.getElementById("nlcqSaveDraft").textContent = "Save invoice";
        }
        async function initializeQuote() {
            const [productResult, customerResult, taxResult, settingsResult] = await Promise.all([
                nivisApi("products"), nivisApi("customers"), nivisApi("tax"), nivisApi("settings")
            ]);
            quoteConfiguration = {...taxResult.configuration, seller_state_code:settingsResult.data?.state_code || taxResult.configuration.seller_state_code};
            const defaults=JSON.parse(settingsResult.data?.document_defaults || "{}");
            for(const k of ["terms","payment_terms","delivery_timeline","bank_details","payment_instructions","signatory"]) document.getElementById("nlcq_"+k).value=defaults[k] || "";
            chargeRates=taxResult.rates.filter(r=>Number(r.is_enabled));
            chargeRates.forEach(r=>document.getElementById("nlcqChargeRate").add(new Option(r.name + " ("+r.rate_percent+"%)",r.id)));
            products = productResult.data.filter(product => product.status === "active");
            customers = customerResult.data;
            nivisCustomerSearch(productSelect, document.getElementById("nlcqCustomerSearch"), document.getElementById("nlcqCustomerMatches"));
            document.getElementById("nlcqSupplyState").value = quoteConfiguration.seller_state_code;
            productSelect.replaceChildren(new Option("Select customer", ""));
            customers.forEach(customer => productSelect.add(new Option(customer.name + " (" + (customer.customer_category || "CB1") + ")", customer.id)));
            const today = new Date();
            const plusDays = new Date(today);
            plusDays.setDate(today.getDate() + 15);
            const localDate = date => [
                date.getFullYear(),
                String(date.getMonth() + 1).padStart(2, "0"),
                String(date.getDate()).padStart(2, "0")
            ].join("-");
            document.getElementById("nlcqIssueDate").value = localDate(today);
            document.getElementById("nlcqValidUntil").value = localDate(plusDays);
            document.getElementById("nlcqQuoteNumber").value = "Assigned when saved";

            if (quoteId || copyId) {
                const result = await nivisApi(directInvoice ? "invoices" : "quotations", "GET", null, quoteId || copyId);
                const quote = result.data;
                if (!copyId && quote.status !== "draft") {
                    throw new Error("Only draft quotations can be edited.");
                }
                document.getElementById("nlcqQuoteNumber").value = copyId ? "Assigned when saved" : (quote.quote_number || quote.invoice_number);
                productSelect.value = quote.customer_id;
                document.getElementById("nlcqSupplyState").value = quote.place_of_supply_state_code;
                if (!copyId) document.getElementById("nlcqIssueDate").value = quote.issue_date;
                if (invoiceEditId) document.getElementById("nlcqIssueDate").disabled=true;
                if (!copyId) document.getElementById("nlcqValidUntil").value = quote.valid_until || quote.due_date || quote.issue_date;
                document.getElementById("nlcqNotes").value = quote.notes || "";
                const savedDetails=JSON.parse(quote.document_details || "{}");
                for(const k of ["terms","payment_terms","delivery_timeline","bank_details","payment_instructions","signatory"]) document.getElementById("nlcq_"+k).value=savedDetails[k] || "";
                document.getElementById("nlcqCharges").value=quote.additional_charges || 0;
                document.getElementById("nlcqChargeDescription").value=savedDetails.charge_description || "";
                document.getElementById("nlcqChargeHsn").value=savedDetails.charge_hsn || "";
                document.getElementById("nlcqChargeRate").value=savedDetails.charge_tax_rate_id || "";
                quote.items.filter(item=>item.product_id).forEach(item => rows.push({
                    product_id: Number(item.product_id),
                    quantity: Number(item.quantity),
                    unit_price: Number(item.unit_price),
                    discount_percent: Number(item.discount_percent)
                }));
            } else {
                rows.push({ product_id: "", quantity: 1, unit_price: 0, discount_percent: 0 });
            }
            showCustomer();
            renderQuoteLines();
            await refreshQuoteNumber();
        }

        function showCustomer() {
            const c=customers.find(c=>String(c.id)===productSelect.value);
            document.getElementById("nlcqCustomerDetails").textContent=c ? [c.name, "Phone: "+c.mobile, "Email: "+(c.email||"Not provided"), "GSTIN: "+(c.gstin||"Unregistered"), "Bill to: "+[c.billing_address,c.city,c.state,c.pincode].filter(Boolean).join(", "), "Ship to: "+[c.shipping_address||c.billing_address,c.shipping_city||c.city,c.shipping_state||c.state,c.shipping_pincode||c.pincode].filter(Boolean).join(", ")].join("\n") : "Select a customer.";
        }
        ["nlcqCharges","nlcqChargeRate"].forEach(id=>document.getElementById(id).addEventListener("input",calculateQuote));
        document.getElementById("nlcqSent").addEventListener("click",async()=>{if(await saveQuotation("sent")) location.href="quotations.php";});
        productSelect.addEventListener("change", () => {
            showCustomer();
            const customer = customers.find(c => String(c.id) === productSelect.value);
            const codes = {"Andhra Pradesh":"37","Telangana":"36","Karnataka":"29","Tamil Nadu":"33","Maharashtra":"27","Kerala":"32","Odisha":"21","Delhi":"07"};
            document.getElementById("nlcqSupplyState").value = codes[customer?.shipping_state || customer?.state] || "37";
            calculateQuote();
        });
        document.getElementById("nlcqSupplyState").addEventListener("change", calculateQuote);
        quoteLines.addEventListener("change", event => {
            const select = event.target.closest("select[data-index]");
            if (select) {
                const row = rows[Number(select.dataset.index)];
                const product = products.find(item => Number(item.id) === Number(select.value));
                if (!row || !product) return;
                row.product_id = Number(product.id);
                row.quantity = Number(product.quantity || 1);
                row.unit_price = Number(product.price);
                row.discount_percent = Number(product.discount);
                renderQuoteLines();
            }
        });
        quoteLines.addEventListener("input", event => {
            const input = event.target.closest("input[data-field]");
            if (!input) return;
            rows[Number(input.dataset.index)][input.dataset.field] = input.value;
            calculateQuote();
        });
        quoteLines.addEventListener("click", event => {
            const button = event.target.closest("[data-remove]");
            if (!button) return;
            rows.splice(Number(button.dataset.remove), 1);
            renderQuoteLines();
        });
        document.getElementById("nlcqAddLine").addEventListener("click", () => {
            rows.push({ product_id: "", quantity: 1, unit_price: 0, discount_percent: 0 });
            renderQuoteLines();
        });
        document.getElementById("nlcqSaveDraft").addEventListener("click", async () => {
            const result = await saveQuotation("draft");
            if (result) window.location.href = "quotations.php";
        });
        document.getElementById("nlcqApprove").addEventListener("click", async () => {
            const result = await saveQuotation("approved");
            if (result) window.location.href = "quotations.php";
        });
        document.getElementById("nlcqConvert").addEventListener("click", async () => {
            const result = await saveQuotation("approved");
            if (!result) return;
            try {
                const invoice = await nivisApi("invoices", "POST", {
                    action: "convert", quotation_id: result.id
                });
                window.location.href = "add-edit-invoice.php?id=" + encodeURIComponent(invoice.id);
            } catch (error) {
                nivisMessage(feedback, error.message, true);
            }
        });
        document.getElementById("nlcqPreview").addEventListener("click", async () => {
            const result=await saveQuotation("draft");
            if(result) location.href="document.php?type=quotation&id="+result.id;
        });
        initializeQuote().catch(error => nivisMessage(feedback, error.message, true));
    }

    const invoiceTable = document.getElementById("nivisInvoiceTableBody");
    if (invoiceTable) {
        const invoices = [];
        const search = document.getElementById("nivisInvoiceSearch");
        const customerFilter = document.getElementById("nivisInvoiceCustomer");
        const statusFilter = document.getElementById("nivisInvoiceStatus");
        const empty = document.getElementById("nivisInvoiceEmpty");

        function paymentStatus(invoice) {
            if (invoice.status !== "issued") return invoice.status === "draft" ? "Draft" : "Cancelled";
            const balance = Number(invoice.grand_total) - Number(invoice.paid_total);
            if (balance <= 0.005) return "Paid";
            return Number(invoice.paid_total) > 0 ? "Part-paid" : "Unpaid";
        }
        const invoicePage=nivisListControls(invoiceTable,renderInvoices);
        function renderInvoices() {
            const query = search.value.toLowerCase().trim();
            const customer = customerFilter.value;
            const status = statusFilter.value;
            const filtered = invoices.filter(invoice =>
                (invoice.invoice_number + " " + invoice.products.join(" "))
                    .toLowerCase().includes(query) &&
                (customer === "all" || invoice.customer === customer) &&
                (status === "all" || paymentStatus(invoice) === status)
            );
            invoiceTable.replaceChildren();
            const visibleInvoices=invoicePage(filtered);
            visibleInvoices.forEach(invoice => {
                const row = document.createElement("tr");
                [
                    invoice.invoice_number,
                    invoice.quote_number || "Direct invoice",
                    invoice.customer,
                    invoice.products.join(", "),
                    "Rs " + Number(invoice.grand_total).toLocaleString("en-IN"),
                    "Rs " + Number(invoice.paid_total).toLocaleString("en-IN"),
                    paymentStatus(invoice)
                ].forEach(value => {
                    const cell = document.createElement("td");
                    cell.textContent = value;
                    row.appendChild(cell);
                });
                const action = document.createElement("td");
                const view = nivisActionButton("view", "View invoice", "fa-eye");
                view.className = "nivis-labs-invoice-icon-action action-view";
                view.dataset.id = invoice.id;
                action.appendChild(view);
                if (invoice.status === "draft") {
                    [["edit", "Edit details", "fa-pen-to-square"], ["delete", "Delete draft", "fa-trash-can"]].forEach(([kind,label,icon]) => {
                        const button = nivisActionButton(kind,label,icon);
                        button.dataset.id = invoice.id;
                        action.appendChild(button);
                    });
                }
                row.appendChild(action);
                invoiceTable.appendChild(row);
            });
            empty.classList.toggle("d-none", visibleInvoices.length > 0);
        }
        async function refreshInvoices() {
            const [invoiceResult, customerResult] = await Promise.all([
                nivisApi("invoices"),
                nivisApi("customers")
            ]);
            invoices.splice(0, invoices.length, ...invoiceResult.data);
            const selected = customerFilter.value;
            customerFilter.replaceChildren(new Option("All customers", "all"));
            customerResult.data.forEach(customer =>
                customerFilter.add(new Option(customer.name, customer.name))
            );
            customerFilter.value = customerResult.data.some(item => item.name === selected)
                ? selected : "all";
            renderInvoices();
        }
        invoiceTable.addEventListener("click", async event => {
            const button = event.target.closest("[data-id]");
            if (button?.dataset.action === "delete") {
                if (!confirm("Delete this draft invoice?")) return;
                try { await nivisApi("invoices", "DELETE", null, button.dataset.id); await refreshInvoices(); }
                catch (error) { alert(error.message); }
                return;
            }
            if (button) {
                window.location.href = "add-edit-invoice.php?id=" +
                    encodeURIComponent(button.dataset.id);
            }
        });
        [search, customerFilter, statusFilter].forEach(control =>
            control.addEventListener(control === search ? "input" : "change", renderInvoices)
        );
        refreshInvoices().catch(error => window.alert(error.message));
    }

    const invoiceNumber = document.getElementById("nlidpInvoiceNumber");
    if (invoiceNumber) {
        const invoiceId = new URLSearchParams(window.location.search).get("id");
        const feedback = document.getElementById("nlidpFeedback");
        const paymentForm = document.getElementById("nlidpPaymentForm");
        let currentInvoice = null;

        async function loadInvoice() {
            if (!invoiceId) throw new Error("Choose an invoice from the invoices page.");
            const result = await nivisApi("invoices", "GET", null, invoiceId);
            currentInvoice = result.data;
            const invoice = currentInvoice;
            const money = value => "Rs " + Number(value).toLocaleString("en-IN", {
                minimumFractionDigits: 2, maximumFractionDigits: 2
            });
            document.getElementById("invoiceDraftEditor").hidden = invoice.status !== "draft";
            document.getElementById("invoiceDueDate").value = invoice.due_date || "";
            document.getElementById("invoiceDueDate").min = invoice.issue_date;
            document.getElementById("invoiceNotes").value = invoice.notes || "";
            document.getElementById("invoiceSavedNotes").textContent = invoice.notes || "No notes";
            document.getElementById("invoiceSavedDue").textContent = invoice.due_date || "Not set";
            document.getElementById("invoiceTypeTitle").textContent=invoice.supplier_gstin ? "TAX INVOICE" : "INVOICE";
            document.getElementById("invoicePrintLink").href="document.php?type=invoice&id="+invoice.id;
            const editLines=document.getElementById("invoiceLinesEdit");editLines.hidden=invoice.status!=="draft" || !!invoice.quotation_id;editLines.href="create-invoice.php?id="+invoice.id;
            document.getElementById("invoiceCancel").hidden=invoice.status!=="issued" || Number(invoice.paid_total)>0;
            document.getElementById("invoiceCancellationReason").textContent=invoice.cancellation_reason || "";
            const history=document.getElementById("invoicePaymentHistory");history.replaceChildren();
            for(const payment of invoice.payments) {const tr=document.createElement("tr");for(const value of [payment.payment_date,money(payment.amount),payment.payment_method,payment.transaction_reference || "—",payment.notes || "—"]){const td=document.createElement("td");td.textContent=value;tr.append(td);}history.append(tr);}
            if(!invoice.payments.length){const tr=document.createElement("tr"),td=document.createElement("td");td.colSpan=5;td.textContent="No payments recorded.";tr.append(td);history.append(tr);}
            invoiceNumber.textContent = invoice.invoice_number;
            document.getElementById("nlidpInvoiceDate").textContent = invoice.issue_date;
            document.getElementById("nlidpSellerName").textContent = invoice.supplier_name;
            document.getElementById("nlidpSellerDetails").textContent = invoice.supplier_address + " | GSTIN: " + (invoice.supplier_gstin || "Not configured");
            document.getElementById("nlidpCustomerName").textContent =
                invoice.buyer_name + (invoice.billing_address ? ", " + invoice.billing_address : "") + " | GSTIN: " + (invoice.buyer_gstin || "Unregistered");
            document.getElementById("nlidpShippingAddress").textContent = invoice.shipping_address || invoice.billing_address;
            document.getElementById("nlidpQuotationLink").textContent =
                invoice.quote_number ? "Linked " + invoice.quote_number : "Direct invoice";
            document.getElementById("nlidpQuotationLink").href = invoice.quotation_id
                ? "quotations.php?quote_id=" + encodeURIComponent(invoice.quote_number)
                : "invoices.php";
            const states = {"37":"Andhra Pradesh","36":"Telangana","29":"Karnataka","33":"Tamil Nadu","27":"Maharashtra","32":"Kerala","21":"Odisha","07":"Delhi"};
            document.getElementById("nlidpSupplyState").textContent = "Place of supply: " + (states[invoice.place_of_supply_state_code] || "") + " (" + invoice.place_of_supply_state_code + ")";
            const items = document.getElementById("nlidpInvoiceItems");
            items.replaceChildren();
            invoice.items.forEach((item, index) => {
                const row = document.createElement("tr");
                [
                    index + 1, item.product_name, item.hsn, item.quantity,
                    money(item.unit_price), Number(item.discount_percent) + "%",
                    money(item.taxable_amount), Number(item.gst_rate) + "%", money(item.line_total)
                ].forEach(value => {
                    const cell = document.createElement("td");
                    cell.textContent = value;
                    row.appendChild(cell);
                });
                items.appendChild(row);
            });
            document.getElementById("nlidpTaxable").textContent = money(invoice.taxable_total);
            const isIntra = invoice.tax_mode === "cgst_sgst";
            document.getElementById("nlidpCgstRow").classList.toggle("d-none", !isIntra);
            document.getElementById("nlidpSgstRow").classList.toggle("d-none", !isIntra);
            document.getElementById("nlidpIgstRow").classList.toggle("d-none", isIntra);
            document.getElementById("nlidpCgst").textContent = money(invoice.cgst_total);
            document.getElementById("nlidpSgst").textContent = money(invoice.sgst_total);
            document.getElementById("nlidpIgst").textContent = money(invoice.igst_total);
            document.getElementById("nlidpGrandTotal").textContent = money(invoice.grand_total);
            const paid = Number(invoice.paid_total);
            document.getElementById("nlidpOutstanding").textContent =
                money(Math.max(0, Number(invoice.grand_total) - paid));
            const paymentButton = document.getElementById("nlidpRecordPayment");
            paymentButton.disabled = invoice.status !== "issued" || Number(invoice.grand_total) - paid <= 0.005;
            document.getElementById("nlidpPaid").textContent = money(paid);
            document.getElementById("nlidpBalance").textContent = money(Math.max(0, Number(invoice.grand_total) - paid));
            document.getElementById("nlidpDocumentStatus").textContent = invoice.status.toUpperCase();
            document.getElementById("nlidpPaymentAmount").max = Math.max(0, Number(invoice.grand_total) - paid).toFixed(2);
            const issueButton = document.getElementById("nlidpSendInvoice");
            issueButton.textContent = invoice.status === "draft" ? "Issue invoice" : "Invoice issued";
            issueButton.disabled = invoice.status !== "draft";
        }

        document.getElementById("invoiceDraftEditor").addEventListener("submit", async event => {
            event.preventDefault();
            if (!event.target.reportValidity()) return;
            try {
                await nivisApi("invoices", "POST", {action:"edit", invoice_id:invoiceId, due_date:document.getElementById("invoiceDueDate").value, notes:document.getElementById("invoiceNotes").value});
                await loadInvoice(); nivisMessage(feedback,"Invoice details saved.");
            } catch(error) { nivisMessage(feedback,error.message,true); }
        });
        document.getElementById("nlidpSendInvoice").addEventListener("click", async () => {
            if (!currentInvoice || currentInvoice.status !== "draft") return;
            if (!window.confirm("Issue this invoice? Issued invoices cannot be edited.")) return;
            try {
                await nivisApi("invoices", "POST", { action: "issue", invoice_id: currentInvoice.id });
                await loadInvoice();
                nivisMessage(feedback, "Invoice issued.");
            } catch (error) {
                nivisMessage(feedback, error.message, true);
            }
        });
        document.getElementById("invoiceCancel").addEventListener("click",async()=>{
            const reason=prompt("Reason for cancellation (invoice history will be preserved):");if(!reason?.trim())return;
            try{await nivisApi("invoices","POST",{action:"cancel",invoice_id:invoiceId,reason});await loadInvoice();}catch(e){nivisMessage(feedback,e.message,true);}
        });
        document.getElementById("nlidpDownloadPDF").addEventListener("click",()=>{location.href="document.php?type=invoice&id="+invoiceId;});
        document.getElementById("nlidpRecordPayment").addEventListener("click", () => {
            const today = new Date();
            document.getElementById("nlidpPaymentDate").value = [today.getFullYear(),String(today.getMonth()+1).padStart(2,"0"),String(today.getDate()).padStart(2,"0")].join("-");
        });
        paymentForm.addEventListener("submit", async event => {
            event.preventDefault();
            if (!paymentForm.reportValidity() || !currentInvoice || paymentForm.dataset.saving) return;
            paymentForm.dataset.saving="1";
            paymentForm.querySelector("button[type=submit]").disabled=true;
            try {
                await nivisApi("invoices", "POST", {
                    action: "payment",
                    invoice_id: currentInvoice.id,
                    amount: Number(document.getElementById("nlidpPaymentAmount").value),
                    payment_date: document.getElementById("nlidpPaymentDate").value,
                    payment_method: document.getElementById("nlidpPaymentMethod").value
                        .toLowerCase().replace(/ /g, "_"),
                    notes: document.getElementById("nlidpPaymentNotes").value,
                    transaction_reference: document.getElementById("nlidpPaymentReference").value
                });
                paymentForm.reset();
                bootstrap.Modal.getOrCreateInstance(
                    document.getElementById("nlidpPaymentModal")
                ).hide();
                await loadInvoice();
                nivisMessage(feedback, "Payment recorded.");
            } catch (error) {
                nivisMessage(document.getElementById("nlidpPaymentMessage"), error.message, true);
            } finally {delete paymentForm.dataset.saving;paymentForm.querySelector("button[type=submit]").disabled=false;}
        });
        loadInvoice().catch(error => nivisMessage(feedback, error.message, true));
    }

    const sellerState = document.getElementById("taxSellerState");
    if (sellerState) {
        const taxMode = document.getElementById("taxDefaultMode");
        const priceMode = document.getElementById("taxPriceMode");
        const ratesBody = document.getElementById("taxRatesTableBody");
        const save = document.getElementById("taxSaveSettings");
        const message = document.getElementById("taxSettingsMessage");

        document.getElementById("taxRateForm").addEventListener("submit", async event => {
            event.preventDefault();
            if (!event.target.reportValidity()) return;
            try {
                await nivisApi("tax_rates", "POST", { id:document.getElementById("taxRateId").value || null, name:document.getElementById("taxRateName").value, rate_percent:Number(document.getElementById("taxRatePercent").value), hsn_codes:document.getElementById("taxRateHsn").value, is_enabled:Number(document.getElementById("taxRateEnabled").value), treatment:document.getElementById("taxRateTreatment").value, effective_from:document.getElementById("taxRateDate").value });
                location.reload();
            } catch(error) { nivisMessage(document.getElementById("taxRateMessage"), error.message, true); }
        });
        function updateTaxIndicators() {
            document.getElementById("taxRegionDescription").textContent = taxMode.value === "outside_ap"
                ? "Outside Andhra Pradesh: IGST" : "Inside Andhra Pradesh: CGST + SGST";
        }
        sellerState.addEventListener("change", updateTaxIndicators);
        taxMode.addEventListener("change", updateTaxIndicators);
        updateTaxIndicators();

        nivisApi("tax").then(result => {
            const config = result.configuration;
            if (config) {
                sellerState.value = config.seller_state_code;
                sellerState.dataset.name = config.seller_state_name;
                document.getElementById("taxSupplierSummary").textContent = "Registered supplier state: " + config.seller_state_name;
                taxMode.value = config.default_region || "inside_ap";
                priceMode.value = config.price_mode;
            }
            updateTaxIndicators();
            if (!ratesBody) return;
            ratesBody.replaceChildren();
            result.rates.forEach(rate => {
                const row = document.createElement("tr");
                const total = Number(rate.rate_percent);
                [rate.name, total + "%", (total / 2) + "% + " + (total / 2) + "%",
                    total + "%", Number(rate.is_enabled) ? "Enabled" : "Disabled"]
                    .forEach(value => {
                        const cell = document.createElement("td");
                        cell.textContent = value;
                        row.appendChild(cell);
                    });
                const actions = document.createElement("td");
                const edit = nivisActionButton("edit", "Edit rate", "fa-pen-to-square");
                edit.addEventListener("click", () => {
                    document.getElementById("taxRateId").value = rate.id;
                    document.getElementById("taxRateHsn").value = rate.hsn_codes || "";
                    document.getElementById("taxRateEnabled").value = String(rate.is_enabled);
                    document.getElementById("taxRateName").value = rate.name;
                    document.getElementById("taxRatePercent").value = rate.rate_percent;
                    document.getElementById("taxRateTreatment").value = rate.treatment;
                    document.getElementById("taxRateDate").value = rate.effective_from;
                    document.getElementById("taxRateName").focus();
                });
                const remove = nivisActionButton("delete", "Delete rate", "fa-trash-can");
                remove.addEventListener("click", async () => {
                    if (!confirm("Delete GST rate " + rate.name + "? It will be removed from available rates. Existing records are preserved; linked products will need a new rate for future billing.")) return;
                    try { await nivisApi("tax_rates", "DELETE", null, rate.id); location.reload(); }
                    catch(error) { nivisMessage(message,error.message,true); }
                });
                const view = nivisActionButton("view", "View rate", "fa-eye");
                view.addEventListener("click", () => alert(rate.name + "\nGST: " + total + "%\nTreatment: " + rate.treatment + "\nHSN/SAC: " + (rate.hsn_codes || "Not specified") + "\nEffective: " + rate.effective_from));
                const toggle = nivisActionButton("toggle", Number(rate.is_enabled) ? "Deactivate" : "Activate", "fa-power-off");
                toggle.addEventListener("click", async () => { try { await nivisApi("tax_rates","POST",{id:rate.id,action:"toggle",is_enabled:!Number(rate.is_enabled)}); location.reload(); } catch(e) { nivisMessage(message,e.message,true); } });
                actions.append(view,edit,toggle,remove); row.appendChild(actions);
                ratesBody.appendChild(row);
            });
        }).catch(error => nivisMessage(message, error.message, true));

        save?.addEventListener("click", async () => {
            try {
                await nivisApi("tax", "POST", {
                    seller_state_name: sellerState.dataset.name,
                    default_region: taxMode.value,
                    tax_mode: "auto",
                    price_mode: priceMode.value
                });
                nivisMessage(message, "Tax settings saved.");
            } catch (error) {
                nivisMessage(message, error.message, true);
            }
        });
    }
});

document.addEventListener('DOMContentLoaded', async () => {
    const form = document.getElementById('companySettings');
    if (!form) return;
    const message = document.getElementById('companyMessage');
    const button = form.querySelector('button[type="submit"]');
    try {
        const {data} = await nivisApi('settings');
        if (data) for (const [key, value] of Object.entries({...data, ...JSON.parse(data.document_defaults || "{}")})) {
            if (form.elements.namedItem(key)) form.elements.namedItem(key).value = value ?? '';
        }
        button.disabled = false;
    } catch (error) { nivisMessage(message, error.message, true); }
    form.addEventListener('submit', async event => {
        event.preventDefault(); button.disabled = true;
        try {
            const result = await nivisApi('settings', 'POST', Object.fromEntries(new FormData(form)));
            nivisMessage(message, result.message);
        } catch (error) { nivisMessage(message, error.message, true); }
        finally { button.disabled = false; }
    });
});
