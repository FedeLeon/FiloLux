'use strict';
const menu = document.querySelector('.menu-toggle');
menu?.addEventListener('click', () => {
  const expanded = menu.getAttribute('aria-expanded') !== 'true';
  menu.setAttribute('aria-expanded', String(expanded));
  document.querySelector('#main-nav')?.classList.toggle('open', expanded);
});
document.querySelectorAll('[data-image]').forEach(button => button.addEventListener('click', () => {
  document.querySelector('#main-product-image').src = button.dataset.image;
  document.querySelectorAll('[data-image]').forEach(b => b.classList.toggle('selected', b === button));
}));
document.querySelectorAll('[data-back]').forEach(button => button.addEventListener('click', () => {
  if (history.length > 1) history.back(); else location.assign('/');
}));
document.querySelectorAll('[data-confirm]').forEach(button => button.addEventListener('click', event => {
  if (!confirm(button.dataset.confirm)) event.preventDefault();
}));
const checkout = document.querySelector('#checkout-form');
if (checkout) {
  const money = n => '$ ' + (n / 100).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const update = () => {
    const shipping = checkout.querySelector('[name="delivery"]:checked').value === 'shipping';
    const address = document.querySelector('#address-field');
    address.hidden = !shipping;
    address.querySelector('textarea').required = shipping;
    const shippingLabel = document.querySelector('#shipping-total');
    const cost = shipping ? Number(shippingLabel.dataset.shipping) : 0;
    shippingLabel.textContent = shipping ? money(cost) : 'Gratis';
    const total = document.querySelector('#checkout-total');
    total.textContent = money(Number(total.dataset.subtotal) + cost);
  };
  checkout.querySelectorAll('[name="delivery"]').forEach(input => input.addEventListener('change', update));
  update();
}
// Native forms remain available when the cart enhancement is unavailable.

document.querySelectorAll('[data-submit-on-change]').forEach(select => {
  select.addEventListener('change', () => select.form?.requestSubmit());
});

