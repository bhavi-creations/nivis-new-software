const fs = require('fs');
const path = require('path');
const cp = require('child_process');
const assert = require('assert');
const root = path.resolve(__dirname, '..');
const php = process.env.PHP_BINARY || 'C:/xampp/php/php.exe';
const db = 'nivis_image_test_' + Date.now();
const env = {...process.env, NIVIS_DB_NAME:db};
const run = code => cp.execFileSync(php, ['-r',code], {cwd:root,env,stdio:'pipe'});
let server;
const photos = new Set();
(async () => {
 try {
  run('require "config/database.php"; database_connection(false)->exec("CREATE DATABASE '+db+'");');
  cp.execFileSync(php,['database/setup.php'],{cwd:root,env,stdio:'pipe'});
  server = cp.spawn(php,['-d','upload_max_filesize=50M','-d','post_max_size=55M','-d','upload_tmp_dir='+require('os').tmpdir(),'-S','127.0.0.1:18764'],{cwd:root,env,stdio:'ignore'});
  const base = 'http://127.0.0.1:18764/api.php?resource=';
  for(let i=0;i<30;i++) { try {await fetch(base+'products');break;} catch {await new Promise(r=>setTimeout(r,100));} }
  const request = async (resource,method='GET',data) => {
   const response=await fetch(base+resource,{method,headers:data && !(data instanceof FormData)?{'Content-Type':'application/json'}:{},body:data instanceof FormData?data:data?JSON.stringify(data):undefined});
   const text=await response.text(); let result; try {result=JSON.parse(text);} catch {throw new Error(resource+' '+text.slice(0,1500));} return {status:response.status,...result};
  };
  const ok = r => {assert(!r.error,JSON.stringify(r));return r;};
  const cat=ok(await request('categories','POST',{name:'Image test category'})).id;
  ok(await request('tax_rates','POST',{name:'Test rate',rate_percent:18,effective_from:'2020-01-01',treatment:'taxable'}));
  const fields={name:'Photo product',sku:'PHOTO-TEST',category_id:cat,hsn:'330499',unit:'PCS',price:100,gst:18,quantity:2};
  const png=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=','base64');
  const form=(values,bytes=png,type='image/png')=>{const f=new FormData();Object.entries(values).forEach(([k,v])=>f.append(k,v));f.append('image',new Blob([bytes],{type}),'photo.png');return f;};
  const id=ok(await request('products','POST',form(fields))).id;
  const get=async()=>ok(await request('products')).data.find(p=>Number(p.id)===Number(id));
  const first=(await get()).image_path;photos.add(first);
  assert.match(first,/^uploads\/photos\/[a-f0-9]{32}\.png$/);assert(fs.existsSync(path.join(root,first)));
  const image=await fetch('http://127.0.0.1:18764/'+first);assert.equal(image.status,200);assert(Buffer.from(await image.arrayBuffer()).equals(png));
  ok(await request('products','POST',{...fields,id,name:'Photo retained'}));assert.equal((await get()).image_path,first);
  ok(await request('products','POST',form({...fields,id})));
  const second=(await get()).image_path;photos.add(second);assert.notEqual(first,second);assert(!fs.existsSync(path.join(root,first)));
  assert.equal((await request('products','POST',form({...fields,id},Buffer.from('<?php echo 1; ?>')))).status,422);
  assert.equal((await get()).image_path,second);
  assert.equal((await request('products','POST',form({...fields,id},Buffer.alloc(50*1024*1024+1)))).status,422);
  const large=Buffer.alloc(50*1024*1024);png.copy(large);
  ok(await request('products','POST',form({...fields,id},large)));
  const largePath=(await get()).image_path;photos.add(largePath);
  assert.equal(fs.statSync(path.join(root,largePath)).size,50*1024*1024);
  const gif=Buffer.from('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7','base64');
  ok(await request('products','POST',form({...fields,id},gif,'image/gif')));
  const gifPath=(await get()).image_path;photos.add(gifPath);assert(gifPath.endsWith('.gif'));
  ok(await request('products&id='+id,'DELETE'));
  assert(!fs.existsSync(path.join(root,gifPath)));assert(!fs.existsSync(path.join(root,second)));
  console.log('PASS: upload, served image bytes, database path, preserve, replace, invalid/oversized rejection, delete cleanup.');
 } finally {
  if(server) server.kill();
  for(const file of photos) {const full=path.join(root,file);if(fs.existsSync(full))fs.unlinkSync(full);}
  run('require "config/database.php"; database_connection(false)->exec("DROP DATABASE IF EXISTS '+db+'");');
 }
})().catch(e=>{console.error(e);process.exitCode=1;});
