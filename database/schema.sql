-- Select your database in phpMyAdmin before importing. No demo records.
SET NAMES utf8mb4;
-- 00 - Run first. MySQL 8.0.16+; InnoDB, utf8mb4.


CREATE TABLE IF NOT EXISTS users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 full_name VARCHAR(120) NOT NULL,
 email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('admin','manager','staff') NOT NULL DEFAULT 'staff',
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Customer master and distinct billing/shipping addresses.

CREATE TABLE IF NOT EXISTS customers (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 customer_code VARCHAR(40) UNIQUE,
 name VARCHAR(180) NOT NULL,
 mobile VARCHAR(20) NOT NULL,
 email VARCHAR(190),
 customer_type ENUM('business','individual','unregistered') NOT NULL DEFAULT 'business',
 gst_registration ENUM('registered','unregistered','composition','other') NOT NULL DEFAULT 'unregistered',
 gstin CHAR(15) NULL UNIQUE,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_customer_name(name), INDEX idx_customer_mobile(mobile),
 CONSTRAINT chk_customer_gstin CHECK (gstin IS NULL OR gstin REGEXP '^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$')
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS customer_addresses (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 customer_id BIGINT UNSIGNED NOT NULL,
 address_type ENUM('billing','shipping') NOT NULL,
 address_line1 VARCHAR(255) NOT NULL,
 address_line2 VARCHAR(255), city VARCHAR(120) NOT NULL,
 district VARCHAR(120), state_name VARCHAR(100) NOT NULL,
 state_code CHAR(2) NOT NULL, pincode CHAR(6) NOT NULL,
 country VARCHAR(80) NOT NULL DEFAULT 'India',
 is_default TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
 INDEX idx_customer_address(customer_id,address_type),
 CONSTRAINT chk_customer_address_state CHECK (state_code REGEXP '^[0-9]{2}$'),
 CONSTRAINT chk_customer_pincode CHECK (pincode REGEXP '^[0-9]{6}$')
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS categories (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 product_count INT UNSIGNED NULL DEFAULT NULL,
 name VARCHAR(120) NOT NULL UNIQUE,
 code VARCHAR(30) NOT NULL UNIQUE,
 description VARCHAR(500),
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Do not seed rates as if they are current law; confirm HSN and effective date.

CREATE TABLE IF NOT EXISTS tax_rates (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(100) NOT NULL,
 rate_percent DECIMAL(6,3) NOT NULL,
 treatment ENUM('taxable','zero_rated','nil_rated','exempt','non_gst') NOT NULL DEFAULT 'taxable',
 effective_from DATE NOT NULL,
 effective_to DATE NULL,
 is_enabled TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT chk_tax_rate CHECK(rate_percent >= 0 AND rate_percent <= 100),
 CONSTRAINT chk_tax_dates CHECK(effective_to IS NULL OR effective_to >= effective_from),
 INDEX idx_tax_active(is_enabled,effective_from,effective_to)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS hsn_tax_mappings (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 hsn_code VARCHAR(8) NOT NULL,
 tax_rate_id BIGINT UNSIGNED NOT NULL,
 effective_from DATE NOT NULL,
 effective_to DATE,
 notes VARCHAR(255),
 FOREIGN KEY (tax_rate_id) REFERENCES tax_rates(id) ON DELETE RESTRICT,
 INDEX idx_hsn_date(hsn_code,effective_from)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS gst_configuration (
 id TINYINT UNSIGNED PRIMARY KEY,
 seller_state_name VARCHAR(100) NOT NULL DEFAULT 'Andhra Pradesh',
 seller_state_code CHAR(2) NOT NULL DEFAULT '37',
 tax_mode ENUM('auto','manual_review') NOT NULL DEFAULT 'auto',
 price_mode ENUM('exclusive','inclusive') NOT NULL DEFAULT 'exclusive',
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT chk_gst_config_single CHECK(id = 1)
) ENGINE=InnoDB;
INSERT INTO gst_configuration(id) VALUES(1) ON DUPLICATE KEY UPDATE id=id;


CREATE TABLE IF NOT EXISTS products (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(180) NOT NULL,
 sku VARCHAR(70) NOT NULL UNIQUE,
 category_id BIGINT UNSIGNED NOT NULL,
 hsn_code VARCHAR(8) NOT NULL,
 unit VARCHAR(30) NOT NULL DEFAULT 'PCS',
 default_quantity DECIMAL(12,3) NOT NULL DEFAULT 1,
 gst_region ENUM('inside_ap','outside_ap') NOT NULL DEFAULT 'inside_ap',
 selling_price DECIMAL(15,2) NOT NULL DEFAULT 0,
 default_discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
 tax_rate_id BIGINT UNSIGNED NULL,
 description TEXT,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(category_id) REFERENCES categories(id) ON DELETE RESTRICT,
 FOREIGN KEY(tax_rate_id) REFERENCES tax_rates(id) ON DELETE RESTRICT,
 CONSTRAINT chk_product_price CHECK(selling_price >= 0),
 CONSTRAINT chk_product_discount CHECK(default_discount_percent BETWEEN 0 AND 100),
 INDEX idx_product_name(name), INDEX idx_product_hsn(hsn_code)
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS quotations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 quote_number VARCHAR(40) NOT NULL UNIQUE,
 customer_id BIGINT UNSIGNED NOT NULL,
 issue_date DATE NOT NULL,
 valid_until DATE NOT NULL,
 status ENUM('draft','sent','approved','rejected','expired','converted') NOT NULL DEFAULT 'draft',
 supplier_state_code CHAR(2) NOT NULL,
 place_of_supply_state_code CHAR(2) NOT NULL,
 tax_mode ENUM('cgst_sgst','igst','special_review') NOT NULL,
 price_mode ENUM('exclusive','inclusive') NOT NULL DEFAULT 'exclusive',
 notes TEXT, terms TEXT,
 subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
 discount_total DECIMAL(15,2) NOT NULL DEFAULT 0,
 taxable_total DECIMAL(15,2) NOT NULL DEFAULT 0,
 cgst_total DECIMAL(15,2) NOT NULL DEFAULT 0,
 sgst_total DECIMAL(15,2) NOT NULL DEFAULT 0,
 igst_total DECIMAL(15,2) NOT NULL DEFAULT 0,
 round_off DECIMAL(9,2) NOT NULL DEFAULT 0,
 grand_total DECIMAL(15,2) NOT NULL DEFAULT 0,
 approved_by BIGINT UNSIGNED NULL, approved_at DATETIME NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
 FOREIGN KEY(approved_by) REFERENCES users(id) ON DELETE SET NULL,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT chk_quote_dates CHECK(valid_until >= issue_date),
 INDEX idx_quote_status(status,issue_date), INDEX idx_quote_customer(customer_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS quotation_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 quotation_id BIGINT UNSIGNED NOT NULL,
 line_no INT UNSIGNED NOT NULL,
 product_id BIGINT UNSIGNED NULL,
 product_name VARCHAR(180) NOT NULL,
 sku VARCHAR(70), hsn_code VARCHAR(8) NOT NULL, unit VARCHAR(30) NOT NULL,
 quantity DECIMAL(14,3) NOT NULL,
 unit_price DECIMAL(15,2) NOT NULL,
 discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
 discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
 taxable_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
 gst_rate DECIMAL(6,3) NOT NULL DEFAULT 0,
 cgst_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
 sgst_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
 igst_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
 line_total DECIMAL(15,2) NOT NULL DEFAULT 0,
 FOREIGN KEY(quotation_id) REFERENCES quotations(id) ON DELETE CASCADE,
 FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE SET NULL,
 UNIQUE KEY uq_quote_line(quotation_id,line_no),
 CONSTRAINT chk_quote_quantity CHECK(quantity > 0),
 CONSTRAINT chk_quote_price CHECK(unit_price >= 0),
 CONSTRAINT chk_quote_discount CHECK(discount_percent BETWEEN 0 AND 100)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS quotation_status_history (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 quotation_id BIGINT UNSIGNED NOT NULL,
 from_status VARCHAR(30), to_status VARCHAR(30) NOT NULL,
 changed_by BIGINT UNSIGNED NULL,
 notes VARCHAR(500), changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(quotation_id) REFERENCES quotations(id) ON DELETE CASCADE,
 FOREIGN KEY(changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;


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


CREATE TABLE IF NOT EXISTS invoice_payments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 invoice_id BIGINT UNSIGNED NOT NULL,
 payment_date DATE NOT NULL,
 amount DECIMAL(15,2) NOT NULL,
 payment_method ENUM('cash','upi','bank_transfer','cheque','card','other') NOT NULL,
 transaction_reference VARCHAR(160),
 receipt_number VARCHAR(50) UNIQUE,
 notes VARCHAR(500),
 recorded_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(invoice_id) REFERENCES invoices(id) ON DELETE RESTRICT,
 FOREIGN KEY(recorded_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT chk_payment_amount CHECK(amount > 0),
 INDEX idx_payment_date(payment_date), INDEX idx_payment_invoice(invoice_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS payment_refunds (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 payment_id BIGINT UNSIGNED NOT NULL,
 refund_date DATE NOT NULL,
 amount DECIMAL(15,2) NOT NULL,
 reason VARCHAR(500) NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(payment_id) REFERENCES invoice_payments(id) ON DELETE RESTRICT,
 CONSTRAINT chk_refund_amount CHECK(amount > 0)
) ENGINE=InnoDB;

-- Derived figures: views instead of duplicating totals in report tables.

CREATE OR REPLACE VIEW v_category_directory AS
SELECT c.id,c.name,c.code,c.description,c.status,COUNT(p.id) AS product_count
FROM categories c LEFT JOIN products p ON p.category_id=c.id
GROUP BY c.id,c.name,c.code,c.description,c.status;
CREATE OR REPLACE VIEW v_quotation_pipeline AS
SELECT status,COUNT(*) AS quote_count,COALESCE(SUM(grand_total),0) AS value_total
FROM quotations GROUP BY status;
CREATE OR REPLACE VIEW v_invoice_balances AS
SELECT i.id,i.invoice_number,i.quotation_id,i.customer_id,i.issue_date,i.due_date,i.status,
 i.grand_total,
 COALESCE(p.paid_amount,0) AS paid_amount,
 COALESCE(r.refunded_amount,0) AS refunded_amount,
 COALESCE(p.paid_amount,0)-COALESCE(r.refunded_amount,0) AS net_paid,
 i.grand_total-(COALESCE(p.paid_amount,0)-COALESCE(r.refunded_amount,0)) AS outstanding,
 CASE WHEN i.status='cancelled' THEN 'cancelled'
 WHEN i.grand_total-(COALESCE(p.paid_amount,0)-COALESCE(r.refunded_amount,0))<=0.005 THEN 'paid'
 WHEN COALESCE(p.paid_amount,0)-COALESCE(r.refunded_amount,0)>0 THEN 'part_paid'
 ELSE 'unpaid' END AS payment_status
FROM invoices i
LEFT JOIN (SELECT invoice_id,SUM(amount) paid_amount FROM invoice_payments GROUP BY invoice_id) p ON p.invoice_id=i.id
LEFT JOIN (SELECT ip.invoice_id,SUM(pr.amount) refunded_amount
           FROM payment_refunds pr JOIN invoice_payments ip ON ip.id=pr.payment_id GROUP BY ip.invoice_id) r ON r.invoice_id=i.id;
CREATE OR REPLACE VIEW v_monthly_sales AS
SELECT DATE_FORMAT(issue_date,'%Y-%m') AS sales_month,
 COUNT(*) AS invoice_count,
 SUM(taxable_total) AS taxable_sales,
 SUM(cgst_total) AS cgst,
 SUM(sgst_total) AS sgst,
 SUM(igst_total) AS igst,
 SUM(grand_total) AS invoiced_sales
FROM invoices WHERE status='issued' GROUP BY DATE_FORMAT(issue_date,'%Y-%m');
CREATE OR REPLACE VIEW v_customer_outstanding AS
SELECT c.id AS customer_id,c.name,
 COALESCE(SUM(CASE WHEN b.status='issued' THEN b.outstanding ELSE 0 END),0) AS outstanding
FROM customers c LEFT JOIN v_invoice_balances b ON b.customer_id=c.id
GROUP BY c.id,c.name;
-- Dashboard example:
-- SELECT (SELECT COUNT(*) FROM customers WHERE status='active') customers,
--        (SELECT COUNT(*) FROM products WHERE status='active') products,
--        (SELECT COUNT(*) FROM quotations WHERE status='sent') waiting_quotes,
--        (SELECT COALESCE(SUM(grand_total),0) FROM invoices WHERE status='issued'
--          AND issue_date>=DATE_FORMAT(CURDATE(),'%Y-%m-01')
--          AND issue_date<DATE_FORMAT(CURDATE()+INTERVAL 1 MONTH,'%Y-%m-01')) month_invoices;
-- Report example: SELECT * FROM v_monthly_sales ORDER BY sales_month DESC;
-- Outstanding: SELECT * FROM v_customer_outstanding WHERE outstanding > 0;


CREATE TABLE IF NOT EXISTS company_settings (
 id TINYINT UNSIGNED PRIMARY KEY,
 company_name VARCHAR(180) NOT NULL,
 gstin CHAR(15) NULL,
 pan VARCHAR(10), phone VARCHAR(20), email VARCHAR(190),
 address TEXT NOT NULL, city VARCHAR(120), pincode CHAR(6),
 state_name VARCHAR(100) NOT NULL, state_code CHAR(2) NOT NULL,
 invoice_prefix VARCHAR(20) NOT NULL DEFAULT 'INV-',
 quote_prefix VARCHAR(20) NOT NULL DEFAULT 'QT-',
 currency CHAR(3) NOT NULL DEFAULT 'INR',
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT chk_company_single CHECK(id = 1)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS audit_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 actor_user_id BIGINT UNSIGNED NULL,
 entity_type VARCHAR(60) NOT NULL,
 entity_id BIGINT UNSIGNED NOT NULL,
 action VARCHAR(60) NOT NULL,
 before_data JSON NULL, after_data JSON NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_audit_entity(entity_type,entity_id,created_at)
) ENGINE=InnoDB;
