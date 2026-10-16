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
        if(!window.html2pdf){message.textContent='PDF library unavailable. Use Print → Save as PDF, or restore access to the CDN.';return;}
        event.target.disabled=true; message.textContent='Preparing PDF…';
        try {
            await document.fonts.ready;
            await Promise.all(Array.from(document.images).map(img=>img.decode().catch(()=>{})));
            await html2pdf().set({margin:0,filename:document.title+'.pdf',image:{type:'jpeg',quality:0.98},html2canvas:{scale:2,backgroundColor:'#ffffff',windowWidth:1000},jsPDF:{unit:'mm',format:'a4',orientation:'portrait'},pagebreak:{mode:['css','legacy'],avoid:['tr','.summary','.signature']}}).from(document.getElementById('billingDocument')).save();
            message.textContent='PDF downloaded.';
        } catch {message.textContent='PDF export failed. Use Print → Save as PDF.';} finally {event.target.disabled=false;}
    });
})();
