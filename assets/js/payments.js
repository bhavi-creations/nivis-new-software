document.addEventListener('DOMContentLoaded',async()=>{
    const el=id=>document.getElementById(id), customer=el('payCustomer'),invoice=el('payInvoice'),product=el('payProduct'),form=el('payForm'),message=el('payMessage');
    let invoices=[],quotes=[],current=null,version=0,busy=false;
    const money=nivisMoney;
    function row(body,values){const tr=document.createElement('tr');values.forEach(value=>{const td=document.createElement('td');td.textContent=value;tr.append(td);});body.append(tr);}
    function maxAmount(){const item=current?.product_payments.find(i=>String(i.id)===product.value);el('payAmount').max=(item?Number(item.balance_due):Math.max(0,Number(current?.grand_total)-Number(current?.paid_total))).toFixed(2);}
    async function load(){
        const run=++version;current=null;form.hidden=true;el('payView').hidden=true;el('payProducts').replaceChildren();el('payHistory').replaceChildren();el('paySummary').textContent='';
        if(!invoice.value)return;
        try{
            const result=await nivisApi('invoices','GET',null,invoice.value);if(run!==version)return;current=result.data;
            el('paySummary').textContent=current.buyer_name+' | '+current.invoice_number+' | '+current.issue_date+' | Total '+money(current.grand_total)+' | Paid '+money(current.paid_total)+' | Balance '+money(Math.max(0,Number(current.grand_total)-Number(current.paid_total)));
            el('payView').href='document.php?type=invoice&id='+current.id;el('payView').hidden=false;
            product.replaceChildren(new Option('All unpaid products (line order)',''));
            current.product_payments.forEach(i=>{row(el('payProducts'),[i.product_name,i.quantity+' '+i.unit,money(i.line_total),money(i.paid_amount),money(i.balance_due),i.payment_status]);if(Number(i.balance_due)>0)product.add(new Option(i.product_name+' — '+money(i.balance_due)+' due',i.id));});
            current.payments.forEach(p=>row(el('payHistory'),[current.buyer_name,p.payment_date,money(p.amount),money(p.refunded_amount),p.payment_method,p.products,[p.transaction_reference,p.notes].filter(Boolean).join(' / ')]));
            if(!current.payments.length)row(el('payHistory'),['No payments recorded.']);
            form.hidden=!['draft','issued'].includes(current.status)||Number(current.paid_total)>=Number(current.grand_total);maxAmount();
        }catch(e){if(run===version)nivisMessage(message,e.message,true);}
    }
    function filter(){
        invoice.replaceChildren(new Option('Select invoice',''));invoices.filter(i=>String(i.customer_id)===customer.value).forEach(i=>invoice.add(new Option(i.invoice_number+' | '+i.issue_date+' | '+money(i.grand_total)+' | '+i.status,i.id)));
        const advances=quotes.filter(q=>String(q.customer_id)===customer.value&&!q.invoice_id&&q.status!=='converted'&&Number(q.advance_amount)>0);
        el('payAdvances').hidden=!advances.length;el('payAdvanceRows').replaceChildren();
        advances.forEach(q=>{row(el('payAdvanceRows'),[q.order_number||q.quote_number,q.advance_date,money(q.advance_amount),q.advance_method]);const td=document.createElement('td'),a=document.createElement('a');a.href='document.php?type=quotation&id='+q.id;a.textContent='View';td.append(a);el('payAdvanceRows').lastChild.append(td);});load();
    }
    customer.addEventListener('change',filter);invoice.addEventListener('change',load);product.addEventListener('change',maxAmount);
    form.addEventListener('submit',async e=>{
        e.preventDefault();if(busy||!current||!form.reportValidity())return;busy=true;
        const payload={action:'payment',invoice_id:current.id,invoice_item_id:product.value||undefined,amount:Number(el('payAmount').value),payment_date:el('payDate').value,payment_method:el('payMethod').value,transaction_reference:el('payReference').value};
        form.querySelectorAll('input,select,button').forEach(c=>c.disabled=true);customer.disabled=invoice.disabled=true;
        try{await nivisApi('invoices','POST',payload);el('payAmount').value='';el('payReference').value='';await load();nivisMessage(message,'Payment saved. Invoice paid amount and product balances updated.');}
        catch(error){nivisMessage(message,error.message,true);}
        finally{busy=false;form.querySelectorAll('input,select,button').forEach(c=>c.disabled=false);customer.disabled=invoice.disabled=false;}
    });
    try{
        const [c,i,q]=await Promise.all([nivisApi('customers'),nivisApi('invoices'),nivisApi('quotations')]);invoices=i.data;quotes=q.data;
        c.data.forEach(c=>customer.add(new Option(c.name,c.id)));nivisCustomerSearch(customer,el('paySearch'),el('payMatches'));
        const selected=invoices.find(i=>String(i.id)===new URLSearchParams(location.search).get('invoice_id'));
        if(selected){customer.value=selected.customer_id;filter();invoice.value=selected.id;await load();}
    }catch(e){nivisMessage(message,e.message,true);}
});
