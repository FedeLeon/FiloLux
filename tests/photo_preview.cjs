// Isolated fixture server :8087 and filolux_test only. No production credentials.
const {chromium}=require(process.env.FILOLUX_PLAYWRIGHT || 'playwright');
const {execFileSync}=require('node:child_process');
const fs=require('node:fs');
const os=require('node:os');
const path=require('node:path');
const assert=require('node:assert/strict');
const root=path.resolve(__dirname,'..');
const sql=(statement,args=[])=>JSON.parse(execFileSync('php',['-r',`define('ROOT',getcwd().'/filolux-private');$GLOBALS['config']=require 'tests/.runtime/config.php';require ROOT.'/app/core.php';if($GLOBALS['config']['db']['name']!=='filolux_test')exit(1);$data=json_decode(stream_get_contents(STDIN),true);echo json_encode(query($data[0],$data[1])->fetchAll());`],{cwd:root,input:JSON.stringify([statement,args]),encoding:'utf8'}));
(async()=>{
 const temp=fs.mkdtempSync(path.join(os.tmpdir(),'filolux-photo-preview-'));
 const name='Prueba de fotos '+Date.now();
 const png=execFileSync('php',['-r','$im=imagecreatetruecolor(24,16);imagepng($im);']);
 const photo=filename=>{const target=path.join(temp,filename);fs.writeFileSync(target,png);return target;};
 const files=['a.png','b.png','c.png','d.png','e.png','f.png','g.png','h.png','i.png'].map(photo);
 let browser;
 try {
  sql('SELECT id FROM products LIMIT 1'); // Guard before making requests.
  browser=await chromium.launch({headless:true,...(process.env.FILOLUX_CHROMIUM?{executablePath:process.env.FILOLUX_CHROMIUM}:{})});
  const page=await browser.newPage({reducedMotion:'reduce'});const errors=[];
  page.on('pageerror',error=>errors.push(error.message));
  await page.goto('http://127.0.0.1:8087/login');await page.getByLabel('Usuario o correo electrónico').fill('admin');await page.getByLabel('Contraseña',{exact:true}).fill('AdminTest2026!');await page.getByRole('button',{name:'Ingresar'}).click();await page.waitForURL('**/admin');
  await page.goto('http://127.0.0.1:8087/admin/productos/nuevo');
  const input=page.locator('[data-product-photo-input]');
  const previews=page.locator('[data-photo-previews]');
  const ready=()=>page.waitForFunction(()=>!document.querySelector('button.btn.full').disabled);
  const filenames=()=>input.evaluate(el=>[...el.files].map(file=>file.name));
  await input.setInputFiles(files.slice(0,2));await ready();
  assert.equal(await previews.locator('figure').count(),2);assert.deepEqual(await filenames(),['a.png','b.png']);
  await previews.getByRole('button',{name:'Quitar a.png',exact:true}).click();assert.deepEqual(await filenames(),['b.png']);
  await input.setInputFiles(files[2]);await ready();assert.deepEqual(await filenames(),['b.png','c.png']);
  await input.setInputFiles(files[2]);await ready();assert.deepEqual(await filenames(),['b.png','c.png']);
  for(const bad of [
   {name:'no.svg',mimeType:'image/svg+xml',buffer:Buffer.from('<svg xmlns="http://www.w3.org/2000/svg"/>')},
   {name:'grande.png',mimeType:'image/png',buffer:Buffer.alloc(5*1024*1024+1)},
   {name:'rota.png',mimeType:'image/png',buffer:Buffer.from('not an image')}
  ]) {await input.setInputFiles(bad);await ready();assert.equal(await page.locator('[data-photo-error]').isVisible(),true);assert.deepEqual(await filenames(),['b.png','c.png']);}
  await input.setInputFiles(files.slice(3));await ready();assert.equal((await filenames()).length,8);
  await input.setInputFiles(files[0]);await ready();assert.equal((await filenames()).length,8);assert.ok((await page.locator('[data-photo-error]').innerText()).includes('8 fotos'));
  for(const filename of ['d.png','e.png','f.png','g.png','h.png','i.png'])await previews.getByRole('button',{name:'Quitar '+filename,exact:true}).click();
  assert.deepEqual(await filenames(),['b.png','c.png']);
  for(const width of [320,390,1440])for(const theme of ['dark','light']){
   await page.setViewportSize({width,height:1000});await page.evaluate(t=>document.documentElement.dataset.theme=t,theme);
   const measurements=await previews.locator('figure').first().evaluate(el=>{const image=el.querySelector('img'),button=el.querySelector('button'),svg=button.querySelector('svg');const i=image.getBoundingClientRect(),b=button.getBoundingClientRect(),s=svg.getBoundingClientRect();return{loaded:image.complete&&image.naturalWidth>0,square:Math.abs(i.width-i.height),top:b.y-i.y,right:i.right-b.right,dx:Math.abs(b.x+b.width/2-s.x-s.width/2),dy:Math.abs(b.y+b.height/2-s.y-s.height/2),color:getComputedStyle(button).color};});
   assert.ok(measurements.loaded);assert.ok(measurements.square<1);assert.ok(measurements.top>=7&&measurements.top<=9);assert.ok(measurements.right>=7&&measurements.right<=9);assert.ok(measurements.dx<1&&measurements.dy<1);assert.equal(measurements.color,theme==='dark'?'rgb(239, 116, 116)':'rgb(180, 35, 24)');assert.equal(await page.locator('body').evaluate(el=>el.scrollWidth>innerWidth),false);
  }
  await page.getByLabel('Nombre de la pieza',{exact:true}).fill(name);await page.getByLabel('Descripción',{exact:true}).fill('Foto seleccionada, eliminada y reemplazada antes de guardar.');await page.getByLabel('Precio en ARS',{exact:true}).fill('1234.00');
  const sent=page.waitForRequest(request=>request.method()==='POST'&&request.url().endsWith('/admin/productos/nuevo'));
  await page.getByRole('button',{name:'Guardar producto'}).click();const request=await sent;assert.ok((await request.allHeaders())['content-type'].startsWith('multipart/form-data;'));
  await page.waitForURL('**/admin/productos');
  const rows=sql('SELECT i.path FROM products p JOIN product_images i ON i.product_id=p.id WHERE p.name=?',[name]);assert.equal(rows.length,2);
  assert.deepEqual(errors,[]);
  console.log('PASS: vista previa, papelera roja centrada, quitar/reemplazar/acumular, duplicados, límites, archivos inválidos, multipart y dos fotos guardadas; móvil/escritorio y ambos modos.');
 } finally {
  if(browser)await browser.close();
  for(const row of sql('SELECT i.path FROM products p JOIN product_images i ON i.product_id=p.id WHERE p.name=?',[name])) if(/^uploads\/[a-f0-9]+\.webp$/.test(row.path))fs.rmSync(path.join(root,'public',row.path),{force:true});
  sql('DELETE FROM products WHERE name=?',[name]);fs.rmSync(temp,{recursive:true,force:true});
 }
})().catch(error=>{console.error(error);process.exitCode=1;});
