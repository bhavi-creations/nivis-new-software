-- Derived figures: views instead of duplicating totals in report tables.
USE nivis_labs_billing;
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
