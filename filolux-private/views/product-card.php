<?php
$cardPhotos=array_map(fn($photo)=>'/'.$photo, $p['images']??($p['image']?[$p['image']]:[]));
$hasGallery=count($cardPhotos)>1;
?>
<article class="product-card">
    <div class="product-media"<?php if($hasGallery): ?> data-product-photos="<?= e(json_encode($cardPhotos,JSON_THROW_ON_ERROR)) ?>"<?php endif ?>>
        <a class="product-image" href="/producto/<?= (int)$p['id'] ?>">
            <?php if($cardPhotos): ?><img data-card-photo src="<?= e($cardPhotos[0]) ?>" alt="<?= e($p['name']) ?>" loading="lazy" width="600" height="600"><?php else: ?><div class="product-placeholder" aria-label="Foto pendiente"><span><span class="brand-symbol" aria-hidden="true"></span></span><small>HECHO PARA VOS</small></div><?php endif ?>
            <span class="product-tag"><?= $p['mode']==='quote'?'A TU MEDIDA':'POR ENCARGO' ?></span><span class="image-arrow">↗</span>
        </a>
        <?php if($hasGallery): ?>
            <div class="card-photo-controls" data-card-photo-controls hidden>
                <button type="button" class="card-photo-button card-photo-previous" data-card-photo-step="-1" aria-label="Foto anterior de <?= e($p['name']) ?>"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m14 6-6 6 6 6"/></svg></button>
                <button type="button" class="card-photo-button card-photo-next" data-card-photo-step="1" aria-label="Foto siguiente de <?= e($p['name']) ?>"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m10 6 6 6-6 6"/></svg></button>
                <span class="card-photo-count" data-card-photo-count aria-hidden="true">1 / <?= count($cardPhotos) ?></span>
            </div>
            <span class="sr-only" data-card-photo-status role="status" aria-live="polite" aria-atomic="true"></span>
        <?php endif ?>
    </div>
    <div class="product-meta"><span><?= e($p['category']??'Piezas únicas') ?></span><span><?= e($p['lead_time']) ?></span></div>
    <h3><a href="/producto/<?= (int)$p['id'] ?>"><?= e($p['name']) ?></a></h3>
    <p class="price"><?= $p['mode']==='quote'?'Consultanos tu proyecto':money($p['price_cents']) ?></p>
</article>
