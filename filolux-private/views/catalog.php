<?php
$catalogLink=function(array $changes=[]) use ($filters): string {
    $params=array_merge($filters,$changes);
    $params=array_filter($params,fn($value)=>$value!=='' && $value!==0);
    return '/catalogo?'.http_build_query($params);
};
?>
<section class="container section">
    <p class="eyebrow">PIEZAS CON IDENTIDAD</p>
    <div class="section-heading">
        <div><h1>Encontrá tu próxima idea.</h1><p>Diseñadas con intención. Fabricadas especialmente para vos.</p></div>
        <div class="catalog-heading-actions">
            <span class="result-count" role="status"><?= $total ?> <?= $total===1?'pieza':'piezas' ?></span>
            <details class="catalog-download" data-catalog-download>
                <summary class="btn small secondary">Descargar catálogo <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M12 3v12m-5-5 5 5 5-5M4 16v5h16v-5"/></svg></summary>
                <div class="catalog-download-panel">
                    <p>Elegí un formato</p>
                    <a href="/catalogo/descargar/pdf" download>PDF <small>Con fotos, para compartir o imprimir.</small></a>
                    <a href="/catalogo/descargar/xlsx" download>Excel (.xlsx) <small>Planilla con datos, fotos y enlaces.</small></a>
                    <a href="/catalogo/descargar/csv" download>CSV <small>Para importar en otras herramientas.</small></a>
                    <small class="catalog-download-note">Catálogo completo por categorías, sin productos repetidos.</small>
                </div>
            </details>
        </div>
    </div>
    <nav class="category-buttons" aria-label="Categorías del catálogo">
        <a class="category-button<?= $filters['categoria']===0?' active':'' ?>" href="<?= e($catalogLink(['categoria'=>0])) ?>" <?= $filters['categoria']===0?'aria-current="true"':'' ?>>Todas las categorías</a>
        <?php foreach($categories as $c): $selected=$filters['categoria']===(int)$c['id']; ?>
            <a class="category-button<?= $selected?' active':'' ?>" href="<?= e($catalogLink(['categoria'=>(int)$c['id']])) ?>" <?= $selected?'aria-current="true"':'' ?>><?= e($c['name']) ?></a>
        <?php endforeach ?>
    </nav>
    <form class="catalog-filters" method="get" action="/catalogo">
        <?php if($filters['categoria']): ?><input type="hidden" name="categoria" value="<?= $filters['categoria'] ?>"><?php endif ?>
        <label class="search-field"><span class="sr-only">Buscar productos</span><input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Buscá algo especial…" maxlength="100"></label>
        <label class="catalog-sort">Ordenar por<select name="orden" data-submit-on-change><?php foreach($sortOptions as $value=>$option): ?><option value="<?= e($value) ?>" <?= $filters['orden']===$value?'selected':'' ?>><?= e($option['label']) ?></option><?php endforeach ?></select></label>
        <button class="btn small">Buscar ↗</button>
        <a class="text-link" href="/catalogo">Limpiar</a>
    </form>
    <?php if(in_array($filters['orden'],['precio_asc','precio_desc'],true)): ?><p class="microcopy catalog-sort-note">Los trabajos personalizados sin precio aparecen al final.</p><?php endif ?>
    <?php if($products): ?>
        <div class="product-grid"><?php foreach($products as $p) require ROOT.'/views/product-card.php' ?></div>
        <nav class="pagination" aria-label="Páginas del catálogo">
            <?php if($page>1): ?><a class="btn secondary" href="<?= e($catalogLink(['pagina'=>$page-1])) ?>">← Anterior</a><?php endif ?>
            <?php if($page*12<$total): ?><a class="btn secondary" href="<?= e($catalogLink(['pagina'=>$page+1])) ?>">Siguiente →</a><?php endif ?>
        </nav>
    <?php else: ?>
        <div class="empty"><span class="little-star"><span class="brand-symbol" aria-hidden="true"></span></span><h2>No hay piezas para mostrar todavía.</h2><p>Probá otra búsqueda o consultanos por tu idea.</p><a class="text-link" href="/contacto">Contactanos ↗</a></div>
    <?php endif ?>
</section>
