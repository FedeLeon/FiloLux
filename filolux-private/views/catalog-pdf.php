<?php
$sections=[];
foreach($products as $p) {
    $name=$p['export_category'];
    if(!isset($sections[$name])) $sections[$name]=['id'=>'categoria-'.count($sections),'name'=>$name,'count'=>0];
    $sections[$name]['count']++;
}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><style>
@page{margin:38px 42px 48px}body{font-family:"DejaVu Sans",sans-serif;color:#29251e;font-size:10px;line-height:1.5}h1{font-size:27px;margin:0 0 8px}h2{font-size:27px;color:#88641d;border-bottom:2px solid #c49a45;padding-bottom:6px;page-break-after:avoid}h3{font-size:15px;margin:18px 0 7px;page-break-after:avoid}p{margin:5px 0}.muted{color:#625b4e}.meta{background:#f4f1e9;padding:10px;page-break-inside:avoid}.photos{margin:10px 0}.photo{display:inline-block;margin:0 6px 6px 0}.photo img{max-width:145px;max-height:112px}.description{white-space:pre-line;overflow-wrap:break-word}.link{font-size:9px;overflow-wrap:break-word;color:#88641d}.product-end{border-bottom:1px solid #ddd5c4;margin:14px 0}a{color:#88641d}
.category-heading{page-break-before:always;page-break-inside:avoid;page-break-after:avoid}.catalog-logo{display:block;width:235px;height:auto;margin:0 0 18px}.catalog-index{margin:22px 0 30px}.catalog-index h2{font-size:19px;border-bottom:1px solid #ddd5c4}.catalog-index ul{list-style:none;margin:0;padding:0}.catalog-index li{page-break-inside:avoid;margin:0;padding:0}.catalog-index a{display:block;padding:7px 0;border-bottom:1px solid #eee8da;font-size:13px;text-decoration:none}.catalog-index .count{font-size:10px;color:#625b4e}.back-to-index{font-size:9px;display:block;margin:4px 0 12px}
</style></head><body>
<img class="catalog-logo" src="<?= e($logo) ?>" alt="<?= e(setting('brand','FiloLux')) ?>">
<h1>Catálogo de productos</h1><p><?= e(setting('brand','FiloLux')) ?> · <?= date('d/m/Y') ?></p>
<p class="muted">Fabricación por encargo. Precios en pesos argentinos (ARS), sin envío. Consultá la disponibilidad y los precios actualizados en la tienda.</p>
<p class="link"><?= e(url('/catalogo')) ?></p>
<?php if(setting('contact_email') || setting('whatsapp')): ?><p class="muted"><?= e(setting('contact_email')) ?> <?= e(setting('whatsapp')) ?></p><?php endif ?>
<?php if($sections): ?>
<div class="catalog-index"><h2 id="indice-categorias">Índice de categorías</h2><ul>
<?php foreach($sections as $section): ?>
<li><a href="#<?= e($section['id']) ?>"><?= e($section['name']) ?> <span class="count">· <?= $section['count'] ?> <?= $section['count']===1?'producto':'productos' ?></span></a></li>
<?php endforeach ?>
</ul></div>
<?php endif ?>
<?php $previous=null; foreach($products as $p): ?>
<?php if($previous!==$p['export_category']): $previous=$p['export_category']; ?><div class="category-heading"><h2 id="<?= e($sections[$previous]['id']) ?>"><?= e($previous) ?></h2><a class="back-to-index" href="#indice-categorias">Volver al índice</a></div><?php endif ?>
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
