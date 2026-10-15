<section class="container section">
    <div class="section-heading"><div><p class="eyebrow">TU CATÁLOGO</p><h1>Productos.</h1></div><a href="/admin/productos/nuevo" class="btn">Nueva pieza +</a></div>
    <nav class="category-buttons admin-product-categories" aria-label="Filtrar productos por categoría">
        <a class="category-button<?= $selectedCategory===0?' active':'' ?>" href="/admin/productos" <?= $selectedCategory===0?'aria-current="true"':'' ?>>Todas las categorías</a>
        <?php foreach($categories as $c): $selected=$selectedCategory===(int)$c['id']; ?>
            <a class="category-button<?= $selected?' active':'' ?>" href="/admin/productos?categoria=<?= (int)$c['id'] ?>" <?= $selected?'aria-current="true"':'' ?>><?= e($c['name']) ?></a>
        <?php endforeach ?>
        <a class="category-button<?= $selectedCategory==='sin_categoria'?' active':'' ?>" href="/admin/productos?categoria=sin_categoria" <?= $selectedCategory==='sin_categoria'?'aria-current="true"':'' ?>>Sin categoría</a>
    </nav>
    <?php if(!$products): ?>
        <?php if($selectedCategory!==0): ?>
            <div class="empty"><h2>No hay productos en esta categoría.</h2><p>Elegí otra categoría o agregá una nueva pieza a tu catálogo.</p><a class="btn secondary" href="/admin/productos">Ver todos los productos ↗</a></div>
        <?php else: ?>
            <div class="empty"><h2>Tu catálogo empieza con una pieza.</h2><p>Subí las fotos, contá su historia y elegí cómo ofrecerla.</p><a class="btn" href="/admin/productos/nuevo">Crear mi primer producto ↗</a></div>
        <?php endif ?>
    <?php else: ?>
        <div class="table-wrap"><table><thead><tr><th>Pieza</th><th>Categoría</th><th>Precio</th><th>Visibilidad</th><th></th></tr></thead><tbody><?php foreach($products as $p): ?><tr><td><strong><?= e($p['name']) ?></strong><?php if($p['featured']): ?><small>Destacado</small><?php endif ?></td><td><?= e($p['category']??'Sin categoría') ?></td><td><?= $p['mode']==='quote'?'Consultar':money($p['price_cents']) ?></td><td><span class="badge"><?= $p['active']?'Publicado':'Oculto' ?></span></td><td><a href="/admin/productos/<?= $p['id'] ?>" class="text-link">Editar ↗</a></td></tr><?php endforeach ?></tbody></table></div>
    <?php endif ?>
</section>
