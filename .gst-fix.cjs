const fs=require('fs');let s=fs.readFileSync('assets/js/simple-quotation.js','utf8').replace(/\r\n/g,'\n');
s=s.replace("    const rows=[];","    const rows=[];\n    const region=document.getElementById('simpleQuoteRegion'), supply=document.getElementById('simpleQuoteSupplyState');");
s=s.replace("    function place() {const c=customers.find(c=>String(c.id)===customer.value);return states[c?.shipping_state||c?.state]||config.seller_state_code;}",`    function place() {return region.value==='inside' ? config.seller_state_code : supply.value;}
    function updateRegion() {
        document.getElementById('simpleQuoteOutsideField').hidden=region.value!=='outside';
        supply.required=region.value==='outside';
        calculate();
    }
    function customerRegion() {
        const c=customers.find(c=>String(c.id)===customer.value), code=states[c?.shipping_state||c?.state];
        if(code){region.value=code===config.seller_state_code?'inside':'outside';supply.value=code===config.seller_state_code?'':code;}
        updateRegion();
    }
    region.addEventListener('change',updateRegion);
    supply.addEventListener('change',calculate);`);
s=s.replace('let tax=0,total=0;','let tax=0,total=0,cgst=0,sgst=0;');
s=s.replace('tax+=amount.tax;total+=amount.total;','tax+=amount.tax;total+=amount.total;cgst+=amount.cgst;sgst+=amount.sgst;');
s=s.replace("(Number(product?.gst)||0)+'% · '+nivisMoney(amount.tax)","region.value==='inside' ? 'CGST '+nivisMoney(amount.cgst)+' + SGST '+nivisMoney(amount.sgst)+' ('+(Number(product?.gst)||0)+'%)' : 'IGST '+nivisMoney(amount.tax)+' ('+(Number(product?.gst)||0)+'%)'");
s=s.replace("document.getElementById('simpleQuoteTax').textContent=nivisMoney(tax);","document.getElementById('simpleQuoteTax').textContent=region.value==='inside' ? 'CGST '+nivisMoney(cgst)+' + SGST '+nivisMoney(sgst) : 'IGST '+nivisMoney(tax);");
s=s.replace("(place()===config.seller_state_code?'CGST + SGST':'IGST')","(region.value==='inside'?'Inside GST: CGST + SGST':'Outside GST: IGST')");
s=s.replace("customer.addEventListener('change',calculate);","customer.addEventListener('change',customerRegion);");
s=s.replace("        customers.forEach(c=>customer.add", "        Object.entries(states).filter(([name,code])=>code!==config.seller_state_code).forEach(([name,code])=>supply.add(new Option(name,code)));\n        region.value=config.default_region==='outside_ap'?'outside':'inside';\n        updateRegion();\n        customers.forEach(c=>customer.add");
fs.writeFileSync('assets/js/simple-quotation.js',s);
