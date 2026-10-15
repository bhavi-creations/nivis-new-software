const fs = require('fs');
const cp = require('child_process');
const assert = require('assert');
const path = require('path');
const root = path.resolve(__dirname, '..');
const php = process.env.PHP_BINARY || 'C:/xampp/php/php.exe';
const db = 'nivis_test_' + Date.now();
const run = (code, args = [], test = false) => cp.execFileSync(php, ['-r', code, '--', ...args], {cwd:root, encoding:'utf8', env:{...process.env,...(test ? {NIVIS_DB_NAME:db} : {})}});
const api = (resource, method='GET', data={}, query={}) => {
 const result = JSON.parse(run('$_SERVER["REQUEST_METHOD"]=$argv[1]; $_GET=json_decode($argv[2],true); $_POST=json_decode($argv[3],true); require "api.php";', [method,JSON.stringify({resource,...query}),JSON.stringify(data)], true));
 return result;
};
const ok = result => { assert(!result.error, JSON.stringify(result)); return result; };
try {
 run('require "config/database.php"; $p=database_connection(false); $p->exec("CREATE DATABASE " . $argv[1]);', [db]);
 run('require "config/database.php"; $p=database_connection(); $p->exec($argv[1]);', [fs.readFileSync(path.join(root,'database/schema.sql'),'utf8')], true);
 cp.execFileSync(php, ['database/setup.php'], {cwd:root, env:{...process.env,NIVIS_DB_NAME:db}});
 ok(api('settings','POST',{company_name:'Test Company',address:'Test address',state_name:'Andhra Pradesh',email:'test@example.com',phone:'9000000000'}));
 assert.equal(ok(api('settings')).data.company_name,'Test Company');
 assert(api('settings','POST',{company_name:'Invalid',address:'Address',state_name:'Unknown'}).error);
 // Simulate an older hosting database and verify automatic migration preserves data.
 run('require "config/database.php"; $p=database_connection(); $p->exec("ALTER TABLE products DROP COLUMN default_quantity, DROP COLUMN gst_region"); $p->exec("ALTER TABLE categories DROP COLUMN product_count");', [], true);
 ok(api('products')); ok(api('categories'));
 ok(api('products')); ok(api('categories')); // Repeated checks must be safe.
 ok(api('tax_rates','POST',{name:'Test 18%',rate_percent:18,treatment:'taxable',effective_from:'2020-01-01'}));
 // Reproduce hosting imports with missing product/category AUTO_INCREMENT.
 run('require "config/database.php"; $p=database_connection(); $p->exec("ALTER TABLE categories MODIFY id BIGINT UNSIGNED NOT NULL"); $p->exec("ALTER TABLE products MODIFY id BIGINT UNSIGNED NOT NULL");', [], true);
 const cat=ok(api('categories','POST',{name:'Test category',code:'TEST',status:'active'})).id;
 const product={name:'Test product',sku:'TEST-1',category_id:cat,hsn:'330499',unit:'PCS',price:100,gst:18,discount:10,quantity:2,gst_region:'inside_ap',status:'active'};
 const pid=ok(api('products','POST',product)).id;
 // Repair populated tables as well; IDs and category references must survive.
 run('require "config/database.php"; $p=database_connection(); $p->exec("ALTER TABLE categories MODIFY id BIGINT UNSIGNED NOT NULL"); $p->exec("ALTER TABLE products MODIFY id BIGINT UNSIGNED NOT NULL");', [], true);
 ok(api('categories','POST',{id:cat,name:'Test category',code:'TEST',status:'active'}));
 ok(api('products','POST',{...product,id:pid,quantity:3,gst_region:'outside_ap'}));
 const saved=ok(api('products')).data.find(p=>p.id===pid); assert.equal(Number(saved.quantity),3); assert.equal(saved.gst_region,'outside_ap');
 assert.equal(ok(api('categories')).data[0].products,1);
 ok(api('categories','POST',{id:cat,name:'Test category',code:'TEST',product_count:12}));
 assert.equal(ok(api('categories')).data[0].products,12);
 assert.equal(ok(api('categories')).data[0].linked_products,1);
 ok(api('categories','POST',{id:cat,name:'Test category',code:'TEST',product_count:null}));
 assert.equal(ok(api('categories')).data[0].products,1);
 assert(api('categories','POST',{name:'Bad count',code:'BAD',product_count:-1}).error);
 assert(api('products','POST',{...product,quantity:-1}).error);
 // Manual category entry, stable serials, and zero-stock persistence.
 const manualCategory=ok(api('categories','POST',{name:'Manual category'})).id;
 const stockId=ok(api('products','POST',{...product,sku:'STOCK-TEST',category_id:manualCategory,quantity:0})).id;
 assert(stockId > pid);
 assert.equal(Number(ok(api('products')).data.find(p=>p.id===stockId).quantity),0);
 ok(api('products','POST',{...product,id:stockId,sku:'STOCK-TEST',category_id:manualCategory,quantity:5,price:125}));
 const stock=ok(api('products')).data.find(p=>p.id===stockId);
 assert.equal(Number(stock.quantity),5); assert.equal(Number(stock.price),125);
 ok(api('products','DELETE',{}, {id:stockId}));
 assert(!ok(api('products')).data.some(p=>p.id===stockId));
 ok(api('categories','DELETE',{}, {id:manualCategory}));
 const customer={name:'Test buyer',mobile:'9000000000',customer_type:'unregistered',gst_registration:'unregistered',billing_address:'Test street',city:'Test city',pincode:'533001',state:'Andhra Pradesh'};
 // Hosting may require explicit customer codes and address defaults.
 run('require "config/database.php"; $p=database_connection(); $p->exec("ALTER TABLE customers MODIFY customer_code VARCHAR(40) NOT NULL"); $p->exec("ALTER TABLE customer_addresses MODIFY country VARCHAR(80) NOT NULL, MODIFY is_default TINYINT(1) NOT NULL");', [], true);
 // Reproduce a hosting import that lost AUTO_INCREMENT on both identity columns.
 run('require "config/database.php"; $p=database_connection(); $p->exec("ALTER TABLE customers MODIFY id BIGINT UNSIGNED NOT NULL"); $p->exec("ALTER TABLE customer_addresses MODIFY id BIGINT UNSIGNED NOT NULL");', [], true);
 const cid=ok(api('customers','POST',customer)).id;
 assert(cid > 0);
 ok(api('customers','POST',{...customer,id:cid,name:'Updated buyer',shipping_address:'Shipping street',shipping_city:'Hyderabad',shipping_state:'Telangana',shipping_pincode:'500001'}));
 // Repeat the repair on populated tables and verify the original customer survives.
 run('require "config/database.php"; require "config/schema.php"; $p=database_connection(); $p->exec("ALTER TABLE customers MODIFY id BIGINT UNSIGNED NOT NULL"); $p->exec("ALTER TABLE customer_addresses MODIFY id BIGINT UNSIGNED NOT NULL"); ensure_customer_identity_columns($p); ensure_customer_identity_columns($p);', [], true);
 const nextCustomer = ok(api('customers','POST',{...customer,name:'After identity repair'})).id;
 assert(nextCustomer > cid);
 const customerSaved=ok(api('customers')).data.find(c=>c.id===cid);
 assert.equal(customerSaved.name,'Updated buyer'); assert.equal(customerSaved.shipping_state,'Telangana');

 const quote={customer_id:cid,issue_date:'2026-10-12',valid_until:'2026-10-27',notes:'Keep these notes',status:'draft',items:[{product_id:pid,quantity:2,unit_price:100,discount_percent:10}]};
 const qid=ok(api('quotations','POST',quote)).id;
 let q=ok(api('quotations','GET',{}, {id:qid})).data; assert.equal(q.notes,'Keep these notes');assert.equal(Number(q.grand_total),212.4);
 ok(api('quotations','POST',{...quote,id:qid,status:'approved'}));
 const iid=ok(api('invoices','POST',{action:'convert',quotation_id:qid})).id;
 ok(api('invoices','POST',{action:'edit',invoice_id:iid,due_date:'2026-12-31',notes:'Updated draft'}));
 assert.equal(ok(api('invoices','GET',{}, {id:iid})).data.notes,'Updated draft');
 assert(api('invoices','POST',{action:'edit',invoice_id:iid,due_date:'2020-01-01'}).error);
 let invoice=ok(api('invoices','GET',{}, {id:iid})).data;
 assert(invoice.supplier_name); assert.equal(Number(invoice.cgst_total),16.2);assert.equal(Number(invoice.sgst_total),16.2);assert.equal(Number(invoice.igst_total),0);assert(invoice.invoice_number.length<=16);
 assert(api('invoices','POST',{action:'convert',quotation_id:qid}).error);
 ok(api('invoices','POST',{action:'issue',invoice_id:iid}));
 assert(api('invoices','DELETE',{}, {id:iid}).error);
 assert(api('invoices','POST',{action:'edit',invoice_id:iid,notes:'Blocked'}).error);
 const draftQuote=ok(api('quotations','POST',{...quote,status:'approved'})).id;
 const draftInvoice=ok(api('invoices','POST',{action:'convert',quotation_id:draftQuote})).id;
 ok(api('invoices','DELETE',{}, {id:draftInvoice}));
 assert(api('invoices','GET',{}, {id:draftInvoice}).error);
 const testRate=ok(api('tax_rates','POST',{name:'Temporary',rate_percent:7,treatment:'taxable',effective_from:'2020-01-01'})).id;
 ok(api('tax_rates','POST',{id:testRate,name:'Updated rate',rate_percent:8,treatment:'taxable',effective_from:'2020-01-01'}));
 assert.equal(ok(api('tax')).rates.find(r=>r.id===testRate).name,'Updated rate');
 ok(api('tax_rates','DELETE',{}, {id:testRate}));
 assert(!ok(api('tax')).rates.some(r=>r.id===testRate));
 ok(api('invoices','POST',{action:'payment',invoice_id:iid,amount:100,payment_date:'2026-10-12',payment_method:'cash'}));
 assert(api('invoices','POST',{action:'payment',invoice_id:iid,amount:200,payment_date:'2026-10-12',payment_method:'cash'}).error);
 for(const type of ['sales','gst','quotation','payments','outstanding','customers','products']) {let report=ok(api('reports','GET',{}, {from:'2020-01-01',to:'2030-12-31',type}));assert(report.rows.length,type);}
 const outside=ok(api('customers','POST',{...customer,name:'Outside buyer',state:'Telangana',pincode:'500001'})).id;
 const outsideQuote=ok(api('quotations','POST',{...quote,customer_id:outside,status:'approved'})).id;
 const outsideInvoice=ok(api('invoices','POST',{action:'convert',quotation_id:outsideQuote})).id;
 invoice=ok(api('invoices','GET',{}, {id:outsideInvoice})).data;assert.equal(Number(invoice.igst_total),32.4);assert.equal(Number(invoice.cgst_total),0);
 ok(api('tax','POST',{seller_state_name:'Andhra Pradesh',tax_mode:'auto',price_mode:'inclusive'}));
 const inclusive=ok(api('quotations','POST',{...quote,status:'approved'})).id;
 assert.equal(Number(ok(api('quotations','GET',{}, {id:inclusive})).data.grand_total),180);
 const override=ok(api('quotations','POST',{...quote,place_of_supply_state_code:'29',status:'approved'})).id;
 const overrideInvoice=ok(api('invoices','POST',{action:'convert',quotation_id:override})).id;
 assert.equal(ok(api('invoices','GET',{}, {id:overrideInvoice})).data.tax_mode,'igst');
 assert(api('quotations','POST',{...quote,place_of_supply_state_code:'XX'}).error);
 ok(api('products','POST',{...product,id:pid,status:'inactive'}));
 assert.equal(ok(api('dashboard')).stats.products,1);
 // Deletion must work with address-only and invoice-linked customers.
 ok(api('customers','DELETE',{}, {id:nextCustomer}));
 assert(!ok(api('customers')).data.some(c=>c.id===nextCustomer));
 ok(api('customers','DELETE',{}, {id:cid}));
 assert(!ok(api('customers')).data.some(c=>c.id===cid));
 assert(api('customers','POST',{...customer,id:cid}).error);
 assert(api('customers','DELETE',{}, {id:cid}).error);
 assert(api('quotations','POST',quote).error);
 assert.equal(ok(api('invoices','GET',{}, {id:iid})).data.id,iid);
 assert.equal(ok(api('quotations','GET',{}, {id:qid})).data.id,qid);
 assert(ok(api('reports','GET',{}, {from:'2020-01-01',to:'2030-12-31',type:'sales'})).rows.length);
 console.log('PASS: customer/product CRUD, counts, quotation notes, inside/outside GST, inclusive pricing, conversion, issuing, payment validation, all seven reports.');
} finally {
 if(!/^nivis_test_[0-9]+$/.test(db)) throw new Error('Unsafe test database name');
 run('require "config/database.php"; $p=database_connection(false); $p->exec("DROP DATABASE " . $argv[1]);', [db]);
}
