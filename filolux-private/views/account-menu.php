<?php $accountUser=user(); if($accountUser): ?>
<details class="account-menu" data-account-menu>
    <summary class="account-link" aria-label="Cuenta de <?= e($accountUser['name']) ?>"><span><?= e(explode(' ',trim($accountUser['name']))[0]) ?></span><svg class="account-chevron" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg></summary>
    <div class="account-panel">
        <p><strong><?= e($accountUser['name']) ?></strong><small><?= e($accountUser['email']) ?></small></p>
        <a href="/cuenta/perfil"><svg class="account-menu-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/></svg><span>Mi perfil</span></a>
        <a href="/cuenta"><svg class="account-menu-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 3h14v18l-3-2-4 2-4-2-3 2V3Zm4 5h6M9 12h6M9 16h3"/></svg><span>Mis pedidos</span></a>
        <?php if($accountUser['role']==='admin'): ?><a href="/admin"><svg class="account-menu-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z"/></svg><span>Administración</span></a><?php endif ?>
        <form method="post" action="/logout"><?= csrf() ?><button type="submit"><svg class="account-menu-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H4V3h5M9 12h12m-4-4 4 4-4 4"/></svg><span>Cerrar sesión</span></button></form>
    </div>
</details>
<?php else: ?><a class="account-link" href="/login">Ingresar</a><?php endif ?>
