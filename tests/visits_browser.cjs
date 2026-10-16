const {chromium}=require(process.env.FILOLUX_PLAYWRIGHT || 'playwright');
const assert=require('node:assert/strict');
(async()=>{
 const browser=await chromium.launch({headless:true,...(process.env.FILOLUX_CHROMIUM?{executablePath:process.env.FILOLUX_CHROMIUM}:{})});
 try {
  const visitor=await browser.newPage({viewport:{width:390,height:844},isMobile:true,hasTouch:true});
  await visitor.goto('http://127.0.0.1:8087/');
  await visitor.goto('http://127.0.0.1:8087/producto/1');
  await visitor.getByRole('button',{name:'Agregar al carrito'}).click();
  await visitor.locator('.mini-cart-notice').waitFor();
  const page=await browser.newPage();const errors=[];
  page.on('pageerror',e=>errors.push(e.message));
  await page.goto('http://127.0.0.1:8087/login');
  await page.getByLabel('Usuario o correo electrónico').fill('admin');
  await page.getByLabel('Contraseña',{exact:true}).fill('AdminTest2026!');
  await page.getByRole('button',{name:'Ingresar'}).click();await page.waitForURL('**/admin');
  for(const width of [320,390,768,1440])for(const theme of ['dark','light']){
   await page.setViewportSize({width,height:900});
   await page.goto('http://127.0.0.1:8087/admin/visitas');
   await page.evaluate(t=>{localStorage.setItem('filolux-theme',t);document.documentElement.dataset.theme=t;},theme);
   await page.waitForFunction(()=>!document.querySelector('[data-visit-geolocation] button').disabled);
   assert.ok(await page.locator('[data-location-status]').innerText());
   assert.ok(await page.getByRole('heading',{name:'Visitas.',exact:true}).isVisible());
   assert.ok(await page.locator('body').evaluate(el=>el.scrollWidth<=innerWidth));
   await page.getByLabel('Agregaron al carrito').check();await page.getByRole('button',{name:'Filtrar',exact:true}).click();
   await page.waitForURL('**/admin/visitas?**');
   const card=page.locator('.visit-card').first();assert.ok((await card.innerText()).includes('Agregó al carrito'));
   await page.screenshot({path:`tests/.runtime/visits-${width}-${theme}.png`,fullPage:true});
   await card.getByRole('link',{name:'Ver recorrido'}).click();await page.waitForURL(/\/admin\/visitas\/\d+$/);
   await page.waitForFunction(()=>!document.querySelector('[data-visit-geolocation] button').disabled);
   assert.ok(await page.getByRole('heading',{name:'Vio un producto',exact:true}).isVisible());
   assert.ok(await page.getByRole('heading',{name:'Agregó al carrito',exact:true}).isVisible());
   assert.ok(await page.locator('body').evaluate(el=>el.scrollWidth<=innerWidth));
   await page.screenshot({path:`tests/.runtime/visit-detail-${width}-${theme}.png`,fullPage:true});
  }
  assert.deepEqual(errors,[]);
  console.log('PASS: visita real, producto y carrito; listado, filtros y recorrido en 320/390/768/1440 px, ambos temas, geolocalización privada sin errores ni desbordamiento.');
 } finally { await browser.close(); }
})().catch(e=>{console.error(e);process.exitCode=1});
