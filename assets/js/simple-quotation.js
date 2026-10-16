document.addEventListener('DOMContentLoaded', async () => {
    const form=document.getElementById('simpleQuotationForm'); if(!form)return;
    const customer=document.getElementById('simpleQuoteCustomer'), body=document.getElementById('simpleQuoteLines');
    const save=document.getElementById('simpleQuoteSave'), message=document.getElementById('simpleQuoteMessage');
    let products=[],customers=[],config={},defaults={},busy=false;
    const rows=[];
    const region=document.getElementById('simpleQuoteRegion'), supply=document.getElementById('simpleQuoteSupplyState');
    const states={'Andhra Pradesh':'37','Telangana':'36','Karnataka':'29','Tamil Nadu':'33','Maharashtra':'27','Kerala':'32','Odisha':'21','Delhi':'07'};
    function place() {return region.value==='inside' ? config.seller_state_code : supply.value;}
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
    supply.addEventListener('change',calculate);
    function calculate() {
        let tax=0,total=0,cgst=0,sgst=0;
        rows.forEach((row,index)=>{
            const product=products.find(p=>String(p.id)===row.product_id);
            const amount=nivisAmounts(Number(row.quantity)||0,Number(row.unit_price)||0,0,Number(product?.gst)||0,config.price_mode);
            tax+=amount.tax;total+=amount.total;cgst+=amount.cgst;sgst+=amount.sgst;
            body.querySelector('[data-tax="'+index+'"]').textContent=region.value==='inside' ? 'CGST '+nivisMoney(amount.cgst)+' + SGST '+nivisMoney(amount.sgst)+' ('+(Number(product?.gst)||0)+'%)' : 'IGST '+nivisMoney(amount.tax)+' ('+(Number(product?.gst)||0)+'%)';
            body.querySelector('[data-total="'+index+'"]').textContent=nivisMoney(amount.total);
        });
        document.getElementById('simpleQuoteTax').textContent=region.value==='inside' ? 'CGST '+nivisMoney(cgst)+' + SGST '+nivisMoney(sgst) : 'IGST '+nivisMoney(tax);
        document.getElementById('simpleQuoteTotal').textContent=nivisMoney(total);
        document.getElementById('simpleQuoteTaxMode').textContent=(region.value==='inside'?'Inside GST: CGST + SGST':'Outside GST: IGST')+' · Prices '+(config.price_mode||'exclusive')+' of GST';
    }
    function render() {
        body.replaceChildren();
        rows.forEach((row,index)=>{
            const tr=document.createElement('tr'),cell=document.createElement('td'),select=document.createElement('select');
            select.className='form-select';select.required=true;select.setAttribute('aria-label','Product name '+(index+1));
            select.add(new Option('Select product',''));products.forEach(p=>select.add(new Option(p.name,p.id)));select.value=row.product_id;
            select.addEventListener('change',()=>{const p=products.find(p=>String(p.id)===select.value);row.product_id=select.value;row.unit_price=Number(p?.price)||0;render();});cell.append(select);tr.append(cell);
            for(const [key,label,step,min] of [['quantity','Quantity','0.001','0.001'],['unit_price','Price','0.01','0']]) {
                const td=document.createElement('td'),input=document.createElement('input');input.className='form-control';input.type='number';input.required=true;input.min=min;input.step=step;input.max=key==='quantity'?'1000000':'1000000000';input.value=row[key];input.setAttribute('aria-label',label+' '+(index+1));input.addEventListener('input',()=>{row[key]=input.value;calculate();});td.append(input);tr.append(td);
            }
            for(const key of ['tax','total']){const td=document.createElement('td');td.dataset[key]=index;tr.append(td);}
            const td=document.createElement('td'),remove=document.createElement('button');remove.type='button';remove.className='btn btn-sm btn-outline-secondary';remove.textContent='Remove';remove.disabled=rows.length===1;remove.setAttribute('aria-label','Remove product '+(index+1));remove.onclick=()=>{rows.splice(index,1);render();};td.append(remove);tr.append(td);body.append(tr);
        });calculate();
    }
    document.getElementById('simpleQuoteAdd').onclick=()=>{rows.push({product_id:'',quantity:1,unit_price:0});render();};
    customer.addEventListener('change',customerRegion);
    form.addEventListener('submit',async event=>{
        event.preventDefault();if(busy||!form.reportValidity())return;
        busy=true;save.disabled=true;save.textContent='Saving...';
        const today=new Date(),valid=new Date(today);valid.setDate(valid.getDate()+15);
        const date=d=>[d.getFullYear(),String(d.getMonth()+1).padStart(2,'0'),String(d.getDate()).padStart(2,'0')].join('-');
        try {
            await nivisApi('quotations','POST',{...defaults,customer_id:customer.value,issue_date:date(today),valid_until:date(valid),place_of_supply_state_code:place(),status:'draft',items:rows.map(r=>({...r,quantity:Number(r.quantity),unit_price:Number(r.unit_price),discount_percent:0}))});
            location.href='quotations.php?saved=1';
        } catch(error){nivisMessage(message,error.message,true);busy=false;save.disabled=false;save.textContent='Save quotation';}
    });
    try {
        const [p,c,t,s]=await Promise.all([nivisApi('products'),nivisApi('customers'),nivisApi('tax'),nivisApi('settings')]);
        products=p.data.filter(p=>p.status==='active');customers=c.data;config={...t.configuration,seller_state_code:s.data?.state_code||t.configuration.seller_state_code};defaults=JSON.parse(s.data?.document_defaults||'{}');
        Object.entries(states).filter(([name,code])=>code!==config.seller_state_code).forEach(([name,code])=>supply.add(new Option(name,code)));
        region.value=config.default_region==='outside_ap'?'outside':'inside';
        updateRegion();
        customers.forEach(c=>customer.add(new Option(c.name,c.id)));nivisCustomerSearch(customer,document.getElementById('simpleQuoteSearch'),document.getElementById('simpleQuoteMatches'));
        rows.push({product_id:'',quantity:1,unit_price:0});render();save.disabled=false;nivisMessage(message,'');
    }catch(error){nivisMessage(message,error.message,true);}
});
