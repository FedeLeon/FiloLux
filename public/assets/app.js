'use strict';
// Resolve pending IP locations from the admin, without delaying storefront requests.
(() => {
  const form = document.querySelector('[data-visit-geolocation]');
  if (!form || !window.fetch) return;
  const button = form.querySelector('button');
  const status = form.querySelector('[data-location-status]');
  let busy = false;
  const update = async () => {
    if (busy) return;
    busy = true; button.disabled = true; status.textContent = 'Consultando ubicaciones…';
    try {
      const response = await fetch(form.action, {method:'POST',body:new FormData(form),headers:{Accept:'application/json'}});
      if (!response.ok) throw new Error('location');
      const data = await response.json();
      if (!data.locations || typeof data.locations !== 'object') throw new Error('location');
      document.querySelectorAll('[data-visit-location]').forEach(label => {
        if (Object.hasOwn(data.locations, label.dataset.ip)) label.textContent = data.locations[label.dataset.ip];
      });
      status.textContent = Object.keys(data.locations).length ? 'Consulta terminada. Podés actualizar otra tanda si quedan ubicaciones pendientes.' : 'No hay ubicaciones pendientes de consulta.';
    } catch (_) { status.textContent = 'No pudimos actualizar las ubicaciones. Intentá nuevamente.'; }
    finally { busy = false; button.disabled = false; }
  };
  form.addEventListener('submit', event => { event.preventDefault(); update(); });
  update();
})();
// Keep newly chosen photos editable before the native multipart form is saved.
(() => {
  const input = document.querySelector('[data-product-photo-input]');
  if (!input || !window.DataTransfer || !window.URL?.createObjectURL) return;
  try { input.files = new DataTransfer().files; } catch (_) { return; }
  const form = input.form;
  const previews = form.querySelector('[data-photo-previews]');
  const template = form.querySelector('[data-photo-preview-template]');
  const errorBox = form.querySelector('[data-photo-error]');
  const status = form.querySelector('[data-photo-status]');
  const save = form.querySelector('button.btn.full');
  const chosen = [];
  let queue = Promise.resolve();
  let pending = 0;
  const syncFiles = () => {
    const files = new DataTransfer();
    chosen.forEach(photo => files.items.add(photo.file));
    input.files = files.files;
    previews.hidden = chosen.length === 0;
    status.hidden = false;
    status.textContent = chosen.length === 1 ? '1 foto nueva seleccionada.' : `${chosen.length} fotos nuevas seleccionadas.`;
  };
  const inspect = file => new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file);
    const probe = new Image();
    const fail = message => { URL.revokeObjectURL(url); reject(new Error(message)); };
    probe.onload = () => {
      if (probe.naturalWidth * probe.naturalHeight > 16000000) fail('supera los 16 megapíxeles.');
      else resolve({file, url});
    };
    probe.onerror = () => fail('no se pudo leer como imagen.');
    probe.src = url;
  });
  const addPhoto = photo => {
    const item = template.content.firstElementChild.cloneNode(true);
    const image = item.querySelector('img');
    image.src = photo.url;
    image.alt = `Vista previa de ${photo.file.name}`;
    item.querySelector('figcaption').textContent = photo.file.name;
    const remove = item.querySelector('[data-photo-remove]');
    remove.setAttribute('aria-label', `Quitar ${photo.file.name}`);
    remove.title = 'Quitar foto seleccionada';
    remove.addEventListener('click', () => {
      const position = chosen.indexOf(photo);
      if (position < 0) return;
      chosen.splice(position, 1);
      item.remove(); URL.revokeObjectURL(photo.url); syncFiles();
      errorBox.hidden = true;
      const next = previews.querySelector('[data-photo-remove]');
      (next || input).focus();
    });
    chosen.push(photo); previews.append(item);
  };
  input.addEventListener('change', () => {
    const incoming = [...input.files];
    pending++; if (save) save.disabled = true;
    status.hidden = false; status.textContent = 'Preparando fotos…';
    queue = queue.then(async () => {
      const errors = [];
      for (const file of incoming) {
        if (chosen.some(photo => photo.file.name === file.name && photo.file.size === file.size && photo.file.lastModified === file.lastModified)) continue;
        const kept = form.querySelectorAll('[name="delete_images[]"]:not(:checked)').length;
        if (kept + chosen.length >= 8) { errors.push('Podés guardar hasta 8 fotos por producto.'); break; }
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) { errors.push(`${file.name}: elegí una imagen JPG, PNG o WebP.`); continue; }
        if (file.size > 5 * 1024 * 1024) { errors.push(`${file.name}: supera los 5 MB.`); continue; }
        try { addPhoto(await inspect(file)); } catch (error) { errors.push(`${file.name}: ${error.message}`); }
      }
      syncFiles();
      errorBox.textContent = errors.join(' ');
      errorBox.hidden = errors.length === 0;
    }).catch(() => {
      syncFiles(); errorBox.textContent = 'No pudimos preparar esas fotos. Probá seleccionarlas nuevamente.'; errorBox.hidden = false;
    }).finally(() => { pending--; if (save) save.disabled = pending > 0; });
  });
  form.addEventListener('submit', event => { if (pending > 0) event.preventDefault(); });
  window.addEventListener('pagehide', event => { if (!event.persisted) chosen.forEach(photo => URL.revokeObjectURL(photo.url)); });
})();
// Custom-project inquiries: native dialog and the same usable server-side form.
(() => {
  const dialog = document.querySelector('#inquiry-modal');
  if (!dialog || typeof dialog.showModal !== 'function' || !window.fetch) return;
  const form = dialog.querySelector('[data-inquiry-form]');
  const errorBox = form.querySelector('[data-inquiry-error]');
  const success = dialog.querySelector('[data-inquiry-success]');
  const submit = form.querySelector('[data-inquiry-submit]');
  let busy = false;
  document.querySelectorAll('[data-inquiry-open]').forEach(link => link.addEventListener('click', event => {
    if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
    event.preventDefault();
    if (dialog.open) return;
    if (!success.hidden) { form.reset(); form.hidden = false; success.hidden = true; }
    errorBox.hidden = true;
    document.querySelector('#main-nav')?.classList.remove('open');
    document.querySelector('.menu-toggle')?.setAttribute('aria-expanded', 'false');
    dialog.showModal();
    form.querySelector('[name="name"]').focus();
  }));
  dialog.querySelectorAll('[data-inquiry-close]').forEach(button => button.addEventListener('click', () => dialog.close()));
  dialog.addEventListener('click', event => {
    if (event.target !== dialog) return;
    const bounds = dialog.getBoundingClientRect();
    if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) dialog.close();
  });
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (busy) return;
    busy = true; submit.disabled = true; errorBox.hidden = true;
    form.setAttribute('aria-busy', 'true');
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 15000);
    try {
      const response = await fetch(form.action, {method:'POST', body:new FormData(form), credentials:'same-origin', headers:{Accept:'application/json'}, signal:controller.signal});
      const data = await response.json();
      if (!response.ok) throw new Error(data.error || 'No pudimos enviar tu consulta. Intentá nuevamente.');
      if (typeof data.next_key !== 'string') throw new Error('No pudimos confirmar el envío. Intentá nuevamente.');
      const token = form.querySelector('[name="submission_key"]');
      token.value = token.defaultValue = data.next_key;
      form.hidden = true; success.hidden = false; success.focus();
    } catch (error) {
      errorBox.textContent = error.name === 'AbortError' || error instanceof TypeError || error instanceof SyntaxError
        ? 'No pudimos confirmar el envío. Podés reintentar sin duplicar tu consulta.' : error.message;
      errorBox.hidden = false;
    } finally { clearTimeout(timeout); busy = false; submit.disabled = false; form.removeAttribute('aria-busy'); }
  });
})();
// Catalog arrows are outside the product link, so changing photos never navigates.
document.querySelectorAll('[data-product-photos]').forEach(card => {
  let photos;
  try { photos = JSON.parse(card.dataset.productPhotos); } catch (_) { return; }
  const image = card.querySelector('[data-card-photo]');
  const controls = card.querySelector('[data-card-photo-controls]');
  if (!Array.isArray(photos) || photos.length < 2 || !photos.every(photo => typeof photo === 'string') || !image || !controls) return;
  const name = image.alt;
  const counter = card.querySelector('[data-card-photo-count]');
  const status = card.querySelector('[data-card-photo-status]');
  let index = 0;
  controls.querySelectorAll('[data-card-photo-step]').forEach(button => {
    button.addEventListener('click', () => {
      index = (index + Number(button.dataset.cardPhotoStep) + photos.length) % photos.length;
      image.src = photos[index];
      image.alt = `${name}. Foto ${index + 1} de ${photos.length}`;
      if (counter) counter.textContent = `${index + 1} / ${photos.length}`;
      if (status) status.textContent = `Foto ${index + 1} de ${photos.length} de ${name}`;
    });
  });
  controls.hidden = false;
});
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

// Native checkbox/radio controls keep color choices accessible without JavaScript.
document.querySelectorAll('[data-all-colors]').forEach(button => {
  button.addEventListener('click', () => {
    button.closest('[data-admin-colors]').querySelectorAll('input[type="checkbox"]').forEach(input => { input.checked = true; });
  });
});
