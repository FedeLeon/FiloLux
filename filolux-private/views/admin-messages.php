<section class="container section">
    <div class="section-heading"><div><p class="eyebrow">IDEAS QUE LLEGAN A TU TALLER</p><h1>Mensajes.</h1><p>Consultas de trabajos a medida. Actualizá el estado después de contactar a la persona.</p></div></div>
    <?php if(!$available): ?><div class="alert">Para recibir consultas, importá la migración <code>2026-10-15-contact-messages.sql</code> en esta base.</div><?php else: ?>
    <nav class="category-buttons" aria-label="Filtrar mensajes">
        <?php foreach([''=>'Todos','pendiente'=>'Pendientes','respondido'=>'Respondidos'] as $state=>$label): ?>
        <a href="/admin/mensajes<?= $state!==''?'?estado='.$state:'' ?>" class="category-button<?= $filter===$state?' active':'' ?>" <?= $filter===$state?'aria-current="true"':'' ?>><?= $label ?> (<?= $state!==''?$counts[$state]:array_sum($counts) ?>)</a>
        <?php endforeach ?>
    </nav>
    <?php if(!$messages): ?><div class="empty"><h2>No hay mensajes para mostrar.</h2><p>Las consultas del formulario aparecerán acá.</p></div><?php else: ?>
    <div class="inquiry-message-list">
        <?php foreach($messages as $message): ?>
        <article class="form-panel inquiry-message" data-message-id="<?= (int)$message['id'] ?>">
            <div class="inquiry-message-heading"><div><h2><?= e($message['name']) ?></h2><a class="text-link" href="tel:<?= e(preg_replace('/[^0-9+]/','',$message['phone'])) ?>"><?= e($message['phone']) ?></a></div><span class="badge"><?= $message['status']==='respondido'?'Respondido':'Pendiente' ?></span></div>
            <p class="microcopy">Recibido el <?= date('d/m/Y H:i',strtotime($message['created_at'])) ?><?php if($message['responded_at']): ?> · Respondido el <?= date('d/m/Y H:i',strtotime($message['responded_at'])) ?><?php endif ?></p>
            <p class="preserve inquiry-message-body"><?= e($message['message']) ?></p>
            <form method="post" action="/admin/mensajes/<?= (int)$message['id'] ?>/estado"><?= csrf() ?><button class="btn secondary small" name="status" value="<?= $message['status']==='pendiente'?'respondido':'pendiente' ?>"><?= $message['status']==='pendiente'?'Marcar como respondido':'Volver a pendiente' ?></button></form>
        </article>
        <?php endforeach ?>
    </div>
    <nav class="pagination" aria-label="Páginas de mensajes">
        <?php if($page>1): ?><a class="btn secondary" href="/admin/mensajes?<?= e(http_build_query(['estado'=>$filter,'pagina'=>$page-1])) ?>">← Anterior</a><?php endif ?>
        <?php if($page*20<$total): ?><a class="btn secondary" href="/admin/mensajes?<?= e(http_build_query(['estado'=>$filter,'pagina'=>$page+1])) ?>">Siguiente →</a><?php endif ?>
    </nav>
    <?php endif; endif ?>
</section>
