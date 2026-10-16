// Run against the isolated fixture server on :8087 (see README.md).
const {chromium} = require(process.env.FILOLUX_PLAYWRIGHT || 'playwright');
const assert = require('node:assert/strict');

(async () => {
  const browser = await chromium.launch({headless:true, ...(process.env.FILOLUX_CHROMIUM ? {executablePath:process.env.FILOLUX_CHROMIUM} : {})});
  try {
    const page = await browser.newPage({isMobile:true, hasTouch:true, reducedMotion:'reduce'});
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const visit = async path => {
      const response = await page.goto('http://127.0.0.1:8087' + path);
      assert.equal(response.status(), 200, path);
      await page.evaluate(() => document.fonts.ready);
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'Overflow: ' + path);
    };
    for (const width of [320,360,390,430,720,768]) {
      await page.setViewportSize({width,height:844});
      for (const theme of ['dark','light']) {
        await visit('/');
        await page.evaluate(theme => { localStorage.setItem('filolux-theme',theme); document.documentElement.dataset.theme = theme; }, theme);
        if (width <= 720) {
          assert.ok(await page.locator('.hero').evaluate(hero => {
            const rings = hero.querySelector('.hero-tech-rings').getBoundingClientRect();
            const bounds = hero.getBoundingClientRect();
            return rings.top >= bounds.top && rings.bottom <= bounds.bottom;
          }), 'Logo rings cross the next section');
          await page.getByRole('button',{name:'Abrir menú',exact:true}).click();
          assert.equal(await page.locator('.menu-toggle').getAttribute('aria-expanded'),'true');
          await page.locator('#main-nav [data-inquiry-open]').click();
          assert.equal(await page.locator('.menu-toggle').getAttribute('aria-expanded'),'false');
          await page.setViewportSize({width,height:480});
          assert.ok(await page.locator('#inquiry-modal').evaluate(dialog => {
            const r = dialog.getBoundingClientRect();
            return dialog.open && r.left >= 0 && r.right <= innerWidth && r.top >= 0 && r.bottom <= innerHeight;
          }), 'Inquiry dialog outside viewport');
          await page.locator('[data-inquiry-close]').first().click();
          await page.setViewportSize({width,height:844});
        }
        for (const path of ['/catalogo','/producto/1','/contacto','/login','/registro','/a-medida']) {
          await visit(path);
          if (width <= 720) {
            assert.ok(await page.locator('.site-header').evaluate(header => {
              const outer = header.getBoundingClientRect();
              return [...header.querySelectorAll('.brand,.header-actions,.menu-toggle')].every(el => {
                const r = el.getBoundingClientRect();
                return r.left >= outer.left - 1 && r.right <= outer.right + 1;
              });
            }), 'Header controls extend beyond their container: ' + path);
            assert.ok(await page.locator('input:not([type=hidden]),textarea,select').evaluateAll(fields => fields.every(el => parseFloat(getComputedStyle(el).fontSize) >= 16)), 'Fields trigger iOS zoom');
          }
          if (path === '/producto/1' && width <= 720) {
            assert.ok(await page.locator('.add-to-cart .btn').evaluate(button => {
              const text = [...button.childNodes].find(node => node.nodeType === Node.TEXT_NODE && node.textContent.trim());
              const range = document.createRange(); range.selectNodeContents(text);
              return range.getClientRects().length === 1;
            }), 'Purchase arrow wraps below its label');
          }
        }
        await visit('/producto/1');
        await page.getByRole('button',{name:'Agregar al carrito'}).click();
        await page.locator('.mini-cart-notice').waitFor();
        assert.ok(await page.locator('#mini-cart').evaluate(panel => {
          const r = panel.getBoundingClientRect();
          return r.left >= 0 && r.right <= innerWidth && r.bottom <= innerHeight;
        }), 'Cart popover outside viewport');
        await visit('/carrito');
        if (width === 320 || width === 390) await page.screenshot({path:`tests/.runtime/cart-${width}-${theme}.png`,fullPage:true});
      }
    }
    await visit('/login');
    await page.getByLabel('Usuario o correo electrónico').fill('admin');
    await page.getByLabel('Contraseña',{exact:true}).fill('AdminTest2026!');
    await page.getByRole('button',{name:'Ingresar'}).click();
    await page.waitForURL('**/admin');
    for (const width of [320,390,430,768,1440]) {
      await page.setViewportSize({width,height:844});
      for (const path of ['/checkout','/cuenta']) await visit(path);
      for (const path of ['/admin','/admin/productos','/admin/productos/nuevo','/admin/categorias','/admin/pedidos','/admin/mensajes','/admin/configuracion']) await visit(path);
      if (width <= 768) {
        await page.getByRole('button',{name:'Abrir menú de administración'}).click();
        await page.locator('.admin-sidebar.is-open').waitFor();
        assert.ok(await page.locator('.admin-side-nav').isVisible());
        await page.locator('.admin-sidebar-close').click();
        assert.equal(await page.locator('[data-sidebar-toggle]').getAttribute('aria-expanded'),'false');
      }
    }
    assert.deepEqual(errors,[]);
    console.log('PASS: distribución en 320–768 px, ambos temas, encabezado, anillos, compra, carrito, modal con altura reducida y administración; sin desbordamiento ni errores JS.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
