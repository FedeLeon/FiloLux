<section class="container section">
    <div class="section-heading"><div><p class="eyebrow">ACTIVIDAD EN TU TIENDA</p><h1>Visitas.</h1><p>Conocé cómo llegan y qué exploran tus visitantes. Horarios de Argentina (UTC−3).</p></div></div>
    <?php if(!$available): ?><div class="alert">Para empezar a registrar visitas, importá la migración <code>2026-10-15-site-visits.sql</code> en esta base.</div><?php else: ?>
    <form method="get" class="form-panel visit-filters">
        <label>Desde<input type="date" name="desde" value="<?= e($filters['desde']) ?>" required></label>
        <label>Hasta<input type="date" name="hasta" value="<?= e($filters['hasta']) ?>" required></label>
        <label>Dispositivo<select name="dispositivo"><?php foreach([''=>'Todos','Celular'=>'Celular','Tablet'=>'Tablet','Escritorio'=>'Escritorio','Bot'=>'Bot / rastreador'] as $value=>$label): ?><option value="<?= e($value) ?>" <?= $filters['dispositivo']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach ?></select></label>
        <label>IP<input name="ip" value="<?= e($filters['ip']) ?>" maxlength="45" placeholder="Todas las IP"></label>
        <label class="check"><input type="checkbox" name="carrito" value="1" <?= $filters['carrito']==='1'?'checked':'' ?>>Agregaron al carrito</label>
        <button class="btn small">Filtrar</button><a href="/admin/visitas" class="text-link">Limpiar</a>
    </form>
    <div class="stats-grid">
        <div class="stat"><span>Visitas</span><strong><?= $total ?></strong><span>↗</span></div>
        <div class="stat"><span>Páginas vistas</span><strong><?= (int)$summary['pages'] ?></strong><span>↗</span></div>
        <div class="stat"><span>Visitas con carrito</span><strong><?= (int)$summary['carts'] ?></strong><span>↗</span></div>
    </div>
    <p class="microcopy">Una visita agrupa actividad hasta 30 minutos de inactividad. Se excluye al administrador conectado. Los dispositivos y bots se estiman a partir del navegador. La IP puede ser compartida por varias personas.</p>
    <form method="post" action="/admin/visitas/ubicaciones" data-visit-geolocation class="visit-location-form"><?= csrf() ?><button class="btn secondary small">Actualizar ubicaciones</button><span role="status" data-location-status>Se consultan hasta 10 IP pendientes por actualización.</span></form>
    <p class="microcopy">Ubicación aproximada por IP mediante GeoJS; puede variar con VPN y redes móviles. No es GPS ni una dirección exacta.</p>
    <?php if(!$visits): ?><div class="empty"><h2>No hay visitas para estos filtros.</h2><p>Las nuevas visitas se registran desde que se instala esta función.</p></div><?php else: ?>
    <div class="inquiry-message-list">
        <?php foreach($visits as $visit): ?>
        <article class="form-panel visit-card">
            <div class="inquiry-message-heading"><div><h2>Visita #<?= (int)$visit['id'] ?></h2><p class="visit-ip"><?= e($visit['ip']) ?></p></div><span class="badge"><?= e($visit['device']) ?></span></div>
            <p data-visit-location data-ip="<?= e($visit['ip']) ?>"><?= e(visitLocationLabel($visit)) ?></p>
            <p><?= e($visit['browser']) ?> · <?= e($visit['os']) ?></p>
            <p class="microcopy">Inicio: <?= date('d/m/Y H:i:s',strtotime($visit['started_at'])) ?><br>Última actividad: <?= date('d/m/Y H:i:s',strtotime($visit['last_seen_at'])) ?></p>
            <p><?= (int)$visit['pages'] ?> páginas · <?= (int)$visit['products'] ?> vistas de productos · <?= $visit['has_cart']?'Agregó al carrito':'Sin productos agregados' ?></p>
            <a class="btn secondary small" href="/admin/visitas/<?= (int)$visit['id'] ?>">Ver recorrido ↗</a>
        </article>
        <?php endforeach ?>
    </div>
    <nav class="pagination" aria-label="Páginas de visitas">
        <?php if($page>1): ?><a class="btn secondary" href="/admin/visitas?<?= e(http_build_query($filters+['pagina'=>$page-1])) ?>">← Anterior</a><?php endif ?>
        <?php if($page*20<$total): ?><a class="btn secondary" href="/admin/visitas?<?= e(http_build_query($filters+['pagina'=>$page+1])) ?>">Siguiente →</a><?php endif ?>
    </nav>
    <?php endif ?>
    <?php if($topProducts): ?>
    <div class="section-heading visit-products-heading"><h2>Productos explorados</h2></div>
    <div class="table-wrap"><table><thead><tr><th>Producto</th><th>Vistas</th><th>Unidades agregadas</th></tr></thead><tbody><?php foreach($topProducts as $product): ?><tr><td><?= e($product['name']) ?></td><td><?= (int)$product['views'] ?></td><td><?= (int)$product['added'] ?></td></tr><?php endforeach ?></tbody></table></div>
    <?php endif ?>
    <p class="microcopy">Se conservan 90 días de actividad; la limpieza se ejecuta al abrir esta sección. Los recorridos muestran páginas abiertas y cambios del carrito, sin datos de formularios ni contraseñas.</p>
    <?php endif ?>
</section>
