<section class="container section">
    <a href="/admin/visitas" class="back-link">← Volver a visitas</a>
    <div class="section-heading"><div><p class="eyebrow">RECORRIDO DEL VISITANTE</p><h1>Visita #<?= (int)$visit['id'] ?>.</h1></div><span class="badge"><?= e($visit['device']) ?></span></div>
    <div class="form-panel visit-card">
        <p class="visit-ip"><?= e($visit['ip']) ?></p>
        <p data-visit-location data-ip="<?= e($visit['ip']) ?>"><?= e(visitLocationLabel($visit)) ?></p>
        <p><?= e($visit['browser']) ?> · <?= e($visit['os']) ?></p>
        <p>Inicio: <?= date('d/m/Y H:i:s',strtotime($visit['started_at'])) ?><br>Última actividad: <?= date('d/m/Y H:i:s',strtotime($visit['last_seen_at'])) ?> · Argentina (UTC−3)</p>
        <details><summary>Información del navegador</summary><p class="preserve"><?= e($visit['user_agent']) ?></p></details>
        <form method="post" action="/admin/visitas/ubicaciones" data-visit-geolocation class="visit-location-form"><?= csrf() ?><button class="btn secondary small">Actualizar ubicaciones</button><span role="status" data-location-status></span></form>
    </div>
    <div class="section-heading visit-products-heading"><h2>Actividad</h2></div>
    <p class="microcopy">Últimos 200 eventos, ordenados desde el más antiguo. Una vista indica que se abrió la página; no mide cuánto tiempo se leyó.</p>
    <ol class="visit-timeline">
        <?php foreach($events as $event): ?>
        <li class="form-panel"><time><?= date('d/m/Y H:i:s',strtotime($event['created_at'])) ?></time><h3><?= ['page_view'=>'Abrió una página','product_view'=>'Vio un producto','cart_add'=>'Agregó al carrito','cart_remove'=>'Quitó del carrito'][$event['type']]??'Actividad' ?></h3>
            <?php if($event['product_name']!==''): ?><p><?= e($event['product_name']) ?><?php if(in_array($event['type'],['cart_add','cart_remove'],true)): ?> · <?= (int)$event['quantity'] ?> unidad(es)<?php endif ?></p><?php endif ?>
            <small><?= e($event['path']) ?></small>
        </li>
        <?php endforeach ?>
    </ol>
</section>
