USE nivis_labs_billing;
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
