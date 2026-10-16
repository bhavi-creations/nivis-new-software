const fs=require('fs');const f='tests/billing-http.cjs';let s=fs.readFileSync(f,'utf8');const setup=" ok(await request('settings','POST',{company_name:'HTTP Test Company',address:'Test address',state_name:'Andhra Pradesh',gstin:'37ABCDE1234F1Z5'}));";s=s.replace(setup,'');const marker=" const key='billing-test-'+Date.now();";s=s.replace(marker,` // Quotations must save without a company_settings row, using configured supplier state.
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
${setup}
`+marker);fs.writeFileSync(f,s);
