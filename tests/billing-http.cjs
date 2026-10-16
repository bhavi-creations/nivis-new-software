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
   const r=await fetch(base+'document.php?type='+type+'&id='+id,{headers:{Cookie:cookie}});const html=await r.text();assert.equal(r.status,200);assert(html.includes('&lt;script&gt;unsafe&lt;/script&gt;'));assert(!html.includes('<script>unsafe</script>'));assert(html.includes('billingDocument'));assert(html.includes('HTTP Test Company'));
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
