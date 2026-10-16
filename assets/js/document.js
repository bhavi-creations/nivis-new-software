(() => {
    const ones=['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'];
    const tens=['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
    function words(n) {
        n=Math.floor(n); if(n<20)return ones[n]; if(n<100)return tens[Math.floor(n/10)]+' '+ones[n%10];
        for(const [size,label] of [[10000000,'Crore'],[100000,'Lakh'],[1000,'Thousand'],[100,'Hundred']]) if(n>=size)return words(Math.floor(n/size))+' '+label+' '+words(n%size);
    }
    const amount=document.getElementById('amountWords'); const paise=Math.round(Number(amount.dataset.amount)*100);
    amount.textContent='Rupees '+(words(Math.floor(paise/100))||'Zero')+(paise%100?' and '+words(paise%100)+' Paise':'')+' Only';
    document.getElementById('printDocument').addEventListener('click',()=>window.print());
    document.getElementById('downloadDocument').addEventListener('click',async event=>{
        const message=document.getElementById('pdfMessage');
        if(!window.html2canvas || !window.jspdf){message.textContent='PDF library unavailable. Use Print → Save as PDF, or restore access to the CDN.';return;}
        event.target.disabled=true; message.textContent='Preparing PDF…';
        let layout;
        try {
            await document.fonts.ready;
            await Promise.all(Array.from(document.images).map(img=>img.decode().catch(()=>{})));
            const billingDocument=document.getElementById('billingDocument');
            layout=nivisDocumentPages(billingDocument);
            const pdf=new window.jspdf.jsPDF({unit:'mm',format:'a4',orientation:'portrait'});
            for(let index=0;index<layout.pages.length;index++) {
                message.textContent='Preparing page '+(index+1)+' of '+layout.pages.length+'...';
                const canvas=await html2canvas(layout.pages[index],{scale:2,backgroundColor:'#ffffff',windowWidth:1200,scrollX:0,scrollY:0,useCORS:true,onclone:doc=>{doc.querySelector('.pdf-stage').style.left='0';}});
                if(index)pdf.addPage();
                const scale=Math.min(186/canvas.width,265/canvas.height);
                pdf.addImage(canvas.toDataURL('image/jpeg',0.98),'JPEG',12,12,canvas.width*scale,canvas.height*scale);
                pdf.setFontSize(9);pdf.setTextColor(90);pdf.text(document.title+' | Page '+(index+1)+' / '+layout.pages.length,12,287);
                canvas.width=1;canvas.height=1;
            }
            await pdf.save(billingDocument.dataset.documentType+'-'+document.title+'.pdf',{returnPromise:true});
            message.textContent='PDF downloaded.';
        } catch {message.textContent='PDF export failed. Use Print → Save as PDF.';} finally {layout?.stage.remove();event.target.disabled=false;}
    });
})();
