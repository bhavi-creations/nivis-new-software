USE nivis_labs_billing;
CREATE TABLE IF NOT EXISTS products (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(180) NOT NULL,
 image_path VARCHAR(255) NULL DEFAULT NULL,
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
