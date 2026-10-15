-- Customer master and distinct billing/shipping addresses.
USE nivis_labs_billing;
CREATE TABLE IF NOT EXISTS customers (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 customer_code VARCHAR(40) UNIQUE,
 deleted_at DATETIME NULL DEFAULT NULL,
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
