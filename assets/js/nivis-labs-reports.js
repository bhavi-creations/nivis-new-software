document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("nlrFilterForm");
    const from = document.getElementById("nlrFromDate");
    const to = document.getElementById("nlrToDate");
    const type = document.getElementById("nlrReportType");
    const tableHead = document.getElementById("nlrReportTableHead");
    const tableBody = document.getElementById("nlrReportTableBody");
    const empty = document.getElementById("nlrEmptyMessage");
    const exportButton = document.getElementById("nlrExportCSV");
    const printButton = document.getElementById('nlrPrint');
    const client = document.getElementById('nlrClient');
    const allDates = document.getElementById('nlrAllDates');
    const clients = new Map();
    let currentRows = [];
    let requestVersion = 0;
    let reportSelection = null;
    function recordResource(reportType){return ({quotation:'quotations',payments:'payments',customers:'customers',products:'products'})[reportType]||'invoices';}
    function editLink(record,reportType){
        const id=encodeURIComponent(record._id);
        switch(recordResource(reportType)){
            case 'quotations':return (record._source_type==='order'?'create-order.php':'create-quotation.php')+'?id='+id;
            case 'products':return 'add-edit-product.php?id='+id;
            case 'customers':return 'add-edit-customer.php?id='+id;
            case 'payments':return 'payments.php?invoice_id='+encodeURIComponent(record._invoice_id)+'&payment_id='+id+'&action=edit';
            default:return (record._status==='draft'&&!Number(record._has_payments)?'create-invoice.php':'add-edit-invoice.php')+'?id='+id;
        }
    }
    function actionCell(record,reportType){
        const cell=document.createElement('td');cell.className='report-actions';
        const view=nivisActionButton('view','View','fa-eye');
        view.onclick=()=>{const target=document.getElementById('nlrRecordDetails');target.replaceChildren();
            Object.entries(record).filter(([key])=>!key.startsWith('_')).forEach(([key,value])=>{const p=document.createElement('p');p.textContent=key.replaceAll('_',' ')+': '+(value??'');target.append(p);});
            document.getElementById('nlrRecordDialog').showModal();};
        const edit=document.createElement('a');edit.href=editLink(record,reportType);edit.className='btn btn-sm btn-outline-primary';edit.textContent='Edit';
        const remove=nivisActionButton('delete','Delete','fa-trash-can');
        remove.onclick=async()=>{
            const resource=recordResource(reportType);
            if(!confirm('Delete '+(record.document||record.name||'this record')+'? This updates the underlying '+resource+' record and report totals.'))return;
            remove.disabled=true;
            try{
                if(resource==='payments')await nivisApi('invoices','POST',{action:'payment_delete',invoice_id:record._invoice_id,payment_id:record._id});
                else await nivisApi(resource,'DELETE',null,record._id);
                if(resource==='customers'&&String(clients.get(client.value))===String(record._id))client.value='';
                await loadReport();
            }catch(e){alert(e.message);remove.disabled=false;}
        };
        cell.append(view,edit,remove);return cell;
    }

    const localDate = date => [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, "0"),
        String(date.getDate()).padStart(2, "0")
    ].join("-");
    const today = new Date();
    const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
    from.value = localDate(monthStart);
    to.value = localDate(today);

    function formatMoney(amount) {
        return "Rs " + Number(amount || 0).toLocaleString("en-IN", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    async function loadReport() {
        client.setCustomValidity(client.value && !clients.has(client.value) ? 'Select a client from the suggestions, or clear for all clients.' : '');
        from.disabled = to.disabled = allDates.checked;
        to.setCustomValidity("");
        exportButton.disabled = printButton.disabled = true;
        const version = ++requestVersion;
        if (!form.reportValidity()) return;
        if (!allDates.checked && to.value < from.value) {
            to.setCustomValidity("End date must not be earlier than start date.");
            to.reportValidity();
            return;
        }
        to.setCustomValidity("");
        const query = new URLSearchParams({
            resource: "reports",
            from: allDates.checked ? '1000-01-01' : from.value,
            to: allDates.checked ? '9999-12-31' : to.value,
            type: type.value,
            customer_id: clients.get(client.value) || 0
        });
        const selection={type:type.value, from:query.get('from'), to:query.get('to'), client:client.value||'All clients', customerId:query.get('customer_id')};
        const title=type.selectedOptions[0].text+' | '+selection.client+' | '+(allDates.checked?'All dates':selection.from+' to '+selection.to);
        try {
            const response = await fetch("api.php?" + query);
            const result = await response.json();
            if (version !== requestVersion) return;
            if (!response.ok) throw new Error(result.error || "Could not load report.");
            currentRows = result.rows;
            document.getElementById("nlrReportTitle").textContent = title;
            reportSelection = {...selection, summary:result.summary};
            exportButton.disabled = printButton.disabled = !currentRows.length;
            document.getElementById("nlrInvoicedSales").textContent =
                formatMoney(result.summary.sales);
            document.getElementById("nlrCollected").textContent =
                formatMoney(result.summary.collected);
            document.getElementById("nlrOutstanding").textContent =
                formatMoney(result.summary.outstanding);

            const columns = currentRows.length
                ? Object.keys(currentRows[0]).filter(key=>!key.startsWith('_'))
                : ({
                    invoice: ["document", "date", "customer", "source", "total", "status"],
                    sales: ["document", "date", "customer", "total", "status"],
                    gst: ["document", "date", "customer", "taxable_total", "cgst_total", "sgst_total", "igst_total", "grand_total"],
                    quotation: ["document", "date", "customer", "status", "total"],
                    payments: ["document", "date", "customer", "payment_method", "transaction_reference", "amount"],
                    outstanding: ["document", "date", "customer", "total", "paid_amount", "outstanding"],
                    customers: ["name", "mobile", "customer_type", "gst_registration", "status", "created_at"],
                    products: ["name", "sku", "category", "selling_price", "status", "created_at"]
                })[type.value];
            tableHead.replaceChildren();
            const headingRow = document.createElement("tr");
            columns.forEach(column => {
                const cell = document.createElement("th");
                cell.textContent = column === "document" ? (type.value === "quotation" ? "Quote number" : "Invoice number") : column.replace(/_/g, " ").replace(/\b\w/g, letter => letter.toUpperCase());
                headingRow.appendChild(cell);
            });
            tableHead.appendChild(headingRow);
            const actionHeading=document.createElement('th');actionHeading.className='report-actions';actionHeading.textContent='Actions';headingRow.append(actionHeading);

            tableBody.replaceChildren();
            currentRows.forEach(record => {
                const row = document.createElement("tr");
                columns.forEach(column => {
                    const cell = document.createElement("td");
                    const value = record[column];
                    cell.textContent = value !== null &&
                        /total|amount|price|outstanding|paid|sales|balance_due/.test(column)
                        ? formatMoney(value) : (value ?? "");
                    row.appendChild(cell);
                });
                row.append(actionCell(record,selection.type));tableBody.appendChild(row);
            });
            empty.textContent = currentRows.length
                ? "" : "No report data found for the selected period.";
            empty.classList.toggle("d-none", currentRows.length > 0);
        } catch (error) {
            if (version !== requestVersion) return;
            currentRows = [];
            ["nlrInvoicedSales","nlrCollected","nlrOutstanding"].forEach(id => document.getElementById(id).textContent = "—");
            tableHead.replaceChildren();
            tableBody.replaceChildren();
            empty.textContent = error.message;
            empty.classList.remove("d-none");
        }
    }

    function csvEscape(value) {
        let text = String(value ?? "");
        if (/^[=+\-@\t\r]/.test(text)) text = "'" + text;
        return '"' + text.replace(/"/g, '""') + '"';
    }

    form.addEventListener("submit", event => {
        event.preventDefault();
        loadReport();
    });
    [from, to, type, client, allDates].forEach(control =>
        control.addEventListener("change", loadReport)
    );
    exportButton.addEventListener("click", () => {
        if (!currentRows.length) return;
        const columns = Object.keys(currentRows[0]).filter(key=>!key.startsWith('_'));
        const csv = [
            ['Client',reportSelection.client].map(csvEscape).join(','),
            ['Invoice dates',reportSelection.from,reportSelection.to].map(csvEscape).join(','),
            ['Invoiced sales',reportSelection.summary.sales,'Collected (net)',reportSelection.summary.collected,'Balance due (issued)',reportSelection.summary.outstanding].map(csvEscape).join(','),
            columns.map(csvEscape).join(","),
            ...currentRows.map(row => columns.map(column => csvEscape(row[column])).join(","))
        ].join("\r\n");
        const url = URL.createObjectURL(new Blob(["\uFEFF", csv], {
            type: "text/csv;charset=utf-8;"
        }));
        const link = document.createElement("a");
        link.href = url;
        link.download = "nivis-" + reportSelection.type + "-client-" + (reportSelection.customerId==='0'?'all':reportSelection.customerId) + "-report-" + reportSelection.from + "-to-" + reportSelection.to + ".csv";
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    });
    printButton.addEventListener("click", () => {if(!printButton.disabled)window.print();});
    client.addEventListener('input',()=>{client.setCustomValidity('');exportButton.disabled=printButton.disabled=true;requestVersion++;});
    document.getElementById('nlrClearClient').addEventListener('click',()=>{client.value='';loadReport();});
    fetch('api.php?resource=customers').then(async response=>{
        const result=await response.json();if(!response.ok)throw new Error(result.error||'Could not load clients.');
        const list=document.getElementById('nlrClients');
        result.data.forEach(c=>{const label=c.name+' (#'+c.id+')';clients.set(label,c.id);list.append(new Option(label,label));});
        return loadReport();
    }).catch(error=>{empty.textContent=error.message;empty.classList.remove('d-none');});
});
