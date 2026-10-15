// Restore light/dark mode before styles load; graphite and gold is the fixed palette.
(() => {
  const root = document.documentElement;
  let theme = 'dark';
  try {
    const saved = localStorage.getItem('filolux-theme');
    if (saved === 'light' || saved === 'dark') theme = saved;
    else {
      const previous = localStorage.getItem('filolux-variant');
      if (['gold-light', 'copper-light', 'rose-light'].includes(previous)) theme = 'light';
    }
    localStorage.setItem('filolux-theme', theme);
    localStorage.removeItem('filolux-variant');
  } catch (_) { /* Switching themes also works when storage is unavailable. */ }
  const apply = () => {
    root.dataset.theme = theme;
    root.dataset.palette = 'gold';
    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.content = theme === 'dark' ? '#171717' : '#F7F3EB';
    const favicon = document.querySelector('link[data-brand-favicon]');
    if (favicon) favicon.href = `/assets/brand/icon-${theme === 'dark' ? 'white' : 'black'}.svg`;
    document.querySelectorAll('[data-theme-toggle]').forEach(button => {
      const label = theme === 'dark' ? 'Activar modo claro' : 'Activar modo oscuro';
      button.setAttribute('aria-label', label);
      button.title = label;
    });
  };
  apply();
  document.addEventListener('DOMContentLoaded', () => {
    apply();
    document.querySelectorAll('[data-theme-toggle]').forEach(button => button.addEventListener('click', () => {
      theme = theme === 'dark' ? 'light' : 'dark';
      try { localStorage.setItem('filolux-theme', theme); } catch (_) { /* Optional persistence. */ }
      apply();
    }));
  });
})();
