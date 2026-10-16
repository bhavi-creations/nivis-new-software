// Deterministic pagination tests. These verify content preservation and control
// flow with a layout model; they do not replace browser/PDF visual inspection.
const assert=require('assert'),fs=require('fs'),vm=require('vm');
class Element {
 constructor(tag){this.tagName=tag.toUpperCase();this.children=[];this.style={};this.className='';this.text='';this.parent=null;this.fixedHeight=0;}
 get classList(){return {contains:c=>this.className.split(' ').includes(c)};}
 append(...nodes){for(const n of nodes){n.remove();n.parent=this;this.children.push(n);}}
 remove(){if(this.parent)this.parent.children=this.parent.children.filter(n=>n!==this);this.parent=null;}
 setAttribute(){} removeAttribute(){}
 get textContent(){return this.text+this.children.map(c=>c.textContent).join('');}
 set textContent(v){this.text=String(v);this.children=[];}
 get innerText(){return this.textContent;}
 get cells(){return this.children;}
 get tHead(){return this.children.find(c=>c.tagName==='THEAD');}
 get clientWidth(){return 703;}get scrollWidth(){return 703;}
 getBoundingClientRect(){
  let height=this.fixedHeight;
  if(this.style.height==='265mm')height=1000;
  else if(this.tagName==='TR')height=Math.max(24,...this.children.map(c=>c.getBoundingClientRect().height));
  else if(this.tagName==='TD')height=Math.max(24,Math.ceil(this.textContent.length/30)*15);
  else if(this.children.length)height+=this.children.reduce((n,c)=>n+c.getBoundingClientRect().height,0);
  else if(this.text)height+=Math.ceil(this.text.length/80)*20;
  return {height};
 }
 cloneNode(deep=false){const c=new Element(this.tagName);c.className=this.className;c.text=this.text;c.fixedHeight=this.fixedHeight;if(deep)this.children.forEach(n=>c.append(n.cloneNode(true)));return c;}
 querySelectorAll(){return this.children.filter(c=>c.tagName==='TBODY').flatMap(c=>c.children);}
}
const document={body:new Element('body'),title:'QT-TEST',createElement:t=>new Element(t)},window={};
vm.runInNewContext(fs.readFileSync(require('path').join(__dirname,'../assets/js/document-pages.js'),'utf8'),{window,document});
function source(rows){const root=new Element('main');root.className='paper document-quotation';const header=new Element('header');header.fixedHeight=250;root.append(header);const table=new Element('table'),head=new Element('thead'),body=new Element('tbody');head.fixedHeight=30;table.append(head,body);for(const text of rows){const row=new Element('tr'),cell=new Element('td');cell.textContent=text;row.append(cell);body.append(row);}root.append(table);return root;}
const lines=Array.from({length:100},(_,i)=>'Product '+i);
let layout=window.nivisDocumentPages(source(lines));
assert(layout.pages.length>1);
const output=layout.pages.flatMap(p=>p.children.filter(n=>n.tagName==='TABLE').flatMap(t=>{assert(t.tHead);return t.querySelectorAll().map(r=>r.textContent);}));
assert.deepEqual(output,lines);layout.pages.forEach(p=>assert(p.getBoundingClientRect().height<=1000));layout.stage.remove();
const longText='Long product description '.repeat(300);
layout=window.nivisDocumentPages(source([longText]));
const fragments=layout.pages.flatMap(p=>p.children.filter(n=>n.tagName==='TABLE').flatMap(t=>t.querySelectorAll().map(r=>r.textContent)));
assert.equal(fragments.join(''),longText);layout.pages.forEach(p=>assert(p.getBoundingClientRect().height<=1000));layout.stage.remove();
const terms=source(['Product']);const paragraph=new Element('p');paragraph.textContent='Payment and delivery terms. '.repeat(1000);terms.append(paragraph);
layout=window.nivisDocumentPages(terms);
assert.equal(layout.pages.flatMap(p=>p.children.filter(c=>c.className==='pdf-text-fragment').map(c=>c.textContent)).join(''),paragraph.textContent);
layout.pages.forEach(p=>assert(p.getBoundingClientRect().height<=1000));layout.stage.remove();
assert.equal(document.body.children.length,0);
console.log('PASS: multipage rows, repeated table headers, oversized row continuation, long terms, content preservation and cleanup (layout model).');
