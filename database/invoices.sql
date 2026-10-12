USE nivis_labs_billing;
CREATE TABLE IF NOT EXISTS invoices (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 invoice_number VARCHAR(40) NOT NULL UNIQUE,
 quotation_id BIGINT UNSIGNED NULL UNIQUE, -- Prevent 2 invoices from the same quotation.
 customer_id BIGINT UNSIGNED NOT NULL,
 issue_date DATE NOT NULL, due_date DATE NULL,
 status ENUM('draft','issued','cancelled') NOT NULL DEFAULT 'draft',
 supplier_name VARCHAR(180) NOT NULL, supplier_gstin CHAR(15) NULL,
 supplier_address TEXT NOT NULL, supplier_state_code CHAR(2) NOT NULL,
 buyer_name VARCHAR(180) NOT NULL, buyer_gstin CHAR(15) NULL,
 billing_address TEXT NOT NULL, shipping_address TEXT NULL,
 place_of_supply_state_code CHAR(2) NOT NULL,
 tax_mode ENUM('cgst_sgst','igst','special_review') NOT NULL,
 price_mode ENUM('exclusive','inclusive') NOT NULL DEFAULT 'exclusive',
 subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
 discount_total DECIMAL(15,2) NOT NULL DEFAULT 0,
 taxable_total DECIMAL(15,2) NOT NULL DEFAULT 0,
 cgst_total DECIMAL(15,2) NOT NULL DEFAULT 0,
 sgst_total DECIMAL(15,2) NOT NULL DEFAULT 0,
 igst_total DECIMAL(15,2) NOT NULL DEFAULT 0,
 round_off DECIMAL(9,2) NOT NULL DEFAULT 0,
 grand_total DECIMAL(15,2) NOT NULL DEFAULT 0,
 notes TEXT,
 issued_at DATETIME NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(quotation_id) REFERENCES quotations(id) ON DELETE RESTRICT,
 FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT chk_invoice_dates CHECK(due_date IS NULL OR due_date >= issue_date),
 INDEX idx_invoice_dates(issue_date,status), INDEX idx_invoice_customer(customer_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS invoice_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 invoice_id BIGINT UNSIGNED NOT NULL,
 line_no INT UNSIGNED NOT NULL,
 source_quotation_item_id BIGINT UNSIGNED NULL,
 product_id BIGINT UNSIGNED NULL,
 product_name VARCHAR(180) NOT NULL,
 sku VARCHAR(70), hsn_code VARCHAR(8) NOT NULL, unit VARCHAR(30) NOT NULL,
 quantity DECIMAL(14,3) NOT NULL, unit_price DECIMAL(15,2) NOT NULL,
 discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
 discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
 taxable_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
 gst_rate DECIMAL(6,3) NOT NULL DEFAULT 0,
 cgst_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
 sgst_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
 igst_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
 line_total DECIMAL(15,2) NOT NULL DEFAULT 0,
 FOREIGN KEY(invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
 FOREIGN KEY(source_quotation_item_id) REFERENCES quotation_items(id) ON DELETE SET NULL,
 FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE SET NULL,
 UNIQUE KEY uq_invoice_line(invoice_id,line_no),
 CONSTRAINT chk_invoice_qty CHECK(quantity > 0)
) ENGINE=InnoDB;
-- Atomic invoice number issuance should use this counter with SELECT ... FOR UPDATE.
CREATE TABLE IF NOT EXISTS document_sequences (
 document_type ENUM('quotation','invoice') NOT NULL,
 financial_year VARCHAR(9) NOT NULL,
 last_number BIGINT UNSIGNED NOT NULL DEFAULT 0,
 PRIMARY KEY(document_type,financial_year)
) ENGINE=InnoDB;
