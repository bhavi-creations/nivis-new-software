-- Do not seed rates as if they are current law; confirm HSN and effective date.
USE nivis_labs_billing;
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
