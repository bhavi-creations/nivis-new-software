const assert=require('assert'),fs=require('fs'),vm=require('vm');
let click,removed=false,saved=false;const images=[],footers=[];
const nodes={amountWords:{dataset:{amount:'100'}},printDocument:{addEventListener(){}},downloadDocument:{addEventListener(t,f){click=f}},pdfMessage:{},billingDocument:{dataset:{documentType:'quotation'}}};
class PDF{addPage(){} addImage(data,type,x,y,w,h){images.push({x,y,w,h});}setFontSize(){}setTextColor(){}text(t,x,y){footers.push(y);}async save(){saved=true;}}
const context={document:{getElementById:id=>nodes[id],fonts:{ready:Promise.resolve()},images:[],title:'QT-test'},window:{jspdf:{jsPDF:PDF},html2canvas:true},nivisDocumentPages:()=>({stage:{remove(){removed=true}},pages:[{width:1400,height:2300},{width:1600,height:800}]}),html2canvas:async page=>({width:page.width,height:page.height,toDataURL:()=>''})};
vm.runInNewContext(fs.readFileSync('assets/js/document.js','utf8'),context);
(async()=>{const target={disabled:false};await click({target});assert(saved);assert(removed);assert.equal(images.length,2);for(const i of images){assert(i.x+i.w<=198.001);assert(i.y+i.h<=277.001);}assert(footers.every(y=>y===287));assert(!target.disabled);console.log('PASS: PDF images fit A4 width and height with footer clearance; cleanup and download complete.');})().catch(e=>{console.error(e);process.exitCode=1});
