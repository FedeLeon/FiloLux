const { chromium } = require(process.env.FILOLUX_PLAYWRIGHT || 'playwright');
(async () => {
  const browser = await chromium.launch({headless:true,...(process.env.FILOLUX_CHROMIUM ? {executablePath:process.env.FILOLUX_CHROMIUM} : {})});
  const errors = [];
  const page = await browser.newPage({viewport:{width:1440,height:1000},deviceScaleFactor:1});
  page.on('pageerror', e => errors.push(e.message));
  await page.goto('http://127.0.0.1:8087/');
  await page.screenshot({path:'tests/.runtime/home-desktop.png',fullPage:true});
  for (const path of ['/', '/catalogo','/producto/1','/carrito','/contacto','/login','/registro']) {
    await page.goto('http://127.0.0.1:8087'+path);
    if(await page.locator('body').evaluate(e=>e.scrollWidth>innerWidth)) throw Error('Desktop overflow '+path);
  }
  await page.goto('http://127.0.0.1:8087/login');
  await page.getByLabel('Usuario o correo electrónico').fill('admin');
  await page.getByLabel('Contraseña',{exact:true}).fill('AdminTest2026!');
  await page.getByRole('button',{name:'Ingresar'}).click();
  await page.waitForURL('**/admin');
  await page.screenshot({path:'tests/.runtime/admin-desktop.png',fullPage:true});
  for(const path of ['/admin/productos','/admin/productos/nuevo','/admin/categorias','/admin/pedidos','/admin/configuracion']) {
    await page.goto('http://127.0.0.1:8087'+path);
    if(await page.locator('body').evaluate(e=>e.scrollWidth>innerWidth)) throw Error('Admin overflow '+path);
  }
  const mobile=await browser.newPage({viewport:{width:390,height:844},deviceScaleFactor:1,isMobile:true,hasTouch:true});
  mobile.on('pageerror',e=>errors.push(e.message));
  await mobile.goto('http://127.0.0.1:8087/');
  await mobile.screenshot({path:'tests/.runtime/home-mobile.png',fullPage:true});
  await mobile.getByRole('button',{name:'Abrir menú'}).click();
  if(await mobile.locator('.menu-toggle').getAttribute('aria-expanded')!=='true') throw Error('Mobile menu');
  for(const path of ['/','/catalogo','/producto/1','/contacto','/login']) {
    await mobile.goto('http://127.0.0.1:8087'+path);
    if(await mobile.locator('body').evaluate(e=>e.scrollWidth>innerWidth)) throw Error('Mobile overflow '+path);
  }
  await mobile.goto('http://127.0.0.1:8087/producto/1');
  await mobile.getByRole('button',{name:'Agregar al carrito'}).click();
  await mobile.locator('.mini-cart-notice').waitFor();
  if (!mobile.url().endsWith('/producto/1')) throw Error('Agregar debe conservar la ficha');
  await mobile.getByRole('link',{name:'Ver carrito ↗',exact:true}).click();
  await mobile.waitForURL('**/carrito');
  if(!await mobile.getByRole('link',{name:'Continuar compra'}).isVisible()) throw Error('Cart flow');
  await mobile.screenshot({path:'tests/.runtime/cart-mobile.png',fullPage:true});
  await mobile.goto('http://127.0.0.1:8087/login');
  await mobile.getByLabel('Usuario o correo electrónico').fill('admin');
  await mobile.getByLabel('Contraseña',{exact:true}).fill('AdminTest2026!');
  await mobile.getByRole('button',{name:'Ingresar'}).click();
  await mobile.waitForURL('**/admin');
  for(const path of ['/admin','/admin/productos','/admin/productos/nuevo','/admin/categorias','/admin/pedidos','/admin/configuracion']) {
    await mobile.goto('http://127.0.0.1:8087'+path);
    if(await mobile.locator('body').evaluate(e=>e.scrollWidth>innerWidth)) throw Error('Mobile admin overflow '+path);
  }
  await mobile.screenshot({path:'tests/.runtime/settings-mobile.png',fullPage:true});
  if(errors.length) throw Error(errors.join('\n'));
  console.log('PASS: tienda y administración en escritorio y móvil; navegación, menú y carrito; sin errores JS ni desbordamiento.');
  await browser.close();
})().catch(e=>{console.error(e);process.exit(1)});
