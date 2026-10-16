<?php
$pendingMessages=hasContactMessages()?(int)query("SELECT COUNT(*) FROM contact_messages WHERE status='pendiente'")->fetchColumn():0;
$newOrders=unreadOrderCount();
$adminLinks=[
    '/admin'=>['Panel','M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z'],
    '/admin/productos'=>['Productos','m12 3 9 5-9 5-9-5 9-5Zm-9 5v9l9 5 9-5V8M12 13v9'],
    '/admin/categorias'=>['Categorías','M3 6h7l2 3h9v12H3V6Zm0 0V3h7l2 3'],
    '/admin/usuarios'=>['Usuarios','M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M22 21v-2a4 4 0 0 0-3-4M16 3a4 4 0 0 1 0 8'],
    '/admin/pedidos'=>['Pedidos','M5 3h14v19l-3-2-4 2-4-2-3 2V3Zm4 5h6M9 12h6M9 16h4'],
    '/admin/mensajes'=>['Mensajes','M3 4h18v13H8l-5 4V4Zm4 5h10M7 13h6'],
    '/admin/visitas'=>['Visitas','M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Zm10-3a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z'],
    '/admin/configuracion'=>['Configuración','M4 6h16M4 12h16M4 18h16M8 3v6M16 9v6M10 15v6'],
];
?>
<!doctype html><html lang="es-AR" data-palette="gold" data-theme="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#111410"><title><?= e($title??'Administración') ?> · <?= e($brandName) ?></title><link rel="icon" type="image/svg+xml" href="/assets/brand/icon-white.svg" data-brand-favicon><script src="<?= e(assetUrl('/assets/theme.js')) ?>"></script><link rel="stylesheet" href="<?= e(assetUrl('/assets/app.css')) ?>"><script src="<?= e(assetUrl('/assets/app.js')) ?>" defer></script></head>
<body class="admin-page">
<a href="#contenido" class="skip">Ir al contenido</a>
<div class="admin-shell">
    <aside class="admin-sidebar" id="admin-sidebar" aria-label="Menú de administración">
        <div class="admin-sidebar-brand"><a class="brand" href="/admin" aria-label="<?= e($brandName) ?>, panel"><?php require ROOT.'/views/brand-mark.php'; ?></a><button class="admin-sidebar-close" type="button" data-sidebar-close aria-label="Cerrar menú de administración">×</button></div>
        <p class="eyebrow admin-sidebar-caption">TU TALLER DIGITAL</p>
        <nav class="admin-side-nav" aria-label="Administración">
            <?php foreach($adminLinks as $href=>[$label,$icon]): $active=$href==='/admin'?$path===$href:($path===$href || str_starts_with($path,$href.'/')); ?>
            <a href="<?= e($href) ?>" <?= $active?'aria-current="page"':'' ?>><svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="<?= e($icon) ?>"/></svg><span><?= e($label) ?></span><?php if($href==='/admin/pedidos'): ?><span class="admin-message-badge admin-order-badge" data-order-count role="img" aria-label="<?= $newOrders ?> pedidos nuevos sin revisar" <?= $newOrders===0?'hidden':'' ?>><?= $newOrders ?></span><?php endif ?><?php if($href==='/admin/mensajes' && $pendingMessages>0): ?><span class="admin-message-badge" role="img" aria-label="<?= $pendingMessages ?> <?= $pendingMessages===1?'mensaje pendiente':'mensajes pendientes' ?>"><?= $pendingMessages ?></span><?php elseif($active): ?><span class="admin-nav-indicator" aria-hidden="true"></span><?php endif ?></a>
            <?php endforeach ?>
        </nav>
        <div class="admin-sidebar-bottom"><a class="admin-store-link" href="/">Ver mi tienda <span>↗</span></a><div class="admin-profile"><span class="admin-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr(user()['name'],0,1))) ?></span><div><strong><?= e(user()['name']) ?></strong><small>Administrador</small></div></div><form method="post" action="/logout"><?= csrf() ?><button class="admin-logout" type="submit">Cerrar sesión <span>↗</span></button></form></div>
    </aside>
    <button class="admin-sidebar-overlay" type="button" data-sidebar-close aria-label="Cerrar menú de administración" hidden></button>
    <div class="admin-workspace">
        <header class="admin-topbar"><div class="admin-topbar-title"><button class="admin-menu-toggle" type="button" data-sidebar-toggle aria-expanded="false" aria-controls="admin-sidebar" aria-label="Abrir menú de administración">☰</button><span>Administración <span class="admin-topbar-separator">/</span> <strong><?= e($title??'Panel') ?></strong></span></div><div class="admin-topbar-actions"><a href="/" class="text-link">Ver tienda ↗</a><?php require ROOT.'/views/theme-toggle.php'; ?></div></header>
        <main id="contenido" class="admin-main"><?php if(isset($_SESSION['flash'])): ?><div class="container"><div class="alert success" role="status"><?= e($_SESSION['flash']) ?></div></div><?php unset($_SESSION['flash']); endif ?><?= $content ?></main>
        <footer class="admin-footer">© <?= date('Y') ?> <?= e($brandName) ?> <span>Ideas que toman forma.</span></footer>
    </div>
</div>
<?php require ROOT.'/views/live-notifications.php'; ?>
</body></html>