const adminSidebar = document.querySelector('#admin-sidebar');
const adminMenuToggle = document.querySelector('[data-sidebar-toggle]');
if (adminSidebar && adminMenuToggle) {
  const compactAdmin = matchMedia('(max-width: 1000px)');
  const overlay = document.querySelector('.admin-sidebar-overlay');
  const setAdminMenu = (open, restoreFocus = false) => {
    open = open && compactAdmin.matches;
    adminSidebar.classList.toggle('is-open', open);
    adminSidebar.inert = compactAdmin.matches && !open;
    adminMenuToggle.setAttribute('aria-expanded', String(open));
    document.body.classList.toggle('admin-menu-open', open);
    if (overlay) overlay.hidden = !open;
    if (open) adminSidebar.querySelector('[data-sidebar-close]')?.focus();
    else if (restoreFocus) adminMenuToggle.focus();
  };
  adminMenuToggle.addEventListener('click', () => setAdminMenu(adminMenuToggle.getAttribute('aria-expanded') !== 'true'));
  document.querySelectorAll('[data-sidebar-close]').forEach(button => button.addEventListener('click', () => setAdminMenu(false, true)));
  document.addEventListener('keydown', event => {
    if (adminMenuToggle.getAttribute('aria-expanded') !== 'true') return;
    if (event.key === 'Escape') { event.preventDefault(); setAdminMenu(false, true); }
    if (event.key === 'Tab') {
      const controls = [...adminSidebar.querySelectorAll('a[href], button, input:not([type="hidden"])')].filter(el => el.getClientRects().length);
      const first = controls[0], last = controls[controls.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
    }
  });
  compactAdmin.addEventListener('change', () => setAdminMenu(false));
  setAdminMenu(false);
}

// Enhance the same session-based forms with a non-modal cart beside its icon.
(() => {
  const trigger = document.querySelector('[data-cart-toggle]');
  const panel = document.querySelector('#mini-cart');
  if (!trigger || !panel || !window.fetch) return;
  const content = panel.querySelector('[data-mini-cart-content]');
  const errorBox = panel.querySelector('[data-cart-error]');
  let opener = trigger;
  let queue = Promise.resolve();
  const enqueue = task => { const result = queue.then(task, task); queue = result.catch(() => {}); return result; };
  const setOpen = (open, restoreFocus = false, from = trigger) => {
    panel.hidden = !open;
    trigger.setAttribute('aria-expanded', String(open));
    if (open) {
      opener = from;
      document.querySelector('#main-nav')?.classList.remove('open');
      document.querySelector('.menu-toggle')?.setAttribute('aria-expanded', 'false');
      panel.querySelector('.cart-popover-close')?.focus({preventScroll:true});
    } else if (restoreFocus) (opener?.isConnected ? opener : trigger).focus({preventScroll:true});
  };
  const showError = message => {
    errorBox.textContent = message;
    errorBox.hidden = false;
    panel.querySelector('[data-cart-error-link]').hidden = false;
    setOpen(true);
  };
  const apply = (data, refreshPage = false) => {
    if (typeof data.html !== 'string' || !Number.isInteger(data.count)) throw new Error('Respuesta inválida del carrito.');
    content.innerHTML = data.html;
    document.querySelectorAll('[data-cart-count]').forEach(badge => badge.textContent = String(data.count));
    trigger.setAttribute('aria-label', `Carrito, ${data.count} ${data.count === 1 ? 'producto' : 'productos'}`);
    if (refreshPage && location.pathname === '/carrito' && typeof data.cartHtml === 'string') {
      const page = document.querySelector('[data-cart-page]');
      if (page) page.outerHTML = data.cartHtml;
    }
    errorBox.hidden = true;
    panel.querySelector('[data-cart-error-link]').hidden = true;
  };
  const request = async (endpoint, body) => {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 15000);
    panel.setAttribute('aria-busy','true');
    try {
      const response = await fetch(endpoint, {method:body?'POST':'GET',body,credentials:'same-origin',headers:{Accept:'application/json'},cache:'no-store',signal:controller.signal});
      const data = await response.json();
      if (!response.ok) throw new Error(data.error || 'No pudimos actualizar el carrito.');
      return data;
    } finally { clearTimeout(timeout); panel.removeAttribute('aria-busy'); }
  };
  trigger.addEventListener('click', event => {
    if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
    event.preventDefault();
    if (!panel.hidden) { setOpen(false, true); return; }
    setOpen(true);
    enqueue(async () => { try { apply(await request('/carrito/resumen')); } catch (_) { showError('No pudimos cargar el resumen. Podés revisar el carrito completo.'); } });
  });
  document.addEventListener('submit', event => {
    const form = event.target;
    const button = event.submitter;
    const adding = form.matches('.add-to-cart');
    const removing = button?.matches('[data-cart-remove]');
    if (!adding && !removing) return;
    event.preventDefault();
    if (button?.disabled) return;
    const body = new FormData(form);
    if (button?.name) body.set(button.name, button.value);
    const endpoint = removing ? '/carrito/quitar' : '/carrito/agregar';
    if (button) button.disabled = true;
    enqueue(async () => {
      try {
        apply(await request(endpoint, body), true);
        if (adding) setOpen(true, false, button || trigger);
        else if (!panel.hidden) panel.querySelector('.cart-popover-close')?.focus({preventScroll:true});
      } catch (error) {
        showError(error.name === 'AbortError' || error instanceof TypeError || error instanceof SyntaxError
          ? 'No pudimos confirmar la actualización. Revisá el carrito completo antes de volver a intentarlo.'
          : error.message);
      } finally { if (button?.isConnected) button.disabled = false; }
    });
  });
  panel.addEventListener('click', event => { if (event.target.closest('[data-cart-close]')) setOpen(false, true); });
  document.addEventListener('click', event => { if (!panel.hidden && !panel.contains(event.target) && !trigger.contains(event.target)) setOpen(false); });
  document.addEventListener('keydown', event => { if (event.key === 'Escape' && !panel.hidden) { event.preventDefault(); setOpen(false, true); } });
  document.addEventListener('focusin', event => { if (!panel.hidden && !panel.contains(event.target) && !trigger.contains(event.target)) setOpen(false); });
})();
