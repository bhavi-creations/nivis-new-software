// Build separate A4 content areas, so a long document never becomes one huge canvas.
window.nivisDocumentPages = function(source) {
    const stage=document.createElement('div');stage.className='pdf-stage';stage.setAttribute('aria-hidden','true');document.body.append(stage);
    const ruler=document.createElement('div');ruler.style.height='265mm';stage.append(ruler);
    const maxHeight=ruler.getBoundingClientRect().height;ruler.remove();
    const pages=[];let page;
    function next() {
        page=document.createElement('div');page.className=source.className+' pdf-page';stage.append(page);pages.push(page);
        if(pages.length>1){const title=document.createElement('p');title.className='pdf-continuation';title.textContent=document.title+' - continued';page.append(title);}
    }
    const fits=()=>page.getBoundingClientRect().height<=maxHeight;
    const hasContent=()=>Array.from(page.children).some(n=>!n.classList.contains('pdf-continuation'));
    function textPages(text) {
        while(text.length) {
            const fragment=document.createElement('p');fragment.className='pdf-text-fragment';page.append(fragment);
            let low=0,high=text.length;
            while(low<high){const mid=Math.ceil((low+high)/2);fragment.textContent=text.slice(0,mid);if(fits())low=mid;else high=mid-1;}
            if(!low){fragment.remove();if(!hasContent())throw new Error('Text cannot fit the PDF page.');next();continue;}
            fragment.textContent=text.slice(0,low);text=text.slice(low);if(text)next();
        }
    }
    function block(node) {
        const clone=node.cloneNode(true);clone.removeAttribute('id');page.append(clone);
        if(fits())return;
        clone.remove();if(hasContent())next();page.append(clone);
        if(fits())return;
        const text=clone.innerText||clone.textContent;clone.remove();textPages(text);
    }
    function table(sourceTable) {
        let table,body;
        function freshTable() {
            table=sourceTable.cloneNode(false);table.removeAttribute('id');
            if(sourceTable.tHead)table.append(sourceTable.tHead.cloneNode(true));
            body=document.createElement('tbody');table.append(body);page.append(table);
        }
        freshTable();
        for(const original of sourceTable.querySelectorAll('tbody > tr')) {
            let row=original.cloneNode(true);body.append(row);
            if(fits())continue;
            row.remove();if(!body.children.length)table.remove();
            if(hasContent())next();freshTable();body.append(row);
            if(fits())continue;
            row.remove();
            // An exceptionally long description continues across pages, rather
            // than dropping text or shrinking the whole invoice until unreadable.
            let remaining=Array.from(original.cells,c=>c.innerText||c.textContent);
            while(remaining.some(Boolean)) {
                row=document.createElement('tr');remaining.forEach(()=>row.append(document.createElement('td')));body.append(row);
                let consumed=0;
                remaining=remaining.map((text,index)=>{
                    const cell=row.cells[index];cell.className='pdf-cell-fragment';let low=0,high=text.length;
                    while(low<high){const mid=Math.ceil((low+high)/2);cell.textContent=text.slice(0,mid);if(fits())low=mid;else high=mid-1;}
                    cell.textContent=text.slice(0,low);consumed+=low;return text.slice(low);
                });
                if(!consumed)throw new Error('A table row cannot fit the PDF page.');
                if(remaining.some(Boolean)){next();freshTable();}
            }
        }
    }
    try {
        next();
        for(const node of source.children) {if(node.tagName==='TABLE')table(node);else block(node);}
        for(const p of pages)if(p.scrollWidth>p.clientWidth+2 || p.getBoundingClientRect().height>maxHeight+1)throw new Error('PDF content exceeds its page.');
        return {stage,pages};
    } catch(error){stage.remove();throw error;}
};
