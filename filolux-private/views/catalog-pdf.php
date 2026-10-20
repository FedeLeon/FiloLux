<!doctype html><html lang="es"><head><meta charset="utf-8"><style>
@page{margin:38px 42px 48px}body{font-family:"DejaVu Sans",sans-serif;color:#29251e;font-size:10px;line-height:1.5}h1{font-size:27px;margin:0 0 8px}h2{font-size:19px;color:#88641d;border-bottom:2px solid #c49a45;padding-bottom:6px;page-break-after:avoid}h3{font-size:15px;margin:18px 0 7px;page-break-after:avoid}p{margin:5px 0}.muted{color:#625b4e}.meta{background:#f4f1e9;padding:10px;page-break-inside:avoid}.photos{margin:10px 0}.photo{display:inline-block;margin:0 6px 6px 0}.photo img{max-width:145px;max-height:112px}.description{white-space:pre-line;overflow-wrap:break-word}.link{font-size:9px;overflow-wrap:break-word;color:#88641d}.product-end{border-bottom:1px solid #ddd5c4;margin:14px 0}a{color:#88641d}
</style></head><body>
<h1><?= e(setting('brand','FiloLux')) ?></h1><p>Catálogo de productos · <?= date('d/m/Y') ?></p>
<p class="muted">Fabricación por encargo. Precios en pesos argentinos (ARS), sin envío. Consultá la disponibilidad y los precios actualizados en la tienda.</p>
<p class="link"><?= e(url('/catalogo')) ?></p>
<?php if(setting('email') || setting('whatsapp')): ?><p class="muted"><?= e(setting('email')) ?> <?= e(setting('whatsapp')) ?></p><?php endif ?>
<?php $previous=null; foreach($products as $p): ?>
<?php if($previous!==$p['export_category']): $previous=$p['export_category']; ?><h2><?= e($previous) ?></h2><?php endif ?>
<h3><?= e($p['name']) ?> <span class="muted">· #<?= (int)$p['id'] ?></span></h3>
<div class="meta"><strong><?= $p['mode']==='buy'?money($p['price_cents']).' ARS':'A medida · Precio a consultar' ?></strong>
<p>Medidas: <?= e($p['dimensions']?:'Consultar') ?> · Fabricación: <?= e($p['lead_time']) ?></p>
<p>Colores: <?= e($p['colors']?:'Consultar opciones') ?></p>
<p>Categorías: <?= e($p['categories']?:'Sin categoría') ?><?= (int)$p['featured']?' · Producto destacado':'' ?></p></div>
<?php if($p['images']): ?><div class="photos"><?php foreach($p['images'] as $image): $jpeg=catalogExportThumbnail($image); if($jpeg): ?><a class="photo" href="<?= e(url('/'.$image)) ?>"><img src="data:image/jpeg;base64,<?= base64_encode($jpeg) ?>" alt="<?= e($p['name']) ?>"></a><?php endif; endforeach ?></div><?php endif ?>
<p class="description"><?= e($p['description']) ?></p>
<p class="link"><a href="<?= e($p['link']) ?>">Ver producto en la tienda: <?= e($p['link']) ?></a></p>
<div class="product-end"></div>
<?php endforeach; if(!$products): ?><p>No hay productos publicados todavía.</p><?php endif ?>
</body></html>
