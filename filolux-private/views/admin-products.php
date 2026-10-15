<?php
$adminProductLink=function(int|string $category) use ($search): string {
    $params=[];
    if($category!==0) $params['categoria']=$category;
    if($search!=='') $params['q']=$search;
    return '/admin/productos'.($params?'?'.http_build_query($params):'');
};
?>
<section class="container section">
    <div class="section-heading"><div><p class="eyebrow">TU CATÁLOGO</p><h1>Productos.</h1></div><a href="/admin/productos/nuevo" class="btn">Nueva pieza +</a></div>
    <nav class="category-buttons admin-product-categories" aria-label="Filtrar productos por categoría">
        <a class="category-button<?= $selectedCategory===0?' active':'' ?>" href="<?= e($adminProductLink(0)) ?>" <?= $selectedCategory===0?'aria-current="true"':'' ?>>Todas las categorías</a>
        <?php foreach($categories as $c): $selected=$selectedCategory===(int)$c['id']; ?>
            <a class="category-button<?= $selected?' active':'' ?>" href="<?= e($adminProductLink((int)$c['id'])) ?>" <?= $selected?'aria-current="true"':'' ?>><?= e($c['name']) ?></a>
        <?php endforeach ?>
        <a class="category-button<?= $selectedCategory==='sin_categoria'?' active':'' ?>" href="<?= e($adminProductLink('sin_categoria')) ?>" <?= $selectedCategory==='sin_categoria'?'aria-current="true"':'' ?>>Sin categoría</a>
    </nav>
    <form class="catalog-filters admin-products-filters" method="get" action="/admin/productos" role="search" aria-label="Buscar productos">
        <?php if($selectedCategory!==0): ?><input type="hidden" name="categoria" value="<?= e($selectedCategory) ?>"><?php endif ?>
        <label class="search-field"><span class="sr-only">Buscar productos</span><input type="search" name="q" value="<?= e($search) ?>" maxlength="100" placeholder="Nombre, descripción o medidas…"></label>
        <button class="btn small" type="submit">Buscar ↗</button>
        <a class="text-link" href="/admin/productos">Limpiar</a>
    </form>
    <?php if(!$products): ?>
        <?php if($search!==''): ?>
            <div class="empty"><h2>No encontramos productos.</h2><p>Probá otra búsqueda o cambiá de categoría.</p><a class="btn secondary" href="/admin/productos">Limpiar filtros ↗</a></div>
        <?php elseif($selectedCategory!==0): ?>
            <div class="empty"><h2>No hay productos en esta categoría.</h2><p>Elegí otra categoría o agregá una nueva pieza a tu catálogo.</p><a class="btn secondary" href="/admin/productos">Ver todos los productos ↗</a></div>
        <?php else: ?>
            <div class="empty"><h2>Tu catálogo empieza con una pieza.</h2><p>Subí las fotos, contá su historia y elegí cómo ofrecerla.</p><a class="btn" href="/admin/productos/nuevo">Crear mi primer producto ↗</a></div>
        <?php endif ?>
    <?php else: ?>
        <div class="table-wrap"><table><thead><tr><th scope="col" class="admin-products-photo">Foto</th><th scope="col">Pieza</th><th scope="col">Categorías</th><th scope="col">Precio</th><th scope="col">Visibilidad</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead><tbody><?php foreach($products as $p): ?><tr><td class="admin-products-photo"><a class="admin-product-thumb" href="/admin/productos/<?= (int)$p['id'] ?>" aria-label="Editar <?= e($p['name']) ?>"><?php if($p['image']): ?><img src="/<?= e($p['image']) ?>" alt="" loading="lazy" width="56" height="56"><?php else: ?><span class="brand-symbol" aria-hidden="true"></span><?php endif ?></a></td><td><strong><?= e($p['name']) ?></strong><?php if($p['featured']): ?><small>Destacado</small><?php endif ?></td><td><?= e($p['category']??'Sin categoría') ?></td><td><?= $p['mode']==='quote'?'Consultar':money($p['price_cents']) ?></td><td><span class="badge"><?= $p['active']?'Publicado':'Oculto' ?></span></td><td><a href="/admin/productos/<?= $p['id'] ?>" class="text-link">Editar ↗</a></td></tr><?php endforeach ?></tbody></table></div>
    <?php endif ?>
</section>
