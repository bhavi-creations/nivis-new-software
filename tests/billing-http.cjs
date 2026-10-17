const fs=require('fs'),path=require('path'),cp=require('child_process'),assert=require('assert'),os=require('os');
const root=path.resolve(__dirname,'..'),php=process.env.PHP_BINARY||'C:/xampp/php/php.exe',db='nivis_http_test_'+Date.now();
const env={...process.env,NIVIS_DB_NAME:db};let server;
const run=code=>cp.execFileSync(php,['-r',code],{cwd:root,env,encoding:'utf8'});
const base='http://127.0.0.1:18765/';let cookie='',csrf='';
async function request(resource,method='GET',data=null,extra={}) {
 const res=await fetch(base+'api.php?resource='+resource,{method,headers:{Cookie:cookie,'Content-Type':'application/json','X-CSRF-Token':csrf,...extra},body:data?JSON.stringify(data):undefined,signal:AbortSignal.timeout(20000)});
 const body=await res.text();let json;try{json=JSON.parse(body);}catch{throw Error(body.slice(0,1500));}return {status:res.status,...json};
}
const ok=r=>{assert(!r.error,JSON.stringify(r));return r;};
(async()=>{try {
 run('require "config/database.php";database_connection(false)->exec("CREATE DATABASE '+db+'");');
 cp.execFileSync(php,['database/setup.php'],{cwd:root,env,stdio:'pipe'});
 server=cp.spawn(php,['-d','session.save_path='+os.tmpdir(),'-S','127.0.0.1:18765'],{cwd:root,env,stdio:'ignore'});
 let connected=false;for(let i=0;i<40;i++){try{const r=await fetch(base+'api.php?resource=security');cookie=r.headers.get('set-cookie').split(';')[0];csrf=(await r.json()).csrf;connected=true;break;}catch{await new Promise(r=>setTimeout(r,100));}}
 assert(connected,'Test HTTP server did not start');
 assert.equal((await request('categories','POST',{name:'Blocked'},{'X-CSRF-Token':''})).status,403);

 const category=ok(await request('categories','POST',{name:'Test category'})).id;
 const rate=ok(await request('tax_rates','POST',{name:'Test 18',rate_percent:18,treatment:'taxable',effective_from:'2020-01-01',hsn_codes:'330499'})).id;
 const product=ok(await request('products','POST',{name:'<script>unsafe</script>',category_id:category,hsn:'330499',unit:'PCS',price:100,gst:18,tax_rate_id:rate})).id;
 const customer=ok(await request('customers','POST',{name:'Test buyer',mobile:'9000000000',customer_type:'unregistered',billing_address:'Test street',city:'Test city',state:'Andhra Pradesh',pincode:'533001'})).id;
 const today=run('require "config/database.php";echo database_connection()->query("SELECT CURRENT_DATE()")->fetchColumn();').trim();
 const draft={action:'create',customer_id:customer,issue_date:today,due_date:today,items:[{product_id:product,quantity:2,unit_price:100,discount_percent:10}]};
 const invoiceValue=async()=>Number(ok(await request('dashboard')).stats.invoice_value);
 assert.equal(await invoiceValue(),0);
 // Quotations must save without a company_settings row, using configured supplier state.
 for(const [state,mode] of [['37','cgst_sgst'],['36','igst']]) {
   const result=ok(await request('quotations','POST',{...draft,action:undefined,valid_until:'2099-12-31',place_of_supply_state_code:state,status:'draft'}));
   const saved=ok(await request('quotations&id='+result.id)).data;
   assert.equal(saved.place_of_supply_state_code,state);assert.equal(saved.tax_mode,mode);
   assert.equal(Number(saved.grand_total),212.4);
   assert.equal(Number(saved.cgst_total),state==='37'?16.2:0);
   assert.equal(Number(saved.sgst_total),state==='37'?16.2:0);
   assert.equal(Number(saved.igst_total),state==='36'?32.4:0);
   assert.deepEqual(JSON.parse(saved.party_snapshot).supplier,[]);
 }
 ok(await request('settings','POST',{company_name:'HTTP Test Company',address:'Test address',state_name:'Andhra Pradesh',gstin:'37ABCDE1234F1Z5'}));
 const key='billing-test-'+Date.now();
 const [first,second]=await Promise.all([request('invoices','POST',draft,{'Idempotency-Key':key}),request('invoices','POST',draft,{'Idempotency-Key':key})]);
 ok(first);ok(second);assert.equal(first.id,second.id);assert.equal(ok(await request('invoices')).data.length,1);
 assert.equal(Number(ok(await request('invoices&id='+first.id)).data.grand_total),212.4);
 assert.equal(await invoiceValue(),212.4,'Saved direct draft invoice is counted once');
 assert.equal((await request('invoices','POST',{...draft,notes:'Changed'},{'Idempotency-Key':key})).status,409);
 const quote=ok(await request('quotations','POST',{...draft,action:undefined,valid_until:'2099-12-31',status:'draft'})).id;
 for(const [type,id] of [['invoice',first.id],['quotation',quote]]) {
   const r=await fetch(base+'document.php?type='+type+'&id='+id,{headers:{Cookie:cookie}});const html=await r.text();assert.equal(r.status,200);assert(html.includes('&lt;script&gt;unsafe&lt;/script&gt;'));assert(!html.includes('<script>unsafe</script>'));assert(html.includes('billingDocument'));assert(html.includes(type==='quotation'?'Nivis Labs International':'HTTP Test Company'));
   assert(html.includes('document-'+type));assert(html.includes('document-pages.js'));
   if(type==='quotation'){assert(html.includes('2-21/632/SF-08-08'));assert(html.includes('nivislabs@gmail.com'));assert(html.includes('96666 90910'));assert(html.includes('class="supplier-logo"'));}
 }
 for(const page of ['tax.php','quotations.php','invoices.php','create-quotation.php','create-invoice.php','add-edit-product.php','customers.php','categories.php','dashboard.php','reports.php','settings.php']) {
   const r=await fetch(base+page,{headers:{Cookie:cookie}});const html=await r.text();assert.equal(r.status,200,page);assert(!/Fatal error|Warning:/.test(html),page);assert(html.includes('csrf-token'),page);
 }
 // Simple Save -> View -> Convert flow does not need an approval step.
 const before=ok(await request('quotations&id='+quote)).data;
 assert.equal(before.status,'draft');
 const simple=ok(await request('invoices','POST',{action:'convert_simple',quotation_id:quote}));
 const converted=ok(await request('invoices&id='+simple.id)).data;
 assert.equal(Number(converted.grand_total),Number(before.grand_total));
 assert.equal(converted.quotation_id,quote);
 assert(ok(await request('invoices')).data.some(i=>i.id===simple.id));
 assert.equal((await request('invoices','POST',{action:'convert_simple',quotation_id:quote})).status,409);
 assert.equal(ok(await request('quotations&id='+quote)).data.status,'converted');
 assert.equal(await invoiceValue(),424.8,'Converted draft invoice is included without counting its quotation');
 const simpleForm=await (await fetch(base+'create-quotation.php',{headers:{Cookie:cookie}})).text();
 assert(simpleForm.includes('simpleQuotationForm'));assert(!simpleForm.includes('nlcqApprove'));assert(!simpleForm.includes('Additional charges'));
 // Orders are persisted quotations, with quantity units retained on conversion.
 const orderPayload={...draft,action:undefined,source_type:'order',customer_gstin:'37ABCDE1234F1Z5',valid_until:'2099-12-31',place_of_supply_state_code:'37',items:[{product_id:product,quantity:50,unit:'PCS',unit_price:100},{product_id:product,quantity:30,unit:'KG',unit_price:200}]};
 const order=ok(await request('quotations','POST',orderPayload));
 const savedOrder=ok(await request('quotations&id='+order.id)).data;
 assert.equal(savedOrder.source_type,'order');assert(savedOrder.order_number.startsWith('ORD-'));
 assert.deepEqual(savedOrder.items.map(i=>[Number(i.quantity),i.unit]),[[50,'PCS'],[30,'KG']]);
 assert.equal(Number(savedOrder.grand_total),12980);
 assert.equal(JSON.parse(savedOrder.party_snapshot).buyer.gstin,orderPayload.customer_gstin);
 assert(ok(await request('quotations')).data.some(q=>q.id===order.id&&q.source_type==='order'));
 const orderInvoice=ok(await request('invoices','POST',{action:'convert_simple',quotation_id:order.id}));
 const savedInvoice=ok(await request('invoices&id='+orderInvoice.id)).data;
 assert.deepEqual(savedInvoice.items.map(i=>[Number(i.quantity),i.unit]),[[50,'PCS'],[30,'KG']]);
 assert.equal(JSON.parse(savedInvoice.document_details).order_number,savedOrder.order_number);
 assert.equal(JSON.parse(savedInvoice.party_snapshot).buyer.gstin,orderPayload.customer_gstin);
 assert.equal((await request('invoices','POST',{action:'convert_simple',quotation_id:order.id})).status,409);
 assert.equal((await request('quotations','POST',{...orderPayload,customer_gstin:'INVALID'})).status,422);
 assert.equal((await request('quotations','POST',{...orderPayload,items:[{...orderPayload.items[0],unit:'INVALID'}]})).status,422);
 const direct=ok(await request('invoices','POST',{...orderPayload,action:'create',source_type:'quotation'}));
 const directSaved=ok(await request('invoices&id='+direct.id)).data;
 assert(!directSaved.quotation_id);assert.equal(directSaved.items[1].unit,'KG');
 for(const page of ['orders.php','create-order.php','create-invoice.php']) {
   const r=await fetch(base+page,{headers:{Cookie:cookie}}),html=await r.text();assert.equal(r.status,200);assert(!/Fatal error|Warning:/.test(html));
   if(page!=='orders.php')assert(html.includes('data-mode="'+(page==='create-order.php'?'order':'invoice')+'"'));
 }
 for(const [type,id] of [['quotation',order.id],['invoice',orderInvoice.id]]){
   const html=await(await fetch(base+'document.php?type='+type+'&id='+id,{headers:{Cookie:cookie}})).text();
   assert(html.includes(savedOrder.order_number));assert(html.includes(' KG'));assert(html.includes('Save A4 PDF'));
 }
 const editable=ok(await request('quotations','POST',orderPayload));
 const edited=ok(await request('quotations','POST',{...orderPayload,id:editable.id,items:[{product_id:product,quantity:30,unit:'KG',unit_price:200,discount_percent:10}]}));
 assert.equal(edited.id,editable.id);
 const editedSaved=ok(await request('quotations&id='+edited.id)).data;
 assert.equal(Number(editedSaved.grand_total),6372);assert.equal(Number(editedSaved.items[0].discount_percent),10);assert.equal(editedSaved.items[0].unit,'KG');assert.equal(editedSaved.source_type,'order');
 const productHtml=await(await fetch(base+'add-edit-product.php',{headers:{Cookie:cookie}})).text();assert(!productHtml.includes('id="editProductCustomer"'));
 // Inventory: 50 - 30 - 20, sold history, cancellation and unit conversion.
 const stockProduct={name:'Stock product',category_id:category,hsn:'330499',unit:'PCS',quantity:50,price:100,gst:18,tax_rate_id:rate};
 const stockId=ok(await request('products','POST',stockProduct)).id;
 const balance=async()=>ok(await request('products')).data.find(p=>p.id===stockId);
 const saleDraft=async(qty,unit='PCS',pid=stockId)=>ok(await request('invoices','POST',{...draft,items:[{product_id:pid,quantity:qty,unit,unit_price:100}]})).id;
 const saleA=await saleDraft(30);assert.equal(Number((await balance()).available_quantity),50);
 ok(await request('invoices','POST',{action:'issue',invoice_id:saleA}));assert.equal(Number((await balance()).available_quantity),20);
 assert.equal((await request('invoices','POST',{action:'issue',invoice_id:saleA})).status,409);
 const tooMuch=await saleDraft(21);assert.equal((await request('invoices','POST',{action:'issue',invoice_id:tooMuch})).status,409);
 assert.equal(Number((await balance()).available_quantity),20);
 const saleB=await saleDraft(20);ok(await request('invoices','POST',{action:'issue',invoice_id:saleB}));assert.equal(Number((await balance()).available_quantity),0);
 const history=ok(await request('product_sales&id='+stockId)).data;assert.equal(history.length,2);assert(history.every(s=>s.customer==='Test buyer'&&s.issued_at&&s.invoice_number));assert.equal(history.reduce((sum,s)=>sum+Number(s.quantity),0),50);
 assert.equal((await request('products','POST',{...stockProduct,id:stockId,quantity:49})).status,422);
 assert.equal((await request('products&id='+stockId,'DELETE')).status,409);
 ok(await request('invoices','POST',{action:'cancel',invoice_id:saleB,reason:'Stock test cancellation'}));assert.equal(Number((await balance()).available_quantity),20);
 assert.equal(ok(await request('product_sales&id='+stockId)).data.length,1);
 const weightId=ok(await request('products','POST',{...stockProduct,name:'Weight product',unit:'KG',quantity:2})).id;
 const grams=await saleDraft(500,'GRAM',weightId);ok(await request('invoices','POST',{action:'issue',invoice_id:grams}));
 assert.equal(Number(ok(await request('products')).data.find(p=>p.id===weightId).available_quantity),1.5);
 const wrongUnit=await saleDraft(1,'PCS',weightId);assert.equal((await request('invoices','POST',{action:'issue',invoice_id:wrongUnit})).status,422);
 const salesPage=await(await fetch(base+'product-sales.php?id='+stockId,{headers:{Cookie:cookie}})).text();assert(salesPage.includes('productSalesBody'));
 // Two independent PHP processes must not oversell the last 20 units.
 const raceIds=[await saleDraft(15),await saleDraft(15)];
 const issueProcess=id=>new Promise((resolve,reject)=>cp.execFile(php,['-r','$_SERVER["REQUEST_METHOD"]="POST";$_GET=["resource"=>"invoices"];$_POST=["action"=>"issue","invoice_id"=>(int)$argv[1]];require "api.php";','--',String(id)],{cwd:root,env,encoding:'utf8'},(e,out)=>{if(e)reject(e);else{try{resolve(JSON.parse(out));}catch(e){reject(e);}}}));
 const race=await Promise.all(raceIds.map(issueProcess));assert.equal(race.filter(r=>!r.error).length,1);assert.equal(Number((await balance()).available_quantity),5);
 // Converted invoices can edit their lines without changing the source quotation.
 const editQuote=ok(await request('quotations','POST',{...orderPayload,items:[{product_id:stockId,quantity:2,unit:'PCS',unit_price:100}]}));
 const editInvoice=ok(await request('invoices','POST',{action:'convert_simple',quotation_id:editQuote.id}));
 const original=ok(await request('invoices&id='+editInvoice.id)).data;
 ok(await request('invoices','POST',{...draft,action:'revise',invoice_id:editInvoice.id,issue_date:original.issue_date,items:[{product_id:stockId,quantity:3,unit:'PCS',unit_price:80,discount_percent:10}]}));
 const revised=ok(await request('invoices&id='+editInvoice.id)).data;assert.equal(revised.quotation_id,editQuote.id);assert.equal(Number(revised.items[0].quantity),3);assert.equal(Number(revised.grand_total),254.88);
 assert.equal(Number(ok(await request('quotations&id='+editQuote.id)).data.items[0].quantity),2);
 assert(ok(await request('product_sales&id='+stockId)).pending.some(i=>i.invoice_id===editInvoice.id&&Number(i.quantity)===3));
 const editHtml=await(await fetch(base+'create-invoice.php?id='+editInvoice.id,{headers:{Cookie:cookie}})).text();assert(editHtml.includes('data-mode="invoice"'));assert(editHtml.includes('simpleQuotationForm'));
 ok(await request('invoices','POST',{action:'issue',invoice_id:editInvoice.id}));assert.equal(Number((await balance()).available_quantity),2);
 assert.equal((await request('invoices','POST',{...draft,action:'revise',invoice_id:editInvoice.id})).status,409);
 // Dashboard uses persisted monthly totals after edits, issuance and cancellation.
 const persistedValue=()=>Number(run('require "config/database.php";echo database_connection()->query("SELECT COALESCE(SUM(grand_total),0) FROM invoices WHERE status IN (\'draft\',\'issued\') AND YEAR(issue_date)=YEAR(CURRENT_DATE()) AND MONTH(issue_date)=MONTH(CURRENT_DATE())")->fetchColumn();'));
 assert.equal(await invoiceValue(),persistedValue());
 const previousMonth=run('require "config/database.php";echo database_connection()->query("SELECT DATE_FORMAT(CURRENT_DATE() - INTERVAL 1 MONTH,\'%Y-%m-01\')")->fetchColumn();').trim();
 const currentValue=await invoiceValue();
 ok(await request('invoices','POST',{...draft,issue_date:previousMonth}));
 assert.equal(await invoiceValue(),currentValue,'Previous months are excluded');
 ok(await request('invoices&id='+tooMuch,'DELETE'));
 assert.equal(await invoiceValue(),Math.round((currentValue-2478)*100)/100,'Deleted drafts are removed from Invoice Value');
 // Saved quotations/orders support edit and delete; converted records remain protected.
 for(const source_type of ['quotation','order']) {
   const saved=ok(await request('quotations','POST',{...orderPayload,source_type,status:'approved'}));
   ok(await request('quotations','POST',{...orderPayload,id:saved.id,source_type,status:'draft',items:[{product_id:product,quantity:1,unit_price:75}]}));
   assert.equal(Number(ok(await request('quotations&id='+saved.id)).data.grand_total),88.5);
   ok(await request('quotations&id='+saved.id,'DELETE'));
   assert.equal((await request('quotations&id='+saved.id)).status,404);
 }
 ok(await request('quotations&id='+quote,'DELETE'));
 assert.equal((await request('quotations&id='+quote)).status,404);
 assert.equal((await request('quotations&id='+quote,'DELETE')).status,404);
 assert.equal((await request('invoices','POST',{action:'convert_simple',quotation_id:quote})).status,409);
 assert.equal(Number(ok(await request('invoices&id='+simple.id)).data.grand_total),424.8/2);
 assert.equal((await request('quotations','POST',{...orderPayload,id:quote})).status,404);
 // Reports reflect persisted payments and isolate the selected client in every report type.
 ok(await request('invoices','POST',{action:'payment',invoice_id:editInvoice.id,amount:100,payment_date:today,payment_method:'cash'}));
 const secondCustomer=ok(await request('customers','POST',{name:'Second report client',mobile:'9000000001',customer_type:'unregistered',billing_address:'Second street',city:'Test city',state:'Andhra Pradesh',pincode:'533001'})).id;
 const otherInvoice=ok(await request('invoices','POST',{...draft,customer_id:secondCustomer}));
 const report=async(type,client='')=>ok(await request('reports&from=1000-01-01&to=9999-12-31&type='+type+'&customer_id='+(client||0)));
 const selected=await report('invoice',customer);
 assert(!selected.rows.some(r=>r.document===otherInvoice.invoice_number));
 const paidRow=selected.rows.find(r=>r.document===revised.invoice_number);
 assert.equal(Number(paidRow.net_paid),100);assert.equal(Number(paidRow.balance_due),154.88);
 assert(paidRow.products.includes('Stock product'));assert(paidRow.payment_history.includes(today+': 100.00 (cash)'));
 assert.equal(Number(selected.summary.collected),100);
 assert.equal(Number(selected.summary.sales),Math.round(selected.rows.filter(r=>r.status!=='cancelled').reduce((s,r)=>s+Number(r.total),0)*100)/100);
 assert.equal(Number(selected.summary.outstanding),Math.round(selected.rows.filter(r=>r.status==='issued').reduce((s,r)=>s+Number(r.balance_due),0)*100)/100);
 const other=await report('invoice',secondCustomer);assert.equal(other.rows.length,1);assert.equal(Number(other.summary.sales),212.4);assert.equal(Number(other.summary.collected),0);assert.equal(Number(other.summary.outstanding),0);
 assert.equal(Number((await report('invoice')).summary.sales),Math.round((Number(selected.summary.sales)+212.4)*100)/100);
 for(const type of ['sales','gst','quotation','payments','outstanding','customers','products']) {
   const result=await report(type,secondCustomer);
   if(type==='customers')assert.deepEqual(result.rows.map(r=>r.name),['Second report client']);
   else if(type==='products')assert(result.rows.every(r=>Number(r.sales)===0));
   else assert.equal(result.rows.length,0,type);
 }
 assert.equal((await request('reports&from=1000-01-01&to=9999-12-31&type=invoice&customer_id=invalid')).status,422);
 const totalsBeforeDelete=(await report('invoice')).summary;
 const dashboardBeforeDelete=ok(await request('dashboard')).stats;
 ok(await request('quotations&id='+editQuote.id,'DELETE'));
 assert(!ok(await request('quotations')).data.some(q=>q.id===editQuote.id));
 const retained=ok(await request('invoices&id='+editInvoice.id)).data;
 assert.equal(Number(retained.grand_total),254.88);assert.equal(Number(retained.payments[0].amount),100);
 assert.deepEqual((await report('invoice')).summary,totalsBeforeDelete);
 assert.equal(Number(ok(await request('dashboard')).stats.quotations),Number(dashboardBeforeDelete.quotations)-1);
 assert(!(await report('quotation')).rows.some(q=>q.document===editQuote.quote_number));
 assert.equal((await fetch(base+'document.php?type=quotation&id='+editQuote.id,{headers:{Cookie:cookie}})).status,404);
 // Advances carry over once; specific product payments do not mark other products paid.
 const advancePayload={...orderPayload,advance_amount:50,advance_date:today,advance_method:'upi',items:[{product_id:product,quantity:1,unit:'PCS',unit_price:100},{product_id:product,quantity:1,unit:'PCS',unit_price:200}]};
 assert.equal((await request('quotations','POST',{...advancePayload,advance_amount:355})).status,422);
 const advanceOrder=ok(await request('quotations','POST',advancePayload));
 assert.equal(Number(ok(await request('quotations&id='+advanceOrder.id)).data.advance_amount),50);
 const advanceInvoice=ok(await request('invoices','POST',{action:'convert_simple',quotation_id:advanceOrder.id}));
 let advanceSaved=ok(await request('invoices&id='+advanceInvoice.id)).data;
 assert.equal(Number(advanceSaved.paid_total),50);assert.equal(advanceSaved.payments.length,1);
 assert.deepEqual(advanceSaved.product_payments.map(i=>[Number(i.paid_amount),Number(i.balance_due),i.payment_status]),[[50,68,'Part paid'],[0,236,'Unpaid']]);
 assert.equal((await request('invoices','POST',{action:'convert_simple',quotation_id:advanceOrder.id})).status,409);
 // Converted source edits retain invoice history and cannot change the transferred advance.
 ok(await request('quotations','POST',{...advancePayload,id:advanceOrder.id,notes:'Edited source after conversion',items:[{product_id:product,quantity:2,unit:'PCS',unit_price:200}]}));
 assert.equal(ok(await request('quotations&id='+advanceOrder.id)).data.status,'converted');
 assert.equal(Number(ok(await request('invoices&id='+advanceInvoice.id)).data.grand_total),354);
 assert.equal((await request('quotations','POST',{...advancePayload,id:advanceOrder.id,advance_amount:60})).status,409);
 const secondLine=advanceSaved.product_payments[1].id;
 const payProduct={action:'payment',invoice_id:advanceInvoice.id,invoice_item_id:secondLine,amount:236,payment_date:today,payment_method:'cash'};
 const receipt=ok(await request('invoices','POST',payProduct));
 advanceSaved=ok(await request('invoices&id='+advanceInvoice.id)).data;
 assert.equal(Number(advanceSaved.paid_total),286);
 assert.deepEqual(advanceSaved.product_payments.map(i=>i.payment_status),['Part paid','Paid']);
 assert.equal((await request('invoices','POST',{...payProduct,amount:1})).status,422);
 assert.equal((await request('invoices','POST',{...payProduct,invoice_item_id:999999})).status,422);
 assert.equal((await request('invoices','POST',{...draft,action:'revise',invoice_id:advanceInvoice.id})).status,409);
 ok(await request('invoices','POST',{...payProduct,invoice_item_id:advanceSaved.product_payments[0].id,amount:68}));
 advanceSaved=ok(await request('invoices&id='+advanceInvoice.id)).data;
 assert.equal(Number(advanceSaved.paid_total),354);assert(advanceSaved.product_payments.every(i=>i.payment_status==='Paid'));
 const paidHtml=await(await fetch(base+'document.php?type=invoice&id='+advanceInvoice.id,{headers:{Cookie:cookie}})).text();assert(paidHtml.includes('Product payment status'));assert(paidHtml.includes('Payment history'));assert(paidHtml.includes('Advance from'));
 assert.equal((await fetch(base+'payments.php',{headers:{Cookie:cookie}})).status,200);
 // A partial refund reopens only the credited product balance.
 run('require "config/database.php";database_connection()->exec("INSERT INTO payment_refunds(payment_id,refund_date,amount,reason) VALUES('+receipt.id+',CURRENT_DATE(),36,\'Test refund\')");');
 advanceSaved=ok(await request('invoices&id='+advanceInvoice.id)).data;
 assert.equal(Number(advanceSaved.paid_total),318);assert.equal(Number(advanceSaved.product_payments[1].balance_due),36);assert.equal(advanceSaved.product_payments[0].payment_status,'Paid');
 // Provision isolated test users through the existing users table.
 run('require "config/database.php";$q=database_connection()->prepare("INSERT INTO users(full_name,email,password_hash,role) VALUES(?,?,?,?)");$q->execute(["Test Admin","admin@example.test",password_hash("test-password-123",PASSWORD_DEFAULT),"admin"]);$q->execute(["Test Staff","staff@example.test",password_hash("test-password-123",PASSWORD_DEFAULT),"staff"]);');
 assert.equal((await request('invoices')).status,401);
 async function login(email) {
   const r=await fetch(base+'login.php',{headers:{Cookie:cookie}});const html=await r.text();const token=html.match(/name="csrf" value="([^"]+)"/)[1];
   const login=await fetch(base+'login.php',{method:'POST',headers:{Cookie:cookie,'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({csrf:token,email,password:'test-password-123'}),redirect:'manual'});
   assert.equal(login.status,302);cookie=login.headers.get('set-cookie').split(';')[0];csrf=ok(await request('security')).csrf;
 }
 await login('staff@example.test');ok(await request('invoices'));assert.equal((await request('categories','POST',{name:'Forbidden'})).status,403);
 await login('admin@example.test');ok(await request('categories','POST',{name:'Allowed'}));
 console.log('PASS: HTTP CSRF, session authentication, staff permissions, concurrent duplicate submission replay, A4 HTML escaping, and all module page responses.');
} finally {if(server)server.kill();if(!/^nivis_http_test_\d+$/.test(db))throw Error('Unsafe test database');run('require "config/database.php";database_connection(false)->exec("DROP DATABASE IF EXISTS '+db+'");');}})().catch(e=>{console.error(e);process.exitCode=1;});
