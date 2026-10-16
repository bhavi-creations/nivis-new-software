document.addEventListener('DOMContentLoaded',async()=>{
    const id=new URLSearchParams(location.search).get('id'),message=document.getElementById('productSalesMessage');
    try{
        if(!/^\d+$/.test(id||''))throw Error('Choose a product from Products.');
        const [catalogue,sales]=await Promise.all([nivisApi('products'),nivisApi('product_sales','GET',null,id)]);
        const product=catalogue.data.find(p=>String(p.id)===id);if(!product)throw Error('Product not found.');
        document.getElementById('stockSummary').textContent=product.name+' | Total: '+product.quantity+' '+product.unit+' | Sold: '+product.sold_quantity+' | Available: '+product.available_quantity+(product.stock_error?' | '+product.stock_error:'');
        for(const sale of sales.data){
            const row=document.createElement('tr');
            for(const value of [sale.customer,Number(sale.quantity)+' '+sale.unit,sale.issue_date,sale.issued_at||'Not recorded']){const cell=document.createElement('td');cell.textContent=value;row.append(cell);}
            const cell=document.createElement('td'),link=document.createElement('a');link.href='add-edit-invoice.php?id='+sale.invoice_id;link.textContent=sale.invoice_number;cell.append(link);row.append(cell);document.getElementById('productSalesBody').append(row);
        }
        message.textContent=sales.data.length?'':'No sales yet.';
    }catch(error){message.textContent=error.message;}
});
