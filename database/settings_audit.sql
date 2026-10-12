USE nivis_labs_billing;
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
