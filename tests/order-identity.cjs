const cp=require('child_process'),path=require('path'),assert=require('assert');
const root=path.resolve(__dirname,'..'),php=process.env.PHP_BINARY||'C:/xampp/php/php.exe',db='nivis_identity_test_'+Date.now();
const env={...process.env,NIVIS_DB_NAME:db};
const run=(code,args=[])=>cp.execFileSync(php,['-r',code,'--',...args],{cwd:root,env,encoding:'utf8'});
const api=(resource,data={},id=null)=>{
 const result=JSON.parse(run('$_SERVER["REQUEST_METHOD"]=$argv[1];$_GET=json_decode($argv[2],true);$_POST=json_decode($argv[3],true);require "api.php";',[Object.keys(data).length?'POST':'GET',JSON.stringify({resource,id}),JSON.stringify(data)]));
 assert(!result.error,JSON.stringify(result));return result;
};
const tables=['quotations','quotation_items','quotation_status_history','invoices','invoice_items','invoice_payments','audit_logs'];
function breakIdentities(){run('require "config/database.php";$p=database_connection();foreach(json_decode($argv[1],true) as $table)$p->exec("ALTER TABLE `$table` MODIFY id BIGINT UNSIGNED NOT NULL");',[JSON.stringify(tables)]);}
try{
 run('require "config/database.php";database_connection(false)->exec("CREATE DATABASE ".$argv[1]);',[db]);
 cp.execFileSync(php,['database/setup.php'],{cwd:root,env,stdio:'pipe'});
 api('settings',{company_name:'Identity test',address:'Test address',state_name:'Andhra Pradesh',gstin:'37ABCDE1234F1Z5'});
 const category=api('categories',{name:'Identity test'}).id;
 const rate=api('tax_rates',{name:'Identity GST',rate_percent:18,treatment:'taxable',effective_from:'2020-01-01'}).id;
 const product=api('products',{name:'Identity product',category_id:category,hsn:'330499',unit:'PCS',quantity:1000,price:100,gst:18,tax_rate_id:rate}).id;
 const customer=api('customers',{name:'Identity buyer',mobile:'9000000000',customer_type:'unregistered',billing_address:'Street',city:'City',state:'Andhra Pradesh',pincode:'533001'}).id;
 const today=run('require "config/database.php";echo database_connection()->query("SELECT CURRENT_DATE()")->fetchColumn();').trim();
 const payload={source_type:'order',customer_id:customer,issue_date:today,valid_until:'2099-12-31',advance_amount:10,advance_date:today,advance_method:'cash',items:[{product_id:product,quantity:1,unit_price:100}]};
 breakIdentities();
 const order=api('quotations',payload);
 assert(order.id>0);assert(api('quotations',{},order.id).data.items.length===1);
 const invoice=api('invoices',{action:'convert_simple',quotation_id:order.id});
 const saved=api('invoices',{},invoice.id).data;assert.equal(Number(saved.paid_total),10);assert(saved.payments[0].id>0);
 // Re-import damage on populated tables must preserve all existing references.
 breakIdentities();
 const secondOrder=api('quotations',payload);assert(secondOrder.id>order.id);
 const secondInvoice=api('invoices',{action:'convert_simple',quotation_id:secondOrder.id});assert(secondInvoice.id>invoice.id);
 assert.equal(api('invoices',{},invoice.id).data.quotation_id,order.id);
 assert.equal(api('invoices',{},invoice.id).data.payments[0].id,saved.payments[0].id);
 // Standalone hosting repair is repeatable.
 breakIdentities();
 for(let i=0;i<2;i++)cp.execFileSync(php,['database/migrate_identity_columns.php'],{cwd:root,env,stdio:'pipe'});
 const columns=JSON.parse(run('require "config/database.php";$p=database_connection();$result=[];foreach(json_decode($argv[1],true) as $t)$result[$t]=$p->query("SHOW COLUMNS FROM `$t` WHERE Field=\'id\'")->fetch()["Extra"];echo json_encode($result);',[JSON.stringify(tables)]));
 assert(Object.values(columns).every(value=>value.includes('auto_increment')));
 console.log('PASS: Missing identities repaired for order, items, history, invoice, advance/payment and audit tables; existing IDs preserved; migration repeatable.');
}finally{
 if(!/^nivis_identity_test_\d+$/.test(db))throw Error('Unsafe test database');
 run('require "config/database.php";database_connection(false)->exec("DROP DATABASE IF EXISTS ".$argv[1]);',[db]);
}
