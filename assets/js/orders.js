document.addEventListener('DOMContentLoaded',async()=>{
    const table=document.getElementById('orderTable');if(!table)return;
    const search=document.getElementById('orderSearch'),message=document.getElementById('orderMessage');let orders=[];
    const paginate=nivisListControls(table,render);
    function render(){
        const q=search.value.trim().toLowerCase();const visible=paginate(orders.filter(o=>[o.order_number,o.quote_number,o.customer,...o.products].join(' ').toLowerCase().includes(q)));
        table.replaceChildren();
        for(const order of visible){
            const tr=document.createElement('tr');
            for(const value of [order.order_number,order.quote_number,order.customer,order.products.join(', '),nivisMoney(order.grand_total),order.invoice_id?'Converted to invoice':'Quotation ready']){const td=document.createElement('td');td.textContent=value;tr.append(td);}
            const actions=document.createElement('td');const view=document.createElement('a');view.className='btn btn-sm btn-outline-primary';view.textContent='View / Save A4';view.href='document.php?type=quotation&id='+order.id;actions.append(view);
            for(const [action,label,icon] of [['edit','Edit','fa-pen-to-square'],['delete','Delete','fa-trash-can']]) {
                const button=nivisActionButton(action,label,icon);
                button.disabled=action==='edit'&&(order.status==='converted'||!!order.invoice_id);
                if(button.disabled)button.title='Already converted to an invoice';
                button.onclick=async()=>{
                    if(action==='edit'){location.href='create-order.php?id='+order.id;return;}
                    if(!confirm('Delete '+order.order_number+'? Linked invoices and payments will be kept.'))return;
                    button.disabled=true;
                    try{await nivisApi('quotations','DELETE',null,order.id);orders=orders.filter(o=>o.id!==order.id);render();}
                    catch(e){nivisMessage(message,e.message,true);button.disabled=false;}
                };actions.append(button);
            }
            if(order.invoice_id){const link=document.createElement('a');link.className='btn btn-sm btn-outline-primary ms-2';link.textContent='View invoice';link.href='add-edit-invoice.php?id='+order.invoice_id;actions.append(link);}
            else if(['draft','sent','approved'].includes(order.status)){
                const button=document.createElement('button');button.type='button';button.className='btn btn-sm btn-primary ms-2';button.textContent='Convert to Invoice';
                button.onclick=async()=>{button.disabled=true;try{await nivisApi('invoices','POST',{action:'convert_simple',quotation_id:order.id});location.href='invoices.php?converted=1';}catch(e){nivisMessage(message,e.message,true);button.disabled=false;}};actions.append(button);
            }
            tr.append(actions);table.append(tr);
        }
        nivisMessage(message,visible.length?'':'No orders found. Add a new order to get started.');
    }
    search.addEventListener('input',render);
    try{orders=(await nivisApi('quotations')).data.filter(q=>q.source_type==='order');render();}catch(e){nivisMessage(message,e.message,true);}
});
