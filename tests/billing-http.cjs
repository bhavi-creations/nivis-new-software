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
 const draft={action:'create',customer_id:customer,issue_date:'2026-10-16',due_date:'2026-10-30',items:[{product_id:product,quantity:2,unit_price:100,discount_percent:10}]};
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
